<?php
// 1. Carga del sistema y seguridad
if (file_exists(__DIR__ . '/../../includes/bootstrap.php')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
} else { 
    session_start(); 
}

$usuario_id = $_SESSION['user_id'] ?? 0;
$rol = $_SESSION['user_role'] ?? '';

// Solo permitimos al coordinador regional
$permitidos = ['coordinador_regional', 'regional'];
if (!in_array($rol, $permitidos)) { 
    header('Location: /public/index.php'); 
    exit(); 
}

// Obtener estado del coordinador y estructuras dinámicas
$estado_actual = '';
$region_actual  = '';  // la región asignada al coordinador regional
$zonas_region   = [];  // solo las zonas de su región
$region_municipios_map = [];
$region_zonas_map = [];
$zona_secciones_map = [];
$secciones_geo  = [];  // [estado][municipio|distrito] => [secciones]
$tipo_integracion_region = 'municipios';

try {
    if(isset($pdo)){
        // Regional: estado viene de sesión; user_region es el ID numérico de la región
        $estado_actual  = $_SESSION['user_estado'] ?? '';
        $region_id_sess = $_SESSION['user_region'] ?? '';
        $region_actual  = '';

        if (!empty($estado_actual) && !empty($region_id_sess)) {
            // Cargar la región por ID (igual que estructura_cr.php)
            $stmtR = $pdo->prepare("SELECT id, nombre, municipios, tipo_integracion FROM regiones_estatales WHERE id = ? LIMIT 1");
            $stmtR->execute([$region_id_sess]);
            $reg = $stmtR->fetch(PDO::FETCH_ASSOC);
            if ($reg) $region_actual = $reg['nombre'];

            if ($reg) {
                $region_municipios_map[$reg['nombre']] = json_decode($reg['municipios'] ?? '[]', true) ?: [];
                $tipo_integracion_region = $reg['tipo_integracion'] ?: 'municipios';

                // Zonas de su región (con id para construir el mapa zona_id→nombre)
                $stmtZ = $pdo->prepare("SELECT id, nombre, secciones FROM zonas_estatales WHERE estructura_id = ? ORDER BY nombre");
                $stmtZ->execute([$reg['id']]);
                $zonas_rows = $stmtZ->fetchAll(PDO::FETCH_ASSOC);
                $region_zonas_map[$reg['nombre']] = array_column($zonas_rows, 'nombre');
                foreach ($zonas_rows as $z) {
                    $zona_secciones_map[$z['nombre']] = json_decode($z['secciones'] ?? '[]', true) ?: [];
                    $zona_id_nombre_map[(int)$z['id']] = $z['nombre'];
                    $zonas_region[] = $z['nombre'];
                }
            }
        }

        // Secciones geo por municipio/distrito local/distrito federal + mapa seccion→entidad para resolver eventos legacy
        $seccion_municipio_map = [];
        if (!empty($estado_actual)) {
            try {
                $tipoGeo = $tipo_integracion_region ?? 'municipios';
                if ($tipoGeo === 'distrito_local') {
                    $stmtSec = $pdo->prepare(
                        "SELECT sec.estado_nombre, sec.distrito_local AS entidad_nombre, sec.seccion
                         FROM secciones sec
                         WHERE sec.estado_nombre = ? AND sec.distrito_local <> ''
                         ORDER BY CAST(sec.distrito_local AS UNSIGNED) ASC, CAST(sec.seccion AS UNSIGNED) ASC"
                    );
                } elseif ($tipoGeo === 'distrito_federal') {
                    $stmtSec = $pdo->prepare(
                        "SELECT sec.estado_nombre, sec.distrito_federal AS entidad_nombre, sec.seccion
                         FROM secciones sec
                         WHERE sec.estado_nombre = ? AND sec.distrito_federal <> ''
                         ORDER BY CAST(sec.distrito_federal AS UNSIGNED) ASC, CAST(sec.seccion AS UNSIGNED) ASC"
                    );
                } else {
                    $stmtSec = $pdo->prepare(
                        "SELECT sec.estado_nombre, m.nombre AS entidad_nombre, sec.seccion
                         FROM secciones sec
                         LEFT JOIN municipios m ON m.id = sec.municipio_id
                         WHERE sec.estado_nombre = ?
                         ORDER BY m.nombre ASC, CAST(sec.seccion AS UNSIGNED) ASC"
                    );
                }
                $stmtSec->execute([$estado_actual]);
                foreach ($stmtSec->fetchAll() as $row) {
                    $ent = $row['entidad_nombre'] ?? '';
                    if ($ent !== '') {
                        $secciones_geo[$estado_actual][$ent][] = $row['seccion'];
                        $seccion_municipio_map[(string)$row['seccion']] = $ent;
                    }
                }
            } catch (Exception $e2) { /* sin secciones */ }
        }
    }
} catch (PDOException $e) {
    error_log("Error al obtener estructuras del coordinador regional: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agenda - <?php echo htmlspecialchars($region_actual ?: $estado_actual ?: 'Coordinador Regional'); ?></title>
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
    .state-badge {
        background: var(--primary-light);
        color: var(--primary);
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 700;
        margin-left: 10px;
        text-transform: uppercase;
    }

    /* ── BODY: sidebar mini + main ── */
    .cal-body{display:flex;flex:1;overflow:hidden;}

    /* ── MINI SIDEBAR ── */
    .mini-sidebar{
        width:256px;flex-shrink:0;padding:16px;
        border-right:1px solid var(--gray-200);
        overflow-y:auto;display:flex;flex-direction:column;gap:20px;
    }

    /* Mini calendar */
    .mini-cal-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;}
    .mini-cal-title{font-size:13px;font-weight:600;color:var(--text);}
    .mini-nav{width:28px;height:28px;border-radius:50%;border:none;background:transparent;cursor:pointer;font-size:13px;color:var(--text-muted);display:flex;align-items:center;justify-content:center;transition:background 0.15s;}
    .mini-nav:hover{background:var(--gray-100);}
    .mini-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:2px;}
    .mini-dow{font-size:10px;font-weight:700;text-transform:uppercase;color:var(--text-light);text-align:center;padding:4px 0;}
    .mini-day {
        width: 32px;  /* Aumentamos un poco el tamaño para que se vea mejor */
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: 500;
        color: var(--text);
        cursor: pointer;
        margin: 0 auto;
        transition: background 0.12s;
        position: relative; /* Importante para el punto absoluto */
    }
    .mini-day:hover{background:var(--gray-100);}
    .mini-day.other-month{color:var(--text-light);}
    .mini-day.today{background:var(--primary);color:white;font-weight:700;}
    .mini-day.selected{background:var(--primary-light);color:var(--primary);font-weight:700;}
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
    .upcoming-title{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted);margin-bottom:10px;}
    .upcoming-item{display:flex;gap:10px;align-items:flex-start;padding:8px 0;border-bottom:1px solid var(--gray-100);}
    .upcoming-item:last-child{border-bottom:none;}
    .upcoming-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0;margin-top:3px;}
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
    /* all-day strip for week */
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
        width:90%;max-width:620px;padding:36px;
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
    .finput:disabled { opacity: 0.7; cursor: not-allowed; }
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

    /* ── BUSCADOR SECCIÓN ── */
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

    /* ── TOGGLE VISTA ── */
    .toggle-vista{display:inline-flex;align-items:center;gap:9px;padding:0 14px;height:34px;background:var(--white);border:1.5px solid var(--gray-200);border-radius:20px;cursor:pointer;transition:all 0.2s;user-select:none;flex-shrink:0;}
    .toggle-vista:hover{border-color:var(--primary);}
    .toggle-track{width:36px;height:18px;border-radius:9px;background:var(--gray-300);position:relative;transition:background 0.2s;flex-shrink:0;}
    .toggle-track.on{background:var(--primary);}
    .toggle-thumb{position:absolute;top:2px;left:2px;width:14px;height:14px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,0.25);transition:left 0.2s;}
    .toggle-track.on .toggle-thumb{left:20px;}
    .toggle-label{font-size:11px;font-weight:700;color:var(--text-muted);white-space:nowrap;}
    .toggle-vista.ver-todo .toggle-label{color:var(--comunicacion);}

    /* ── FILTRO BAR ── */
    .filter-bar {
        display: flex; align-items: center; gap: 10px;
        padding: 10px 20px; background: var(--white);
        border-bottom: 1px solid var(--gray-200);
        flex-shrink: 0; flex-wrap: wrap;
        position: sticky; top: 64px; z-index: 90;
    }
    .filter-label {
        font-size: 11px; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.5px; color: var(--text-muted); white-space: nowrap;
    }
    .filter-select {
        height: 34px; padding: 0 10px; border-radius: 8px;
        border: 1.5px solid var(--gray-200); background: var(--gray-50);
        font-size: 13px; font-weight: 500; color: var(--text);
        cursor: pointer; outline: none; transition: all 0.18s;
        min-width: 130px; max-width: 180px;
        font-family: inherit;
    }
    .filter-select:focus { border-color: var(--primary); background: var(--white); }
    .filter-sep { width: 1px; height: 24px; background: var(--gray-200); margin: 0 2px; }
    .filter-clear {
        height: 34px; padding: 0 14px; border-radius: 8px;
        border: 1.5px solid var(--gray-200); background: var(--white);
        font-size: 12px; font-weight: 600; color: var(--text-muted);
        cursor: pointer; transition: all 0.15s; white-space: nowrap;
        font-family: inherit;
    }
    .filter-clear:hover { border-color: var(--primary); color: var(--primary); }
    .filter-results {
        font-size: 12px; color: var(--text-muted); white-space: nowrap;
        margin-left: auto; font-weight: 500;
    }
    .filter-results b { color: var(--primary); }
    @media(max-width:900px){
        .filter-bar { top: 56px; gap: 6px; }
        .filter-select { min-width: 100px; }
    }

    </style>
</head>
<body>

<?php 
// Llamamos al sidebar correcto del coordinador estatal
if (file_exists(__DIR__ . '/../../includes/components/sidebar_regional.php')) {
    include __DIR__ . '/../../includes/components/sidebar_regional.php';
}
?>

<div class="topbar">
    <div class="topbar-left">
        <button class="today-btn" onclick="irHoy()">Hoy</button>
        <div class="topbar-nav">
            <button class="nav-btn" onclick="navegar(-1)"><i class="fas fa-chevron-left"></i></button>
            <button class="nav-btn" onclick="navegar(1)"><i class="fas fa-chevron-right"></i></button>
        </div>
        <span class="topbar-title" id="topbar-label">—</span>
        <?php if($region_actual): ?>
            <span class="state-badge"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($region_actual); ?></span>
        <?php elseif($estado_actual): ?>
            <span class="state-badge"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($estado_actual); ?></span>
        <?php endif; ?>
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
    <span class="filter-label"><i class="fas fa-filter"></i> Filtrar:</span>

    <!-- Regional: estado y región fijos -->
    <input type="hidden" id="fil-estado" value="<?= htmlspecialchars($estado_actual) ?>">
    <input type="hidden" id="fil-region" value="<?= htmlspecialchars($region_actual) ?>">
    <span style="font-size:13px;font-weight:700;color:var(--primary);background:rgba(45,122,62,0.1);padding:5px 12px;border-radius:8px;white-space:nowrap;">
        <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($region_actual ?: $estado_actual) ?>
    </span>

    <select class="filter-select" id="fil-zona" onchange="onFiltroZonaChange()">
        <option value="">Todas las zonas</option>
        <?php foreach($zonas_region as $z): ?>
            <option value="<?= htmlspecialchars($z) ?>"><?= htmlspecialchars($z) ?></option>
        <?php endforeach; ?>
    </select>

    <select class="filter-select" id="fil-seccion" onchange="aplicarFiltros()">
        <option value="">Todas las secciones</option>
    </select>

    <div class="filter-sep"></div>

    <select class="filter-select" id="fil-eje" onchange="aplicarFiltros()">
        <option value="">Todos los ejes</option>
        <option value="voto">🗳️ Voto / Territorio</option>
        <option value="comunicacion">📣 Comunicación</option>
        <option value="organizacion">⚙️ Organización</option>
    </select>

    <button class="filter-clear" onclick="limpiarFiltros()">
        <i class="fas fa-times"></i> Limpiar
    </button>

    <span class="filter-results" id="fil-results"></span>

    <!-- Toggle Mi Agenda / Ver Todo -->
    <div class="toggle-vista" id="toggle-vista-btn" onclick="toggleVistaCE()" title="Mi Agenda: tus propios eventos · Ver Todo: toda tu región">
        <div class="toggle-track on" id="ce-toggle-track"><div class="toggle-thumb"></div></div>
        <span class="toggle-label" id="ce-toggle-label">Mi Agenda</span>
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
            <div class="upcoming-title">Próximos eventos</div>
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
        <p class="modal-box-sub">Registra un evento en la agenda semanal inteligente.</p>
        <!-- Banda informativa para eventos de zona/región (solo lectura) -->
        <div id="zona-info-bar" style="display:none;margin-bottom:18px;padding:10px 14px;border-radius:10px;background:rgba(26,115,232,0.08);border:1.5px solid rgba(26,115,232,0.25);align-items:center;gap:10px;">
            <i class="fas fa-layer-group" style="color:var(--comunicacion);font-size:14px;flex-shrink:0;"></i>
            <div>
                <div id="zona-info-tipo" style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:var(--comunicacion);">Evento de Zona</div>
                <div id="zona-info-nombre" style="font-size:13px;font-weight:600;color:var(--text);margin-top:2px;">—</div>
            </div>
            <span style="margin-left:auto;font-size:10px;color:var(--text-muted);font-style:italic;flex-shrink:0;">Solo lectura</span>
        </div>

        <div class="fg">
            <label class="flabel">Eje *</label>
            <div class="eje-row">
                <button class="eje-pill" data-eje="voto"         onclick="selEje('voto')"         type="button">🗳️ Voto / Territorio</button>
                <button class="eje-pill" data-eje="comunicacion" onclick="selEje('comunicacion')" type="button">📣 Comunicación</button>
                <button class="eje-pill" data-eje="organizacion" onclick="selEje('organizacion')" type="button">⚙️ Organización</button>
            </div>
        </div>
        <div class="form-grid-2" style="margin-bottom:18px;">
            <div class="fg" style="margin-bottom:0;">
                <label class="flabel">Subtipo</label>
                <select id="f-subtipo" class="finput"><option value="">— Selecciona un eje primero —</option></select>
            </div>
            <div class="fg" style="margin-bottom:0;">
                <label class="flabel">Título *</label>
                <input type="text" id="f-titulo" class="finput" placeholder="Ej. Recorrido Colonia Pitic">
            </div>
        </div>

        <div class="form-grid-3" style="margin-bottom:18px;">
            <div class="fg" style="margin-bottom:0;">
                <label class="flabel">Fecha *</label>
                <input type="date" id="f-fecha" class="finput" onchange="verificarFechaFutura()">
                <div id="aviso-fecha-futura" style="display:none;margin-top:6px;padding:7px 11px;background:#fffbeb;border:1px solid #f59e0b;border-radius:8px;font-size:12px;color:#92400e;display:flex;align-items:center;gap:7px;">
                    <i class="fas fa-clock" style="color:#f59e0b;font-size:13px;flex-shrink:0;"></i>
                    <span>Estás registrando un evento en una fecha <strong>futura</strong>. Verifica que sea correcto antes de guardar.</span>
                </div>
            </div>
            <div class="fg" style="margin-bottom:0;">
                <label class="flabel">Estado</label>
                <select id="f-estado" class="finput" onchange="cargarMunicipios(this.value)">
                    <option value="">— Estado —</option>
                    <option value="Aguascalientes">Aguascalientes</option><option value="Baja California">Baja California</option>
                    <option value="Baja California Sur">Baja California Sur</option><option value="Campeche">Campeche</option>
                    <option value="Chiapas">Chiapas</option><option value="Chihuahua">Chihuahua</option>
                    <option value="Ciudad de México">Ciudad de México</option><option value="Coahuila">Coahuila</option>
                    <option value="Colima">Colima</option><option value="Durango">Durango</option>
                    <option value="Guanajuato">Guanajuato</option><option value="Guerrero">Guerrero</option>
                    <option value="Hidalgo">Hidalgo</option><option value="Jalisco">Jalisco</option>
                    <option value="México">México</option><option value="Michoacán">Michoacán</option>
                    <option value="Morelos">Morelos</option><option value="Nayarit">Nayarit</option>
                    <option value="Nuevo León">Nuevo León</option><option value="Oaxaca">Oaxaca</option>
                    <option value="Puebla">Puebla</option><option value="Querétaro">Querétaro</option>
                    <option value="Quintana Roo">Quintana Roo</option><option value="San Luis Potosí">San Luis Potosí</option>
                    <option value="Sinaloa">Sinaloa</option><option value="Sonora">Sonora</option>
                    <option value="Tabasco">Tabasco</option><option value="Tamaulipas">Tamaulipas</option>
                    <option value="Tlaxcala">Tlaxcala</option><option value="Veracruz">Veracruz</option>
                    <option value="Yucatán">Yucatán</option><option value="Zacatecas">Zacatecas</option>
                </select>
            </div>
            <div class="fg" style="margin-bottom:0;">
                <label class="flabel" id="f-municipio-label">Municipio *</label>
                <select id="f-municipio" class="finput" onchange="cargarSecciones(this.value)" required>
                    <option value="">— Municipio —</option>
                </select>
            </div>
        </div>

        <div class="form-grid-3" style="margin-bottom:18px;">
            <div class="fg" style="margin-bottom:0;">
                <label class="flabel">
                    Sección *
                    <span id="f-sec-contador" style="font-weight:400;color:var(--text-muted);margin-left:6px;font-size:10px;"></span>
                </label>
                <div class="sec-agenda-wrap" id="sec-agenda-wrap">
                    <input type="hidden" id="f-seccion" value="">
                    <input type="text" class="sec-agenda-input finput" id="sec-agenda-display"
                           placeholder="— Selecciona municipio primero —"
                           readonly onclick="secAgendaToggle()" autocomplete="off">
                    <i class="fas fa-chevron-down sec-agenda-arrow" id="sec-agenda-arrow"></i>
                    <div class="sec-agenda-list" id="sec-agenda-list">
                        <input type="text" class="sec-agenda-search" id="sec-agenda-search"
                               placeholder="🔍 Buscar sección…"
                               oninput="secAgendaFiltrar(this.value)"
                               onkeydown="secAgendaNavegar(event)">
                        <div class="sec-agenda-options" id="sec-agenda-options">
                            <div class="sec-agenda-empty">Selecciona municipio primero</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="fg" style="margin-bottom:0;">
                <label class="flabel">Inicio</label>
                <select id="f-inicio" class="finput" onchange="limpiarErrorFin()">
                    <option value="">—</option>
                    <?php for($h=0;$h<24;$h++) for($m=0;$m<60;$m+=30) { $t=sprintf('%02d:%02d',$h,$m); echo "<option value='$t'>$t</option>"; } ?>
                </select>
            </div>
            <div class="fg" style="margin-bottom:0;">
                <label class="flabel">Fin</label>
                <select id="f-fin" class="finput" onchange="calcHoras()">
                    <option value="">—</option>
                    <?php for($h=0;$h<24;$h++) for($m=0;$m<60;$m+=30) { $t=sprintf('%02d:%02d',$h,$m); echo "<option value='$t'>$t</option>"; } ?>
                </select>
                <span id="hora-error-msg" style="display:none;font-size:11px;font-weight:700;color:#dc2626;margin-top:4px;">
                    <i class="fas fa-exclamation-circle"></i> El fin debe ser posterior
                </span>
            </div>
        </div>

        <div class="form-grid-3" style="margin-bottom:18px;">
            <div class="fg" style="margin-bottom:0;">
                <label class="flabel">Región</label>
                <select id="f-region" class="finput" onchange="onModalRegionChange()" disabled>
                    <option value="">— Región —</option>
                    <?php if(!empty($region_actual)): ?>
                        <option value="<?= htmlspecialchars($region_actual) ?>" selected><?= htmlspecialchars($region_actual) ?></option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="fg" style="margin-bottom:0;">
                <label class="flabel">Zona</label>
                <select id="f-zona" class="finput" disabled>
                    <option value="">— Zona —</option>
                    <?php foreach($zonas_region as $z): ?>
                        <option value="<?= htmlspecialchars($z) ?>"><?= htmlspecialchars($z) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="fg" style="margin-bottom:0;">
                <label class="flabel">Horas</label>
                <input type="number" id="f-horas" class="finput" min="0" max="24" step="0.5" placeholder="0">
            </div>
        </div>

        <div class="fg">
            <label class="flabel">Responsable</label>
            <input type="text" id="f-responsable" class="finput" placeholder="Nombre">
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
// Capturamos el estado del coordinador desde PHP
const ESTADO_COORDINADOR = '<?php echo addslashes($estado_actual); ?>';
const REGION_ACTUAL = '<?php echo addslashes($region_actual); ?>';
// ID del usuario actual para distinguir sus propios eventos
const USUARIO_ID = <?php echo intval($usuario_id); ?>;

const PROCESAR_URL = '../../api/agenda/procesar.php';
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

// Mapa zona_id → nombre (construido desde PHP con datos reales de BD)
var ZONA_ID_TO_NAME = <?php echo json_encode(array_map('strval', $zona_id_nombre_map ?? []), JSON_UNESCAPED_UNICODE); ?>;

// Mapa seccion → municipio (para resolver municipio en eventos legacy sin municipio guardado)
const SECCION_MUNICIPIO_MAP = <?php echo json_encode($seccion_municipio_map ?? [], JSON_UNESCAPED_UNICODE); ?>;

// ─────────────────────────────────────────────────────────
//  GEO — Cache y estado (igual que secciones.php)
// ─────────────────────────────────────────────────────────
const GEO_CACHE     = {};
let   SEC_AGENDA    = [];
let   SEC_FOCUSED   = -1;
const SECCIONES_API = '/api/secciones/secciones_api.php';

async function geoInicializar() {
    try {
        const res  = await fetch(SECCIONES_API + '?action=geo_data');
        const data = await res.json();
        if (!data.ok) throw new Error(data.msg || 'error');
        Object.assign(GEO_CACHE, data.estados);
        _poblarSelectEstados(Object.values(data.estados).map(e => e.label));
        // Si el modal ya está abierto y el municipio quedó bloqueado, re-cargar
        const modalOpen = document.getElementById('modal-form').classList.contains('open');
        const selMun = document.getElementById('f-municipio');
        if (modalOpen && selMun && selMun.disabled) {
            const estadoActual = document.getElementById('f-estado')?.value || ESTADO_COORDINADOR;
            if (estadoActual) cargarMunicipios(estadoActual);
        }
    } catch(err) {
        console.warn('geoInicializar fallback:', err);
    }
}

function _poblarSelectEstados(lista) {
    const sel = document.getElementById('f-estado');
    if (!sel) return;
    const primerOpt = sel.options[0].outerHTML;
    sel.innerHTML = primerOpt;
    lista.forEach(nombre => {
        const o = document.createElement('option'); o.value = nombre; o.textContent = nombre; sel.appendChild(o);
    });
    if (ESTADO_COORDINADOR) {
        sel.value = ESTADO_COORDINADOR;
        sel.disabled = true;
        cargarMunicipios(ESTADO_COORDINADOR);
    }
}

function cargarMunicipios(estado, cb) {
    const et = getEtiquetaIntegracionRegional();
    const selMun = document.getElementById('f-municipio');
    selMun.innerHTML = `<option value="">${et.vacio}</option>`;
    selMun.disabled  = true;
    secAgendaReset(et.vacioSel);
    if (!estado) { if(cb) cb(); return; }
    const entry = Object.values(GEO_CACHE).find(e => e.label === estado);
    let lista = (entry && entry.municipios) ? entry.municipios : [];

    // Fallback: si GEO_CACHE no tiene municipios, usar los de la región del coordinador (desde PHP)
    if (!lista.length) {
        const region = document.getElementById('f-region')?.value || '';
        const regionMunis = region ? (REGION_MUNICIPIOS_MAP[region] || []) : [];
        // Si no hay región seleccionada aún, tomar todos los municipios del REGION_MUNICIPIOS_MAP
        if (regionMunis.length) {
            lista = regionMunis;
        } else {
            // Aplanar todos los municipios de todas las regiones disponibles
            const todosRegion = Object.values(REGION_MUNICIPIOS_MAP).flat();
            if (todosRegion.length) lista = [...new Set(todosRegion)];
        }
    }

    if (lista.length) {
        [...lista].sort().forEach(m => { const o=document.createElement('option'); o.value=m; o.textContent=et.prefijo+m; selMun.appendChild(o); });
        selMun.disabled = false;
    } else {
        selMun.innerHTML = `<option value="">${et.sinDatos}</option>`;
    }
    if (cb) cb();
}

function cargarSecciones(municipio, cb) {
    // Usar SECCIONES_GEO_ACT (precargado desde PHP) igual que activaciones_cr
    const et = getEtiquetaIntegracionRegional();
    secAgendaReset('Cargando secciones…', true);
    if (!municipio) { secAgendaReset(et.vacioSel); if(cb) cb(); return; }
    const estadoLabel = document.getElementById('f-estado')?.value || ESTADO_COORDINADOR || '';
    const secciones = (SECCIONES_GEO_ACT[estadoLabel] || {})[municipio] || [];
    SEC_AGENDA = secciones;
    const n = SEC_AGENDA.length;
    secAgendaActualizarContador(n);
    if (n > 0) {
        secAgendaRenderOpts(SEC_AGENDA);
        document.getElementById('sec-agenda-display').placeholder = `— Selecciona sección (${n}) —`;
    } else {
        secAgendaReset('Sin secciones para este municipio');
    }
    if (cb) cb();
}

// ── Buscador sección ───────────────────────────────────────────
function secAgendaReset(msg='Selecciona municipio primero', loading=false) {
    SEC_AGENDA=[]; SEC_FOCUSED=-1;
    document.getElementById('f-seccion').value='';
    document.getElementById('sec-agenda-display').value='';
    document.getElementById('sec-agenda-display').placeholder='— '+msg+' —';
    document.getElementById('sec-agenda-search').value='';
    secAgendaActualizarContador(0);
    const opts=document.getElementById('sec-agenda-options');
    opts.innerHTML = loading
        ? `<div class="sec-agenda-loading"><i class="fas fa-spinner fa-spin"></i> ${msg}</div>`
        : `<div class="sec-agenda-empty">${msg}</div>`;
    secAgendaCerrar();
}
function secAgendaActualizarContador(n){const el=document.getElementById('f-sec-contador');if(el)el.textContent=n>0?`(${n} secciones)`:'';}
function secAgendaRenderOpts(lista,busqueda=''){
    const opts=document.getElementById('sec-agenda-options');
    const actual=document.getElementById('f-seccion').value;
    SEC_FOCUSED=-1;
    if(!lista.length){opts.innerHTML=`<div class="sec-agenda-empty">Sin resultados${busqueda?` para "${busqueda}"`:''}</div>`;return;}
    opts.innerHTML=lista.map(s=>{
        const label=busqueda.trim()?s.replace(new RegExp(`(${busqueda.trim()})`, 'gi'),'<em>$1</em>'):s;
        return `<div class="sec-agenda-opt${String(s)===String(actual)?' selected':''}" onmousedown="secAgendaSeleccionar('${s}')">${label}</div>`;
    }).join('');
}
function secAgendaFiltrar(q){
    if(!SEC_AGENDA.length)return;
    const f=q.trim()?SEC_AGENDA.filter(s=>s.toString().toLowerCase().includes(q.trim().toLowerCase())):SEC_AGENDA;
    secAgendaRenderOpts(f,q);
}
function secAgendaSeleccionar(val){
    document.getElementById('f-seccion').value=val;
    document.getElementById('sec-agenda-display').value=val;
    document.getElementById('sec-agenda-display').placeholder='';
    secAgendaCerrar();
    document.querySelectorAll('.sec-agenda-opt').forEach(el=>el.classList.toggle('selected',el.textContent===val));
    // Auto-detectar región y zona basándose en la sección elegida
    autoDetectarRegionZona(val);
}
function secAgendaToggle(){
    const wrap=document.getElementById('sec-agenda-wrap');
    if(!SEC_AGENDA.length)return;
    if(wrap.classList.contains('open')){secAgendaCerrar();}
    else{wrap.classList.add('open');const s=document.getElementById('sec-agenda-search');s.value='';secAgendaRenderOpts(SEC_AGENDA);setTimeout(()=>s.focus(),50);}
}
function secAgendaCerrar(){document.getElementById('sec-agenda-wrap')?.classList.remove('open');}
function secAgendaNavegar(e){
    const items=document.querySelectorAll('.sec-agenda-opt');if(!items.length)return;
    if(e.key==='ArrowDown')SEC_FOCUSED=Math.min(SEC_FOCUSED+1,items.length-1);
    else if(e.key==='ArrowUp')SEC_FOCUSED=Math.max(SEC_FOCUSED-1,0);
    else if(e.key==='Enter'&&SEC_FOCUSED>=0){e.preventDefault();secAgendaSeleccionar(items[SEC_FOCUSED].textContent);return;}
    else if(e.key==='Escape'){secAgendaCerrar();return;}
    items.forEach((el,i)=>el.classList.toggle('focused',i===SEC_FOCUSED));
    if(SEC_FOCUSED>=0)items[SEC_FOCUSED].scrollIntoView({block:'nearest'});
}
document.addEventListener('click',e=>{if(!e.target.closest('#sec-agenda-wrap'))secAgendaCerrar();});

// ── Validación horario ─────────────────────────────────────────
function limpiarErrorFin(){
    const finEl=document.getElementById('f-fin');
    finEl.style.borderColor='';finEl.style.background='';finEl.title='';
    finEl.setCustomValidity&&finEl.setCustomValidity('');
    const errMsg=document.getElementById('hora-error-msg');
    if(errMsg)errMsg.style.display='none';
    calcHoras();
}
function calcHoras(){
    const ini=document.getElementById('f-inicio').value,fin=document.getElementById('f-fin').value;
    const finEl=document.getElementById('f-fin');
    if(ini&&fin){
        const[hi,mi]=ini.split(':').map(Number),[hf,mf]=fin.split(':').map(Number);
        const d=(hf*60+mf-hi*60-mi)/60;
        if(d<=0){
            finEl.style.borderColor='#dc2626';finEl.style.background='#fef2f2';
            finEl.title='El horario de fin debe ser posterior al inicio';
            document.getElementById('f-horas').value='';
        } else {
            finEl.style.borderColor='';finEl.style.background='';finEl.title='';
            document.getElementById('f-horas').value=d.toFixed(1);
        }
    } else {
        finEl.style.borderColor='';finEl.style.background='';
    }
}


// ── Estado global ──────────────────────────────────────
let eventos    = [];
let vista      = 'week';     // 'month' | 'week'
let hoy        = new Date(); hoy.setHours(0,0,0,0);
let cursor     = new Date(hoy); // semana/mes actual
let miniCursor = new Date(hoy);
let editandoId = null;
let ejeSeleccionado = null;
let popoverEvId = null;

// ── Helpers ───────────────────────────────────────────
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

const escaparHTML = text => {
    const div = document.createElement('div');
    div.innerText = text;
    return div.innerHTML;
};

// ── Cargar eventos ────────────────────────────────────
let _cargaEventosToken = 0;
async function cargarEventos() {
    const miToken = ++_cargaEventosToken;
    try {
        const res  = await fetch(PROCESAR_URL + '?action=list');
        const data = await res.json();
        // Si mientras esperábamos esta respuesta se disparó OTRA carga más
        // reciente (doble clic, toggle rápido, etc.), esta ya quedó obsoleta:
        // se descarta para que nunca pise datos más nuevos con datos viejos.
        if (miToken !== _cargaEventosToken) return;
        if (data.ok) eventosOriginales = data.eventos.map(e => ({...e, id: parseInt(e.id)}));
    } catch(e) { console.error(e); return; }
    reconstruirSeccionesDisponibles();
    aplicarFiltros();
}

// ── Render principal ──────────────────────────────────
function render() {
    if (vista === 'month') renderMes();
    else renderSemana();
    renderMiniCal();
    renderBalance();
    renderUpcoming();
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
    inicioGrid.setDate(primerDia.getDate() - (dow === 0 ? 6 : dow - 1)); // lunes

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
        const evsDia   = eventos.filter(e => e.fecha === fechaStr)
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
                          title="${escaparHTML(ev.titulo)}">${ev.inicio ? fmtHora(ev.inicio)+' ' : ''}${escaparHTML(ev.titulo)}</span>`;
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
        const evs = eventos.filter(e => e.fecha === f && !e.inicio);
        return `<div class="week-allday-cell">${evs.map(ev=>
            `<span class="cell-pill ${ev.eje}" style="font-size:10px;" onclick="abrirPopover(event,${ev.id})">${escaparHTML(ev.titulo)}</span>`
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
        const evsDia = eventos.filter(e => e.fecha === f && e.inicio);
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
              <div style="font-weight:700;line-height:1.2;">${escaparHTML(ev.titulo)}</div>
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

// ══════════════════════════════════════════════════════
//  MINI CALENDARIO
// ══════════════════════════════════════════════════════
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
        const hasEv   = eventos.some(e => e.fecha === f);
        const isSel   = (vista==='week')
            ? (()=>{ const s=getInicioSemana(cursor); const e=new Date(s); e.setDate(s.getDate()+6); return d>=s&&d<=e; })()
            : (d.getMonth()===cursor.getMonth()&&d.getFullYear()===cursor.getFullYear());
        html += `<div class="mini-day${otroMes?' other-month':''}${esHoy?' today':''}${isSel&&!esHoy?' selected':''}${hasEv?' has-event':''}" 
                      onclick="miniClickDia(new Date(${d.getFullYear()},${d.getMonth()},${d.getDate()}))">${d.getDate()}</div>`;
        d.setDate(d.getDate() + 1);
        if (d.getMonth() !== month && d.getDay() === 1) break;
        if (d > new Date(year, month+1, 14)) break;
    }
    document.getElementById('mini-grid').innerHTML = html;
}

// ══════════════════════════════════════════════════════
//  BALANCE
// ══════════════════════════════════════════════════════
function renderBalance() {
    let evsSem;
    if (vista === 'month') {
        const prefijoMes = `${cursor.getFullYear()}-${pad(cursor.getMonth()+1)}-`;
        evsSem = eventos.filter(e => (e.fecha||'').startsWith(prefijoMes));
    } else {
        const inicioSem = getInicioSemana(cursor);
        const fechas = Array.from({length:7},(_,i)=>{ const d=new Date(inicioSem); d.setDate(inicioSem.getDate()+i); return fmtDate(d); });
        evsSem = eventos.filter(e => fechas.includes(e.fecha));
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

// ══════════════════════════════════════════════════════
//  PRÓXIMOS EVENTOS
// ══════════════════════════════════════════════════════
function renderUpcoming() {
    const hoyStr = fmtDate(hoy);
    const proximos = eventos
        .filter(e => e.fecha >= hoyStr)
        .sort((a,b)=>a.fecha.localeCompare(b.fecha)||(a.inicio||'').localeCompare(b.inicio||''))
        .slice(0,5);
    const ul = document.getElementById('upcoming-list');
    if (!proximos.length) { ul.innerHTML='<div style="font-size:12px;color:var(--text-light);padding:8px 0;">Sin eventos próximos</div>'; return; }
    ul.innerHTML = proximos.map(ev=>{
        const c = EJE_COLORS[ev.eje]||'#666';
        const fechaD = new Date(ev.fecha+'T12:00:00');
        const label  = ev.fecha===hoyStr ? 'Hoy' : `${fechaD.getDate()} ${MESES_CORTO[fechaD.getMonth()]}`;
        return `<div class="upcoming-item" style="cursor:pointer;" onclick="abrirPopoverById(${ev.id})">
          <div class="upcoming-dot" style="background:${c};"></div>
          <div class="upcoming-info">
            <div class="upcoming-ev-title">${escaparHTML(ev.titulo)}</div>
            <div class="upcoming-ev-date">${label}${ev.inicio?' · '+fmtHora(ev.inicio):''}</div>
          </div>
        </div>`;
    }).join('');
}

// ══════════════════════════════════════════════════════
//  NAVEGACIÓN
// ══════════════════════════════════════════════════════
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
    const ev = eventos.find(x => x.id === id); if (!ev) return;
    popoverEvId = id;
    const c = EJE_COLORS[ev.eje]||'#666';
    document.getElementById('pop-bar').style.background = c;
    document.getElementById('pop-title').textContent = ev.titulo;
    const b = document.getElementById('pop-badge');
    b.textContent = EJE_LABELS[ev.eje]||ev.eje;
    b.style.cssText = `background:${c}18;color:${c};border:1px solid ${c}40;`;
    
    const setRow = (id, val) => {
        const row = document.getElementById(id);
        row.style.display = val ? 'flex' : 'none';
        if (val) row.querySelector('span').textContent = val;
    };
    const fechaD = new Date(ev.fecha+'T12:00:00');
    setRow('pop-fecha', `${fechaD.getDate()} de ${MESES[fechaD.getMonth()]} ${fechaD.getFullYear()}`);
    setRow('pop-hora', (ev.inicio||ev.fin) ? `${fmtHora(ev.inicio)}${ev.fin?' – '+fmtHora(ev.fin):''}${ev.horas?' ('+ev.horas+'h)':''}` : null);
    const _zonaNombre = ev.zona ? (ZONA_ID_TO_NAME[String(ev.zona)] || ev.zona) : null;
    setRow('pop-lugar', [ev.estado, ev.region, _zonaNombre, ev.municipio, ev.seccion?'Sec.'+ev.seccion:null].filter(Boolean).join(' · ')||null);
    setRow('pop-resp', ev.responsable||null);
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
    // Solo el creador puede eliminar o editar; otros ven "Ver" (solo lectura)
    const _esCreador = (parseInt(ev.creado_por_id) === USUARIO_ID && !!ev.creado_por_id) ||
                       (!ev.creado_por_id && (ev.creado_por_rol === 'coordinador_regional' || ev.creado_por_rol === 'regional'));
    const _popDel  = document.getElementById('pop-del');
    const _popEdit = document.getElementById('pop-edit');
    if (_esCreador) {
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
    
    // Posicionar
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
//  MODAL CREAR/EDITAR
// ══════════════════════════════════════════════════════
function abrirModal(id=null, fechaPre=null) {
    cerrarPopover();
    editandoId = id;
    ejeSeleccionado = null;
    document.getElementById('modal-title').textContent = id ? 'Editar Evento' : 'Nuevo Evento';
    const btnDel = document.getElementById('btn-del-ev');

    ['f-titulo','f-inicio','f-fin','f-horas','f-responsable','f-notas','f-region','f-zona'].forEach(x=>document.getElementById(x).value='');
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
    document.getElementById('f-estado').value='';
    document.getElementById('f-municipio').innerHTML=`<option value="">${getEtiquetaIntegracionRegional().vacio}</option>`;
    secAgendaReset(getEtiquetaIntegracionRegional().vacioSel);
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

    // Detectar si el evento es de zona o regional (no propio del estatal)
    // Es "ajeno" si tiene zona/región Y fue creado por otro usuario (no por el estatal actual)
    // Buscamos en todas las fuentes disponibles para máxima robustez
    const _todosEventos = [...new Map(
        [...(eventosOriginales||[]), ...(eventos||[])].map(e => [e.id, e])
    ).values()];
    const _evParaAbrir = id ? _todosEventos.find(e => e.id === id) : null;
    const _tieneZona   = _evParaAbrir && (_evParaAbrir.zona   || '').toString().trim() !== '';
    const _tieneRegion = _evParaAbrir && (_evParaAbrir.region || '').toString().trim() !== '';
    // Usamos la misma lógica que esEventoPropio en aplicarFiltros
    const _esPropio    = _evParaAbrir && (
                             (parseInt(_evParaAbrir.creado_por_id) === USUARIO_ID && !!_evParaAbrir.creado_por_id) ||
                             (!_evParaAbrir.creado_por_id && (_evParaAbrir.creado_por_rol === 'coordinador_regional' || _evParaAbrir.creado_por_rol === 'regional'))
                         );
    const _esAjeno     = (_tieneZona || _tieneRegion) && !_esPropio;
    const _zonaBar     = document.getElementById('zona-info-bar');
    const _btnSave     = document.getElementById('btn-save-ev');
    if (_esAjeno) {
        let _infoTipo, _infoNombre;
        if (_tieneZona) {
            _infoTipo   = 'Evento de Zona';
            _infoNombre = ZONA_ID_TO_NAME[String(_evParaAbrir.zona)] || _evParaAbrir.zona || 'Zona desconocida';
            if (_tieneRegion) _infoNombre += ' · ' + _evParaAbrir.region;
        } else {
            _infoTipo   = 'Evento de Región';
            _infoNombre = _evParaAbrir.region;
        }
        document.getElementById('zona-info-tipo').textContent  = _infoTipo;
        document.getElementById('zona-info-nombre').textContent = _infoNombre;
        _zonaBar.style.display = 'flex';
        _btnSave.disabled = true; _btnSave.style.opacity = '0.4';
        _btnSave.style.cursor = 'not-allowed';
        _btnSave.title = 'Los eventos de zona/región no se editan desde aquí';
    } else {
        _zonaBar.style.display = 'none';
        _btnSave.disabled = false; _btnSave.style.opacity = '';
        _btnSave.style.cursor = ''; _btnSave.title = '';
    }

    if (id) {
        const ev = _evParaAbrir || _todosEventos.find(e=>e.id===id); if(!ev) { console.warn('[abrirModal] Evento no encontrado, id=', id); return; }
        selEje(ev.eje);
        setTimeout(()=>{document.getElementById('f-subtipo').value=ev.subtipo||'';},50);
        // Normalizar inicio/fin: quitar segundos si viene HH:MM:SS
        const _norm = v => v ? v.substring(0,5) : '';
        ['titulo','fecha','horas','responsable','notas'].forEach(k=>{
            const el=document.getElementById('f-'+k); if(el) el.value=ev[k]||'';
        });
        // Inicio y fin: normalizar y asignar al select
        const _elIni = document.getElementById('f-inicio');
        const _elFin = document.getElementById('f-fin');
        if (_elIni) _elIni.value = _norm(ev.inicio);
        if (_elFin) _elFin.value = _norm(ev.fin);
        // Región: asignar y poblar zonas
        const _inpReg = document.getElementById('f-region');
        if (_inpReg && ev.region) {
            _inpReg.value = ev.region;
            onModalRegionChange();
        }
        // Zona: ev.zona puede ser ID numérico (legacy) o nombre — convertir con ZONA_ID_TO_NAME
        const elZona = document.getElementById('f-zona');
        const _zonaNombre = ZONA_ID_TO_NAME[String(ev.zona)] || String(ev.zona || '');
        if (elZona && _zonaNombre) {
            const _optZona = [...elZona.options].find(o => o.value === _zonaNombre || o.textContent === _zonaNombre);
            elZona.value = _optZona ? _optZona.value : _zonaNombre;
        }

        // Municipio: si viene vacío, resolverlo desde SECCION_MUNICIPIO_MAP con la sección
        const _municipio = ev.municipio || (ev.seccion ? (SECCION_MUNICIPIO_MAP[String(ev.seccion)] || '') : '');

        if(ev.estado){
            document.getElementById('f-estado').value=ev.estado;
            if (ESTADO_COORDINADOR) {
                document.getElementById('f-estado').disabled = true;
            }
            // Inyectar municipio directamente sin esperar GEO_CACHE
            const _selMun = document.getElementById('f-municipio');
            if (_municipio && _selMun) {
                if (![..._selMun.options].some(o => o.value === _municipio)) {
                    const _o = document.createElement('option');
                    _o.value = _municipio; _o.textContent = _municipio;
                    _selMun.appendChild(_o);
                }
                _selMun.value = _municipio;
                _selMun.disabled = false;
            }
            // Mostrar sección inmediatamente
            if (ev.seccion) {
                document.getElementById('f-seccion').value = ev.seccion;
                document.getElementById('sec-agenda-display').value = ev.seccion;
                document.getElementById('sec-agenda-display').placeholder = '';
            }
            // Cargar municipios completos en background y re-asegurar valores
            cargarMunicipios(ev.estado, () => {
                const _sm = document.getElementById('f-municipio');
                if (_municipio && _sm) {
                    _sm.value = _municipio;
                    // Re-asignar zona por si onModalRegionChange la limpió
                    if (elZona && _zonaNombre) {
                        const _oz = [...elZona.options].find(o => o.value === _zonaNombre || o.textContent === _zonaNombre);
                        if (_oz) elZona.value = _oz.value;
                    }
                    cargarSecciones(_municipio, () => {
                        if (ev.seccion) {
                            document.getElementById('f-seccion').value = ev.seccion;
                            document.getElementById('sec-agenda-display').value = ev.seccion;
                        }
                    });
                }
            });
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

        // Solo muestra Eliminar si el usuario actual fue quien creó el evento
        if (_esPropio) {
            btnDel.style.visibility='visible';
            btnDel.onclick=()=>eliminarEvento(id);
        } else {
            btnDel.style.visibility='hidden';
        }
        // Estado "Realizado": bloquea edición/eliminación
        const _estaRealizado = (ev.realizado==1 || ev.realizado==='1');
        aplicarBloqueoRealizado(_estaRealizado, _esPropio && !_esAjeno);
    } else {
        document.getElementById('f-fecha').value = fechaPre || fmtDate(hoy);
        verificarFechaFutura();
        btnDel.style.visibility='hidden';

        if (ESTADO_COORDINADOR) {
            const elEstado = document.getElementById('f-estado');
            elEstado.value = ESTADO_COORDINADOR;
            elEstado.disabled = true;
            cargarMunicipios(ESTADO_COORDINADOR);
        }
        if (REGION_ACTUAL) {
            const elReg = document.getElementById('f-region');
            elReg.value = REGION_ACTUAL;
            onModalRegionChange();
        }
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


function verificarFechaFutura() {
    const fechaVal = document.getElementById('f-fecha').value;
    const aviso = document.getElementById('aviso-fecha-futura');
    if (!aviso) return;
    if (fechaVal) {
        const hoy = new Date(); hoy.setHours(0,0,0,0);
        const seleccionada = new Date(fechaVal + 'T00:00:00');
        aviso.style.display = seleccionada > hoy ? 'flex' : 'none';
    } else {
        aviso.style.display = 'none';
    }
}

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
    const _municipioVal = document.getElementById('f-municipio').value;
    if(!_municipioVal){
        document.getElementById('f-municipio').focus();
        document.getElementById('f-municipio').style.borderColor='#dc2626';
        setTimeout(()=>document.getElementById('f-municipio').style.borderColor='',2500);
        alert('Selecciona el municipio del evento.');
        return;
    }
    const _seccionVal = document.getElementById('f-seccion').value;
    if(!_seccionVal){
        document.getElementById('sec-agenda-display').focus();
        document.getElementById('sec-agenda-display').style.borderColor='#dc2626';
        setTimeout(()=>document.getElementById('sec-agenda-display').style.borderColor='',2500);
        alert('Selecciona la sección electoral del evento.');
        return;
    }
    // Aviso si la fecha es futura
    const _hoy = new Date(); _hoy.setHours(0,0,0,0);
    const _fechaSel = new Date(fecha + 'T00:00:00');
    if (_fechaSel > _hoy) {
        const _diasDif = Math.round((_fechaSel - _hoy) / 86400000);
        const _confirmar = confirm(`⚠️ Fecha futura detectada\n\nEstás registrando un evento para el ${fecha} (en ${_diasDif} día${_diasDif!==1?'s':''}).\n\n¿Deseas continuar y guardar el evento?`);
        if (!_confirmar) return;
    }
    let horas=parseFloat(document.getElementById('f-horas').value)||0;
    const ini=document.getElementById('f-inicio').value, fin=document.getElementById('f-fin').value;
    if(ini&&fin){
        const[hi,mi]=ini.split(':').map(Number),[hf,mf]=fin.split(':').map(Number);
        if(hf*60+mf<=hi*60+mi){
            const finEl=document.getElementById('f-fin');
            finEl.style.borderColor='#dc2626';finEl.style.background='#fef2f2';finEl.focus();
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
    ['eje','subtipo','titulo','fecha','municipio','region','zona','responsable','notas'].forEach(k=>{
        const el=document.getElementById('f-'+k)||{value:k==='eje'?ejeSeleccionado:''};
        fd.append(k, k==='eje'?ejeSeleccionado:(el.value||''));
    });
    const elEstado = document.getElementById('f-estado');
    fd.append('estado', elEstado.disabled ? ESTADO_COORDINADOR : (elEstado.value || ''));
    fd.append('seccion', document.getElementById('f-seccion').value || '');
    fd.append('inicio',  document.getElementById('f-inicio').value  || '');
    fd.append('fin',     document.getElementById('f-fin').value     || '');
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

// ══════════════════════════════════════════════════════
//  FILTROS: Estado · Región · Zona · Sección · Eje
// ══════════════════════════════════════════════════════

// Mapa región → estados
const REGION_ESTADOS = {
    'Norte':           ['Chihuahua','Coahuila','Durango','Nuevo León','Tamaulipas','Zacatecas'],
    'Noreste':         ['Nuevo León','Tamaulipas','Coahuila'],
    'Noroeste':        ['Baja California','Baja California Sur','Sinaloa','Sonora','Nayarit'],
    'Centro':          ['Ciudad de México','Hidalgo','México','Morelos','Puebla','Querétaro','Tlaxcala'],
    'Centro-Occidente':['Aguascalientes','Colima','Guanajuato','Jalisco','Michoacán'],
    'Sur':             ['Guerrero','Oaxaca','Chiapas'],
    'Sureste':         ['Tabasco','Veracruz'],
    'Península':       ['Campeche','Quintana Roo','Yucatán']
};

// Cache de secciones disponibles según eventos
let seccionesDisponibles = new Set();

// Mapas de cascada: región→zonas y zona→secciones (generados desde PHP)
const REGION_ZONAS_MAP      = <?php echo json_encode($region_zonas_map,      JSON_UNESCAPED_UNICODE); ?>;
const ZONA_SECCIONES_MAP    = <?php echo json_encode($zona_secciones_map,    JSON_UNESCAPED_UNICODE); ?>;
const REGION_MUNICIPIOS_MAP = <?php echo json_encode($region_municipios_map, JSON_UNESCAPED_UNICODE); ?>;
const TIPO_INTEGRACION_REGION = <?php echo json_encode($tipo_integracion_region ?? 'municipios', JSON_UNESCAPED_UNICODE); ?>;

function getEtiquetaIntegracionRegional() {
    if (TIPO_INTEGRACION_REGION === 'distrito_local')   return { label: 'Distrito Local *', vacio: '— Distrito Local —', sinDatos: '— Sin distritos —', prefijo: 'DL ', placeholder: '— Selecciona distrito local primero —', vacioSel: 'Selecciona distrito local primero' };
    if (TIPO_INTEGRACION_REGION === 'distrito_federal') return { label: 'Distrito Federal *', vacio: '— Distrito Federal —', sinDatos: '— Sin distritos —', prefijo: 'DF ', placeholder: '— Selecciona distrito federal primero —', vacioSel: 'Selecciona distrito federal primero' };
    return { label: 'Municipio *', vacio: '— Municipio —', sinDatos: '— Sin municipios —', prefijo: '', placeholder: '— Selecciona municipio primero —', vacioSel: 'Selecciona municipio primero' };
}

(function aplicarEtiquetaIntegracionRegional() {
    const et = getEtiquetaIntegracionRegional();
    const lbl = document.getElementById('f-municipio-label');
    if (lbl) lbl.textContent = et.label;
    const disp = document.getElementById('sec-agenda-display');
    if (disp) disp.placeholder = et.placeholder;
})();
const SECCIONES_GEO_ACT     = <?php echo json_encode($secciones_geo ?? [], JSON_UNESCAPED_UNICODE); ?>;

// Mapas inversos: sección→zona y zona→región (para auto-detección)
const SECCION_ZONA_MAP = {};
for (const [zona, secs] of Object.entries(ZONA_SECCIONES_MAP)) {
    (secs || []).forEach(s => { SECCION_ZONA_MAP[String(s)] = zona; });
}
const ZONA_REGION_MAP = {};
for (const [region, zonas] of Object.entries(REGION_ZONAS_MAP)) {
    (zonas || []).forEach(z => { ZONA_REGION_MAP[z] = region; });
}

// Auto-detecta región y zona a partir de la sección elegida
function autoDetectarRegionZona(seccion) {
    const zona = SECCION_ZONA_MAP[String(seccion)];
    if (!zona) return;
    const region = ZONA_REGION_MAP[zona];
    if (region) {
        const selRegion = document.getElementById('f-region');
        if (selRegion) { selRegion.value = region; onModalRegionChange(); }
    }
    // Asignar zona tras repoblar el select (onModalRegionChange ya lo rellena)
    setTimeout(() => {
        const selZona = document.getElementById('f-zona');
        if (selZona && [...selZona.options].some(o => o.value === zona)) {
            selZona.value = zona;
        }
    }, 0);
}

function onFiltroRegionChange() {
    const region = document.getElementById('fil-region').value;
    const selZona = document.getElementById('fil-zona');
    selZona.innerHTML = '<option value="">Todas las zonas</option>';
    const zonas = region ? (REGION_ZONAS_MAP[region] || []) : Object.keys(ZONA_SECCIONES_MAP).sort();
    zonas.forEach(z => {
        const o = document.createElement('option');
        o.value = z; o.textContent = z;
        selZona.appendChild(o);
    });
    // Resetear zona y sección
    selZona.value = '';
    resetFiltroSecciones();
    aplicarFiltros();
}

// Filtra el select de Zona del MODAL según la Región seleccionada
// y filtra también los municipios disponibles
function onModalRegionChange() {
    const region   = document.getElementById('f-region').value;
    const selZona  = document.getElementById('f-zona');
    const zonaAnterior = selZona.value;

    // ── Cascada Zonas ──
    selZona.innerHTML = '<option value="">— Zona —</option>';
    const zonas = region ? (REGION_ZONAS_MAP[region] || []) : Object.keys(ZONA_SECCIONES_MAP).sort();
    zonas.forEach(z => {
        const o = document.createElement('option');
        o.value = z; o.textContent = z;
        selZona.appendChild(o);
    });
    selZona.value = zonas.includes(zonaAnterior) ? zonaAnterior : '';

    // ── Filtrar Municipios ──
    const municipiosRegion = region ? (REGION_MUNICIPIOS_MAP[region] || []) : null;
    const selMun   = document.getElementById('f-municipio');
    const munActual = selMun.value;
    const estadoLabel = document.getElementById('f-estado')?.value || '';
    let allMunis = (Object.values(GEO_CACHE).find(e => e.label === estadoLabel)?.municipios || []);

    // Fallback: si GEO_CACHE está vacío, usar los municipios de la región desde PHP
    if (!allMunis.length) {
        const todosRegion = Object.values(REGION_MUNICIPIOS_MAP).flat();
        if (todosRegion.length) allMunis = [...new Set(todosRegion)];
    }

    const filtrados = municipiosRegion
        ? allMunis.filter(m => municipiosRegion.includes(m))
        : allMunis;

    const et = getEtiquetaIntegracionRegional();
    selMun.innerHTML = `<option value="">${et.vacio}</option>`;
    [...filtrados].sort().forEach(m => {
        const o = document.createElement('option');
        o.value = m; o.textContent = et.prefijo + m;
        selMun.appendChild(o);
    });
    selMun.disabled = filtrados.length === 0;

    // Si el municipio actual ya no es válido para la región, limpiar sección
    if (munActual && municipiosRegion && !municipiosRegion.includes(munActual)) {
        selMun.value = '';
        secAgendaReset(et.vacioSel);
    } else {
        selMun.value = munActual;
    }
}

function onFiltroZonaChange() {
    const zona = document.getElementById('fil-zona').value;
    const selSec = document.getElementById('fil-seccion');
    selSec.innerHTML = '<option value="">Todas las secciones</option>';
    const secciones = zona ? (ZONA_SECCIONES_MAP[zona] || []) : [];
    [...secciones].sort((a,b) => Number(a)-Number(b)).forEach(s => {
        const o = document.createElement('option');
        o.value = s; o.textContent = 'Sección ' + String(s);
        selSec.appendChild(o);
    });
    selSec.value = '';
    aplicarFiltros();
}

function resetFiltroSecciones() {
    const selSec = document.getElementById('fil-seccion');
    selSec.innerHTML = '<option value="">Todas las secciones</option>';
    selSec.value = '';
}

let modoVerTodoCE  = false; // false = Mi Agenda (propios), true = Ver Todo (toda la estructura)
let filtrosActivos = { estado:'', region:'', zona:'', seccion:'', eje:'' };
let eventosOriginales = [];   // copia completa sin filtrar, la llena cargarEventos() directo

function reconstruirSeccionesDisponibles() {
    // Si hay zona seleccionada, la cascada ya pobló fil-seccion; no sobreescribir
    const zonaActual = document.getElementById('fil-zona').value;
    if (zonaActual) return;
    // Sin zona: poblar con secciones que aparecen en eventos
    seccionesDisponibles.clear();
    eventosOriginales.forEach(e => { if (e.seccion) seccionesDisponibles.add(e.seccion); });
    const sel = document.getElementById('fil-seccion');
    const prev = sel.value;
    sel.innerHTML = '<option value="">Todas las secciones</option>';
    [...seccionesDisponibles].sort((a,b)=>Number(a)-Number(b)).forEach(s => {
        const o = document.createElement('option');
        o.value = s; o.textContent = 'Sección ' + String(s);
        sel.appendChild(o);
    });
    sel.value = prev;
}

function aplicarFiltros() {
    filtrosActivos.estado  = document.getElementById('fil-estado').value;
    filtrosActivos.region  = document.getElementById('fil-region').value;
    filtrosActivos.zona    = document.getElementById('fil-zona').value;
    filtrosActivos.seccion = document.getElementById('fil-seccion').value;
    filtrosActivos.eje     = document.getElementById('fil-eje').value;

    // Determina si un evento fue creado por el coordinador estatal actual
    const esEventoPropio = ev =>
        (ev.creado_por_id && parseInt(ev.creado_por_id) === USUARIO_ID) ||
        (!ev.creado_por_id && (ev.creado_por_rol === 'coordinador_regional' || ev.creado_por_rol === 'regional'));

    // Mi Agenda (toggle ON)  → solo los eventos propios del estatal
    // Ver Todo  (toggle OFF) → solo eventos de zona y regional (excluye los propios)
    const baseEventos = modoVerTodoCE
        ? eventosOriginales.filter(ev => !esEventoPropio(ev))
        : eventosOriginales.filter(ev =>  esEventoPropio(ev));

    eventos = baseEventos.filter(ev => {
        if (filtrosActivos.estado  && ev.estado    !== filtrosActivos.estado)  return false;
        if (filtrosActivos.region  && (ev.region||'') !== filtrosActivos.region) return false;
        if (filtrosActivos.zona) {
            // ev.zona puede ser ID numérico (coord. zona) o nombre (coord. estatal)
            const evZonaNombre = ZONA_ID_TO_NAME[String(ev.zona)] || ev.zona || '';
            if (evZonaNombre !== filtrosActivos.zona) return false;
        }
        if (filtrosActivos.seccion && String(ev.seccion) !== filtrosActivos.seccion) return false;
        if (filtrosActivos.eje     && ev.eje       !== filtrosActivos.eje)      return false;
        return true;
    });

    const total = eventosOriginales.length;
    const shown = eventos.length;
    const hayFiltro = Object.values(filtrosActivos).some(v => v !== '');
    const res = document.getElementById('fil-results');
    if (hayFiltro) {
        res.innerHTML = `Mostrando <b>${shown}</b> de <b>${total}</b> eventos`;
    } else {
        res.textContent = '';
    }

    const btnLimpiar = document.querySelector('.filter-clear');
    if (btnLimpiar) {
        btnLimpiar.style.borderColor = hayFiltro ? 'var(--primary)' : '';
        btnLimpiar.style.color       = hayFiltro ? 'var(--primary)' : '';
        btnLimpiar.style.background  = hayFiltro ? 'var(--primary-light)' : '';
    }

    render();
}

function limpiarFiltros() {
    // fil-estado es hidden en regional, no se limpia
    document.getElementById('fil-region').value  = '';
    document.getElementById('fil-eje').value     = '';
    onFiltroRegionChange(); // repuebla zonas y secciones y llama aplicarFiltros
}

document.addEventListener('DOMContentLoaded', () => {
    const selEstado = null; // fil-estado es hidden en regional
    if (selEstado) {
        selEstado.addEventListener('change', () => {
            const est = selEstado.value;
            if (!est) return;
            const selRegion = document.getElementById('fil-region');
            if (!selRegion.value) {
                for (const [reg, ests] of Object.entries(REGION_ESTADOS)) {
                    if (ests.includes(est)) { selRegion.value = reg; break; }
                }
            }
        });
    }

    if (ESTADO_COORDINADOR) {
        const fe = document.getElementById('fil-estado');
        if (fe) { fe.value = ESTADO_COORDINADOR; fe.disabled = true; }
    }
});

// ── Toggle Mi Agenda / Ver Todo ──────────────────────
function toggleVistaCE() {
    modoVerTodoCE = !modoVerTodoCE;
    const track = document.getElementById('ce-toggle-track');
    const label = document.getElementById('ce-toggle-label');
    const btn   = document.getElementById('toggle-vista-btn');
    const selRegion = document.getElementById('fil-region');
    const selZona   = document.getElementById('fil-zona');
    const selSec    = document.getElementById('fil-seccion');
    if (modoVerTodoCE) {
        track.classList.remove('on');
        label.textContent = 'Ver Todo';
        btn.classList.add('ver-todo');
        // "Ver Todo" = el resto de eventos de la región (zona + regional),
        // excluyendo los propios. El backend (procesar.php action=list) ya
        // filtra eventosOriginales para regresar solo los eventos de la
        // región del coordinador regional, así que no hace falta filtrar
        // de nuevo por región aquí.
        selRegion.value = '';
        selRegion.disabled = false; selZona.disabled = false; selSec.disabled = false;
    } else {
        track.classList.add('on');
        label.textContent = 'Mi Agenda';
        btn.classList.remove('ver-todo');
        // Limpiar filtros de región/zona al volver a Mi Agenda
        selRegion.value = ''; selZona.value = ''; selSec.value = '';
        selRegion.disabled = true; selZona.disabled = true; selSec.disabled = true;
        filtrosActivos.region = ''; filtrosActivos.zona = ''; filtrosActivos.seccion = '';
    }
    aplicarFiltros();
}

// ── Init ──────────────────────────────────────────────
(async()=>{
    await geoInicializar();
    // Iniciar con filtros de región/zona deshabilitados (modo Mi Agenda),
    // y region en blanco — igual que hace toggleVistaCE() al volver a Mi Agenda.
    document.getElementById('fil-region').value     = '';
    document.getElementById('fil-region').disabled  = true;
    document.getElementById('fil-zona').disabled    = true;
    document.getElementById('fil-seccion').disabled = true;
    await cargarEventos();
    render();
})();
</script>
</body>
</html>