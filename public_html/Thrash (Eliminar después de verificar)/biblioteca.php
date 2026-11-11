<?php
/**
 * PÁGINA: BIBLIOTECA DE DOCUMENTOS (ADMIN)
 * Ubicación: public_html/App/admin/biblioteca.php
 * Estado: MIGRADO A NUEVA ARQUITECTURA
 */

// 1. CARGA DEL SISTEMA Y SEGURIDAD
require_once __DIR__ . '/../../Includes/bootstrap.php';

// Verificar permisos de administrador
$rol = $_SESSION['user_role'] ?? '';
if (!in_array($rol, ['admin', 'administrador', 'superadmin'])) {
    header('Location: /App/admin/login.php');
    exit();
}

// 2. OBTENER DATOS CON LA NUEVA CLASE DATABASE
$db = Database::getInstance()->getConnection();
$documentos = [];
$stats = ['total' => 0, 'total_pdf' => 0, 'total_imagenes' => 0, 'total_descargas' => 0];

try {
    // Listar documentos
    $stmt = $db->query("SELECT * FROM documentos ORDER BY fecha_subida DESC");
    $documentos = $stmt->fetchAll();
    
    // Calcular estadísticas
    $stmt = $db->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN archivo_ruta LIKE '%.pdf' THEN 1 ELSE 0 END) as total_pdf,
        SUM(CASE WHEN archivo_ruta REGEXP '\\.(jpg|jpeg|png)$' THEN 1 ELSE 0 END) as total_imagenes,
        0 as total_descargas -- Ajustar si tienes columna de descargas en BD
        FROM documentos");
    $stats = $stmt->fetch();

} catch (PDOException $e) {
    error_log("Error DB: " . $e->getMessage());
}

// Helpers de visualización (Manteniendo tu lógica visual)
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
    <title>Biblioteca - Proyecto Verde</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; background: #ffffff; overflow-x: hidden; }

        /* Animaciones */
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }

        /* Estructura Principal */
        .main-content { margin-left: 280px; padding: 30px; transition: margin-left 0.3s ease; animation: fadeIn 0.5s ease; min-height: 100vh; }
        /* Ajuste para cuando el sidebar se colapsa (asegúrate que tu sidebar JS maneje esta clase) */
        .sidebar.collapsed ~ .main-content { margin-left: 80px; }

        /* Header Verde */
        .page-header { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); padding: 40px; border-radius: 20px; box-shadow: 0 10px 30px rgba(17, 153, 142, 0.3); margin-bottom: 30px; position: relative; overflow: hidden; animation: fadeInUp 0.6s ease; }
        .page-header::before { content: ''; position: absolute; top: -50%; right: -10%; width: 300px; height: 300px; background: rgba(255, 255, 255, 0.1); border-radius: 50%; animation: float 6s ease-in-out infinite; }
        .page-title { font-size: 32px; color: white; margin-bottom: 8px; display: flex; align-items: center; gap: 15px; position: relative; z-index: 1; }
        .page-subtitle { color: rgba(255, 255, 255, 0.9); font-size: 16px; position: relative; z-index: 1; }

        /* Stats Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 30px; border-radius: 20px; box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08); display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 15px; transition: all 0.4s; position: relative; overflow: hidden; animation: fadeInUp 0.6s ease; aspect-ratio: 1 / 1; }
        .stat-card:hover { transform: translateY(-10px) scale(1.02); box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15); }
        .stat-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #11998e 0%, #38ef7d 100%); transform: scaleX(0); transition: transform 0.3s ease; }
        .stat-card:hover::before { transform: scaleX(1); }

        .stat-icon { width: 80px; height: 80px; display: flex; align-items: center; justify-content: center; font-size: 32px; color: white; clip-path: url(#statLeafClip); transition: all 0.3s ease; background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
        .stat-card:hover .stat-icon { transform: rotate(10deg) scale(1.1); }
        .stat-content { text-align: center; }
        .stat-content h3 { font-size: 32px; color: #1a1a2e; margin-bottom: 4px; font-weight: 700; }
        .stat-content p { color: #666; font-size: 14px; }

        /* Actions & Filters */
        .action-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; gap: 15px; flex-wrap: wrap; animation: fadeInUp 0.7s ease; }
        .search-filter-group { display: flex; gap: 15px; flex: 1; flex-wrap: wrap; }
        .search-box { flex: 1; max-width: 400px; position: relative; }
        .search-box input { width: 100%; padding: 12px 45px 12px 45px; border: 2px solid #e0e0e0; border-radius: 12px; font-size: 14px; transition: all 0.3s ease; }
        .search-box input:focus { outline: none; border-color: #11998e; box-shadow: 0 0 0 4px rgba(17, 153, 142, 0.1); transform: translateY(-2px); }
        .search-box i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #999; transition: all 0.3s ease; }
        .search-box input:focus ~ i { color: #11998e; }
        
        .filter-select { padding: 12px 15px; border: 2px solid #e0e0e0; border-radius: 12px; font-size: 14px; cursor: pointer; transition: all 0.3s ease; background: white; }
        .filter-select:focus, .filter-select:hover { border-color: #11998e; outline: none; }

        .btn-primary { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white; border: none; padding: 12px 25px; border-radius: 12px; font-size: 14px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(17, 153, 142, 0.3); }
        .btn-primary:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(17, 153, 142, 0.4); }

        /* Document Grid */
        .documents-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 25px; }
        .document-card { background: white; border-radius: 20px; overflow: hidden; box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08); transition: all 0.4s; opacity: 0; animation: fadeInUp 0.6s ease forwards; }
        .document-card:hover { transform: translateY(-10px) scale(1.02); box-shadow: 0 15px 50px rgba(0, 0, 0, 0.2); }
        
        .document-preview { height: 180px; background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden; }
        .document-preview::before { content: ''; position: absolute; top: -50%; right: -50%; width: 200%; height: 200%; background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%); animation: float 8s ease-in-out infinite; }
        .document-preview.pdf { background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%); }
        .document-preview.word { background: linear-gradient(135deg, #2b5876 0%, #4e4376 100%); }
        .document-preview.excel { background: linear-gradient(135deg, #0f9b0f 0%, #38ef7d 100%); }

        .preview-icon { font-size: 64px; color: white; opacity: 0.9; position: relative; z-index: 1; transition: all 0.3s ease; }
        .document-card:hover .preview-icon { transform: scale(1.2) rotate(5deg); }
        .document-type-badge { position: absolute; top: 15px; right: 15px; background: rgba(255, 255, 255, 0.25); backdrop-filter: blur(10px); color: white; padding: 6px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase; z-index: 2; }

        .document-info { padding: 20px; }
        .document-title { font-size: 16px; font-weight: 600; color: #1a1a2e; margin-bottom: 8px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; min-height: 40px; }
        .document-meta { display: flex; align-items: center; gap: 15px; color: #999; font-size: 12px; margin-bottom: 15px; }
        .document-meta i { color: #11998e; }
        .document-description { color: #666; font-size: 13px; line-height: 1.5; margin-bottom: 15px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; min-height: 40px; }

        .document-actions { display: flex; gap: 10px; }
        .btn-download { flex: 1; background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white; border: none; padding: 10px 15px; border-radius: 10px; font-size: 13px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px; transition: all 0.3s ease; text-decoration: none; }
        .btn-download:hover { opacity: 0.9; transform: scale(1.05); }
        
        .btn-icon { width: 38px; height: 38px; border: none; border-radius: 10px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.3s ease; font-size: 14px; }
        .btn-icon.delete { background: rgba(255, 82, 82, 0.1); color: #ff5252; }
        .btn-icon.delete:hover { background: #ff5252; color: white; transform: scale(1.1) rotate(5deg); }

        /* Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(5px); z-index: 2000; align-items: center; justify-content: center; }
        .modal.show { display: flex; animation: fadeIn 0.3s ease; }
        .modal-content { background: white; border-radius: 20px; width: 90%; max-width: 700px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3); animation: modalSlideIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        @keyframes modalSlideIn { from { transform: translateY(-100px) scale(0.9); opacity: 0; } to { transform: translateY(0) scale(1); opacity: 1; } }
        
        .modal-header { padding: 30px; background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white; display: flex; justify-content: space-between; align-items: center; border-radius: 20px 20px 0 0; }
        .close-modal { background: rgba(255, 255, 255, 0.2); border: none; color: white; font-size: 24px; cursor: pointer; width: 35px; height: 35px; display: flex; align-items: center; justify-content: center; border-radius: 8px; transition: all 0.3s ease; }
        .close-modal:hover { background: rgba(255, 255, 255, 0.3); transform: rotate(90deg); }
        
        .modal-body { padding: 30px; }
        .form-group { margin-bottom: 25px; }
        .form-group label { display: block; margin-bottom: 10px; font-weight: 600; color: #333; font-size: 14px; }
        .form-group input, .form-group select { width: 100%; padding: 12px 15px; border: 2px solid #e0e0e0; border-radius: 12px; font-size: 14px; transition: all 0.3s ease; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #11998e; box-shadow: 0 0 0 4px rgba(17, 153, 142, 0.1); }
        
        #editor-container { height: 200px; border: 2px solid #e0e0e0; border-radius: 12px; background: white; }
        .file-upload-area { border: 3px dashed #e0e0e0; border-radius: 15px; padding: 40px; text-align: center; cursor: pointer; transition: all 0.3s ease; background: #f8f9fa; }
        .file-upload-area:hover { border-color: #11998e; background: rgba(17, 153, 142, 0.05); transform: scale(1.02); }
        .file-upload-area input[type="file"] { display: none; }
        
        .modal-footer { padding: 20px 30px; background: #f8f9fa; display: flex; justify-content: flex-end; gap: 10px; border-radius: 0 0 20px 20px; }
        .btn-secondary { background: #e0e0e0; color: #666; border: none; padding: 12px 25px; border-radius: 12px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; }
        .btn-secondary:hover { background: #d0d0d0; transform: translateY(-2px); }

        .empty-state { text-align: center; padding: 80px 20px; background: white; border-radius: 20px; box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08); animation: fadeInUp 0.6s ease; grid-column: 1/-1; }
        .empty-state i { font-size: 80px; color: #ddd; margin-bottom: 20px; animation: float 3s ease-in-out infinite; }

        @media (max-width: 768px) {
            .main-content { margin-left: 0; }
            .sidebar.collapsed ~ .main-content { margin-left: 0; }
            .stats-grid, .documents-grid { grid-template-columns: 1fr; }
            .action-bar { flex-direction: column; }
        }
    </style>
</head>
<body>
    <svg width="0" height="0" style="position: absolute; clip: rect(0 0 0 0);">
        <defs>
            <clipPath id="statLeafClip" clipPathUnits="objectBoundingBox">
                <path transform="scale(0.0015625)" d="M535.3 70.7C541.7 64.6 551 62.4 559.6 65.2C569.4 68.5 576 77.7 576 88L576 274.9C576 406.1 467.9 512 337.2 512C260.2 512 193.8 462.5 169.7 393.3C134.3 424.1 112 469.4 112 520C112 533.3 101.3 544 88 544C74.7 544 64 533.3 64 520C64 445.1 102.2 379.1 160.1 340.3C195.4 316.7 237.5 304 280 304L360 304C373.3 304 384 293.3 384 280C384 266.7 373.3 256 360 256L280 256C240.3 256 202.7 264.8 169 280.5C192.3 210.5 258.2 160 336 160C402.4 160 451.8 137.9 484.7 116C503.9 103.2 520.2 87.9 535.4 70.7z"/>
            </clipPath>
        </defs>
    </svg>
    
    <?php include __DIR__ . '/../../Includes/components/sidebar-admin.php'; ?>

    <main class="main-content" id="mainContent">
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-book"></i>
                Biblioteca de Documentos
            </h1>
            <p class="page-subtitle">Administra y comparte recursos con tu equipo</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-file-alt"></i></div>
                <div class="stat-content">
                    <h3><?php echo $stats['total']; ?></h3>
                    <p>Total Documentos</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-file-pdf"></i></div>
                <div class="stat-content">
                    <h3><?php echo $stats['total_pdf']; ?></h3>
                    <p>Documentos PDF</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orange"><i class="fas fa-image"></i></div>
                <div class="stat-content">
                    <h3><?php echo $stats['total_imagenes']; ?></h3>
                    <p>Flyers e Imágenes</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-download"></i></div>
                <div class="stat-content">
                    <h3><?php echo $stats['total_descargas']; ?></h3>
                    <p>Descargas Totales</p>
                </div>
            </div>
        </div>

        <div class="action-bar">
            <div class="search-filter-group">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Buscar documentos...">
                </div>
                <select class="filter-select" id="categoryFilter">
                    <option value="todos">Todas las Categorías</option>
                    <option value="Manual">Manuales</option>
                    <option value="Flyer">Flyers</option>
                    <option value="Legal">Legal</option>
                    <option value="Capacitación">Capacitación</option>
                </select>
            </div>
            <button class="btn-primary" onclick="openModal()">
                <i class="fas fa-upload"></i>
                Subir Documento
            </button>
        </div>

        <div class="documents-grid" id="documentsGrid">
            <?php if (empty($documentos)): ?>
                <div class="empty-state">
                    <i class="fas fa-folder-open"></i>
                    <h3>No hay documentos disponibles</h3>
                    <p>Comienza subiendo tu primer documento</p>
                </div>
            <?php else: ?>
                <?php foreach ($documentos as $doc): ?>
                    <div class="document-card" data-category="<?php echo htmlspecialchars($doc['categoria']); ?>">
                        <div class="document-preview <?php echo getFileColorClass($doc['archivo_ruta']); ?>">
                            <i class="fas <?php echo getFileIcon($doc['archivo_ruta']); ?> preview-icon"></i>
                            <span class="document-type-badge">
                                <?php echo strtoupper(pathinfo($doc['archivo_ruta'], PATHINFO_EXTENSION)); ?>
                            </span>
                        </div>
                        <div class="document-info">
                            <h3 class="document-title"><?php echo htmlspecialchars($doc['titulo']); ?></h3>
                            <div class="document-meta">
                                <span><i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($doc['fecha_subida'])); ?></span>
                                <span class="badge badge-cat"><?php echo htmlspecialchars($doc['categoria']); ?></span>
                            </div>
                            <p class="document-description">
                                <?php echo strip_tags($doc['descripcion'] ?? 'Sin descripción'); ?>
                            </p>
                            <div class="document-actions">
                                <a href="../../Api/documents/download.php?id=<?php echo $doc['id']; ?>" class="btn-download" target="_blank">
                                    <i class="fas fa-download"></i> Descargar
                                </a>
                                <button class="btn-icon delete" onclick="deleteDocument(<?php echo $doc['id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <div class="modal" id="documentModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Subir Nuevo Documento</h2>
                <button class="close-modal" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="documentForm" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Título del Documento *</label>
                        <input type="text" name="titulo" required placeholder="Ej: Manual de Capacitación 2024">
                    </div>
                    <div class="form-group">
                        <label>Categoría *</label>
                        <select name="categoria" required>
                            <option value="">Selecciona una categoría</option>
                            <option value="Manual">Manual</option>
                            <option value="Flyer">Flyer</option>
                            <option value="Legal">Legal</option>
                            <option value="Financiero">Financiero</option>
                            <option value="Capacitación">Capacitación</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Descripción</label>
                        <div id="editor-container"></div>
                        <input type="hidden" id="docDescription" name="descripcion">
                    </div>
                    <div class="form-group">
                        <label>Archivo *</label>
                        <label class="file-upload-area" for="fileInput" id="dropZone">
                            <div id="uploadContent">
                                <i class="fas fa-cloud-upload-alt" style="color: #11998e;"></i>
                                <p><strong>Click para seleccionar</strong> o arrastra el archivo aquí</p>
                            </div>
                        </label>
                        <input type="file" id="fileInput" name="documento" accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg" style="display: none;" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeModal()">Cancelar</button>
                <button class="btn-primary" onclick="saveDocument()">
                    <i class="fas fa-save"></i> Guardar Documento
                </button>
            </div>
        </div>
    </div>

    <script>
        // 1. Inicializar Quill
        var quill = new Quill('#editor-container', {
            theme: 'snow',
            placeholder: 'Escribe una descripción...',
            modules: { toolbar: [ ['bold', 'italic'], [{ 'list': 'bullet' }] ] }
        });

        // 2. Lógica UI de Archivos (Drag & Drop + Input)
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('fileInput');

        fileInput.addEventListener('change', function() {
            if(this.files[0]) updateUI(this.files[0].name);
        });

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(e => {
            dropZone.addEventListener(e, (ev) => { ev.preventDefault(); ev.stopPropagation(); });
        });

        dropZone.addEventListener('dragover', () => { dropZone.style.background = 'rgba(17, 153, 142, 0.1)'; });
        dropZone.addEventListener('dragleave', () => { dropZone.style.background = '#f8f9fa'; });
        dropZone.addEventListener('drop', (e) => {
            dropZone.style.background = '#f8f9fa';
            if(e.dataTransfer.files.length) {
                fileInput.files = e.dataTransfer.files;
                updateUI(fileInput.files[0].name);
            }
        });

        function updateUI(name) {
            document.getElementById('uploadContent').innerHTML = `
                <i class="fas fa-check-circle" style="color: #11998e; font-size: 40px;"></i>
                <p><strong>${name}</strong></p>
            `;
        }

        // 3. Funciones del Modal
        function openModal() {
            document.getElementById('documentModal').classList.add('show');
        }
        function closeModal() {
            document.getElementById('documentModal').classList.remove('show');
        }

        // 4. MIGRACIÓN: Conexión con NUEVA API (create.php)
        async function saveDocument() {
            const form = document.getElementById('documentForm');
            // Pasar contenido de Quill al input hidden
            document.getElementById('docDescription').value = quill.root.innerHTML;

            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            const btn = document.querySelector('.modal-footer .btn-primary');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
            btn.disabled = true;

            const formData = new FormData(form);

            try {
                // Fetch a la nueva API
                const response = await fetch('../../Api/documents/create.php', {
                    method: 'POST',
                    body: formData
                });
                
                // Parseo seguro de JSON
                const text = await response.text();
                let data;
                try {
                    data = JSON.parse(text);
                } catch(e) {
                    throw new Error("Respuesta del servidor no válida: " + text);
                }

                if (data.success) {
                    alert('Documento subido correctamente');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                console.error(error);
                alert('Error de conexión: ' + error.message);
            } finally {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        }

        // 5. MIGRACIÓN: Conexión con NUEVA API (delete.php)
        async function deleteDocument(id) {
            if (!confirm('¿Eliminar este documento permanentemente?')) return;

            try {
                const response = await fetch('../../Api/documents/delete.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ id: id })
                });
                const data = await response.json();
                
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                console.error(error);
                alert('Error de conexión');
            }
        }

        // 6. Filtro de búsqueda (Visual)
        document.getElementById('searchInput').addEventListener('input', filterDocs);
        document.getElementById('categoryFilter').addEventListener('change', filterDocs);

        function filterDocs() {
            const term = document.getElementById('searchInput').value.toLowerCase();
            const cat = document.getElementById('categoryFilter').value;
            document.querySelectorAll('.document-card').forEach(card => {
                const title = card.querySelector('.document-title').textContent.toLowerCase();
                const cardCat = card.getAttribute('data-category');
                const matchTerm = title.includes(term);
                const matchCat = cat === 'todos' || cardCat === cat;
                card.style.display = (matchTerm && matchCat) ? '' : 'none';
            });
        }
    </script>
</body>
</html>