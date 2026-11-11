<?php
// 1. Carga del sistema
require_once __DIR__ . '/../../includes/bootstrap.php';

// Verificar permisos
$rol = $_SESSION['user_role'] ?? '';
$permitidos = ['admin', 'administrador', 'superadmin', 'super admin', 'circunscripcion'];

if (!in_array($rol, $permitidos)) {
    header('Location: /public/index.php'); 
    exit();
}

// 2. OBTENER DATOS Y LÓGICA DE CARPETAS
$db = Database::getInstance()->getConnection();
$documentos = [];
$carpetas = [];
$all_folders_list = []; 
$current_folder_id = isset($_GET['carpeta']) && is_numeric($_GET['carpeta']) ? (int)$_GET['carpeta'] : null;
$current_folder_info = null;

$stats = ['total' => 0, 'total_pdf' => 0, 'total_imagenes' => 0, 'total_descargas' => 0];

try {
    // A. Info Carpeta Actual
    if ($current_folder_id) {
        $stmt = $db->prepare("SELECT * FROM carpetas WHERE id = ?");
        $stmt->execute([$current_folder_id]);
        $current_folder_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$current_folder_info) {
            header('Location: biblioteca.php');
            exit;
        }
    }

    // B. Obtener Carpetas Hijas
    $sql_carpetas = "SELECT * FROM carpetas WHERE carpeta_padre_id " . ($current_folder_id ? "= ?" : "IS NULL") . " ORDER BY nombre ASC";
    $stmt = $db->prepare($sql_carpetas);
    $current_folder_id ? $stmt->execute([$current_folder_id]) : $stmt->execute();
    $carpetas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // C. Obtener Documentos
    $sql_docs = "SELECT * FROM documentos WHERE carpeta_id " . ($current_folder_id ? "= ?" : "IS NULL") . " AND activo = 1 ORDER BY fecha_subida DESC";
    $stmt = $db->prepare($sql_docs);
    $current_folder_id ? $stmt->execute([$current_folder_id]) : $stmt->execute();
    $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // D. Lista completa para "Mover a"
    $stmt = $db->query("SELECT id, nombre FROM carpetas ORDER BY nombre ASC");
    $all_folders_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // E. Stats Globales
    $stmt = $db->query("SELECT 
        COUNT(*) as total,
        COALESCE(SUM(CASE WHEN tipo_archivo = 'pdf' OR tipo_archivo = 'PDF' THEN 1 ELSE 0 END), 0) as total_pdf,
        COALESCE(SUM(CASE WHEN tipo_archivo IN ('jpg', 'JPG', 'jpeg', 'JPEG', 'png', 'PNG') THEN 1 ELSE 0 END), 0) as total_imagenes,
        COALESCE(SUM(descargas), 0) as total_descargas 
        FROM documentos WHERE activo = 1");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error DB: " . $e->getMessage());
}

// Helpers
function getFileIcon($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    switch($ext) {
        case 'pdf': return 'fa-file-pdf';
        case 'doc': case 'docx': return 'fa-file-word';
        case 'xls': case 'xlsx': return 'fa-file-excel';
        case 'png': case 'jpg': case 'jpeg': return 'fa-image';
        case 'ppt': case 'pptx': return 'fa-file-powerpoint';
        default: return 'fa-file-alt';
    }
}

function getFileColorClass($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    switch($ext) {
        case 'pdf': return 'pdf';
        case 'doc': case 'docx': return 'word';
        case 'xls': case 'xlsx': return 'excel';
        case 'ppt': case 'pptx': return 'powerpoint';
        case 'png': case 'jpg': case 'jpeg': return 'image';
        default: return 'default';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Biblioteca - Circunscripción</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    <style>
        /* --- VARIABLES GLOBALES --- */
        :root { --primary: #2d7a3e; --primary-dark: #1f5a2d; --dark: #1a1a1a; --light: #f0f7f0; --white: #ffffff; --gray-100: #f8f9fa; --gray-200: #e9ecef; --text-muted: #6c757d; --shadow-sm: 0 10px 30px rgba(0,0,0,0.05); --shadow-hover: 0 20px 40px rgba(45, 122, 62, 0.15); }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; background: var(--light); color: var(--dark); overflow-x: hidden; }

        /* --- LAYOUT --- */
        .container { max-width: 100%; margin: 0; background: var(--white); min-height: 100vh; position: relative; padding-left: 330px; padding-right: 50px; padding-bottom: 40px; transition: padding-left 0.3s ease; }
        .design-text { position: fixed; left: 290px; top: 50%; writing-mode: vertical-rl; font-size: 13px; font-weight: 500; color: var(--primary); opacity: 0.3; letter-spacing: 2px; z-index: 0; pointer-events: none; }

        /* --- HEADER --- */
        .page-header { padding: 50px 0 30px 0; position: relative; z-index: 2; display: flex; justify-content: space-between; align-items: flex-end; }
        .ai-badge { color: var(--primary); font-size: 13px; font-weight: 800; letter-spacing: 4px; margin-bottom: 15px; display: inline-block; text-transform: uppercase; background: rgba(45,122,62,0.1); padding: 5px 15px; border-radius: 20px; }
        .main-title { font-size: 54px; font-weight: 900; line-height: 1; margin-bottom: 15px; color: var(--dark); }
        .description { color: #666; font-size: 16px; line-height: 1.6; max-width: 600px; }

        .header-actions { display: flex; gap: 10px; }
        .btn-primary-action { background: var(--primary); color: white; border: none; padding: 12px 25px; border-radius: 12px; font-weight: 700; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; gap: 10px; font-size: 14px; box-shadow: 0 4px 15px rgba(45, 122, 62, 0.3); text-decoration: none; }
        .btn-primary-action:hover { background: var(--primary-dark); transform: translateY(-2px); }
        .btn-secondary-action { background: var(--dark); color: white; border: none; padding: 12px 25px; border-radius: 12px; font-weight: 700; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; gap: 10px; font-size: 14px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2); }
        .btn-secondary-action:hover { background: #333; transform: translateY(-2px); }

        /* --- STATS --- */
        .grid-overview { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: var(--white); border: 1px solid var(--gray-200); padding: 25px; border-radius: 20px; text-align: left; box-shadow: var(--shadow-sm); position: relative; overflow: hidden; display: flex; flex-direction: column; justify-content: space-between; height: 140px; }
        .stat-card:hover { transform: translateY(-5px); border-color: var(--primary); }
        .stat-card i { position: absolute; right: 20px; top: 20px; font-size: 40px; color: var(--gray-100); }
        .stat-card .number { font-size: 42px; font-weight: 900; color: var(--dark); line-height: 1; }
        .stat-card .label { font-size: 12px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.5px; margin-top: 5px; }

        /* --- BREADCRUMBS --- */
        .breadcrumbs { background: transparent; padding: 0 0 20px 0; display: flex; align-items: center; gap: 10px; font-size: 14px; font-weight: 600; color: var(--text-muted); }
        .breadcrumbs a { text-decoration: none; color: var(--primary); transition: color 0.2s; }
        .breadcrumbs a:hover { color: var(--primary-dark); text-decoration: underline; }
        .breadcrumbs i { font-size: 12px; }
        .breadcrumbs .current { color: var(--dark); }
        .breadcrumbs .separator { color: #ccc; }

        /* --- FILTROS --- */
        .filters-bar { background: var(--white); border: 1px solid var(--gray-200); border-radius: 16px; padding: 20px; margin-bottom: 30px; display: flex; gap: 20px; align-items: flex-end; box-shadow: var(--shadow-sm); z-index: 5; position: relative; }
        
        .form-group { display: flex; flex-direction: column; gap: 8px; flex: 1; margin-bottom: 20px !important; width: 100%; }
        .form-label { font-size: 12px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.5px; margin-bottom: 5px; display: block; }
        .form-input-styled { width: 100%; padding: 12px 15px; border-radius: 10px; border: 2px solid var(--gray-200); background: var(--gray-100); font-size: 14px; font-weight: 600; color: var(--dark); transition: all 0.3s; outline: none; }
        .form-input-styled:focus { border-color: var(--primary); background: var(--white); }

        /* --- GRID DOCUMENTOS --- */
        .documents-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); gap: 20px; }
        
        .document-card { background: white; border-radius: 16px; overflow: hidden; box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); transition: all 0.4s; opacity: 0; animation: fadeInUp 0.6s ease forwards; position: relative; display: flex; flex-direction: column; }
        .document-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-hover); border-color: var(--primary); }
        
        /* Estilos Carpetas */
        .folder-card { border-color: #e0e7ff; }
        .folder-card:hover { border-color: #6366f1; box-shadow: 0 10px 20px rgba(99, 102, 241, 0.15); }
        
        .folder-preview { height: 100px; background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%); display: flex; align-items: center; justify-content: center; position: relative; }
        .folder-preview i { font-size: 45px; color: #6366f1; }

        .document-preview { height: 100px; background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 100%); display: flex; align-items: center; justify-content: center; position: relative; }
        .document-preview.pdf { background: linear-gradient(135deg, #d32f2f 0%, #ff6b6b 100%); }
        .document-preview.word { background: linear-gradient(135deg, #1976d2 0%, #42a5f5 100%); }
        .document-preview.excel { background: linear-gradient(135deg, #388e3c 0%, #66bb6a 100%); }
        .document-preview.powerpoint { background: linear-gradient(135deg, #d84315 0%, #ff7043 100%); }
        .document-preview.image { background: linear-gradient(135deg, #fbc02d 0%, #ffeb3b 100%); }
        
        .preview-icon { font-size: 40px; color: white; opacity: 0.9; }
        .document-type-badge { position: absolute; top: 10px; right: 10px; background: rgba(255, 255, 255, 0.2); backdrop-filter: blur(8px); color: white; padding: 4px 8px; border-radius: 20px; font-size: 10px; font-weight: 700; text-transform: uppercase; }

        .document-info { padding: 15px; flex-grow: 1; display: flex; flex-direction: column; }
        .document-title { font-size: 14px; font-weight: 800; color: var(--dark); margin-bottom: 6px; min-height: 34px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.2; }
        .document-desc { font-size: 12px; color: #666; margin-bottom: 10px; line-height: 1.3; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis; min-height: 0;}
        .document-meta { display: flex; align-items: center; gap: 10px; color: #999; font-size: 11px; margin-bottom: 10px; margin-top: auto; }
        .document-meta i { color: var(--primary); }
        .badge-cat { background: var(--gray-100); color: var(--text-muted); padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 700; }

        .document-actions { display: flex; gap: 8px; margin-top: 10px; align-items: center; }
        .btn-download { flex: 1; background: var(--dark); color: white; border: none; padding: 8px 12px; border-radius: 10px; font-size: 12px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 5px; transition: all 0.3s; text-decoration: none; }
        .btn-download:hover { background: var(--primary); }

        /* --- DROPDOWN 3 PUNTOS --- */
        .action-cell { position: relative; }
        .action-btn { background: var(--gray-100); border: none; cursor: pointer; color: var(--text-muted); font-size: 14px; width: 32px; height: 32px; border-radius: 8px; transition: all 0.2s; display: flex; align-items: center; justify-content: center; }
        .action-btn:hover, .action-btn.active { background: var(--gray-200); color: var(--primary); }
        
        .dropdown-menu { display: none; position: absolute; right: 0; bottom: 100%; margin-bottom: 5px; width: 160px; background: var(--white); border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); border: 1px solid var(--gray-200); z-index: 100; padding: 6px 0; opacity: 0; visibility: hidden; transform: translateY(10px); transition: all 0.25s ease; }
        .dropdown-menu.show { opacity: 1; visibility: visible; transform: translateY(0); display: block; }
        .dropdown-item { display: flex; align-items: center; gap: 10px; padding: 8px 15px; width: 100%; border: none; background: none; text-align: left; cursor: pointer; font-size: 12px; font-weight: 600; color: var(--dark); transition: background 0.2s; }
        .dropdown-item:hover { background: var(--gray-100); color: var(--primary); }
        .dropdown-item.delete { color: #d32f2f; }
        .dropdown-item.delete:hover { background: #ffebee; }

        /* --- MODAL OPTIMIZADO (VISUAL FIXED) --- */
        .modal { 
            display: none; 
            position: fixed; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%; 
            background: rgba(255, 255, 255, 0.9); 
            backdrop-filter: blur(5px); 
            z-index: 2000; 
            align-items: center; 
            justify-content: center; 
            opacity: 0; 
            transition: opacity 0.3s ease; 
        }
        
        .modal.open { 
            display: flex; 
            opacity: 1; 
        }

        .modal-content { 
            background: var(--white); 
            border-radius: 20px; 
            border: 1px solid var(--gray-200); 
            box-shadow: 0 25px 50px rgba(0,0,0,0.1); 
            width: 95%; 
            max-width: 550px; 
            max-height: 85vh; 
            overflow-y: auto;
            padding: 30px; 
            position: relative; 
            transform: translateY(30px); 
            opacity: 0; 
            transition: all 0.4s ease-out; 
            display: flex;
            flex-direction: column;
        }

        .modal-content::-webkit-scrollbar { width: 8px; }
        .modal-content::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 4px; }
        .modal-content::-webkit-scrollbar-thumb { background: #ccc; border-radius: 4px; }
        .modal-content::-webkit-scrollbar-thumb:hover { background: #aaa; }

        .modal.open .modal-content { transform: translateY(0); opacity: 1; }
        
        .close-modal { position: absolute; top: 15px; right: 20px; font-size: 20px; cursor: pointer; color: #ccc; z-index: 10; }
        .close-modal:hover { color: var(--dark); }

        #editor-container { border: 2px solid var(--gray-200); border-radius: 10px; height: 120px; background: white; margin-bottom: 5px; }
        .ql-toolbar { background: var(--gray-100); border: none !important; border-bottom: 1px solid var(--gray-200) !important; }
        .ql-container { border: none !important; font-size: 14px; }
        
        .file-upload-area { border: 2px dashed var(--gray-200); border-radius: 15px; padding: 20px; text-align: center; cursor: pointer; transition: all 0.3s; background: var(--gray-100); }
        .file-upload-area:hover { border-color: var(--primary); background: #f0f7f0; }
        .file-upload-area i { font-size: 32px; color: var(--primary); margin-bottom: 10px; }

        .animate-up { animation: fadeInUp 0.6s ease forwards; opacity: 0; }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .animate-delay-1 { animation-delay: 0.1s; }
        .animate-delay-2 { animation-delay: 0.2s; }

        @media (max-width: 1024px) {
            .container { padding-left: 20px; padding-right: 20px; }
            .grid-overview { grid-template-columns: repeat(2, 1fr); }
            .filters-bar { flex-direction: column; align-items: stretch; }
            .page-header { flex-direction: column; align-items: flex-start; gap: 20px; }
            .design-text { display: none; }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/components/sidebar_circunscripcion.php'; ?>
    <div class="design-text">BIBLIOTECA 2025</div>

    <div class="container" id="mainContent">
        
        <div class="page-header animate-up">
            <div>
                <div class="ai-badge">RECURSOS</div>
                <h1 class="main-title">Biblioteca de<br>Documentos.</h1>
                <p class="description">Repositorio centralizado. Organiza tus archivos en carpetas.</p>
            </div>
            <div class="header-actions">
                <button class="btn-secondary-action" onclick="openFolderModal()">
                    <i class="fas fa-folder-plus"></i> Nueva Carpeta
                </button>
                <button class="btn-primary-action" onclick="openCreateModal()">
                    <i class="fas fa-cloud-upload-alt"></i> Subir Documento
                </button>
            </div>
        </div>
        
        <?php if (!$current_folder_id): ?>
        
        <div class="grid-overview animate-up animate-delay-1">
            <div class="stat-card">
                <i class="fas fa-folder-open"></i>
                <span class="number" data-target="<?php echo $stats['total']; ?>">0</span>
                <span class="label">Total Archivos</span>
            </div>
            <div class="stat-card">
                <i class="fas fa-file-pdf"></i>
                <span class="number" data-target="<?php echo $stats['total_pdf']; ?>">0</span>
                <span class="label">Documentos PDF</span>
            </div>
            <div class="stat-card">
                <i class="fas fa-image"></i>
                <span class="number" data-target="<?php echo $stats['total_imagenes']; ?>">0</span>
                <span class="label">Imágenes/Flyers</span>
            </div>
            <div class="stat-card">
                <i class="fas fa-download"></i>
                <span class="number" data-target="<?php echo $stats['total_descargas']; ?>">0</span>
                <span class="label">Descargas Totales</span>
            </div>
        </div>
        
        <?php endif; ?>

        <div class="breadcrumbs animate-up animate-delay-2">
            <a href="biblioteca.php"><i class="fas fa-home"></i> Inicio</a>
            <?php if ($current_folder_info): ?>
                <span class="separator"><i class="fas fa-chevron-right"></i></span>
                <span class="current"><?php echo htmlspecialchars($current_folder_info['nombre']); ?></span>
            <?php endif; ?>
        </div>

        <div class="filters-bar animate-up animate-delay-2">
            <div class="form-group" style="flex: 2;">
                <label class="form-label">Buscar</label>
                <div style="position: relative;">
                    <i class="fas fa-search" style="position: absolute; left: 15px; top: 14px; color: #999;"></i>
                    <input type="text" id="searchInput" class="form-input-styled" style="padding-left: 40px;" placeholder="Buscar archivo o carpeta..." onkeyup="filterDocs()">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Filtrar Categoría</label>
                <select class="form-input-styled" id="categoryFilter" onchange="filterDocs()">
                    <option value="todos">Todas</option>
                    <option value="manual">Manuales</option>
                    <option value="flyer">Flyers</option>
                    <option value="reporte">Reportes</option>
                    <option value="presentacion">Presentaciones</option>
                    <option value="formulario">Formularios</option>
                </select>
            </div>
        </div>

        <div class="documents-grid" id="documentsGrid">
            
            <?php if (empty($documentos) && empty($carpetas)): ?>
                <div class="empty-state" style="grid-column: 1/-1; text-align: center; padding: 50px; color: #999;">
                    <i class="fas fa-folder-open" style="font-size: 40px; margin-bottom: 10px;"></i>
                    <p>Esta carpeta está vacía.</p>
                </div>
            <?php endif; ?>

            <?php foreach ($carpetas as $folder): ?>
                <div class="document-card folder-card" data-category="todos" onclick="window.location.href='?carpeta=<?php echo $folder['id']; ?>'" style="cursor: pointer;">
                    <div class="folder-preview">
                        <i class="fas fa-folder"></i>
                    </div>
                    <div class="document-info">
                        <h3 class="document-title"><?php echo htmlspecialchars($folder['nombre']); ?></h3>
                        <div class="document-meta">
                            <span><i class="fas fa-clock"></i> <?php echo date('d/m/Y', strtotime($folder['fecha_creacion'])); ?></span>
                        </div>
                        <?php
                        $roles_acceso = json_decode($folder['roles_acceso'] ?? '[]', true) ?: [];
                        $roles_labels = [
                            'estatal'   => ['label' => 'Estatal',   'color' => '#2d7a3e', 'bg' => '#e8f5e9'],
                            'regional'  => ['label' => 'Regional',  'color' => '#1565c0', 'bg' => '#e3f2fd'],
                            'zona'      => ['label' => 'Zona',      'color' => '#f57f17', 'bg' => '#fff8e1'],
                        ];
                        if (!empty($roles_acceso)): ?>
                        <div style="display:flex;flex-wrap:wrap;gap:4px;margin-top:8px;">
                            <?php foreach ($roles_acceso as $rol_key):
                                $rl = $roles_labels[$rol_key] ?? null; if (!$rl) continue; ?>
                            <span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px;background:<?php echo $rl['bg']; ?>;color:<?php echo $rl['color']; ?>;border:1px solid <?php echo $rl['color']; ?>30;">
                                <?php echo $rl['label']; ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div style="font-size:10px;color:#bbb;margin-top:6px;"><i class="fas fa-lock-open" style="margin-right:3px;"></i>Sin restricción de acceso</div>
                        <?php endif; ?>
                        
                        <div class="action-cell" style="align-self: flex-end;">
                            <button class="action-btn" onclick="toggleDropdown(this)">
                                <i class="fas fa-ellipsis-h"></i>
                            </button>
                            <div class="dropdown-menu">
                                <button class="dropdown-item" onclick='event.stopPropagation(); openRenameFolderModal(<?php echo json_encode(["id"=>$folder["id"], "nombre"=>$folder["nombre"], "roles_acceso"=>json_decode($folder["roles_acceso"] ?? "[]", true) ?: []]); ?>)'>
                                    <i class="fas fa-edit"></i> Cambiar Nombre
                                </button>

                                <button class="dropdown-item delete" onclick="event.stopPropagation(); deleteFolder(<?php echo $folder['id']; ?>)">
                                    <i class="fas fa-trash"></i> Eliminar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php foreach ($documentos as $doc): ?>
                <div class="document-card file-card" data-category="<?php echo htmlspecialchars($doc['categoria']); ?>">
                    <div class="document-preview <?php echo getFileColorClass($doc['nombre_archivo'] ?? $doc['ruta_archivo'] ?? ''); ?>">
                        <i class="fas <?php echo getFileIcon($doc['nombre_archivo'] ?? $doc['ruta_archivo'] ?? ''); ?> preview-icon"></i>
                        <span class="document-type-badge">
                            <?php echo strtoupper($doc['tipo_archivo'] ?? ''); ?>
                        </span>
                    </div>
                    <div class="document-info">
                        <h3 class="document-title"><?php echo htmlspecialchars($doc['titulo']); ?></h3>
                        
                        <?php if (!empty($doc['descripcion'])): ?>
                            <div class="document-desc">
                                <?php echo htmlspecialchars(strip_tags(html_entity_decode($doc['descripcion']))); ?>
                            </div>
                        <?php endif; ?>

                        <div class="document-meta">
                            <span><i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($doc['fecha_subida'])); ?></span>
                            <span><i class="fas fa-download"></i> <?php echo $doc['descargas']; ?></span>
                        </div>
                        
                        <div class="document-actions">
                            <a href="../../api/documents/download.php?id=<?php echo $doc['id']; ?>" class="btn-download" target="_blank">
                                <i class="fas fa-download"></i> Descargar
                            </a>
                            
                            <div class="action-cell">
                                <button class="action-btn" onclick="toggleDropdown(this)">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <div class="dropdown-menu">
                                    <button class="dropdown-item" onclick='openEditModal(<?php echo json_encode([
                                        "id" => $doc["id"],
                                        "titulo" => $doc["titulo"],
                                        "categoria" => $doc["categoria"],
                                        "descripcion" => $doc["descripcion"] ?? "",
                                        "carpeta_id" => $doc["carpeta_id"] ?? ""
                                    ]); ?>)'>
                                        <i class="fas fa-edit"></i> Editar
                                    </button>
                                    
                                    <button class="dropdown-item delete" onclick="deleteDocument(<?php echo $doc['id']; ?>)">
                                        <i class="fas fa-trash"></i> Eliminar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="modal" id="documentModal">
        <div class="modal-content">
            <div class="close-modal" onclick="closeModal()"><i class="fas fa-times"></i></div>
            
            <h2 id="modalTitle" style="font-size: 24px; font-weight: 800; margin-bottom: 5px; color: var(--dark);">Subir Documento</h2>
            <p id="modalSubtitle" style="font-size: 14px; color: #999; margin-bottom: 25px;">Sube archivos PDF, Word, Excel, PPT o Imágenes.</p>
            
            <form id="documentForm" enctype="multipart/form-data">
                <input type="hidden" id="editId" name="id" value="">
                <input type="hidden" id="createCarpetaId" name="carpeta_id_hidden" value="<?php echo $current_folder_id ?? ''; ?>">

<div class="form-group">
    <label class="form-label">Título del Documento</label>
    <input type="text" name="titulo" id="inputTitulo" class="form-input-styled" placeholder="Ej: Manual de Capacitación 2024" maxlength="100">
    <div style="text-align: right; font-size: 11px; color: #999; margin-top: 5px;">
        <span id="titleCharCount">0</span>/100 caracteres
    </div>
</div>
                
                <div class="form-group">
                    <label class="form-label">Categoría</label>
                    <select name="categoria" id="inputCategoria" class="form-input-styled">
                        <option value="general">General</option>
                        <option value="manual">Manual</option>
                        <option value="flyer">Flyer</option>
                        <option value="reporte">Reporte</option>
                        <option value="presentacion">Presentación</option>
                        <option value="formulario">Formulario</option>
                        <option value="otro">Otro</option>
                    </select>
                </div>

                <div class="form-group" id="folderSelectGroup" style="display:none;">
                    <label class="form-label">Ubicación (Carpeta)</label>
                    <select id="inputCarpeta" class="form-input-styled">
                        <option value="">/ (Raíz)</option>
                        <?php foreach ($all_folders_list as $f): ?>
                            <option value="<?php echo $f['id']; ?>">
                                / <?php echo htmlspecialchars($f['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
    <label class="form-label">Descripción (Opcional)</label>
    <div id="editor-container"></div>
    <div style="text-align: right; font-size: 11px; color: #999; margin-top: 5px;">
        <span id="charCount">0</span>/300 caracteres
    </div>
    <input type="hidden" id="docDescription" name="descripcion">
</div>

                <div class="form-group">
                    <label class="form-label">Archivo</label>
                    <label class="file-upload-area" for="fileInput" id="dropZone">
                        <div id="uploadContent">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p><strong>Click para seleccionar</strong> o arrastra aquí</p>
                        </div>
                    </label>
                    <input type="file" id="fileInput" name="documento" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.png,.jpg,.jpeg" style="display: none;">
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

                <button type="button" class="btn-primary-action" id="btnSave" onclick="saveDocument()" style="width: 100%; justify-content: center; margin-top: 10px;">
                    <i class="fas fa-save"></i> Guardar Documento
                </button>
            </form>
        </div>
    </div>

    <div class="modal" id="folderModal">
        <div class="modal-content" style="max-width: 400px; max-height: 90vh;">
            <div class="close-modal" onclick="closeFolderModal()"><i class="fas fa-times"></i></div>
            <h2 style="margin-bottom: 5px; font-weight: 800; color: var(--dark);">Nueva Carpeta</h2>
            <p style="font-size: 14px; color: #999; margin-bottom: 20px;">Crea una colección para organizar archivos.</p>
            
            <form id="folderForm">
                <input type="hidden" name="padre_id" value="<?php echo $current_folder_id ?? ''; ?>">
                <div class="form-group">
                    <label class="form-label">Nombre de la Carpeta</label>
                    <input type="text" name="nombre_carpeta" class="form-input-styled" required placeholder="Ej: Finanzas 2024" autofocus>
                </div>
                <div class="form-group" style="margin-top:16px;">
                    <label class="form-label" style="margin-bottom:8px;display:block;">¿Quién puede acceder?</label>
                    <div style="display:flex;flex-direction:column;gap:10px;">
                        <label style="display:flex;align-items:center;gap:10px;font-size:14px;font-weight:600;cursor:pointer;">
                            <input type="checkbox" name="roles_acceso[]" value="estatal" style="width:16px;height:16px;accent-color:#2d7a3e;">
                            <span style="display:flex;align-items:center;gap:6px;">
                                <span style="width:10px;height:10px;border-radius:50%;background:#2d7a3e;display:inline-block;"></span>
                                Coordinador Estatal
                            </span>
                        </label>
                        <label style="display:flex;align-items:center;gap:10px;font-size:14px;font-weight:600;cursor:pointer;">
                            <input type="checkbox" name="roles_acceso[]" value="regional" style="width:16px;height:16px;accent-color:#1565c0;">
                            <span style="display:flex;align-items:center;gap:6px;">
                                <span style="width:10px;height:10px;border-radius:50%;background:#1565c0;display:inline-block;"></span>
                                Coordinador Regional
                            </span>
                        </label>
                        <label style="display:flex;align-items:center;gap:10px;font-size:14px;font-weight:600;cursor:pointer;">
                            <input type="checkbox" name="roles_acceso[]" value="zona" style="width:16px;height:16px;accent-color:#f57f17;">
                            <span style="display:flex;align-items:center;gap:6px;">
                                <span style="width:10px;height:10px;border-radius:50%;background:#f57f17;display:inline-block;"></span>
                                Coordinador de Zona
                            </span>
                        </label>
                    </div>
                    <p style="font-size:11px;color:#aaa;margin-top:8px;"><i class="fas fa-info-circle"></i> Si no seleccionas ninguno, la carpeta será visible para todos.</p>
                </div>
                <button type="button" class="btn-secondary-action" onclick="saveFolder()" style="width: 100%; justify-content: center; margin-top: 20px;">
                    <i class="fas fa-plus-circle"></i> Crear Carpeta
                </button>
            </form>
        </div>
    </div>

    <div class="modal" id="renameFolderModal">
        <div class="modal-content" style="max-width: 400px;">
            <div class="close-modal" onclick="closeRenameFolderModal()"><i class="fas fa-times"></i></div>
            <h2 style="margin-bottom: 20px;">Renombrar Carpeta</h2>
            <form id="renameFolderForm">
                <input type="hidden" name="id" id="renameFolderId">
                <div class="form-group">
                    <label class="form-label">Nuevo Nombre</label>
                    <input type="text" name="nombre" id="renameFolderName" class="form-input-styled" required>
                </div>
                <div class="form-group" style="margin-top:16px;">
                    <label class="form-label" style="margin-bottom:8px;display:block;">¿Quién puede acceder?</label>
                    <div style="display:flex;flex-direction:column;gap:10px;">
                        <label style="display:flex;align-items:center;gap:10px;font-size:14px;font-weight:600;cursor:pointer;">
                            <input type="checkbox" name="roles_acceso[]" value="estatal" id="rename_rol_estatal" style="width:16px;height:16px;accent-color:#2d7a3e;">
                            <span style="display:flex;align-items:center;gap:6px;">
                                <span style="width:10px;height:10px;border-radius:50%;background:#2d7a3e;display:inline-block;"></span>
                                Coordinador Estatal
                            </span>
                        </label>
                        <label style="display:flex;align-items:center;gap:10px;font-size:14px;font-weight:600;cursor:pointer;">
                            <input type="checkbox" name="roles_acceso[]" value="regional" id="rename_rol_regional" style="width:16px;height:16px;accent-color:#1565c0;">
                            <span style="display:flex;align-items:center;gap:6px;">
                                <span style="width:10px;height:10px;border-radius:50%;background:#1565c0;display:inline-block;"></span>
                                Coordinador Regional
                            </span>
                        </label>
                        <label style="display:flex;align-items:center;gap:10px;font-size:14px;font-weight:600;cursor:pointer;">
                            <input type="checkbox" name="roles_acceso[]" value="zona" id="rename_rol_zona" style="width:16px;height:16px;accent-color:#f57f17;">
                            <span style="display:flex;align-items:center;gap:6px;">
                                <span style="width:10px;height:10px;border-radius:50%;background:#f57f17;display:inline-block;"></span>
                                Coordinador de Zona
                            </span>
                        </label>
                    </div>
                    <p style="font-size:11px;color:#aaa;margin-top:8px;"><i class="fas fa-info-circle"></i> Si no seleccionas ninguno, la carpeta será visible para todos.</p>
                </div>
                <button type="button" class="btn-primary-action" onclick="saveRenameFolder()" style="width: 100%; justify-content: center; margin-top: 20px;">
                    Guardar Cambios
                </button>
            </form>
        </div>
    </div>

    <script>
        var quill = new Quill('#editor-container', {
    theme: 'snow',
    placeholder: 'Breve descripción del contenido (máx. 300 caracteres)...',
    modules: { toolbar: [ ['bold', 'italic'], [{ 'list': 'bullet' }] ] }
});

// Limitar caracteres en descripción
quill.on('text-change', function() {
    const text = quill.getText().trim();
    if (text.length > 300) {
        quill.deleteText(300, text.length);
    }
});

// Actualizar contador
quill.on('text-change', function() {
    const text = quill.getText().trim();
    document.getElementById('charCount').textContent = text.length;
    
    if (text.length > 300) {
        quill.deleteText(300, text.length);
    }
});

// Contador para el título
document.getElementById('inputTitulo').addEventListener('input', function() {
    const length = this.value.length;
    document.getElementById('titleCharCount').textContent = length;
});

        // --- FUNCIÓN NUEVA: Auto-rellenar título ---
        function autoFillTitle(filename) {
            const inputTitulo = document.getElementById('inputTitulo');
            if (inputTitulo.value.trim() === '') {
                let nameWithoutExt = filename.replace(/\.[^/.]+$/, "");
                nameWithoutExt = nameWithoutExt.charAt(0).toUpperCase() + nameWithoutExt.slice(1);
                inputTitulo.value = nameWithoutExt;
            }
        }

        function animateNumber(el, target) {
            let current = 0;
            const inc = target / 30;
            if(target <= 0) { el.innerText = target; return; }
            const timer = setInterval(() => {
                current += inc;
                if (current >= target) {
                    el.innerText = target;
                    clearInterval(timer);
                } else {
                    el.innerText = Math.ceil(current);
                }
            }, 30);
        }
        window.addEventListener('load', () => {
            document.querySelectorAll('.number').forEach(el => {
                const target = parseInt(el.getAttribute('data-target'));
                animateNumber(el, target);
            });
        });

        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('fileInput');

        fileInput.addEventListener('change', function() {
            if(this.files[0]) {
                updateUI(this.files[0].name);
                autoFillTitle(this.files[0].name); 
            }
        });

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(e => {
            dropZone.addEventListener(e, (ev) => { ev.preventDefault(); ev.stopPropagation(); });
        });

        dropZone.addEventListener('drop', (e) => {
            if(e.dataTransfer.files.length) {
                fileInput.files = e.dataTransfer.files;
                updateUI(fileInput.files[0].name);
                autoFillTitle(fileInput.files[0].name); 
            }
        });

        function updateUI(name) {
            document.getElementById('uploadContent').innerHTML = `
                <i class="fas fa-check-circle" style="color: var(--primary); font-size: 32px;"></i>
                <p><strong>${name}</strong></p>
            `;
        }

        function toggleDropdown(btn) {
            event.stopPropagation();
            document.querySelectorAll('.dropdown-menu.show').forEach(m => {
                if (m !== btn.nextElementSibling) m.classList.remove('show');
            });
            btn.nextElementSibling.classList.toggle('show');
        }

        window.onclick = function(event) {
            if (!event.target.matches('.action-btn') && !event.target.matches('.action-btn i')) {
                document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.remove('show'));
            }
        }

        function openCreateModal() {
            document.getElementById('documentModal').classList.add('open');
            document.body.style.overflow = 'hidden';
            
            document.getElementById('documentForm').reset();
            document.getElementById('editId').value = ""; 
            quill.root.innerHTML = "";
            document.getElementById('folderSelectGroup').style.display = 'none'; 
            
            document.getElementById('modalTitle').innerText = "Subir Documento";
            document.getElementById('btnSave').innerHTML = '<i class="fas fa-save"></i> Guardar Documento';
            document.getElementById('uploadContent').innerHTML = `<i class="fas fa-cloud-upload-alt"></i><p><strong>Click para seleccionar</strong> o arrastra aquí</p>`;
            
            document.getElementById('uploadProgressBarContainer').style.display = 'none';
            document.getElementById('progressBar').style.width = '0%';
        }

        function openEditModal(data) {
            document.getElementById('documentModal').classList.add('open');
            document.body.style.overflow = 'hidden';
            
            document.getElementById('editId').value = data.id;
            document.getElementById('inputTitulo').value = data.titulo;
            
            // Default visual para categoría
            document.getElementById('inputCategoria').value = data.categoria ? data.categoria : 'general';
            
            document.getElementById('folderSelectGroup').style.display = 'flex'; 
            
            const carpetaSelect = document.getElementById('inputCarpeta');
            if(carpetaSelect) {
                carpetaSelect.value = data.carpeta_id ? data.carpeta_id : "";
            }

            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = data.descripcion;
            quill.root.innerHTML = tempDiv.innerHTML;

            document.getElementById('modalTitle').innerText = "Editar Documento";
            document.getElementById('btnSave').innerHTML = '<i class="fas fa-sync-alt"></i> Guardar Documento';
            document.getElementById('uploadContent').innerHTML = `
                <i class="fas fa-file-invoice" style="color: var(--primary); font-size: 32px;"></i>
                <p><strong>Archivo actual mantenido.</strong><br>Sube uno nuevo solo si deseas reemplazarlo.</p>
            `;
            
            document.getElementById('uploadProgressBarContainer').style.display = 'none';
        }

        function closeModal() {
            document.getElementById('documentModal').classList.remove('open');
            document.body.style.overflow = '';
        }

        // --- FUNCIÓN DE SUBIDA DEFINITIVA (CORREGIDA CON INYECCIÓN PHP) ---
        function saveDocument() {
            const form = document.getElementById('documentForm');
            const editId = document.getElementById('editId').value;
            const inputTitulo = document.getElementById('inputTitulo');
            const fileInput = document.getElementById('fileInput');

            // 1. Sincronizar descripción
            document.getElementById('docDescription').value = quill.root.innerHTML;

            // 2. Lógica de Título Automático
            if (inputTitulo.value.trim() === '' && fileInput.files.length > 0) {
                let name = fileInput.files[0].name.replace(/\.[^/.]+$/, "");
                inputTitulo.value = name.charAt(0).toUpperCase() + name.slice(1);
            }
            if (inputTitulo.value.trim() === '') {
                inputTitulo.value = "Documento Sin Título"; 
            }

            // 3. Validación
            if (!editId && fileInput.files.length === 0) {
                alert("Debes seleccionar un archivo para subir."); return;
            }

            // 4. UI Loading
            const btn = document.getElementById('btnSave');
            const progressContainer = document.getElementById('uploadProgressBarContainer');
            const progressBar = document.getElementById('progressBar');
            const progressText = document.getElementById('uploadPercentage');
            const originalText = btn.innerHTML;

            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Iniciando...';
            btn.disabled = true;
            progressContainer.style.display = 'block';
            progressBar.style.width = '0%';
            progressText.innerText = '0%';

            const formData = new FormData(form);
            
            // --- CORRECCIÓN CRÍTICA: INYECCIÓN DIRECTA DE PHP ---
            // Esto evita que se lea un valor vacío del DOM si el form se reseteó
            if (editId) {
                // Modo Edición: Usamos lo que el usuario seleccione en el dropdown
                const selectedFolder = document.getElementById('inputCarpeta').value;
                formData.set('carpeta_id', selectedFolder); 
            } else {
                // Modo Creación: IGNORAMOS el input hidden y usamos la variable PHP directa.
                // Esta línea inserta el ID de la carpeta desde el servidor al JS.
                const currentFolder = "<?php echo $current_folder_id ?? ''; ?>";
                
                // Usamos .set() para ASEGURAR que sobrescribimos cualquier valor previo
                formData.set('carpeta_id', currentFolder);
            }
            // --------------------------------------------------

            // Categoría por defecto
            if (!formData.get('categoria')) {
                formData.set('categoria', 'general');
            }

            const url = editId ? '../../api/documents/update.php' : '../../api/documents/create.php';

            // 6. Enviar (Ajax)
            const xhr = new XMLHttpRequest();

            xhr.upload.addEventListener("progress", function(evt) {
                if (evt.lengthComputable) {
                    const percentComplete = Math.round((evt.loaded / evt.total) * 100);
                    progressBar.style.width = percentComplete + '%';
                    progressText.innerText = percentComplete + '%';
                    if(percentComplete < 100){
                        btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Subiendo ${percentComplete}%...`;
                    } else {
                        btn.innerHTML = `<i class="fas fa-cog fa-spin"></i> Procesando...`;
                    }
                }
            }, false);

            xhr.addEventListener("load", function() {
                if (xhr.status === 200) {
                    try {
                        const data = JSON.parse(xhr.responseText);
                        if (data.success) {
                            progressBar.style.background = '#2d7a3e'; 
                            setTimeout(() => {
                                closeModal();
                                location.reload(); 
                            }, 500);
                        } else {
                            alert('Error del servidor: ' + data.message);
                            resetUI();
                        }
                    } catch (e) {
                        console.error("Error JSON", e);
                        alert('Error inesperado al procesar respuesta.');
                        resetUI();
                    }
                } else {
                    alert("Error HTTP: " + xhr.status);
                    resetUI();
                }
            }, false);

            xhr.addEventListener("error", function() { alert("Error de conexión."); resetUI(); }, false);
            xhr.addEventListener("abort", function() { alert("Cancelado."); resetUI(); }, false);

            xhr.open("POST", url);
            xhr.send(formData);

            function resetUI() {
                btn.innerHTML = originalText;
                btn.disabled = false;
                progressContainer.style.display = 'none';
                progressBar.style.width = '0%';
                progressBar.style.background = 'var(--primary)';
            }
        }

        // --- CARPETAS ---
        function openFolderModal() {
            document.getElementById('folderModal').classList.add('open');
            setTimeout(() => document.querySelector('input[name="nombre_carpeta"]').focus(), 100);
        }

        function closeFolderModal() {
            document.getElementById('folderModal').classList.remove('open');
        }

        async function saveFolder() {
            const form = document.getElementById('folderForm');
            if (!form.checkValidity()) { form.reportValidity(); return; }

            const formData = new FormData(form);
            
            try {
                const res = await fetch('../../api/documents/create_folder.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if(data.success) location.reload();
                else alert(data.message || "Error al crear carpeta");
            } catch(e) { console.error(e); alert("Error de conexión"); }
        }

        // --- RENOMBRAR CARPETA ---
        function openRenameFolderModal(data) {
            document.getElementById('renameFolderModal').classList.add('open');
            document.getElementById('renameFolderId').value = data.id;
            document.getElementById('renameFolderName').value = data.nombre;
            // Marcar los roles actuales
            const roles = data.roles_acceso || [];
            ['estatal','regional','zona'].forEach(r => {
                const cb = document.getElementById('rename_rol_' + r);
                if (cb) cb.checked = roles.includes(r);
            });
            setTimeout(() => document.getElementById('renameFolderName').focus(), 100);
        }

        function closeRenameFolderModal() {
            document.getElementById('renameFolderModal').classList.remove('open');
        }

        async function saveRenameFolder() {
            const form = document.getElementById('renameFolderForm');
            if (!form.checkValidity()) { form.reportValidity(); return; }

            const formData = new FormData(form);
            try {
                const res = await fetch('../../api/documents/rename_folder.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if(data.success) location.reload();
                else alert(data.message || "Error al renombrar");
            } catch(e) { console.error(e); alert("Error de conexión"); }
        }

        async function deleteFolder(id) {
            if(!confirm("¿Estás seguro? Se eliminará la carpeta y TODOS los documentos dentro de ella permanentemente.")) return;
            try {
                const response = await fetch('../../api/documents/delete_folder.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ id: id })
                });
                const data = await response.json();
                if (data.success) location.reload();
                else alert('Error: ' + (data.message || "No se pudo eliminar"));
            } catch (error) { alert('Error de conexión'); }
        }

        async function deleteDocument(id) {
            if (!confirm('¿Eliminar este documento permanentemente?')) return;
            try {
                const response = await fetch('../../api/documents/delete.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ id: id, hard: true })
                });
                const data = await response.json();
                if (data.success) { location.reload(); } 
                else { alert('Error: ' + data.message); }
            } catch (error) { alert('Error de conexión'); }
        }

        function filterDocs() {
            const term = document.getElementById('searchInput').value.toLowerCase();
            const cat = document.getElementById('categoryFilter').value;
            
            document.querySelectorAll('.file-card').forEach(card => {
                const title = card.querySelector('.document-title').textContent.toLowerCase();
                const cardCat = card.getAttribute('data-category');
                const matchTerm = title.includes(term);
                const matchCat = cat === 'todos' || cardCat === cat;
                card.style.display = (matchTerm && matchCat) ? 'flex' : 'none';
            });

            document.querySelectorAll('.folder-card').forEach(card => {
                const title = card.querySelector('.document-title').textContent.toLowerCase();
                const matchTerm = title.includes(term);
                card.style.display = (matchTerm && cat === 'todos') ? 'flex' : 'none';
            });
        }
    </script>
</body>
</html>