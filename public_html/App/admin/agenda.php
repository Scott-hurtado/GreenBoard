<?php
if (file_exists(__DIR__ . '/../../includes/bootstrap.php')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
} else { session_start(); }
$rol = $_SESSION['user_role'] ?? '';
$permitidos = ['admin','administrador','superadmin','super admin'];
if (!in_array($rol, $permitidos)) { header('Location: /public/index.php'); exit(); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agenda Inteligente - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        transition:filter 0.1s;display:block;
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
    .week-allday-cell{border-left:1px solid var(--gray-200);padding:2px 3px;display:flex;flex-direction:column;gap:2px;}

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
    .popover-body{padding:16px 20px 20px;}
    .popover-title{font-size:16px;font-weight:700;color:var(--text);margin-bottom:8px;line-height:1.3;}
    .popover-badge{display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:700;padding:3px 10px;border-radius:12px;margin-bottom:12px;}
    .popover-row{display:flex;align-items:flex-start;gap:10px;margin-bottom:8px;font-size:13px;color:var(--text-muted);}
    .popover-row i{width:16px;flex-shrink:0;margin-top:2px;}
    .popover-row span{color:var(--text);}
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
    .mbtn{padding:11px 24px;border-radius:12px;font-size:14px;font-weight:600;cursor:pointer;border:none;transition:all 0.2s;font-family:inherit;}
    .mbtn-save{background:var(--primary);color:white;box-shadow:0 2px 8px rgba(45,122,62,0.3);}
    .mbtn-save:hover{background:var(--primary-dark);}
    .mbtn-cancel{background:var(--white);color:var(--text-muted);border:1px solid var(--gray-200);}
    .mbtn-cancel:hover{border-color:var(--gray-400);color:var(--text);}
    .mbtn-del{background:#fef2f2;color:#dc2626;border:1px solid #fecaca;}
    .mbtn-del:hover{background:#fee2e2;}


    /* ── FILTER BAR (Super Admin) ── */
    .filter-bar{
        display:flex;align-items:center;gap:10px;flex-wrap:wrap;
        padding:10px 24px;border-bottom:1px solid var(--gray-200);
        background:var(--gray-50);flex-shrink:0;
    }
    .filter-bar-label{
        font-size:11px;font-weight:700;text-transform:uppercase;
        letter-spacing:0.5px;color:var(--text-muted);white-space:nowrap;
        display:flex;align-items:center;gap:5px;
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

<?php if (file_exists(__DIR__ . '/../../includes/components/sidebar_admin.php')) include __DIR__ . '/../../includes/components/sidebar_admin.php'; ?>

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
    <span class="filter-bar-label"><i class="fas fa-filter"></i> Filtros:</span>

    <select class="filter-select" id="fil-estado" onchange="filtroEstadoChange()">
        <option value="">🗺️ Todos los estados</option>
    </select>

    <select class="filter-select" id="fil-municipio" onchange="applyFilters()" disabled>
        <option value="">🏘️ Municipio</option>
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
            <div class="balance-box-title">Balance semanal</div>
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
        <div class="popover-row" id="pop-activacion"><i class="fas fa-user-tie"></i><span></span></div>
        <div class="popover-row" id="pop-notas"><i class="fas fa-align-left"></i><span></span></div>
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
                <select id="f-estado" class="finput" onchange="cargarMunicipios(this.value)">
                    <option value="">— Estado —</option>
                </select>
            </div>
            <div class="fg" style="margin-bottom:0;">
                <label class="flabel">Municipio</label>
                <select id="f-municipio" class="finput" onchange="cargarSecciones(this.value)" disabled>
                    <option value="">— Municipio —</option>
                </select>
            </div>
        </div>
        <div class="form-grid-3" style="margin-bottom:18px;">
            <div class="fg" style="margin-bottom:0;">
                <label class="flabel">
                    Sección
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
                <input type="time" id="f-inicio" class="finput" onchange="limpiarErrorFin()">
            </div>
            <div class="fg" style="margin-bottom:0;">
                <label class="flabel">Fin</label>
                <input type="time" id="f-fin" class="finput" onchange="calcHoras()">
                <span id="hora-error-msg" style="display:none;font-size:11px;font-weight:700;color:#dc2626;margin-top:4px;display:none;">
                    <i class="fas fa-exclamation-circle"></i> El fin debe ser posterior al inicio
                </span>
            </div>
        </div>
        
        <div class="form-grid-3" style="margin-bottom:18px;">
            <div class="fg" style="margin-bottom:0;">
                <label class="flabel">Horas</label>
                <input type="number" id="f-horas" class="finput" min="0" max="24" step="0.5" placeholder="0">
            </div>
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

        <div class="mfooter">
            <button class="mbtn mbtn-del" id="btn-del-ev" style="visibility:hidden" onclick="eliminarEvento(editandoId)">
                <i class="fas fa-trash-alt"></i> Eliminar
            </button>
            <div style="display:flex;gap:10px;">
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
const SECCIONES_API = '/api/secciones/secciones_api.php';
let eventosFiltrados = [];

// ══════════════════════════════════════════════════════
//  FILTROS SUPER ADMIN Y DATA GEOGRÁFICA DE BD
// ══════════════════════════════════════════════════════

// Mapeo dinámico alimentado desde la BD: { "Sonora": ["Hermosillo", "Cajeme"], "Jalisco": [...] }
let GEO_MAP = {}; 

// Estado activo de filtros
let filEstado    = '';
let filMunicipio = '';
let filEjes      = new Set(); // vacío = todos

// ── Carga desde la BD mediante el nuevo action=get_geo ──
async function geoInicializar() {
    try {
        const res  = await fetch(PROCESAR_URL + '?action=get_geo');
        const data = await res.json();
        
        if (!data.ok) throw new Error(data.msg || 'Error al cargar geografía');
        
        GEO_MAP = data.municipios;
        const listaEstados = data.estados.map(e => e.nombre);

        // Poblar selects de estado (en el filtro superior y en el modal)
        ['fil-estado', 'f-estado'].forEach(id => {
            const sel = document.getElementById(id);
            if (!sel) return;
            // Preservar la opción inicial
            sel.innerHTML = id === 'fil-estado' ? '<option value="">🗺️ Todos los estados</option>' : '<option value="">— Estado —</option>';
            listaEstados.forEach(est => {
                const o = document.createElement('option');
                o.value = est; o.textContent = est;
                sel.appendChild(o);
            });
        });
    } catch(err) {
        console.error('Fallo en geoInicializar:', err);
    }
}

function filtroEstadoChange() {
    filEstado  = document.getElementById('fil-estado').value;
    filMunicipio = '';
    
    const selMun = document.getElementById('fil-municipio');
    selMun.innerHTML = '<option value="">🏘️ Municipio</option>';
    selMun.disabled  = true;
    
    updateSelectStyle('fil-estado', !!filEstado);
    updateSelectStyle('fil-municipio', false);
    
    // Si hay un estado seleccionado, cargamos sus municipios reales de la BD
    if (filEstado && GEO_MAP[filEstado]) {
        GEO_MAP[filEstado].forEach(m => { 
            const o = document.createElement('option'); o.value = m; o.textContent = m; selMun.appendChild(o); 
        });
        selMun.disabled = false;
    }
    applyFilters();
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
    // Tomamos el municipio del filtro (si existe)
    filMunicipio = document.getElementById('fil-municipio').value;
    updateSelectStyle('fil-municipio', !!filMunicipio);

    eventosFiltrados = eventos.filter(ev => {
        if (filEstado     && (ev.estado||'')    !== filEstado)    return false;
        if (filMunicipio  && (ev.municipio||'') !== filMunicipio) return false;
        if (filEjes.size > 0 && !filEjes.has(ev.eje))             return false;
        return true;
    });
    
    const hasFilter = !!(filEstado || filMunicipio || filEjes.size);
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
    filEstado=''; filMunicipio=''; filEjes.clear();
    document.getElementById('fil-estado').value='';
    document.getElementById('fil-municipio').innerHTML='<option value="">🏘️ Municipio</option>';
    document.getElementById('fil-municipio').disabled=true;
    
    ['fil-estado','fil-municipio'].forEach(id=>updateSelectStyle(id,false));
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

// ── Estado global ──────────────────────────────────────
let eventos    = [];
let vista      = 'week';     
let hoy        = new Date(); hoy.setHours(0,0,0,0);
let cursor     = new Date(hoy); 
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

// ── Cargar eventos ────────────────────────────────────
async function cargarEventos() {
    try {
        const res  = await fetch(PROCESAR_URL + '?action=list');
        const data = await res.json();
        if (data.ok) { eventos = data.eventos.map(e => ({...e, id: parseInt(e.id)})); eventosFiltrados = [...eventos]; }
    } catch(e) { console.error(e); }
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

// ══════════════════════════════════════════════════════
//  MINI CALENDARIO Y BALANCE
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
    const inicioSem = getInicioSemana(cursor);
    const fechas = Array.from({length:7},(_,i)=>{ const d=new Date(inicioSem); d.setDate(inicioSem.getDate()+i); return fmtDate(d); });
    const evsSem = eventosFiltrados.filter(e => fechas.includes(e.fecha));
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
    const proximos = eventos
        .filter(e => e.fecha >= hoyStr)
        .filter(e => eventosFiltrados.includes(e))
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
            <div class="upcoming-ev-title">${ev.titulo}</div>
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
    const ev = eventos.find(x => x.id === id); if (!ev) return;
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
    setRow('pop-lugar', [ev.estado, ev.municipio, ev.seccion?'Sec.'+ev.seccion:null].filter(Boolean).join(', ')||null);
    setRow('pop-resp', ev.responsable||null);
    setRow('pop-activacion', ev.activacion||null); 
    setRow('pop-notas', ev.notas||null);
    document.getElementById('pop-del').onclick  = () => eliminarEvento(id);
    document.getElementById('pop-edit').onclick = () => { cerrarPopover(); abrirModal(id); };
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
//  MODAL CREAR/EDITAR
// ══════════════════════════════════════════════════════
function abrirModal(id=null, fechaPre=null) {
    cerrarPopover();
    editandoId = id;
    ejeSeleccionado = null;
    document.getElementById('modal-title').textContent = id ? 'Editar Evento' : 'Nuevo Evento';
    const btnDel = document.getElementById('btn-del-ev');

    ['f-titulo','f-inicio','f-fin','f-horas','f-responsable','f-activacion','f-notas'].forEach(x=>document.getElementById(x).value='');
    document.getElementById('f-estado').value='';
    document.getElementById('f-municipio').innerHTML='<option value="">— Municipio —</option>';
    document.getElementById('f-municipio').disabled=true;
    secAgendaReset('Selecciona municipio primero');
    document.querySelectorAll('.eje-pill').forEach(b=>b.className='eje-pill');
    document.getElementById('f-subtipo').innerHTML='<option value="">— Selecciona un eje primero —</option>';

    if (id) {
        const ev = eventos.find(e=>e.id===id); if(!ev) return;
        selEje(ev.eje);
        setTimeout(()=>{document.getElementById('f-subtipo').value=ev.subtipo||'';},50);
        
        ['titulo','fecha','inicio','fin','horas','responsable','activacion','notas'].forEach(k=>{
            const el=document.getElementById('f-'+k); if(el) el.value=ev[k]||'';
        });
        if(ev.estado){
            document.getElementById('f-estado').value=ev.estado;
            cargarMunicipios(ev.estado, () => {
                document.getElementById('f-municipio').value=ev.municipio||'';
                if(ev.municipio){
                    cargarSecciones(ev.municipio, () => {
                        if(ev.seccion){
                            document.getElementById('f-seccion').value = ev.seccion;
                            document.getElementById('sec-agenda-display').value = ev.seccion;
                        }
                    });
                }
            }); 
        }
        btnDel.style.visibility='visible';
        btnDel.onclick=()=>eliminarEvento(id);
    } else {
        document.getElementById('f-fecha').value = fechaPre || fmtDate(hoy);
        btnDel.style.visibility='hidden';
    }
    document.getElementById('modal-form').classList.add('open');
}

function cerrarModal() { document.getElementById('modal-form').classList.remove('open'); }

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

// ── Cargar municipios al cambiar estado (modal) ───────────────────────────
function cargarMunicipios(estado, cb) {
    const selMun = document.getElementById('f-municipio');
    selMun.innerHTML = '<option value="">— Municipio —</option>';
    selMun.disabled  = true;
    secAgendaReset('Selecciona municipio primero');

    if (!estado) { if(cb) cb(); return; }

    // Usar GEO_MAP en vez de la caché antigua
    if (GEO_MAP[estado] && GEO_MAP[estado].length) {
        GEO_MAP[estado].forEach(m => {
            const o = document.createElement('option'); o.value = m; o.textContent = m;
            selMun.appendChild(o);
        });
        selMun.disabled = false;
    }
    if (cb) cb();
}

async function cargarSecciones(municipio, cb) {
    secAgendaReset('Cargando secciones…', true);
    if (!municipio) { secAgendaReset('Selecciona municipio primero'); if(cb) cb(); return; }

    const estadoLabel = document.getElementById('f-estado')?.value || '';
    
    // Buscar la clave del estado para enviarla a la API de secciones (que aún usa este formato)
    // Extraemos la primera clave disponible si necesitamos enviarla
    try {
        const url = `${SECCIONES_API}?action=geo_secciones&estado=${encodeURIComponent(estadoLabel)}&municipio=${encodeURIComponent(municipio)}`;
        const res  = await fetch(url);
        const data = await res.json();
        if (data.ok && data.secciones && data.secciones.length) {
            SEC_AGENDA = data.secciones;
            secAgendaActualizarContador(SEC_AGENDA.length);
            secAgendaRenderOpts(SEC_AGENDA);
            document.getElementById('sec-agenda-display').placeholder = `— Selecciona sección (${SEC_AGENDA.length}) —`;
        } else {
            throw new Error('sin secciones');
        }
    } catch(err) {
        SEC_AGENDA = Array.from({length:20}, (_,i) => 'Sección ' + String(i+1).padStart(4,'0'));
        secAgendaActualizarContador(SEC_AGENDA.length);
        secAgendaRenderOpts(SEC_AGENDA);
        document.getElementById('sec-agenda-display').placeholder = `— Selecciona sección —`;
    }
    if (cb) cb();
}


// ══════════════════════════════════════════════════════════
//  BUSCADOR SECCIÓN — AGENDA
// ══════════════════════════════════════════════════════════

function secAgendaReset(msg = 'Selecciona municipio primero', loading = false) {
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

async function guardarEvento() {
    const titulo=document.getElementById('f-titulo').value.trim();
    const fecha=document.getElementById('f-fecha').value;
    const btn=document.getElementById('btn-save-ev');
    if(!titulo){document.getElementById('f-titulo').focus();return;}
    if(!fecha||!ejeSeleccionado){return;}
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
    
    ['eje','subtipo','titulo','fecha','estado','municipio','seccion','responsable','activacion','notas'].forEach(k=>{
        const el=document.getElementById('f-'+k)||{value:k==='eje'?ejeSeleccionado:''};
        fd.append(k, k==='eje'?ejeSeleccionado:(el.value||''));
    });
    fd.append('inicio', document.getElementById('f-inicio').value || '');
    fd.append('fin',    document.getElementById('f-fin').value    || '');
    fd.append('horas',horas);
    
    btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Guardando…';
    try{
        const res=await fetch(PROCESAR_URL,{method:'POST',body:fd});
        const data=await res.json();
        if(!data.ok)throw new Error(data.msg||'Error');
        await cargarEventos();
        cerrarModal(); render();
    }catch(e){alert('Error: '+e.message);}
    finally{btn.disabled=false;btn.innerHTML='<i class="fas fa-check"></i> Guardar';}
}

async function eliminarEvento(id){
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