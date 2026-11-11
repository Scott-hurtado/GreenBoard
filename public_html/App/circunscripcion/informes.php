<?php
// 1. Carga del sistema
if (file_exists(__DIR__ . '/../../includes/bootstrap.php')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
} else {
    session_start();
}

// 2. Seguridad
$rol = $_SESSION['user_role'] ?? '';
$permitidos = ['admin', 'administrador', 'superadmin', 'super admin', 'circunscripcion'];

if (!in_array($rol, $permitidos)) {
    header('Location: /public/index.php'); 
    exit();
}

// Obtener nombre del usuario de la sesión
$usuario_actual = $_SESSION['user_name'] ?? 'Super Admin';

// 3. Obtener informes
$informes = [];
try {
    $dbConfigPath = __DIR__ . '/../../includes/database.php';
    if (file_exists($dbConfigPath)) {
        $dbConfig = require $dbConfigPath;
        $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
        $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options'] ?? []);
    } elseif (class_exists('Database')) {
        $pdo = Database::getInstance()->getConnection();
    }

    if (isset($pdo)) {
        $sql = "SELECT id, titulo, creado_por, DATE_FORMAT(fecha_creacion, '%d/%b/%Y') AS fecha_creacion, periodo, tipo, estado, archivo_url, contenido FROM informes ORDER BY id DESC";
        $stmt = $pdo->query($sql);
        $informes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log("Error cargando informes: " . $e->getMessage());
}

// MAPEOS VISUALES
$periodo_class = [
    'mensual' => 'badge-blue',
    'trimestral' => 'badge-purple',
    'anual' => 'badge-orange',
    'especial' => 'badge-dark'
];

$tipo_class = [
    'comunicacion' => 'badge-pink',
    'comunicación' => 'badge-pink',
    'avance' => 'badge-green',
    'territorio' => 'badge-teal',
    'legal' => 'badge-yellow',
    'financiero' => 'badge-gray'
];

$estado_class = [
    'borrador' => 'badge-gray',
    'publicado' => 'badge-green',
    'archivado' => 'badge-yellow'
];

// Función para truncar texto
function truncateText($text, $length = 100) {
    $text = strip_tags($text);
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . '...';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informes - Proyecto Verde</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <style>
        :root {
            --primary: #2d7a3e; --primary-dark: #1f5a2d; --dark: #1a1a1a; --light: #f0f7f0; --white: #ffffff;
            --gray-100: #f8f9fa; --gray-200: #e9ecef; --text-muted: #6c757d;
            --shadow-sm: 0 10px 30px rgba(0,0,0,0.05); --shadow-hover: 0 20px 40px rgba(45, 122, 62, 0.15);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; background: var(--light); color: var(--dark); overflow-x: hidden; }

        .container { max-width: 100%; margin: 0; background: var(--white); min-height: 100vh; position: relative; padding-left: 330px; padding-right: 50px; padding-bottom: 40px; transition: padding-left 0.3s ease; }
        .design-text { position: fixed; left: 290px; top: 50%; writing-mode: vertical-rl; font-size: 13px; font-weight: 500; color: var(--primary); opacity: 0.3; letter-spacing: 2px; z-index: 0; pointer-events: none; }

        .page-header { padding: 50px 0 40px 0; position: relative; z-index: 2; display: flex; justify-content: space-between; align-items: flex-end; }
        .ai-badge { color: var(--primary); font-size: 13px; font-weight: 800; letter-spacing: 4px; margin-bottom: 15px; display: inline-block; text-transform: uppercase; background: rgba(45,122,62,0.1); padding: 5px 15px; border-radius: 20px; }
        .main-title { font-size: 54px; font-weight: 900; line-height: 1; margin-bottom: 15px; color: var(--dark); }
        .description { color: #666; font-size: 16px; line-height: 1.6; max-width: 600px; }

        .btn-primary-action { background: var(--primary); color: white; border: none; padding: 12px 25px; border-radius: 12px; font-weight: 700; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; gap: 10px; font-size: 14px; box-shadow: 0 4px 15px rgba(45, 122, 62, 0.3); text-decoration: none; }
        .btn-primary-action:hover { background: var(--primary-dark); transform: translateY(-2px); }
        
        .btn-action { background: var(--white); color: var(--dark); padding: 10px 20px; border-radius: 10px; border: 2px solid var(--gray-200); font-weight: 700; cursor: pointer; transition: 0.3s; }
        .btn-action:hover { border-color: var(--dark); }

        .filters-bar { background: var(--white); border: 1px solid var(--gray-200); border-radius: 16px; padding: 20px; margin-bottom: 30px; display: flex; gap: 20px; align-items: flex-end; box-shadow: var(--shadow-sm); z-index: 5; position: relative; }
        .form-group { display: flex; flex-direction: column; gap: 8px; flex: 1; }
        .form-label { font-size: 12px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.5px; }
        .form-input-styled { width: 100%; padding: 12px 15px; border-radius: 10px; border: 2px solid var(--gray-200); background: var(--gray-100); font-size: 14px; font-weight: 600; color: var(--dark); transition: all 0.3s; outline: none; }
        .form-input-styled:focus { border-color: var(--primary); background: var(--white); }

        .char-counter { font-size: 11px; color: var(--text-muted); text-align: right; margin-top: 5px; font-weight: 600; }
        .char-counter.warning { color: #ef6c00; }
        .char-counter.danger { color: #d32f2f; }

        .table-section { background: var(--white); border-radius: 20px; padding: 30px; border: 1px solid var(--gray-200); box-shadow: var(--shadow-sm); }
        .section-header { margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; }
        .section-title { font-size: 20px; font-weight: 800; color: var(--dark); }
        
        /* --- ESTILOS DE TABLA MEJORADOS PARA LEGIBILIDAD --- */
        .modern-table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .modern-table th { 
            text-align: left; 
            padding: 18px 20px; /* Más padding */
            color: var(--text-muted); 
            font-size: 12px; 
            text-transform: uppercase; 
            letter-spacing: 1px; 
            border-bottom: 2px solid var(--gray-200); 
        }
        .modern-table td { 
            padding: 22px 20px; /* Celdas más altas */
            border-bottom: 1px solid var(--gray-200); 
            font-size: 14px; 
            color: var(--dark); 
            font-weight: 500; 
            vertical-align: middle; 
        }
        .modern-table tr:hover td { background: var(--gray-100); }

        /* --- ESTILOS DE PAGINACIÓN --- */
        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 20px;
            border-top: 1px solid var(--gray-200);
            margin-top: 20px;
        }
        .pagination-info { font-size: 13px; color: var(--text-muted); font-weight: 600; }
        .pagination-controls { display: flex; gap: 5px; }
        .page-btn {
            background: var(--white);
            border: 1px solid var(--gray-200);
            color: var(--dark);
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 13px;
            font-weight: 700;
        }
        .page-btn:hover:not(:disabled) { background: var(--gray-100); border-color: var(--primary); color: var(--primary); }
        .page-btn.active { background: var(--primary); border-color: var(--primary); color: white; }
        .page-btn:disabled { opacity: 0.5; cursor: not-allowed; background: var(--gray-100); }

        .badge { padding: 6px 12px; border-radius: 30px; font-size: 11px; font-weight: 700; text-transform: uppercase; display: inline-block; }
        .badge-blue { background: #e3f2fd; color: #1565c0; }
        .badge-purple { background: #f3e5f5; color: #7b1fa2; }
        .badge-orange { background: #fff3e0; color: #ef6c00; }
        .badge-dark { background: #343a40; color: #fff; }
        .badge-pink { background: #fce4ec; color: #c2185b; }
        .badge-green { background: #e8f5e9; color: #2e7d32; }
        .badge-teal { background: #e0f2f1; color: #00695c; }
        .badge-yellow { background: #fff8e1; color: #f57c00; }
        .badge-gray { background: #e9ecef; color: #495057; }

        .action-btn { background: transparent; border: none; color: #aaa; cursor: pointer; font-size: 16px; padding: 8px; border-radius: 50%; transition: 0.2s; }
        .action-btn:hover { background: var(--gray-200); color: var(--dark); }
        .dropdown-menu { position: absolute; right: 0; background: white; border: 1px solid var(--gray-200); box-shadow: 0 10px 30px rgba(0,0,0,0.1); border-radius: 10px; width: 180px; opacity: 0; visibility: hidden; transform: translateY(10px); transition: 0.2s; z-index: 50; }
        .dropdown-menu.show { opacity: 1; visibility: visible; transform: translateY(0); }
        .dropdown-item { display: flex; align-items: center; gap: 10px; padding: 12px 15px; color: var(--dark); text-decoration: none; font-size: 13px; transition: 0.2s; }
        .dropdown-item:hover { background: var(--gray-100); color: var(--primary); }

        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(5px); z-index: 2000; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.3s ease; }
        .modal.show { display: flex; opacity: 1; }
        .modal-content { background: var(--white); border-radius: 20px; border: 1px solid var(--gray-200); box-shadow: 0 25px 50px rgba(0,0,0,0.1); width: 90%; max-width: 800px; max-height: 90vh; display: flex; flex-direction: column; overflow: hidden; transform: translateY(30px); transition: transform 0.3s ease; }
        .modal.show .modal-content { transform: translateY(0); }
        .modal-header { padding: 25px 30px; border-bottom: 1px solid var(--gray-200); display: flex; justify-content: space-between; align-items: center; }
        .modal-body { padding: 30px; overflow-y: auto; }
        .modal-footer { padding: 20px 30px; background: var(--gray-100); border-top: 1px solid var(--gray-200); display: flex; justify-content: flex-end; gap: 10px; }
        .close-modal { background: none; border: none; font-size: 20px; color: #ccc; cursor: pointer; transition: 0.2s; }
        .close-modal:hover { color: var(--dark); transform: rotate(90deg); }

        #editor-container { border: 2px solid var(--gray-200); border-radius: 10px; overflow: hidden; background: var(--white); }
        .ql-toolbar { background: var(--gray-100); border: none !important; border-bottom: 1px solid var(--gray-200) !important; }
        .ql-container { border: none !important; font-size: 15px; font-family: 'Arial', sans-serif; min-height: 200px; }

        /* Progress Bar Styles */
        .upload-progress-container { 
            position: fixed; top: 0; left: 50%; transform: translateX(-50%); width: 600px; background: var(--white); 
            border: 2px solid var(--primary); border-radius: 0 0 15px 15px; box-shadow: 0 10px 30px rgba(45, 122, 62, 0.3); 
            padding: 20px 25px; z-index: 3000; display: none; 
        }
        .upload-progress-container.active { display: block; animation: slideDown 0.4s ease; }
        @keyframes slideDown { from { transform: translateX(-50%) translateY(-100%); } to { transform: translateX(-50%) translateY(0); } }

        .upload-progress-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .upload-progress-title { font-size: 14px; font-weight: 700; color: var(--dark); display: flex; align-items: center; gap: 10px; }
        .upload-progress-title i { color: var(--primary); }
        .upload-progress-percentage { font-size: 18px; font-weight: 900; color: var(--primary); }

        .progress-bar-container { width: 100%; height: 8px; background: var(--gray-200); border-radius: 10px; overflow: hidden; position: relative; }
        .progress-bar { height: 100%; background: linear-gradient(90deg, var(--primary) 0%, var(--accent-green, #8bc34a) 100%); border-radius: 10px; transition: width 0.3s ease; width: 0%; }
        .upload-progress-info { margin-top: 10px; font-size: 12px; color: var(--text-muted); display: flex; justify-content: space-between; }

        .animate-up { animation: fadeInUp 0.6s ease forwards; opacity: 0; }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

        @media (max-width: 1024px) { 
            .container { padding-left: 20px; padding-right: 20px; } 
            .page-header { flex-direction: column; align-items: flex-start; gap: 20px; } 
            .main-title { font-size: 40px; } 
            .filters-bar { flex-direction: column; align-items: stretch; } 
            .design-text { display: none; } 
            .upload-progress-container { width: 90%; max-width: 500px; }
        }
        
        .modern-table td a:hover {
    color: var(--primary) !important;
    text-decoration: underline;
}
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/components/sidebar_circunscripcion.php'; ?>

    <div class="design-text">SISTEMA v2.5</div>

    <div class="upload-progress-container" id="uploadProgressContainer">
        <div class="upload-progress-header">
            <div class="upload-progress-title">
                <i class="fas fa-cloud-upload-alt"></i>
                <span>Subiendo archivo...</span>
            </div>
            <div class="upload-progress-percentage" id="uploadPercentage">0%</div>
        </div>
        <div class="progress-bar-container">
            <div class="progress-bar" id="uploadProgressBar"></div>
        </div>
        <div class="upload-progress-info">
            <span id="uploadFileName"></span>
            <span id="uploadStatus">Preparando...</span>
        </div>
    </div>

    <div class="container">
        <div class="page-header animate-up">
            <div>
                <div class="ai-badge">DOCUMENTACIÓN</div>
                <h1 class="main-title">Gestión de<br>Informes.</h1>
                <p class="description">Repositorio centralizado de documentación, reportes de actividad y archivos oficiales del proyecto.</p>
            </div>
            <div class="header-actions">
                <button class="btn-primary-action" onclick="openReportModal()">
                    <i class="fas fa-plus"></i> Nuevo Informe
                </button>
            </div>
        </div>

        <div class="filters-bar animate-up" style="animation-delay: 0.1s;">
            <div class="form-group" style="flex: 2;">
                <label class="form-label">Búsqueda Rápida</label>
                <div style="position: relative;">
                    <i class="fas fa-search" style="position: absolute; left: 15px; top: 14px; color: #999;"></i>
                    <input type="text" id="searchInput" class="form-input-styled" style="padding-left: 40px;" placeholder="Buscar por título, autor o contenido...">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Filtrar por Tipo</label>
                <select id="filterTipo" class="form-input-styled">
                    <option value="all">Todos los Tipos</option>
                    <option value="Comunicacion">Comunicación</option>
                    <option value="Avance">Avance</option>
                    <option value="Territorio">Territorio</option>
                    <option value="Legal">Legal</option>
                    <option value="Financiero">Financiero</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Filtrar por Periodo</label>
                <select id="filterPeriodo" class="form-input-styled">
                    <option value="all">Todos los Periodos</option>
                    <option value="Mensual">Mensual</option>
                    <option value="Trimestral">Trimestral</option>
                    <option value="Anual">Anual</option>
                    <option value="Especial">Especial</option>
                </select>
            </div>
        </div>

        <div class="table-section animate-up" style="animation-delay: 0.2s;">
            <div class="section-header">
                <h3 class="section-title">Archivo Documental</h3>
                <div class="ai-badge" id="totalCount" style="margin:0; font-size:10px; background: #e3f2fd; color: #1565c0;">
                    Total: 0
                </div>
            </div>
            
            <div style="overflow-x: auto;">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th style="width: 25%;">Título</th>
                            <th style="width: 25%;">Resumen</th>
                            <th>Tipo</th>
                            <th>Periodo</th>
                            <th>Estatus</th>
                            <th>Autor</th>
                            <th>Fecha</th>
                            <th style="text-align: right;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <?php if (!empty($informes)): ?>
                        <?php foreach ($informes as $informe): 
                            $archivo_url = $informe['archivo_url'] ?? '';
                            $is_file = !empty($archivo_url);
                            $ext = $is_file ? strtolower(pathinfo($archivo_url, PATHINFO_EXTENSION)) : '';
                            
                            $file_icon = '';
                            if($ext == 'pdf') {
                                $file_icon = '<i class="fas fa-file-pdf" style="color: #d32f2f; margin-right:5px;"></i>';
                            } elseif(in_array($ext, ['doc', 'docx'])) {
                                $file_icon = '<i class="fas fa-file-word" style="color: #2b5797; margin-right:5px;"></i>';
                            }

                            $periodo_lower = strtolower($informe['periodo'] ?? 'mensual');
                            $tipo_lower = strtolower($informe['tipo'] ?? 'comunicacion');
                            $estado_lower = strtolower($informe['estado'] ?? 'borrador');
                            $periodo_text = ucfirst($informe['periodo'] ?? 'Mensual');
                            $tipo_text = $informe['tipo'] ?? 'General';
                            $tipo_display = ucfirst($tipo_text);
                            $resumen = truncateText($informe['contenido'] ?? 'Sin contenido', 120);
                        ?>
                        <tr data-report-id="<?php echo $informe['id']; ?>" 
                            data-tipo="<?php echo $tipo_display; ?>" 
                            data-periodo="<?php echo $periodo_text; ?>">
<td style="font-weight: 600;">
    <?php echo $file_icon; ?>
    <?php if ($is_file): ?>
        <a href="<?php echo htmlspecialchars($archivo_url); ?>" target="_blank" style="color: var(--dark); text-decoration: none; transition: color 0.2s;">
            <?php echo htmlspecialchars($informe['titulo']); ?>
        </a>
    <?php else: ?>
        <a href="#" onclick="viewReport(<?php echo $informe['id']; ?>); return false;" style="color: var(--dark); text-decoration: none; transition: color 0.2s;">
            <?php echo htmlspecialchars($informe['titulo']); ?>
        </a>
    <?php endif; ?>
</td>
                            <td style="color: #666; font-size: 13px; font-weight: 400;">
                                <?php echo htmlspecialchars($resumen); ?>
                            </td>
                            <td><span class="badge <?php echo $tipo_class[$tipo_lower] ?? 'badge-gray'; ?>"><?php echo htmlspecialchars($tipo_display); ?></span></td>
                            <td><span class="badge <?php echo $periodo_class[$periodo_lower] ?? 'badge-blue'; ?>"><?php echo htmlspecialchars($periodo_text); ?></span></td>
                            <td><span class="badge <?php echo $estado_class[$estado_lower] ?? 'badge-gray'; ?>"><?php echo htmlspecialchars(ucfirst($informe['estado'])); ?></span></td>
                            <td><?php echo htmlspecialchars($informe['creado_por']); ?></td>
                            <td style="color: var(--text-muted); font-size: 13px;"><?php echo htmlspecialchars($informe['fecha_creacion']); ?></td>
                            <td style="text-align: right; position: relative;">
                                <button class="action-btn" onclick="toggleSubMenu(this, <?php echo $informe['id']; ?>)">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <div class="dropdown-menu" id="menu-<?php echo $informe['id']; ?>">
                                    <?php if ($is_file): ?>
                                        <a href="<?php echo htmlspecialchars($archivo_url); ?>" target="_blank" class="dropdown-item">
                                            <i class="fas fa-eye"></i> Ver / Descargar
                                        </a>
                                    <?php else: ?>
                                        <a href="#" class="dropdown-item" onclick="viewReport(<?php echo $informe['id']; ?>)">
                                            <i class="fas fa-eye"></i> Ver Contenido
                                        </a>
                                        <a href="#" class="dropdown-item" onclick="editReport(<?php echo $informe['id']; ?>)">
                                            <i class="fas fa-edit"></i> Editar
                                        </a>
                                    <?php endif; ?>
                                    <div style="border-top: 1px solid #eee; margin: 5px 0;"></div>
                                    <a href="#" class="dropdown-item" style="color: #d32f2f;" onclick="deleteReport(<?php echo $informe['id']; ?>)">
                                        <i class="fas fa-trash-alt"></i> Eliminar
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <tr id="noReportsRow">
                            <td colspan="8" style="text-align: center; padding: 40px; color: #999;">No hay informes registrados.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div id="paginationContainer" class="pagination-container" style="display:none;">
                <div class="pagination-info" id="pageInfo">Mostrando 0 de 0</div>
                <div class="pagination-controls" id="paginationControls">
                    </div>
            </div>
        </div>
    </div>

    <div class="modal" id="reportModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Nuevo Informe</h2>
                <button type="button" class="close-modal" onclick="closeModal('reportModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="reportForm" enctype="multipart/form-data"> 
                    <input type="hidden" id="reportId" name="id">
                    
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label class="form-label" for="reportTitle">Título del Informe</label>
                        <input type="text" class="form-input-styled" id="reportTitle" name="titulo" required placeholder="Ej: Informe de Avance Zona Norte" maxlength="80">
                        <div class="char-counter" id="titleCounter">0 / 80 caracteres</div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                        <div class="form-group">
                            <label class="form-label">Tipo (Categoría)</label>
                            <select class="form-input-styled" id="reportTipo" name="tipo" required>
                                <option value="">Selecciona...</option>
                                <option value="Comunicacion">Comunicación</option>
                                <option value="Avance">Avance</option>
                                <option value="Territorio">Territorio</option>
                                <option value="Legal">Legal</option>
                                <option value="Financiero">Financiero</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Periodo</label>
                            <select class="form-input-styled" id="reportPeriodo" name="periodo" required>
                                <option value="">Selecciona...</option>
                                <option value="Mensual">Mensual</option>
                                <option value="Trimestral">Trimestral</option>
                                <option value="Anual">Anual</option>
                                <option value="Especial">Especial</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Estatus</label>
                            <select class="form-input-styled" id="reportStatus" name="estado" required>
                                <option value="Borrador">Borrador</option>
                                <option value="Publicado">Publicado</option>
                                <option value="Archivado">Archivado</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 20px;">
                        <label class="form-label">Autor</label>
                        <input type="text" class="form-input-styled" id="reportAuthor" name="creado_por" value="<?php echo htmlspecialchars($usuario_actual); ?>" readonly style="background: #f5f5f5; cursor: not-allowed;">
                    </div>

                    <div class="form-group" style="margin-bottom: 20px;">
                        <label class="form-label">Resumen / Contenido</label>
                        <div id="editor-container">
                            <div id="editor"></div>
                        </div>
                        <div class="char-counter" id="contentCounter">0 / 500 caracteres</div>
                    </div>

                    <div class="form-group" style="padding-top: 20px; border-top: 1px dashed #e9ecef;">
                        <label class="form-label" style="display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-paperclip" style="color: var(--primary);"></i> Documento Adjunto (PDF o DOCX)
                        </label>
                        <input type="file" id="reportFile" name="archivo" accept=".pdf,.doc,.docx" class="form-input-styled" style="padding: 10px;">
                        <div style="font-size: 11px; color: #999; margin-top: 5px;">
                            * Opcional. Formatos aceptados: .pdf, .doc, .docx
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-action" onclick="closeModal('reportModal')">Cancelar</button>
                <button type="button" class="btn-primary-action" onclick="saveReport()">
                    <i class="fas fa-save"></i> Guardar Informe
                </button>
            </div>
        </div>
    </div>

    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    <script>
        // --- VARIABLES GLOBALES DE PAGINACIÓN ---
        let currentPage = 1;
        const itemsPerPage = 10;
        let filteredRows = []; // Almacena filas visibles después del filtro
        let allReportRows = []; // Almacena referencia a todas las filas

        let quill = null;
        const MAX_CONTENT_LENGTH = 500;

        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar Editor
            initializeEditor();
            
            // Inicializar contadores
            document.getElementById('reportTitle').addEventListener('input', function() {
                updateCharCounter('reportTitle', 'titleCounter', 80);
            });

            // Inicializar Listeners de Filtro
            document.getElementById('searchInput').addEventListener('keyup', applyFilters);
            document.getElementById('filterTipo').addEventListener('change', applyFilters);
            document.getElementById('filterPeriodo').addEventListener('change', applyFilters);

            // Inicializar Datos de Tabla y Paginación
            allReportRows = Array.from(document.querySelectorAll('#tableBody tr[data-report-id]'));
            applyFilters();
        });

        // =============================================
        // LÓGICA DE FILTROS Y PAGINACIÓN
        // =============================================
        function applyFilters() {
            const search = document.getElementById('searchInput').value.toLowerCase();
            const tipoFilter = document.getElementById('filterTipo').value;
            const periodoFilter = document.getElementById('filterPeriodo').value;
            
            // Recalcular lista filtrada
            filteredRows = allReportRows.filter(row => {
                const text = row.textContent.toLowerCase();
                const rowTipo = row.getAttribute('data-tipo');
                const rowPeriodo = row.getAttribute('data-periodo');

                const matchesSearch = text.includes(search);
                const matchesTipo = (tipoFilter === 'all' || rowTipo === tipoFilter);
                const matchesPeriodo = (periodoFilter === 'all' || rowPeriodo === periodoFilter);

                // Ocultar fila por defecto
                row.style.display = 'none'; 

                return matchesSearch && matchesTipo && matchesPeriodo;
            });

            // Actualizar Badge de Total
            const totalBadge = document.getElementById('totalCount');
            if(totalBadge) totalBadge.textContent = 'Total: ' + filteredRows.length;

            // Resetear a página 1 y renderizar
            currentPage = 1;
            renderTablePage();
            renderPaginationControls();
        }

        function renderTablePage() {
            const startIndex = (currentPage - 1) * itemsPerPage;
            const endIndex = startIndex + itemsPerPage;
            
            const rowsToShow = filteredRows.slice(startIndex, endIndex);
            
            // Mostrar solo las filas de la página actual
            rowsToShow.forEach(row => {
                row.style.display = ''; // table-row por defecto
            });

            // Manejo de mensaje "No hay resultados"
            const noRow = document.getElementById('noReportsRow');
            if (filteredRows.length === 0 && allReportRows.length > 0) {
                // Crear fila temporal si no existe
                if(!noRow) {
                    const tbody = document.getElementById('tableBody');
                    const tr = document.createElement('tr');
                    tr.id = 'noResultsRow';
                    tr.innerHTML = '<td colspan="8" style="text-align: center; padding: 30px; color: #999;">No hay coincidencias con tu búsqueda.</td>';
                    tbody.appendChild(tr);
                }
            } else {
                const tempNoRow = document.getElementById('noResultsRow');
                if(tempNoRow) tempNoRow.remove();
            }
        }

        function renderPaginationControls() {
            const container = document.getElementById('paginationContainer');
            const controls = document.getElementById('paginationControls');
            const info = document.getElementById('pageInfo');
            
            const totalItems = filteredRows.length;
            const totalPages = Math.ceil(totalItems / itemsPerPage);

            if (totalItems === 0) {
                container.style.display = 'none';
                return;
            }
            container.style.display = 'flex';

            // Texto informativo
            const startItem = ((currentPage - 1) * itemsPerPage) + 1;
            const endItem = Math.min(currentPage * itemsPerPage, totalItems);
            info.textContent = `Mostrando ${startItem} - ${endItem} de ${totalItems}`;

            // Botones
            let html = '';

            // Anterior
            html += `<button class="page-btn" onclick="changePage(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}><i class="fas fa-chevron-left"></i></button>`;

            // Lógica de botones numéricos (max 5)
            const maxVisibleButtons = 5;
            let startPage = Math.max(1, currentPage - Math.floor(maxVisibleButtons / 2));
            let endPage = Math.min(totalPages, startPage + maxVisibleButtons - 1);

            if (endPage - startPage + 1 < maxVisibleButtons) {
                startPage = Math.max(1, endPage - maxVisibleButtons + 1);
            }

            if (startPage > 1) {
                html += `<button class="page-btn" onclick="changePage(1)">1</button>`;
                if (startPage > 2) html += `<span style="padding: 0 5px;">...</span>`;
            }

            for (let i = startPage; i <= endPage; i++) {
                const activeClass = i === currentPage ? 'active' : '';
                html += `<button class="page-btn ${activeClass}" onclick="changePage(${i})">${i}</button>`;
            }

            if (endPage < totalPages) {
                if (endPage < totalPages - 1) html += `<span style="padding: 0 5px;">...</span>`;
                html += `<button class="page-btn" onclick="changePage(${totalPages})">${totalPages}</button>`;
            }

            // Siguiente
            html += `<button class="page-btn" onclick="changePage(${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''}><i class="fas fa-chevron-right"></i></button>`;

            controls.innerHTML = html;
        }

        function changePage(newPage) {
            const totalPages = Math.ceil(filteredRows.length / itemsPerPage);
            if (newPage < 1 || newPage > totalPages) return;
            
            currentPage = newPage;
            
            // Ocultar todo antes de renderizar nueva pagina
            filteredRows.forEach(r => r.style.display = 'none');
            
            renderTablePage();
            renderPaginationControls();
        }

        // =============================================
        // FUNCIONES DE EDITOR Y FORMULARIOS
        // =============================================
        function initializeEditor() {
            if (quill === null) {
                quill = new Quill('#editor', {
                    theme: 'snow',
                    modules: { toolbar: [ ['bold', 'italic', 'underline'], [{ 'list': 'ordered'}, { 'list': 'bullet' }], ['clean'] ] }
                });
                quill.on('text-change', function() {
                    const text = quill.getText();
                    const length = text.trim().length;
                    updateContentCounter(length);
                    if (length > MAX_CONTENT_LENGTH) quill.deleteText(MAX_CONTENT_LENGTH, length);
                });
            }
        }

        function updateContentCounter(length) {
            const counter = document.getElementById('contentCounter');
            counter.textContent = `${length} / ${MAX_CONTENT_LENGTH} caracteres`;
            counter.className = 'char-counter ' + (length > MAX_CONTENT_LENGTH * 0.9 ? 'danger' : length > MAX_CONTENT_LENGTH * 0.75 ? 'warning' : '');
        }

        function updateCharCounter(inputId, counterId, maxLength) {
            const input = document.getElementById(inputId);
            const counter = document.getElementById(counterId);
            const length = input.value.length;
            counter.textContent = `${length} / ${maxLength} caracteres`;
            counter.className = 'char-counter ' + (length > maxLength * 0.9 ? 'danger' : length > maxLength * 0.75 ? 'warning' : '');
        }

        function toggleSubMenu(btn, id) {
            document.querySelectorAll('.dropdown-menu').forEach(m => {
                if (m.id !== 'menu-' + id) m.classList.remove('show');
            });
            const menu = document.getElementById('menu-' + id);
            menu.classList.toggle('show');
            
            const closeMenu = (e) => {
                if (!btn.contains(e.target) && !menu.contains(e.target)) {
                    menu.classList.remove('show');
                    document.removeEventListener('click', closeMenu);
                }
            };
            setTimeout(() => document.addEventListener('click', closeMenu), 10);
        }

        function openModal(modalId) { document.getElementById(modalId).classList.add('show'); }
        
// También necesitas modificar la función closeModal para limpiar el estado de edición
function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('show');
    setTimeout(() => {
        document.getElementById('reportForm').reset();
        if (quill) quill.setText('');
        
        // Limpiar ID oculto
        document.getElementById('reportId').value = '';
        
        // Re-habilitar campo de archivo
        const fileInput = document.getElementById('reportFile');
        fileInput.disabled = false;
        fileInput.style.opacity = '1';
        fileInput.style.cursor = 'pointer';
        fileInput.value = '';
        
        // Eliminar nota de edición si existe
        const editNote = document.getElementById('editFileNote');
        if (editNote) editNote.remove();
        
        // Resetear contadores
        document.getElementById('titleCounter').textContent = '0 / 80 caracteres';
        document.getElementById('contentCounter').textContent = '0 / 500 caracteres';
        
        // Resetear título del modal
        document.getElementById('modalTitle').textContent = 'Nuevo Informe';
    }, 300);
}

        function openReportModal() {
            openModal('reportModal');
            document.getElementById('modalTitle').textContent = 'Nuevo Informe';
            document.getElementById('reportId').value = '';
            document.getElementById('reportAuthor').value = '<?php echo htmlspecialchars($usuario_actual); ?>';
            initializeEditor();
            quill.setText('');
            updateContentCounter(0);
        }

        // =============================================
        // SUBIDA Y GUARDADO
        // =============================================
        function showUploadProgress() { document.getElementById('uploadProgressContainer').classList.add('active'); }
        function hideUploadProgress() {
            setTimeout(() => {
                document.getElementById('uploadProgressContainer').classList.remove('active');
                document.getElementById('uploadProgressBar').style.width = '0%';
                document.getElementById('uploadPercentage').textContent = '0%';
                document.getElementById('uploadStatus').textContent = 'Preparando...';
            }, 1000);
        }
        function updateUploadProgress(percentage, status = '') {
            document.getElementById('uploadProgressBar').style.width = percentage + '%';
            document.getElementById('uploadPercentage').textContent = percentage + '%';
            if (status) document.getElementById('uploadStatus').textContent = status;
        }

        function addReportToTable(data, isFile = false) {
            const tableBody = document.getElementById('tableBody');
            
            // Eliminar fila de "no hay informes"
            const noReportsRow = document.getElementById('noReportsRow');
            if (noReportsRow) noReportsRow.remove();
            
            const tipoLower = data.tipo.toLowerCase();
            const periodoLower = data.periodo.toLowerCase();
            // Clases mapeadas desde PHP
            const tipoClass = <?php echo json_encode($tipo_class); ?>;
            const periodoClass = <?php echo json_encode($periodo_class); ?>;

            const newRow = document.createElement('tr');
            newRow.setAttribute('data-report-id', data.id);
            newRow.setAttribute('data-tipo', data.tipoDisplay);
            newRow.setAttribute('data-periodo', data.periodoDisplay);

            const ext = data.url ? data.url.split('.').pop().toLowerCase() : '';
            let fileIcon = '';
            let dropdownLinks = '';

            if (isFile) {
                if (ext === 'pdf') {
                    fileIcon = '<i class="fas fa-file-pdf" style="color: #d32f2f; margin-right:5px;"></i>';
                    dropdownLinks = `<a href="${data.url}" target="_blank" class="dropdown-item"><i class="fas fa-eye"></i> Ver PDF</a>`;
                } else {
                    fileIcon = '<i class="fas fa-file-word" style="color: #2b5797; margin-right:5px;"></i>';
                    dropdownLinks = `<a href="${data.url}" download class="dropdown-item"><i class="fas fa-download"></i> Descargar</a>`;
                }
            } else {
                dropdownLinks = `<a href="#" class="dropdown-item" onclick="viewReport(${data.id})"><i class="fas fa-eye"></i> Ver Contenido</a>
                                 <a href="#" class="dropdown-item" onclick="editReport(${data.id})"><i class="fas fa-edit"></i> Editar</a>`;
            }

            const badgeStatus = data.estado.toLowerCase() === 'publicado' ? 'badge-green' : data.estado.toLowerCase() === 'archivado' ? 'badge-yellow' : 'badge-gray';

const titleLink = isFile 
    ? `<a href="${data.url}" target="_blank" style="color: var(--dark); text-decoration: none; transition: color 0.2s;">${data.titulo}</a>`
    : `<a href="#" onclick="viewReport(${data.id}); return false;" style="color: var(--dark); text-decoration: none; transition: color 0.2s;">${data.titulo}</a>`;

newRow.innerHTML = `
    <td style="font-weight: 600;">${fileIcon} ${titleLink}</td>
                <td style="color: #666; font-size: 13px; font-weight: 400;">${data.resumen}</td>
                <td><span class="badge ${tipoClass[tipoLower] || 'badge-gray'}">${data.tipoDisplay}</span></td>
                <td><span class="badge ${periodoClass[periodoLower] || 'badge-blue'}">${data.periodoDisplay}</span></td>
                <td><span class="badge ${badgeStatus}">${data.estado}</span></td>
                <td>${data.autor}</td>
                <td style="color: var(--text-muted); font-size: 13px;">${data.date}</td>
                <td style="text-align: right; position: relative;">
                    <button class="action-btn" onclick="toggleSubMenu(this, ${data.id})"><i class="fas fa-ellipsis-v"></i></button>
                    <div class="dropdown-menu" id="menu-${data.id}">
                        ${dropdownLinks}
                        <div style="border-top: 1px solid #eee; margin: 5px 0;"></div>
                        <a href="#" class="dropdown-item" style="color: #d32f2f;" onclick="deleteReport(${data.id})"><i class="fas fa-trash-alt"></i> Eliminar</a>
                    </div>
                </td>
            `;

            // Insertar al inicio
            tableBody.insertBefore(newRow, tableBody.firstChild);
            
            // ACTUALIZAR LÓGICA DE PAGINACIÓN
            allReportRows.unshift(newRow); // Agregar a la colección global
            applyFilters(); // Recalcular todo
        }

function saveReport() {
    const reportId = document.getElementById('reportId').value;
    const fileInput = document.getElementById('reportFile');
    const title = document.getElementById('reportTitle').value.trim();
    const tipo = document.getElementById('reportTipo').value;
    const periodo = document.getElementById('reportPeriodo').value;
    const status = document.getElementById('reportStatus').value;
    const author = document.getElementById('reportAuthor').value.trim();
    const content = quill.root.innerHTML;
    const contentText = quill.getText().trim();

    if (!title || !tipo || !periodo || !status || !author) { 
        alert("Completa los campos obligatorios."); 
        return; 
    }
    
    if (contentText.length > MAX_CONTENT_LENGTH) { 
        alert("Contenido demasiado largo."); 
        return; 
    }
    
    const isFileUpload = fileInput.files.length > 0;
    
    // Si es edición y no hay archivo nuevo, debe tener contenido
    if (reportId && !isFileUpload && (!contentText || contentText.length === 0)) { 
        alert("El informe debe tener contenido."); 
        return; 
    }
    
    // Si es nuevo y no hay archivo, debe tener contenido
    if (!reportId && !isFileUpload && (!contentText || contentText.length === 0)) { 
        alert("Escribe contenido o adjunta un archivo."); 
        return; 
    }

    if (isFileUpload) {
        document.getElementById('uploadFileName').textContent = fileInput.files[0].name;
    }

    const formData = new FormData();
    let endpoint = '';

    // MODO EDICIÓN
    if (reportId) {
        formData.append('id', reportId);
        formData.append('title', title);
        formData.append('type', tipo);  // Tu API espera 'type'
        formData.append('status', status);
        formData.append('author', author);
        formData.append('content', content);
        endpoint = '../../api/reports/update.php';
    } 
    // MODO CREACIÓN CON ARCHIVO
    else if (isFileUpload) {
        formData.append('pdfTitle', title);
        formData.append('pdfTipo', tipo);
        formData.append('pdfPeriodo', periodo);
        formData.append('pdfStatus', status);
        formData.append('pdfAuthor', author);
        formData.append('pdfFile', fileInput.files[0]);
        endpoint = '../../api/reports/upload.php';
    } 
    // MODO CREACIÓN SIN ARCHIVO
    else {
        formData.append('title', title);
        formData.append('tipo', tipo);
        formData.append('periodo', periodo);
        formData.append('status', status);
        formData.append('author', author);
        formData.append('content', content);
        endpoint = '../../api/reports/create.php';
    }

            closeModal('reportModal');
            if (isFileUpload) showUploadProgress();

            const xhr = new XMLHttpRequest();
            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    const percentComplete = Math.round((e.loaded / e.total) * 100);
                    updateUploadProgress(percentComplete, 'Subiendo archivo...');
                }
            });

xhr.addEventListener('load', function() {
        if (xhr.status === 200) {
            try {
                const response = JSON.parse(xhr.responseText);
                if (response.success) {
                    if (reportId) {
                        // MODO EDICIÓN: Actualizar fila existente
                        updateReportInTable(reportId, {
                            titulo: title,
                            tipo: tipo,
                            periodo: periodo,
                            estado: status,
                            autor: author,
                            contenido: content
                        });
                        alert('Informe actualizado correctamente.');
                    } else {
                        // MODO CREACIÓN: Agregar nueva fila
                        updateUploadProgress(100, 'Completado');
                        const tempDiv = document.createElement('div');
                        tempDiv.innerHTML = content;
                        const plainText = tempDiv.textContent || '';
                        const reportData = {
                            id: response.id,
                            titulo: title,
                            tipo: tipo,
                            tipoDisplay: tipo === 'Comunicacion' ? 'Comunicación' : tipo,
                            periodo: periodo,
                            periodoDisplay: periodo,
                            estado: status,
                            autor: author,
                            date: response.date || '<?php echo date('d/M/Y'); ?>',
                            url: response.url || '',
                            resumen: plainText.length > 120 ? plainText.substring(0, 120) + '...' : plainText
                        };
                        addReportToTable(reportData, isFileUpload);
                        hideUploadProgress();
                        alert(isFileUpload ? 'Archivo subido.' : 'Informe creado.');
                    }
                } else {
                    hideUploadProgress();
                    alert('Error: ' + response.message);
                }
            } catch(e) { 
                hideUploadProgress(); 
                alert('Error en respuesta del servidor.'); 
            }
        } else {
            hideUploadProgress();
            alert('Error de conexión.');
        }
    });
            xhr.open('POST', endpoint);
            xhr.send(formData);
        }
        
        function updateReportInTable(id, data) {
    const row = document.querySelector(`tr[data-report-id="${id}"]`);
    if (!row) return;
    
    // Extraer texto plano del contenido HTML
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = data.contenido;
    const plainText = tempDiv.textContent || '';
    const resumen = plainText.length > 120 ? plainText.substring(0, 120) + '...' : plainText;
    
    // Mapeos de clases
    const tipoClass = <?php echo json_encode($tipo_class); ?>;
    const periodoClass = <?php echo json_encode($periodo_class); ?>;
    const estadoClass = {
        'borrador': 'badge-gray',
        'publicado': 'badge-green',
        'archivado': 'badge-yellow'
    };
    
    const tipoLower = data.tipo.toLowerCase();
    const periodoLower = data.periodo.toLowerCase();
    const estadoLower = data.estado.toLowerCase();
    const tipoDisplay = data.tipo === 'Comunicacion' ? 'Comunicación' : data.tipo;
    
    // Actualizar atributos de la fila
    row.setAttribute('data-tipo', tipoDisplay);
    row.setAttribute('data-periodo', data.periodo);
    
    // Actualizar celdas
    const cells = row.cells;
    cells[0].innerHTML = `<a href="#" onclick="viewReport(${id}); return false;" style="color: var(--dark); text-decoration: none; transition: color 0.2s;">${data.titulo}</a>`;
    cells[1].innerHTML = `<span style="color: #666; font-size: 13px; font-weight: 400;">${resumen}</span>`;
    cells[2].innerHTML = `<span class="badge ${tipoClass[tipoLower] || 'badge-gray'}">${tipoDisplay}</span>`;
    cells[3].innerHTML = `<span class="badge ${periodoClass[periodoLower] || 'badge-blue'}">${data.periodo}</span>`;
    cells[4].innerHTML = `<span class="badge ${estadoClass[estadoLower] || 'badge-gray'}">${data.estado}</span>`;
    cells[5].textContent = data.autor;
    
    // Re-aplicar filtros para mantener consistencia
    applyFilters();
}

        function deleteReport(id) {
            if (!confirm("¿Eliminar este informe?")) return;
            $.ajax({
                url: '../../api/reports/delete.php',
                type: 'POST',
                data: { id: id },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const row = document.querySelector(`tr[data-report-id="${id}"]`);
                        if(row) {
                            row.remove();
                            // Actualizar array global y paginación
                            allReportRows = allReportRows.filter(r => r.getAttribute('data-report-id') != id);
                            applyFilters();
                            alert('Informe eliminado.');
                        }
                    } else { alert('Error: ' + response.message); }
                }
            });
        }

function viewReport(id) {
    $.ajax({
        url: '../../api/reports/get.php',
        type: 'GET',
        data: { id: id },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const report = response.report;
                
                // Mapeo de clases de badges
                const tipoClass = {
                    'comunicacion': 'badge-pink',
                    'avance': 'badge-green',
                    'territorio': 'badge-teal',
                    'legal': 'badge-yellow',
                    'financiero': 'badge-gray'
                };
                
                const estadoClass = {
                    'borrador': 'badge-gray',
                    'publicado': 'badge-green',
                    'archivado': 'badge-yellow'
                };
                
                const tipoLower = report.tipo.toLowerCase();
                const estadoLower = report.estado.toLowerCase();
                
                // Crear modal de vista previa
                const viewModal = `
                    <div class="modal show" id="viewReportModal" style="display: flex;">
                        <div class="modal-content" style="max-width: 900px;">
                            <div class="modal-header">
                                <div>
                                    <h2 style="margin-bottom: 10px;">${report.titulo}</h2>
                                    <div style="display: flex; gap: 10px; align-items: center;">
                                        <span class="badge ${tipoClass[tipoLower] || 'badge-gray'}">${report.tipo}</span>
                                        <span class="badge ${estadoClass[estadoLower] || 'badge-gray'}">${report.estado}</span>
                                    </div>
                                </div>
                                <button type="button" class="close-modal" onclick="closeViewModal()">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <div class="modal-body">
                                <div style="margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid var(--gray-200);">
                                    <div style="display: flex; justify-content: space-between; font-size: 13px; color: var(--text-muted);">
                                        <span><strong>Autor:</strong> ${report.creado_por}</span>
                                    </div>
                                </div>
                                <div style="line-height: 1.8; color: var(--dark); padding: 20px; background: var(--gray-100); border-radius: 10px;">
                                    ${report.contenido}
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn-action" onclick="closeViewModal()">Cerrar</button>
                                <button type="button" class="btn-primary-action" onclick="closeViewModal(); editReport(${id});">
                                    <i class="fas fa-edit"></i> Editar
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                
                // Agregar modal al body
                document.body.insertAdjacentHTML('beforeend', viewModal);
                
            } else if (response.is_pdf) {
                alert('Este informe es un archivo PDF. Usa el menú de acciones para verlo o descargarlo.');
            } else {
                alert('Error al cargar el informe: ' + response.message);
            }
        },
        error: function() {
            alert('Error de conexión al cargar el informe.');
        }
    });
}

function closeViewModal() {
    const modal = document.getElementById('viewReportModal');
    if(modal) {
        modal.classList.remove('show');
        setTimeout(() => modal.remove(), 300);
    }
}
       function editReport(id) {
    $.ajax({
        url: '../../api/reports/get.php',
        type: 'GET',
        data: { id: id },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const report = response.report;
                
                // Verificar si es un archivo PDF
                if (report.archivo_url && report.archivo_url.trim() !== '') {
                    alert('No se pueden editar informes con archivos adjuntos (PDF/DOCX). Solo puedes ver o eliminar este tipo de informes.');
                    return;
                }
                
                // Abrir modal en modo edición
                openModal('reportModal');
                
                // Cambiar título del modal
                document.getElementById('modalTitle').textContent = 'Editar Informe';
                
                // Llenar campos del formulario
                document.getElementById('reportId').value = report.id;
                document.getElementById('reportTitle').value = report.titulo;
                document.getElementById('reportTipo').value = report.tipo;
                document.getElementById('reportPeriodo').value = report.periodo || 'Mensual';
                document.getElementById('reportStatus').value = report.estado;
                document.getElementById('reportAuthor').value = report.creado_por;
                
                // Inicializar editor y llenar contenido
                initializeEditor();
                
                // Cargar contenido HTML en el editor
                if (report.contenido) {
                    quill.root.innerHTML = report.contenido;
                    const textLength = quill.getText().trim().length;
                    updateContentCounter(textLength);
                }
                
                // Actualizar contador de título
                updateCharCounter('reportTitle', 'titleCounter', 80);
                
                // Deshabilitar campo de archivo en edición
                const fileInput = document.getElementById('reportFile');
                fileInput.disabled = true;
                fileInput.style.opacity = '0.5';
                fileInput.style.cursor = 'not-allowed';
                
                // Agregar nota sobre archivo
                const fileGroup = fileInput.closest('.form-group');
                let editNote = document.getElementById('editFileNote');
                if (!editNote) {
                    editNote = document.createElement('div');
                    editNote.id = 'editFileNote';
                    editNote.style.cssText = 'font-size: 11px; color: #ef6c00; margin-top: 5px; font-weight: 600;';
                    editNote.innerHTML = '<i class="fas fa-info-circle"></i> No se puede modificar el archivo en modo edición.';
                    fileGroup.appendChild(editNote);
                }
                
            } else {
                alert('Error al cargar el informe: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error AJAX:', status, error);
            alert('Error de conexión al cargar el informe para editar.');
        }
    });
}
    </script>
</body>
</html>