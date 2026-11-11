<?php
// 1. Carga del sistema
require_once __DIR__ . '/../../includes/bootstrap.php';

$rol = $_SESSION['user_role'] ?? '';
$estado_actual = $_SESSION['user_estado'] ?? 'Estatal';

// Solo permitimos al coordinador estatal
$permitidos = ['coordinador_estatal'];

if (!in_array($rol, $permitidos)) {
    header('Location: /public/index.php'); 
    exit();
}


// 2. LÓGICA DE DATOS Y CARPETAS
$db = Database::getInstance()->getConnection();
$documentos = [];
$carpetas = [];
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
            header('Location: ?');
            exit;
        }
    }

    // B. Obtener Carpetas (Solo las del nivel actual)
    $sql_carpetas = "SELECT * FROM carpetas WHERE carpeta_padre_id " . ($current_folder_id ? "= ?" : "IS NULL") . " ORDER BY nombre ASC";
    $stmt = $db->prepare($sql_carpetas);
    $current_folder_id ? $stmt->execute([$current_folder_id]) : $stmt->execute();
    $carpetas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // C. Obtener Documentos (Solo los del nivel actual y activos)
    $sql_docs = "SELECT * FROM documentos WHERE activo = 1 AND carpeta_id " . ($current_folder_id ? "= ?" : "IS NULL") . " ORDER BY fecha_subida DESC";
    $stmt = $db->prepare($sql_docs);
    $current_folder_id ? $stmt->execute([$current_folder_id]) : $stmt->execute();
    $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // D. Estadísticas GLOBALES
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

// Helpers visuales
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
    <title>Recursos <?php echo htmlspecialchars($estado_actual); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* --- VARIABLES GLOBALES --- */
        :root {
            --primary: #2d7a3e; --primary-dark: #1f5a2d; --dark: #1a1a1a; --light: #f0f7f0;
            --white: #ffffff; --gray-100: #f8f9fa; --gray-200: #e9ecef; --text-muted: #6c757d;
            --shadow-sm: 0 10px 30px rgba(0,0,0,0.05); --shadow-hover: 0 20px 40px rgba(45, 122, 62, 0.15);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; background: var(--light); color: var(--dark); overflow-x: hidden; }

        /* --- LAYOUT --- */
        .container { 
            max-width: 100%; margin: 0; background: var(--white); min-height: 100vh; 
            position: relative; padding-left: 330px; padding-right: 50px; padding-bottom: 40px;
            transition: padding-left 0.3s ease;
        }

        .design-text { 
            position: fixed; left: 290px; top: 50%; writing-mode: vertical-rl; font-size: 13px; font-weight: 500; 
            color: var(--primary); opacity: 0.3; letter-spacing: 2px; z-index: 0; pointer-events: none;
        }

        /* --- HEADER --- */
        .page-header { padding: 50px 0 40px 0; position: relative; z-index: 2; display: flex; justify-content: space-between; align-items: flex-end; }
        .ai-badge { color: var(--primary); font-size: 13px; font-weight: 800; letter-spacing: 4px; margin-bottom: 15px; display: inline-block; text-transform: uppercase; background: rgba(45,122,62,0.1); padding: 5px 15px; border-radius: 20px; }
        .main-title { font-size: 54px; font-weight: 900; line-height: 1; margin-bottom: 15px; color: var(--dark); }
        .description { color: #666; font-size: 16px; line-height: 1.6; max-width: 600px; }

        /* --- STATS GRID --- */
        .grid-overview { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card {
            background: var(--white); border: 1px solid var(--gray-200); padding: 25px; border-radius: 20px; 
            text-align: left; box-shadow: var(--shadow-sm); position: relative; overflow: hidden;
            display: flex; flex-direction: column; justify-content: space-between; height: 140px;
        }
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
        .filters-bar {
            background: var(--white); border: 1px solid var(--gray-200); border-radius: 16px; padding: 20px; 
            margin-bottom: 30px; display: flex; gap: 20px; align-items: flex-end; 
            box-shadow: var(--shadow-sm); z-index: 5; position: relative;
        }
        .form-group { display: flex; flex-direction: column; gap: 8px; flex: 1; margin-bottom: 0 !important; }
        .form-label { font-size: 12px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.5px; }
        .form-input-styled {
            width: 100%; padding: 12px 15px; border-radius: 10px; border: 2px solid var(--gray-200); 
            background: var(--gray-100); font-size: 14px; font-weight: 600; color: var(--dark); 
            transition: all 0.3s; outline: none;
        }
        .form-input-styled:focus { border-color: var(--primary); background: var(--white); }

        /* --- GRID DOCUMENTOS (COMPACTO) --- */
        .documents-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); gap: 20px; }
        
        .document-card { 
            background: white; border-radius: 16px; overflow: hidden; box-shadow: var(--shadow-sm); 
            border: 1px solid var(--gray-200); transition: all 0.4s; opacity: 0; /* Opacidad inicial para animación */
            animation: fadeInUp 0.6s ease forwards;
            display: flex; flex-direction: column;
        }
        .document-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-hover); border-color: var(--primary); }
        
        /* ESTILOS CARPETAS */
        .folder-card { border-color: #e0e7ff; }
        .folder-card:hover { border-color: #6366f1; box-shadow: 0 10px 20px rgba(99, 102, 241, 0.15); }
        .folder-preview { height: 100px; background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%); display: flex; align-items: center; justify-content: center; position: relative; }
        .folder-preview i { font-size: 45px; color: #6366f1; }

        /* ESTILOS ARCHIVOS */
        .document-preview { 
            height: 100px; background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 100%); 
            display: flex; align-items: center; justify-content: center; position: relative;
        }
        .document-preview.pdf { background: linear-gradient(135deg, #d32f2f 0%, #ff6b6b 100%); }
        .document-preview.word { background: linear-gradient(135deg, #1976d2 0%, #42a5f5 100%); }
        .document-preview.excel { background: linear-gradient(135deg, #388e3c 0%, #66bb6a 100%); }
        .document-preview.image { background: linear-gradient(135deg, #fbc02d 0%, #ffeb3b 100%); }
        
        .preview-icon { font-size: 40px; color: white; opacity: 0.9; }
        .document-type-badge { 
            position: absolute; top: 10px; right: 10px; background: rgba(255, 255, 255, 0.2); 
            backdrop-filter: blur(8px); color: white; padding: 4px 8px; border-radius: 20px; 
            font-size: 10px; font-weight: 700; text-transform: uppercase; 
        }

        .document-info { padding: 15px; flex-grow: 1; display: flex; flex-direction: column; }
        
        .document-title { 
            font-size: 14px; font-weight: 800; color: var(--dark); margin-bottom: 6px; 
            min-height: 34px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.2;
        }
        
        .document-desc {
            font-size: 12px; color: #666; margin-bottom: 10px; line-height: 1.3;
            display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; 
            overflow: hidden; text-overflow: ellipsis; min-height: 0;
        }

        .document-meta { display: flex; align-items: center; gap: 10px; color: #999; font-size: 11px; margin-bottom: 10px; margin-top: auto; }
        .document-meta i { color: var(--primary); }
        .badge-cat { background: var(--gray-100); color: var(--text-muted); padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 700; }

        .document-actions { display: flex; gap: 8px; margin-top: 10px; }
        .btn-download { 
            flex: 1; background: var(--dark); color: white; border: none; 
            padding: 8px 12px; border-radius: 10px; font-size: 12px; font-weight: 700; 
            cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 5px; 
            transition: all 0.3s; text-decoration: none; 
        }
        .btn-download:hover { background: var(--primary); }

        /* --- ANIMACIONES DE ENTRADA --- */
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
    
    <?php include __DIR__ . '/../../includes/components/sidebar_ce.php'; ?>

    <div class="design-text">RECURSOS <?php echo strtoupper($estado_actual); ?></div>

    <div class="container" id="mainContent">

        <div class="page-header animate-up">
            <div>
                <div class="ai-badge"><?php echo strtoupper($estado_actual); ?></div>
                <h1 class="main-title">Biblioteca</h1>
                <p class="description">Repositorio de manuales nacionales, formatos legales y recursos compartidos.</p>
            </div>
        </div>

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

        <div class="breadcrumbs animate-up animate-delay-2">
            <a href="?"><i class="fas fa-home"></i> Inicio</a>
            
            <?php if ($current_folder_info): ?>
                <span class="separator"><i class="fas fa-chevron-right"></i></span>
                <span class="current"><?php echo htmlspecialchars($current_folder_info['nombre']); ?></span>
            <?php endif; ?>
        </div>

        <div class="filters-bar animate-up animate-delay-2">
            <div class="form-group" style="flex: 2;">
                <label class="form-label">Buscar Recurso</label>
                <div style="position: relative;">
                    <i class="fas fa-search" style="position: absolute; left: 15px; top: 14px; color: #999;"></i>
                    <input type="text" id="searchInput" class="form-input-styled" style="padding-left: 40px;" placeholder="Ej: Manual de estructura..." onkeyup="filterDocs()">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Filtrar por Categoría</label>
                <select class="form-input-styled" id="categoryFilter" onchange="filterDocs()">
                    <option value="todos">Todo el Material</option>
                    <option value="manual">Manuales Operativos</option>
                    <option value="flyer">Material Gráfico</option>
                    <option value="reporte">Reportes Subidos</option>
                    <option value="formulario">Formatos Legales</option>
                    <option value="presentacion">Presentaciones</option>
                    <option value="otro">Otros</option>
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
                                <i class="fas fa-download"></i> Descargar / Ver
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        // --- 1. Animación de Números (Contadores) ---
        function animateNumber(el, target) {
            let current = 0;
            const inc = target / 30; // Velocidad de incremento
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
            // Iniciar contadores
            document.querySelectorAll('.number').forEach(el => {
                const target = parseInt(el.getAttribute('data-target'));
                animateNumber(el, target);
            });

            // --- 2. Animación en Cascada (Staggered Animation) para los documentos ---
            // Esto hace que los documentos aparezcan uno tras otro suavemente
            const cards = document.querySelectorAll('.document-card');
            cards.forEach((card, index) => {
                // Añade un retraso progresivo de 0.05s por cada tarjeta
                card.style.animationDelay = `${index * 0.05}s`;
            });
        });

        // --- 3. Filtrado de Documentos ---
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