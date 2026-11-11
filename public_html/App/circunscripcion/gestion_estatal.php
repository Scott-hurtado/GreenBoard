<?php
// ==========================================
// 1. CONFIGURACIÓN Y SEGURIDAD
// ==========================================
require_once __DIR__ . '/../../includes/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$rol = $_SESSION['user_role'] ?? '';
$permitidos = ['admin', 'administrador', 'superadmin', 'coordinador', 'coordinador_estatal', 'circunscripcion']; 

if (!in_array($rol, $permitidos)) {
    header('Location: /public/index.php'); 
    exit();
}

$db = Database::getInstance()->getConnection();

$estados_permitidos = [
    'Sonora', 'Chihuahua', 'Sinaloa', 'Jalisco', 
    'Durango', 'Nayarit', 'Baja California', 'Baja California Sur'
];

$estado_actual = isset($_GET['estado']) && in_array($_GET['estado'], $estados_permitidos) 
                 ? $_GET['estado'] 
                 : 'Sonora';

// ==========================================
// 2. CONSULTAS DE DATOS (READ - SERVER SIDE)
// ==========================================
$data = [
    'vision_mision' => ['vision' => '', 'mision' => ''], 
    'objetivos' => [], 
    'estrategias' => [], 
    'metas' => [], 
    'cronograma' => [],
    'diagnostico' => null,
    'diag_electoral' => [],
    'archivos' => []
];

try {
    // Visión/Misión
    $stmt = $db->prepare("SELECT vision, mision FROM geo_vision_mision WHERE estado = ? LIMIT 1");
    $stmt->execute([$estado_actual]);
    $vm = $stmt->fetch(PDO::FETCH_ASSOC);
    if($vm) $data['vision_mision'] = $vm;
    else $data['vision_mision'] = ['vision' => "Sin definir.", 'mision' => "Sin definir."];

    // Objetivos
    $stmt = $db->prepare("SELECT * FROM geo_objetivos WHERE estado = ? AND activo = 1 ORDER BY id DESC");
    $stmt->execute([$estado_actual]);
    $data['objetivos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Estrategias
    $stmt = $db->prepare("SELECT * FROM geo_estrategias WHERE estado = ? AND activo = 1 ORDER BY id DESC");
    $stmt->execute([$estado_actual]);
    $data['estrategias'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Metas
    $stmt = $db->prepare("SELECT * FROM geo_metas WHERE estado = ? AND activo = 1 ORDER BY id ASC");
    $stmt->execute([$estado_actual]);
    $data['metas'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Cronograma
    $stmt = $db->prepare("SELECT * FROM geo_cronograma WHERE estado = ? AND activo = 1 ORDER BY fecha_orden ASC");
    $stmt->execute([$estado_actual]);
    $data['cronograma'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Diagnóstico Inicial
    $stmt = $db->prepare("SELECT * FROM geo_diagnostico WHERE estado = ? LIMIT 1");
    $stmt->execute([$estado_actual]);
    $data['diagnostico'] = $stmt->fetch(PDO::FETCH_ASSOC);

    // Datos Electorales
    $stmt = $db->prepare("SELECT * FROM geo_diag_electoral WHERE estado = ? ORDER BY anio ASC");
    $stmt->execute([$estado_actual]);
    $electoral = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach($electoral as $row) {
        $data['diag_electoral'][$row['anio']] = $row;
    }

    // Archivos adjuntos
    $stmt = $db->prepare("SELECT * FROM geo_diag_archivos WHERE estado = ? ORDER BY fecha_subida DESC");
    $stmt->execute([$estado_actual]);
    $data['archivos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) { $error_msg = "Error DB: " . $e->getMessage(); }

$coordinadores = [
    'Sonora' => 'Sergio Augusto López',
    'Chihuahua' => 'Octavio Borunda',
    'Sinaloa' => 'Ricardo Madrid',
    'Jalisco' => 'Miyagi Munguía',
    'Durango' => 'Gerardo Villareal',
    'Nayarit' => 'Jazmín Bugarín',
    'Baja California' => 'Fausto Gallardo',
    'Baja California Sur' => 'Raul Gonzalez'
];
$nombre_coord = $coordinadores[$estado_actual] ?? 'Coordinador Estatal';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión Estatal - <?php echo htmlspecialchars($estado_actual); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ESTILOS */
        :root { --primary: #2d7a3e; --primary-dark: #1f5a2d; --dark: #1a1a1a; --light: #f0f7f0; --white: #ffffff; --gray-100: #f8f9fa; --gray-200: #e9ecef; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; background: var(--light); color: var(--dark); overflow-x: hidden; }
        .container { max-width: 100%; margin: 0; background: var(--white); min-height: 100vh; padding-left: 330px; padding-right: 50px; padding-bottom: 40px; transition: padding-left 0.3s ease; }
        
        .page-header { padding: 40px 0 20px 0; }
        .ai-badge { 
            color: var(--primary); font-size: 13px; font-weight: 800; 
            letter-spacing: 4px; margin-bottom: 15px; display: inline-block; 
            text-transform: uppercase; background: rgba(45,122,62,0.1);
            padding: 5px 15px; border-radius: 20px;
        }
        .main-title { font-size: 54px; font-weight: 900; line-height: 1; margin-bottom: 15px; color: var(--dark); }
        .subtitle { color: #666; font-size: 16px; margin-bottom: 30px; }

        .state-nav { display: flex; gap: 10px; overflow-x: auto; padding-bottom: 10px; margin-bottom: 30px; border-bottom: 2px solid var(--gray-200); }
        .state-link { text-decoration: none; padding: 10px 20px; border-radius: 10px 10px 0 0; background: var(--white); color: #666; font-weight: 700; font-size: 14px; border: 1px solid transparent; transition: all 0.3s; white-space: nowrap; position: relative; top: 2px; }
        .state-link:hover { background: var(--light); color: var(--primary); }
        .state-link.active { background: var(--white); color: var(--primary); border: 2px solid var(--gray-200); border-bottom: 2px solid var(--white); z-index: 2; }

        .coord-card { background: #e8f5e9; border-left: 5px solid var(--primary); padding: 20px; border-radius: 10px; margin-bottom: 30px; display: flex; align-items: center; gap: 20px; }
        .coord-avatar { width: 60px; height: 60px; background: var(--white); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; color: var(--primary); box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .coord-info h4 { margin: 0; font-size: 18px; font-weight: 800; color: var(--dark); }
        .coord-info p { margin: 5px 0 0 0; font-size: 13px; color: #555; }

        .tabs-container { background: var(--gray-100); border-radius: 16px; padding: 5px; display: inline-flex; gap: 5px; margin-bottom: 30px; flex-wrap: wrap; }
        .tab-btn { padding: 10px 20px; border: none; background: transparent; border-radius: 12px; font-weight: 600; color: #666; cursor: pointer; }
        .tab-btn.active { background: var(--white); color: var(--primary); box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .tab-content { display: none; }
        .tab-content.active { display: block; animation: fadeIn 0.5s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        .content-card { background: var(--white); border-radius: 16px; padding: 25px; border: 1px solid var(--gray-200); margin-bottom: 20px; position: relative; box-shadow: 0 5px 15px rgba(0,0,0,0.05); transition: 0.3s; }
        .content-card:hover { transform: translateY(-3px); border-color: var(--primary); }
        .card-tag { position: absolute; top: 20px; left: 20px; font-size: 10px; font-weight: 800; text-transform: uppercase; color: var(--white); background: var(--primary); padding: 4px 10px; border-radius: 20px; }
        .content-card h4 { margin-top: 25px; }

        .diag-section-title { font-size: 14px; font-weight: 800; color: var(--primary); text-transform: uppercase; letter-spacing: 1px; margin: 25px 0 15px 0; display: flex; align-items: center; gap: 10px; border-bottom: 2px solid var(--light); padding-bottom: 8px; }
        .data-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .data-item { background: var(--gray-100); padding: 12px 15px; border-radius: 10px; border: 1px solid #eee; }
        .data-label { display: block; font-size: 11px; font-weight: 700; color: #888; text-transform: uppercase; margin-bottom: 4px; }
        .data-value { display: block; font-size: 15px; font-weight: 700; color: var(--dark); }

        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        @media (max-width: 1024px) { .grid-3 { grid-template-columns: 1fr; } }
        .btn-admin-add { background: var(--dark); color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 700; cursor: pointer; float: right; font-size: 12px; display: flex; align-items: center; gap: 8px; }
        
        .card-menu { position: absolute; top: 20px; right: 20px; z-index: 10; }
        .menu-btn { background: none; border: none; font-size: 18px; color: #999; cursor: pointer; padding: 5px 10px; border-radius: 50%; }
        .menu-btn:hover { background: #f0f0f0; color: var(--dark); }
        .menu-content { display: none; position: absolute; right: 0; top: 30px; background: white; border: 1px solid #ddd; box-shadow: 0 5px 15px rgba(0,0,0,0.1); border-radius: 8px; width: 140px; z-index: 20; }
        .menu-content a { display: block; padding: 10px 15px; color: #333; text-decoration: none; font-size: 13px; transition: 0.2s; cursor: pointer; }
        .menu-content a:hover { background: var(--gray-100); color: var(--primary); }
        .menu-content a.delete { color: #d32f2f; }
        .menu-content.show { display: block; }

        .table-actions-container { background: #fff; border-radius: 12px; border: 1px solid var(--gray-200); overflow: visible; margin-top: 15px; min-height: 200px; }
        .custom-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .custom-table th { background: #f4f6f8; color: #555; font-weight: 700; text-align: left; padding: 15px; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; }
        .custom-table td { padding: 15px; border-bottom: 1px solid #eee; vertical-align: top; color: #333; }
        .custom-table tr:hover { background: #fafafa; }
        .date-badge { background: #e3f2fd; color: #1565c0; padding: 4px 8px; border-radius: 4px; font-weight: 600; font-size: 11px; display: inline-block; white-space: nowrap; }

        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 3000; align-items: center; justify-content: center; }
        .modal-content { background: white; width: 500px; padding: 30px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); max-height: 90vh; overflow-y: auto; }
        .close-modal { position: absolute; top: 20px; right: 20px; font-size: 20px; cursor: pointer; color: #999; }

        @media (max-width: 1024px) { .container { padding-left: 20px; padding-right: 20px; } .grid-2 { grid-template-columns: 1fr; } .table-actions-container { overflow-x: auto; } }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/components/sidebar_circunscripcion.php'; ?>

    <div class="container">
        
        <div class="page-header">
            <div class="ai-badge">VISTA ADMINISTRADOR</div>
            <h1 class="main-title">Gestión Estatal</h1>
            <p class="subtitle">Estructura operativa del estado de <strong><?php echo htmlspecialchars($estado_actual); ?></strong>.</p>
        </div>

        <div class="state-nav">
            <?php foreach ($estados_permitidos as $edo): ?>
                <a href="?estado=<?php echo urlencode($edo); ?>" class="state-link <?php echo ($estado_actual === $edo) ? 'active' : ''; ?>">
                    <?php echo $edo; ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="coord-card">
            <div class="coord-avatar"><i class="fas fa-user-tie"></i></div>
            <div class="coord-info">
                <h4><?php echo htmlspecialchars($nombre_coord); ?></h4>
                <p>Coordinador Estatal de <strong><?php echo $estado_actual; ?></strong></p>
            </div>
            <button class="btn-admin-add" style="margin-left: auto;" onclick="openModal()">
                <i class="fas fa-plus"></i> Gestión Admin
            </button>
        </div>

        <div class="tabs-container">
            <button class="tab-btn" data-tab="vision" onclick="switchTab('vision')">Visión & Misión</button>
            <button class="tab-btn" data-tab="objetivos" onclick="switchTab('objetivos')">Objetivos</button>
            <button class="tab-btn" data-tab="metas" onclick="switchTab('metas')">Metas</button>
            <button class="tab-btn" data-tab="estrategias" onclick="switchTab('estrategias')">Estrategias</button>
            <button class="tab-btn" data-tab="cronograma" onclick="switchTab('cronograma')">Acciones</button>
            <button class="tab-btn" data-tab="diagnostico" onclick="switchTab('diagnostico')">Diagnóstico Inicial</button>
        </div>

        <div id="tab-vision" class="tab-content">
            <div class="grid-2">
                <div class="content-card">
                    <h3 style="margin-bottom: 15px; color: var(--dark); font-size: 18px;"><i class="fas fa-eye"></i> Visión</h3>
                    <p style="color: #666; font-size: 15px; line-height: 1.6; white-space: pre-line;">
                        <?php echo nl2br(htmlspecialchars($data['vision_mision']['vision'])); ?>
                    </p>
                </div>
                <div class="content-card">
                    <h3 style="margin-bottom: 15px; color: var(--dark); font-size: 18px;"><i class="fas fa-bullseye"></i> Misión</h3>
                    <p style="color: #666; font-size: 15px; line-height: 1.6; white-space: pre-line;">
                        <?php echo nl2br(htmlspecialchars($data['vision_mision']['mision'])); ?>
                    </p>
                </div>
            </div>
        </div>

        <div id="tab-objetivos" class="tab-content">
            <h3 style="margin-bottom: 15px; color: var(--dark); font-size: 18px;"><i class="fas fa-flag"></i> Objetivos del Estado</h3>
            <?php if (empty($data['objetivos'])): ?>
                <div class="content-card" style="text-align: center; color: #999;">No hay objetivos registrados.</div>
            <?php else: ?>
                <div class="grid-2">
                    <?php foreach ($data['objetivos'] as $obj): ?>
                        <div class="content-card" id="card-objetivo-<?php echo $obj['id']; ?>">
                            <div class="card-menu">
                                <button class="menu-btn" onclick="toggleMenu(event, 'menu-obj-<?php echo $obj['id']; ?>')"><i class="fas fa-ellipsis-v"></i></button>
                                <div class="menu-content" id="menu-obj-<?php echo $obj['id']; ?>">
                                    <a onclick="openEditModal(<?php echo $obj['id']; ?>, 'objetivo', '<?php echo addslashes($obj['titulo']); ?>', '<?php echo addslashes($obj['descripcion']); ?>')"><i class="fas fa-edit"></i> Modificar</a>
                                    <a class="delete" onclick="deleteItem(<?php echo $obj['id']; ?>, 'objetivo')"><i class="fas fa-trash-alt"></i> Eliminar</a>
                                </div>
                            </div>
                            <span class="card-tag">Objetivo</span>
                            <h4 style="font-weight: 800; margin-bottom: 5px; padding-right: 30px;"><?php echo htmlspecialchars($obj['titulo']); ?></h4>
                            <p style="font-size: 14px; color: #666;"><?php echo htmlspecialchars($obj['descripcion']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div id="tab-metas" class="tab-content">
            <h3 style="margin-bottom: 15px; color: var(--dark); font-size: 18px;"><i class="fas fa-crosshairs"></i> Metas a alcanzar</h3>
            <?php if (empty($data['metas'])): ?>
                <div class="content-card" style="text-align: center; color: #999;">No hay metas visibles para <strong><?php echo $estado_actual; ?></strong>.</div>
            <?php else: ?>
                <div class="grid-3">
                    <?php $contador = 1; foreach ($data['metas'] as $meta): ?>
                        <div class="content-card" id="card-meta-<?php echo $meta['id']; ?>" style="border-left: 4px solid var(--primary); padding: 15px 20px; min-height: 80px; display: flex; align-items: center;">
                            
                            <div class="card-menu" style="top: 10px; right: 10px;">
                                <button class="menu-btn" onclick="toggleMenu(event, 'menu-meta-<?php echo $meta['id']; ?>')"><i class="fas fa-ellipsis-v"></i></button>
                                <div class="menu-content" id="menu-meta-<?php echo $meta['id']; ?>">
                                    <a onclick='openEditModal(<?php echo $meta['id']; ?>, "meta", "Meta #<?php echo $contador; ?>", <?php echo json_encode($meta['titulo']); ?>)'>
                                        <i class="fas fa-edit"></i> Modificar
                                    </a>
                                    <a class="delete" onclick="deleteItem(<?php echo $meta['id']; ?>, 'meta')"><i class="fas fa-trash-alt"></i> Eliminar</a>
                                </div>
                            </div>
                            
                            <p style="font-weight: 600; font-size: 14px; margin: 0; color: #1a1a1a; line-height: 1.4; padding-right: 25px; width: 100%;"><?php echo nl2br(htmlspecialchars($meta['titulo'])); ?></p>
                        </div>
                    <?php $contador++; endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div id="tab-estrategias" class="tab-content">
            <h3 style="margin-bottom: 15px; color: var(--dark); font-size: 18px;"><i class="fas fa-chess"></i> Estrategias Locales</h3>
            <?php if (empty($data['estrategias'])): ?>
                <div class="content-card" style="text-align: center; color: #999;">No hay estrategias registradas.</div>
            <?php else: ?>
                <div class="grid-2">
                    <?php foreach ($data['estrategias'] as $est): ?>
                        <div class="content-card" id="card-estrategia-<?php echo $est['id']; ?>" style="border-left: 4px solid var(--primary);">
                            <div class="card-menu">
                                <button class="menu-btn" onclick="toggleMenu(event, 'menu-est-<?php echo $est['id']; ?>')"><i class="fas fa-ellipsis-v"></i></button>
                                <div class="menu-content" id="menu-est-<?php echo $est['id']; ?>">
                                    <a onclick="openEditModal(<?php echo $est['id']; ?>, 'estrategia', '<?php echo addslashes($est['titulo']); ?>', '<?php echo addslashes($est['descripcion']); ?>')"><i class="fas fa-edit"></i> Modificar</a>
                                    <a class="delete" onclick="deleteItem(<?php echo $est['id']; ?>, 'estrategia')"><i class="fas fa-trash-alt"></i> Eliminar</a>
                                </div>
                            </div>
                            <h4 style="font-weight: 800; margin-bottom: 5px; padding-right: 30px;"><?php echo htmlspecialchars($est['titulo']); ?></h4>
                            <p style="font-size: 14px; color: #666;"><?php echo htmlspecialchars($est['descripcion']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div id="tab-cronograma" class="tab-content">
            <div class="table-filter-bar">
                <h3 style="margin:0; color: var(--dark); font-size: 18px;"><i class="fas fa-calendar-alt"></i> Cronograma de Actividades</h3>
            </div>

            <?php if (empty($data['cronograma'])): ?>
                <div class="content-card" style="text-align: center; color: #999;">No hay actividades registradas en el cronograma.</div>
            <?php else: ?>
                <div class="table-actions-container">
                    <table class="custom-table" id="cronogramaTable">
                        <thead>
                            <tr>
                                <th style="width: 20%;">Fecha (Visual)</th>
                                <th style="width: 25%;">Actividad</th>
                                <th style="width: 45%;">Detalles</th>
                                <th style="width: 10%;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data['cronograma'] as $crono): ?>
                                <tr>
                                    <td>
                                        <span class="date-badge">
                                            <?php echo htmlspecialchars($crono['fecha_visual'] ?? 'Sin fecha'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="font-weight: 700; color: #333;"><?php echo htmlspecialchars($crono['titulo']); ?></div>
                                    </td>
                                    <td>
                                        <div style="font-size: 13px; color: #666;"><?php echo htmlspecialchars($crono['descripcion']); ?></div>
                                    </td>
                                    <td style="text-align: center; position: relative;">
                                        <button class="menu-btn" onclick="toggleMenu(event, 'menu-crono-<?php echo $crono['id']; ?>')"><i class="fas fa-ellipsis-h"></i></button>
                                        <div class="menu-content" id="menu-crono-<?php echo $crono['id']; ?>" style="right: 100%; top: 0; margin-right: 10px;">
                                            <a onclick="openEditModal(<?php echo $crono['id']; ?>, 'cronograma', '<?php echo addslashes($crono['titulo']); ?>', '<?php echo addslashes($crono['descripcion']); ?>', '', '', '', '', '<?php echo addslashes($crono['fecha_visual'] ?? ''); ?>', '<?php echo addslashes($crono['fecha_orden'] ?? ''); ?>')"><i class="fas fa-edit"></i> Editar</a>
                                            <a class="delete" onclick="deleteItem(<?php echo $crono['id']; ?>, 'cronograma')"><i class="fas fa-trash-alt"></i> Eliminar</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div id="tab-diagnostico" class="tab-content">
            <?php if (!$data['diagnostico']): ?>
                <div class="content-card" style="text-align: center; padding: 50px;">
                    <i class="fas fa-file-invoice" style="font-size: 40px; color: #ccc; margin-bottom: 15px;"></i>
                    <h3 style="color: #888;">No hay un diagnóstico registrado aún para <?php echo $estado_actual; ?></h3>
                </div>
            <?php else: $d = $data['diagnostico']; ?>
                <div class="content-card">
                    <h3 style="margin-bottom: 20px; color: var(--dark); font-size: 20px;"><i class="fas fa-file-contract"></i> Diagnóstico Integral Estatal</h3>
                    
                    <div class="diag-section-title"><i class="fas fa-id-card"></i> Ficha Identificativa</div>
                    <div class="data-grid">
                        <div class="data-item"><span class="data-label">Responsable</span><span class="data-value"><?php echo htmlspecialchars($d['responsable'] ?: 'N/D'); ?></span></div>
                        <div class="data-item"><span class="data-label">Cargo</span><span class="data-value"><?php echo htmlspecialchars($d['cargo'] ?: 'N/D'); ?></span></div>
                        <div class="data-item"><span class="data-label">Teléfono</span><span class="data-value"><?php echo htmlspecialchars($d['tel'] ?: 'N/D'); ?></span></div>
                        <div class="data-item"><span class="data-label">Correo</span><span class="data-value"><?php echo htmlspecialchars($d['mail'] ?: 'N/D'); ?></span></div>
                        <div class="data-item"><span class="data-label">Otro Contacto</span><span class="data-value"><?php echo htmlspecialchars($d['contacto_otro'] ?: 'N/D'); ?></span></div>
                    </div>

                    <div class="diag-section-title"><i class="fas fa-sitemap"></i> Estructura Organizativa y Recursos</div>
                    <div class="data-grid">
                        <div class="data-item"><span class="data-label">Municipios Totales</span><span class="data-value"><?php echo number_format($d['mun_totales'] ?? 0); ?></span></div>
                        <div class="data-item"><span class="data-label">Con Presencia</span><span class="data-value"><?php echo number_format($d['mun_presencia'] ?? 0); ?></span></div>
                        <div class="data-item"><span class="data-label">Afiliados Registrados</span><span class="data-value"><?php echo number_format($d['afil_registrados'] ?? 0); ?></span></div>
                        <div class="data-item"><span class="data-label">Afiliados Activos</span><span class="data-value"><?php echo number_format($d['afil_activos'] ?? 0); ?></span></div>
                        <div class="data-item"><span class="data-label">Promotores Registrados</span><span class="data-value"><?php echo number_format($d['promo_registrados'] ?? 0); ?></span></div>
                        <div class="data-item"><span class="data-label">Promotores Confirmados</span><span class="data-value"><?php echo number_format($d['promo_confirmados'] ?? 0); ?></span></div>
                        <div class="data-item" style="grid-column: span 2; background: #e8f5e9; border-color: #c8e6c9;">
                            <span class="data-label" style="color: var(--primary);">Presupuesto Anual</span>
                            <span class="data-value" style="color: var(--primary);">$ <?php echo number_format($d['presupuesto'] ?? 0, 2); ?></span>
                        </div>
                    </div>

                    <div class="grid-2">
                        <div>
                            <div class="diag-section-title"><i class="fas fa-building"></i> Infraestructura / Oficinas</div>
                            <div class="data-grid" style="grid-template-columns: 1fr 1fr 1fr;">
                                <div class="data-item"><span class="data-label">Municipal</span><span class="data-value"><?php echo number_format($d['ofi_municipal'] ?? 0); ?></span></div>
                                <div class="data-item"><span class="data-label">Regional</span><span class="data-value"><?php echo number_format($d['ofi_regional'] ?? 0); ?></span></div>
                                <div class="data-item"><span class="data-label">Estatal</span><span class="data-value"><?php echo number_format($d['ofi_estatal'] ?? 0); ?></span></div>
                            </div>
                        </div>
                        <div>
                            <div class="diag-section-title"><i class="fas fa-hashtag"></i> Plataformas Digitales</div>
                            <table class="custom-table" style="font-size: 11px;">
                                <thead style="background: #f1f3f5;"><tr><th>Plataforma</th><th>Link</th><th>Seguidores</th></tr></thead>
                                <tbody>
                                    <?php foreach(['facebook', 'x', 'instagram', 'otro'] as $plat): ?>
                                    <tr>
                                        <td style="text-transform: capitalize;"><strong><?php echo $plat === 'x' ? 'X.com' : $plat; ?></strong></td>
                                        <td>
                                            <?php if(!empty($d["link_$plat"])): ?>
                                                <a href="<?php echo htmlspecialchars($d["link_$plat"]); ?>" target="_blank" style="color:var(--primary);"><i class="fas fa-external-link-alt"></i> Ver</a>
                                            <?php else: ?> <span style="color:#ccc;">-</span> <?php endif; ?>
                                        </td>
                                        <td><?php echo number_format($d["seg_$plat"] ?? 0); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="diag-section-title"><i class="fas fa-chart-bar"></i> Datos Electorales Históricos</div>
                    <div class="table-actions-container" style="min-height: auto; overflow-x: auto; margin-bottom: 20px;">
                        <table class="custom-table" style="min-width: 600px; text-align: center;">
                            <thead style="background: #f1f3f5;">
                                <tr>
                                    <th rowspan="2" style="vertical-align: middle; text-align: center;">Año</th>
                                    <th rowspan="2" style="vertical-align: middle; text-align: center;">Lista Nominal</th>
                                    <th rowspan="2" style="vertical-align: middle; text-align: center;">Participación</th>
                                    <th colspan="2" style="text-align: center; border-left: 1px solid #ddd;">ESTATAL</th>
                                    <th colspan="2" style="text-align: center; border-left: 1px solid #ddd;">FEDERAL</th>
                                </tr>
                                <tr>
                                    <th style="border-left: 1px solid #ddd; text-align: center;">Votos</th>
                                    <th style="text-align: center;">%</th>
                                    <th style="border-left: 1px solid #ddd; text-align: center;">Votos</th>
                                    <th style="text-align: center;">%</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach([2012, 2015, 2018, 2021, 2024] as $anio): 
                                    $e = $data['diag_electoral'][$anio] ?? null; ?>
                                <tr>
                                    <td><strong><?php echo $anio; ?></strong></td>
                                    <td><?php echo number_format($e['lista_nominal'] ?? 0); ?></td>
                                    <td><?php echo htmlspecialchars($e['participacion'] ?? '0'); ?>%</td>
                                    <td style="border-left: 1px solid #eee;"><?php echo number_format($e['votos_est'] ?? 0); ?></td>
                                    <td><?php echo htmlspecialchars($e['porc_est'] ?? '0'); ?>%</td>
                                    <td style="border-left: 1px solid #eee;"><?php echo number_format($e['votos_fed'] ?? 0); ?></td>
                                    <td><?php echo htmlspecialchars($e['porc_fed'] ?? '0'); ?>%</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="diag-section-title"><i class="fas fa-poll"></i> Encuestas e Intención de Voto</div>
                    <div class="table-actions-container" style="min-height: auto; overflow-x: auto; margin-bottom: 20px;">
                        <table class="custom-table">
                            <thead style="background: #f1f3f5;">
                                <tr><th>Año</th><th>Fuente</th><th>Fecha</th><th>Intención PVEM</th><th>Enlace</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach([2012, 2015, 2018, 2021, 2024] as $anio): 
                                    $e = $data['diag_electoral'][$anio] ?? null; 
                                    if($e && (!empty($e['encuesta_fuente']) || !empty($e['encuesta_intencion']))):
                                ?>
                                <tr>
                                    <td><strong><?php echo $anio; ?></strong></td>
                                    <td><?php echo htmlspecialchars($e['encuesta_fuente'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($e['encuesta_fecha'] ?? '-'); ?></td>
                                    <td><strong style="color: var(--primary);"><?php echo htmlspecialchars($e['encuesta_intencion'] ?? '0'); ?>%</strong></td>
                                    <td>
                                        <?php if(!empty($e['encuesta_link'])): ?>
                                            <a href="<?php echo htmlspecialchars($e['encuesta_link']); ?>" target="_blank" style="color:var(--primary);"><i class="fas fa-link"></i> Ver Fuente</a>
                                        <?php else: ?> <span style="color:#ccc;">-</span> <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endif; endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="diag-section-title"><i class="fas fa-project-diagram"></i> Análisis FODA</div>
                    <div class="grid-2">
                        <div style="background: #e8f5e9; padding: 15px; border-radius: 10px; border-left: 4px solid #2d7a3e;">
                            <h5 style="color: #2d7a3e; margin-bottom: 10px;"><i class="fas fa-arrow-up"></i> Fortalezas Internas</h5>
                            <p style="font-size: 13px; white-space: pre-line; margin: 0;"><?php echo htmlspecialchars($d['foda_fortalezas'] ?: 'Sin definir.'); ?></p>
                        </div>
                        <div style="background: #ffebee; padding: 15px; border-radius: 10px; border-left: 4px solid #d32f2f;">
                            <h5 style="color: #d32f2f; margin-bottom: 10px;"><i class="fas fa-arrow-down"></i> Debilidades Internas</h5>
                            <p style="font-size: 13px; white-space: pre-line; margin: 0;"><?php echo htmlspecialchars($d['foda_debilidades'] ?: 'Sin definir.'); ?></p>
                        </div>
                        <div style="background: #e3f2fd; padding: 15px; border-radius: 10px; border-left: 4px solid #1976d2;">
                            <h5 style="color: #1976d2; margin-bottom: 10px;"><i class="fas fa-lightbulb"></i> Oportunidades Externas</h5>
                            <p style="font-size: 13px; white-space: pre-line; margin: 0;"><?php echo htmlspecialchars($d['foda_oportunidades'] ?: 'Sin definir.'); ?></p>
                        </div>
                        <div style="background: #fff3e0; padding: 15px; border-radius: 10px; border-left: 4px solid #f57c00;">
                            <h5 style="color: #f57c00; margin-bottom: 10px;"><i class="fas fa-exclamation-triangle"></i> Amenazas Externas</h5>
                            <p style="font-size: 13px; white-space: pre-line; margin: 0;"><?php echo htmlspecialchars($d['foda_amenazas'] ?: 'Sin definir.'); ?></p>
                        </div>
                    </div>

                    <div class="diag-section-title"><i class="fas fa-tasks"></i> Problemas Críticos y KPI's</div>
                    <div class="grid-3" style="margin-bottom: 20px;">
                        <?php for($i=1; $i<=3; $i++): ?>
                        <div class="content-card" style="margin-bottom:0; box-shadow:none; background:#f8f9fa;">
                            <span class="card-tag" style="position:static; display:inline-block; margin-bottom:10px;">Problema <?php echo $i; ?></span>
                            <h4 style="margin-top:0; font-size:15px; color:var(--dark); margin-bottom:5px;"><?php echo htmlspecialchars($d["problema_{$i}_titulo"] ?: 'No definido'); ?></h4>
                            <p style="font-size:12px; color:#666; margin-bottom:15px; min-height: 40px;"><?php echo htmlspecialchars($d["problema_{$i}_desc"] ?: 'Sin descripción detallada.'); ?></p>
                            <div style="border-top: 1px solid #ddd; padding-top: 10px;">
                                <strong style="font-size:11px; color:#888; text-transform:uppercase;">KPI's Asignados:</strong>
                                <ul style="font-size:12px; padding-left:15px; margin-top:5px; color:#333;">
                                    <li><?php echo htmlspecialchars($d["kpi_{$i}_1"] ?: '-'); ?></li>
                                    <li><?php echo htmlspecialchars($d["kpi_{$i}_2"] ?: '-'); ?></li>
                                    <li><?php echo htmlspecialchars($d["kpi_{$i}_3"] ?: '-'); ?></li>
                                </ul>
                            </div>
                        </div>
                        <?php endfor; ?>
                    </div>

                    <div class="grid-2">
                        <div>
                            <div class="diag-section-title"><i class="fas fa-folder-open"></i> Documentos Cargados</div>
                            <?php if (empty($data['archivos'])): ?>
                                <p style="font-size: 13px; color: #999; background: #f9f9f9; padding: 15px; border-radius: 8px;">No se han subido documentos para este estado.</p>
                            <?php else: ?>
                                <div class="table-actions-container" style="min-height: auto; margin-top: 0;">
                                    <table class="custom-table" style="font-size: 11px;">
                                        <thead style="background: #f1f3f5;">
                                            <tr><th>Nombre del Archivo</th><th style="text-align: center;">Acción</th></tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($data['archivos'] as $archivo): ?>
                                                <tr>
                                                    <td title="<?php echo htmlspecialchars($archivo['nombre_original']); ?>" style="max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                        <i class="fas fa-file-pdf" style="color: #d32f2f; margin-right: 5px;"></i> <?php echo htmlspecialchars($archivo['nombre_original']); ?>
                                                    </td>
                                                    <td style="text-align: center;">
                                                        <a href="<?php echo htmlspecialchars($archivo['ruta_archivo']); ?>" target="_blank" style="color: var(--primary); text-decoration: none; font-weight: 800; background: #e8f5e9; padding: 4px 8px; border-radius: 4px;">
                                                            <i class="fas fa-download"></i> Bajar
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div>
                            <div class="diag-section-title"><i class="fas fa-link"></i> Enlaces Externos</div>
                            <div class="data-item" style="word-break: break-all; display: flex; flex-direction: column; gap: 8px; background: #f9f9f9;">
                                <?php 
                                if (!empty($d['links_adjuntos'])) {
                                    $lineas = explode("\n", $d['links_adjuntos']);
                                    foreach($lineas as $linea) {
                                        $linea = trim($linea);
                                        if(strpos($linea, 'http') === 0 || strpos($linea, 'www.') === 0) {
                                            $url = strpos($linea, 'http') !== 0 ? 'https://' . $linea : $linea;
                                            echo '<a href="' . htmlspecialchars($url) . '" target="_blank" style="color: var(--primary); text-decoration: none; font-weight: 800; background: #e8f5e9; padding: 8px 12px; border-radius: 6px; font-size: 12px; display: inline-block; width: fit-content; border: 1px solid #c8e6c9;"><i class="fas fa-external-link-alt" style="margin-right: 8px;"></i> ' . htmlspecialchars($linea) . '</a>';
                                        } else if (!empty($linea)) {
                                            echo '<p style="font-size: 12px; margin:0; color: #555;"><i class="fas fa-angle-right" style="color:#ccc; margin-right:5px;"></i> ' . htmlspecialchars($linea) . '</p>';
                                        }
                                    }
                                } else {
                                    echo '<p style="font-size: 13px; color: #999; margin:0;">Sin enlaces.</p>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>

                </div>
            <?php endif; ?>
        </div>
        
    </div>

    <div class="modal" id="stateModal">
        <div class="modal-content">
            <div class="close-modal" onclick="closeModal()"><i class="fas fa-times"></i></div>
            <h2 style="margin-bottom: 15px;">Gestión Administrativa</h2>
            <p style="font-size: 13px; color: #666; margin-bottom: 20px;">Administrar datos para: <strong><?php echo htmlspecialchars($estado_actual); ?></strong></p>
            
            <form id="createForm" onsubmit="handleCreateSubmit(event)">
                <input type="hidden" name="accion" value="crear">
                <input type="hidden" name="estado" value="<?php echo htmlspecialchars($estado_actual); ?>">
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 12px; font-weight: bold; margin-bottom: 5px;">¿Qué deseas administrar?</label>
                    <select id="createType" name="tipo" style="width: 100%; padding: 12px; border: 1px solid var(--primary); border-radius: 8px; font-weight: bold; color: var(--primary);" onchange="toggleCreateFields()">
                        <option value="objetivo">Objetivo (Nuevo)</option>
                        <option value="meta">Meta (Nueva)</option>
                        <option value="estrategia">Estrategia (Nueva)</option>
                        <option value="cronograma">Actividad Cronograma (Nueva)</option>
                        <option value="vision_mision">Editar Visión y Misión</option>
                    </select>
                </div>

                <div id="divVMFields" style="display: none; background: #f0f7f0; padding: 15px; border-radius: 10px; margin-bottom: 15px;">
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; font-size: 12px; font-weight: bold; margin-bottom: 5px;">Visión</label>
                        <textarea id="vmVision" name="vision" rows="4" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 8px;"></textarea>
                    </div>
                    <div style="margin-bottom: 0;">
                        <label style="display: block; font-size: 12px; font-weight: bold; margin-bottom: 5px;">Misión</label>
                        <textarea id="vmMision" name="mision" rows="4" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 8px;"></textarea>
                    </div>
                </div>

                <div id="divCronoFields" style="display: none; background: #e3f2fd; padding: 15px; border-radius: 10px; margin-bottom: 15px;">
                    <div style="margin-bottom: 10px;">
                        <label style="display: block; font-size: 11px; font-weight: bold;">Fecha Visual (Texto, ej: "Enero 2026")</label>
                        <input type="text" name="fecha_visual" class="crono-input" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px;">
                    </div>
                    <div style="margin-bottom: 10px;">
                        <label style="display: block; font-size: 11px; font-weight: bold;">Fecha Orden</label>
                        <input type="date" name="fecha_orden" class="crono-input" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px;">
                    </div>
                </div>

                <div id="divMetaFields" style="display: none; background: #fff8e1; padding: 15px; border-radius: 10px; margin-bottom: 15px;">
                    <label style="display: block; font-size: 12px; font-weight: bold; margin-bottom: 5px;">Descripción de la Meta</label>
                    <textarea name="titulo" class="meta-input" rows="3" placeholder="Ej: Lograr el 11% de la votación..." style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;"></textarea>
                </div>

                <div id="divGenericFields">
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; font-size: 12px; font-weight: bold; margin-bottom: 5px;">Título</label>
                        <input type="text" name="titulo" class="generic-input" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; font-size: 12px; font-weight: bold; margin-bottom: 5px;">Descripción</label>
                        <textarea name="descripcion" class="generic-input" rows="3" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;"></textarea>
                    </div>
                </div>

                <div style="text-align: right; gap: 10px; display: flex; justify-content: flex-end;">
                    <button type="button" onclick="closeModal()" style="padding: 10px 20px; background: #eee; border: none; border-radius: 5px; cursor: pointer;">Cancelar</button>
                    <button type="submit" style="padding: 10px 20px; background: var(--primary); color: white; border: none; border-radius: 5px; cursor: pointer;">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal" id="editModal" style="z-index: 3001;">
        <div class="modal-content">
            <h2 style="margin-bottom: 15px;">Modificar Elemento</h2>
            <form id="editForm" onsubmit="handleEditSubmit(event)">
                <input type="hidden" id="editId">
                <input type="hidden" id="editType">
                
                <div style="margin-bottom: 15px;" id="divEditTitle">
                    <label style="display: block; font-size: 12px; font-weight: bold; margin-bottom: 5px;">Título</label>
                    <input type="text" id="editTitle" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
                </div>
                
                <div id="divEditCronoFields" style="display: none; background: #f9f9f9; padding: 10px; border-radius: 8px; margin-bottom: 15px;">
                     <div style="margin-bottom: 10px;"><label style="font-size: 11px; font-weight: bold;">Fecha Visual</label><input type="text" id="editFechaVis" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px;"></div>
                     <div style="margin-bottom: 10px;"><label style="font-size: 11px; font-weight: bold;">Fecha Orden</label><input type="date" id="editFechaOrd" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px;"></div>
                </div>

                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-size: 12px; font-weight: bold; margin-bottom: 5px;">Descripción</label>
                    <textarea id="editDesc" rows="4" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;" required></textarea>
                </div>

                <div style="text-align: right; gap: 10px; display: flex; justify-content: flex-end; margin-top: 15px;">
                    <button type="button" onclick="closeEditModal()" style="padding: 10px 20px; background: #eee; border: none; border-radius: 5px; cursor: pointer;">Cancelar</button>
                    <button type="submit" style="padding: 10px 20px; background: var(--primary); color: white; border: none; border-radius: 5px; cursor: pointer;">Actualizar</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        const API_URL = '/api/gestion_estatal/crud_estatal.php';

        function switchTab(tabName) {
            localStorage.setItem('activeTab', tabName);
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            
            const activeContent = document.getElementById('tab-' + tabName);
            if(activeContent) activeContent.classList.add('active');
            
            const activeBtn = document.querySelector(`.tab-btn[data-tab="${tabName}"]`);
            if(activeBtn) activeBtn.classList.add('active');
        }

        document.addEventListener('DOMContentLoaded', () => {
            const savedTab = localStorage.getItem('activeTab') || 'vision';
            switchTab(savedTab);
            toggleCreateFields(); 
        });

        const modal = document.getElementById('stateModal');
        const editModal = document.getElementById('editModal');

        function openModal() { modal.style.display = 'flex'; toggleCreateFields(); }
        function closeModal() { modal.style.display = 'none'; }
        function closeEditModal() { editModal.style.display = 'none'; }

        function toggleCreateFields() {
            const type = document.getElementById('createType').value;
            const cronoDiv = document.getElementById('divCronoFields');
            const metaDiv = document.getElementById('divMetaFields');
            const vmDiv = document.getElementById('divVMFields');
            const genericDiv = document.getElementById('divGenericFields');
            
            const cronoInputs = cronoDiv.querySelectorAll('input, select, textarea');
            const metaInputs = metaDiv.querySelectorAll('input, select, textarea');
            const vmInputs = vmDiv.querySelectorAll('input, select, textarea');
            const genericInputs = genericDiv.querySelectorAll('input, select, textarea');

            const setInputState = (nodeList, isActive) => {
                nodeList.forEach(input => { input.disabled = !isActive; });
            };

            cronoDiv.style.display = 'none'; metaDiv.style.display = 'none';
            vmDiv.style.display = 'none'; genericDiv.style.display = 'none';

            setInputState(cronoInputs, false); setInputState(metaInputs, false);
            setInputState(vmInputs, false); setInputState(genericInputs, false);

            if(type === 'cronograma') {
                cronoDiv.style.display = 'block'; genericDiv.style.display = 'block';
                setInputState(cronoInputs, true); setInputState(genericInputs, true);
            } 
            else if (type === 'meta') {
                metaDiv.style.display = 'block'; setInputState(metaInputs, true);
            }
            else if (type === 'vision_mision') {
                vmDiv.style.display = 'block'; setInputState(vmInputs, true);
                if (typeof currentVision !== 'undefined') document.getElementById('vmVision').value = currentVision;
                if (typeof currentMision !== 'undefined') document.getElementById('vmMision').value = currentMision;
            }
            else {
                genericDiv.style.display = 'block'; setInputState(genericInputs, true);
            }
        }

        function toggleMenu(event, menuId) {
            event.stopPropagation();
            document.querySelectorAll('.menu-content').forEach(m => { if(m.id !== menuId) m.classList.remove('show'); });
            document.getElementById(menuId).classList.toggle('show');
        }

        window.onclick = function(event) {
            if (!event.target.matches('.menu-btn') && !event.target.matches('.menu-btn i')) {
                document.querySelectorAll('.menu-content').forEach(m => m.classList.remove('show'));
            }
            if (event.target === modal) closeModal();
            if (event.target === editModal) closeEditModal();
        }

        async function handleCreateSubmit(e) {
            e.preventDefault();
            const formData = new FormData(e.target);
            const data = Object.fromEntries(formData.entries());
            try {
                const response = await fetch(API_URL, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(data)
                });
                const res = await response.json();
                if(res.success) { location.reload(); } else { alert('Error: ' + res.message); }
            } catch(e) { alert('Error de conexión con la API'); }
        }

        async function deleteItem(id, type) {
            if(!confirm('¿Estás seguro de eliminar este elemento?')) return;
            try {
                const response = await fetch(API_URL, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ accion: 'eliminar', id: id, tipo: type })
                });
                const res = await response.json();
                if(res.success) { location.reload(); } else { alert('Error: ' + res.message); }
            } catch(e) { alert('Error de conexión'); }
        }

        function openEditModal(id, type, title, desc, resp = '', plazo = '', rec = '', ind = '', fvis = '', ford = '') {
            document.getElementById('editId').value = id;
            document.getElementById('editType').value = type;
            document.getElementById('editDesc').value = desc;
            const divTitle = document.getElementById('divEditTitle');
            const inpTitle = document.getElementById('editTitle');
            const divCrono = document.getElementById('divEditCronoFields');
            if(type === 'meta') {
                divTitle.style.display = 'none'; inpTitle.required = false;
            } else {
                divTitle.style.display = 'block'; inpTitle.value = title; inpTitle.required = true;
            }
            if(type === 'cronograma') {
                divCrono.style.display = 'block';
                document.getElementById('editFechaVis').value = fvis;
                document.getElementById('editFechaOrd').value = ford;
            } else { divCrono.style.display = 'none'; }
            editModal.style.display = 'flex';
        }

        async function handleEditSubmit(e) {
            e.preventDefault();
            const id = document.getElementById('editId').value;
            const type = document.getElementById('editType').value;
            let tituloRaw = document.getElementById('editTitle').value;
            const descRaw = document.getElementById('editDesc').value;
            if(type === 'meta') { tituloRaw = descRaw; }
            let extraData = {};
            if(type === 'cronograma') {
                extraData = { fecha_visual: document.getElementById('editFechaVis').value, fecha_orden: document.getElementById('editFechaOrd').value };
            }
            try {
                const response = await fetch(API_URL, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ accion: 'editar', id: id, tipo: type, titulo: tituloRaw, descripcion: descRaw, ...extraData })
                });
                const res = await response.json();
                if(res.success) { location.reload(); } else { alert('Error: ' + res.message); }
            } catch(e) { alert('Error de conexión'); }
        }
    </script>
</body>
</html>