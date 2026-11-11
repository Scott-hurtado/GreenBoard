<?php
// ========================================
// 1. SESIÓN Y REDIRECCIÓN ANTES DE CUALQUIER SALIDA
// ========================================
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Verificación de sesión
if (!isset($_SESSION['user_id']) && !isset($_SESSION['is_admin'])) {
    header('Location: /iniciar_sesion.php');
    exit();
}

// ========================================
// 2. AHORA SÍ INCLUIMOS EL SIDEBAR ADMIN (después del header)
// ========================================
$sidebar_path = $_SERVER['DOCUMENT_ROOT'] . '/includes/components/sidebar_admin.php';
if (file_exists($sidebar_path)) {
    include_once $sidebar_path;
} else {
    die('Error: No se encontró el archivo sidebar_admin.php');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión Estratégica - Proyecto Verde</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* --- ESTILOS BASE & VAR --- */
        :root {
            --primary: #2d7a3e;
            --primary-dark: #1f5a2d;
            --dark: #1a1a1a;
            --light: #f0f7f0;
            --white: #ffffff;
            --shadow-sm: 0 10px 30px rgba(0,0,0,0.05);
            --shadow-hover: 0 20px 40px rgba(45, 122, 62, 0.15);
            --glass: rgba(255, 255, 255, 0.95);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Arial', sans-serif; 
            background: var(--light); 
            color: var(--dark); 
            overflow-x: hidden; 
        }
        .container { 
            max-width: 100%; 
            margin: 0; 
            background: var(--white); 
            min-height: 100vh; 
            position: relative; 
            padding-left: 280px;
            transition: padding-left 0.3s ease;
        }

        /* --- ANIMACIONES --- */
        @keyframes slideInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes pulse-green { 0% { box-shadow: 0 0 0 0 rgba(45, 122, 62, 0.4); } 70% { box-shadow: 0 0 0 10px rgba(45, 122, 62, 0); } 100% { box-shadow: 0 0 0 0 rgba(45, 122, 62, 0); } }
        
        .animate-stagger > * { opacity: 0; animation: slideInUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        .animate-stagger > *:nth-child(1) { animation-delay: 0.1s; }
        .animate-stagger > *:nth-child(2) { animation-delay: 0.2s; }
        .animate-stagger > *:nth-child(3) { animation-delay: 0.3s; }
        .animate-stagger > *:nth-child(4) { animation-delay: 0.4s; }
        .animate-stagger > *:nth-child(5) { animation-delay: 0.5s; }
        .animate-stagger > *:nth-child(6) { animation-delay: 0.6s; }

        /* --- HEADER --- */
        .page-header { 
            padding: 40px 40px 40px 40px; 
            position: relative; 
            overflow: hidden; 
        }
        .ai-badge { 
            color: var(--primary); 
            font-size: 14px; 
            font-weight: 700; 
            letter-spacing: 6px; 
            margin-bottom: 20px; 
            display: inline-block; 
            text-transform: uppercase; 
        }
        .main-title { 
            font-size: 64px; 
            font-weight: 900; 
            line-height: 1; 
            margin-bottom: 20px; 
            position: relative; 
            z-index: 2; 
        }
        .description { 
            color: #666; 
            font-size: 16px; 
            line-height: 1.8; 
            max-width: 600px; 
            position: relative; 
            z-index: 2; 
        }
        
        .design-text { 
            position: absolute; 
            left: 20px; 
            top: 50%; 
            writing-mode: vertical-rl; 
            font-size: 13px; 
            font-weight: 500; 
            color: var(--primary); 
            letter-spacing: 2px; 
            opacity: 0.5; 
        }
        .dots-1 { 
            width: 100px; 
            height: 40px; 
            background-image: radial-gradient(circle, #ddd 1px, transparent 1px); 
            background-size: 8px 8px; 
            position: absolute; 
            top: 100px; 
            right: 50px; 
            z-index: 0; 
        }

        .header-actions { 
            position: absolute; 
            top: 40px; 
            right: 40px; 
            z-index: 10; 
        }
        .btn-live-action {
            background: var(--primary); 
            color: white; 
            padding: 16px 32px; 
            border-radius: 50px; 
            border: none;
            font-weight: 800; 
            font-size: 14px; 
            cursor: pointer; 
            letter-spacing: 0.5px;
            box-shadow: 0 10px 25px rgba(45, 122, 62, 0.4); 
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            display: flex; 
            align-items: center; 
            gap: 12px;
        }
        .btn-live-action:hover { 
            transform: translateY(-4px) scale(1.02); 
            background: var(--primary-dark); 
            box-shadow: 0 20px 35px rgba(45, 122, 62, 0.5); 
        }

        /* --- TABS --- */
        .tabs-container { 
            padding: 0 40px; 
            margin-bottom: 40px; 
            border-bottom: 1px solid #eee; 
        }
        .tabs-nav { 
            display: flex; 
            gap: 30px; 
        }
        .tab-btn { 
            padding: 15px 5px; 
            background: transparent; 
            border: none; 
            font-size: 15px; 
            font-weight: 600; 
            color: #999; 
            cursor: pointer; 
            position: relative; 
            transition: all 0.3s ease; 
        }
        .tab-btn::after { 
            content: ''; 
            position: absolute; 
            bottom: -1px; 
            left: 0; 
            width: 0; 
            height: 3px; 
            background: var(--primary); 
            transition: width 0.3s ease; 
        }
        .tab-btn:hover { color: var(--primary); }
        .tab-btn.active { color: var(--dark); font-weight: 800; }
        .tab-btn.active::after { width: 100%; }

        .tab-content { 
            display: none; 
            padding: 20px 40px 40px 40px; 
            opacity: 0; 
            transition: opacity 0.4s ease; 
        }
        .tab-content.active { 
            display: block; 
            opacity: 1; 
        }

        /* --- TARJETAS --- */
        .grid-overview { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); 
            gap: 25px; 
            margin-bottom: 50px; 
        }
        .stat-card { 
            background: white; 
            border: 1px solid #eee; 
            padding: 25px; 
            border-radius: 16px; 
            text-align: center; 
            transition: all 0.3s ease; 
            position: relative; 
            overflow: hidden; 
        }
        .stat-card:hover { 
            transform: translateY(-10px); 
            box-shadow: var(--shadow-hover); 
            border-color: var(--primary); 
        }
        .stat-card i { 
            font-size: 32px; 
            color: var(--primary); 
            margin-bottom: 15px; 
            opacity: 0.8; 
        }
        .stat-card .number { 
            font-size: 42px; 
            font-weight: 900; 
            color: var(--dark); 
            display: block; 
            line-height: 1; 
        }
        .stat-card .label { 
            font-size: 13px; 
            color: #666; 
            font-weight: 600; 
            text-transform: uppercase; 
            margin-top: 5px; 
        }

        .grid-2 { 
            display: grid; 
            grid-template-columns: repeat(2, 1fr); 
            gap: 30px; 
            margin-bottom: 30px; 
        }
        .grid-3 { 
            display: grid; 
            grid-template-columns: repeat(3, 1fr); 
            gap: 30px; 
            margin-bottom: 30px; 
        }

        .info-card { 
            background: #fcfcfc; 
            border: 1px solid #eee; 
            border-radius: 12px; 
            padding: 30px; 
            transition: all 0.3s ease; 
        }
        .info-card:hover { 
            background: white; 
            box-shadow: var(--shadow-sm); 
            transform: translateX(5px); 
            border-left: 5px solid var(--primary); 
        }
        /* Estilo para los nuevos objetivos/estrategias */
        .info-card.new-item { 
            border-left: 5px solid var(--primary); 
        }
        .info-card.new-item .new-badge {
            font-size:10px; 
            background:var(--primary); 
            color:var(--white); 
            padding:2px 6px; 
            border-radius:4px; 
            margin-left:auto;
        }

        .strategy-card { 
            background: white; 
            border: 1px solid #eee; 
            border-radius: 16px; 
            padding: 30px; 
            transition: all 0.4s ease; 
            position: relative; 
            z-index: 1; 
        }
        .strategy-card::before { 
            content: ''; 
            position: absolute; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 0; 
            background: #f0f7f0; 
            z-index: -1; 
            transition: height 0.4s ease; 
            border-radius: 16px; 
        }
        .strategy-card:hover::before { height: 100%; }
        .strategy-number { 
            font-size: 40px; 
            font-weight: 900; 
            color: rgba(45, 122, 62, 0.1); 
            position: absolute; 
            top: 15px; 
            right: 20px; 
            transition: all 0.4s ease; 
        }
        .strategy-card:hover .strategy-number { 
            color: var(--primary); 
            transform: scale(1.2) rotate(-10deg); 
            opacity: 0.2; 
        }

        .timeline-container { 
            position: relative; 
            padding-left: 50px; 
        }
        .timeline-line { 
            position: absolute; 
            left: 15px; 
            top: 0; 
            bottom: 0; 
            width: 2px; 
            background: #eee; 
        }
        .timeline-item { 
            position: relative; 
            margin-bottom: 40px; 
        }
        .timeline-dot { 
            position: absolute; 
            left: -43px; 
            top: 0; 
            width: 16px; 
            height: 16px; 
            background: white; 
            border: 4px solid var(--primary); 
            border-radius: 50%; 
            z-index: 2; 
            transition: all 0.3s; 
        }
        /* Estilo para nuevos eventos de cronograma */
        .timeline-item.new-item .timeline-dot {
            border-color: var(--dark); /* Cambia el color para resaltar */
        }
        .timeline-content.new-item { 
            border-left: 5px solid var(--primary); 
        }

        .timeline-content { 
            background: white; 
            border: 1px solid #eee; 
            padding: 25px; 
            border-radius: 12px; 
            position: relative; 
            transition: all 0.3s; 
        }
        .timeline-content::after { 
            content: ''; 
            position: absolute; 
            left: -8px; 
            top: 15px; 
            width: 15px; 
            height: 15px; 
            background: white; 
            border-left: 1px solid #eee; 
            border-bottom: 1px solid #eee; 
            transform: rotate(45deg); 
        }
        .t-date { 
            display: inline-block; 
            background: var(--primary); 
            color: white; 
            padding: 4px 12px; 
            border-radius: 20px; 
            font-size: 11px; 
            font-weight: 700; 
            margin-bottom: 10px; 
        }

        .region-card { 
            background: white; 
            border-radius: 20px; 
            overflow: hidden; 
            box-shadow: var(--shadow-sm); 
            transition: all 0.4s ease; 
            cursor: pointer; 
            border: 1px solid transparent; 
        }
        .region-card:hover { 
            transform: translateY(-8px); 
            box-shadow: var(--shadow-hover); 
            border-color: var(--primary); 
        }
        .region-header { 
            background: #f9f9f9; 
            padding: 20px; 
            display: flex; 
            align-items: center; 
            gap: 15px; 
            border-bottom: 1px solid #eee; 
        }
        .region-icon { 
            width: 45px; 
            height: 45px; 
            background: var(--primary); 
            border-radius: 12px; 
            color: white; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 20px; 
        }
        .region-body { padding: 25px; }
        .region-stats { 
            display: flex; 
            justify-content: space-between; 
            margin-top: 15px; 
            padding-top: 15px; 
            border-top: 1px dashed #eee; 
        }
        .r-val { 
            font-weight: 800; 
            color: var(--primary); 
            font-size: 18px; 
        }
        .r-lbl { 
            font-size: 11px; 
            color: #999; 
            text-transform: uppercase; 
        }

        .action-buttons { 
            margin-top: 40px; 
            display: flex; 
            gap: 15px; 
        }
        .btn-secondary { 
            padding: 12px 25px; 
            border: 2px solid #ddd; 
            background: white; 
            color: #666; 
            font-weight: 700; 
            border-radius: 8px; 
            cursor: pointer; 
            transition: all 0.3s; 
            display: flex; 
            align-items: center; 
            gap: 8px; 
        }
        .btn-secondary:hover { 
            border-color: var(--dark); 
            color: var(--dark); 
            background: #f9f9f9; 
        }

        /* --- MODAL --- */
        .modal-overlay { 
            position: fixed; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%; 
            background: rgba(0,0,0,0.3); 
            backdrop-filter: blur(8px); 
            z-index: 1000; 
            opacity: 0; 
            visibility: hidden; 
            transition: all 0.4s ease; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
        }
        .modal-overlay.open { 
            opacity: 1; 
            visibility: visible; 
        }
        .modal-content { 
            background: rgba(255,255,255,0.95); 
            width: 500px; 
            padding: 50px; 
            border-radius: 24px; 
            box-shadow: 0 25px 50px rgba(0,0,0,0.25); 
            transform: scale(0.9) translateY(30px); 
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1); 
            position: relative; 
            border: 1px solid rgba(255,255,255,0.5); 
        }
        .modal-overlay.open .modal-content { 
            transform: scale(1) translateY(0); 
        }
        .close-modal { 
            position: absolute; 
            top: 25px; 
            right: 25px; 
            cursor: pointer; 
            font-size: 20px; 
            color: #ccc; 
            transition: 0.3s; 
        }
        .close-modal:hover { 
            color: var(--dark); 
            transform: rotate(90deg); 
        }

        .form-group { margin-bottom: 25px; }
        .form-label { 
            display: block; 
            font-size: 12px; 
            font-weight: 800; 
            text-transform: uppercase; 
            margin-bottom: 10px; 
            color: var(--primary); 
            letter-spacing: 1px; 
        }
        .form-input, .form-select, .form-textarea { 
            width: 100%; 
            padding: 15px; 
            border: 2px solid #f0f0f0; 
            border-radius: 12px; 
            font-family: 'Arial', sans-serif; 
            font-size: 14px; 
            transition: all 0.3s; 
            background: #fbfbfb; 
        }
        .form-input:focus, .form-select:focus, .form-textarea:focus { 
            border-color: var(--primary); 
            background: white; 
            outline: none; 
            box-shadow: 0 5px 15px rgba(45,122,62,0.1); 
        }
        .btn-submit { 
            width: 100%; 
            background: var(--dark); 
            color: white; 
            border: none; 
            padding: 18px; 
            font-weight: 800; 
            border-radius: 12px; 
            cursor: pointer; 
            transition: all 0.3s; 
            margin-top: 10px; 
        }
        .btn-submit:hover { 
            background: var(--primary); 
            transform: translateY(-2px); 
            box-shadow: 0 10px 20px rgba(45,122,62,0.3); 
        }

        /* --- RESPONSIVE --- */
        @media (max-width: 1024px) {
            .container { padding-left: 80px; }
            .grid-2, .grid-3 { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .container { padding-left: 0; }
            .page-header, .tab-content { padding: 20px !important; }
            .header-actions { position: static; margin: 20px auto; text-align: center; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="design-text">Gestión 2025</div>
        <div class="dots dots-1"></div>

        <div class="page-header">
            <div class="ai-badge">PLANIFICACIÓN</div>
            <h1 class="main-title">Modelo de<br>Gestión Integral.</h1>
            <p class="description">Planificación estratégica, seguimiento de objetivos y control operativo en tiempo real para todas las estructuras del proyecto.</p>

            <div class="header-actions">
                <button class="btn-live-action" onclick="openModal()">
                    <i class="fas fa-plus"></i>
                    <span>Nuevo Elemento</span>
                </button>
            </div>
        </div>

        <div class="tabs-container">
            <div class="tabs-nav">
                <button class="tab-btn active" onclick="showTab('objetivos')">Objetivos & Estrategias</button>
                <button class="tab-btn" onclick="showTab('propuesta')">Propuesta de Trabajo</button>
                <button class="tab-btn" onclick="showTab('cronograma')">Cronograma</button>
                <button class="tab-btn" onclick="showTab('regiones')">Regiones</button>
            </div>
        </div>

        <div id="tab-objetivos" class="tab-content active animate-stagger">
            <div class="grid-overview">
                <div class="stat-card"><i class="fas fa-target"></i><span class="number" data-target="8">0</span><span class="label">Objetivos</span></div>
                <div class="stat-card"><i class="fas fa-chess-knight"></i><span class="number" data-target="12">0</span><span class="label">Estrategias</span></div>
                <div class="stat-card"><i class="fas fa-chart-line"></i><span class="number" data-target="85">0</span>%<span class="label">Progreso</span></div>
                <div class="stat-card"><i class="fas fa-users"></i><span class="number" data-target="32">0</span><span class="label">Estados</span></div>
            </div>

            <h2 style="font-size: 22px; font-weight: 800; margin-bottom: 25px;">Objetivos Principales</h2>
            <div class="grid-2" id="objectives-container">
                <div class="info-card"><h3><i class="fas fa-check-circle"></i> Fortalecimiento Estructural</h3><p>Fortalecer la estructura organizativa mediante la capacitación continua de dirigentes estatales y municipales.</p></div>
                <div class="info-card"><h3><i class="fas fa-check-circle"></i> Participación Masiva</h3><p>Incrementar la participación ciudadana en actividades comunitarias y eventos políticos en un 40%.</p></div>
                <div class="info-card"><h3><i class="fas fa-check-circle"></i> Digitalización</h3><p>Implementar sistemas digitales de seguimiento y evaluación para mejorar la eficiencia operativa.</p></div>
                <div class="info-card"><h3><i class="fas fa-check-circle"></i> Alianzas Clave</h3><p>Establecer alianzas estratégicas con organizaciones civiles y gubernamentales para maximizar el impacto.</p></div>
            </div>

            <h2 style="font-size: 22px; font-weight: 800; margin-bottom: 25px; margin-top: 40px;">Estrategias de Implementación</h2>
            <div class="grid-3" id="strategies-container">
                <div class="strategy-card"><div class="strategy-number">01</div><h4 style="font-weight: 800; font-size: 18px; margin-bottom: 10px;">Capacitación Continua</h4><p style="font-size: 14px; color: #666;">Programa mensual de formación para líderes regionales con enfoque en habilidades de gestión.</p></div>
                <div class="strategy-card"><div class="strategy-number">02</div><h4 style="font-weight: 800; font-size: 18px; margin-bottom: 10px;">Comunicación Efectiva</h4><p style="font-size: 14px; color: #666;">Desarrollo de canales digitales y tradicionales para mantener una comunicación fluida.</p></div>
                <div class="strategy-card"><div class="strategy-number">03</div><h4 style="font-weight: 800; font-size: 18px; margin-bottom: 10px;">Evaluación Periódica</h4><p style="font-size: 14px; color: #666;">Sistema de métricas e indicadores para medir el progreso y ajustar estrategias.</p></div>
                <div class="strategy-card"><div class="strategy-number">04</div><h4 style="font-weight: 800; font-size: 18px; margin-bottom: 10px;">Trabajo Territorial</h4><p style="font-size: 14px; color: #666;">Brigadas zonales enfocadas en trabajo comunitario directo y atención personalizada.</p></div>
                <div class="strategy-card"><div class="strategy-number">05</div><h4 style="font-weight: 800; font-size: 18px; margin-bottom: 10px;">Tecnología e Innovación</h4><p style="font-size: 14px; color: #666;">Implementación de herramientas tecnológicas para optimizar procesos administrativos.</p></div>
                <div class="strategy-card"><div class="strategy-number">06</div><h4 style="font-weight: 800; font-size: 18px; margin-bottom: 10px;">Alianzas Estratégicas</h4><p style="font-size: 14px; color: #666;">Construcción de redes colaborativas con sectores clave para amplificar el impacto.</p></div>
            </div>

            <div class="action-buttons">
                <button class="btn-secondary"><i class="fas fa-download"></i> Descargar Plan Estratégico</button>
            </div>
        </div>

        <div id="tab-propuesta" class="tab-content animate-stagger">
            <h2 style="font-size: 28px; font-weight: 800; margin-bottom: 30px;">Propuesta de Trabajo</h2>
            <div class="info-card" style="margin-bottom: 30px; border-left: 5px solid var(--primary);">
                <h3><i class="fas fa-info-circle"></i> Descripción General</h3>
                <p>Nuestra propuesta de trabajo se fundamenta en un modelo de gestión participativo, transparente y orientado a resultados. Buscamos transformar la manera en que se ejecutan las acciones políticas y comunitarias.</p>
            </div>
            <div class="grid-2">
                <div class="info-card"><h3><i class="fas fa-users-cog"></i> Eje Organizativo</h3><ul><li><i class="fas fa-caret-right"></i> Reestructuración de comités estatales</li><li><i class="fas fa-caret-right"></i> Definición clara de roles</li><li><i class="fas fa-caret-right"></i> Creación de equipos especializados</li></ul></div>
                <div class="info-card"><h3><i class="fas fa-graduation-cap"></i> Eje de Capacitación</h3><ul><li><i class="fas fa-caret-right"></i> Talleres mensuales de formación</li><li><i class="fas fa-caret-right"></i> Cursos de liderazgo y gestión</li><li><i class="fas fa-caret-right"></i> Seminarios sobre políticas</li></ul></div>
                <div class="info-card"><h3><i class="fas fa-hands-helping"></i> Eje Social</h3><ul><li><i class="fas fa-caret-right"></i> Jornadas comunitarias semanales</li><li><i class="fas fa-caret-right"></i> Programas de apoyo vulnerable</li><li><i class="fas fa-caret-right"></i> Eventos culturales</li></ul></div>
                <div class="info-card"><h3><i class="fas fa-chart-bar"></i> Eje de Evaluación</h3><ul><li><i class="fas fa-caret-right"></i> Indicadores de desempeño</li><li><i class="fas fa-caret-right"></i> Reportes trimestrales</li><li><i class="fas fa-caret-right"></i> Auditorías internas</li></ul></div>
            </div>
            <div class="action-buttons">
                <button class="btn-secondary"><i class="fas fa-file-pdf"></i> Exportar Propuesta</button>
            </div>
        </div>

        <div id="tab-cronograma" class="tab-content animate-stagger">
            <h2 style="font-size: 28px; font-weight: 800; margin-bottom: 30px;">Cronograma de Actividades</h2>
            <div class="timeline-container" id="timeline-container">
                <div class="timeline-line"></div>
                <div class="timeline-item"><div class="timeline-dot"></div><div class="timeline-content"><span class="t-date">ENE 2025</span><h4 style="font-weight: 800; font-size: 18px; margin-bottom: 8px;">Fase de Diagnóstico</h4><p style="font-size: 14px; color: #666;">Evaluación inicial de la estructura organizativa actual e identificación de fortalezas por estado.</p></div></div>
                <div class="timeline-item"><div class="timeline-dot"></div><div class="timeline-content"><span class="t-date">FEB 2025</span><h4 style="font-weight: 800; font-size: 18px; margin-bottom: 8px;">Capacitación Inicial</h4><p style="font-size: 14px; color: #666;">Inicio del programa de formación para dirigentes estatales y municipales. Talleres intensivos.</p></div></div>
                <div class="timeline-item"><div class="timeline-dot"></div><div class="timeline-content"><span class="t-date">MAR - MAY 2025</span><h4 style="font-weight: 800; font-size: 18px; margin-bottom: 8px;">Implementación de Estrategias</h4><p style="font-size: 14px; color: #666;">Puesta en marcha de las estrategias definidas en cada región y activación de brigadas.</p></div></div>
                <div class="timeline-item"><div class="timeline-dot"></div><div class="timeline-content"><span class="t-date">JUN 2025</span><h4 style="font-weight: 800; font-size: 18px; margin-bottom: 8px;">Primera Evaluación</h4><p style="font-size: 14px; color: #666;">Evaluación trimestral de resultados, análisis de indicadores y ajuste de estrategias.</p></div></div>
                <div class="timeline-item"><div class="timeline-dot"></div><div class="timeline-content"><span class="t-date">OCT - DIC 2025</span><h4 style="font-weight: 800; font-size: 18px; margin-bottom: 8px;">Cierre y Proyección</h4><p style="font-size: 14px; color: #666;">Evaluación anual integral, informes finales y planificación del siguiente ciclo.</p></div></div>
            </div>
            <div class="action-buttons">
                <button class="btn-secondary" onclick="window.print()"><i class="fas fa-print"></i> Imprimir Cronograma</button>
            </div>
        </div>

        <div id="tab-regiones" class="tab-content animate-stagger">
            <h2 style="font-size: 28px; font-weight: 800; margin-bottom: 30px;">Gestión por Regiones</h2>
            <div class="grid-3">
                <div class="region-card"><div class="region-header"><div class="region-icon"><i class="fas fa-mountain"></i></div><div><h4 style="font-weight: 800; margin-bottom: 2px;">Región Norte</h4><span style="font-size: 12px; color: #666;">8 Estados</span></div></div><div class="region-body"><p style="font-size: 13px; color: #666; margin-bottom: 15px;">Chihuahua, Sonora, Coahuila, Nuevo León, Durango, Sinaloa, Baja California, BCS.</p><div class="region-stats"><div class="r-stat"><span class="r-val">42</span><span class="r-lbl">Coordinadores</span></div><div class="r-stat"><span class="r-val">580</span><span class="r-lbl">Brigadistas</span></div></div></div></div>
                <div class="region-card"><div class="region-header"><div class="region-icon"><i class="fas fa-city"></i></div><div><h4 style="font-weight: 800; margin-bottom: 2px;">Centro-Occidente</h4><span style="font-size: 12px; color: #666;">7 Estados</span></div></div><div class="region-body"><p style="font-size: 13px; color: #666; margin-bottom: 15px;">Jalisco, Guanajuato, Aguascalientes, Zacatecas, Colima, Nayarit, Michoacán.</p><div class="region-stats"><div class="r-stat"><span class="r-val">38</span><span class="r-lbl">Coordinadores</span></div><div class="r-stat"><span class="r-val">520</span><span class="r-lbl">Brigadistas</span></div></div></div></div>
                <div class="region-card"><div class="region-header"><div class="region-icon"><i class="fas fa-landmark"></i></div><div><h4 style="font-weight: 800; margin-bottom: 2px;">Región Centro</h4><span style="font-size: 12px; color: #666;">6 Estados</span></div></div><div class="region-body"><p style="font-size: 13px; color: #666; margin-bottom: 15px;">CDMX, Edomex, Morelos, Hidalgo, Tlaxcala, Puebla.</p><div class="region-stats"><div class="r-stat"><span class="r-val">48</span><span class="r-lbl">Coordinadores</span></div><div class="r-stat"><span class="r-val">680</span><span class="r-lbl">Brigadistas</span></div></div></div></div>
                <div class="region-card"><div class="region-header"><div class="region-icon"><i class="fas fa-tree"></i></div><div><h4 style="font-weight: 800; margin-bottom: 2px;">Región Sur</h4><span style="font-size: 12px; color: #666;">6 Estados</span></div></div><div class="region-body"><p style="font-size: 13px; color: #666; margin-bottom: 15px;">Oaxaca, Chiapas, Guerrero, Veracruz, Tabasco, Campeche.</p><div class="region-stats"><div class="r-stat"><span class="r-val">32</span><span class="r-lbl">Coordinadores</span></div><div class="r-stat"><span class="r-val">420</span><span class="r-lbl">Brigadistas</span></div></div></div></div>
                <div class="region-card"><div class="region-header"><div class="region-icon"><i class="fas fa-umbrella-beach"></i></div><div><h4 style="font-weight: 800; margin-bottom: 2px;">Región Sureste</h4><span style="font-size: 12px; color: #666;">5 Estados</span></div></div><div class="region-body"><p style="font-size: 13px; color: #666; margin-bottom: 15px;">Quintana Roo, Yucatán, Campeche, SLP, Querétaro.</p><div class="region-stats"><div class="r-stat"><span class="r-val">28</span><span class="r-lbl">Coordinadores</span></div><div class="r-stat"><span class="r-val">380</span><span class="r-lbl">Brigadistas</span></div></div></div></div>
            </div>
            <div class="grid-2">
                <div class="info-card"><h3><i class="fas fa-users-cog"></i> Estructura de Coordinación</h3><ul><li><i class="fas fa-caret-right"></i> Coordinador Regional Principal</li><li><i class="fas fa-caret-right"></i> Coordinadores Estatales por región</li><li><i class="fas fa-caret-right"></i> Coordinadores Municipales</li></ul></div>
                <div class="info-card"><h3><i class="fas fa-handshake"></i> Reuniones de Coordinación</h3><ul><li><i class="fas fa-caret-right"></i> Reunión mensual de coordinadores</li><li><i class="fas fa-caret-right"></i> Videoconferencias semanales</li><li><i class="fas fa-caret-right"></i> Encuentros trimestrales presenciales</li></ul></div>
            </div>
            <div class="action-buttons">
                <button class="btn-secondary"><i class="fas fa-map-marked"></i> Ver Mapa Interactivo</button>
            </div>
        </div>

        <div class="modal-overlay" id="gestionModal">
            <div class="modal-content">
                <div class="close-modal" onclick="closeModal()"><i class="fas fa-times"></i></div>
                <h2 style="font-size: 24px; font-weight: 800; margin-bottom: 5px; color: #1a1a1a;">Crear Elemento</h2>
                <p style="font-size: 14px; color: #666; margin-bottom: 25px;">Agrega nuevos ítems al plan estratégico en tiempo real.</p>
                <form id="createForm" onsubmit="handleCreate(event)">
                    <div class="form-group">
                        <label class="form-label">Tipo de Elemento</label>
                        <select class="form-select" id="itemType" onchange="toggleFields()">
                            <option value="objetivo">Objetivo Estratégico</option>
                            <option value="estrategia">Nueva Estrategia</option>
                            <option value="cronograma">Evento de Cronograma</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Título</label>
                        <input type="text" class="form-input" id="itemTitle" required placeholder="Escribe un título claro...">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Descripción</label>
                        <textarea class="form-textarea" id="itemDesc" rows="3" required placeholder="Detalles operativos..."></textarea>
                    </div>
                    <div class="form-group" id="dateField" style="display: none;">
                        <label class="form-label">Fecha Estimada</label>
                        <input type="text" class="form-input" id="itemDate" placeholder="Ej: NOV 2025">
                    </div>
                    <button type="submit" class="btn-submit"><i class="fas fa-save"></i> Guardar Elemento</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // TABS
        function showTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.getElementById('tab-' + tabName).classList.add('active');
            
            // Encuentra y activa el botón de la pestaña
            document.querySelectorAll('.tabs-nav .tab-btn').forEach(btn => {
                if(btn.getAttribute('onclick').includes("'" + tabName + "'")) {
                    btn.classList.add('active');
                }
            });

            // Re-ejecutar animación stagger
            const content = document.getElementById('tab-' + tabName);
            content.classList.remove('animate-stagger');
            void content.offsetWidth; // Truco para forzar el reflow
            content.classList.add('animate-stagger');
        }

        // MODAL
        const modal = document.getElementById('gestionModal');
        const form = document.getElementById('createForm');

        function openModal() {
            modal.classList.add('open');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            modal.classList.remove('open');
            document.body.style.overflow = '';
            setTimeout(() => form.reset(), 300);
            toggleFields(); // Reset field visibility
        }

        modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

        function toggleFields() {
            document.getElementById('dateField').style.display = 
                document.getElementById('itemType').value === 'cronograma' ? 'block' : 'none';
        }

        // CREAR ELEMENTO
        function handleCreate(e) {
            e.preventDefault();
            const type = document.getElementById('itemType').value;
            const title = document.getElementById('itemTitle').value;
            const desc = document.getElementById('itemDesc').value;

            const btn = form.querySelector('.btn-submit');
            const original = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
            btn.disabled = true;

            setTimeout(() => {
                let tabToShow = '';
                if (type === 'objetivo') {
                    injectObjective(title, desc);
                    tabToShow = 'objetivos';
                    updateStatCard('Objetivos', 1);
                } else if (type === 'estrategia') {
                    injectStrategy(title, desc);
                    tabToShow = 'objetivos';
                    updateStatCard('Estrategias', 1);
                } else if (type === 'cronograma') {
                    const date = document.getElementById('itemDate').value || 'FUTURO';
                    injectTimeline(title, desc, date);
                    tabToShow = 'cronograma';
                }

                btn.innerHTML = original;
                btn.disabled = false;
                closeModal();
                showTab(tabToShow);

                const toast = document.createElement('div');
                toast.style.cssText = 'position:fixed; bottom:30px; right:30px; background:var(--primary); color:white; padding:15px 30px; border-radius:50px; box-shadow:0 10px 20px rgba(0,0,0,0.2); z-index:2000; font-weight:700; animation: slideInUp 0.5s ease forwards;';
                toast.innerHTML = '<i class="fas fa-check-circle"></i> Elemento creado con éxito';
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 3000);
            }, 800);
        }
        
        // Función auxiliar para actualizar contadores
        function updateStatCard(label, increment) {
            const card = document.querySelector(`.stat-card .label:contains("${label}")`);
            if (card) {
                const numberSpan = card.previousElementSibling;
                let currentNum = parseInt(numberSpan.getAttribute('data-target'));
                if (!isNaN(currentNum)) {
                    const newNum = currentNum + increment;
                    numberSpan.setAttribute('data-target', newNum);
                    animateNumber(numberSpan, newNum); // Re-animar el número con el nuevo target
                }
            }
        }


        function injectObjective(title, desc) {
            const container = document.getElementById('objectives-container');
            // Usar clases CSS para los estilos en lugar de estilos en línea con variables JS desconocidas
            const html = `<div class="info-card new-item">
                <h3><i class="fas fa-check-circle" style="color:var(--primary);"></i> ${title} <span class="new-badge">NUEVO</span></h3>
                <p>${desc}</p></div>`;
            container.insertAdjacentHTML('afterbegin', html);
        }

        function injectStrategy(title, desc) {
            const container = document.getElementById('strategies-container');
            const totalStrategies = container.children.length + 1;
            
            // Se inyecta al final para mantener la numeración (o al inicio, pero hay que calcular el número correctamente)
            // Por simplicidad y para destacar, lo inyectamos al inicio, y el número lo ponemos como 'NEW'
            const html = `<div class="strategy-card new-item" style="animation: slideInUp 0.5s ease forwards;">
                <div class="strategy-number" style="color:var(--primary); opacity:0.2;">NEW</div>
                <h4 style="font-weight: 800; font-size: 18px; margin-bottom: 10px;">${title}</h4>
                <p style="font-size: 14px; color: #666;">${desc}</p></div>`;
            container.insertAdjacentHTML('afterbegin', html);
            
            // NOTA: Para mantener la numeración secuencial (01, 02...), se requeriría re-renderizar
            // todo el contenedor o re-asignar los strategy-number de los elementos existentes.
            // Para la corrección inmediata, lo dejamos como 'NEW'.
        }

        function injectTimeline(title, desc, date) {
            const container = document.getElementById('timeline-container');
            const line = container.querySelector('.timeline-line');
            const html = `<div class="timeline-item new-item" style="animation: slideInUp 0.5s ease forwards;">
                <div class="timeline-dot"></div>
                <div class="timeline-content new-item">
                    <span class="t-date">${date}</span>
                    <h4 style="font-weight: 800; font-size: 18px; margin-bottom: 8px;">${title}</h4>
                    <p style="font-size: 14px; color: #666;">${desc}</p>
                </div>
            </div>`;
            // Se inserta después de la línea (al inicio de la lista de items)
            line.insertAdjacentHTML('afterend', html);
        }

        // Animación de números
        function animateNumber(el, target) {
            let current = 0;
            const isPercentage = el.innerText.includes('%');
            const inc = target / 50;
            const timer = setInterval(() => {
                current += inc;
                if (current >= target) {
                    el.innerText = target + (isPercentage ? '%' : '');
                    clearInterval(timer);
                } else {
                    el.innerText = Math.ceil(current) + (isPercentage ? '%' : '');
                }
            }, 20);
        }

        window.addEventListener('load', () => {
            document.querySelectorAll('.number').forEach(el => {
                const target = parseInt(el.getAttribute('data-target'));
                animateNumber(el, target);
            });
        });
        
        // Polyfill simple para :contains (necesario para updateStatCard en algunos navegadores)
        // Aunque la solución más limpia es usar data-attributes o IDs en los elementos.
        // Lo dejamos así para respetar la estructura HTML original, asumiendo navegadores modernos.
        if (!Element.prototype.matches) {
            Element.prototype.matches = Element.prototype.msMatchesSelector || Element.prototype.webkitMatchesSelector;
        }
        if (!Element.prototype.closest) {
            Element.prototype.closest = function(s) {
                var el = this;
                do {
                    if (el.matches(s)) return el;
                    el = el.parentElement || el.parentNode;
                } while (el !== null && el.nodeType === 1);
                return null;
            };
        }
        
        // Agregar soporte para `:contains` simple para propósitos de demostración/ejemplo.
        // En un entorno de producción, se debe usar data-attributes o IDs.
        jQuery.expr.pseudos.contains = jQuery.expr.createPseudo(function(text) {
            return function(elem) {
                return jQuery(elem).text().toUpperCase().indexOf(text.toUpperCase()) >= 0;
            };
        });


    </script>
</body>
</html>