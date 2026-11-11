<?php
if (file_exists(__DIR__ . '/../../includes/bootstrap.php')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
} else { session_start(); }

$rol         = $_SESSION['user_role']   ?? '';
$user_region = $_SESSION['user_region'] ?? '';

$permitidos = ['coordinador_regional', 'regional'];
if (!in_array($rol, $permitidos)) { header('Location: /public/index.php'); exit(); }

$documentos          = [];
$carpetas            = [];
$current_folder_id   = isset($_GET['carpeta']) && is_numeric($_GET['carpeta']) ? (int)$_GET['carpeta'] : null;
$current_folder_info = null;
$stats               = ['total' => 0, 'total_pdf' => 0, 'total_imagenes' => 0, 'total_descargas' => 0];

try {
    $dbCfg = __DIR__ . '/../../includes/database.php';
    $cfg = file_exists($dbCfg) ? require $dbCfg : [
        'host' => 'localhost', 'database' => 'u881824383_pverde',
        'username' => 'u881824383_proyverde', 'password' => 'PProyectoverde8#',
        'charset' => 'utf8mb4',
        'options' => [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    ];
    $dsn = "mysql:host={$cfg['host']};dbname={$cfg['database']};charset={$cfg['charset']}";
    $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], $cfg['options'] ?? [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // Info carpeta actual
    if ($current_folder_id) {
        $st = $pdo->prepare("SELECT * FROM carpetas WHERE id = ?");
        $st->execute([$current_folder_id]);
        $current_folder_info = $st->fetch();
        if (!$current_folder_info) { header('Location: biblioteca.php'); exit; }
    }

    // Carpetas hijas (solo las accesibles para rol regional)
    $sql_c = "SELECT * FROM carpetas WHERE carpeta_padre_id " . ($current_folder_id ? "= ?" : "IS NULL") .
             " AND (roles_acceso IS NULL OR roles_acceso = '[]' OR roles_acceso LIKE '%regional%')" .
             " ORDER BY nombre ASC";
    $st = $pdo->prepare($sql_c);
    $current_folder_id ? $st->execute([$current_folder_id]) : $st->execute();
    $carpetas = $st->fetchAll();

    // Documentos
    $sql_d = "SELECT * FROM documentos WHERE carpeta_id " . ($current_folder_id ? "= ?" : "IS NULL") .
             " AND activo = 1 ORDER BY fecha_subida DESC";
    $st = $pdo->prepare($sql_d);
    $current_folder_id ? $st->execute([$current_folder_id]) : $st->execute();
    $documentos = $st->fetchAll();

    // Stats globales (solo documentos accesibles)
    $st = $pdo->query("SELECT
        COUNT(*) as total,
        COALESCE(SUM(CASE WHEN tipo_archivo IN ('pdf','PDF') THEN 1 ELSE 0 END), 0) as total_pdf,
        COALESCE(SUM(CASE WHEN tipo_archivo IN ('jpg','JPG','jpeg','JPEG','png','PNG') THEN 1 ELSE 0 END), 0) as total_imagenes,
        COALESCE(SUM(descargas), 0) as total_descargas
        FROM documentos WHERE activo = 1");
    $stats = $st->fetch();

} catch (PDOException $e) { $error_db = $e->getMessage(); }

function getFileIcon($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    switch ($ext) {
        case 'pdf':  return 'fa-file-pdf';
        case 'doc': case 'docx': return 'fa-file-word';
        case 'xls': case 'xlsx': return 'fa-file-excel';
        case 'png': case 'jpg': case 'jpeg': return 'fa-image';
        case 'ppt': case 'pptx': return 'fa-file-powerpoint';
        default: return 'fa-file-alt';
    }
}
function getFileColorClass($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    switch ($ext) {
        case 'pdf':  return 'pdf';
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
    <title>Biblioteca - Región</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* --- VARIABLES --- */
        :root {
            --primary: #2d7a3e; --primary-dark: #1f5a2d; --dark: #1a1a1a;
            --light: #f0f7f0; --white: #ffffff;
            --gray-100: #f8f9fa; --gray-200: #e9ecef;
            --text-muted: #6c757d;
            --shadow-sm: 0 10px 30px rgba(0,0,0,0.05);
            --shadow-hover: 0 20px 40px rgba(45, 122, 62, 0.15);
        }
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

        /* --- STATS --- */
        .grid-overview { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: var(--white); border: 1px solid var(--gray-200); padding: 25px; border-radius: 20px; text-align: left; box-shadow: var(--shadow-sm); position: relative; overflow: hidden; display: flex; flex-direction: column; justify-content: space-between; height: 140px; transition: transform 0.3s ease; }
        .stat-card:hover { transform: translateY(-5px); border-color: var(--primary); }
        .stat-card > i { position: absolute; right: 20px; top: 20px; font-size: 40px; color: var(--gray-100); }
        .stat-card .number { font-size: 42px; font-weight: 900; color: var(--dark); line-height: 1; }
        .stat-card .label { font-size: 12px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.5px; margin-top: 5px; }

        /* --- BREADCRUMBS --- */
        .breadcrumbs { padding: 0 0 20px 0; display: flex; align-items: center; gap: 10px; font-size: 14px; font-weight: 600; color: var(--text-muted); }
        .breadcrumbs a { text-decoration: none; color: var(--primary); transition: color 0.2s; }
        .breadcrumbs a:hover { color: var(--primary-dark); text-decoration: underline; }
        .breadcrumbs .current { color: var(--dark); }
        .breadcrumbs .separator { color: #ccc; }

        /* --- FILTROS --- */
        .filters-bar { background: var(--white); border: 1px solid var(--gray-200); border-radius: 16px; padding: 20px; margin-bottom: 30px; display: flex; gap: 20px; align-items: flex-end; box-shadow: var(--shadow-sm); }
        .form-group { display: flex; flex-direction: column; gap: 8px; flex: 1; }
        .form-label { font-size: 12px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.5px; margin-bottom: 5px; display: block; }
        .form-input-styled { width: 100%; padding: 12px 15px; border-radius: 10px; border: 2px solid var(--gray-200); background: var(--gray-100); font-size: 14px; font-weight: 600; color: var(--dark); transition: all 0.3s; outline: none; font-family: inherit; appearance: none; }
        .form-input-styled:focus { border-color: var(--primary); background: var(--white); }

        /* --- DOCUMENT GRID --- */
        .documents-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); gap: 20px; }

        .document-card { background: white; border-radius: 16px; overflow: hidden; box-shadow: var(--shadow-sm); border: 1px solid var(--gray-200); transition: all 0.4s; opacity: 0; animation: fadeInUp 0.6s ease forwards; position: relative; display: flex; flex-direction: column; }
        .document-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-hover); border-color: var(--primary); }

        /* Carpeta */
        .folder-card { border-color: #e0e7ff; cursor: pointer; }
        .folder-card:hover { border-color: #6366f1; box-shadow: 0 10px 20px rgba(99,102,241,0.15); }
        .folder-preview { height: 100px; background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%); display: flex; align-items: center; justify-content: center; }
        .folder-preview i { font-size: 45px; color: #6366f1; }

        /* Documento */
        .document-preview { height: 100px; background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 100%); display: flex; align-items: center; justify-content: center; position: relative; }
        .document-preview.pdf        { background: linear-gradient(135deg, #d32f2f 0%, #ff6b6b 100%); }
        .document-preview.word       { background: linear-gradient(135deg, #1976d2 0%, #42a5f5 100%); }
        .document-preview.excel      { background: linear-gradient(135deg, #388e3c 0%, #66bb6a 100%); }
        .document-preview.powerpoint { background: linear-gradient(135deg, #d84315 0%, #ff7043 100%); }
        .document-preview.image      { background: linear-gradient(135deg, #fbc02d 0%, #ffeb3b 100%); }
        .preview-icon { font-size: 40px; color: white; opacity: 0.9; }
        .document-type-badge { position: absolute; top: 10px; right: 10px; background: rgba(255,255,255,0.2); backdrop-filter: blur(8px); color: white; padding: 4px 8px; border-radius: 20px; font-size: 10px; font-weight: 700; text-transform: uppercase; }

        .document-info { padding: 15px; flex-grow: 1; display: flex; flex-direction: column; }
        .document-title { font-size: 14px; font-weight: 800; color: var(--dark); margin-bottom: 6px; min-height: 34px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.2; }
        .document-desc { font-size: 12px; color: #666; margin-bottom: 10px; line-height: 1.3; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
        .document-meta { display: flex; align-items: center; gap: 10px; color: #999; font-size: 11px; margin-bottom: 10px; margin-top: auto; }
        .document-meta i { color: var(--primary); }

        .document-actions { display: flex; gap: 8px; margin-top: 10px; align-items: center; }
        .btn-download { flex: 1; background: var(--dark); color: white; border: none; padding: 8px 12px; border-radius: 10px; font-size: 12px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 5px; transition: all 0.3s; text-decoration: none; }
        .btn-download:hover { background: var(--primary); }

        /* --- EMPTY STATE --- */
        .empty-state { grid-column: 1/-1; text-align: center; padding: 60px 20px; color: var(--text-muted); }
        .empty-state i { font-size: 48px; opacity: 0.15; display: block; margin-bottom: 16px; }

        /* --- ANIMATIONS --- */
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
    <?php if (file_exists(__DIR__ . '/../../includes/components/sidebar_regional.php'))
        include __DIR__ . '/../../includes/components/sidebar_regional.php'; ?>

    <div class="design-text">BIBLIOTECA</div>

    <div class="container">

        <!-- ── HEADER ── -->
        <div class="page-header animate-up">
            <div>
                <div class="ai-badge">RECURSOS</div>
                <h1 class="main-title">Biblioteca de<br>Documentos.</h1>
                <p class="description">Repositorio centralizado de archivos compartidos con tu región.</p>
            </div>
        </div>

        <?php if (isset($error_db)): ?>
        <div style="background:#fee2e2;color:#991b1b;padding:14px 18px;border-radius:12px;margin-bottom:20px;font-size:14px;">
            <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error_db) ?>
        </div>
        <?php endif; ?>

        <!-- ── STAT CARDS (solo en raíz) ── -->
        <?php if (!$current_folder_id): ?>
        <div class="grid-overview animate-up animate-delay-1">
            <div class="stat-card">
                <i class="fas fa-folder-open"></i>
                <span class="number" data-target="<?= $stats['total'] ?>">0</span>
                <span class="label">Total Archivos</span>
            </div>
            <div class="stat-card">
                <i class="fas fa-file-pdf"></i>
                <span class="number" data-target="<?= $stats['total_pdf'] ?>">0</span>
                <span class="label">Documentos PDF</span>
            </div>
            <div class="stat-card">
                <i class="fas fa-image"></i>
                <span class="number" data-target="<?= $stats['total_imagenes'] ?>">0</span>
                <span class="label">Imágenes / Flyers</span>
            </div>
            <div class="stat-card">
                <i class="fas fa-download"></i>
                <span class="number" data-target="<?= $stats['total_descargas'] ?>">0</span>
                <span class="label">Descargas Totales</span>
            </div>
        </div>
        <?php endif; ?>

        <!-- ── BREADCRUMBS ── -->
        <div class="breadcrumbs animate-up animate-delay-2">
            <a href="biblioteca.php"><i class="fas fa-home"></i> Inicio</a>
            <?php if ($current_folder_info): ?>
                <span class="separator"><i class="fas fa-chevron-right"></i></span>
                <span class="current"><?= htmlspecialchars($current_folder_info['nombre']) ?></span>
            <?php endif; ?>
        </div>

        <!-- ── FILTROS ── -->
        <div class="filters-bar animate-up animate-delay-2">
            <div class="form-group" style="flex: 2;">
                <label class="form-label">Buscar</label>
                <div style="position: relative;">
                    <i class="fas fa-search" style="position:absolute;left:15px;top:14px;color:#999;"></i>
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

        <!-- ── GRID ── -->
        <div class="documents-grid" id="documentsGrid">

            <?php if (empty($documentos) && empty($carpetas)): ?>
            <div class="empty-state">
                <i class="fas fa-folder-open"></i>
                <p>No hay documentos disponibles<?= $current_folder_info ? ' en esta carpeta' : '' ?>.</p>
            </div>
            <?php endif; ?>

            <?php foreach ($carpetas as $folder): ?>
            <div class="document-card folder-card" data-category="todos"
                 onclick="window.location.href='?carpeta=<?= $folder['id'] ?>'">
                <div class="folder-preview">
                    <i class="fas fa-folder"></i>
                </div>
                <div class="document-info">
                    <h3 class="document-title"><?= htmlspecialchars($folder['nombre']) ?></h3>
                    <div class="document-meta">
                        <span><i class="fas fa-clock"></i> <?= date('d/m/Y', strtotime($folder['fecha_creacion'])) ?></span>
                    </div>
                    <?php
                    $roles_acceso = json_decode($folder['roles_acceso'] ?? '[]', true) ?: [];
                    $roles_labels = [
                        'estatal'  => ['label' => 'Estatal',   'color' => '#2d7a3e', 'bg' => '#e8f5e9'],
                        'regional' => ['label' => 'Regional',  'color' => '#1565c0', 'bg' => '#e3f2fd'],
                        'zona'     => ['label' => 'Zona',      'color' => '#f57f17', 'bg' => '#fff8e1'],
                    ];
                    if (!empty($roles_acceso)): ?>
                    <div style="display:flex;flex-wrap:wrap;gap:4px;margin-top:8px;">
                        <?php foreach ($roles_acceso as $rk):
                            $rl = $roles_labels[$rk] ?? null; if (!$rl) continue; ?>
                        <span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px;background:<?= $rl['bg'] ?>;color:<?= $rl['color'] ?>;border:1px solid <?= $rl['color'] ?>30;">
                            <?= $rl['label'] ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div style="font-size:10px;color:#bbb;margin-top:6px;"><i class="fas fa-lock-open" style="margin-right:3px;"></i>Sin restricción de acceso</div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <?php foreach ($documentos as $doc): ?>
            <?php
                $fname = $doc['nombre_archivo'] ?? $doc['ruta_archivo'] ?? '';
                $colorClass = getFileColorClass($fname);
                $iconClass  = getFileIcon($fname);
            ?>
            <div class="document-card file-card" data-category="<?= htmlspecialchars($doc['categoria'] ?? 'general') ?>">
                <div class="document-preview <?= $colorClass ?>">
                    <i class="fas <?= $iconClass ?> preview-icon"></i>
                    <span class="document-type-badge"><?= strtoupper($doc['tipo_archivo'] ?? '') ?></span>
                </div>
                <div class="document-info">
                    <h3 class="document-title"><?= htmlspecialchars($doc['titulo']) ?></h3>
                    <?php if (!empty($doc['descripcion'])): ?>
                    <div class="document-desc">
                        <?= htmlspecialchars(strip_tags(html_entity_decode($doc['descripcion']))) ?>
                    </div>
                    <?php endif; ?>
                    <div class="document-meta">
                        <span><i class="fas fa-calendar"></i> <?= date('d/m/Y', strtotime($doc['fecha_subida'])) ?></span>
                        <span><i class="fas fa-download"></i> <?= $doc['descargas'] ?></span>
                    </div>
                    <div class="document-actions">
                        <a href="../../api/documents/download.php?id=<?= $doc['id'] ?>"
                           class="btn-download" target="_blank">
                            <i class="fas fa-download"></i> Descargar
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

        </div><!-- /documents-grid -->

    </div><!-- /container -->

    <script>
        // ── ANIMACIÓN DE NÚMEROS ──
        function animateNumber(el, target) {
            let current = 0;
            if (target <= 0) { el.innerText = 0; return; }
            const inc = target / 30;
            const timer = setInterval(() => {
                current += inc;
                if (current >= target) { el.innerText = target; clearInterval(timer); }
                else el.innerText = Math.ceil(current);
            }, 30);
        }
        window.addEventListener('load', () => {
            document.querySelectorAll('.number[data-target]').forEach(el => {
                animateNumber(el, parseInt(el.getAttribute('data-target')));
            });
        });

        // ── FILTRO ──
        function filterDocs() {
            const term = document.getElementById('searchInput').value.toLowerCase();
            const cat  = document.getElementById('categoryFilter').value;

            document.querySelectorAll('.file-card').forEach(card => {
                const title    = card.querySelector('.document-title').textContent.toLowerCase();
                const cardCat  = card.getAttribute('data-category');
                const matchTerm = title.includes(term);
                const matchCat  = cat === 'todos' || cardCat === cat;
                card.style.display = (matchTerm && matchCat) ? 'flex' : 'none';
            });

            document.querySelectorAll('.folder-card').forEach(card => {
                const title = card.querySelector('.document-title').textContent.toLowerCase();
                card.style.display = (title.includes(term) && cat === 'todos') ? 'flex' : 'none';
            });
        }
    </script>
</body>
</html>