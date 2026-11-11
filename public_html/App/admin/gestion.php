<?php
// 1. Carga del sistema y seguridad
require_once __DIR__ . '/../../includes/bootstrap.php';

$rol = $_SESSION['user_role'] ?? '';
$permitidos = ['admin', 'administrador', 'superadmin', 'super admin'];

if (!in_array($rol, $permitidos)) {
    header('Location: /public/index.php'); 
    exit();
}

// 2. Conexión y Datos
$db = Database::getInstance()->getConnection();
$data = [
    'objetivos' => [],
    'estrategias' => [],
    'metas' => [],
    'cronograma' => [],
    'vision' => '',
    'mision' => ''
];

$stats = [
    'total_objetivos' => 0, 
    'total_estrategias' => 0, 
    'total_estados' => 0, 
    'progreso' => 0, 
    'total_metas' => 0
];

try {
    // OBJETIVOS
    $stmt = $db->query("SELECT * FROM geo_objetivos WHERE activo = 1 ORDER BY id DESC");
    $data['objetivos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stats['total_objetivos'] = count($data['objetivos']);
    
    // ESTRATEGIAS
    $stmt = $db->query("SELECT * FROM geo_estrategias WHERE activo = 1 ORDER BY id DESC");
    $data['estrategias'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stats['total_estrategias'] = count($data['estrategias']);
    
    // METAS (solo titulo, orden, activo)
    $stmt = $db->query("SELECT * FROM geo_metas WHERE activo = 1 ORDER BY orden ASC, id DESC");
    $data['metas'] = $stmt->fetchAll(PDO::FETCH_ASSOC); 
    $stats['total_metas'] = count($data['metas']);
    
    // CRONOGRAMA
    $stmt = $db->query("SELECT * FROM geo_cronograma WHERE activo = 1 ORDER BY fecha_orden ASC");
    $data['cronograma'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // VISIÓN Y MISIÓN
    $stmt = $db->query("SELECT clave, valor FROM geo_configuracion WHERE clave IN ('vision', 'mision')");
    $config = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($config as $item) {
        if ($item['clave'] === 'vision') $data['vision'] = $item['valor'];
        if ($item['clave'] === 'mision') $data['mision'] = $item['valor'];
    }

    // PROGRESO GLOBAL
    $stmt = $db->query("SELECT valor FROM geo_configuracion WHERE clave = 'progreso_general'");
    $progreso = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['progreso'] = $progreso ? (int)$progreso['valor'] : 0;
      
} catch (PDOException $e) {
    error_log("Error DB: " . $e->getMessage());
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
        /* --- VARIABLES --- */
        :root {
            --primary: #2d7a3e;
            --primary-dark: #1f5a2d;
            --dark: #1a1a1a;
            --light: #f0f7f0;
            --white: #ffffff;
            --gray-100: #f8f9fa;
            --gray-200: #e9ecef;
            --text-muted: #6c757d;
            --shadow-sm: 0 10px 30px rgba(0,0,0,0.05);
            --shadow-hover: 0 20px 40px rgba(45, 122, 62, 0.15);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            font-family: 'Arial', sans-serif; 
            background: var(--light); 
            color: var(--dark); 
            overflow-x: hidden; 
        }

        /* --- LAYOUT --- */
        .container { 
            max-width: 100%; margin: 0; background: var(--white); 
            min-height: 100vh; position: relative; 
            padding-left: 330px; padding-right: 50px; padding-bottom: 40px;
            transition: padding-left 0.3s ease;
        }

        .design-text { 
            position: fixed; left: 290px; top: 50%; 
            writing-mode: vertical-rl; font-size: 13px; font-weight: 500; 
            color: var(--primary); opacity: 0.3; letter-spacing: 2px; 
            z-index: 0; pointer-events: none;
        }

        /* --- HEADER & TITLES --- */
        .page-header { 
            padding: 50px 0 40px 0; position: relative; z-index: 2;
            display: flex; justify-content: space-between; align-items: flex-end;
        }
        .ai-badge { 
            color: var(--primary); font-size: 13px; font-weight: 800; 
            letter-spacing: 4px; margin-bottom: 15px; display: inline-block; 
            text-transform: uppercase; background: rgba(45,122,62,0.1);
            padding: 5px 15px; border-radius: 20px;
        }
        .main-title { font-size: 54px; font-weight: 900; line-height: 1; margin-bottom: 15px; color: var(--dark); }
        .description { color: #666; font-size: 16px; line-height: 1.6; max-width: 600px; }
        .section-title { font-size: 20px; font-weight: 800; margin-bottom: 20px; color: var(--dark); display: flex; align-items: center; gap: 10px; }

        /* --- BUTTONS --- */
        .btn-primary-action {
            background: var(--primary); color: white; border: none;
            padding: 12px 25px; border-radius: 12px; font-weight: 700; 
            cursor: pointer; transition: all 0.3s; 
            display: flex; align-items: center; gap: 10px; font-size: 14px;
            box-shadow: 0 4px 15px rgba(45, 122, 62, 0.3);
        }
        .btn-primary-action:hover { background: var(--primary-dark); transform: translateY(-2px); }
        
        /* --- GRIDS & CARDS --- */
        .grid-overview { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; }
        .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 25px; }

        .stat-card {
            background: var(--white); border: 1px solid var(--gray-200);
            padding: 25px; border-radius: 20px; text-align: left;
            box-shadow: var(--shadow-sm); position: relative; overflow: hidden;
            display: flex; flex-direction: column; justify-content: space-between; 
            height: 140px; transition: transform 0.3s ease;
        }
        .stat-card:hover { transform: translateY(-5px); border-color: var(--primary); }
        .stat-card i { position: absolute; right: 20px; top: 20px; font-size: 40px; color: var(--gray-100); }
        .stat-card .number { font-size: 42px; font-weight: 900; color: var(--dark); line-height: 1; }
        .stat-card .label { font-size: 12px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-top: 5px; }

        .content-card {
            background: var(--white); border-radius: 20px; padding: 30px;
            border: 1px solid var(--gray-200); box-shadow: var(--shadow-sm);
            position: relative; transition: all 0.3s ease; 
            overflow: visible; 
            display: flex; flex-direction: column;
            height: 100%;
        }
        .content-card:hover { transform: translateY(-5px); border-color: var(--primary); box-shadow: var(--shadow-hover); }
        .content-card h4, .content-card p, .content-card .card-icon { position: relative; z-index: 2; }

        .card-number-watermark {
            position: absolute; bottom: -15px; right: 10px; font-size: 80px;
            font-weight: 900; color: var(--primary); opacity: 0.08;
            z-index: 0; line-height: 1; pointer-events: none; transition: all 0.3s ease;
        }
        .content-card:hover .card-number-watermark { opacity: 0.15; transform: scale(1.1) rotate(-5deg); }
        
        .card-icon { 
            width: 50px; height: 50px; background: var(--light); color: var(--primary); 
            border-radius: 15px; display: flex; align-items: center; justify-content: center; 
            font-size: 20px; margin-bottom: 20px; 
        }

        /* --- TABS --- */
        .tabs-container { 
            background: var(--gray-100); border-radius: 16px; padding: 5px; 
            display: inline-flex; gap: 5px; margin-bottom: 30px; 
            border: 1px solid var(--gray-200);
        }
        .tab-btn { 
            padding: 10px 20px; border: none; background: transparent; 
            border-radius: 12px; font-weight: 600; color: #666; 
            cursor: pointer; transition: 0.3s; font-size: 13px;
        }
        .tab-btn.active { background: var(--white); color: var(--primary); box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        /* --- CRONOGRAMA & TIMELINE --- */
        .timeline-container { position: relative; padding-left: 30px; border-left: 2px solid var(--gray-200); margin-left: 10px; }
        .timeline-item { position: relative; margin-bottom: 40px; padding-left: 20px; overflow: visible; }
        .timeline-dot { position: absolute; left: -36px; top: 0; width: 14px; height: 14px; background: var(--white); border: 4px solid var(--primary); border-radius: 50%; }
        .timeline-date { font-size: 11px; font-weight: 800; color: var(--primary); background: var(--light); padding: 4px 10px; border-radius: 20px; display: inline-block; margin-bottom: 8px; }

        /* --- MENÚ DE 3 PUNTOS --- */
        .action-cell { 
            position: absolute; 
            top: 20px; 
            right: 20px; 
            z-index: 50; 
        }
        
        .timeline-item .action-cell {
            top: 0;
            right: 0;
        }

        .action-btn { 
            background: transparent; 
            border: none; 
            cursor: pointer; 
            color: #ccc; 
            font-size: 18px; 
            padding: 5px 10px; 
            transition: 0.2s; 
        }
        .action-btn:hover, .action-btn.active { color: var(--dark); }

        .dropdown-menu { 
            position: absolute; 
            right: 0; 
            top: 100%; 
            background: white; 
            border: 1px solid #eee; 
            border-radius: 8px; 
            box-shadow: 0 5px 15px rgba(0,0,0,0.1); 
            width: 140px; 
            display: none; 
            z-index: 100; 
            padding: 5px 0;
        }
        .dropdown-menu.show { display: block; }

        .dropdown-item { 
            padding: 10px 15px; 
            display: flex; 
            align-items: center; 
            gap: 10px; 
            font-size: 13px; 
            color: #333; 
            cursor: pointer; 
            transition: 0.2s; 
            background: white;
            border: none;
            width: 100%;
            text-align: left;
        }
        .dropdown-item:hover { background: #f8f9fa; color: var(--primary); }
        .dropdown-item.delete { color: #d32f2f; }
        .dropdown-item.delete:hover { background: #ffeaea; }

        /* --- MODALES --- */
        .modal { 
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
            background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(5px);
            z-index: 2000; 
            align-items: center; justify-content: center;
            opacity: 0; transition: opacity 0.4s ease;
            padding: 20px;
        }
        
        .modal.open { display: flex; opacity: 1; }
        
        .modal-content {
            background: var(--white); border-radius: 15px; 
            border: 1px solid var(--gray-200); box-shadow: 0 25px 50px rgba(0,0,0,0.1); 
            width: 100%; max-width: 600px; 
            position: relative;
            max-height: 90vh; overflow-y: auto; padding: 25px; 
            transform: translateY(20px) scale(0.95);
            transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.4s ease;
            opacity: 0;
        }

        .modal.open .modal-content { transform: translateY(0) scale(1); opacity: 1; }
        
        .modal-content::-webkit-scrollbar { width: 6px; }
        .modal-content::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 4px; }
        .modal-content::-webkit-scrollbar-thumb { background: #ccc; border-radius: 4px; }
        .modal-content::-webkit-scrollbar-thumb:hover { background: var(--primary); }

        .modal-content .form-input-styled { padding: 10px 15px; margin-bottom: 15px; }
        .modal-content .form-label { margin-bottom: 5px; }

        @media (max-height: 768px) {
            .modal-content { padding: 20px; max-height: 95vh; }
        }
        
        .close-modal { position: absolute; top: 20px; right: 20px; font-size: 20px; cursor: pointer; color: #ccc; transition: all 0.3s; }
        .close-modal:hover { color: var(--dark); transform: rotate(90deg); }

        /* --- FORMS --- */
        .form-label { font-size: 12px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 8px; display: block; }
        .form-input-styled { width: 100%; padding: 15px; border-radius: 12px; border: 2px solid var(--gray-200); background: var(--gray-100); font-size: 14px; font-weight: 600; color: var(--dark); transition: all 0.3s; outline: none; display: block; margin-bottom: 20px; }
        .form-input-styled:focus { border-color: var(--primary); background: var(--white); }

        /* --- ANIMATIONS & MEDIA QUERIES --- */
        .animate-up { animation: fadeInUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; opacity: 0; transform: translateY(20px); }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .animate-delay-1 { animation-delay: 0.1s; }
        .animate-delay-2 { animation-delay: 0.2s; }
        .animate-delay-3 { animation-delay: 0.3s; }

        @media (max-width: 1024px) {
            .container { padding-left: 20px; padding-right: 20px; }
            .grid-overview { grid-template-columns: repeat(2, 1fr); }
            .grid-2, .grid-3 { grid-template-columns: 1fr; }
            .design-text { display: none; }
            .page-header { flex-direction: column; align-items: flex-start; gap: 20px; }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/components/sidebar_admin.php'; ?>
    
    <div class="design-text">PLANIFICACIÓN 2025</div>

    <div class="container">
        
        <div class="page-header animate-up">
            <div>
                <div class="ai-badge">ESTRATEGIA</div>
                <h1 class="main-title">Modelo de<br>Gestión General.</h1>
                <p class="description">Planificación estratégica: Visión, Misión, objetivos, metas, estrategias y cronograma.</p>
            </div>
            <div>
                <button class="btn-primary-action" onclick="openModal()">
                    <i class="fas fa-plus-circle"></i> Nuevo Elemento
                </button>
            </div>
        </div>

        <div class="grid-overview animate-up animate-delay-1">
            <div class="stat-card">
                <i class="fas fa-flag-usa"></i> <span class="number" data-target="8">0</span> <span class="label">Estados Activos</span>
            </div>
            <div class="stat-card">
                <i class="fas fa-bullseye"></i>
                <span class="number" data-target="<?php echo $stats['total_objetivos']; ?>">0</span>
                <span class="label">Objetivos Activos</span>
            </div>
            <div class="stat-card">
                <i class="fas fa-chess-knight"></i>
                <span class="number" data-target="<?php echo $stats['total_metas']; ?>">0</span>
                <span class="label">Metas Estatales</span>
            </div>
            <div class="stat-card">
                <i class="fas fa-chess-knight"></i>
                <span class="number" data-target="<?php echo $stats['total_estrategias']; ?>">0</span>
                <span class="label">Estrategias</span>
            </div>
        </div>

        <div class="tabs-container animate-up animate-delay-2">
            <button class="tab-btn active" onclick="showTab('vision')">Visión y Misión</button>
            <button class="tab-btn" onclick="showTab('objetivos')">Objetivos</button>
            <button class="tab-btn" onclick="showTab('metas')">Metas</button>
            <button class="tab-btn" onclick="showTab('estrategias')">Estrategias</button>
            <button class="tab-btn" onclick="showTab('cronograma')">Acciones</button>
        </div>

        <div id="tab-vision" class="tab-content animate-up active animate-delay-3">
            <h3 class="section-title"><i class="fas fa-compass"></i> Visión y Misión</h3>
            <div class="grid-2">
                <div class="content-card">
                    <div class="action-cell">
                        <button class="action-btn" onclick="toggleMenu(event, this)">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <div class="dropdown-menu">
                            <button class="dropdown-item" onclick='openEditModal({
                                type: "vision", 
                                title: "Nuestra Visión", 
                                desc: <?php echo json_encode($data["vision"]); ?> 
                            })'>
                                <i class="fas fa-pen"></i> Editar
                            </button>
                        </div>
                    </div>
                    <div class="card-icon"><i class="fas fa-eye"></i></div>
                    <h4 style="font-size: 20px; font-weight: 800; margin-bottom: 15px;">Nuestra Visión</h4>
                    <p style="color: #666; font-size: 15px; line-height: 1.6;">
                        <?php echo !empty($data['vision']) ? htmlspecialchars($data['vision']) : 'Sin visión definida.'; ?>
                    </p>
                </div>
                
                <div class="content-card">
                    <div class="action-cell">
                        <button class="action-btn" onclick="toggleMenu(event, this)">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <div class="dropdown-menu">
                            <button class="dropdown-item" onclick='openEditModal({
                                type: "mision", 
                                title: "Nuestra Misión", 
                                desc: <?php echo json_encode($data["mision"]); ?> 
                            })'>
                                <i class="fas fa-pen"></i> Editar
                            </button>
                        </div>
                    </div>
                    <div class="card-icon"><i class="fas fa-bullseye"></i></div>
                    <h4 style="font-size: 20px; font-weight: 800; margin-bottom: 15px;">Nuestra Misión</h4>
                    <p style="color: #666; font-size: 15px; line-height: 1.6;">
                        <?php echo !empty($data['mision']) ? htmlspecialchars($data['mision']) : 'Sin misión definida.'; ?>
                    </p>
                </div>
            </div>
        </div>

        <div id="tab-objetivos" class="tab-content animate-up animate-delay-3">
            <h3 class="section-title"><i class="fas fa-bullseye"></i> Objetivos Principales</h3>
            
            <div class="grid-3" id="objectives-container">
                <?php if (empty($data['objetivos'])): ?>
                    <div style="color: #999; font-style: italic; padding: 20px; grid-column: 1/-1;">No hay objetivos registrados.</div>
                <?php else: ?>
                    <?php $i = 1; foreach ($data['objetivos'] as $obj): ?>
                        <div class="content-card">
                            <div class="action-cell">
                                <button class="action-btn" onclick="toggleMenu(event, this)">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <div class="dropdown-menu">
                                    <button class="dropdown-item" onclick='openEditModal(<?php echo json_encode([
                                        "id" => $obj["id"], "type" => "objetivo", "title" => $obj["titulo"], "desc" => $obj["descripcion"]
                                    ]); ?>)'>
                                        <i class="fas fa-pen"></i> Editar
                                    </button>
                                    <button class="dropdown-item delete" onclick="deleteCard(this, 'Objetivos', <?php echo $obj['id']; ?>)">
                                        <i class="fas fa-trash-alt"></i> Eliminar
                                    </button>
                                </div>
                            </div>

                            <div class="card-number-watermark">
                                <?php echo sprintf("%02d", $i++); ?>
                            </div>
                            
                            <div class="card-icon"><i class="fas fa-bullseye"></i></div>
                            <h4 style="font-size: 16px; font-weight: 800; margin-bottom: 10px; margin-right: 20px;">
                                <?php echo htmlspecialchars($obj['titulo']); ?>
                            </h4>
                            <p style="color: #666; font-size: 14px; line-height: 1.5; flex-grow: 1;">
                                <?php echo htmlspecialchars($obj['descripcion']); ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <div id="tab-metas" class="tab-content animate-up animate-delay-3">
            <h3 class="section-title"><i class="fas fa-flag"></i> Metas a alcanzar</h3>
            
            <div class="grid-3" id="goals-container">
                <?php if (empty($data['metas'])): ?>
                    <div style="color: #999; font-style: italic; padding: 20px; grid-column: 1/-1;">No hay metas registradas.</div>
                <?php else: ?>
                    <?php $i = 1; foreach ($data['metas'] as $meta): ?>
                        <div class="content-card">
                            <div class="action-cell">
                                <button class="action-btn" onclick="toggleMenu(event, this)">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <div class="dropdown-menu">
                                    <button class="dropdown-item" onclick='openEditModal(<?php echo json_encode([
                                        "id" => $meta["id"], 
                                        "type" => "meta", 
                                        "title" => $meta["titulo"]
                                    ]); ?>)'>
                                        <i class="fas fa-pen"></i> Editar
                                    </button>
                                    <button class="dropdown-item delete" onclick="deleteCard(this, 'Metas', <?php echo $meta['id']; ?>)">
                                        <i class="fas fa-trash-alt"></i> Eliminar
                                    </button>
                                </div>
                            </div>

                            <div class="card-number-watermark">
                                <?php echo sprintf("%02d", $i++); ?>
                            </div>

                            <div class="card-icon"><i class="fas fa-flag"></i></div>
                            
                            <h4 style="font-size: 16px; font-weight: 800; margin-bottom: 10px; margin-right: 20px;">
                                <?php echo htmlspecialchars($meta['titulo']); ?>
                            </h4>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div id="tab-estrategias" class="tab-content animate-up animate-delay-3">
            <h3 class="section-title"><i class="fas fa-chess-board"></i> Estrategias de Implementación</h3>
            <div class="grid-3" id="strategies-container">
                <?php if (empty($data['estrategias'])): ?>
                    <div style="color: #999; font-style: italic; grid-column: 1/-1;">No hay estrategias registradas.</div>
                <?php else: ?>
                    <?php $i = 1; foreach ($data['estrategias'] as $est): ?>
                        <div class="content-card" data-id="<?php echo $est['id']; ?>">
                            
                            <div class="action-cell">
                                <button class="action-btn" onclick="toggleMenu(event, this)">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <div class="dropdown-menu">
                                    <button class="dropdown-item" onclick='openEditModal(<?php echo json_encode([
                                        "id" => $est["id"], "type" => "estrategia", "title" => $est["titulo"], "desc" => $est["descripcion"]
                                    ]); ?>)'>
                                        <i class="fas fa-pen"></i> Editar
                                    </button>
                                    <button class="dropdown-item delete" onclick="deleteCard(this, 'Estrategias', <?php echo $est['id']; ?>)">
                                        <i class="fas fa-trash-alt"></i> Eliminar
                                    </button>
                                </div>
                            </div>

                            <div class="card-number-watermark">
                                <?php echo sprintf("%02d", $i++); ?>
                            </div>
                            <h4 style="font-size: 16px; font-weight: 800; margin-bottom: 10px; margin-top: 10px; margin-right: 20px;"><?php echo htmlspecialchars($est['titulo']); ?></h4>
                            <p style="color: #666; font-size: 13px;"><?php echo htmlspecialchars($est['descripcion']); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div id="tab-cronograma" class="tab-content animate-up animate-delay-3">
            <h3 class="section-title"><i class="fas fa-clock"></i> Línea de Tiempo</h3>
            <div class="content-card">
                <div class="timeline-container" id="timeline-container">
                    <?php foreach ($data['cronograma'] as $evento): ?>
                    <div class="timeline-item">
                        <div class="timeline-dot"></div>
                        
                        <div class="action-cell">
                            <button class="action-btn" onclick="toggleMenu(event, this)">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <div class="dropdown-menu">
                                <button class="dropdown-item" onclick='openEditModal(<?php echo json_encode([
                                    "id" => $evento["id"], 
                                    "type" => "cronograma", 
                                    "title" => $evento["titulo"], 
                                    "desc" => $evento["descripcion"],
                                    "date_visual" => $evento["fecha_visual"],
                                    "date_sort" => $evento["fecha_orden"]
                                ]); ?>)'>
                                    <i class="fas fa-pen"></i> Editar
                                </button>
                                <button class="dropdown-item delete" onclick="deleteCard(this, 'Cronograma', <?php echo $evento['id']; ?>)">
                                    <i class="fas fa-trash-alt"></i> Eliminar
                                </button>
                            </div>
                        </div>

                        <span class="timeline-date"><?php echo htmlspecialchars($evento['fecha_visual']); ?></span>
                        <h4 style="font-weight: 800; margin-bottom: 5px; margin-right: 30px;"><?php echo htmlspecialchars($evento['titulo']); ?></h4>
                        <p style="font-size: 14px; color: #666;"><?php echo htmlspecialchars($evento['descripcion']); ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="modal" id="gestionModal">
        <div class="modal-content animate-up">
            <div class="close-modal" onclick="closeModal()"><i class="fas fa-times"></i></div>
            <h2 id="modalTitle" style="font-size: 24px; font-weight: 800; margin-bottom: 5px; color: var(--dark);">Crear Elemento</h2>
            <p style="font-size: 14px; color: #999; margin-bottom: 25px;">Gestiona los elementos estratégicos del plan.</p>
            
            <form id="createForm" onsubmit="handleSave(event)">
                <input type="hidden" id="itemId" name="id" value=""> 

                <div class="form-group">
                    <label class="form-label">Tipo de Elemento</label>
                    <select class="form-input-styled" id="itemType" onchange="toggleFields()">
                        <option value="vision">Visión</option>
                        <option value="mision">Misión</option>
                        <option value="objetivo">Objetivo</option>
                        <option value="meta">Meta</option>
                        <option value="estrategia">Estrategia</option>
                        <option value="cronograma">Acciones</option>
                    </select>
                </div>
                
                <div id="titleField">
                    <label class="form-label">Título</label>
                    <input type="text" class="form-input-styled" id="itemTitle" placeholder="Título corto y claro" maxlength="255" oninput="updateTitleCounter()">
                    <div style="font-size: 11px; color: #6c757d; text-align: right; margin-top: 5px;" id="titleCounter">0 / 255</div>
                </div>
                
                <div id="descField">
                    <label class="form-label">Descripción / Contenido</label>
                    <textarea class="form-input-styled" id="itemDesc" rows="3" required placeholder="Detalles operativos..."></textarea>
                </div>
                
                <div id="dateField" style="display: none; background: #f8f9fa; padding: 15px; border-radius: 12px; margin-bottom: 20px;">
                    <label class="form-label" style="color: var(--primary);">Configuración de Tiempo</label>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div class="form-group">
                            <label class="form-label">1. Fecha Real (Orden)</label>
                            <input type="date" class="form-input-styled" id="itemSortDate" onchange="generateVisualLabel()">
                        </div>
                        <div class="form-group">
                            <label class="form-label">2. Formato Visual</label>
                            <select class="form-input-styled" id="dateFormat" onchange="generateVisualLabel()">
                                <option value="mes">Mensual (Eje: ENERO 2025)</option>
                                <option value="trimestre">Trimestral (Eje: 1ER TRIMESTRE)</option>
                                <option value="semestre">Semestral (Eje: 1ER SEMESTRE)</option>
                                <option value="anio">Anual (Eje: 2025)</option>
                                <option value="custom">Manual (Escribir libremente)</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">3. Etiqueta Visual (Resultado)</label>
                        <input type="text" class="form-input-styled" id="itemDate" placeholder="Se generará automáticamente..." readonly style="background: #e9ecef; font-weight: 700;">
                        <small style="color: #999; font-size: 11px;">Esto es lo que verá el usuario en el cronograma.</small>
                    </div>
                </div>

                <button type="submit" class="btn-primary-action" style="width: 100%; justify-content: center;">
                    <i class="fas fa-save"></i> Guardar Cambios
                </button>
            </form>
        </div>
    </div>

    <script>
        const VISION_EXISTS = <?php echo !empty($data['vision']) ? 'true' : 'false'; ?>;
        const MISION_EXISTS = <?php echo !empty($data['mision']) ? 'true' : 'false'; ?>;

        // TABS
        function showTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(el => {
                el.classList.remove('active');
            });
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            
            const target = document.getElementById('tab-' + tabName);
            if(target) target.classList.add('active');
            
            const buttons = document.querySelectorAll('.tab-btn');

            if(tabName === 'vision') buttons[0].classList.add('active');
            if(tabName === 'objetivos') buttons[1].classList.add('active');
            if(tabName === 'metas') buttons[2].classList.add('active');
            if(tabName === 'estrategias') buttons[3].classList.add('active');
            if(tabName === 'cronograma') buttons[4].classList.add('active');
        }

        // --- AUTOMATIZACIÓN DE ETIQUETA VISUAL ---
        function generateVisualLabel() {
            const dateVal = document.getElementById('itemSortDate').value;
            const format = document.getElementById('dateFormat').value;
            const visualInput = document.getElementById('itemDate');

            // Si es manual, permitir escritura
            if (format === 'custom') {
                visualInput.readOnly = false;
                visualInput.style.background = '#fff';
                visualInput.focus();
                return;
            } 
            
            // Bloquear escritura y generar
            visualInput.readOnly = true;
            visualInput.style.background = '#e9ecef';

            if (!dateVal) return;

            const dateObj = new Date(dateVal + 'T00:00:00');
            const year = dateObj.getFullYear();
            const month = dateObj.getMonth();

            const meses = ['ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO', 'JULIO', 'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE'];
            let label = '';

            if (format === 'mes') {
                label = `${meses[month]} ${year}`;
            } 
            else if (format === 'trimestre') {
                const q = Math.floor(month / 3) + 1;
                label = `${q}º TRIMESTRE ${year}`;
            }
            else if (format === 'semestre') {
                const s = month < 6 ? '1ER' : '2DO';
                label = `${s} SEMESTRE ${year}`;
            }
            else if (format === 'anio') {
                label = `AÑO ${year}`;
            }

            visualInput.value = label;
        }

        // --- MENÚ DE 3 PUNTOS ---
        function toggleMenu(event, btn) {
            event.stopPropagation();
            const menu = btn.nextElementSibling;
            
            document.querySelectorAll('.dropdown-menu').forEach(m => {
                if (m !== menu) m.classList.remove('show');
            });
            
            menu.classList.toggle('show');
        }

        window.addEventListener('click', () => {
            document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.remove('show'));
        });

        // --- MODAL GESTIÓN ---
        const modal = document.getElementById('gestionModal');
        const form = document.getElementById('createForm');

        function openModal() { 
            document.getElementById('createForm').reset();
            document.getElementById('itemId').value = "";
            document.getElementById('itemType').disabled = false;
            document.getElementById('modalTitle').innerText = "Crear Nuevo Elemento";
            document.getElementById('gestionModal').classList.add('open'); 
            document.body.style.overflow = 'hidden'; 
            toggleFields();
            updateTitleCounter();
        }
        
        function closeModal() { 
            modal.classList.remove('open'); 
            document.body.style.overflow = '';
            setTimeout(() => form.reset(), 300);
            toggleFields();
        }

        modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

        function updateTitleCounter() {
            const titleInput = document.getElementById('itemTitle');
            const counter = document.getElementById('titleCounter');
            const currentLength = titleInput.value.length;
            counter.textContent = currentLength + ' / 255';
        }

        function toggleFields() {
            const type = document.getElementById('itemType').value;
            const titleField = document.getElementById('titleField');
            const descField = document.getElementById('descField');
            const dateField = document.getElementById('dateField');
            const titleInput = document.getElementById('itemTitle');
            const descInput = document.getElementById('itemDesc');
            
            // Lógica para cada tipo
            if (type === 'vision' || type === 'mision') {
                // Visión y Misión: solo descripción
                titleField.style.display = 'none';
                descField.style.display = 'block';
                dateField.style.display = 'none';
                titleInput.removeAttribute('required');
                descInput.setAttribute('required', 'required');
            } 
            else if (type === 'meta') {
                // Meta: solo título (sin descripción ni fecha)
                titleField.style.display = 'block';
                descField.style.display = 'none';
                dateField.style.display = 'none';
                titleInput.setAttribute('required', 'required');
                descInput.removeAttribute('required');
            }
            else if (type === 'cronograma') {
                // Cronograma: título, descripción y fecha
                titleField.style.display = 'block';
                descField.style.display = 'block';
                dateField.style.display = 'block';
                titleInput.setAttribute('required', 'required');
                descInput.setAttribute('required', 'required');
            }
            else {
                // Objetivo y Estrategia: título y descripción
                titleField.style.display = 'block';
                descField.style.display = 'block';
                dateField.style.display = 'none';
                titleInput.setAttribute('required', 'required');
                descInput.setAttribute('required', 'required');
            }
        }

        // --- LÓGICA DE EDICIÓN ---
        function openEditModal(data) {
            document.getElementById('createForm').reset();
            
            document.getElementById('modalTitle').innerText = "Editar Elemento";
            document.getElementById('itemId').value = data.id || '';
            document.getElementById('itemType').value = data.type;
            document.getElementById('itemType').disabled = true;
            
            document.getElementById('itemTitle').value = data.title || '';
            document.getElementById('itemDesc').value = data.desc || '';

            if (data.date_sort) document.getElementById('itemSortDate').value = data.date_sort;
            if (data.date_visual) document.getElementById('itemDate').value = data.date_visual;

            toggleFields();
            updateTitleCounter();
            
            document.getElementById('gestionModal').classList.add('open'); 
            document.body.style.overflow = 'hidden';
        }

        // --- GUARDAR (CREAR O EDITAR) ---
        async function handleSave(e) {
            e.preventDefault();
            
            const id = document.getElementById('itemId').value;
            const type = document.getElementById('itemType').value;
            const titleInput = document.getElementById('itemTitle');
            const title = titleInput ? titleInput.value : '';
            const desc = document.getElementById('itemDesc').value;
            
            const dateInput = document.getElementById('itemDate');
            const sortDateInput = document.getElementById('itemSortDate');
            const date = dateInput ? dateInput.value : '';
            const sort_date = sortDateInput ? sortDateInput.value : '';

            // Validación
            if (type === 'meta' && !title.trim()) {
                alert('El título es obligatorio para las metas.');
                return;
            }

            if (type !== 'vision' && type !== 'mision' && type !== 'meta' && !title.trim()) {
                alert('El título es obligatorio.');
                return;
            }

            const url = id ? '../../api/gestion/update.php' : '../../api/gestion/create.php';

            const btn = document.getElementById('createForm').querySelector('button[type="submit"]');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
            btn.disabled = true;

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id, type, title, desc, date, sort_date })
                });
                const res = await response.json();

                if (res.success) {
                    closeModal();
                    location.reload();
                } else {
                    alert('Error: ' + res.message);
                }
            } catch (err) {
                console.error(err);
                alert('Error de conexión');
            } finally {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        }

        // AJAX DELETE
        async function deleteCard(btn, type, id) {
            if(!confirm('¿Eliminar este elemento permanentemente?')) return;
            
            const card = btn.closest('.content-card') || btn.closest('.timeline-item');
            
            try {
                const response = await fetch('../../api/gestion/delete.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id, type: type })
                });
                const data = await response.json();

                if (data.success) {
                    card.style.transition = 'all 0.3s ease';
                    card.style.opacity = '0';
                    card.style.transform = 'scale(0.9)';
                    setTimeout(() => card.remove(), 300);
                } else {
                    alert("Error: " + data.message);
                }
            } catch (e) {
                console.error(e);
                alert("Error de conexión");
            }
        }

        // ANIMACIÓN NÚMEROS
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
            toggleFields();
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                if (modal.classList.contains('open')) closeModal();
            }
        });
    </script>
</body>
</html>