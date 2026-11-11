<?php
// ── Handlers AJAX inline ──────────────────────────────────────────────────────
// Guardar meta_contactos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_meta_contactos') {
    header('Content-Type: application/json');
    if (file_exists(__DIR__ . '/../../includes/bootstrap.php')) {
        require_once __DIR__ . '/../../includes/bootstrap.php';
    } else { session_start(); }
    $rol = $_SESSION['user_role'] ?? '';
    $permitidos = ['admin','administrador','superadmin','super admin','circunscripcion','estatal','coordinador estatal','coordinador_estatal'];
    if (!in_array(strtolower($rol), $permitidos)) { echo json_encode(['ok'=>false,'msg'=>'Sin permisos']); exit; }
    $id    = intval($_POST['id']    ?? 0);
    $valor = intval($_POST['value'] ?? 0);
    if (!$id) { echo json_encode(['ok'=>false,'msg'=>'ID inválido']); exit; }
    try {
        if (!isset($pdo)) {
            $dbConfigPath = __DIR__ . '/../../includes/database.php';
            $dbConfig = file_exists($dbConfigPath) ? require $dbConfigPath : [
                'host'=>'localhost','database'=>'u881824383_pverde',
                'username'=>'u881824383_proyverde','password'=>'PProyectoverde8#',
                'charset'=>'utf8mb4',
                'options'=>[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
            ];
            $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
            $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options'] ?? []);
        }
        $stGet = $pdo->prepare("SELECT seccion, estado_nombre FROM secciones WHERE id = ?");
        $stGet->execute([$id]);
        $row = $stGet->fetch();
        if (!$row) { echo json_encode(['ok'=>false,'msg'=>'Sección no encontrada']); exit; }
        $stUp = $pdo->prepare(
            "UPDATE secciones_electorales SET meta_contactos = ?
             WHERE nombre_seccion = ?
               AND UPPER(TRIM(estado)) = UPPER(TRIM(?))
               AND activo = 1"
        );
        $stUp->execute([$valor, $row['seccion'], $row['estado_nombre']]);
        if ($stUp->rowCount() === 0) {
            $stIns = $pdo->prepare(
                "INSERT INTO secciones_electorales (nombre_seccion, estado, meta_contactos, activo, fecha_creacion)
                 VALUES (?, ?, ?, 1, NOW())"
            );
            $stIns->execute([$row['seccion'], $row['estado_nombre'], $valor]);
        }
        echo json_encode(['ok'=>true, 'updated'=> $stUp->rowCount()]);
    } catch (Exception $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// Guardar contactos_conseguidos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_contactos') {
    header('Content-Type: application/json');
    if (file_exists(__DIR__ . '/../../includes/bootstrap.php')) {
        require_once __DIR__ . '/../../includes/bootstrap.php';
    } else { session_start(); }
    $rol      = $_SESSION['user_role'] ?? '';
    $permitidos = ['admin','administrador','superadmin','super admin','circunscripcion','estatal','coordinador estatal','coordinador_estatal'];
    if (!in_array(strtolower($rol), $permitidos)) { echo json_encode(['ok'=>false,'msg'=>'Sin permisos']); exit; }
    $id    = intval($_POST['id']    ?? 0);
    $valor = intval($_POST['value'] ?? 0);
    if (!$id) { echo json_encode(['ok'=>false,'msg'=>'ID inválido']); exit; }
    try {
        if (!isset($pdo)) {
            $dbConfigPath = __DIR__ . '/../../includes/database.php';
            $dbConfig = file_exists($dbConfigPath) ? require $dbConfigPath : [
                'host'=>'localhost','database'=>'u881824383_pverde',
                'username'=>'u881824383_proyverde','password'=>'PProyectoverde8#',
                'charset'=>'utf8mb4',
                'options'=>[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
            ];
            $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
            $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options'] ?? []);
        }
        $stGet = $pdo->prepare("SELECT seccion, estado_nombre FROM secciones WHERE id = ?");
        $stGet->execute([$id]);
        $row = $stGet->fetch();
        if (!$row) { echo json_encode(['ok'=>false,'msg'=>'Sección no encontrada']); exit; }
        $stUp = $pdo->prepare(
            "UPDATE secciones_electorales SET contactos_conseguidos = ?
             WHERE nombre_seccion = ?
               AND UPPER(TRIM(estado)) = UPPER(TRIM(?))
               AND activo = 1"
        );
        $stUp->execute([$valor, $row['seccion'], $row['estado_nombre']]);
        if ($stUp->rowCount() === 0) {
            $stIns = $pdo->prepare(
                "INSERT INTO secciones_electorales (nombre_seccion, estado, contactos_conseguidos, activo, fecha_creacion)
                 VALUES (?, ?, ?, 1, NOW())"
            );
            $stIns->execute([$row['seccion'], $row['estado_nombre'], $valor]);
        }
        echo json_encode(['ok'=>true, 'updated'=> $stUp->rowCount()]);
    } catch (Exception $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── Seguridad ──────────────────────────────────────────────
if (file_exists(__DIR__ . '/../../includes/bootstrap.php')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
} else { session_start(); }

$rol = $_SESSION['user_role'] ?? '';
$user_estado = $_SESSION['user_estado'] ?? 'Sonora';
$permitidos = ['admin','administrador','superadmin','super admin', 'circunscripcion', 'estatal', 'coordinador estatal', 'coordinador_estatal'];

if (!in_array(strtolower($rol), $permitidos) || empty($user_estado)) {
    header('Location: /public/index.php');
    exit();
}

// ── Conexión y carga MÍNIMA (solo estados/municipios, sin secciones) ──
$geo_js = '{}';
$munPorEstado = [];
$estado_id = null;

try {
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

    $stEstado = $pdo->prepare("SELECT id, clave, nombre FROM estados WHERE nombre = ? ORDER BY nombre ASC");
    $stEstado->execute([$user_estado]);
    $rowsEstados = $stEstado->fetchAll();

    if (count($rowsEstados) > 0) {
        $estado_id = $rowsEstados[0]['id'];

        $stMun = $pdo->prepare("SELECT estado_id, nombre FROM municipios WHERE estado_id = ? ORDER BY nombre ASC");
        $stMun->execute([$estado_id]);
        $rowsMunicipios = $stMun->fetchAll();

        foreach ($rowsMunicipios as $m) {
            $munPorEstado[$m['estado_id']][] = $m['nombre'];
        }

        $geoData = [];
        foreach ($rowsEstados as $e) {
            $geoData[$e['clave']] = [
                'label'      => $e['nombre'],
                'municipios' => $munPorEstado[$e['id']] ?? [],
            ];
        }
        $geo_js = json_encode($geoData, JSON_UNESCAPED_UNICODE);
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
    <title>Secciones Estatales - GreenBoard Admin</title>
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
        white-space: nowrap; cursor: pointer; user-select: none; transition: color 0.2s;
    }
    .modern-table th:hover { color: var(--primary); }
    .modern-table th i { margin-left: 5px; color: #ccc; }
    .modern-table th i.fa-sort-up,
    .modern-table th i.fa-sort-down { color: var(--primary); }
    .modern-table td {
        padding: 16px 20px; border-bottom: 1px solid var(--gray-200);
        font-size: 14px; color: var(--dark); font-weight: 500;
        vertical-align: middle; transition: background 0.2s;
    }
    .modern-table tr:hover td { background: var(--gray-100); }
    .modern-table tr:last-child td { border-bottom: none; }

    /* ── PROGRESS BAR ── */
    .progress-wrap { min-width: 100px; }
    .progress-bar-bg { height: 8px; border-radius: 4px; background: var(--gray-200); overflow: hidden; margin-top: 4px; }
    .progress-bar-fill { height: 100%; border-radius: 4px; background: linear-gradient(90deg, var(--primary), #4caf50); transition: width 0.5s ease; }
    .progress-label { font-size: 11px; color: var(--text-muted); font-weight: 600; }

    .stat-cell { font-size: 16px; font-weight: 800; color: var(--primary); }
    .stat-cell.neutral { color: var(--dark); }
    .stat-cell-label { font-size: 10px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.5px; }

    /* ── ACTION BUTTONS ── */
    .btn-detalle {
        background: rgba(45,122,62,0.1); color: var(--primary);
        border: none; padding: 8px 18px; border-radius: 8px;
        font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; white-space: nowrap;
    }
    .btn-detalle:hover { background: var(--primary); color: white; }

    .empty-row td { text-align: center; padding: 60px 20px; color: var(--text-muted); font-size: 14px; }

    /* ── PAGINACIÓN ── */
    .pagination {
        display: flex; justify-content: center; align-items: center;
        gap: 6px; margin-top: 25px; flex-wrap: wrap;
    }
    .page-btn {
        background: var(--white); border: 1px solid var(--gray-200);
        color: var(--dark); padding: 8px 14px; border-radius: 8px;
        font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.2s;
        display: flex; align-items: center; justify-content: center;
    }
    .page-btn:hover:not(.disabled) { background: var(--gray-100); border-color: var(--primary); color: var(--primary); }
    .page-btn.active { background: var(--primary); color: var(--white); border-color: var(--primary); }
    .page-btn.disabled { opacity: 0.5; cursor: not-allowed; }
    .page-dots { color: var(--text-muted); font-weight: 600; padding: 0 5px; }

    /* ── MODAL ── */
    .modal {
        display: none; position: fixed; top: 0; left: 0;
        width: 100%; height: 100%; background: rgba(255,255,255,0.9);
        backdrop-filter: blur(5px); z-index: 2000; align-items: center; justify-content: center;
        opacity: 0; transition: opacity 0.3s ease;
    }
    .modal.open { display: flex; opacity: 1; }
    .modal-content {
        background: var(--white); border-radius: 20px; border: 1px solid var(--gray-200);
        box-shadow: 0 25px 50px rgba(0,0,0,0.1); width: 90%; max-width: 760px; padding: 40px;
        position: relative; transform: translateY(30px); opacity: 0;
        transition: all 0.4s ease-out; max-height: 90vh; overflow-y: auto;
    }
    .modal.open .modal-content { transform: translateY(0); opacity: 1; }
    .modal-close {
        position: absolute; top: 20px; right: 20px; background: var(--gray-100); border: none;
        cursor: pointer; width: 36px; height: 36px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 16px; color: var(--text-muted); transition: all 0.2s;
    }
    .modal-close:hover { background: #ffeaea; color: #d32f2f; }
    .modal-title { font-size: 26px; font-weight: 900; margin-bottom: 6px; color: var(--dark); }
    .modal-subtitle { font-size: 13px; color: var(--text-muted); margin-bottom: 30px; }

    .det-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px; }
    .det-item label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); display: block; margin-bottom: 4px; }
    .det-item span { font-size: 15px; font-weight: 700; color: var(--dark); }
    .det-item.full { grid-column: 1 / -1; }

    .det-section-title {
        font-size: 13px; font-weight: 800; text-transform: uppercase;
        letter-spacing: 1px; color: var(--primary); margin: 20px 0 12px;
        padding-bottom: 6px; border-bottom: 2px solid var(--light);
    }

    .field-editable {
        background: var(--gray-100); border-radius: 10px; padding: 14px 16px;
        font-size: 14px; color: var(--dark); min-height: 60px; line-height: 1.5; position: relative;
    }
    .field-editable .edit-btn {
        position: absolute; top: 8px; right: 8px; background: var(--primary); color: white; border: none;
        border-radius: 6px; padding: 4px 10px; font-size: 11px; font-weight: 700; cursor: pointer;
        opacity: 0; transition: opacity 0.2s;
    }
    .field-editable:hover .edit-btn { opacity: 1; }

    .act-stats { display: grid; grid-template-columns: repeat(3,1fr); gap: 12px; }
    .act-stat-card { background: var(--gray-100); border-radius: 12px; padding: 16px; text-align: center; }
    .act-stat-num { font-size: 28px; font-weight: 900; color: var(--primary); }
    .act-stat-lbl { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); margin-top: 2px; }

    .modal-progress-wrap { margin: 10px 0; }
    .modal-progress-bg { height: 12px; border-radius: 6px; background: var(--gray-200); overflow: hidden; }
    .modal-progress-fill { height: 100%; border-radius: 6px; background: linear-gradient(90deg, var(--primary), #4caf50); transition: width 0.6s ease; }
    .modal-progress-label { display: flex; justify-content: space-between; font-size: 12px; font-weight: 700; color: var(--text-muted); margin-top: 4px; }

    .inline-textarea {
        width: 100%; border: 2px solid var(--primary); border-radius: 8px; padding: 10px 12px;
        font-size: 14px; font-family: Arial, sans-serif; resize: vertical; min-height: 80px; outline: none; background: white;
    }
    .save-inline-btn { margin-top: 6px; background: var(--primary); color: white; border: none; border-radius: 8px; padding: 6px 16px; font-size: 13px; font-weight: 700; cursor: pointer; }
    .cancel-inline-btn { margin-top: 6px; margin-left: 6px; background: var(--gray-200); color: var(--dark); border: none; border-radius: 8px; padding: 6px 14px; font-size: 13px; font-weight: 700; cursor: pointer; }

    /* ── Skeleton loader ── */
    .skeleton { background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%); background-size: 200% 100%; animation: shimmer 1.2s infinite; border-radius: 4px; display: inline-block; }
    @keyframes shimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0} }

    @media (max-width: 768px) {
        .container { padding-left: 15px; padding-right: 15px; }
        .main-title { font-size: 36px; }
        .stats-row { grid-template-columns: 1fr; }
        .det-grid { grid-template-columns: 1fr; }
        .act-stats { grid-template-columns: 1fr 1fr; }
    }

    /* ── SEARCHABLE DROPDOWN SECCIÓN ── */
    .sec-dropdown-wrap { position: relative; width: 100%; }
    .sec-dropdown-input { width: 100%; padding: 12px 38px 12px 15px; border-radius: 10px; border: 2px solid var(--gray-200); background: var(--gray-100); font-size: 14px; font-weight: 600; color: var(--dark); transition: all 0.3s; outline: none; font-family: Arial, sans-serif; cursor: pointer; box-sizing: border-box; }
    .sec-dropdown-input:focus { border-color: var(--primary); background: var(--white); }
    .sec-dropdown-input::placeholder { color: #aaa; font-weight: 400; }
    .sec-dropdown-arrow { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); pointer-events: none; color: var(--text-muted); font-size: 12px; transition: transform 0.2s; }
    .sec-dropdown-wrap.open .sec-dropdown-arrow { transform: translateY(-50%) rotate(180deg); }
    .sec-dropdown-list { display: none; position: absolute; top: calc(100% + 4px); left: 0; right: 0; background: var(--white); border: 2px solid var(--primary); border-radius: 10px; z-index: 9999; max-height: 220px; overflow: hidden; box-shadow: 0 8px 24px rgba(0,0,0,0.12); flex-direction: column; }
    .sec-dropdown-wrap.open .sec-dropdown-list { display: flex; }
    .sec-dropdown-search { padding: 10px 12px; border: none; border-bottom: 1px solid var(--gray-200); font-size: 13px; outline: none; font-family: Arial, sans-serif; color: var(--dark); background: var(--gray-100); flex-shrink: 0; }
    .sec-dropdown-search:focus { background: var(--white); }
    .sec-dropdown-options { overflow-y: auto; flex: 1; }
    .sec-dropdown-option { padding: 10px 14px; font-size: 13px; font-weight: 600; cursor: pointer; color: var(--dark); transition: background 0.15s; font-family: Arial, sans-serif; }
    .sec-dropdown-option:hover, .sec-dropdown-option.focused { background: var(--light); color: var(--primary); }
    .sec-dropdown-option.selected { background: rgba(45,122,62,0.1); color: var(--primary); }
    .sec-dropdown-empty { padding: 14px; text-align: center; font-size: 13px; color: var(--text-muted); font-style: italic; }
    .sec-dropdown-loading { padding: 14px; text-align: center; font-size: 13px; color: var(--text-muted); }
    </style>
</head>
<body>

<?php
if (file_exists(__DIR__ . '/../../includes/components/sidebar_ce.php')) {
    include __DIR__ . '/../../includes/components/sidebar_ce.php';
}
?>

<div class="container" id="mainContainer">
    <div class="design-text">SECCIONES ELECTORALES</div>

    <div class="page-header">
        <div>
            <div class="ai-badge"><i class="fas fa-layer-group"></i> &nbsp; Coordinación Estatal</div>
            <h1 class="main-title">Secciones</h1>
            <p class="description">Gestión y seguimiento por sección electoral &middot; Estado de <strong><?= htmlspecialchars($user_estado) ?></strong>.</p>
        </div>
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
            <label class="form-label">Municipio</label>
            <select class="form-input-styled" id="filtroMunicipio" onchange="aplicarFiltros()">
                <option value="">Todos los municipios</option>
                <?php
                if (isset($munPorEstado) && isset($estado_id)) {
                    foreach ($munPorEstado[$estado_id] as $mun) {
                        echo '<option value="' . htmlspecialchars($mun) . '">' . htmlspecialchars($mun) . '</option>';
                    }
                }
                ?>
            </select>
        </div>
        <div class="filter-group">
            <label class="form-label">Buscar</label>
            <input type="text" class="form-input-styled" id="filtroBuscar" placeholder="Número de sección…" oninput="debounceFiltros()">
        </div>
        <button class="btn-secondary" onclick="limpiarFiltros()">
            <i class="fas fa-times"></i> Limpiar
        </button>
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
                        <th onclick="ordenarTabla('nombre_seccion')">Nombre de la Sección <i class="fas fa-sort" id="sort-nombre_seccion"></i></th>
                        <th onclick="ordenarTabla('lista_nominal')">Lista Nominal <i class="fas fa-sort" id="sort-lista_nominal"></i></th>
                        <th onclick="ordenarTabla('meta_contactos')">Meta de Contactos <i class="fas fa-sort" id="sort-meta_contactos"></i></th>
                        <th onclick="ordenarTabla('contactos_conseguidos')">Contactos Conseguidos <i class="fas fa-sort" id="sort-contactos_conseguidos"></i></th>
                        <th onclick="ordenarTabla('barra_avance')">Barra de Avance <i class="fas fa-sort" id="sort-barra_avance"></i></th>
                        <th onclick="ordenarTabla('eventos_realizados')">Eventos Realizados <i class="fas fa-sort" id="sort-eventos_realizados"></i></th>
                        <th onclick="ordenarTabla('metas_votos')">Metas de Votos <i class="fas fa-sort" id="sort-metas_votos"></i></th>
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

<!-- ══ MODAL DETALLE ══ -->
<div class="modal" id="modal-det">
    <div class="modal-content">
        <button class="modal-close" onclick="cerrarModal('modal-det')"><i class="fas fa-times"></i></button>
        <h2 class="modal-title" id="det-titulo">—</h2>
        <div class="modal-subtitle" id="det-ubicacion">—</div>

        <div class="det-grid">
            <div class="det-item"><label>Sección</label><span id="det-seccion">—</span></div>
            <div class="det-item"><label>Municipio</label><span id="det-municipio">—</span></div>
            <div class="det-item"><label>Distrito Federal</label><span id="det-df">—</span></div>
            <div class="det-item"><label>Distrito Local</label><span id="det-dl">—</span></div>
            <div class="det-item"><label>Región Asignada</label><span id="det-region">—</span></div>
            <div class="det-item"><label>Zona Asignada</label><span id="det-zona">—</span></div>
        </div>

        <div class="det-section-title"><i class="fas fa-users"></i> Lista Nominal</div>
        <div class="det-grid" style="margin-bottom:16px;grid-template-columns:1fr 1fr 1fr;">
            <div class="det-item">
                <label><i class="fas fa-users" style="color:#6b7280;margin-right:4px;"></i> TOTAL</label>
                <span id="det-padron-electoral" style="font-size:22px;font-weight:900;color:#374151;">—</span>
            </div>
            <div class="det-item">
                <label><i class="fas fa-mars" style="color:#3b82f6;margin-right:4px;"></i> Hombres</label>
                <span id="det-lista-hombres" style="font-size:22px;font-weight:900;color:#3b82f6;">—</span>
            </div>
            <div class="det-item">
                <label><i class="fas fa-venus" style="color:#ec4899;margin-right:4px;"></i> Mujeres</label>
                <span id="det-lista-mujeres" style="font-size:22px;font-weight:900;color:#ec4899;">—</span>
            </div>
        </div>

        <div class="det-section-title"><i class="fas fa-address-book"></i> Contactos y Avance</div>
        <div class="det-grid">
            <div class="det-item">
                <label>Meta de Contactos</label>
                <div style="display:flex;align-items:center;gap:8px;margin-top:4px;">
                    <span id="det-meta-contactos" style="font-size:20px;font-weight:900;color:var(--dark);">—</span>
                    <button id="btn-edit-meta" onclick="activarEdicionMeta()"
                        style="background:rgba(45,122,62,0.1);color:var(--primary);border:none;border-radius:6px;padding:4px 10px;font-size:11px;font-weight:700;cursor:pointer;"
                        onmouseover="this.style.background='var(--primary)';this.style.color='white'"
                        onmouseout="this.style.background='rgba(45,122,62,0.1)';this.style.color='var(--primary)'">
                        <i class="fas fa-pencil-alt"></i> Editar
                    </button>
                </div>
                <div id="meta-edit-wrap" style="display:none;margin-top:8px;flex-direction:column;gap:6px;">
                    <div style="display:flex;align-items:center;gap:6px;">
                        <span style="font-size:11px;font-weight:700;color:var(--text-muted);white-space:nowrap;">% de lista:</span>
                        <input type="number" id="input-meta-pct" min="0" max="100" step="0.1" placeholder="12"
                            oninput="recalcularMetaDesde('pct')"
                            style="width:70px;padding:6px 10px;border:2px solid #e2e8f0;border-radius:8px;font-size:14px;font-weight:700;outline:none;font-family:Arial,sans-serif;">
                        <span style="font-size:13px;color:var(--text-muted);">%</span>
                        <span style="font-size:11px;color:var(--text-muted);margin:0 4px;">→</span>
                        <span style="font-size:11px;font-weight:700;color:var(--text-muted);white-space:nowrap;">o número:</span>
                        <input type="number" id="input-meta" min="0" oninput="recalcularMetaDesde('num')"
                            style="width:100px;padding:6px 10px;border:2px solid var(--primary);border-radius:8px;font-size:14px;font-weight:700;outline:none;font-family:Arial,sans-serif;">
                    </div>
                    <div style="display:flex;gap:6px;">
                        <button onclick="guardarMeta()" style="background:var(--primary);color:white;border:none;border-radius:8px;padding:7px 14px;font-size:13px;font-weight:700;cursor:pointer;"><i class="fas fa-check"></i> Guardar</button>
                        <button onclick="cancelarEdicionMeta()" style="background:#eee;color:#555;border:none;border-radius:8px;padding:7px 12px;font-size:13px;font-weight:700;cursor:pointer;"><i class="fas fa-times"></i></button>
                    </div>
                </div>
            </div>
            <div class="det-item">
                <label>Contactos Conseguidos</label>
                <div style="display:flex;align-items:center;gap:8px;margin-top:4px;">
                    <span id="det-contactos" style="font-size:20px;font-weight:900;color:var(--primary);">—</span>
                    <small style="color:#aaa;font-size:11px;font-style:italic;">(automático)</small>
                </div>
            </div>
        </div>
        <div class="modal-progress-wrap">
            <div class="modal-progress-bg"><div class="modal-progress-fill" id="det-progress-fill" style="width:0%"></div></div>
            <div class="modal-progress-label"><span id="det-progress-pct">0%</span><span id="det-progress-rem">0 restantes</span></div>
        </div>
        <div class="det-grid" style="margin-top:16px;">
            <div class="det-item"><label>Eventos Realizados</label><span id="det-eventos">—</span></div>
            <div class="det-item"><label>Metas de Votos</label><span id="det-votos">—</span></div>
        </div>

        <div class="det-section-title"><i class="fas fa-edit"></i> Campos Abiertos</div>
        <div style="margin-bottom:14px;">
            <label class="form-label">Principales Necesidades</label>
            <div class="field-editable" id="det-necesidades"><span class="content-text">—</span><button class="edit-btn" onclick="activarEdicion('necesidades','det-necesidades')">Editar</button></div>
        </div>
        <div style="margin-bottom:14px;">
            <label class="form-label">Enfoque</label>
            <div class="field-editable" id="det-enfoque"><span class="content-text">—</span><button class="edit-btn" onclick="activarEdicion('enfoque','det-enfoque')">Editar</button></div>
        </div>
        <div style="margin-bottom:14px;">
            <label class="form-label">Perfil Socioeconómico</label>
            <div class="field-editable" id="det-perfil"><span class="content-text">—</span><button class="edit-btn" onclick="activarEdicion('perfil_socioeconomico','det-perfil')">Editar</button></div>
        </div>

        <div class="det-section-title"><i class="fas fa-bolt"></i> Activaciones Totales / Eventos</div>
        <div class="act-stats">
            <div class="act-stat-card"><div class="act-stat-num" id="det-act-total">—</div><div class="act-stat-lbl">Total</div></div>
            <div class="act-stat-card"><div class="act-stat-num" id="det-act-con">—</div><div class="act-stat-lbl">Con Candidato</div></div>
            <div class="act-stat-card"><div class="act-stat-num" id="det-act-sin">—</div><div class="act-stat-lbl">Sin Candidato</div></div>
        </div>

        <div style="display:flex; gap:10px; margin-top:30px; flex-wrap:wrap;">
            <button class="btn-primary-action" id="det-btn-edit"><i class="fas fa-pen"></i> Editar Sección</button>
            <button class="btn-secondary" onclick="cerrarModal('modal-det')"><i class="fas fa-times"></i> Cerrar</button>
        </div>
    </div>
</div>

<!-- ══ MODAL FORMULARIO ══ -->
<div class="modal" id="modal-form">
    <div class="modal-content">
        <button class="modal-close" onclick="cerrarModal('modal-form')"><i class="fas fa-times"></i></button>
        <h2 class="modal-title" id="form-titulo">Editar Sección</h2>
        <div class="modal-subtitle">Complete los datos de la sección electoral</div>

        <input type="hidden" id="form-id">
        <input type="hidden" id="form-sec-id">

        <div class="det-section-title"><i class="fas fa-map-marker-alt"></i> Ubicación</div>
        <div class="det-grid">
            <div class="det-item" style="grid-column: 1 / -1;">
                <label class="form-label">Sección Electoral <small style="color:#aaa;font-weight:400;">(solo lectura)</small></label>
                <input type="hidden" id="f-nombre">
                <input type="text" class="form-input-styled" id="sec-display" readonly style="background:#f0f0f0;cursor:not-allowed;opacity:0.75;">
            </div>
            <div class="det-item" style="display:none;">
                <label class="form-label">Estado *</label>
                <select class="form-input-styled" id="f-estado" disabled>
                    <option value="<?= htmlspecialchars($user_estado) ?>"><?= htmlspecialchars($user_estado) ?></option>
                </select>
            </div>
            <div class="det-item" style="grid-column: 1 / -1;">
                <label class="form-label">Municipio <small style="color:#aaa;font-weight:400;">(solo lectura)</small></label>
                <input type="text" class="form-input-styled" id="f-municipio-display" readonly style="background:#f0f0f0;cursor:not-allowed;opacity:0.75;">
                <input type="hidden" id="f-municipio">
            </div>
            <div class="det-item"><label class="form-label">Distrito Federal</label><input type="text" class="form-input-styled" id="f-df" readonly style="background:#f0f0f0;cursor:not-allowed;opacity:0.75;"></div>
            <div class="det-item"><label class="form-label">Distrito Local</label><input type="text" class="form-input-styled" id="f-dl" readonly style="background:#f0f0f0;cursor:not-allowed;opacity:0.75;"></div>
            <div class="det-item"><label class="form-label">Región Asignada</label><input type="text" class="form-input-styled" id="f-region" readonly style="background:#f0f0f0;cursor:not-allowed;opacity:0.75;"></div>
            <div class="det-item"><label class="form-label">Zona Asignada</label><input type="text" class="form-input-styled" id="f-zona" readonly style="background:#f0f0f0;cursor:not-allowed;opacity:0.75;"></div>
            <div class="det-item"><label class="form-label">Lista Nominal <small style="color:#aaa;font-weight:400;">(solo lectura)</small></label><input type="number" class="form-input-styled" id="f-nominal" readonly style="background:#f0f0f0;cursor:not-allowed;opacity:0.75;"></div>
        </div>

        <div class="det-section-title"><i class="fas fa-bullseye"></i> Metas y Resultados</div>
        <div class="det-grid">
            <div class="det-item"><label class="form-label">Meta de Contactos</label><input type="number" class="form-input-styled" id="f-meta-cont" placeholder="0"></div>
            <div class="det-item"><label class="form-label">Contactos Conseguidos <small style="color:#aaa;font-weight:400;">(solo lectura)</small></label><input type="number" class="form-input-styled" id="f-cont" readonly style="background:#f0f0f0;cursor:not-allowed;opacity:0.75;"></div>
            <div class="det-item"><label class="form-label">Eventos Realizados <small style="color:#aaa;font-weight:400;">(auto)</small></label><input type="number" class="form-input-styled" id="f-eventos" readonly style="background:#f0f0f0;cursor:not-allowed;opacity:0.75;"></div>
            <div class="det-item"><label class="form-label">Metas de Votos</label><input type="number" class="form-input-styled" id="f-votos" placeholder="0"></div>
            <div class="det-item"><label class="form-label">Activaciones Totales <small style="color:#aaa;font-weight:400;">(auto)</small></label><input type="number" class="form-input-styled" id="f-act-total" readonly style="background:#f0f0f0;cursor:not-allowed;opacity:0.75;"></div>
            <div class="det-item"><label class="form-label">Con Candidato <small style="color:#aaa;font-weight:400;">(auto)</small></label><input type="number" class="form-input-styled" id="f-act-con" readonly style="background:#f0f0f0;cursor:not-allowed;opacity:0.75;"></div>
            <div class="det-item"><label class="form-label">Sin Candidato <small style="color:#aaa;font-weight:400;">(auto)</small></label><input type="number" class="form-input-styled" id="f-act-sin" readonly style="background:#f0f0f0;cursor:not-allowed;opacity:0.75;"></div>
        </div>

        <div class="det-section-title"><i class="fas fa-align-left"></i> Campos Abiertos</div>
        <div style="margin-bottom:14px;"><label class="form-label">Principales Necesidades</label><textarea class="form-input-styled" id="f-necesidades" rows="3" placeholder="Describe las principales necesidades…"></textarea></div>
        <div style="margin-bottom:14px;"><label class="form-label">Enfoque</label><textarea class="form-input-styled" id="f-enfoque" rows="3" placeholder="Enfoque estratégico…"></textarea></div>
        <div style="margin-bottom:14px;"><label class="form-label">Perfil Socioeconómico</label><textarea class="form-input-styled" id="f-perfil" rows="3" placeholder="Describe el perfil socioeconómico…"></textarea></div>

        <div style="display:flex; gap:10px; margin-top:24px; flex-wrap:wrap;">
            <button class="btn-primary-action" id="form-save-btn" onclick="guardarSeccion()"><i class="fas fa-save"></i> Guardar</button>
            <button class="btn-secondary" onclick="cerrarModal('modal-form')"><i class="fas fa-times"></i> Cancelar</button>
        </div>
    </div>
</div>

<script>
// ══════════════════════════════════════════════════════════
//  CONFIGURACIÓN
// ══════════════════════════════════════════════════════════
const API       = '/api/secciones/secciones_api.php';
const ESTADO    = <?php echo json_encode($user_estado); ?>;
const PER_PAGE  = 50;

// Estado de la tabla
let currentPage  = 1;
let totalRows    = 0;
let sortCol      = 'nombre_seccion';
let sortAsc      = true;

// Cache de filas de la página actual (para modal de detalle y edición)
let pageCache    = {};   // { id: rowObject }
let seccionActualId = null;

// Debounce para búsqueda
let debounceTimer = null;

// ══════════════════════════════════════════════════════════
//  HELPERS
// ══════════════════════════════════════════════════════════
function fmt(n){ return n != null && n !== '' ? parseInt(n).toLocaleString('es-MX') : '—'; }
function pct(conseguidos, meta){
    if(!meta || meta == 0) return 0;
    return Math.min(100, Math.round((conseguidos / meta) * 100));
}

// ══════════════════════════════════════════════════════════
//  FETCH CON PAGINACIÓN SERVER-SIDE
// ══════════════════════════════════════════════════════════
async function fetchTabla() {
    const tbody = document.getElementById('tablaBody');
    tbody.innerHTML = '<tr class="empty-row"><td colspan="8"><i class="fas fa-spinner fa-spin"></i> Cargando…</td></tr>';

    const municipio = document.getElementById('filtroMunicipio').value;
    const buscar    = document.getElementById('filtroBuscar').value.trim();
    const dir       = sortAsc ? 'asc' : 'desc';

    const params = new URLSearchParams({
        action:   'list',
        estado:   ESTADO,
        page:     currentPage,
        per_page: PER_PAGE,
        sort:     sortCol,
        dir:      dir,
    });
    if (municipio) params.append('municipio', municipio);
    if (buscar)    params.append('buscar', buscar);

    try {
        const res  = await fetch(`${API}?${params}`);
        const data = await res.json();

        if (!data.ok) {
            tbody.innerHTML = `<tr class="empty-row"><td colspan="8"><i class="fas fa-exclamation-triangle"></i> Error: ${data.msg}</td></tr>`;
            return;
        }

        totalRows = data.total;

        // Actualizar stats
        document.getElementById('stat-total').textContent        = fmt(data.total);
        document.getElementById('stat-contactos').textContent    = fmt(data.stat_contactos);
        document.getElementById('stat-activaciones').textContent = fmt(data.stat_activaciones);
        document.getElementById('tableCount').textContent        = `${fmt(data.total)} sección(es)`;

        // Guardar en cache para modal
        pageCache = {};
        data.rows.forEach(s => { pageCache[s.id] = s; });

        if (!data.rows.length) {
            tbody.innerHTML = '<tr class="empty-row"><td colspan="8"><i class="fas fa-search"></i> Sin resultados</td></tr>';
            renderPaginacion();
            return;
        }

        tbody.innerHTML = data.rows.map(s => {
            const nominal = parseInt(s.lista_nominal) || 0;
            const meta    = (parseInt(s.meta_contactos) || 0) || Math.round(nominal * 0.12);
            const cons    = parseInt(s.contactos_conseguidos) || 0;
            const p       = pct(cons, meta);
            return `<tr>
                <td><strong>${s.nombre_seccion || '—'}</strong></td>
                <td>${fmt(s.lista_nominal)}</td>
                <td>${fmt(meta)}</td>
                <td class="stat-cell">${fmt(s.contactos_conseguidos)}</td>
                <td>
                    <div class="progress-wrap">
                        <div class="progress-label">${p}%</div>
                        <div class="progress-bar-bg"><div class="progress-bar-fill" style="width:${p}%"></div></div>
                    </div>
                </td>
                <td>${fmt(s.eventos_realizados)}</td>
                <td class="stat-cell">${fmt(s.metas_votos)}</td>
                <td><button class="btn-detalle" onclick="verDetalle(${s.id})"><i class="fas fa-eye"></i> Detalles</button></td>
            </tr>`;
        }).join('');

        renderPaginacion();

    } catch (err) {
        tbody.innerHTML = `<tr class="empty-row"><td colspan="8"><i class="fas fa-exclamation-triangle"></i> Error de red</td></tr>`;
        console.error('fetchTabla:', err);
    }
}

// ══════════════════════════════════════════════════════════
//  FILTROS Y ORDENAMIENTO
// ══════════════════════════════════════════════════════════
function aplicarFiltros() {
    currentPage = 1;
    fetchTabla();
}

function debounceFiltros() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(aplicarFiltros, 350);
}

function limpiarFiltros() {
    document.getElementById('filtroMunicipio').value = '';
    document.getElementById('filtroBuscar').value = '';
    aplicarFiltros();
}

function ordenarTabla(col) {
    if (sortCol === col) {
        sortAsc = !sortAsc;
    } else {
        sortCol = col;
        sortAsc = true;
    }
    document.querySelectorAll('.modern-table th i.fas').forEach(icon => { icon.className = 'fas fa-sort'; });
    const activeIcon = document.getElementById('sort-' + col);
    if (activeIcon) activeIcon.className = sortAsc ? 'fas fa-sort-up' : 'fas fa-sort-down';
    currentPage = 1;
    fetchTabla();
}

// ══════════════════════════════════════════════════════════
//  PAGINACIÓN
// ══════════════════════════════════════════════════════════
function renderPaginacion() {
    const container = document.getElementById('pagination-container');
    const totalPages = Math.ceil(totalRows / PER_PAGE);
    if (totalPages <= 1) { container.innerHTML = ''; return; }

    let html = '';
    html += `<button class="page-btn ${currentPage === 1 ? 'disabled' : ''}"
             onclick="${currentPage === 1 ? '' : `cambiarPagina(${currentPage - 1})`}"><i class="fas fa-chevron-left"></i></button>`;

    let start = Math.max(1, currentPage - 2);
    let end   = Math.min(totalPages, currentPage + 2);

    if (start > 1) {
        html += `<button class="page-btn" onclick="cambiarPagina(1)">1</button>`;
        if (start > 2) html += `<span class="page-dots">...</span>`;
    }
    for (let i = start; i <= end; i++) {
        html += `<button class="page-btn ${i === currentPage ? 'active' : ''}" onclick="cambiarPagina(${i})">${i}</button>`;
    }
    if (end < totalPages) {
        if (end < totalPages - 1) html += `<span class="page-dots">...</span>`;
        html += `<button class="page-btn" onclick="cambiarPagina(${totalPages})">${totalPages}</button>`;
    }
    html += `<button class="page-btn ${currentPage === totalPages ? 'disabled' : ''}"
             onclick="${currentPage === totalPages ? '' : `cambiarPagina(${currentPage + 1})`}"><i class="fas fa-chevron-right"></i></button>`;

    container.innerHTML = html;
}

function cambiarPagina(page) {
    const totalPages = Math.ceil(totalRows / PER_PAGE);
    if (page < 1 || page > totalPages) return;
    currentPage = page;
    fetchTabla();
    document.querySelector('.table-section').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// ══════════════════════════════════════════════════════════
//  DETALLE MODAL — usa pageCache, si no está llama API
// ══════════════════════════════════════════════════════════
async function verDetalle(id) {
    seccionActualId = id;
    let s = pageCache[id];

    // Si no está en cache (caso raro: modal abierto desde fuera de página), fetch por id
    if (!s) {
        document.getElementById('modal-det').classList.add('open');
        document.getElementById('det-titulo').textContent = 'Cargando…';
        try {
            const res  = await fetch(`${API}?action=get_by_num&seccion=${id}&estado=${encodeURIComponent(ESTADO)}`);
            const data = await res.json();
            s = data.seccion;
        } catch(e) { return; }
    }

    poblarModal(s);
    document.getElementById('modal-det').classList.add('open');
}

function poblarModal(s) {
    document.getElementById('det-titulo').textContent    = s.nombre_seccion || '—';
    document.getElementById('det-ubicacion').textContent = [s.estado, s.municipio].filter(Boolean).join(' › ') || '—';
    document.getElementById('det-seccion').textContent   = s.nombre_seccion || '—';
    document.getElementById('det-municipio').textContent = s.municipio || '—';
    document.getElementById('det-df').textContent        = s.distrito_federal || '—';
    document.getElementById('det-dl').textContent        = s.distrito_local || '—';
    document.getElementById('det-region').textContent    = s.region_asignada || '—';
    document.getElementById('det-zona').textContent      = s.zona_asignada || '—';

    const total = parseInt(s.lista_nominal) > 0
        ? parseInt(s.lista_nominal)
        : (parseInt(s.lista_hombres||0) + parseInt(s.lista_mujeres||0));
    document.getElementById('det-padron-electoral').textContent = fmt(total);
    document.getElementById('det-lista-hombres').textContent    = fmt(parseInt(s.lista_hombres)||0);
    document.getElementById('det-lista-mujeres').textContent    = fmt(parseInt(s.lista_mujeres)||0);

    let meta = parseInt(s.meta_contactos) || 0;
    const cons = parseInt(s.contactos_conseguidos) || 0;
    if (meta === 0 && parseInt(s.lista_nominal) > 0) {
        meta = Math.round(parseInt(s.lista_nominal) * 0.12);
        // Guardar auto-meta en background
        (async () => {
            const fd = new FormData();
            fd.append('action', 'save_meta_contactos');
            fd.append('id',     seccionActualId);
            fd.append('value',  meta);
            const res = await fetch(window.location.pathname, { method:'POST', body:fd });
            const data = await res.json();
            if (data.ok && pageCache[seccionActualId]) { pageCache[seccionActualId].meta_contactos = meta; }
        })();
    }

    const p = pct(cons, meta);
    document.getElementById('det-meta-contactos').textContent  = fmt(meta);
    document.getElementById('det-contactos').textContent       = fmt(cons);
    document.getElementById('det-progress-fill').style.width   = p + '%';
    document.getElementById('det-progress-pct').textContent    = p + '%';
    document.getElementById('det-progress-rem').textContent    = fmt(Math.max(0, meta - cons)) + ' restantes';
    document.getElementById('det-eventos').textContent = fmt(s.eventos_realizados);
    document.getElementById('det-votos').textContent   = fmt(s.metas_votos);

    setFieldEditable('det-necesidades', s.principales_necesidades);
    setFieldEditable('det-enfoque',     s.enfoque);
    setFieldEditable('det-perfil',      s.perfil_socioeconomico);

    document.getElementById('det-act-total').textContent = fmt(s.activaciones_totales);
    document.getElementById('det-act-con').textContent   = fmt(s.con_candidato);
    document.getElementById('det-act-sin').textContent   = fmt(s.sin_candidato);

    document.getElementById('det-btn-edit').onclick = () => { cerrarModal('modal-det'); abrirModalEditar(seccionActualId); };
}

// ══ EDICIÓN INLINE META ══
function activarEdicionMeta() {
    const s = pageCache[seccionActualId];
    const nominal = parseInt(s?.lista_nominal) || 0;
    let val = (parseInt(s?.meta_contactos) || 0) || Math.round(nominal * 0.12);
    const pctVal = nominal > 0 ? parseFloat(((val / nominal) * 100).toFixed(1)) : 12;
    document.getElementById('input-meta').value     = val;
    document.getElementById('input-meta-pct').value = pctVal;
    document.getElementById('meta-edit-wrap').style.display = 'flex';
    document.getElementById('btn-edit-meta').style.display  = 'none';
    document.getElementById('input-meta-pct').focus();
}

function recalcularMetaDesde(origen) {
    const s = pageCache[seccionActualId];
    const nominal = parseInt(s?.lista_nominal) || 0;
    if (origen === 'pct') {
        const pctVal = parseFloat(document.getElementById('input-meta-pct').value) || 0;
        document.getElementById('input-meta').value = Math.round(nominal * pctVal / 100);
    } else {
        const numVal = parseInt(document.getElementById('input-meta').value) || 0;
        document.getElementById('input-meta-pct').value = nominal > 0 ? parseFloat(((numVal / nominal) * 100).toFixed(1)) : 0;
    }
}

function cancelarEdicionMeta() {
    document.getElementById('meta-edit-wrap').style.display = 'none';
    document.getElementById('btn-edit-meta').style.display  = 'inline-flex';
}

async function guardarMeta() {
    const valor = parseInt(document.getElementById('input-meta').value) || 0;
    const fd = new FormData();
    fd.append('action', 'save_meta_contactos');
    fd.append('id',     seccionActualId);
    fd.append('value',  valor);
    try {
        const res  = await fetch(window.location.pathname, { method:'POST', body: fd });
        const data = await res.json();
        if (!data.ok) throw new Error(data.msg || 'Error');
        if (pageCache[seccionActualId]) pageCache[seccionActualId].meta_contactos = valor;
        document.getElementById('det-meta-contactos').textContent = fmt(valor);
        const cons = parseInt(pageCache[seccionActualId]?.contactos_conseguidos) || 0;
        const p    = pct(cons, valor);
        document.getElementById('det-progress-fill').style.width = p + '%';
        document.getElementById('det-progress-pct').textContent  = p + '%';
        document.getElementById('det-progress-rem').textContent  = fmt(Math.max(0, valor - cons)) + ' restantes';
        cancelarEdicionMeta();
    } catch(err) { alert('Error al guardar: ' + err.message); }
}

// ══════════════════════════════════════════════════════════
//  EDICIÓN INLINE CAMPOS ABIERTOS
// ══════════════════════════════════════════════════════════
const fieldMap = {
    'necesidades':           'principales_necesidades',
    'enfoque':               'enfoque',
    'perfil':                'perfil_socioeconomico',
    'perfil_socioeconomico': 'perfil_socioeconomico',
};

function setFieldEditable(divId, value) {
    const div = document.getElementById(divId);
    div.innerHTML = `<span class="content-text">${value || '<em style="color:var(--text-muted)">Sin información</em>'}</span>
        <button class="edit-btn" onclick="activarEdicion('${divId.replace('det-','')}','${divId}')">Editar</button>`;
}

function activarEdicion(field, divId) {
    const div = document.getElementById(divId);
    const s = pageCache[seccionActualId];
    const dbField = fieldMap[field] || field;
    const actual = s ? (s[dbField] || '') : '';
    div.innerHTML = `
        <textarea class="inline-textarea" id="inline-ta-${field}">${actual}</textarea><br>
        <button class="save-inline-btn" onclick="guardarInline('${field}','${divId}')"><i class="fas fa-check"></i> Guardar</button>
        <button class="cancel-inline-btn" onclick="setFieldEditable('${divId}', document.getElementById('inline-ta-${field}').defaultValue)">Cancelar</button>`;
}

async function guardarInline(field, divId) {
    const ta = document.getElementById('inline-ta-' + field);
    const valor = ta.value;
    const dbField = fieldMap[field] || field;
    const fd = new FormData();
    fd.append('action', 'update_field');
    fd.append('id',     seccionActualId);
    fd.append('field',  dbField);
    fd.append('value',  valor);
    try {
        const res  = await fetch(API, { method:'POST', body: fd });
        const data = await res.json();
        if (!data.ok) throw new Error(data.msg || 'Error');
        if (pageCache[seccionActualId]) pageCache[seccionActualId][dbField] = valor;
        setFieldEditable(divId, valor);
    } catch(err) { alert('Error al guardar: ' + err.message); }
}

// ══════════════════════════════════════════════════════════
//  MODAL EDITAR SECCIÓN
// ══════════════════════════════════════════════════════════
function abrirModalEditar(id) {
    const s = pageCache[id];
    if (!s) return;
    document.getElementById('form-titulo').textContent        = 'Editar Sección: ' + s.nombre_seccion;
    document.getElementById('form-id').value                  = s.se_id || '';
    document.getElementById('form-sec-id').value              = s.id || '';
    document.getElementById('f-nombre').value                 = s.nombre_seccion || '';
    document.getElementById('sec-display').value              = 'Sección ' + (s.nombre_seccion || '—');
    document.getElementById('f-municipio').value              = s.municipio || '';
    document.getElementById('f-municipio-display').value      = s.municipio || '—';
    document.getElementById('f-df').value                     = s.distrito_federal || '';
    document.getElementById('f-dl').value                     = s.distrito_local || '';
    document.getElementById('f-region').value                 = s.region_asignada || '';
    document.getElementById('f-zona').value                   = s.zona_asignada || '';
    document.getElementById('f-nominal').value                = s.lista_nominal || '';
    document.getElementById('f-meta-cont').value              = s.meta_contactos || '';
    document.getElementById('f-cont').value                   = s.contactos_conseguidos || '';
    document.getElementById('f-eventos').value                = s.eventos_realizados || '';
    document.getElementById('f-votos').value                  = s.metas_votos || '';
    document.getElementById('f-act-total').value              = s.activaciones_totales || '';
    document.getElementById('f-act-con').value                = s.con_candidato || '';
    document.getElementById('f-act-sin').value                = s.sin_candidato || '';
    document.getElementById('f-necesidades').value            = s.principales_necesidades || '';
    document.getElementById('f-enfoque').value                = s.enfoque || '';
    document.getElementById('f-perfil').value                 = s.perfil_socioeconomico || '';
    document.getElementById('modal-form').classList.add('open');
}

async function guardarSeccion() {
    const btn   = document.getElementById('form-save-btn');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando…';
    const seId  = document.getElementById('form-id').value;
    const secId = document.getElementById('form-sec-id').value;
    const fd    = new FormData();
    const camposEditables = {
        meta_contactos:          'f-meta-cont',
        metas_votos:             'f-votos',
        principales_necesidades: 'f-necesidades',
        enfoque:                 'f-enfoque',
        perfil_socioeconomico:   'f-perfil',
    };
    if (seId) {
        fd.append('action', 'update');
        fd.append('id', seId);
    } else {
        fd.append('action', 'create_for_sec');
        fd.append('sec_id', secId);
    }
    for (const [k, v] of Object.entries(camposEditables)) {
        const el = document.getElementById(v);
        if (el) fd.append(k, el.value);
    }
    try {
        const res  = await fetch(API, { method:'POST', body: fd });
        const data = await res.json();
        if (!data.ok) throw new Error(data.msg || 'Error');
        if (pageCache[secId] && data.seccion) {
            Object.assign(pageCache[secId], {
                se_id:                   data.seccion.id,
                meta_contactos:          data.seccion.meta_contactos,
                metas_votos:             data.seccion.metas_votos,
                principales_necesidades: data.seccion.principales_necesidades,
                enfoque:                 data.seccion.enfoque,
                perfil_socioeconomico:   data.seccion.perfil_socioeconomico,
            });
        }
        cerrarModal('modal-form');
        fetchTabla();
    } catch(err) { alert('Error al guardar: ' + err.message); }
    finally { btn.innerHTML = '<i class="fas fa-save"></i> Guardar'; }
}

// ══════════════════════════════════════════════════════════
//  MODALS
// ══════════════════════════════════════════════════════════
function cerrarModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal').forEach(el =>
    el.addEventListener('click', e => { if (e.target === el) cerrarModal(el.id); })
);

// ══════════════════════════════════════════════════════════
//  SIDEBAR PADDING SYNC
// ══════════════════════════════════════════════════════════
(function(){
    const container = document.getElementById('mainContainer');
    const sidebar   = document.getElementById('sidebar');
    if (!sidebar || !container) return;
    const obs = new MutationObserver(() => {
        container.style.paddingLeft = sidebar.classList.contains('collapsed') ? '110px' : '330px';
    });
    obs.observe(sidebar, { attributes: true, attributeFilter: ['class'] });
})();

// ── INIT ──
fetchTabla();
</script>

</body>
</html>