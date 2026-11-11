<?php
// ==========================================
// 1. CONFIGURACIÓN Y SEGURIDAD
// ==========================================
if (file_exists(__DIR__ . '/../../includes/bootstrap.php')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
} else {
    session_start();
}

$rol = $_SESSION['user_role'] ?? '';
$estado_sesion = $_SESSION['user_estado'] ?? 'Estatal'; 

// Solo permitimos al coordinador estatal
$permitidos = ['coordinador_estatal'];

if (!in_array($rol, $permitidos)) {
    header('Location: /public/index.php'); 
    exit();
}

// ==========================================
// 2. OBTENER DATOS (SQL GLOBAL CORREGIDO)
// ==========================================
$actividades = [];
try {
    $dbConfigPath = __DIR__ . '/../../includes/database.php';
    if (file_exists($dbConfigPath)) {
        $dbConfig = require $dbConfigPath;
    } else {
        $dbConfig = [
            'host' => 'localhost', 'database' => 'u881824383_pverde', 'username' => 'u881824383_proyverde',
            'password' => 'PProyectoverde8#', 'charset' => 'utf8mb4',
            'options' => [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        ];
    }

    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options'] ?? []);

    // --- CAMBIO CLAVE: CONSULTA GLOBAL ---
    // Eliminamos el INNER JOIN con dirigentes_actividades y el filtro de estado.
    // Ahora vemos TODO el banco, pero respetamos los nombres de los autores.
    
    $sql = "SELECT 
                ba.*,
                
                -- Datos del Admin (Solo si el rol en banco coincide)
                adm.nombre_completo AS nombre_admin,
                
                -- Datos del Coordinador (Solo si el rol en banco coincide)
                coord.nombre AS nombre_coord,
                coord.ap_paterno AS apellido_coord
            FROM 
                banco_actividades ba
            
            -- JOIN para obtener nombre si fue creado por ADMIN
            LEFT JOIN 
                administradores adm ON ba.creado_por_id = adm.id 
                AND (ba.creado_por_rol LIKE '%admin%' OR ba.creado_por_rol = 'superadmin')
            
            -- JOIN para obtener nombre si fue creado por COORDINADOR
            LEFT JOIN 
                coordinador_estatal coord ON ba.creado_por_id = coord.id 
                AND (ba.creado_por_rol = 'coordinador_estatal')
            
            WHERE 
                ba.activo = 1
            ORDER BY 
                ba.fecha_creacion DESC";
                
    $stmt = $pdo->prepare($sql);
    $stmt->execute(); // Ya no necesitamos pasar :estado
    $actividades = $stmt->fetchAll();

} catch (PDOException $e) {
    $error_db = "Error al cargar el banco: " . $e->getMessage();
}

$status = $_GET['status'] ?? '';
$message = $_GET['message'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Banco de Actividades - <?php echo htmlspecialchars($estado_sesion); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    
   <style>
    /* --- ESTILOS BASE --- */
    :root { --primary: #2d7a3e; --primary-dark: #1f5a2d; --dark: #1a1a1a; --light: #f0f7f0; --white: #ffffff; --gray-100: #f8f9fa; --gray-200: #e9ecef; --text-muted: #6c757d; --shadow-sm: 0 10px 30px rgba(0,0,0,0.05); --impact-high: #d32f2f; --impact-medium: #ffc107; --impact-low: #198754; --agenda-mediatica: #e91e63; --agenda-tematica: #9c27b0; --agenda-politica: #2196f3; --agenda-social: #4caf50; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Arial', sans-serif; background: var(--light); color: var(--dark); overflow-x: hidden; }
    
    /* --- LAYOUT CORREGIDO (IGUAL AL DASHBOARD) --- */
    .container { 
        max-width: 100%; 
        margin: 0; 
        background: var(--white); 
        min-height: 100vh; 
        position: relative; 
        /* Ajustado a 330px para igualar el Dashboard */
        padding-left: 330px; 
        padding-right: 40px; 
        padding-bottom: 40px; 
        transition: padding-left 0.3s ease; 
    }

    /* Texto vertical decorativo ajustado */
    .design-text { 
        position: fixed; 
        left: 290px; 
        top: 50%; 
        writing-mode: vertical-rl; 
        font-size: 13px; 
        font-weight: 500; 
        color: var(--primary); 
        opacity: 0.3; 
        letter-spacing: 2px; 
        z-index: 0; 
        pointer-events: none;
        text-transform: uppercase;
    }
    
    /* Header */
    .page-header { 
        padding: 50px 0 40px 0; 
        position: relative; 
        z-index: 2; 
        display: flex; 
        justify-content: space-between; 
        align-items: flex-end; 
        flex-wrap: wrap;
        gap: 20px;
    }

    .ai-badge { color: var(--primary); font-size: 13px; font-weight: 800; letter-spacing: 4px; margin-bottom: 15px; display: inline-block; text-transform: uppercase; background: rgba(45,122,62,0.1); padding: 5px 15px; border-radius: 20px; }
    .main-title { font-size: 54px; font-weight: 900; line-height: 1; margin-bottom: 15px; color: var(--dark); }
    .description { color: #666; font-size: 16px; line-height: 1.6; max-width: 600px; }
    
    /* Botones */
    .btn-primary-action { background: var(--primary); color: white; border: none; padding: 12px 25px; border-radius: 12px; font-weight: 700; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; gap: 10px; font-size: 14px; box-shadow: 0 4px 15px rgba(45, 122, 62, 0.3); }
    .btn-primary-action:hover { background: var(--primary-dark); transform: translateY(-2px); }
    .btn-secondary-action { background: var(--white); color: var(--text-muted); border: 1px solid var(--gray-200); padding: 12px 20px; border-radius: 12px; font-weight: 600; cursor: pointer; transition: 0.3s; display: flex; align-items: center; gap: 8px; font-size: 13px; }
    .btn-secondary-action:hover { border-color: var(--dark); color: var(--dark); }

    /* Filtros */
    .filters-bar { background: var(--white); border: 1px solid var(--gray-200); border-radius: 16px; padding: 20px; margin-bottom: 30px; display: flex; gap: 20px; align-items: flex-end; box-shadow: var(--shadow-sm); z-index: 5; position: relative; }
    .form-group { display: flex; flex-direction: column; gap: 8px; flex: 1; }
    .form-label { font-size: 12px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.5px; }
    .form-input-styled { width: 100%; padding: 12px 15px; border-radius: 10px; border: 2px solid var(--gray-200); background: var(--gray-100); font-size: 14px; font-weight: 600; color: var(--dark); transition: all 0.3s; outline: none; }
    .form-input-styled:focus { border-color: var(--primary); background: var(--white); }
    
    /* Tabla */
    .table-section { background: var(--white); border-radius: 20px; padding: 30px; border: 1px solid var(--gray-200); box-shadow: var(--shadow-sm); }
    .section-header { margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; }
    .section-title { font-size: 20px; font-weight: 800; color: var(--dark); }
    .modern-table { width: 100%; border-collapse: separate; border-spacing: 0; }
    .modern-table th { text-align: left; padding: 15px 20px; color: var(--text-muted); font-size: 12px; text-transform: uppercase; letter-spacing: 1px; border-bottom: 2px solid var(--gray-200); }
    .modern-table td { padding: 20px; border-bottom: 1px solid var(--gray-200); font-size: 14px; color: var(--dark); font-weight: 500; vertical-align: middle; }
    .modern-table tr:hover td { background: var(--gray-100); }
    .modern-table tr:last-child td { border-bottom: none; }
    
    .activity-title-link { color: var(--dark); cursor: pointer; transition: color 0.2s ease; position: relative; display: inline-block; }
    .activity-title-link:hover { color: var(--primary); text-decoration: underline; }
    
    /* Badges */
    .badge { padding: 6px 12px; border-radius: 30px; font-size: 11px; font-weight: 700; text-transform: uppercase; display: inline-block; }
    .badge-mediatica { background: rgba(233, 30, 99, 0.1); color: var(--agenda-mediatica); } 
    .badge-tematica { background: rgba(156, 39, 176, 0.1); color: var(--agenda-tematica); } 
    .badge-politica { background: rgba(33, 150, 243, 0.1); color: var(--agenda-politica); }  
    .badge-social { background: rgba(76, 175, 80, 0.1); color: var(--agenda-social); }
    .badge-mine { background: #e8f5e9; color: var(--primary); border: 1px solid var(--primary); }
    
    .impact-high { color: var(--impact-high); font-weight: 800; } 
    .impact-medium { color: var(--impact-medium); font-weight: 800; } 
    .impact-low { color: var(--impact-low); font-weight: 800; } 

    /* Badge Origen */
    .badge-origen {
        padding: 4px 10px; 
        border-radius: 12px; 
        font-size: 11px; 
        font-weight: 700; 
        display: inline-flex; 
        align-items: center; 
        gap: 5px;
        white-space: nowrap;
    }
    .badge-origen.admin { 
        background-color: #e3f2fd; 
        color: #1565c0; 
        border: 1px solid #bbdefb; 
    }
    .badge-origen.user { 
        background-color: #f0f7f0; 
        color: #2d7a3e; 
        border: 1px solid #c8e6c9; 
    }
    
    /* Acciones */
    .action-cell { text-align: right; position: relative; }
    .action-btn { background: none; border: none; cursor: pointer; color: var(--text-muted); font-size: 16px; padding: 8px 12px; border-radius: 50%; transition: all 0.2s; }
    .action-btn:hover, .action-btn.active { background: var(--gray-200); color: var(--primary); }
    
    .dropdown-menu { position: absolute; right: 20px; top: 50px; width: 160px; background: var(--white); border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); border: 1px solid var(--gray-200); z-index: 100; padding: 8px 0; opacity: 0; visibility: hidden; transform: translateY(10px); transition: all 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
    .dropdown-menu.show { opacity: 1; visibility: visible; transform: translateY(0); }
    .dropdown-item { display: flex; align-items: center; gap: 10px; padding: 10px 20px; width: 100%; border: none; background: none; text-align: left; cursor: pointer; font-size: 13px; font-weight: 600; color: var(--dark); transition: background 0.2s; }
    .dropdown-item:hover { background: var(--gray-100); color: var(--primary); }
    .dropdown-item i { width: 16px; text-align: center; }

    /* Modal */
    .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(5px); z-index: 2000; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.3s ease; }
    .modal.open { display: flex; opacity: 1; }
    .modal-content { background: var(--white); border-radius: 20px; border: 1px solid var(--gray-200); box-shadow: 0 25px 50px rgba(0,0,0,0.1); width: 90%; max-width: 850px; padding: 40px; position: relative; transform: translateY(30px); opacity: 0; transition: all 0.4s ease-out; max-height: 90vh; overflow-y: auto; }
    .modal.open .modal-content { transform: translateY(0); opacity: 1; }
    .close-modal { position: absolute; top: 25px; right: 25px; font-size: 22px; cursor: pointer; color: #ccc; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: 50%; transition: all 0.3s; }
    .close-modal:hover { color: var(--primary); background: #f0f7f0; transform: rotate(90deg) scale(1.1); }

    .full-desc-box { background: var(--gray-100); padding: 20px; border-radius: 12px; margin-top: 20px; font-size: 14px; line-height: 1.6; color: #444; border-left: 4px solid var(--primary); }
    .detail-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 25px; padding-top: 25px; border-top: 1px solid var(--gray-200); }
    .detail-item strong { display: block; font-size: 11px; color: var(--text-muted); text-transform: uppercase; margin-bottom: 5px; }
    .detail-item span { font-size: 15px; color: var(--dark); font-weight: 600; }

    /* File Upload */
    .file-upload-area { border: 2px dashed var(--gray-200); border-radius: 12px; padding: 20px; text-align: center; cursor: pointer; transition: all 0.3s; background: var(--gray-100); }
    .file-upload-area:hover { border-color: var(--primary); background: var(--light); }
    .file-info { display: flex; align-items: center; gap: 10px; background: var(--white); border: 1px solid var(--gray-200); padding: 10px 15px; border-radius: 8px; margin-top: 10px; }
    .file-download-link { display: inline-flex; align-items: center; gap: 8px; padding: 8px 15px; background: var(--gray-100); border-radius: 8px; color: var(--primary); font-weight: 600; font-size: 13px; text-decoration: none; transition: all 0.3s; }
    .file-download-link:hover { background: var(--primary); color: white; }

    /* Quill */
    .ql-toolbar.ql-snow { border-top-left-radius: 10px; border-top-right-radius: 10px; border-color: var(--gray-200); }
    .ql-container.ql-snow { border-bottom-left-radius: 10px; border-bottom-right-radius: 10px; border-color: var(--gray-200); font-family: 'Arial', sans-serif; font-size: 14px; }
    .ql-editor { min-height: 120px; }

    /* Pagination & Animation */
    .pagination-container { display: flex; justify-content: space-between; align-items: center; padding-top: 20px; margin-top: 20px; border-top: 1px solid var(--gray-200); }
    .pagination-info { font-size: 13px; color: var(--text-muted); font-weight: 500; }
    .pagination-controls { display: flex; gap: 5px; }
    .page-btn { background: var(--white); border: 1px solid var(--gray-200); color: var(--dark); width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; font-size: 13px; font-weight: 600; }
    .page-btn:hover:not(:disabled) { border-color: var(--primary); color: var(--primary); }
    .page-btn.active { background: var(--primary); color: var(--white); border-color: var(--primary); }
    .page-btn:disabled { background: var(--gray-100); color: #ccc; cursor: not-allowed; }
    
    .animate-up { animation: fadeInUp 0.6s ease forwards; opacity: 0; }
    @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    .animate-delay-1 { animation-delay: 0.1s; }
    .animate-delay-2 { animation-delay: 0.2s; }

    /* --- RESPONSIVE CORREGIDO --- */
    @media (max-width: 1200px) {
        .container { 
            /* Se colapsa el sidebar */
            padding-left: 40px; 
        }
        .design-text { display: none; }
        .stats-grid { grid-template-columns: 1fr; }
        .charts-section { grid-template-columns: 1fr; }
        .municipios-grid { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 768px) {
        .main-title { font-size: 36px; }
        .page-header { flex-direction: column; align-items: flex-start; gap: 20px; }
        .filters-bar { flex-direction: column; align-items: stretch; }
        .detail-grid { grid-template-columns: 1fr; }
        .container { padding-left: 20px; padding-right: 20px; }
    }
</style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/components/sidebar_ce.php'; ?>
    
    <div class="design-text">BANCO <?php echo strtoupper($estado_sesion); ?></div>

    <div class="container">
        
        <div class="page-header animate-up">
            <div>
                <div class="ai-badge"><?php echo strtoupper($estado_sesion); ?></div>
                <h1 class="main-title">Banco de<br>Actividades.</h1>
                <p class="description">Catálogo global de actividades estratégicas. Puedes proponer nuevas acciones que se sumarán al banco.</p>
            </div>
            <div>
                <button class="btn-primary-action" onclick="openCreateModal()">
                    <i class="fas fa-plus-circle"></i> Proponer Actividad
                </button>
            </div>
        </div>

        <?php if (!empty($status)): ?>
            <div style="background: <?php echo $status == 'success' ? '#d4edda' : ($status == 'warning' ? '#fff3cd' : '#f8d7da'); ?>; 
                        color: <?php echo $status == 'success' ? '#155724' : ($status == 'warning' ? '#856404' : '#721c24'); ?>; 
                        padding: 15px; border-radius: 10px; margin-bottom: 20px; border: 1px solid rgba(0,0,0,0.1);">
                <i class="fas <?php echo $status == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i> 
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if(isset($error_db)): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
                <i class="fas fa-exclamation-triangle"></i> <?= $error_db ?>
            </div>
        <?php endif; ?>

        <div class="filters-bar animate-up animate-delay-1">
            <div class="form-group" style="flex: 2;">
                <label class="form-label">Buscar Actividad</label>
                <div style="position: relative;">
                    <i class="fas fa-search" style="position: absolute; left: 15px; top: 14px; color: #999;"></i>
                    <input type="text" id="searchInput" class="form-input-styled" style="padding-left: 40px;" placeholder="Buscar por nombre o descripción...">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Filtrar por Agenda</label>
                <select id="agendaFilter" class="form-input-styled">
                    <option value="all">Todas</option>
                    <option value="mediatica">Mediática</option>
                    <option value="tematica">Temática</option>
                    <option value="politica">Política</option>
                    <option value="social">Social</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Filtrar por Impacto</label>
                <select id="levelFilter" class="form-input-styled">
                    <option value="all">Todos</option>
                    <option value="alto">Alto</option>
                    <option value="medio">Medio</option>
                    <option value="bajo">Bajo</option>
                </select>
            </div>
        </div>

        <div class="table-section animate-up animate-delay-2">
            <div class="section-header">
                <h3 class="section-title">Catálogo Global</h3>
            </div>
            
            <div style="overflow-x: auto; padding-bottom: 10px;">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Origen</th> <th>Nombre</th>
                            <th width="25%">Descripción</th>
                            <th>Impacto</th>
                            <th>Agenda</th>
                            <th>Alcance</th>
                            <th>Documento</th>
                            <th style="text-align: right;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="activities-table-body">
                        
                        <?php if (empty($actividades)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 40px; color: #999;">
                                    <i class="fas fa-folder-open" style="font-size: 40px; margin-bottom: 10px; display: block;"></i>
                                    No hay actividades registradas en el banco.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($actividades as $act): 
                                $desc_clean = strip_tags(html_entity_decode($act['descripcion']));
                                if(strlen($desc_clean) > 40) {
                                    $desc_short = substr($desc_clean, 0, 40) . '...';
                                } else {
                                    $desc_short = $desc_clean;
                                }
                                                                                
                                $nivel_class = '';
                                if($act['nivel_impacto'] === 'alto') $nivel_class = 'impact-high';
                                elseif($act['nivel_impacto'] === 'medio') $nivel_class = 'impact-medium';
                                else $nivel_class = 'impact-low';
                            
                                $costo_fmt = '$ ' . number_format($act['costo'], 2);
                                $agenda = !empty($act['agenda']) ? $act['agenda'] : 'social';
                                $documento = $act['documento_adjunto'] ?? '';
                                
                                $creador_mostrar = 'Sistema'; // Valor por defecto
                                $es_admin = false;
                            
                                // 1. Verificamos si vino información de la tabla de administradores
                                if (!empty($act['nombre_admin'])) {
                                    $creador_mostrar = $act['nombre_admin'];
                                    $es_admin = true; // Esto activará el badge azul
                                } 
                                // 2. Si no es admin, verificamos si vino información de coordinadores
                                elseif (!empty($act['nombre_coord'])) {
                                    // Concatenamos Nombre + Apellido Paterno
                                    $creador_mostrar = $act['nombre_coord'] . ' ' . ($act['apellido_coord'] ?? '');
                                    $es_admin = false; // Esto activará el badge verde
                                }
                            
                                // --- GENERACIÓN DEL BADGE HTML ---
                                $origenHtml = '';
                            
                                if ($es_admin) {
                                    // Estilo para Administradores (Azul / Escudo)
                                    $origenHtml = '<span class="badge-origen admin">
                                                        <i class="fas fa-user-shield"></i> ' . htmlspecialchars($creador_mostrar) . '
                                                   </span>';
                                } else {
                                    // Estilo para Coordinadores/Usuarios (Verde / Usuario)
                                    $origenHtml = '<span class="badge-origen user">
                                                        <i class="fas fa-user"></i> ' . htmlspecialchars($creador_mostrar) . '
                                                   </span>';
                                }
                            ?>
                            <tr data-agenda="<?= htmlspecialchars($agenda) ?>" 
                                data-level="<?= htmlspecialchars($act['nivel_impacto']) ?>" 
                                data-title="<?= htmlspecialchars($act['titulo']) ?>" 
                                data-desc="<?= htmlspecialchars($act['descripcion']) ?>"
                                data-impact-value="<?= htmlspecialchars($act['impacto_personas']) ?>" 
                                data-cost="<?= $costo_fmt ?>"
                                data-id="<?= $act['codigo_referencia'] ?>"
                                data-db-id="<?= $act['id'] ?? '' ?>"
                                data-documento="<?= htmlspecialchars($documento) ?>">
                                
                                <td><?= $origenHtml ?></td>

                                <td style="font-weight: 600;">
                                    <span class="activity-title-link" onclick="viewDetails(this)">
                                        <?= htmlspecialchars($act['titulo']) ?>
                                    </span>
                                </td>
                                <td style="color: #666; font-size: 13px;"><?= $desc_short ?></td>
                                
                                <td>
                                    <span class="<?= $nivel_class ?>"><?= ucfirst($act['nivel_impacto']) ?></span>
                                </td>
                                
                                <td>
                                    <span class="badge badge-<?= htmlspecialchars($agenda) ?>">
                                        <?= ucfirst($agenda) ?>
                                    </span>
                                </td>
                                
                                <td><?= htmlspecialchars($act['impacto_personas']) ?></td>
                                
                                <td>
                                    <?php if (!empty($documento)): ?>
                                        <a href="../../api/banco_de_actividades/documentos/<?= htmlspecialchars($documento) ?>" 
                                           target="_blank" 
                                           class="file-download-link">
                                            <i class="fas fa-file-download"></i> Descargar
                                        </a>
                                    <?php else: ?>
                                        <span style="color: #999; font-size: 12px;">Sin archivo</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="action-cell">
                                    <button class="action-btn" onclick="toggleDropdown(this)"><i class="fas fa-ellipsis-v"></i></button>
                                    <div class="dropdown-menu">
                                        <button class="dropdown-item" onclick="handleMenuAction(this, 'view')"><i class="fas fa-eye"></i> Ver detalle</button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>

                    </tbody>
                </table>
            </div>

            <div class="pagination-container" id="paginationContainer">
                <div class="pagination-info" id="paginationInfo">Mostrando 0 - 0 de 0 resultados</div>
                <div class="pagination-controls" id="paginationControls">
                </div>
            </div>

        </div>
    </div>
    
    
    <div class="modal" id="taskModal">
        <div class="modal-content">
            <div class="close-modal" onclick="closeCreateModal()"><i class="fas fa-times"></i></div>
            
            <h2 id="modalTitle" style="font-size: 24px; font-weight: 800; margin-bottom: 5px; color: var(--dark);">Proponer Nueva Actividad</h2>
            <p style="font-size: 14px; color: #999; margin-bottom: 30px;">Esta actividad se añadirá al banco global y podrá ser utilizada por otros estados.</p>
            
            <form id="taskForm" action="/api/banco_de_actividades/procesar.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" id="actionType" name="action" value="create">
                <input type="hidden" id="dbId" name="db_id" value=""> 
                <input type="hidden" id="documentoActual" name="documento_actual" value="">

                <div style="display: grid; grid-template-columns: 1fr; gap: 25px;">
                    <div class="form-group">
                        <label class="form-label">ID (Automático Global)</label>
                        <input type="text" id="inputId" name="codigo_referencia" class="form-input-styled" 
                               placeholder="Se generará al guardar" 
                               style="background-color: #e9ecef; color: #666; cursor: not-allowed;" 
                               readonly>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 15px;">
                    <label class="form-label">Título de la Actividad</label>
                    <input type="text" id="inputTitle" name="titulo" class="form-input-styled" placeholder="Ej: Jornada de Monitoreo Hídrico" required>
                </div>

                <div class="form-group" style="margin-top: 15px;">
                    <label class="form-label">Descripción Detallada</label>
                    <div id="editor-container" style="background: white;"></div>
                    <input type="hidden" id="inputDesc" name="descripcion">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 25px; margin-top: 15px;">
                    <div class="form-group">
                        <label class="form-label">Agenda</label>
                        <select id="inputAgenda" name="agenda" class="form-input-styled" required>
                            <option value="">Selecciona...</option>
                            <option value="mediatica">Mediática</option>
                            <option value="tematica">Temática</option>
                            <option value="politica">Política</option>
                            <option value="social">Social</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Impacto Esperado</label>
                        <select id="inputLevel" name="nivel_impacto" class="form-input-styled" required>
                            <option value="alto">Alto</option>
                            <option value="medio">Medio</option>
                            <option value="bajo">Bajo</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Impacto (Ciudadanos)</label>
                        <input type="number" id="inputImpact" name="impacto_personas" class="form-input-styled" placeholder="0">
                    </div>
                </div>

                <div style="margin-top: 15px;">
                    <div class="form-group">
                        <label class="form-label">Costo Estimado</label>
                        <input type="number" step="0.01" id="inputCost" name="costo" class="form-input-styled" placeholder="0.00">
                    </div>
                </div>
                
                <div class="form-group" style="margin-top: 15px;">
                    <label class="form-label">Documento Adjunto (Opcional)</label>
                    <div class="file-upload-area" id="fileUploadArea" onclick="document.getElementById('fileInput').click()">
                        <i class="fas fa-cloud-upload-alt" style="font-size: 32px; color: var(--primary); margin-bottom: 10px;"></i>
                        <p style="font-size: 14px; color: #666; margin: 0;">Haz clic o arrastra un archivo aquí</p>
                        <p style="font-size: 12px; color: #999; margin-top: 5px;">PDF, DOC, DOCX, XLS, XLSX (Máx. 10MB)</p>
                    </div>
                    <input type="file" id="fileInput" name="documento" style="display: none;" 
                           accept=".pdf,.doc,.docx,.xls,.xlsx"
                           onchange="handleFileSelect(event)">
                    <div id="fileInfoContainer"></div>
                </div>

                <div id="uploadProgressBarContainer" style="display: none; margin-top: 15px; margin-bottom: 15px;">
                    <div style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 5px; color: var(--text-muted); font-weight: 700;">
                        <span>Subiendo archivo...</span>
                        <span id="uploadPercentage">0%</span>
                    </div>
                    <div style="width: 100%; background: var(--gray-200); height: 8px; border-radius: 4px; overflow: hidden;">
                        <div id="progressBar" style="width: 0%; height: 100%; background: var(--primary); transition: width 0.2s ease;"></div>
                    </div>
                </div>

                <div style="display: flex; gap: 15px; margin-top: 35px;">
                    <button type="button" class="btn-secondary-action" style="flex: 1; justify-content: center;" onclick="closeCreateModal()">Cancelar</button>
                    <button type="submit" class="btn-primary-action" style="flex: 2; justify-content: center;">Guardar Actividad</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal" id="detailModal">
        <div class="modal-content">
            <div class="close-modal" onclick="closeDetailModal()"><i class="fas fa-times"></i></div>
            
            <div style="display: flex; justify-content: space-between; align-items: start;">
                <div style="flex: 1;">
                    <span id="detailAgenda" class="badge badge-social" style="margin-bottom: 10px; font-size: 12px;">Agenda</span>
                    <h2 id="detailTitle" style="font-size: 28px; font-weight: 800; line-height: 1.2; color: var(--dark);">Título de la Actividad</h2>
                </div>
            </div>

            <div class="full-desc-box">
                <strong style="display: block; margin-bottom: 5px; color: var(--primary);">Descripción Completa:</strong>
                <div id="detailDesc">Aquí va la descripción detallada...</div>
            </div>

            <div class="detail-grid">
                <div class="detail-item">
                    <strong>Nivel de Impacto</strong>
                    <span id="detailLevel">Alto</span>
                </div>
                <div class="detail-item">
                    <strong>Alcance (Personas)</strong>
                    <span id="detailImpactValue">500+</span>
                </div>
                <div class="detail-item">
                    <strong>Costo Operativo</strong>
                    <span id="detailCost">$ 0.00</span>
                </div>
                <div class="detail-item">
                    <strong>ID Referencia</strong>
                    <span id="detailRef">#ACT-2024-X</span>
                </div>
                <div class="detail-item">
                    <strong>Documento</strong>
                    <span id="detailDocumento">Sin documento</span>
                </div>
            </div>

            <div style="margin-top: 35px; text-align: right;">
                <button type="button" class="btn-secondary-action" style="display: inline-flex;" onclick="closeDetailModal()">Cerrar Detalles</button>
            </div>
        </div>
    </div>

    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>

    <script>
        var quill = new Quill('#editor-container', {
            theme: 'snow',
            placeholder: 'Escribe la descripción detallada de la actividad...',
            modules: {
                toolbar: [
                    ['bold', 'italic', 'underline'],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    ['link', 'clean']
                ]
            }
        });

        // MANEJO DE ARCHIVOS
        let selectedFile = null;

        function handleFileSelect(event) {
            const file = event.target.files[0];
            if (!file) return;
            // Quitamos validación JS de tamaño (se maneja en servidor PHP.ini)
            selectedFile = file;
            showFileInfo(file);
        }

        function showFileInfo(file) {
            const container = document.getElementById('fileInfoContainer');
            const icon = getFileIcon(file.name);
            container.innerHTML = `<div class="file-info"><i class="${icon}"></i><span class="file-name">${file.name}</span><i class="fas fa-times file-remove" onclick="removeFile()"></i></div>`;
        }

        function removeFile() {
            selectedFile = null;
            document.getElementById('fileInput').value = '';
            document.getElementById('fileInfoContainer').innerHTML = '';
        }

        function getFileIcon(filename) {
            const ext = filename.split('.').pop().toLowerCase();
            const icons = { 'pdf': 'fas fa-file-pdf', 'doc': 'fas fa-file-word', 'docx': 'fas fa-file-word', 'xls': 'fas fa-file-excel', 'xlsx': 'fas fa-file-excel' };
            return icons[ext] || 'fas fa-file';
        }

        const uploadArea = document.getElementById('fileUploadArea');
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => { uploadArea.addEventListener(eventName, preventDefaults, false); });
        function preventDefaults(e) { e.preventDefault(); e.stopPropagation(); }
        ['dragenter', 'dragover'].forEach(eventName => { uploadArea.addEventListener(eventName, () => { uploadArea.classList.add('dragover'); }, false); });
        ['dragleave', 'drop'].forEach(eventName => { uploadArea.addEventListener(eventName, () => { uploadArea.classList.remove('dragover'); }, false); });
        uploadArea.addEventListener('drop', (e) => {
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                document.getElementById('fileInput').files = files;
                handleFileSelect({ target: { files: files } });
            }
        }, false);

        // MENU Y ACCIONES
        function toggleDropdown(btn) {
            document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                if (menu !== btn.nextElementSibling) {
                    menu.classList.remove('show');
                    menu.previousElementSibling.classList.remove('active');
                }
            });
            const menu = btn.nextElementSibling;
            menu.classList.toggle('show');
            btn.classList.toggle('active');
            event.stopPropagation();
        }

        function handleMenuAction(item, action) {
            const row = item.closest('tr');
            if (action === 'view') { viewDetails(row); } 
            item.closest('.dropdown-menu').classList.remove('show');
            item.closest('.action-cell').querySelector('.action-btn').classList.remove('active');
            event.stopPropagation();
        }

        window.addEventListener('click', () => {
            document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                menu.classList.remove('show');
                menu.previousElementSibling.classList.remove('active');
            });
        });

        // MODAL LOGIC
        const createModal = document.getElementById('taskModal');
        const createForm = document.getElementById('taskForm');

        function openCreateModal() {
            createForm.reset(); 
            quill.setContents([]); 
            removeFile();
            
            document.getElementById('modalTitle').innerText = "Proponer Nueva Actividad";
            document.getElementById('actionType').value = "create";
            document.getElementById('dbId').value = "";
            document.getElementById('documentoActual').value = "";
            
            const hoy = new Date();
            const yy = String(hoy.getFullYear()).slice(-2);
            const mm = String(hoy.getMonth() + 1).padStart(2, '0');
            const dd = String(hoy.getDate()).padStart(2, '0');
            
            document.getElementById('inputId').value = `${yy}${mm}${dd}-XXX`;
            
            // Reset Progress Bar
            document.getElementById('uploadProgressBarContainer').style.display = 'none';
            document.getElementById('progressBar').style.width = '0%';

            createModal.style.display = 'flex';
            setTimeout(() => { createModal.classList.add('open'); document.body.style.overflow = 'hidden'; }, 10); 
        }

        function closeCreateModal() {
            createModal.classList.remove('open'); document.body.style.overflow = '';
            setTimeout(() => { createModal.style.display = 'none'; }, 400); 
        }

        const detailModal = document.getElementById('detailModal');

        function viewDetails(element) {
            const row = element.closest('tr');
            const title = row.getAttribute('data-title');
            const desc = row.getAttribute('data-desc');
            const agenda = row.getAttribute('data-agenda');
            const level = row.getAttribute('data-level');
            const impactVal = row.getAttribute('data-impact-value');
            const cost = row.getAttribute('data-cost');
            const refId = row.getAttribute('data-id');
            const documento = row.getAttribute('data-documento');

            document.getElementById('detailTitle').innerText = title;
            document.getElementById('detailDesc').innerHTML = desc; 
            document.getElementById('detailCost').innerText = cost || 'No especificado';
            document.getElementById('detailImpactValue').innerText = impactVal;
            document.getElementById('detailRef').innerText = refId || 'N/A';

            const docSpan = document.getElementById('detailDocumento');
            if (documento) { docSpan.innerHTML = `<a href="../../api/banco_de_actividades/documentos/${documento}" target="_blank" class="file-download-link"><i class="fas fa-file-download"></i> ${documento}</a>`; } else { docSpan.innerText = 'Sin documento'; }

            const agendaBadge = document.getElementById('detailAgenda');
            agendaBadge.className = `badge badge-${agenda}`;
            agendaBadge.innerText = agenda.charAt(0).toUpperCase() + agenda.slice(1);

            const levelSpan = document.getElementById('detailLevel');
            levelSpan.innerText = level.charAt(0).toUpperCase() + level.slice(1);
            levelSpan.className = ''; 
            if(level === 'alto') levelSpan.classList.add('impact-high');
            else if(level === 'medio') levelSpan.classList.add('impact-medium');
            else levelSpan.classList.add('impact-low');

            detailModal.style.display = 'flex';
            setTimeout(() => { detailModal.classList.add('open'); document.body.style.overflow = 'hidden'; }, 10);
        }

        function closeDetailModal() {
            detailModal.classList.remove('open'); document.body.style.overflow = '';
            setTimeout(() => { detailModal.style.display = 'none'; }, 400);
        }

        window.addEventListener('click', (e) => { 
            if (e.target === createModal) closeCreateModal(); 
            if (e.target === detailModal) closeDetailModal();
        });

        // -----------------------------------------------------------------
        // NUEVO: MANEJO DEL SUBMIT CON AJAX PARA BARRA DE PROGRESO ANIMADA
        // -----------------------------------------------------------------
        createForm.addEventListener('submit', function(e) {
            e.preventDefault(); // Detenemos el envío normal para usar AJAX

            // 1. Validar y sincronizar Quill
            if (quill.getText().trim().length === 0) {
                alert('Por favor, ingresa una descripción para la actividad.');
                return;
            }
            document.getElementById('inputDesc').value = quill.root.innerHTML;
            
            // 2. Elementos de UI
            const btnSave = this.querySelector('button[type="submit"]');
            const originalBtnText = btnSave.innerHTML;
            const progressContainer = document.getElementById('uploadProgressBarContainer');
            const progressBar = document.getElementById('progressBar');
            const progressText = document.getElementById('uploadPercentage');

            // 3. Preparar UI para carga
            btnSave.disabled = true;
            btnSave.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Subiendo...';
            progressContainer.style.display = 'block';
            progressBar.style.width = '0%';
            progressText.innerText = '0%';

            // 4. Crear FormData y XHR
            const formData = new FormData(this);
            const xhr = new XMLHttpRequest();

            // Evento: Progreso de subida
            xhr.upload.addEventListener("progress", function(evt) {
                if (evt.lengthComputable) {
                    const percentComplete = Math.round((evt.loaded / evt.total) * 100);
                    progressBar.style.width = percentComplete + '%';
                    progressText.innerText = percentComplete + '%';
                    
                    if(percentComplete < 100){
                        btnSave.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Subiendo ${percentComplete}%...`;
                    } else {
                        btnSave.innerHTML = `<i class="fas fa-cog fa-spin"></i> Procesando...`;
                    }
                }
            }, false);

            // Evento: Completado
            xhr.addEventListener("load", function() {
                if (xhr.status === 200) {
                    progressBar.style.background = '#2d7a3e'; // Verde éxito
                    btnSave.innerHTML = '<i class="fas fa-check"></i> ¡Listo!';
                    alert('¡Propuesta enviada al Banco!\n\nTu actividad ha sido registrada');
                    setTimeout(() => {
                        window.location.reload(); 
                    }, 500);
                } else {
                    alert("Error en la subida");
                    resetUI();
                }
            }, false);

            // Evento: Error
            xhr.addEventListener("error", function() {
                alert("Error de red al intentar subir.");
                resetUI();
            }, false);

            xhr.open("POST", this.getAttribute("action"));
            xhr.send(formData);

            function resetUI() {
                btnSave.innerHTML = originalBtnText;
                btnSave.disabled = false;
                progressContainer.style.display = 'none';
                progressBar.style.width = '0%';
                progressBar.style.background = 'var(--primary)';
            }
        });

        // PAGINACION Y FILTROS
        let currentPage = 1;
        const rowsPerPage = 10;
        const searchInput = document.getElementById('searchInput');
        const agendaFilter = document.getElementById('agendaFilter');
        const levelFilter = document.getElementById('levelFilter');
        const tableBody = document.getElementById('activities-table-body');
        const paginationInfo = document.getElementById('paginationInfo');
        const paginationControls = document.getElementById('paginationControls');

        function updateTable() {
            const search = searchInput.value.toLowerCase();
            const agenda = agendaFilter.value;
            const level = levelFilter.value;
            const rows = Array.from(tableBody.querySelectorAll('tr'));
            
            const filteredRows = rows.filter(row => {
                if (!row.hasAttribute('data-title')) return true; 
                const title = row.getAttribute('data-title').toLowerCase();
                const desc = row.getAttribute('data-desc').toLowerCase();
                const rowAgenda = row.getAttribute('data-agenda');
                const rowLevel = row.getAttribute('data-level');
                const text = title + " " + desc;
                const matchesSearch = text.includes(search);
                const matchesAgenda = agenda === 'all' || rowAgenda === agenda;
                const matchesLevel = level === 'all' || rowLevel === level;
                return matchesSearch && matchesAgenda && matchesLevel;
            });

            const validRows = filteredRows.filter(r => r.hasAttribute('data-title'));
            const totalItems = validRows.length;
            const totalPages = Math.ceil(totalItems / rowsPerPage);

            if (currentPage > totalPages) currentPage = totalPages;
            if (currentPage < 1) currentPage = 1;

            const start = (currentPage - 1) * rowsPerPage;
            const end = start + rowsPerPage;

            rows.forEach(row => row.style.display = 'none');

            if (totalItems === 0) {
                rows.forEach(row => { if(!row.hasAttribute('data-title')) row.style.display = ''; });
                paginationInfo.innerText = 'Sin resultados';
                paginationControls.innerHTML = '';
                return;
            }

            validRows.slice(start, end).forEach(row => { row.style.display = ''; });

            paginationInfo.innerText = `Mostrando ${start + 1} - ${Math.min(end, totalItems)} de ${totalItems} actividades`;
            renderPaginationControls(totalPages);
        }

        function renderPaginationControls(totalPages) {
            paginationControls.innerHTML = '';
            const prevBtn = document.createElement('button');
            prevBtn.className = 'page-btn';
            prevBtn.innerHTML = '<i class="fas fa-chevron-left"></i>';
            prevBtn.disabled = currentPage === 1;
            prevBtn.onclick = () => { if(currentPage > 1) { currentPage--; updateTable(); }};
            paginationControls.appendChild(prevBtn);

            let startPage = Math.max(1, currentPage - 2);
            let endPage = Math.min(totalPages, currentPage + 2);

            if (startPage > 1) { paginationControls.appendChild(createPageBtn(1)); if(startPage > 2) paginationControls.appendChild(createEllipsis()); }

            for (let i = startPage; i <= endPage; i++) { paginationControls.appendChild(createPageBtn(i)); }

            if (endPage < totalPages) { if(endPage < totalPages - 1) paginationControls.appendChild(createEllipsis()); paginationControls.appendChild(createPageBtn(totalPages)); }

            const nextBtn = document.createElement('button');
            nextBtn.className = 'page-btn';
            nextBtn.innerHTML = '<i class="fas fa-chevron-right"></i>';
            nextBtn.disabled = currentPage === totalPages;
            nextBtn.onclick = () => { if(currentPage < totalPages) { currentPage++; updateTable(); }};
            paginationControls.appendChild(nextBtn);
        }

        function createPageBtn(page) {
            const btn = document.createElement('button');
            btn.className = `page-btn ${page === currentPage ? 'active' : ''}`;
            btn.innerText = page;
            btn.onclick = () => { currentPage = page; updateTable(); };
            return btn;
        }

        function createEllipsis() {
            const span = document.createElement('span');
            span.innerText = '...'; span.style.padding = '0 5px'; span.style.color = '#999'; span.style.display = 'flex'; span.style.alignItems = 'center';
            return span;
        }

        searchInput.addEventListener('input', () => { currentPage = 1; updateTable(); });
        agendaFilter.addEventListener('change', () => { currentPage = 1; updateTable(); });
        levelFilter.addEventListener('change', () => { currentPage = 1; updateTable(); });
        document.addEventListener('DOMContentLoaded', updateTable);
    </script>
</body>
</html>