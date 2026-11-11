<?php
// ── Seguridad ──────────────────────────────────────────────
if (file_exists(__DIR__ . '/../../includes/bootstrap.php')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
} else { session_start(); }

$rol         = $_SESSION['user_role']   ?? '';
$user_estado = $_SESSION['user_estado'] ?? '';
$user_region = $_SESSION['user_region'] ?? $_SESSION['user_region_nombre'] ?? 'Región Asignada';
$user_zona   = $_SESSION['user_zona']   ?? $_SESSION['user_zona_nombre']   ?? 'Zona Asignada';

// Solo permitimos roles operativos a nivel zona y superiores
$permitidos = ['coordinador_zona', 'coordinador_regional', 'coordinador_estatal', 'admin', 'superadmin'];
if (!in_array($rol, $permitidos)) { header('Location: /public/index.php'); exit(); }

// ── Conexión a BD para obtener Secciones de la Zona del Coordinador ──
$seccionesPlanas = [];
$zona_id_real    = 0;
$zona_nombre_real   = '';
$region_nombre_real = '';
$usuario_id = $_SESSION['user_id'] ?? 0;
try {
    $dbConfigPath = __DIR__ . '/../../includes/database.php';
    $dbConfig = file_exists($dbConfigPath) ? require $dbConfigPath : [
        'host'=>'localhost','database'=>'u881824383_pverde',
        'username'=>'u881824383_proyverde','password'=>'PProyectoverde8#',
        'charset'=>'utf8mb4',
        'options'=>[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
    ];
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options'] ?? []);

    $cz_id           = (int)($_SESSION['coordinador_zona_id'] ?? 0);
    $zona_id_session = 0;

    // Estrategia 1: coordinador_zona.zona (FK a zonas_estatales.id)
    if ($cz_id > 0) {
        $r = $pdo->prepare("SELECT zona FROM coordinador_zona WHERE id = ? LIMIT 1");
        $r->execute([$cz_id]);
        $zona_id_session = (int)($r->fetchColumn() ?: 0);
    }

    // Estrategia 2: zonas_estatales.coordinador_id = user_id
    if ($zona_id_session === 0 && $usuario_id > 0) {
        $r = $pdo->prepare("SELECT id FROM zonas_estatales WHERE coordinador_id = ? LIMIT 1");
        $r->execute([$usuario_id]);
        $zona_id_session = (int)($r->fetchColumn() ?: 0);
    }

    // Estrategia 3: zonas_estatales.nombre = user_name
    if ($zona_id_session === 0 && !empty($_SESSION['user_name'])) {
        $r = $pdo->prepare("SELECT id FROM zonas_estatales WHERE nombre = ? LIMIT 1");
        $r->execute([$_SESSION['user_name']]);
        $zona_id_session = (int)($r->fetchColumn() ?: 0);
    }

    // Estrategia 4: LIKE parcial sobre user_name
    if ($zona_id_session === 0 && !empty($_SESSION['user_name'])) {
        $r = $pdo->prepare("SELECT id FROM zonas_estatales WHERE nombre LIKE ? LIMIT 1");
        $r->execute(['%' . trim($_SESSION['user_name']) . '%']);
        $zona_id_session = (int)($r->fetchColumn() ?: 0);
    }

    // DEBUG EXTRA: ver qué hay en coordinador_zona y zonas_estatales
    $dbg_cz_zona = null;
    try {
        $rx = $pdo->prepare("SELECT zona, nombre_zona FROM coordinador_zona WHERE id = ? LIMIT 1");
        $rx->execute([$cz_id]);
        $dbg_cz_zona = $rx->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $ex) {
        $dbg_cz_zona = ['err' => $ex->getMessage()];
    }
    $dbg_zonas = [];
    try {
        $rx2 = $pdo->prepare("SELECT id, nombre, coordinador_id, LEFT(secciones,40) AS secs_preview FROM zonas_estatales WHERE estado = ? OR estructura_id IN (SELECT id FROM regiones_estatales WHERE estado = ?) ORDER BY id LIMIT 20");
        $rx2->execute([$user_estado, $user_estado]);
        $dbg_zonas = $rx2->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $ex2) {
        $dbg_zonas = [['err'=>$ex2->getMessage()]];
    }

    // Con el ID, cargar datos completos
    if ($zona_id_session > 0) {
        $stmtZona = $pdo->prepare("
            SELECT z.id AS zona_id, z.nombre AS zona_nombre, z.secciones,
                   r.nombre AS region_nombre
            FROM zonas_estatales z
            LEFT JOIN regiones_estatales r ON z.estructura_id = r.id
            WHERE z.id = ? LIMIT 1
        ");
        $stmtZona->execute([$zona_id_session]);
        $rowZona = $stmtZona->fetch(PDO::FETCH_ASSOC);
        if ($rowZona) {
            $zona_id_real       = (int)$rowZona['zona_id'];
            $zona_nombre_real   = $rowZona['zona_nombre']   ?? '';
            $region_nombre_real = $rowZona['region_nombre'] ?? '';
            if (!empty($rowZona['secciones'])) {
                $arr = json_decode($rowZona['secciones'], true);
                if (!is_array($arr)) {
                    $arr = array_values(array_filter(array_map('trim', explode(',', $rowZona['secciones']))));
                }
                if (is_array($arr) && count($arr) > 0) {
                    usort($arr, fn($a, $b) => (int)$a - (int)$b);
                    $seccionesPlanas = $arr;
                }
            }
            if ($zona_nombre_real)   $user_zona   = $zona_nombre_real;
            if ($region_nombre_real) $user_region = $region_nombre_real;
        }
    }

} catch (PDOException $e) {
    error_log("Error DB agenda_cz: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agenda Operativa - <?= htmlspecialchars($user_estado) ?> | Zona <?= htmlspecialchars($user_zona) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
    :root {
        --primary:#2d7a3e; --primary-dark:#1f5a2d; --primary-light:rgba(45,122,62,0.08);
        --dark:#1a1a1a; --light:#f0f7f0; --white:#ffffff;
        --gray-50:#f9fafb; --gray-100:#f3f4f6; --gray-200:#e5e7eb; --gray-300:#d1d5db;
        --text:#111827; --text-muted:#6b7280; --text-light:#9ca3af;
        --voto:#2d7a3e; --voto-bg:rgba(45,122,62,0.12);
        --comunicacion:#1a73e8; --comunicacion-bg:rgba(26,115,232,0.12);
        --organizacion:#8b00d4; --organizacion-bg:rgba(139,0,212,0.12);
        --shadow:0 1px 3px rgba(0,0,0,0.1),0 1px 2px rgba(0,0,0,0.06);
        --shadow-lg:0 10px 25px rgba(0,0,0,0.1),0 4px 6px rgba(0,0,0,0.05);
        --sidebar-w:280px;
    }
    *{margin:0;padding:0;box-sizing:border-box;}
    body{font-family:'Google Sans','Segoe UI',Arial,sans-serif;background:var(--white);color:var(--text);height:100vh;display:flex;flex-direction:column;overflow:hidden;padding-left:var(--sidebar-w);}

    /* ── TOP BAR ── */
    .topbar{
        height:64px;border-bottom:1px solid var(--gray-200);
        display:flex;align-items:center;justify-content:space-between;
        padding:0 24px;flex-shrink:0;background:var(--white);
        position:sticky;top:0;z-index:100;
    }
    .topbar-left{display:flex;align-items:center;gap:16px;}
    .topbar-title{font-size:22px;font-weight:400;color:var(--text);letter-spacing:-0.3px;}
    .topbar-title b{font-weight:700;color:var(--primary);}
    .topbar-nav{display:flex;align-items:center;gap:4px;}
    .nav-btn{
        width:36px;height:36px;border-radius:50%;border:none;
        background:transparent;cursor:pointer;font-size:16px;
        color:var(--text-muted);display:flex;align-items:center;justify-content:center;
        transition:background 0.15s;
    }
    .nav-btn:hover{background:var(--gray-100);}
    .today-btn{
        padding:0 14px;height:36px;border-radius:18px;
        border:1px solid var(--gray-300);background:var(--white);
        font-size:13px;font-weight:600;color:var(--text);
        cursor:pointer;transition:background 0.15s;white-space:nowrap;
    }
    .today-btn:hover{background:var(--gray-50);}
    .view-switcher{display:flex;border:1px solid var(--gray-200);border-radius:8px;overflow:hidden;}
    .view-btn{
        padding:7px 14px;font-size:12px;font-weight:600;
        border:none;cursor:pointer;transition:all 0.15s;
        background:var(--white);color:var(--text-muted);
    }
    .view-btn.active{background:var(--primary);color:white;}
    .view-btn:not(:last-child){border-right:1px solid var(--gray-200);}
    .new-event-btn{
        display:flex;align-items:center;gap:8px;
        background:var(--primary);color:white;border:none;
        padding:10px 20px;border-radius:24px;font-size:14px;font-weight:600;
        cursor:pointer;box-shadow:0 1px 3px rgba(45,122,62,0.4);
        transition:all 0.2s;
    }
    .new-event-btn:hover{background:var(--primary-dark);box-shadow:0 4px 12px rgba(45,122,62,0.35);}

    /* ── BODY: sidebar mini + main ── */
    .cal-body{display:flex;flex:1;overflow:hidden;}

    /* ── MINI SIDEBAR ── */
    .mini-sidebar{
        width:256px;flex-shrink:0;padding:16px;
        border-right:1px solid var(--gray-200);
        overflow-y:auto;display:flex;flex-direction:column;gap:20px;
    }

    /* Mini calendar */
    .mini-cal{}
    .mini-cal-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;}
    .mini-cal-title{font-size:13px;font-weight:600;color:var(--text);}
    .mini-nav{width:28px;height:28px;border-radius:50%;border:none;background:transparent;cursor:pointer;font-size:13px;color:var(--text-muted);display:flex;align-items:center;justify-content:center;transition:background 0.15s;}
    .mini-nav:hover{background:var(--gray-100);}
    .mini-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:2px;}
    .mini-dow{font-size:10px;font-weight:700;text-transform:uppercase;color:var(--text-light);text-align:center;padding:4px 0;}
    .mini-day{
        width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;
        font-size:12px;font-weight:500;color:var(--text);cursor:pointer;margin:0 auto;
        transition:background 0.12s;
    }
    .mini-day:hover{background:var(--gray-100);}
    .mini-day.other-month{color:var(--text-light);}
    .mini-day.today{background:var(--primary);color:white;font-weight:700;}
    .mini-day.selected{background:var(--primary-light);color:var(--primary);font-weight:700;}
    .mini-day.has-event::after{content:'';display:block;width:4px;height:4px;border-radius:50%;background:var(--primary);margin:0 auto;margin-top:-2px;}

    /* Balance */
    .balance-box{background:var(--gray-50);border-radius:12px;padding:14px;}
    .balance-box-title{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted);margin-bottom:12px;}
    .bal-row{margin-bottom:10px;}
    .bal-label{display:flex;justify-content:space-between;font-size:11px;margin-bottom:4px;}
    .bal-name{color:var(--text-muted);font-weight:500;}
    .bal-pct{font-weight:700;}
    .bal-track{height:4px;border-radius:2px;background:var(--gray-200);overflow:hidden;}
    .bal-fill{height:100%;border-radius:2px;transition:width 0.6s ease;}
    .badge-bal{display:inline-flex;align-items:center;gap:5px;font-size:10px;font-weight:700;padding:3px 9px;border-radius:12px;margin-top:8px;}
    .badge-bal.ok{background:rgba(45,122,62,0.12);color:var(--primary);}
    .badge-bal.bad{background:#fff7ed;color:#d97706;}

    /* Upcoming */
    .upcoming-box{}
    .upcoming-title{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted);margin-bottom:10px;}
    .upcoming-item{display:flex;gap:10px;align-items:flex-start;padding:8px 0;border-bottom:1px solid var(--gray-100);}
    .upcoming-item:last-child{border-bottom:none;}
    .upcoming-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0;margin-top:3px;}
    .upcoming-info{}
    .upcoming-ev-title{font-size:12px;font-weight:600;color:var(--text);line-height:1.3;}
    .upcoming-ev-date{font-size:10px;color:var(--text-muted);margin-top:2px;}

    /* ── MAIN CALENDAR ── */
    .cal-main{flex:1;display:flex;flex-direction:column;overflow:hidden;}

    /* Month view */
    .month-view{flex:1;display:flex;flex-direction:column;overflow:hidden;}
    .month-dow-row{
        display:grid;grid-template-columns:repeat(7,1fr);
        border-bottom:1px solid var(--gray-200);flex-shrink:0;
    }
    .month-dow{
        padding:10px 8px;font-size:11px;font-weight:700;
        text-transform:uppercase;letter-spacing:0.5px;
        color:var(--text-muted);text-align:center;
    }
    .month-grid{
        flex:1;display:grid;grid-template-columns:repeat(7,1fr);
        grid-auto-rows:1fr;overflow:hidden;
    }
    .month-cell{
        border-right:1px solid var(--gray-200);
        border-bottom:1px solid var(--gray-200);
        padding:6px 8px;overflow:hidden;cursor:pointer;
        transition:background 0.12s;min-height:100px;
        display:flex;flex-direction:column;
    }
    .month-cell:hover{background:var(--gray-50);}
    .month-cell.other-month{background:var(--gray-50);}
    .month-cell.other-month .cell-num{color:var(--text-light);}
    .month-cell.today{background:#e8f5e9;}
    .cell-num{
        font-size:13px;font-weight:500;color:var(--text);
        width:28px;height:28px;border-radius:50%;
        display:flex;align-items:center;justify-content:center;
        flex-shrink:0;margin-bottom:4px;
    }
    .month-cell.today .cell-num{background:var(--primary);color:white;font-weight:700;}
    .cell-events{display:flex;flex-direction:column;gap:2px;flex:1;overflow:hidden;}
    .cell-pill{
        font-size:11px;font-weight:600;padding:2px 7px;
        border-radius:4px;white-space:nowrap;overflow:hidden;
        text-overflow:ellipsis;cursor:pointer;
        transition:filter 0.1s;display:block;min-width:0;
    }
    .cell-pill:hover{filter:brightness(0.92);}
    .cell-pill.voto        {background:var(--voto-bg);color:var(--voto);}
    .cell-pill.comunicacion{background:var(--comunicacion-bg);color:var(--comunicacion);}
    .cell-pill.organizacion{background:var(--organizacion-bg);color:var(--organizacion);}
    .cell-more{font-size:10px;font-weight:700;color:var(--text-muted);padding:2px 4px;cursor:pointer;}
    .cell-more:hover{color:var(--text);}

    /* Week view */
    .week-view{flex:1;display:flex;flex-direction:column;overflow:hidden;}
    .week-header-row{
        display:grid;grid-template-columns:52px repeat(7,1fr);
        border-bottom:1px solid var(--gray-200);flex-shrink:0;
    }
    .week-header-time{padding:8px;}
    .week-header-day{
        padding:10px 8px;text-align:center;border-left:1px solid var(--gray-200);
    }
    .whd-dow{font-size:11px;font-weight:700;text-transform:uppercase;color:var(--text-muted);}
    .whd-num{font-size:24px;font-weight:400;color:var(--text);line-height:1.1;margin-top:2px;}
    .week-header-day.today .whd-num{
        background:var(--primary);color:white;
        width:40px;height:40px;border-radius:50%;
        display:inline-flex;align-items:center;justify-content:center;
        font-weight:700;font-size:20px;
    }
    .week-body{flex:1;overflow-y:auto;display:grid;grid-template-columns:52px repeat(7,1fr);}
    .week-time-col{display:flex;flex-direction:column;}
    .week-time-slot{
        height:48px;flex-shrink:0;display:flex;align-items:flex-start;
        justify-content:flex-end;padding:0 8px;padding-top:2px;
        font-size:10px;font-weight:500;color:var(--text-light);
        border-bottom:1px solid transparent;
    }
    .week-day-col{
        border-left:1px solid var(--gray-200);
        display:flex;flex-direction:column;position:relative;
    }
    .week-day-col.today{background:rgba(45,122,62,0.02);}
    .week-hour-row{
        height:48px;flex-shrink:0;border-bottom:1px solid var(--gray-100);
        position:relative;
    }
    .week-hour-row:hover{background:rgba(45,122,62,0.04);}
    .week-event{
        position:absolute;left:2px;right:2px;
        border-radius:4px;padding:2px 6px;
        font-size:11px;font-weight:600;cursor:pointer;
        z-index:1;overflow:hidden;
        transition:filter 0.1s;
    }
    .week-event:hover{filter:brightness(0.92);}
    .week-event.voto        {background:var(--voto-bg);color:var(--voto);border-left:3px solid var(--voto);}
    .week-event.comunicacion{background:var(--comunicacion-bg);color:var(--comunicacion);border-left:3px solid var(--comunicacion);}
    .week-event.organizacion{background:var(--organizacion-bg);color:var(--organizacion);border-left:3px solid var(--organizacion);}
    .week-allday-row{
        display:grid;grid-template-columns:52px repeat(7,1fr);
        border-bottom:1px solid var(--gray-200);flex-shrink:0;min-height:28px;
    }
    .week-allday-label{font-size:10px;color:var(--text-light);padding:4px 8px;text-align:right;}
    .week-allday-cell{border-left:1px solid var(--gray-200);padding:2px 3px;display:flex;flex-direction:column;gap:2px;min-width:0;overflow:hidden;}

    /* ── POPOVER evento ── */
    .ev-popover{
        position:fixed;z-index:9000;
        background:var(--white);border-radius:12px;
        box-shadow:0 8px 30px rgba(0,0,0,0.18);
        border:1px solid var(--gray-200);
        width:300px;padding:0;overflow:hidden;
        animation:popIn 0.15s ease;
    }
    @keyframes popIn{from{opacity:0;transform:scale(0.95)}to{opacity:1;transform:scale(1)}}
    .popover-bar{height:6px;}
    .popover-body{padding:16px 20px 20px;max-height:340px;overflow-y:auto;}
    .popover-title{font-size:16px;font-weight:700;color:var(--text);margin-bottom:8px;line-height:1.3;}
    .popover-badge{display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:700;padding:3px 10px;border-radius:12px;margin-bottom:12px;}
    .popover-row{display:flex;align-items:flex-start;gap:10px;margin-bottom:8px;font-size:13px;color:var(--text-muted);}
    .popover-row i{width:16px;flex-shrink:0;margin-top:2px;}
    .popover-row span{color:var(--text);}
    #pop-notas span{display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;text-overflow:ellipsis;line-height:1.4;}
    .popover-actions{display:flex;justify-content:flex-end;gap:8px;padding-top:12px;border-top:1px solid var(--gray-100);margin-top:4px;}
    .pop-btn{font-size:12px;font-weight:600;padding:6px 14px;border-radius:8px;border:none;cursor:pointer;transition:background 0.15s;}
    .pop-btn-edit{background:var(--primary);color:white;}
    .pop-btn-edit:hover{background:var(--primary-dark);}
    .pop-btn-del{background:#fef2f2;color:#dc2626;}
    .pop-btn-del:hover{background:#fee2e2;}
    .pop-close{position:absolute;top:10px;right:10px;width:28px;height:28px;border-radius:50%;border:none;background:transparent;cursor:pointer;font-size:14px;color:var(--text-muted);display:flex;align-items:center;justify-content:center;}
    .pop-close:hover{background:var(--gray-100);}

    /* ── MODAL CREAR/EDITAR ── */
    .modal-overlay{
        display:none;position:fixed;inset:0;
        background:rgba(0,0,0,0.35);z-index:5000;
        align-items:center;justify-content:center;
        opacity:0;transition:opacity 0.2s;
    }
    .modal-overlay.open{display:flex;opacity:1;}
    .modal-box{
        background:var(--white);border-radius:20px;
        box-shadow:0 25px 50px rgba(0,0,0,0.25);
        width:90%;max-width:680px;padding:36px;
        position:relative;transform:translateY(20px);opacity:0;
        transition:all 0.3s ease;max-height:90vh;overflow-y:auto;
    }
    .modal-overlay.open .modal-box{transform:translateY(0);opacity:1;}
    .modal-box-title{font-size:22px;font-weight:700;margin-bottom:4px;color:var(--text);}
    .modal-box-sub{font-size:13px;color:var(--text-muted);margin-bottom:24px;}
    .mclose{position:absolute;top:20px;right:20px;width:36px;height:36px;border-radius:50%;border:none;background:var(--gray-100);cursor:pointer;font-size:16px;color:var(--text-muted);display:flex;align-items:center;justify-content:center;transition:all 0.2s;}
    .mclose:hover{background:var(--gray-200);transform:rotate(90deg);}

    .fg{display:flex;flex-direction:column;gap:6px;margin-bottom:18px;}
    .flabel{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted);}
    .finput{width:100%;padding:11px 14px;border-radius:10px;border:1.5px solid var(--gray-200);background:var(--gray-50);font-size:14px;font-weight:500;color:var(--text);outline:none;transition:all 0.2s;font-family:inherit;appearance:none;}
    .finput:focus{border-color:var(--primary);background:var(--white);}
    textarea.finput{resize:vertical;min-height:80px;font-weight:400;}

    .eje-row{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;}
    .eje-pill{
        padding:10px 8px;border-radius:10px;border:2px solid var(--gray-200);
        font-size:11px;font-weight:700;text-align:center;cursor:pointer;
        background:var(--gray-50);color:var(--text-muted);
        transition:all 0.15s;
    }
    .eje-pill.sel-voto        {background:var(--voto-bg);border-color:var(--voto);color:var(--voto);}
    .eje-pill.sel-comunicacion{background:var(--comunicacion-bg);border-color:var(--comunicacion);color:var(--comunicacion);}
    .eje-pill.sel-organizacion{background:var(--organizacion-bg);border-color:var(--organizacion);color:var(--organizacion);}

    .form-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
    .form-grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;}

    .mfooter{display:flex;justify-content:space-between;align-items:center;margin-top:24px;padding-top:20px;border-top:1px solid var(--gray-200);}
    #mapa-evento{height:220px;border-radius:12px;border:2px solid var(--gray-200);margin-top:2px;}
    .map-coords{display:flex;gap:10px;margin-top:8px;}
    .map-note{font-size:12px;color:var(--text-muted);margin-bottom:6px;}
    .fotos-upload-area{border:2px dashed var(--gray-200);border-radius:14px;padding:22px 16px;text-align:center;cursor:pointer;transition:all 0.3s;background:var(--gray-100);position:relative;}
    .fotos-upload-area:hover,.fotos-upload-area.drag-over{border-color:var(--primary);background:rgba(45,122,62,0.04);}
    .fotos-upload-area .upload-icon{font-size:24px;color:var(--primary);opacity:0.6;margin-bottom:6px;}
    .fotos-upload-area p{font-size:12px;color:var(--text-muted);font-weight:600;margin:0;}
    .fotos-upload-area p span{color:var(--primary);text-decoration:underline;}
    .fotos-preview-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(84px,1fr));gap:10px;margin-top:12px;}
    .foto-thumb{position:relative;border-radius:10px;overflow:hidden;aspect-ratio:1;background:var(--gray-200);box-shadow:0 2px 8px rgba(0,0,0,0.08);}
    .foto-thumb img{width:100%;height:100%;object-fit:cover;display:block;transition:transform 0.2s;}
    .foto-thumb:hover img{transform:scale(1.04);}
    .foto-thumb .remove-foto{position:absolute;top:4px;right:4px;background:rgba(0,0,0,0.6);color:#fff;border:none;border-radius:50%;width:20px;height:20px;cursor:pointer;font-size:10px;display:flex;align-items:center;justify-content:center;}
    .foto-counter{font-size:11px;font-weight:700;color:var(--text-muted);margin-top:6px;text-align:right;}
    .pdf-item{display:flex;align-items:center;gap:8px;padding:8px 10px;border:1px solid var(--gray-200);border-radius:8px;margin-top:6px;background:#fff;}
    .pdf-item span{flex:1;font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
    .pdf-item button{border:none;background:none;color:#999;cursor:pointer;font-size:13px;}
    .pdf-item button:hover{color:#dc2626;}
    #realizado-banner{display:none;margin-bottom:18px;padding:12px 14px;border-radius:10px;background:rgba(22,163,74,0.08);border:1.5px solid rgba(22,163,74,0.3);align-items:center;gap:10px;}
    .mbtn-realizado{background:#16a34a;color:#fff;border:none;}
    .mbtn-realizado:hover{background:#15803d;}

    .mbtn{padding:11px 24px;border-radius:12px;font-size:14px;font-weight:600;cursor:pointer;border:none;transition:all 0.2s;font-family:inherit;}
    .mbtn-save{background:var(--primary);color:white;box-shadow:0 2px 8px rgba(45,122,62,0.3);}
    .mbtn-save:hover{background:var(--primary-dark);}
    .mbtn-cancel{background:var(--white);color:var(--text-muted);border:1px solid var(--gray-200);}
    .mbtn-cancel:hover{border-color:var(--gray-400);color:var(--text);}
    .mbtn-del{background:#fef2f2;color:#dc2626;border:1px solid #fecaca;}
    .mbtn-del:hover{background:#fee2e2;}


    /* ── FILTER BAR (Zona) ── */
    .filter-bar{
        display:flex;align-items:center;gap:10px;flex-wrap:wrap;
        padding:10px 24px;border-bottom:1px solid var(--gray-200);
        background:var(--gray-50);flex-shrink:0;
    }
    .filter-bar-label{
        font-size:13px;font-weight:700;text-transform:uppercase;
        letter-spacing:0.5px;color:var(--primary);white-space:nowrap;
        display:flex;align-items:center;gap:6px;
    }
    .filter-select{
        padding:6px 28px 6px 10px;border-radius:8px;
        border:1.5px solid var(--gray-200);background:var(--white);
        font-size:12px;font-weight:500;color:var(--text);
        cursor:pointer;outline:none;transition:border-color 0.2s;
        appearance:none;
        background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath fill='%236b7280' d='M0 0l5 6 5-6z'/%3E%3C/svg%3E");
        background-repeat:no-repeat;background-position:right 8px center;
        min-width:140px;
    }
    .filter-select:focus{border-color:var(--primary);}
    .filter-select.active{border-color:var(--primary);background-color:var(--primary-light);color:var(--primary);font-weight:700;}
    .filter-sep{width:1px;height:20px;background:var(--gray-200);margin:0 4px;}
    .filter-clear{
        padding:6px 12px;border-radius:8px;border:1.5px solid var(--gray-200);
        background:var(--white);font-size:11px;font-weight:600;color:var(--text-muted);
        cursor:pointer;transition:all 0.15s;white-space:nowrap;
        display:flex;align-items:center;gap:5px;
    }
    .filter-clear:hover{border-color:var(--primary);color:var(--primary);background:var(--primary-light);}
    /* ── TOGGLE MI AGENDA ── */
    .toggle-zona{display:flex;align-items:center;gap:8px;padding:5px 12px;background:var(--white);border:1.5px solid var(--gray-200);border-radius:20px;cursor:pointer;transition:all 0.2s;user-select:none;white-space:nowrap;}
    .toggle-zona:hover{border-color:var(--primary);}
    .toggle-zona.asignados-mode{border-color:var(--comunicacion);}
    .toggle-track-z{width:34px;height:18px;border-radius:9px;background:var(--gray-300);position:relative;transition:background 0.2s;flex-shrink:0;}
    .toggle-track-z.on{background:var(--primary);}
    .toggle-thumb-z{position:absolute;top:2px;left:2px;width:14px;height:14px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,0.2);transition:left 0.2s;}
    .toggle-track-z.on .toggle-thumb-z{left:18px;}
    .toggle-label-z{font-size:11px;font-weight:700;color:var(--text-muted);transition:color 0.2s;}
    .toggle-zona.asignados-mode .toggle-label-z{color:var(--comunicacion);}
    .ev-counter-z{font-size:12px;color:var(--text-muted);white-space:nowrap;font-weight:500;}
    .ev-counter-z b{color:var(--primary);}
    .filter-count{
        font-size:11px;font-weight:700;padding:3px 10px;border-radius:12px;
        background:var(--primary);color:white;margin-left:4px;
        display:inline-flex;align-items:center;
    }
    .filter-count.hidden{display:none;}
    .filter-eje-btn{
        padding:6px 12px;border-radius:8px;border:1.5px solid var(--gray-200);
        background:var(--white);font-size:11px;font-weight:600;color:var(--text-muted);
        cursor:pointer;transition:all 0.15s;white-space:nowrap;
        display:flex;align-items:center;gap:5px;
    }
    .filter-eje-btn.active-voto        {border-color:var(--voto);background:var(--voto-bg);color:var(--voto);}
    .filter-eje-btn.active-comunicacion{border-color:var(--comunicacion);background:var(--comunicacion-bg);color:var(--comunicacion);}
    .filter-eje-btn.active-organizacion{border-color:var(--organizacion);background:var(--organizacion-bg);color:var(--organizacion);}

    /* ── BUSCADOR SECCIÓN EN AGENDA ── */
    .sec-agenda-wrap{position:relative;width:100%;}
    .sec-agenda-input{cursor:pointer!important;padding-right:32px!important;}
    .sec-agenda-arrow{
        position:absolute;right:12px;top:50%;transform:translateY(-50%);
        pointer-events:none;color:var(--text-muted);font-size:11px;
        transition:transform 0.2s;
    }
    .sec-agenda-wrap.open .sec-agenda-arrow{transform:translateY(-50%) rotate(180deg);}
    .sec-agenda-list{
        display:none;position:absolute;top:calc(100% + 4px);left:0;right:0;
        background:var(--white);border:2px solid var(--primary);
        border-radius:10px;z-index:9999;max-height:220px;
        overflow:hidden;box-shadow:0 8px 24px rgba(0,0,0,0.12);
        flex-direction:column;
    }
    .sec-agenda-wrap.open .sec-agenda-list{display:flex;}
    .sec-agenda-search{
        padding:10px 12px;border:none;border-bottom:1px solid var(--gray-200);
        font-size:13px;outline:none;font-family:inherit;
        color:var(--text);background:var(--gray-50);flex-shrink:0;
    }
    .sec-agenda-search:focus{background:var(--white);}
    .sec-agenda-options{overflow-y:auto;flex:1;}
    .sec-agenda-opt{
        padding:9px 14px;font-size:13px;font-weight:600;
        cursor:pointer;color:var(--text);transition:background 0.12s;
        font-family:inherit;border-bottom:1px solid var(--gray-100);
    }
    .sec-agenda-opt:last-child{border-bottom:none;}
    .sec-agenda-opt:hover,.sec-agenda-opt.focused{background:var(--primary-light);color:var(--primary);}
    .sec-agenda-opt.selected{background:var(--primary-light);color:var(--primary);font-weight:700;}
    .sec-agenda-opt em{font-style:normal;font-weight:700;color:var(--primary);}
    .sec-agenda-empty,.sec-agenda-loading{
        padding:14px;text-align:center;font-size:13px;
        color:var(--text-muted);font-style:italic;
    }
    @media(max-width:900px){
        body{padding-left:0;}
        .mini-sidebar{display:none;}
        .topbar-title{font-size:16px;}
    }
    </style>
</head>
<body>

<?php include __DIR__ . '/../../includes/components/sidebar_cz.php'; ?>


<div class="topbar">
    <div class="topbar-left">
        <button class="today-btn" onclick="irHoy()">Hoy</button>
        <div class="topbar-nav">
            <button class="nav-btn" onclick="navegar(-1)"><i class="fas fa-chevron-left"></i></button>
            <button class="nav-btn" onclick="navegar(1)"><i class="fas fa-chevron-right"></i></button>
        </div>
        <span class="topbar-title" id="topbar-label">—</span>
    </div>
    <div style="display:flex;align-items:center;gap:12px;">
        <div class="view-switcher">
            <button class="view-btn" id="vbtn-month" onclick="setVista('month')">Mes</button>
            <button class="view-btn active" id="vbtn-week"  onclick="setVista('week')">Semana</button>
        </div>
        <button class="new-event-btn" onclick="abrirModal()">
            <i class="fas fa-plus"></i> Nuevo Evento
        </button>
    </div>
</div>

<div class="filter-bar" id="filter-bar">
    <span class="filter-bar-label">
        <i class="fas fa-map-marker-alt"></i> 
        <?= htmlspecialchars($user_estado) ?> 
        <i class="fas fa-chevron-right" style="font-size: 8px; margin: 0 4px; color: #9ca3af;"></i> 
        <?= htmlspecialchars($user_region) ?> 
        <i class="fas fa-chevron-right" style="font-size: 8px; margin: 0 4px; color: #9ca3af;"></i> 
        Zona <?= htmlspecialchars($user_zona) ?>
    </span>

    <div class="filter-sep"></div>

    <select class="filter-select" id="fil-municipio" onchange="applyFilters()">
        <option value="">📋 Todas mis secciones</option>
    </select>

    <div class="filter-sep"></div>

    <button class="filter-eje-btn" id="feje-voto"         onclick="toggleEjeFilter('voto')">🗳️ Voto</button>
    <button class="filter-eje-btn" id="feje-comunicacion" onclick="toggleEjeFilter('comunicacion')">📣 Comunicación</button>
    <button class="filter-eje-btn" id="feje-organizacion" onclick="toggleEjeFilter('organizacion')">⚙️ Organización</button>

    <div class="filter-sep"></div>

    <button class="filter-clear" onclick="clearFilters()" id="btn-clear-filters">
        <i class="fas fa-times-circle"></i> Limpiar
    </button>
    <span class="filter-count hidden" id="filter-count">0 eventos</span>
    <div class="filter-sep"></div>
    <span class="ev-counter-z" id="ev-counter-z" style="display:none;"></span>
    <div class="toggle-zona" id="toggle-zona-btn" onclick="toggleModoAgenda()" title="Mi Agenda / Ver Asignados">
        <div class="toggle-track-z on" id="toggle-track-z"><div class="toggle-thumb-z"></div></div>
        <span class="toggle-label-z" id="toggle-label-z">Mi Agenda</span>
    </div>
</div>

<div class="cal-body">

    <aside class="mini-sidebar">

        <div class="mini-cal">
            <div class="mini-cal-header">
                <span class="mini-cal-title" id="mini-cal-label">—</span>
                <div style="display:flex;gap:2px;">
                    <button class="mini-nav" onclick="miniNavegar(-1)"><i class="fas fa-chevron-left"></i></button>
                    <button class="mini-nav" onclick="miniNavegar(1)"><i class="fas fa-chevron-right"></i></button>
                </div>
            </div>
            <div class="mini-grid" id="mini-grid"></div>
        </div>

        <div class="balance-box">
            <div class="balance-box-title" id="balance-box-title">Balance semanal</div>
            <div class="bal-row">
                <div class="bal-label"><span class="bal-name">Voto</span><span class="bal-pct" id="bpct-voto" style="color:var(--voto)">0%</span></div>
                <div class="bal-track"><div class="bal-fill" id="bbar-voto" style="background:var(--voto);width:0%"></div></div>
            </div>
            <div class="bal-row">
                <div class="bal-label"><span class="bal-name">Comunicación</span><span class="bal-pct" id="bpct-comunicacion" style="color:var(--comunicacion)">0%</span></div>
                <div class="bal-track"><div class="bal-fill" id="bbar-comunicacion" style="background:var(--comunicacion);width:0%"></div></div>
            </div>
            <div class="bal-row">
                <div class="bal-label"><span class="bal-name">Organización</span><span class="bal-pct" id="bpct-organizacion" style="color:var(--organizacion)">0%</span></div>
                <div class="bal-track"><div class="bal-fill" id="bbar-organizacion" style="background:var(--organizacion);width:0%"></div></div>
            </div>
            <div id="badge-bal" class="badge-bal bad"><i class="fas fa-triangle-exclamation"></i> Desbalance</div>
        </div>

        <div class="upcoming-box">
            <div class="upcoming-title">Próximos eventos (Zona)</div>
            <div id="upcoming-list"></div>
        </div>

    </aside>

    <div class="cal-main" id="cal-main"></div>

</div>

<div class="ev-popover" id="ev-popover" style="display:none;">
    <button class="pop-close" onclick="cerrarPopover()"><i class="fas fa-times"></i></button>
    <div class="popover-bar" id="pop-bar"></div>
    <div class="popover-body">
        <div class="popover-title" id="pop-title">—</div>
        <div class="popover-badge" id="pop-badge"></div>
        <div class="popover-row" id="pop-fecha"><i class="fas fa-calendar"></i><span></span></div>
        <div class="popover-row" id="pop-hora"><i class="fas fa-clock"></i><span></span></div>
        <div class="popover-row" id="pop-lugar"><i class="fas fa-map-marker-alt"></i><span></span></div>
        <div class="popover-row" id="pop-resp"><i class="fas fa-user"></i><span></span></div>
        <div class="popover-row" id="pop-activacion"><i class="fas fa-user-tie"></i><span></span></div>
        <div class="popover-row" id="pop-notas"><i class="fas fa-align-left"></i><span></span></div>
        <div class="popover-row" id="pop-creador"><i class="fas fa-user-edit"></i><span></span></div>
        <div class="popover-actions">
            <button class="pop-btn pop-btn-del" id="pop-del">Eliminar</button>
            <button class="pop-btn pop-btn-edit" id="pop-edit">Editar</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="modal-form" onclick="if(event.target===this)cerrarModal()">
    <div class="modal-box">
        <button class="mclose" onclick="cerrarModal()"><i class="fas fa-times"></i></button>
        <h2 class="modal-box-title" id="modal-title">Nuevo Evento</h2>
        <p class="modal-box-sub">Registra un evento en la agenda de tu zona.</p>

        <div class="fg">
            <label class="flabel">Eje *</label>
            <div class="eje-row">
                <button class="eje-pill" data-eje="voto"         onclick="selEje('voto')"         type="button">🗳️ Voto / Territorio</button>
                <button class="eje-pill" data-eje="comunicacion" onclick="selEje('comunicacion')" type="button">📣 Comunicación</button>
                <button class="eje-pill" data-eje="organizacion" onclick="selEje('organizacion')" type="button">⚙️ Organización</button>
            </div>
        </div>
        <div class="fg">
            <label class="flabel">Subtipo</label>
            <select id="f-subtipo" class="finput"><option value="">— Selecciona un eje primero —</option></select>
        </div>
        <div class="fg">
            <label class="flabel">Título *</label>
            <input type="text" id="f-titulo" class="finput" placeholder="Ej. Recorrido Colonia Pitic">
        </div>
        
        <div class="form-grid-3" style="margin-bottom:18px;">
            <div class="fg" style="margin-bottom:0;">
                <label class="flabel">Fecha *</label>
                <input type="date" id="f-fecha" class="finput">
            </div>
            <div class="fg" style="margin-bottom:0;">
                <label class="flabel">Estado</label>
                <input type="text" id="f-estado" class="finput" value="<?= htmlspecialchars($user_estado) ?>" readonly style="background:var(--gray-200); color:var(--text-muted); cursor:not-allowed;">
            </div>
            <div class="fg" style="margin-bottom:0;">
                <label class="flabel">Región</label>
                <input type="text" id="f-region" class="finput" value="<?= htmlspecialchars($user_region) ?>" readonly style="background:var(--gray-200); color:var(--text-muted); cursor:not-allowed;">
            </div>
        </div>

        <div class="form-grid-2" style="margin-bottom:18px;">
            <div class="fg" style="margin-bottom:0;">
                <label class="flabel">Zona</label>
                <input type="text" id="f-zona" class="finput" value="<?= htmlspecialchars($user_zona) ?>" readonly style="background:var(--gray-200); color:var(--text-muted); cursor:not-allowed;">
            </div>
            <div class="fg" style="margin-bottom:0;">
                <label class="flabel">
                    Sección
                    <span id="f-sec-contador" style="font-weight:400;color:var(--text-muted);margin-left:6px;font-size:10px;"></span>
                </label>
                <div class="sec-agenda-wrap" id="sec-agenda-wrap">
                    <input type="hidden" id="f-municipio" value="">
                    <input type="hidden" id="f-seccion" value="">
                    <input type="text" class="sec-agenda-input finput" id="sec-agenda-display"
                           placeholder="— Selecciona sección —"
                           readonly onclick="secAgendaToggle()" autocomplete="off">
                    <i class="fas fa-chevron-down sec-agenda-arrow" id="sec-agenda-arrow"></i>
                    <div class="sec-agenda-list" id="sec-agenda-list">
                        <input type="text" class="sec-agenda-search" id="sec-agenda-search"
                               placeholder="🔍 Buscar sección…"
                               oninput="secAgendaFiltrar(this.value)"
                               onkeydown="secAgendaNavegar(event)">
                        <div class="sec-agenda-options" id="sec-agenda-options">
                            <div class="sec-agenda-empty">Sin secciones asignadas</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="form-grid-3" style="margin-bottom:18px;">
            <div class="fg" style="margin-bottom:0;">
                <label class="flabel">Inicio</label>
                <input type="time" id="f-inicio" class="finput" onchange="limpiarErrorFin()">
            </div>
            <div class="fg" style="margin-bottom:0;">
                <label class="flabel">Fin</label>
                <input type="time" id="f-fin" class="finput" onchange="calcHoras()">
                <span id="hora-error-msg" style="display:none;font-size:11px;font-weight:700;color:#dc2626;margin-top:4px;">
                    <i class="fas fa-exclamation-circle"></i> El fin debe ser posterior al inicio
                </span>
            </div>
            <div class="fg" style="margin-bottom:0;">
                <label class="flabel">Horas</label>
                <input type="number" id="f-horas" class="finput" min="0" max="24" step="0.5" placeholder="0">
            </div>
        </div>

        <div class="form-grid-2" style="margin-bottom:18px;">
            <div class="fg" style="margin-bottom:0;">
                <label class="flabel">Responsable</label>
                <input type="text" id="f-responsable" class="finput" placeholder="Nombre">
            </div>
            <div class="fg" style="margin-bottom:0;">
                <label class="flabel">Activación</label>
                <select id="f-activacion" class="finput">
                    <option value="">— Seleccionar —</option>
                    <option value="Con Candidato">Con Candidato</option>
                    <option value="Sin Candidato">Sin Candidato</option>
                </select>
            </div>
        </div>

        <div class="fg">
            <label class="flabel">Notas</label>
            <textarea id="f-notas" class="finput" placeholder="Observaciones…"></textarea>
        </div>

        <div id="realizado-banner">
            <i class="fas fa-check-circle" style="color:#16a34a;font-size:16px;flex-shrink:0;"></i>
            <div>
                <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:#16a34a;">Evento Realizado</div>
                <div style="font-size:12px;color:var(--text-muted);margin-top:2px;">Este evento ya fue marcado como realizado y no puede editarse ni eliminarse.</div>
            </div>
        </div>

        <div class="fg" style="margin-bottom:18px;">
            <label class="flabel">Ubicación / Georreferencia</label>
            <div style="display:flex;gap:8px;margin-bottom:8px;">
                <div style="position:relative;flex:1;">
                    <i class="fas fa-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#aaa;font-size:13px;pointer-events:none;"></i>
                    <input type="text" id="map-search-input" class="finput" placeholder="Buscar dirección o lugar…" style="padding-left:34px;"
                           onkeydown="if(event.key==='Enter'){event.preventDefault();buscarEnMapaEv();}">
                </div>
            </div>
            <div id="map-search-results" style="display:none;border:1px solid #eee;border-radius:10px;background:#fff;max-height:180px;overflow-y:auto;margin-bottom:8px;box-shadow:0 4px 12px rgba(0,0,0,0.08);"></div>
            <p class="map-note"><i class="fas fa-hand-pointer"></i> O haz clic directamente en el mapa para ubicar el punto exacto</p>
            <div id="mapa-evento"></div>
            <div class="map-coords">
                <input type="number" id="f-lat" class="finput" placeholder="Latitud" step="0.000001" readonly style="background:#f0f0f0;">
                <input type="number" id="f-lng" class="finput" placeholder="Longitud" step="0.000001" readonly style="background:#f0f0f0;">
            </div>
        </div>

        <div class="fg" style="margin-bottom:18px;">
            <label class="flabel">📷 Evidencia — Fotos</label>
            <div class="fotos-upload-area" id="drop-zone-foto" onclick="document.getElementById('foto-input').click()" ondragover="dragOverEv(event,'drop-zone-foto')" ondragleave="dragLeaveEv(event,'drop-zone-foto')" ondrop="dropFotosEv(event)">
                <div class="upload-icon"><i class="fas fa-images"></i></div>
                <p>Arrastra imágenes aquí o <span>haz clic para seleccionar</span></p>
                <p style="font-size:11px;margin-top:4px;opacity:0.7;">JPG, PNG, WEBP — máx. 5 MB por foto</p>
            </div>
            <input type="file" id="foto-input" accept="image/*" multiple onchange="procesarFotosEv(this.files)">
            <div class="fotos-preview-grid" id="fotos-preview"></div>
            <div class="foto-counter" id="foto-counter"></div>
        </div>

        <div class="fg" style="margin-bottom:18px;">
            <label class="flabel">📄 Evidencia — Documentos PDF</label>
            <div class="fotos-upload-area" id="drop-zone-pdf" onclick="document.getElementById('pdf-input').click()" ondragover="dragOverEv(event,'drop-zone-pdf')" ondragleave="dragLeaveEv(event,'drop-zone-pdf')" ondrop="dropPdfsEv(event)">
                <div class="upload-icon"><i class="fas fa-file-pdf"></i></div>
                <p>Arrastra archivos PDF aquí o <span>haz clic para seleccionar</span></p>
                <p style="font-size:11px;margin-top:4px;opacity:0.7;">PDF — máx. 8 MB por archivo</p>
            </div>
            <input type="file" id="pdf-input" accept="application/pdf" multiple onchange="procesarPdfsEv(this.files)">
            <div id="pdfs-preview"></div>
        </div>

        <div class="fg" style="margin-bottom:18px;">
            <label class="flabel">🔗 Evidencia — Enlaces</label>
            <div style="display:flex;gap:8px;margin-bottom:8px;">
                <input type="url" id="enlace-input-ev" class="finput" placeholder="https://... (Drive, Fotos, Facebook, Instagram, X, etc.)" style="flex:1;"
                       onkeydown="if(event.key==='Enter'){event.preventDefault();agregarEnlaceEv();}">
                <button type="button" onclick="agregarEnlaceEv()" style="background:var(--primary);color:#fff;border:none;border-radius:10px;padding:0 16px;font-weight:700;font-size:13px;cursor:pointer;white-space:nowrap;"><i class="fas fa-plus"></i> Agregar</button>
            </div>
            <p style="font-size:11px;margin:0 0 6px;opacity:0.7;">Pega enlaces a Drive, Fotos, Facebook, Instagram, X, YouTube, etc.</p>
            <div id="enlaces-preview-ev"></div>
        </div>

        <div class="fg" style="background:#f0f7f0;border:1px solid #d5e8d5;border-radius:10px;padding:14px;margin-bottom:18px;">
            <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-weight:700;font-size:13px;color:#1f5a2d;">
                <input type="checkbox" id="f-registro-qr" style="width:18px;height:18px;">
                <i class="fas fa-qrcode"></i> Activar registro de asistentes (liga + QR)
            </label>
            <div style="font-size:11px;color:#4a7a4a;margin-top:6px;">La liga y el QR quedan disponibles en "Database QR" al guardar.</div>
        </div>

        <div class="mfooter">
            <button class="mbtn mbtn-del" id="btn-del-ev" style="visibility:hidden" onclick="eliminarEvento(editandoId)">
                <i class="fas fa-trash-alt"></i> Eliminar
            </button>
            <div style="display:flex;gap:10px;">
                <button class="mbtn mbtn-realizado" id="btn-realizado-ev" style="visibility:hidden" onclick="marcarRealizado(editandoId)">
                    <i class="fas fa-check-double"></i> Marcar como Realizado
                </button>
                <button class="mbtn mbtn-cancel" onclick="cerrarModal()">Cancelar</button>
                <button class="mbtn mbtn-save" id="btn-save-ev" onclick="guardarEvento()">
                    <i class="fas fa-check"></i> Guardar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const PROCESAR_URL = '../../api/agenda/procesar.php';
let eventosFiltrados = [];

// ══════════════════════════════════════════════════════
//  DATOS DE LA ZONA INYECTADOS POR PHP
// ══════════════════════════════════════════════════════
const ESTADO_COORDINADOR = '<?= htmlspecialchars($user_estado) ?>';
const USER_ZONA      = '<?= htmlspecialchars($user_zona) ?>';
const SECCIONES_ZONA = <?= json_encode(array_values($seccionesPlanas)) ?>;
const USUARIO_ID     = <?= intval($usuario_id) ?>;
const ZONA_ID_REAL   = <?= intval($zona_id_real) ?>; // ID numérico real de la zona desde BD

let eventosOriginales = [];
let modoAsignados     = false; // false = Mi Agenda, true = Ver Asignados

let filMunicipio = '';
let filEjes      = new Set(); 

// ── Carga inicial: poblar filtro de secciones desde PHP ──
async function geoInicializar() {
    const selSecFil = document.getElementById('fil-municipio');
    if (!selSecFil) return;

    selSecFil.innerHTML = '<option value="">📋 Todas mis secciones</option>';
    if (Array.isArray(SECCIONES_ZONA) && SECCIONES_ZONA.length > 0) {
        SECCIONES_ZONA.forEach(s => {
            const o = document.createElement('option');
            o.value = s;
            o.textContent = 'Sección ' + s;
            selSecFil.appendChild(o);
        });
        selSecFil.disabled = false;
    }
}

function toggleEjeFilter(eje) {
    if (filEjes.has(eje)) filEjes.delete(eje);
    else filEjes.add(eje);
    ['voto','comunicacion','organizacion'].forEach(e => {
        const btn = document.getElementById('feje-'+e);
        btn.className = 'filter-eje-btn' + (filEjes.has(e) ? ' active-'+e : '');
    });
    applyFilters();
}

function applyFilters() {
    filMunicipio = document.getElementById('fil-municipio').value;
    updateSelectStyle('fil-municipio', !!filMunicipio);

    eventosFiltrados = eventos.filter(ev => {
        if (filMunicipio  && (ev.seccion||'') !== filMunicipio) return false;
        if (filEjes.size > 0 && !filEjes.has(ev.eje))           return false;
        return true;
    });
    
    const hasFilter = !!(filMunicipio || filEjes.size);
    const cnt = document.getElementById('filter-count');
    if (hasFilter) {
        cnt.textContent = eventosFiltrados.length + ' evento' + (eventosFiltrados.length!==1?'s':'');
        cnt.classList.remove('hidden');
    } else {
        cnt.classList.add('hidden');
    }
    document.getElementById('btn-clear-filters').style.opacity = hasFilter ? '1' : '0.5';
    render();
}

function clearFilters() {
    filMunicipio=''; filEjes.clear();
    document.getElementById('fil-municipio').value = '';
    updateSelectStyle('fil-municipio',false);
    ['voto','comunicacion','organizacion'].forEach(e=>{ document.getElementById('feje-'+e).className='filter-eje-btn'; });
    
    document.getElementById('filter-count').classList.add('hidden');
    eventosFiltrados = [...eventos];
    render();
}

function updateSelectStyle(id, active) {
    const el = document.getElementById(id);
    if (!el) return;
    if (active) el.classList.add('active'); else el.classList.remove('active');
}

const MESES        = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
const MESES_CORTO  = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
const DIAS_SEMANA  = ['DOM','LUN','MAR','MIÉ','JUE','VIE','SÁB'];
const EJE_COLORS   = { voto:'#2d7a3e', comunicacion:'#1a73e8', organizacion:'#8b00d4' };
const EJE_LABELS   = { voto:'🗳️ Voto / Territorio', comunicacion:'📣 Comunicación', organizacion:'⚙️ Organización' };
const SUBTIPOS     = {
    voto:         ['Casa por casa','Evento de campaña','Mitin','Brigada vecinal','Recorrido territorial','Firma de compromisos','Contacto directo'],
    comunicacion: ['Entrevista de radio','Entrevista de TV','Rueda de prensa','Publicación redes sociales','Spot / grabación','Vocería','Evento mediático'],
    organizacion: ['Reunión de estructura','Capacitación','Coordinación interna','Planeación estratégica','Logística','Revisión de presupuesto','Reunión de equipo']
};

let   SEC_AGENDA    = [];   
let   SEC_FOCUSED   = -1;   

let eventos    = [];
let vista      = 'week';     
let hoy        = new Date(); hoy.setHours(0,0,0,0);
let cursor     = new Date(hoy); 
let miniCursor = new Date(hoy);
let editandoId = null;
let ejeSeleccionado = null;
let popoverEvId = null;

const fmtDate = d => `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;
const pad     = n => String(n).padStart(2,'0');
const fmtHora = t => {
    if (!t) return '';
    const [h, m] = t.split(':').map(Number);
    return `${h % 12 || 12}:${pad(m)} ${h >= 12 ? 'pm' : 'am'}`;
};
const getInicioSemana = d => {
    const r = new Date(d);
    const dia = r.getDay(); // 0=dom
    r.setDate(r.getDate() - (dia === 0 ? 6 : dia - 1)); // lunes
    r.setHours(0,0,0,0);
    return r;
};

// ── Cargar eventos ─────────────────────────────────
async function cargarEventos() {
    try {
        const res  = await fetch(PROCESAR_URL + '?action=list');
        const data = await res.json();
        if (data.ok) {
            eventosOriginales = data.eventos.map(e => ({...e, id: parseInt(e.id)}));
            aplicarModoAgenda();
        }
    } catch(e) { console.error(e); }
}

// Separa propios vs asignados según el toggle
function esEventoPropio(ev) {
    return (ev.creado_por_id && parseInt(ev.creado_por_id) === USUARIO_ID) ||
           (!ev.creado_por_id && ev.creado_por_rol === 'coordinador_zona');
}

function aplicarModoAgenda() {
    if (!modoAsignados) {
        // Mi Agenda: solo los que creó el coordinador de zona
        eventos = eventosOriginales.filter(ev => esEventoPropio(ev));
    } else {
        // Ver Asignados: los que creó estatal o regional para esta zona
        eventos = eventosOriginales.filter(ev => !esEventoPropio(ev));
    }
    eventosFiltrados = [...eventos];

    // FIX: badge en el botón toggle mostrando cuántos eventos asignados hay
    const asignadosCount = eventosOriginales.filter(ev => !esEventoPropio(ev)).length;
    const btn = document.getElementById('toggle-zona-btn');
    let badge = document.getElementById('badge-asignados-count');
    if (!badge && asignadosCount > 0) {
        badge = document.createElement('span');
        badge.id = 'badge-asignados-count';
        badge.style.cssText = 'background:var(--comunicacion);color:white;border-radius:10px;font-size:10px;font-weight:700;padding:1px 6px;margin-left:2px;line-height:1.6;';
        btn.appendChild(badge);
    }
    if (badge) {
        badge.textContent = asignadosCount > 0 ? asignadosCount : '';
        badge.style.display = asignadosCount > 0 ? 'inline' : 'none';
    }

    actualizarContadorZ();
    render();
}

function toggleModoAgenda() {
    modoAsignados = !modoAsignados;
    const track = document.getElementById('toggle-track-z');
    const label = document.getElementById('toggle-label-z');
    const btn   = document.getElementById('toggle-zona-btn');
    if (modoAsignados) {
        track.classList.remove('on');
        label.textContent = 'Ver Asignados';
        btn.classList.add('asignados-mode');
    } else {
        track.classList.add('on');
        label.textContent = 'Mi Agenda';
        btn.classList.remove('asignados-mode');
    }
    aplicarModoAgenda();
}

function actualizarContadorZ() {
    const ctr = document.getElementById('ev-counter-z');
    if (!ctr) return;
    // El total siempre refleja TODOS los eventos (propios + asignados),
    // sin importar el modo del toggle.
    const total = eventosOriginales.length;
    const shown = eventosFiltrados.length;
    if (total > 0) {
        ctr.innerHTML = `Mostrando <b>${shown}</b> de <b>${total}</b> eventos`;
        ctr.style.display = '';
    } else {
        ctr.style.display = 'none';
    }
}

// ── Render principal ──────────────────────────────────
function render() {
    if (vista === 'month') renderMes();
    else renderSemana();
    renderMiniCal();
    renderBalance();
    renderUpcoming();
    actualizarContadorZ();
}

// ══════════════════════════════════════════════════════
//  VISTA MES
// ══════════════════════════════════════════════════════
function renderMes() {
    const year = cursor.getFullYear(), month = cursor.getMonth();
    document.getElementById('topbar-label').textContent = `${MESES[month]} ${year}`;

    const primerDia = new Date(year, month, 1);
    const inicioGrid = new Date(primerDia);
    const dow = primerDia.getDay();
    inicioGrid.setDate(primerDia.getDate() - (dow === 0 ? 6 : dow - 1)); 

    const dias = [];
    const d = new Date(inicioGrid);
    while (dias.length < 42) { dias.push(new Date(d)); d.setDate(d.getDate() + 1); }

    const DOW = ['LUN','MAR','MIÉ','JUE','VIE','SÁB','DOM'];
    let html = `<div class="month-view">
      <div class="month-dow-row">${DOW.map(d=>`<div class="month-dow">${d}</div>`).join('')}</div>
      <div class="month-grid">`;

    dias.forEach(dia => {
        const fechaStr = fmtDate(dia);
        const esHoy    = fechaStr === fmtDate(hoy);
        const otroMes  = dia.getMonth() !== month;
        const evsDia   = eventosFiltrados.filter(e => e.fecha === fechaStr)
                                .sort((a,b)=>(a.inicio||'').localeCompare(b.inicio||''));
        const MAX = 3;
        const visible = evsDia.slice(0, MAX);
        const extra   = evsDia.length - MAX;

        html += `<div class="month-cell${esHoy?' today':''}${otroMes?' other-month':''}" 
                      onclick="cellClick('${fechaStr}',event)">
          <div class="cell-num">${dia.getDate()}</div>
          <div class="cell-events">`;

        visible.forEach(ev => {
            html += `<span class="cell-pill ${ev.eje}" 
                          onclick="event.stopPropagation();abrirPopover(event,${ev.id})"
                          title="${ev.titulo}">${ev.inicio ? fmtHora(ev.inicio)+' ' : ''}${ev.titulo}</span>`;
        });
        if (extra > 0) html += `<span class="cell-more" onclick="event.stopPropagation();cellClick('${fechaStr}',event)">+${extra} más</span>`;

        html += `</div></div>`;
    });
    html += `</div></div>`;
    document.getElementById('cal-main').innerHTML = html;
}

// ══════════════════════════════════════════════════════
//  VISTA SEMANA
// ══════════════════════════════════════════════════════
function renderSemana() {
    const inicioSem = getInicioSemana(cursor);
    const dias = Array.from({length:7}, (_,i) => {
        const d = new Date(inicioSem); d.setDate(inicioSem.getDate() + i); return d;
    });
    const fin = new Date(dias[6]);
    const mismoMes = inicioSem.getMonth() === fin.getMonth();
    document.getElementById('topbar-label').textContent = mismoMes
        ? `${inicioSem.getDate()} – ${fin.getDate()} ${MESES[fin.getMonth()]} ${fin.getFullYear()}`
        : `${inicioSem.getDate()} ${MESES_CORTO[inicioSem.getMonth()]} – ${fin.getDate()} ${MESES_CORTO[fin.getMonth()]} ${fin.getFullYear()}`;

    const DOW = ['LUN','MAR','MIÉ','JUE','VIE','SÁB','DOM'];
    const allDayHtml = dias.map((dia,i) => {
        const f = fmtDate(dia);
        const evs = eventosFiltrados.filter(e => e.fecha === f && !e.inicio);
        return `<div class="week-allday-cell">${evs.map(ev=>
            `<span class="cell-pill ${ev.eje}" style="font-size:10px;" onclick="abrirPopover(event,${ev.id})">${ev.titulo}</span>`
        ).join('')}</div>`;
    }).join('');

    let html = `<div class="week-view">
      <div class="week-header-row">
        <div class="week-header-time"></div>
        ${dias.map((dia,i)=>{
            const f=fmtDate(dia); const esHoy=f===fmtDate(hoy);
            return `<div class="week-header-day${esHoy?' today':''}">
              <div class="whd-dow">${DOW[i]}</div>
              <div class="whd-num">${dia.getDate()}</div>
            </div>`;
        }).join('')}
      </div>
      <div class="week-allday-row">
        <div class="week-allday-label">todo el día</div>
        ${allDayHtml}
      </div>
      <div class="week-body" id="week-body-inner">
        <div class="week-time-col">
          ${Array.from({length:24},(_,h)=>`<div class="week-time-slot">${h===0?'':h<12?h+' am':h===12?'12 pm':(h-12)+' pm'}</div>`).join('')}
        </div>`;

    dias.forEach((dia,i) => {
        const f = fmtDate(dia); const esHoy = f === fmtDate(hoy);
        const evsDia = eventosFiltrados.filter(e => e.fecha === f && e.inicio);
        html += `<div class="week-day-col${esHoy?' today':''}" 
                      onclick="cellClick('${f}',event)">
          ${Array.from({length:24},(_,h)=>`<div class="week-hour-row" data-hour="${h}"></div>`).join('')}`;

        evsDia.forEach(ev => {
            const [ih, im] = (ev.inicio||'00:00').split(':').map(Number);
            let durMin = 60;
            if (ev.fin) { const [fh,fm]=ev.fin.split(':').map(Number); durMin=Math.max(30,(fh*60+fm)-(ih*60+im)); }
            else if (ev.horas) durMin = parseFloat(ev.horas) * 60;
            const top  = (ih * 48) + (im / 60 * 48);
            const height = Math.max(24, durMin / 60 * 48);
            html += `<div class="week-event ${ev.eje}" 
                          style="top:${top}px;height:${height}px;"
                          onclick="event.stopPropagation();abrirPopover(event,${ev.id})">
              <div style="font-weight:700;line-height:1.2;">${ev.titulo}</div>
              ${ev.inicio?`<div style="font-size:10px;opacity:0.8;">${fmtHora(ev.inicio)}${ev.fin?' – '+fmtHora(ev.fin):''}</div>`:''}
            </div>`;
        });
        html += `</div>`;
    });
    html += `</div></div>`;
    document.getElementById('cal-main').innerHTML = html;

    setTimeout(() => {
        const wb = document.getElementById('week-body-inner');
        if (wb) wb.scrollTop = 8 * 48;
    }, 50);
}

function renderMiniCal() {
    const year = miniCursor.getFullYear(), month = miniCursor.getMonth();
    document.getElementById('mini-cal-label').textContent = `${MESES[month].substring(0,3)} ${year}`;
    const primerDia = new Date(year, month, 1);
    const dow = primerDia.getDay();
    const inicio = new Date(primerDia);
    inicio.setDate(primerDia.getDate() - (dow === 0 ? 6 : dow - 1));

    const DOW = ['L','M','X','J','V','S','D'];
    let html = DOW.map(d=>`<div class="mini-dow">${d}</div>`).join('');
    const d = new Date(inicio);
    while (true) {
        const f = fmtDate(d);
        const esHoy   = f === fmtDate(hoy);
        const otroMes = d.getMonth() !== month;
        const isSel   = (vista==='week')
            ? (()=>{ const s=getInicioSemana(cursor); const e=new Date(s); e.setDate(s.getDate()+6); return d>=s&&d<=e; })()
            : (d.getMonth()===cursor.getMonth()&&d.getFullYear()===cursor.getFullYear());
        html += `<div class="mini-day${otroMes?' other-month':''}${esHoy?' today':''}${isSel&&!esHoy?' selected':''}" 
                      onclick="miniClickDia(new Date(${d.getFullYear()},${d.getMonth()},${d.getDate()}))">${d.getDate()}</div>`;
        d.setDate(d.getDate() + 1);
        if (d.getMonth() !== month && d.getDay() === 1) break;
        if (d > new Date(year, month+1, 14)) break;
    }
    document.getElementById('mini-grid').innerHTML = html;
}

function renderBalance() {
    let evsSem;
    if (vista === 'month') {
        const prefijoMes = `${cursor.getFullYear()}-${pad(cursor.getMonth()+1)}-`;
        evsSem = eventosFiltrados.filter(e => (e.fecha||'').startsWith(prefijoMes));
    } else {
        const inicioSem = getInicioSemana(cursor);
        const fechas = Array.from({length:7},(_,i)=>{ const d=new Date(inicioSem); d.setDate(inicioSem.getDate()+i); return fmtDate(d); });
        evsSem = eventosFiltrados.filter(e => fechas.includes(e.fecha));
    }
    const _tituloBal = document.getElementById('balance-box-title');
    if (_tituloBal) _tituloBal.textContent = vista === 'month' ? 'Balance del mes' : 'Balance semanal';
    let total=0; const h={voto:0,comunicacion:0,organizacion:0};
    evsSem.forEach(e=>{ const v=parseFloat(e.horas)||1; h[e.eje]=(h[e.eje]||0)+v; total+=v; });
    ['voto','comunicacion','organizacion'].forEach(eje=>{
        const p = total>0 ? Math.round(h[eje]/total*100) : 0;
        document.getElementById('bpct-'+eje).textContent = p+'%';
        document.getElementById('bbar-'+eje).style.width = p+'%';
    });
    const METAS={voto:40,comunicacion:30,organizacion:30};
    const ok = total>0 && Object.keys(METAS).every(eje=>Math.abs((total>0?Math.round(h[eje]/total*100):0)-METAS[eje])<=12);
    const b = document.getElementById('badge-bal');
    b.className = 'badge-bal ' + (ok?'ok':'bad');
    b.innerHTML = ok ? '<i class="fas fa-check-circle"></i> Balanceado' : '<i class="fas fa-triangle-exclamation"></i> Desbalance';
}

function renderUpcoming() {
    const hoyStr = fmtDate(hoy);
    // FIX: usamos eventosOriginales para mostrar TODOS los próximos eventos
    // (propios + asignados por estatal/regional), sin importar el modo del toggle.
    const proximos = eventosOriginales
        .filter(e => e.fecha >= hoyStr)
        .sort((a,b)=>a.fecha.localeCompare(b.fecha)||(a.inicio||'').localeCompare(b.inicio||''))
        .slice(0,5);
    const ul = document.getElementById('upcoming-list');
    if (!proximos.length) { ul.innerHTML='<div style="font-size:12px;color:var(--text-light);padding:8px 0;">Sin eventos próximos</div>'; return; }
    ul.innerHTML = proximos.map(ev=>{
        const c = EJE_COLORS[ev.eje]||'#666';
        const fechaD = new Date(ev.fecha+'T12:00:00');
        const label  = ev.fecha===hoyStr ? 'Hoy' : `${fechaD.getDate()} ${MESES_CORTO[fechaD.getMonth()]}`;
        // Indicador visual si el evento es asignado (no propio)
        const esPropio = esEventoPropio(ev);
        const tagAsignado = !esPropio
            ? `<span style="font-size:9px;font-weight:700;background:var(--comunicacion-bg);color:var(--comunicacion);border-radius:6px;padding:1px 5px;margin-left:4px;">Estatal</span>`
            : '';
        return `<div class="upcoming-item" style="cursor:pointer;" onclick="abrirPopoverById(${ev.id})">
          <div class="upcoming-dot" style="background:${c};"></div>
          <div class="upcoming-info">
            <div class="upcoming-ev-title">${ev.titulo}${tagAsignado}</div>
            <div class="upcoming-ev-date">${label}${ev.inicio?' · '+fmtHora(ev.inicio):''}</div>
          </div>
        </div>`;
    }).join('');
}

function navegar(dir) {
    if (vista === 'month') { cursor.setMonth(cursor.getMonth() + dir); miniCursor = new Date(cursor); }
    else { cursor.setDate(cursor.getDate() + dir * 7); miniCursor = new Date(cursor); }
    render();
}
function irHoy() { cursor = new Date(hoy); miniCursor = new Date(hoy); render(); }
function setVista(v) {
    vista = v;
    document.getElementById('vbtn-month').className = 'view-btn' + (v==='month'?' active':'');
    document.getElementById('vbtn-week').className  = 'view-btn' + (v==='week' ?' active':'');
    render();
}
function miniNavegar(dir) { miniCursor.setMonth(miniCursor.getMonth()+dir); renderMiniCal(); }
function miniClickDia(d) { cursor = new Date(d); render(); }
function cellClick(fechaStr, e) {
    if (e.target.closest('.cell-pill,.week-event,.cell-more')) return;
    abrirModal(null, fechaStr);
}

// ══════════════════════════════════════════════════════
//  POPOVER
// ══════════════════════════════════════════════════════
function abrirPopover(e, id) {
    e.stopPropagation();
    cerrarPopover();
    // FIX: buscar en eventosOriginales como fallback para eventos asignados
    // que no están en `eventos` cuando el modo es "Mi Agenda"
    const ev = eventos.find(x => x.id === id) || eventosOriginales.find(x => x.id === id);
    if (!ev) return;
    popoverEvId = id;
    const c = EJE_COLORS[ev.eje]||'#666';
    document.getElementById('pop-bar').style.background = c;
    document.getElementById('pop-title').textContent = ev.titulo;
    const b = document.getElementById('pop-badge');
    b.textContent = EJE_LABELS[ev.eje]||ev.eje;
    b.style.cssText = `background:${c}18;color:${c};border:1px solid ${c}40;`;
    const setRow = (id, val, icon) => {
        const row = document.getElementById(id);
        row.style.display = val ? 'flex' : 'none';
        if (val) row.querySelector('span').textContent = val;
    };
    const fechaD = new Date(ev.fecha+'T12:00:00');
    setRow('pop-fecha', `${fechaD.getDate()} de ${MESES[fechaD.getMonth()]} ${fechaD.getFullYear()}`);
    setRow('pop-hora', (ev.inicio||ev.fin) ? `${fmtHora(ev.inicio)}${ev.fin?' – '+fmtHora(ev.fin):''}${ev.horas?' ('+ev.horas+'h)':''}` : null);
    
    const lugarDetalle = [ev.estado, ev.region, ev.zona, ev.municipio, ev.seccion?'Sec.'+ev.seccion:null].filter(Boolean).join(' > ');
    setRow('pop-lugar', lugarDetalle || null);
    
    setRow('pop-resp', ev.responsable||null);
    setRow('pop-activacion', ev.activacion||null); 
    setRow('pop-notas', ev.notas||null);
    // ── Quién lo hizo ──
    const ROL_LABELS = {
        'coordinador_zona':     'Coordinador de Zona',
        'coordinador_regional': 'Coordinador Regional',
        'coordinador_estatal':  'Coordinador Estatal',
        'admin':                'Administrador',
        'superadmin':           'Administrador'
    };
    const rolLabel  = ROL_LABELS[ev.creado_por_rol] || ev.creado_por_rol || null;
    const nombreLabel = ev.creado_por_nombre ? `${ev.creado_por_nombre} (${rolLabel})` : rolLabel;
    setRow('pop-creador', nombreLabel);
    // Solo el creador puede eliminar/editar; asignados = solo Ver
    const _popDel  = document.getElementById('pop-del');
    const _popEdit = document.getElementById('pop-edit');
    if (esEventoPropio(ev)) {
        _popDel.style.display = '';
        _popDel.disabled = false; _popDel.style.opacity = ''; _popDel.style.cursor = '';
        _popDel.onclick  = () => eliminarEvento(id);
        _popEdit.textContent = 'Editar';
        _popEdit.onclick = () => { cerrarPopover(); abrirModal(id); };
    } else {
        _popDel.style.display = 'none';
        _popEdit.textContent = 'Ver';
        _popEdit.onclick = () => { cerrarPopover(); abrirModal(id); };
    }
    const pop = document.getElementById('ev-popover');
    pop.style.display = 'block';
    
    const rect = e.currentTarget?.getBoundingClientRect ? e.currentTarget.getBoundingClientRect() : {left:e.clientX,top:e.clientY,width:0};
    let left = rect.left + rect.width + 8;
    let top  = rect.top;
    if (left + 320 > window.innerWidth) left = rect.left - 320 - 8;
    if (top + 350 > window.innerHeight) top = window.innerHeight - 360;
    pop.style.left = Math.max(8, left) + 'px';
    pop.style.top  = Math.max(8, top) + 'px';
}
function abrirPopoverById(id) {
    const fakeE = { stopPropagation:()=>{}, clientX:window.innerWidth/2, clientY:window.innerHeight/2,
        currentTarget:{ getBoundingClientRect:()=>({left:window.innerWidth/2-150,top:200,width:0}) } };
    abrirPopover(fakeE, id);
}
function cerrarPopover() {
    document.getElementById('ev-popover').style.display = 'none';
    popoverEvId = null;
}
document.addEventListener('click', e => {
    const pop = document.getElementById('ev-popover');
    if (pop.style.display!=='none' && !pop.contains(e.target)) cerrarPopover();
});

// ══════════════════════════════════════════════════════
//  MODAL CREAR/EDITAR (CORREGIDO PARA CARGA DINÁMICA)
// ══════════════════════════════════════════════════════

// Carga las secciones de la zona directo desde la constante PHP inyectada
function cargarSeccionesDeZona() {
    SEC_AGENDA = Array.isArray(SECCIONES_ZONA) ? [...SECCIONES_ZONA] : [];
    const selSecDisplay = document.getElementById('sec-agenda-display');
    if (SEC_AGENDA.length > 0) {
        secAgendaActualizarContador(SEC_AGENDA.length);
        secAgendaRenderOpts(SEC_AGENDA);
        if (selSecDisplay) selSecDisplay.placeholder = `— Selecciona sección (${SEC_AGENDA.length}) —`;
    } else {
        secAgendaReset('Sin secciones asignadas');
    }
}

// Mantener alias por compatibilidad con el guardado (f-municipio se envía vacío)
async function cargarMunicipiosModal() { cargarSeccionesDeZona(); }
async function cargarSeccionesModal(_municipio) { cargarSeccionesDeZona(); }

async function abrirModal(id=null, fechaPre=null) {
    cerrarPopover();
    editandoId = id;
    ejeSeleccionado = null;
    document.getElementById('modal-title').textContent = id ? 'Editar Evento' : 'Nuevo Evento';
    const btnDel = document.getElementById('btn-del-ev');

    ['f-titulo','f-inicio','f-fin','f-horas','f-responsable','f-activacion','f-notas'].forEach(x=>document.getElementById(x).value='');
    document.getElementById('f-registro-qr').checked = false;
    if (id) {
        fetch(`../../api/registro_asistentes/procesar.php?action=list&tipo=evento`)
            .then(r=>r.json())
            .then(d=>{
                if (d.ok && d.items.some(it => it.origen_id === id)) {
                    document.getElementById('f-registro-qr').checked = true;
                }
            }).catch(()=>{});
    }
    
    document.querySelectorAll('.eje-pill').forEach(b=>b.className='eje-pill');
    document.getElementById('f-subtipo').innerHTML='<option value="">— Selecciona un eje primero —</option>';

    // Reset evidencia (fotos/PDF), georreferencia y estado "Realizado"
    fotosActualesEv = []; pdfsActualesEv = []; enlacesActualesEv = [];
    renderFotosPreviewEv(); renderPdfsPreviewEv(); renderEnlacesPreviewEv();
    const _elLat = document.getElementById('f-lat'), _elLng = document.getElementById('f-lng');
    if (_elLat) _elLat.value = ''; if (_elLng) _elLng.value = '';
    const _elMapSearch = document.getElementById('map-search-input'), _elMapRes = document.getElementById('map-search-results');
    if (_elMapSearch) _elMapSearch.value = ''; if (_elMapRes) _elMapRes.style.display = 'none';
    aplicarBloqueoRealizado(false, false);

    // Cargar secciones de la zona directamente
    cargarSeccionesDeZona();

    if (id) {
        // FIX: buscar en eventosOriginales como fallback para eventos asignados
        const ev = eventos.find(e=>e.id===id) || eventosOriginales.find(e=>e.id===id);
        if(!ev) return;
        selEje(ev.eje);
        setTimeout(()=>{document.getElementById('f-subtipo').value=ev.subtipo||'';},50);
        
        // Normalizar inicio/fin: quitar segundos si viene HH:MM:SS
        const _norm = v => v ? v.substring(0,5) : '';
        ['titulo','fecha','horas','responsable','activacion','notas'].forEach(k=>{
            const el=document.getElementById('f-'+k); if(el) el.value=ev[k]||'';
        });
        const _elIni = document.getElementById('f-inicio');
        const _elFin = document.getElementById('f-fin');
        if (_elIni) _elIni.value = _norm(ev.inicio);
        if (_elFin) _elFin.value = _norm(ev.fin);
        
        if(ev.seccion){
            document.getElementById('f-seccion').value = ev.seccion;
            document.getElementById('sec-agenda-display').value = ev.seccion;
            document.getElementById('sec-agenda-display').placeholder = '';
        }
        
        // Cargar georreferencia y evidencia guardadas
        if (ev.lat) document.getElementById('f-lat').value = ev.lat;
        if (ev.lng) document.getElementById('f-lng').value = ev.lng;
        try {
            const _fotosGuardadas = typeof ev.evidencia_fotos === 'string' ? JSON.parse(ev.evidencia_fotos||'[]') : (ev.evidencia_fotos||[]);
            fotosActualesEv = (_fotosGuardadas||[]).map(d => ({dataUrl:d, name:'foto'}));
        } catch(_e) { fotosActualesEv = []; }
        try {
            const _pdfsGuardados = typeof ev.evidencia_pdfs === 'string' ? JSON.parse(ev.evidencia_pdfs||'[]') : (ev.evidencia_pdfs||[]);
            pdfsActualesEv = (_pdfsGuardados||[]);
        } catch(_e) { pdfsActualesEv = []; }
        try {
            const _enlacesGuardados = typeof ev.evidencia_enlaces === 'string' ? JSON.parse(ev.evidencia_enlaces||'[]') : (ev.evidencia_enlaces||[]);
            enlacesActualesEv = (_enlacesGuardados||[]);
        } catch(_e) { enlacesActualesEv = []; }
        renderFotosPreviewEv(); renderPdfsPreviewEv(); renderEnlacesPreviewEv();

        // Solo el creador del evento puede eliminarlo
        const _esPropioZona = esEventoPropio(ev);
        if (_esPropioZona) {
            btnDel.style.visibility='visible';
            btnDel.onclick=()=>eliminarEvento(id);
        } else {
            btnDel.style.visibility='hidden';
            btnDel.onclick=null;
        }
        // Estado "Realizado": bloquea edición/eliminación
        const _estaRealizado = (ev.realizado==1 || ev.realizado==='1');
        aplicarBloqueoRealizado(_estaRealizado, _esPropioZona);
    } else {
        document.getElementById('f-fecha').value = fechaPre || fmtDate(hoy);
        btnDel.style.visibility='hidden';
    }
    document.getElementById('modal-form').classList.add('open');
    setTimeout(()=>iniciarMapaEvento(), 150);
}

function cerrarModal() { document.getElementById('modal-form').classList.remove('open'); destruirMapaEvento(); }

function selEje(eje) {
    ejeSeleccionado = eje;
    document.querySelectorAll('.eje-pill').forEach(b=>b.className='eje-pill'+(b.dataset.eje===eje?' sel-'+eje:''));
    const sel=document.getElementById('f-subtipo');
    sel.innerHTML='<option value="">Selecciona subtipo…</option>';
    (SUBTIPOS[eje]||[]).forEach(s=>{const o=document.createElement('option');o.value=s;o.textContent=s;sel.appendChild(o);});
}

function limpiarErrorFin(){
    const finEl=document.getElementById('f-fin');
    finEl.style.borderColor=''; finEl.style.background=''; finEl.title='';
    finEl.setCustomValidity && finEl.setCustomValidity('');
    const errMsg=document.getElementById('hora-error-msg');
    if(errMsg) errMsg.style.display='none';
    calcHoras();
}

function calcHoras() {
    const ini=document.getElementById('f-inicio').value, fin=document.getElementById('f-fin').value;
    const inEl=document.getElementById('f-inicio'), finEl=document.getElementById('f-fin');
    if(ini&&fin){
        const[hi,mi]=ini.split(':').map(Number),[hf,mf]=fin.split(':').map(Number);
        const d=(hf*60+mf-hi*60-mi)/60;
        if(d<=0){
            finEl.style.borderColor='#dc2626';
            finEl.style.background='#fef2f2';
            finEl.title='El horario de fin debe ser posterior al inicio';
            document.getElementById('f-horas').value='';
        } else {
            finEl.style.borderColor='';
            finEl.style.background='';
            finEl.title='';
            document.getElementById('f-horas').value=d.toFixed(1);
        }
    } else {
        document.getElementById('f-fin').style.borderColor='';
        document.getElementById('f-fin').style.background='';
    }
}

// ══════════════════════════════════════════════════════════
//  BUSCADOR SECCIÓN — AGENDA
// ══════════════════════════════════════════════════════════

function secAgendaReset(msg = 'Sin secciones asignadas', loading = false) {
    SEC_AGENDA  = [];
    SEC_FOCUSED = -1;
    document.getElementById('f-seccion').value            = '';
    document.getElementById('sec-agenda-display').value   = '';
    document.getElementById('sec-agenda-display').placeholder = '— ' + msg + ' —';
    document.getElementById('sec-agenda-search').value    = '';
    secAgendaActualizarContador(0);
    const opts = document.getElementById('sec-agenda-options');
    opts.innerHTML = loading
        ? `<div class="sec-agenda-loading"><i class="fas fa-spinner fa-spin"></i> ${msg}</div>`
        : `<div class="sec-agenda-empty">${msg}</div>`;
    secAgendaCerrar();
}

function secAgendaActualizarContador(n) {
    const el = document.getElementById('f-sec-contador');
    if (el) el.textContent = n > 0 ? `(${n} secciones)` : '';
}

function secAgendaRenderOpts(lista, busqueda = '') {
    const opts   = document.getElementById('sec-agenda-options');
    const actual = document.getElementById('f-seccion').value;
    SEC_FOCUSED  = -1;
    if (!lista.length) {
        opts.innerHTML = `<div class="sec-agenda-empty">Sin resultados${busqueda ? ` para "${busqueda}"` : ''}</div>`;
        return;
    }
    opts.innerHTML = lista.map(s => {
        const label = busqueda.trim()
            ? s.replace(new RegExp(`(${busqueda.trim()})`, 'gi'), '<em>$1</em>')
            : s;
        return `<div class="sec-agenda-opt${String(s) === String(actual) ? ' selected' : ''}"
                     onmousedown="secAgendaSeleccionar('${s}')">${label}</div>`;
    }).join('');
}

function secAgendaFiltrar(q) {
    if (!SEC_AGENDA.length) return;
    const filtradas = q.trim()
        ? SEC_AGENDA.filter(s => s.toString().toLowerCase().includes(q.trim().toLowerCase()))
        : SEC_AGENDA;
    secAgendaRenderOpts(filtradas, q);
}

function secAgendaSeleccionar(val) {
    document.getElementById('f-seccion').value          = val;
    document.getElementById('f-municipio').value        = '';   // municipio ya no aplica
    document.getElementById('sec-agenda-display').value = val;
    document.getElementById('sec-agenda-display').placeholder = '';
    secAgendaCerrar();
    document.querySelectorAll('.sec-agenda-opt').forEach(el =>
        el.classList.toggle('selected', el.textContent === val)
    );
}

function secAgendaToggle() {
    const wrap = document.getElementById('sec-agenda-wrap');
    if (!SEC_AGENDA.length) return;
    if (wrap.classList.contains('open')) { secAgendaCerrar(); }
    else {
        wrap.classList.add('open');
        const search = document.getElementById('sec-agenda-search');
        search.value = '';
        secAgendaRenderOpts(SEC_AGENDA);
        setTimeout(() => search.focus(), 50);
    }
}

function secAgendaCerrar() {
    document.getElementById('sec-agenda-wrap')?.classList.remove('open');
}

function secAgendaNavegar(e) {
    const items = document.querySelectorAll('.sec-agenda-opt');
    if (!items.length) return;
    if (e.key === 'ArrowDown') {
        SEC_FOCUSED = Math.min(SEC_FOCUSED + 1, items.length - 1);
    } else if (e.key === 'ArrowUp') {
        SEC_FOCUSED = Math.max(SEC_FOCUSED - 1, 0);
    } else if (e.key === 'Enter' && SEC_FOCUSED >= 0) {
        e.preventDefault();
        secAgendaSeleccionar(items[SEC_FOCUSED].textContent);
        return;
    } else if (e.key === 'Escape') {
        secAgendaCerrar(); return;
    }
    items.forEach((el, i) => el.classList.toggle('focused', i === SEC_FOCUSED));
    if (SEC_FOCUSED >= 0) items[SEC_FOCUSED].scrollIntoView({ block: 'nearest' });
}

document.addEventListener('click', e => {
    if (!e.target.closest('#sec-agenda-wrap')) secAgendaCerrar();
});

// ══════════════════════════════════════════════════════
//  EVIDENCIA (fotos/PDF) + GEORREFERENCIA + REALIZADO
// ══════════════════════════════════════════════════════
let fotosActualesEv = [];
let pdfsActualesEv = [];
let mapaEventoInst = null, marcadorEvento = null;

function dragOverEv(e, zoneId){ e.preventDefault(); document.getElementById(zoneId).classList.add('drag-over'); }
function dragLeaveEv(e, zoneId){ document.getElementById(zoneId).classList.remove('drag-over'); }
function dropFotosEv(e){ e.preventDefault(); document.getElementById('drop-zone-foto').classList.remove('drag-over'); procesarFotosEv(e.dataTransfer.files); }
function dropPdfsEv(e){ e.preventDefault(); document.getElementById('drop-zone-pdf').classList.remove('drag-over'); procesarPdfsEv(e.dataTransfer.files); }

function procesarFotosEv(files){
    [...files].forEach(file=>{
        if(!file.type.startsWith('image/')) return;
        if(file.size > 5*1024*1024){ alert(`"${file.name}" excede 5MB.`); return; }
        const reader = new FileReader();
        reader.onload = ev => { fotosActualesEv.push({dataUrl:ev.target.result, name:file.name}); renderFotosPreviewEv(); };
        reader.readAsDataURL(file);
    });
}
function renderFotosPreviewEv(){
    const grid = document.getElementById('fotos-preview');
    if(!grid) return;
    grid.innerHTML = fotosActualesEv.map((f,i)=>`
        <div class="foto-thumb">
            <img src="${f.dataUrl}" alt="evidencia ${i+1}">
            <button class="remove-foto" type="button" onclick="quitarFotoEv(${i})"><i class="fas fa-times"></i></button>
        </div>`).join('');
    const counter = document.getElementById('foto-counter');
    if(counter) counter.textContent = fotosActualesEv.length ? `${fotosActualesEv.length} foto(s)` : '';
}
function quitarFotoEv(i){ fotosActualesEv.splice(i,1); renderFotosPreviewEv(); }

function procesarPdfsEv(files){
    [...files].forEach(file=>{
        if(file.type !== 'application/pdf'){ alert(`"${file.name}" no es un PDF.`); return; }
        if(file.size > 8*1024*1024){ alert(`"${file.name}" excede 8MB.`); return; }
        const reader = new FileReader();
        reader.onload = ev => { pdfsActualesEv.push({dataUrl:ev.target.result, name:file.name}); renderPdfsPreviewEv(); };
        reader.readAsDataURL(file);
    });
}
function renderPdfsPreviewEv(){
    const list = document.getElementById('pdfs-preview');
    if(!list) return;
    list.innerHTML = pdfsActualesEv.map((pfile,i)=>`
        <div class="pdf-item">
            <i class="fas fa-file-pdf" style="color:#dc2626;"></i>
            <span>${pfile.name}</span>
            <button type="button" onclick="quitarPdfEv(${i})"><i class="fas fa-times"></i></button>
        </div>`).join('');
}
function quitarPdfEv(i){ pdfsActualesEv.splice(i,1); renderPdfsPreviewEv(); }

function agregarEnlaceEv(){
    const input = document.getElementById('enlace-input-ev');
    const url = (input.value||'').trim();
    if(!url) return;
    try{ new URL(url); }catch(_e){ alert('Ingresa una URL válida (debe iniciar con http:// o https://).'); return; }
    enlacesActualesEv.push(url);
    input.value='';
    renderEnlacesPreviewEv();
}
function renderEnlacesPreviewEv(){
    const list = document.getElementById('enlaces-preview-ev');
    if(!list) return;
    list.innerHTML = enlacesActualesEv.map((url,i)=>`
        <div style="display:flex;align-items:center;gap:8px;padding:8px 10px;border:1px solid #eee;border-radius:8px;margin-bottom:6px;background:#fafafa;">
            <i class="fas fa-link" style="color:var(--primary);flex-shrink:0;"></i>
            <a href="${url}" target="_blank" rel="noopener" style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px;color:#333;">${url}</a>
            <button type="button" onclick="quitarEnlaceEv(${i})" style="background:none;border:none;cursor:pointer;color:#999;"><i class="fas fa-times"></i></button>
        </div>`).join('');
}
function quitarEnlaceEv(i){ enlacesActualesEv.splice(i,1); renderEnlacesPreviewEv(); }

async function buscarEnMapaEv(){
    const q = document.getElementById('map-search-input').value.trim();
    if(!q) return;
    const resultsEl = document.getElementById('map-search-results');
    resultsEl.style.display = 'block';
    resultsEl.innerHTML = '<div style="padding:10px 14px;font-size:13px;color:#999;"><i class="fas fa-spinner fa-spin"></i> Buscando…</div>';
    try{
        const resp = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(q)}&limit=6&accept-language=es`, {headers:{'Accept-Language':'es'}});
        const data = await resp.json();
        if(!data.length){ resultsEl.innerHTML = '<div style="padding:10px 14px;font-size:13px;color:#999;">Sin resultados.</div>'; return; }
        resultsEl.innerHTML = data.map(r => `
            <div onclick="seleccionarLugarEv(${r.lat},${r.lon},'${r.display_name.replace(/'/g,"\\'")}')"
                 style="padding:10px 14px;font-size:13px;cursor:pointer;border-bottom:1px solid #f5f5f5;display:flex;gap:10px;align-items:flex-start;"
                 onmouseover="this.style.background='#f4fcf6'" onmouseout="this.style.background='white'">
                <i class="fas fa-map-marker-alt" style="color:var(--primary);margin-top:2px;flex-shrink:0;"></i><span>${r.display_name}</span>
            </div>`).join('');
    }catch(e){ resultsEl.innerHTML = '<div style="padding:10px 14px;font-size:13px;color:#e53e3e;">Error al buscar.</div>'; }
}
function seleccionarLugarEv(lat,lng,nombre){
    lat = parseFloat(lat); lng = parseFloat(lng);
    document.getElementById('f-lat').value = lat.toFixed(6);
    document.getElementById('f-lng').value = lng.toFixed(6);
    document.getElementById('map-search-input').value = nombre;
    document.getElementById('map-search-results').style.display = 'none';
    if(!mapaEventoInst) iniciarMapaEvento();
    if(marcadorEvento) mapaEventoInst.removeLayer(marcadorEvento);
    marcadorEvento = L.marker([lat,lng]).addTo(mapaEventoInst);
    mapaEventoInst.setView([lat,lng], 15);
}
document.addEventListener('click', function(e){
    const inp = document.getElementById('map-search-input'), res = document.getElementById('map-search-results');
    if(inp && res && !inp.contains(e.target) && !res.contains(e.target)) res.style.display = 'none';
});
function iniciarMapaEvento(){
    const el = document.getElementById('mapa-evento'); if(!el || mapaEventoInst) return;
    let lat0 = 23.6345, lng0 = -102.5528;
    lat0 = parseFloat(document.getElementById('f-lat').value) || lat0;
    lng0 = parseFloat(document.getElementById('f-lng').value) || lng0;
    mapaEventoInst = L.map('mapa-evento').setView([lat0,lng0], (document.getElementById('f-lat').value ? 14 : 5));
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'© OpenStreetMap'}).addTo(mapaEventoInst);
    if(document.getElementById('f-lat').value) marcadorEvento = L.marker([lat0,lng0]).addTo(mapaEventoInst);
    mapaEventoInst.on('click', function(e){
        const btnSave = document.getElementById('btn-save-ev');
        if(btnSave && btnSave.disabled) return; // evento ajeno o realizado: bloqueado
        const {lat,lng} = e.latlng;
        document.getElementById('f-lat').value = lat.toFixed(6);
        document.getElementById('f-lng').value = lng.toFixed(6);
        if(marcadorEvento) mapaEventoInst.removeLayer(marcadorEvento);
        marcadorEvento = L.marker([lat,lng]).addTo(mapaEventoInst);
    });
}
function destruirMapaEvento(){
    if(mapaEventoInst){ mapaEventoInst.remove(); mapaEventoInst = null; marcadorEvento = null; }
}

// Bloquea/desbloquea el formulario cuando el evento ya fue marcado "Realizado"
function aplicarBloqueoRealizado(realizado, esPropio){
    const banner  = document.getElementById('realizado-banner');
    const btnSave = document.getElementById('btn-save-ev');
    const btnDel  = document.getElementById('btn-del-ev');
    const btnReal = document.getElementById('btn-realizado-ev');
    if(realizado){
        if(banner) banner.style.display = 'flex';
        if(btnSave){ btnSave.disabled = true; btnSave.style.opacity = '0.4'; btnSave.style.cursor = 'not-allowed'; btnSave.title = 'Evento realizado: ya no es editable'; }
        if(btnDel)  btnDel.style.visibility = 'hidden';
        if(btnReal) btnReal.style.visibility = 'hidden';
        document.querySelectorAll('#modal-form .finput, #modal-form .eje-pill').forEach(c=>{ c.disabled = true; });
        const dzFoto = document.getElementById('drop-zone-foto'), dzPdf = document.getElementById('drop-zone-pdf');
        if(dzFoto) dzFoto.style.pointerEvents = 'none';
        if(dzPdf)  dzPdf.style.pointerEvents  = 'none';
        const inFoto = document.getElementById('foto-input'), inPdf = document.getElementById('pdf-input');
        if(inFoto) inFoto.disabled = true;
        if(inPdf)  inPdf.disabled  = true;
    } else {
        if(banner) banner.style.display = 'none';
        if(btnReal) btnReal.style.visibility = esPropio ? 'visible' : 'hidden';
        // Re-habilita los campos que pudieron quedar bloqueados por un evento "Realizado" visto antes
        document.querySelectorAll('#modal-form .finput, #modal-form .eje-pill').forEach(c=>{
            if (c.id === 'f-estado' || c.id === 'f-municipio' || c.id === 'sec-agenda-display') return;
            c.disabled = false;
        });
        const dzFoto2 = document.getElementById('drop-zone-foto'), dzPdf2 = document.getElementById('drop-zone-pdf');
        if(dzFoto2) dzFoto2.style.pointerEvents = '';
        if(dzPdf2)  dzPdf2.style.pointerEvents  = '';
        const inFoto2 = document.getElementById('foto-input'), inPdf2 = document.getElementById('pdf-input');
        if(inFoto2) inFoto2.disabled = false;
        if(inPdf2)  inPdf2.disabled  = false;
    }
}

async function marcarRealizado(id){
    if(!id) return;
    const ok = confirm('⚠️ Vas a marcar este evento como "Realizado".\n\nUna vez marcado, YA NO podrás editarlo ni eliminarlo.\n\n¿Deseas continuar?');
    if(!ok) return;
    const fd = new FormData();
    fd.append('action','marcar_realizado');
    fd.append('id', id);
    const btnReal = document.getElementById('btn-realizado-ev');
    if(btnReal){ btnReal.disabled = true; btnReal.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando…'; }
    try{
        const res = await fetch(PROCESAR_URL, {method:'POST', body:fd});
        const data = await res.json();
        if(!data.ok) throw new Error(data.msg || 'Error');
        await cargarEventos();
        cerrarModal(); render();
    }catch(e){ alert('Error: ' + e.message); }
    finally{ if(btnReal){ btnReal.disabled = false; btnReal.innerHTML = '<i class="fas fa-check-double"></i> Marcar como Realizado'; } }
}

async function guardarEvento() {
    const titulo=document.getElementById('f-titulo').value.trim();
    const fecha=document.getElementById('f-fecha').value;
    const btn=document.getElementById('btn-save-ev');
    if(!titulo){document.getElementById('f-titulo').focus();return;}
    if(!fecha||!ejeSeleccionado){return;}
    const _seccionVal = document.getElementById('f-seccion').value;
    if(!_seccionVal){
        document.getElementById('sec-agenda-display').focus();
        document.getElementById('sec-agenda-display').style.borderColor='#dc2626';
        setTimeout(()=>document.getElementById('sec-agenda-display').style.borderColor='',2500);
        alert('Selecciona la sección electoral del evento.');
        return;
    }
    let horas=parseFloat(document.getElementById('f-horas').value)||0;
    const ini=document.getElementById('f-inicio').value, fin=document.getElementById('f-fin').value;
    
    if(ini&&fin){
        const[hi,mi]=ini.split(':').map(Number),[hf,mf]=fin.split(':').map(Number);
        if(hf*60+mf <= hi*60+mi){
            const finEl=document.getElementById('f-fin');
            finEl.style.borderColor='#dc2626';
            finEl.style.background='#fef2f2';
            finEl.focus();
            finEl.setCustomValidity('El horario de fin debe ser posterior al inicio');
            const errMsg=document.getElementById('hora-error-msg');
            if(errMsg){errMsg.style.display='block';setTimeout(()=>errMsg.style.display='none',3500);}
            return;
        }
        horas=Math.max(0,(hf*60+mf-hi*60-mi)/60);
    }
    if(!horas)horas=1;
    
    const fd=new FormData();
    fd.append('action',editandoId?'update':'create');
    if(editandoId)fd.append('id',editandoId);
    
    ['subtipo','titulo','fecha','municipio','seccion','responsable','activacion','notas'].forEach(k=>{
        const el=document.getElementById('f-'+k);
        fd.append(k, el.value||'');
    });
    
    // Agregamos fijos el eje, estado, región y zona explícitamente
    fd.append('eje', ejeSeleccionado);
    fd.append('estado', document.getElementById('f-estado').value);
    fd.append('region', document.getElementById('f-region').value);
    fd.append('zona', document.getElementById('f-zona').value);

    fd.append('inicio', document.getElementById('f-inicio').value || '');
    fd.append('fin',    document.getElementById('f-fin').value    || '');
    fd.append('horas',horas);
    
    fd.append('lat', document.getElementById('f-lat').value || '');
    fd.append('lng', document.getElementById('f-lng').value || '');
    fd.append('evidencia_fotos', JSON.stringify(fotosActualesEv.map(f=>f.dataUrl)));
    fd.append('evidencia_pdfs', JSON.stringify(pdfsActualesEv.map(p=>({name:p.name, dataUrl:p.dataUrl}))));
    fd.append('evidencia_enlaces', JSON.stringify(enlacesActualesEv));
    btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Guardando…';
    try{
        const res=await fetch(PROCESAR_URL,{method:'POST',body:fd});
        const data=await res.json();
        if(!data.ok)throw new Error(data.msg||'Error');
        const idFinalRA = editandoId || data.id;
        if (idFinalRA) {
            if (document.getElementById('f-registro-qr').checked) {
                await activarRegistroQR('evento', idFinalRA);
            } else if (editandoId) {
                await desactivarRegistroQR('evento', idFinalRA);
            }
        }
        await cargarEventos();
        cerrarModal(); render();
    }catch(e){alert('Error: '+e.message);}
    finally{btn.disabled=false;btn.innerHTML='<i class="fas fa-check"></i> Guardar';}
}

async function activarRegistroQR(tipoOrigen, origenId) {
    try {
        const fdQR = new FormData();
        fdQR.append('action', 'activar');
        fdQR.append('tipo_origen', tipoOrigen);
        fdQR.append('origen_id', origenId);
        await fetch('../../api/registro_asistentes/procesar.php', { method:'POST', body:fdQR });
    } catch (e) { /* no bloquea el guardado del evento si falla el QR */ }
}

async function desactivarRegistroQR(tipoOrigen, origenId) {
    try {
        const fdQR = new FormData();
        fdQR.append('action', 'desactivar');
        fdQR.append('tipo_origen', tipoOrigen);
        fdQR.append('origen_id', origenId);
        await fetch('../../api/registro_asistentes/procesar.php', { method:'POST', body:fdQR });
    } catch (e) { /* no bloquea el guardado del evento */ }
}

async function eliminarEvento(id){
    const _evElim = (eventos||[]).find(e=>e.id===id) || (eventosOriginales||[]).find(e=>e.id===id);
    if(_evElim && (_evElim.realizado==1 || _evElim.realizado==='1')){
        alert('Este evento ya fue marcado como "Realizado" y no puede eliminarse.');
        return;
    }
    if(!confirm('¿Eliminar este evento?'))return;
    const fd=new FormData(); fd.append('action','delete'); fd.append('id',id);
    try{
        const res=await fetch(PROCESAR_URL,{method:'POST',body:fd});
        const data=await res.json();
        if(!data.ok)throw new Error(data.msg);
        cerrarModal(); cerrarPopover();
        await cargarEventos(); render();
    }catch(e){alert('Error: '+e.message);}
}

// ── Init ──────────────────────────────────────────────
(async()=>{ await geoInicializar(); await cargarEventos(); render(); })();
</script>
</body>
</html>