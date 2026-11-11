<?php
// 1. Carga del sistema y seguridad
require_once __DIR__ . '/../../includes/bootstrap.php';

$rol = $_SESSION['user_role'] ?? '';
$permitidos = ['admin', 'administrador', 'superadmin', 'super admin'];

if (!in_array($rol, $permitidos)) {
    header('Location: /public/index.php'); 
    exit();
}
// Conexión
$db = Database::getInstance()->getConnection();

// --- NUEVA ESTRUCTURA: CIRCUNSCRIPCIONES DE MÉXICO ---
$circunscripciones = [
    'Circunscripción 1' => [
        'sede' => 'Guadalajara, Jalisco',
        'estados' => ['Baja California', 'Baja California Sur', 'Chihuahua', 'Durango', 'Jalisco', 'Nayarit', 'Sinaloa', 'Sonora']
    ],
    'Circunscripción 2' => [
        'sede' => 'Monterrey, Nuevo León',
        'estados' => ['Aguascalientes', 'Coahuila', 'Guanajuato', 'Nuevo León', 'San Luis Potosí', 'Tamaulipas', 'Zacatecas']
    ],
    'Circunscripción 3' => [
        'sede' => 'Xalapa, Veracruz',
        'estados' => ['Campeche', 'Chiapas', 'Oaxaca', 'Quintana Roo', 'Tabasco', 'Veracruz', 'Yucatán']
    ],
    'Circunscripción 4' => [
        'sede' => 'Ciudad de México',
        'estados' => ['Ciudad de México', 'Guerrero', 'Hidalgo', 'Morelos', 'Puebla', 'Tlaxcala']
    ],
    'Circunscripción 5' => [
        'sede' => 'Toluca, Edo. de México',
        'estados' => ['Colima', 'Estado de México', 'Michoacán', 'Querétaro']
    ]
];

// Extraer todos los estados para el modal (ordenados alfabéticamente)
$estados_disponibles = [];
foreach($circunscripciones as $data) {
    $estados_disponibles = array_merge($estados_disponibles, $data['estados']);
}
sort($estados_disponibles);

// Función auxiliar para determinar la circunscripción de un estado
function getCircunscripcionId($estadoBuscado, $circunscripciones) {
    if (empty($estadoBuscado)) return 'all';
    
    $estadoBuscado = mb_strtolower(trim($estadoBuscado), 'UTF-8');
    
    $idx = 1;
    foreach($circunscripciones as $nombre => $data) {
        foreach($data['estados'] as $e) {
            if(mb_strtolower(trim($e), 'UTF-8') === $estadoBuscado) {
                return "circ_" . $idx;
            }
        }
        $idx++;
    }
    return 'none'; 
}

// Función actualizada para leer de múltiples tablas usando UNION ALL
function obtenerTodosLosUsuarios($db) {
    $usuarios = [];
    try {
        $sql = "
            SELECT 
                id, nombre_completo AS nombres, telefono, correo AS email, 
                colonia, direccion AS calle, seccion_electoral, 
                IF(activo = 1, 'activo', 'inactivo') AS estatus, 
                estado, region, zona, fecha_registro, 'usuarios' AS tabla_origen, rol AS rol_tabla 
            FROM usuarios 
            
            UNION ALL 
            
            SELECT 
                id, nombre AS nombres, telefono, email, 
                colonia, calle, seccion_electoral, 
                estatus, 
                estado, region, NULL AS zona, fecha_registro, 'coordinador_regional' AS tabla_origen, 'coordinador_regional' AS rol_tabla 
            FROM coordinador_regional
            
            UNION ALL
            
            SELECT 
                id, nombre AS nombres, telefono, email, 
                colonia, calle, seccion_electoral, 
                estatus, 
                estado, region, zona, fecha_registro, 'coordinador_zona' AS tabla_origen, 'coordinador_zona' AS rol_tabla 
            FROM coordinador_zona
            
            ORDER BY fecha_registro DESC
        ";
                
        $stmt = $db->query($sql);
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }

    usort($usuarios, function($a, $b) {
        $estA = $a['estado'] ?? '';
        $estB = $b['estado'] ?? '';
        if ($estA == $estB) {
            return strcmp($a['nombres'], $b['nombres']);
        }
        return strcmp($estA, $estB);
    });

    return $usuarios;
}

$usuarios = obtenerTodosLosUsuarios($db);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Equipo - Proyecto Verde</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* --- ESTILOS --- */
        :root { --primary: #2d7a3e; --primary-dark: #1f5a2d; --dark: #1a1a1a; --light: #f0f7f0; --white: #ffffff; --gray-100: #f8f9fa; --gray-200: #e9ecef; --text-muted: #6c757d; --shadow-sm: 0 10px 30px rgba(0,0,0,0.05); }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; background: var(--light); color: var(--dark); overflow-x: hidden; }
        .container { max-width: 100%; margin: 0; background: var(--white); min-height: 100vh; position: relative; padding-left: 330px; padding-right: 50px; padding-bottom: 40px; transition: padding-left 0.3s ease; }
        .design-text { position: fixed; left: 290px; top: 50%; writing-mode: vertical-rl; font-size: 13px; font-weight: 500; color: var(--primary); opacity: 0.3; letter-spacing: 2px; z-index: 0; pointer-events: none; }
        
        .page-header { padding: 50px 0 30px 0; display: flex; justify-content: space-between; align-items: flex-end; position: relative; z-index: 2; }
        .ai-badge { color: var(--primary); font-size: 13px; font-weight: 800; letter-spacing: 4px; margin-bottom: 15px; display: inline-block; text-transform: uppercase; background: rgba(45,122,62,0.1); padding: 5px 15px; border-radius: 20px; }
        .main-title { font-size: 54px; font-weight: 900; line-height: 1; margin-bottom: 15px; color: var(--dark); }
        .description { color: #666; font-size: 16px; line-height: 1.6; max-width: 600px; }
        
        .btn-primary-action { background: var(--primary); color: white; border: none; padding: 12px 25px; border-radius: 12px; font-weight: 700; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; gap: 10px; font-size: 14px; box-shadow: 0 4px 15px rgba(45, 122, 62, 0.3); }
        .btn-primary-action:hover { background: var(--primary-dark); transform: translateY(-2px); }

        /* --- FILTRO DE CIRCUNSCRIPCIONES --- */
        .circunscripcion-filter { display: flex; align-items: center; margin-bottom: 15px; gap: 10px; position: relative; z-index: 5; overflow-x: auto; overflow-y: hidden; white-space: nowrap; scrollbar-width: thin; padding-bottom: 5px; }
        .circ-label { font-weight: 800; color: var(--text-muted); font-size: 13px; margin-right: 10px; text-transform: uppercase; letter-spacing: 1px; flex-shrink: 0; }
        .circ-btn { padding: 8px 18px; border-radius: 30px; background: var(--white); border: 1px solid var(--gray-200); color: var(--text-muted); font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: var(--shadow-sm); flex-shrink: 0; }
        .circ-btn:hover { border-color: var(--primary); color: var(--primary); }
        .circ-btn.active { background: var(--primary); color: var(--white); border-color: var(--primary); }
        .circ-sede { display: block; font-size: 10px; font-weight: 500; opacity: 0.8; margin-top: 3px; }

        /* --- PESTAÑAS DE ESTADOS --- */
        .tabs-wrapper { background: var(--white); border-radius: 16px; padding: 0px 20px 0 20px; box-shadow: var(--shadow-sm); margin-bottom: 25px; border: 1px solid var(--gray-200); position: relative; z-index: 5; }
        .tabs-header { display: flex; overflow-x: auto; overflow-y: hidden; scrollbar-width: thin; }
        .tab-btn { padding: 18px 24px; background: transparent; border: none; border-bottom: 3px solid transparent; font-size: 14px; font-weight: 700; color: var(--text-muted); cursor: pointer; transition: all 0.2s; white-space: nowrap; margin-bottom: -2px; display: flex; align-items: center; gap: 8px; }
        .tab-btn:hover { color: var(--primary); }
        .tab-btn.active { color: var(--primary); border-bottom-color: var(--primary); }
        .tab-btn i { font-size: 12px; opacity: 0.7; }
        .tab-btn.active i { opacity: 1; }
        .tab-btn[style*="display: none"] { display: none !important; }

        .grid-overview { display: grid; grid-template-columns: repeat(7, 1fr); gap: 15px; margin-bottom: 30px; }
        .stat-card { background: var(--white); border: 1px solid var(--gray-200); padding: 15px; border-radius: 16px; text-align: center; box-shadow: var(--shadow-sm); position: relative; overflow: hidden; display: flex; flex-direction: column; align-items: center; justify-content: center; transition: all 0.3s ease; }
        .stat-card:hover { transform: translateY(-5px); border-color: var(--primary); }
        .stat-card i { font-size: 24px; color: var(--primary); margin-bottom: 10px; opacity: 0.8; }
        .stat-card .number { font-size: 28px; font-weight: 900; color: var(--dark); line-height: 1; }
        .stat-card .label { font-size: 10px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-top: 5px; }

        .search-container { background: var(--white); border: 1px solid var(--gray-200); border-radius: 16px; padding: 20px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: var(--shadow-sm); }
        .form-input-styled { width: 100%; padding: 12px 15px; border-radius: 10px; border: 2px solid var(--gray-200); background: var(--gray-100); font-size: 14px; font-weight: 600; color: var(--dark); transition: all 0.3s; outline: none; }
        .form-input-styled:focus { border-color: var(--primary); background: var(--white); }

        .table-section { background: var(--white); border-radius: 20px; padding: 30px; border: 1px solid var(--gray-200); box-shadow: var(--shadow-sm); }
        .section-header { margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; }
        .modern-table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .modern-table th { text-align: left; padding: 15px 20px; color: var(--text-muted); font-size: 12px; text-transform: uppercase; letter-spacing: 1px; border-bottom: 2px solid var(--gray-200); }
        .modern-table td { padding: 20px; border-bottom: 1px solid var(--gray-200); font-size: 14px; color: var(--dark); font-weight: 500; vertical-align: middle; }
        
        /* ESTILOS DE ORDENAMIENTO (SORTING) */
        .sortable { cursor: pointer; user-select: none; transition: background-color 0.2s; position: relative; }
        .sortable:hover { background-color: var(--gray-100); border-radius: 8px 8px 0 0; }
        .sort-icon { display: inline-block; margin-left: 6px; font-size: 14px; opacity: 0.3; transition: all 0.2s; }
        .sortable.active { color: var(--primary); font-weight: 800; }
        .sortable.active .sort-icon { opacity: 1; color: var(--primary); }

        /* BADGES ACTUALIZADOS PARA NUEVOS ROLES */
        .badge { padding: 6px 12px; border-radius: 30px; font-size: 11px; font-weight: 700; text-transform: uppercase; display: inline-block; }
        .badge-estatal, .badge-coordinador_estatal { background: #ffebee; color: #c62828; }
        .badge-regional, .badge-coordinador_regional { background: #e3f2fd; color: #1565c0; }
        .badge-zona, .badge-coordinador_zona { background: #fff3e0; color: #ef6c00; }
        .badge-seccion, .badge-coordinador_seccion { background: #e0f2f1; color: #00796b; }
        .badge-operador { background: #e8f5e9; color: #2e7d32; }
        .badge-movilizador { background: #f3e5f5; color: #7b1fa2; }
        .badge-ciudadano { background: #e0f7fa; color: #00838f; }
        .badge-activo { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        .badge-inactivo { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }

        .state-badge { background: #f4fcf6; color: var(--primary); border: 1px solid #c8e6c9; padding: 5px 10px; border-radius: 8px; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; gap: 5px; }

        .action-btn { background: transparent; border: none; color: #aaa; cursor: pointer; font-size: 16px; padding: 8px; border-radius: 50%; }
        .action-btn:hover { background: var(--gray-200); color: var(--dark); }
        .dropdown-menu { position: absolute; right: 0; background: white; border: 1px solid var(--gray-200); box-shadow: 0 10px 30px rgba(0,0,0,0.1); border-radius: 10px; width: 160px; opacity: 0; visibility: hidden; transform: translateY(10px); transition: 0.2s; z-index: 50; }
        .dropdown-menu.show { opacity: 1; visibility: visible; transform: translateY(0); }
        .dropdown-item { display: flex; align-items: center; gap: 10px; padding: 12px 15px; color: var(--dark); text-decoration: none; font-size: 13px; cursor: pointer; }
        .dropdown-item:hover { background: var(--gray-100); color: var(--primary); }

        .pagination-controls { display: flex; justify-content: center; align-items: center; gap: 10px; margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--gray-200); }
        .pagination-btn { background: var(--white); border: 2px solid var(--gray-200); color: var(--dark); padding: 10px 18px; border-radius: 10px; font-weight: 600; cursor: pointer; transition: all 0.3s; font-size: 14px; }
        .pagination-btn:hover:not(:disabled) { background: var(--primary); color: white; border-color: var(--primary); }
        .pagination-btn:disabled { opacity: 0.4; cursor: not-allowed; }
        .pagination-info { font-size: 14px; color: var(--text-muted); font-weight: 600; padding: 0 15px; }

        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(5px); z-index: 2000; align-items: center; justify-content: center; }
        .modal.open { display: flex; }
        .modal-content { background: var(--white); border-radius: 20px; border: 1px solid var(--gray-200); box-shadow: 0 25px 50px rgba(0,0,0,0.1); width: 90%; max-width: 700px; padding: 40px; position: relative; transform: translateY(20px); transition: transform 0.3s; max-height: 90vh; overflow-y: auto; }
        .modal.open .modal-content { transform: translateY(0); }
        .close-modal { position: absolute; top: 20px; right: 20px; font-size: 20px; cursor: pointer; color: #ccc; }
        .toast { position: fixed; bottom: 30px; right: 30px; background: var(--primary); color: white; padding: 15px 30px; border-radius: 50px; z-index: 2000; display: none; }
        .toast.show { display: block; }
        .toast.error { background: #e74c3c; }
        .form-group { display: flex; flex-direction: column; gap: 8px; margin-bottom: 20px; }
        .form-label { font-size: 12px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); }

        @media (max-width: 1024px) {
            .container { padding-left: 20px; padding-right: 20px; }
            .grid-overview { grid-template-columns: repeat(3, 1fr); }
            .page-header { flex-direction: column; align-items: flex-start; gap: 20px; }
            .design-text { display: none; }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/components/sidebar_admin.php'; ?>
    
    <div class="design-text">EQUIPO 2025</div>

    <div class="container">
        <div class="page-header animate-up">
            <div>
                <div class="ai-badge">GESTIÓN DE EQUIPO</div>
                <h1 class="main-title">Directorio de<br>Equipo de Trabajo.</h1>
                <p class="description">Administración, seguimiento y asignación de roles a todo el personal operativo.</p>
            </div>
            <div>
                <button class="btn-primary-action" onclick="window.location.href='/App/admin/crear_usuario.php'">
                    <p class="fas fa-user-plus"></p> Nuevo Usuario
                </button>
            </div>
        </div>

        <div class="grid-overview animate-up animate-delay-1">
            <div class="stat-card"><i class="fas fa-users"></i><span class="number" id="stat-total">0</span><span class="label" id="label-total">Total Nacional</span></div>
            <div class="stat-card"><i class="fas fa-crown"></i><span class="number" id="stat-coordinador_estatal">0</span><span class="label">Estatal</span></div>
            <div class="stat-card"><i class="fas fa-city"></i><span class="number" id="stat-coordinador_municipal">0</span><span class="label">Regional / Mun.</span></div>
            <div class="stat-card"><i class="fas fa-map-pin"></i><span class="number" id="stat-responsable_zona">0</span><span class="label">Resp. Zona</span></div>
            <div class="stat-card"><i class="fas fa-user-tag"></i><span class="number" id="stat-seccion">0</span><span class="label">Sección</span></div>
        </div>

        <div class="search-container animate-up animate-delay-2">
            <div style="flex: 2;">
                <div style="position: relative;">
                    <i class="fas fa-search" style="position: absolute; left: 15px; top: 14px; color: #999;"></i>
                    <input type="text" id="searchInput" class="form-input-styled" style="padding-left: 40px;" placeholder="Buscar por nombre, teléfono o sección..." oninput="updateDashboard()">
                </div>
            </div>
        </div>
        
        <div class="circunscripcion-filter animate-up animate-delay-1">
            <span class="circ-label"><i class="fas fa-map"></i> Circunscripción:</span>
            <button class="circ-btn active" data-circ="all" onclick="selectCircunscripcion('all')">Todas (Nacional)</button>
            <?php 
            $i = 1;
            foreach($circunscripciones as $circ_nombre => $data): 
            ?>
            <button class="circ-btn" data-circ="circ_<?php echo $i; ?>" onclick="selectCircunscripcion('circ_<?php echo $i; ?>')" title="Cabecera: <?php echo $data['sede']; ?>">
                <?php echo $circ_nombre; ?>
                <span class="circ-sede">
                    <i class="fas fa-building"></i> Sede: <?php echo $data['sede']; ?>
                </span>
            </button>
            <?php $i++; endforeach; ?>
        </div>

        <div class="tabs-wrapper animate-up animate-delay-1">
            <div class="tabs-header" id="state-tabs-container">
                <button class="tab-btn active state-tab-all" data-state="all" onclick="switchTab(this, 'all')">
                    <i class="fas fa-globe"></i> Todos los Estados
                </button>
                <?php 
                $c = 1;
                foreach ($circunscripciones as $circ_nombre => $data): 
                    foreach ($data['estados'] as $estado): 
                ?>
                <button class="tab-btn state-tab-item" data-circ="circ_<?php echo $c; ?>" data-state="<?php echo strtolower($estado); ?>" onclick="switchTab(this, '<?php echo strtolower($estado); ?>')">
                    <i class="fas fa-map-marker-alt"></i> <?php echo $estado; ?>
                </button>
                <?php 
                    endforeach; 
                $c++;
                endforeach; 
                ?>
            </div>
            <input type="hidden" id="stateFilter" value="all">
        </div>

        <div class="table-section animate-up animate-delay-2">
            <div class="section-header">
                <h3 class="section-title">Directorio de Equipo</h3>
                <div class="ai-badge" style="font-size:10px;"><span id="visible-count">0</span> Registros visibles</div>
            </div>
            
            <div style="overflow-x: auto;">
                <table class="modern-table" id="teamTable">
                    <thead>
                        <tr>
                            <th class="sortable" onclick="sortTable(0, 'string')">Nombre <i class="fas fa-sort sort-icon"></i></th>
                            <th class="sortable" onclick="sortTable(1, 'string')">Rol Asignado <i class="fas fa-sort sort-icon"></i></th>
                            <th class="sortable" onclick="sortTable(2, 'string')">Estado <i class="fas fa-sort sort-icon"></i></th> 
                            <th class="sortable" onclick="sortTable(3, 'number')">Sección <i class="fas fa-sort sort-icon"></i></th>
                            <th class="sortable" onclick="sortTable(4, 'string')">Teléfono <i class="fas fa-sort sort-icon"></i></th>
                            <th class="sortable" onclick="sortTable(5, 'string')">Estatus <i class="fas fa-sort sort-icon"></i></th>
                            <th style="text-align: right;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="users-table-body">
                        <?php foreach ($usuarios as $usuario): ?>
                        <tr data-user-id="<?php echo $usuario['id']; ?>" 
                            data-user-table="<?php echo htmlspecialchars($usuario['tabla_origen']); ?>"
                            data-user-state="<?php echo strtolower($usuario['estado'] ?? ''); ?>"
                            data-user-circ="<?php echo getCircunscripcionId($usuario['estado'] ?? '', $circunscripciones); ?>">
                            
                            <td style="font-weight: 600;">
                                <?php echo htmlspecialchars(trim($usuario['nombres']) ?: 'Sin Nombre'); ?>
                            </td>
                            <td><span class="badge badge-<?php echo htmlspecialchars($usuario['rol_tabla']); ?>"><?php echo ucfirst(str_replace('_', ' ', $usuario['rol_tabla'])); ?></span></td>
                            <td>
                                <span class="state-badge">
                                    <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($usuario['estado'] ?? 'N/A'); ?>
                                </span>
                            </td>
                            <td style="color: #666;"><?php echo htmlspecialchars($usuario['seccion_electoral']); ?></td>
                            <td><?php echo htmlspecialchars($usuario['telefono']); ?></td>
                            <td><span class="badge badge-<?php echo htmlspecialchars($usuario['estatus']); ?>"><?php echo ucfirst($usuario['estatus']); ?></span></td>
                            <td style="text-align: right; position: relative;">
                                <button class="action-btn" onclick="toggleMenuUi(this, 'user-<?php echo $usuario['id']; ?>-<?php echo htmlspecialchars($usuario['tabla_origen']); ?>')">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <div class="dropdown-menu" id="menu-user-<?php echo $usuario['id']; ?>-<?php echo htmlspecialchars($usuario['tabla_origen']); ?>">
                                    <div class="dropdown-item" onclick="viewUser(this)">
                                        <i class="fas fa-eye" style="color: var(--primary);"></i> Ver detalles
                                    </div>
                                    <div class="dropdown-item" onclick="editUser(this)"><i class="fas fa-edit"></i> Editar</div>
                                    <div class="dropdown-item" onclick="deleteUser(this)" style="color: #d32f2f;"><i class="fas fa-trash-alt"></i> Eliminar</div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="pagination-controls">
                <button class="pagination-btn" id="prevBtn" onclick="changePage(-1)">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <span class="pagination-info">
                    Página <strong id="currentPage">1</strong> de <strong id="totalPages">1</strong>
                </span>
                <button class="pagination-btn" id="nextBtn" onclick="changePage(1)">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
    </div>

    <div class="modal" id="gestionModal">
        <div class="modal-content">
            <div class="close-modal" onclick="closeModal()"><i class="fas fa-times"></i></div>
            <h2 id="modal-title" style="font-size: 24px; font-weight: 800; margin-bottom: 5px; color: var(--dark);">Nuevo Usuario</h2>
            <p style="font-size: 14px; color: #999; margin-bottom: 25px;">Registra un nuevo miembro del equipo.</p>
            
            <form id="userForm" onsubmit="handleSubmit(event)">
                <input type="hidden" id="userId" name="id">
                <input type="hidden" id="formAction" name="action" value="create">
                <input type="hidden" id="userTable" name="table" value="usuarios">

                <div class="form-group" style="margin-bottom: 25px; border-bottom: 1px solid #eee; padding-bottom: 20px;">
                    <label class="form-label" style="color: var(--primary); font-size: 13px;">1. Selecciona el Cargo / Rol</label>
                    <select class="form-input-styled" id="userRole" name="rol" required style="border-color: var(--primary); background: #f9fff9;">
                        <option value="" disabled selected>-- Selecciona --</option>
                        <option value="coordinador_regional">Coordinador Regional / Municipal</option>
                        <option value="coordinador_zona">Coordinador de Zona</option>
                        <option value="coordinador_seccion">Coordinador de Sección</option>
                        <option value="operador">Operador / Brigadista</option>
                        <option value="movilizador">Movilizador / Enlace</option>
                        <option value="ciudadano">Ciudadano Afín</option>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div style="grid-column: span 2;">
                        <label class="form-label">Nombre Completo *</label>
                        <input type="text" class="form-input-styled" id="userNombre" name="nombre" required placeholder="Ej: Oscar Ramirez García">
                        <small style="color:#aaa; font-size:11px;">Escribe el nombre empezando por apellidos si lo prefieres.</small>
                    </div>
                    <div>
                        <label class="form-label">Teléfono *</label>
                        <input type="tel" class="form-input-styled" id="userPhone" name="telefono" required placeholder="Ej: 6621234567">
                    </div>
                    <div>
                        <label class="form-label">Correo (Opcional)</label>
                        <input type="email" class="form-input-styled" id="userEmail" name="correo" placeholder="tu@correo.com">
                    </div>
                    <div>
                        <label class="form-label">Clave de Elector (INE)</label>
                        <input type="text" class="form-input-styled" id="userIne" name="ine" placeholder="18 caracteres" maxlength="18" style="text-transform:uppercase">
                    </div>
                    <div>
                        <label class="form-label">CURP</label>
                        <input type="text" class="form-input-styled" id="userCurp" name="curp" placeholder="18 caracteres" maxlength="18" style="text-transform:uppercase">
                    </div>

                    <div style="grid-column: span 2; margin-top: 5px; margin-bottom: 5px;">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" id="checkPassword" name="requiere_acceso" value="true" onchange="togglePasswordSection()">
                            <span style="font-size: 14px; font-weight: 600; color: var(--primary);">
                                <i class="fas fa-key"></i> Asignar acceso al sistema
                            </span>
                        </label>
                    </div>

                    <div id="passwordContainer" style="display: none; grid-column: span 2; background: #f8f9fa; padding: 15px; border-radius: 10px; border: 1px solid #e9ecef;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div style="grid-column: span 2;">
                                <label class="form-label">Nombre de Usuario *</label>
                                <input type="text" class="form-input-styled" id="username" name="username" placeholder="usuario_sistema">
                            </div>
                            <div>
                                <label class="form-label">Contraseña</label>
                                <input type="password" class="form-input-styled" id="userPassword" name="password" placeholder="Mínimo 8 caracteres">
                            </div>
                            <div>
                                <label class="form-label">Confirmar Contraseña</label>
                                <input type="password" class="form-input-styled" id="userConfirmPassword" name="confirm_password" placeholder="Repite la contraseña">
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="form-label">Estado *</label>
                        <select class="form-input-styled" id="userEstado" name="estado" required onchange="cargarRegiones(this.value)">
                            <option value="" disabled selected>Selecciona...</option>
                            <?php foreach ($estados_disponibles as $estado): ?>
                            <option value="<?php echo $estado; ?>"><?php echo $estado; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Región Asignada</label>
                        <select class="form-input-styled" id="userRegion" name="region" onchange="cargarZonas(this.value)">
                            <option value="">-- Selecciona un estado primero --</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Zona Asignada</label>
                        <select class="form-input-styled" id="userZona" name="zona">
                            <option value="">-- Selecciona una región primero --</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Sección Electoral (Opcional)</label>
                        <input type="text" class="form-input-styled" id="userSeccion" name="seccion_electoral" placeholder="Ej: 1234" oninput="this.value = this.value.replace(/[^0-9]/g, '');" maxlength="10">
                    </div>
                    <div>
                        <label class="form-label">Colonia (Opcional)</label>
                        <input type="text" class="form-input-styled" id="userColonia" name="colonia" placeholder="Ej: Centro">
                    </div>
                    <div>
                        <label class="form-label">Dirección/Calle (Opcional)</label>
                        <input type="text" class="form-input-styled" id="userCalle" name="calle" placeholder="Ej: Av. Principal #123">
                    </div>
                </div>
                
                <button type="submit" class="btn-primary-action" style="width: 100%; justify-content: center; margin-top: 30px;">
                    <i class="fas fa-save"></i> Guardar Usuario
                </button>
            </form>
        </div>
    </div>

    <div class="modal" id="viewModal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="close-modal" onclick="closeViewModal()"><i class="fas fa-times"></i></div>
            
            <div style="margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px;">
                <div class="ai-badge" style="margin-bottom: 10px;">FICHA TÉCNICA</div>
                <h2 style="font-size: 28px; font-weight: 900; color: var(--dark);">Detalles del Usuario</h2>
            </div>

            <div id="viewContent" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; row-gap: 25px;">
            </div>

            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; text-align: right;">
                <button class="btn-primary-action" onclick="closeViewModal()" style="background: var(--dark); display: inline-flex;">
                    Cerrar Ficha
                </button>
            </div>
        </div>
    </div>

    <div id="toast" class="toast"></div>

    <script>
        let currentPage = 1;
        const rowsPerPage = 25;
        let filteredRows = [];
        let currentCirc = 'all';

        // Variables de ordenamiento (Sorting)
        let currentSortCol = -1;
        let isSortAscending = true;
        let currentSortType = 'string';

        function sortTable(columnIndex, type) {
            if (currentSortCol === columnIndex) {
                isSortAscending = !isSortAscending;
            } else {
                currentSortCol = columnIndex;
                isSortAscending = true;
                currentSortType = type;
            }

            const headers = document.querySelectorAll('#teamTable th.sortable');
            headers.forEach((th, index) => {
                th.classList.remove('active');
                let icon = th.querySelector('.sort-icon');
                if(icon) icon.className = 'fas fa-sort sort-icon';
                
                if (index === columnIndex) {
                    th.classList.add('active');
                    if(icon) {
                        icon.className = isSortAscending ? 'fas fa-sort-up sort-icon' : 'fas fa-sort-down sort-icon';
                    }
                }
            });

            updateDashboard();
        }

        function selectCircunscripcion(circId) {
            currentCirc = circId;
            
            const labelTotal = document.getElementById('label-total');
            if(labelTotal) {
                labelTotal.textContent = (circId === 'all') ? 'Total Nacional' : 'Total en Región';
            }

            document.querySelectorAll('.circ-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelector(`.circ-btn[data-circ="${circId}"]`).classList.add('active');

            document.querySelectorAll('.state-tab-item').forEach(tab => {
                if (circId === 'all' || tab.dataset.circ === circId) {
                    tab.style.display = 'flex';
                } else {
                    tab.style.display = 'none';
                }
            });

            const allTab = document.querySelector('.state-tab-all');
            switchTab(allTab, 'all');
        }

        function switchTab(element, stateValue) {
            document.querySelectorAll('.tab-btn').forEach(tab => {
                tab.classList.remove('active');
            });
            element.classList.add('active');
            
            document.getElementById('stateFilter').value = stateValue;
            document.getElementById('searchInput').value = '';
            
            updateDashboard();
        }

        function updateDashboard() {
            const stateFilter = document.getElementById('stateFilter').value;
            const search = document.getElementById('searchInput').value.toLowerCase();
            const allRows = Array.from(document.querySelectorAll('#users-table-body tr'));
            
            let counts = { 
                total: 0, 
                coordinador_estatal: 0, 
                coordinador_municipal: 0, 
                coordinador_distrital: 0, 
                responsable_zona: 0, 
                seccion: 0,
                promotor: 0 
            };

            filteredRows = allRows.filter(row => {
                const rowState = row.getAttribute('data-user-state');
                const rowRole = row.getAttribute('data-user-table'); 
                const rowCirc = row.getAttribute('data-user-circ');
                
                const name = row.children[0].textContent.toLowerCase();
                const section = row.children[3].textContent.toLowerCase();
                const phone = row.children[4].textContent.toLowerCase();

                const matchesCirc = currentCirc === 'all' || rowCirc === currentCirc;
                const matchesState = stateFilter === 'all' || rowState === stateFilter;
                const matchesSearch = name.includes(search) || phone.includes(search) || section.includes(search);

                if ((currentCirc === 'all' && matchesState && matchesSearch) || (matchesCirc && matchesState && matchesSearch)) {
                    counts.total++;
                    if (rowRole === 'estatal' || rowRole === 'coordinador_estatal') counts.coordinador_estatal++;
                    else if (rowRole === 'regional' || rowRole === 'coordinador_regional' || rowRole === 'coordinador_municipal') counts.coordinador_municipal++;
                    else if (rowRole === 'zona' || rowRole === 'coordinador_zona' || rowRole === 'responsable_zona') counts.responsable_zona++;
                    else if (rowRole === 'seccion' || rowRole === 'coordinador_seccion') counts.seccion++;
                    else counts.promotor++;
                    return true;
                }
                return false;
            });

            if (currentSortCol > -1) {
                filteredRows.sort((a, b) => {
                    let valA = a.children[currentSortCol].textContent.trim();
                    let valB = b.children[currentSortCol].textContent.trim();

                    if (currentSortType === 'number') {
                        valA = parseFloat(valA) || 0;
                        valB = parseFloat(valB) || 0;
                        return isSortAscending ? valA - valB : valB - valA;
                    } else {
                        valA = valA.toLowerCase();
                        valB = valB.toLowerCase();
                        if (valA < valB) return isSortAscending ? -1 : 1;
                        if (valA > valB) return isSortAscending ? 1 : -1;
                        return 0;
                    }
                });
                
                const tbody = document.getElementById('users-table-body');
                filteredRows.forEach(row => tbody.appendChild(row));
            }

            ['total', 'coordinador_estatal', 'coordinador_municipal', 'responsable_zona', 'seccion'].forEach(id => {
                const el = document.getElementById('stat-'+id);
                if(el) el.textContent = counts[id] || 0;
            });
            document.getElementById('visible-count').textContent = counts.total;

            currentPage = 1;
            displayPage();
        }

        function displayPage() {
            const allRows = document.querySelectorAll('#users-table-body tr');
            allRows.forEach(row => row.style.display = 'none');

            const totalPages = Math.ceil(filteredRows.length / rowsPerPage) || 1;
            
            if (currentPage > totalPages) currentPage = totalPages;
            if (filteredRows.length === 0) currentPage = 1;

            const start = (currentPage - 1) * rowsPerPage;
            const end = start + rowsPerPage;
            const pageRows = filteredRows.slice(start, end);

            pageRows.forEach(row => row.style.display = '');

            document.getElementById('currentPage').textContent = filteredRows.length === 0 ? 0 : currentPage;
            document.getElementById('totalPages').textContent = filteredRows.length === 0 ? 0 : totalPages;

            document.getElementById('prevBtn').disabled = currentPage <= 1;
            document.getElementById('nextBtn').disabled = currentPage >= totalPages || filteredRows.length === 0;
        }

        function changePage(direction) {
            const totalPages = Math.ceil(filteredRows.length / rowsPerPage) || 1;
            const newPage = currentPage + direction;

            if (newPage >= 1 && newPage <= totalPages) {
                currentPage = newPage;
                displayPage();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        }

        window.addEventListener('load', updateDashboard);

        async function cargarRegiones(estado, preSelectRegion = '', preSelectZona = '') {
            const selectRegion = document.getElementById('userRegion');
            const selectZona = document.getElementById('userZona');

            selectRegion.innerHTML = '<option value="">-- Cargando regiones... --</option>';
            selectZona.innerHTML = '<option value="">-- Selecciona una región primero --</option>';

            try {
                const response = await fetch(`/api/get_geografia.php?action=get_regiones&estado=${encodeURIComponent(estado)}`);
                const data = await response.json();
                
                selectRegion.innerHTML = '<option value="">-- Selecciona una región --</option>';
                
                if (data.success && data.regiones && data.regiones.length > 0) {
                    data.regiones.forEach(reg => {
                        let opt = document.createElement('option');
                        opt.value = reg.id; 
                        opt.textContent = reg.nombre;
                        selectRegion.appendChild(opt);
                    });
                    
                    if (preSelectRegion) {
                        selectRegion.value = preSelectRegion;
                        await cargarZonas(preSelectRegion, preSelectZona);
                    }
                } else {
                    selectRegion.innerHTML = '<option value="">-- Sin regiones configuradas --</option>';
                }
            } catch (error) {
                console.error('Error al cargar regiones:', error);
                selectRegion.innerHTML = '<option value="">-- Error de conexión --</option>';
            }
        }

        async function cargarZonas(regionId, preSelectZona = '') {
            const selectZona = document.getElementById('userZona');
            
            if (!regionId) {
                selectZona.innerHTML = '<option value="">-- Selecciona una región primero --</option>';
                return;
            }
            
            selectZona.innerHTML = '<option value="">-- Cargando zonas... --</option>';

            try {
                const response = await fetch(`/api/get_geografia.php?action=get_zonas&region_id=${encodeURIComponent(regionId)}`);
                const data = await response.json();

                selectZona.innerHTML = '<option value="">-- Selecciona una zona --</option>';

                if (data.success && data.zonas && data.zonas.length > 0) {
                    data.zonas.forEach(z => {
                        let opt = document.createElement('option');
                        opt.value = z.id;
                        opt.textContent = `${z.nombre} ${z.clave ? `(${z.clave})` : ''}`;
                        selectZona.appendChild(opt);
                    });
                    
                    if (preSelectZona) {
                        selectZona.value = preSelectZona;
                    }
                } else {
                    selectZona.innerHTML = '<option value="">-- Sin zonas configuradas --</option>';
                }
            } catch (error) {
                console.error('Error al cargar zonas:', error);
                selectZona.innerHTML = '<option value="">-- Error de conexión --</option>';
            }
        }

        function toggleMenuUi(btn, id) {
            document.querySelectorAll('.dropdown-menu').forEach(m => { if(m.id !== 'menu-'+id) m.classList.remove('show'); });
            const menu = document.getElementById('menu-' + id);
            menu.classList.toggle('show');
            const closeMenu = (e) => { if(!btn.contains(e.target) && !menu.contains(e.target)) { menu.classList.remove('show'); document.removeEventListener('click', closeMenu); }};
            setTimeout(() => document.addEventListener('click', closeMenu), 10);
        }

        const modal = document.getElementById('gestionModal');
        const viewModal = document.getElementById('viewModal');
        const form = document.getElementById('userForm');
        const toast = document.getElementById('toast');

        function togglePasswordSection() {
            const isChecked = document.getElementById('checkPassword').checked;
            const container = document.getElementById('passwordContainer');
            const userIn = document.getElementById('username');
            const passInput = document.getElementById('userPassword');
            const confirmInput = document.getElementById('userConfirmPassword');

            if (isChecked) {
                container.style.display = 'block';
                const action = document.getElementById('formAction').value;
                if (action === 'create') {
                    userIn.required = true;
                    passInput.required = true;
                    confirmInput.required = true;
                } else {
                    userIn.required = true;
                }
            } else {
                container.style.display = 'none';
                userIn.required = false;
                passInput.required = false;
                confirmInput.required = false;
            }
        }

        document.getElementById('userNombre')?.addEventListener('blur', function() {
            const isChecked = document.getElementById('checkPassword').checked;
            if(!isChecked) return;
            
            const usr = document.getElementById('username');
            if (!usr.value) {
                let nameParts = this.value.trim().toLowerCase().split(' ');
                if(nameParts.length > 0) {
                   let n = nameParts[0].replace(/[^a-z0-9_]/g,'');
                   let ap = nameParts.length > 1 ? "_" + nameParts[1].replace(/[^a-z0-9_]/g,'') : "";
                   usr.value = n + ap;
                }
            }
        });

        function openModal(mode = 'create') {
            if (mode === 'create') {
                document.getElementById('modal-title').textContent = 'Nuevo Usuario';
                document.getElementById('formAction').value = 'create';
                form.reset();
                document.getElementById('userId').value = "";
                document.getElementById('userTable').value = "usuarios";
                document.getElementById('userRole').disabled = false; 
                
                document.getElementById('checkPassword').checked = false; 
                togglePasswordSection();

                document.getElementById('userRegion').innerHTML = '<option value="">-- Selecciona un estado primero --</option>';
                document.getElementById('userZona').innerHTML = '<option value="">-- Selecciona una región primero --</option>';
            }
            modal.classList.add('open');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            modal.classList.remove('open');
            document.body.style.overflow = '';
            setTimeout(() => form.reset(), 300);
        }

        function closeViewModal() {
            viewModal.classList.remove('open');
            document.body.style.overflow = '';
        }

        const API_URL = '../../api/create_users/crud_usuarios.php'; 

        async function viewUser(el) {
            const row = el.closest('tr');
            const userId = row.dataset.userId;
            const userTable = row.dataset.userTable;
            
            try {
                // Pasamos el parámetro &table para que la API sepa de dónde leer
                const response = await fetch(`${API_URL}?action=get&id=${userId}&table=${userTable}`);
                const data = await response.json();

                if (data.success) {
                    const user = data.user;
                    const container = document.getElementById('viewContent');

                    const field = (label, value, fullWidth = false) => `
                        <div style="${fullWidth ? 'grid-column: span 2;' : ''}">
                            <div class="form-label" style="margin-bottom: 5px;">${label}</div>
                            <div style="font-size: 15px; color: var(--dark); font-weight: 500;">
                                ${value || '<span style="color:#ccc; font-style:italic;">No registrado</span>'}
                            </div>
                        </div>
                    `;

                    const rolObtenido = user.rol || userTable;
                    const rolFormateado = rolObtenido.replace(/_/g, ' ').toUpperCase();
                    const accceso = user.requiere_acceso == 1 ? '<i class="fas fa-check-circle" style="color:green;"></i> Sí' : '<i class="fas fa-times-circle" style="color:#ccc;"></i> No';
                    const userNombreReal = user.nombre_completo || user.nombres || user.nombre || 'N/A';

                    container.innerHTML = `
                        ${field('Rol / Cargo', `<span class="badge badge-${rolObtenido}" style="font-size:12px;">${rolFormateado}</span>`, true)}
                        ${field('Nombre Completo', userNombreReal, true)}
                        ${field('Teléfono', `<a href="tel:${user.telefono}" style="color:var(--primary); text-decoration:none;">${user.telefono}</a>`)}
                        ${field('Correo Electrónico', user.correo || user.email)}
                        ${field('Identificación', `<b>INE:</b> ${user.ine || 'N/A'} <br> <b>CURP:</b> ${user.curp || 'N/A'}`)}
                        ${field('Acceso al Sistema', accceso)}
                        ${field('Usuario de Acceso', user.username)}
                        ${field('Estado / Región / Zona', `${user.estado || 'N/A'} / ${user.region || 'N/A'} / ${user.zona || 'N/A'}`, true)}
                        ${field('Sección Electoral', user.seccion_electoral)}
                        ${field('Dirección', `${user.direccion || user.calle || ''}, ${user.colonia || ''}`, true)}
                        ${field('Fecha de Registro', user.fecha_registro || 'N/A')}
                    `;

                    viewModal.classList.add('open');
                    document.body.style.overflow = 'hidden';
                } else {
                    showToast(data.message || data.error || 'No se pudieron cargar los datos', 'error');
                }
            } catch (error) {
                console.error(error);
                showToast('Error de conexión al obtener detalles', 'error');
            }
        }

        function showToast(message, type = 'success') {
            toast.textContent = message;
            toast.className = 'toast show ' + (type === 'error' ? 'error' : '');
            setTimeout(() => toast.classList.remove('show'), 3000);
        }

        async function handleSubmit(e) {
            e.preventDefault();
            const btn = form.querySelector('button[type="submit"]');
            
            const pass = document.getElementById('userPassword').value;
            const confirm = document.getElementById('userConfirmPassword').value;
            const hasPassword = document.getElementById('checkPassword').checked;

            if (hasPassword && pass !== '') {
                if (pass !== confirm) {
                    showToast('Las contraseñas no coinciden', 'error');
                    return;
                }
                if (pass.length < 8) {
                    showToast('La contraseña debe tener al menos 8 caracteres', 'error');
                    return;
                }
            }

            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
            btn.disabled = true;

            const formData = new FormData(form);

            try {
                const response = await fetch(API_URL, { method: 'POST', body: formData });
                const data = await response.json();

                if (data.success) {
                    showToast('Operación exitosa', 'success');
                    closeModal();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(data.error || data.message, 'error');
                }
            } catch (error) {
                showToast('Error de conexión con el servidor', 'error');
            } finally {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        }

        async function editUser(el) {
            const row = el.closest('tr');
            const userId = row.dataset.userId;
            const userTable = row.dataset.userTable;

            try {
                const response = await fetch(`${API_URL}?action=get&id=${userId}&table=${userTable}`);
                const data = await response.json();

                if (data.success) {
                    const user = data.user;
                    document.getElementById('modal-title').textContent = 'Editar Usuario';
                    document.getElementById('formAction').value = 'edit';
                    document.getElementById('userId').value = user.id;
                    
                    // Asignar en el formulario oculto a qué tabla pertenece para el Update
                    document.getElementById('userTable').value = userTable;
                    
                    const roleSelect = document.getElementById('userRole');
                    roleSelect.value = user.rol || userTable;
                    roleSelect.disabled = false; 

                    document.getElementById('userNombre').value = user.nombre_completo || user.nombres || user.nombre || '';
                    document.getElementById('userPhone').value = user.telefono;
                    document.getElementById('userEmail').value = user.correo || user.email || '';
                    
                    document.getElementById('userIne').value = user.ine || '';
                    document.getElementById('userCurp').value = user.curp || '';
                    
                    document.getElementById('userColonia').value = user.colonia || '';
                    document.getElementById('userCalle').value = user.direccion || user.calle || '';
                    document.getElementById('userSeccion').value = user.seccion_electoral || '';
                    
                    document.getElementById('userEstado').value = user.estado || '';
                    
                    if (user.estado) {
                        await cargarRegiones(user.estado, user.region, user.zona);
                    } else {
                        document.getElementById('userRegion').innerHTML = '<option value="">-- Selecciona un estado primero --</option>';
                        document.getElementById('userZona').innerHTML = '<option value="">-- Selecciona una región primero --</option>';
                    }

                    const cbPass = document.getElementById('checkPassword');
                    if (user.requiere_acceso == 1 || userTable !== 'usuarios') {
                        cbPass.checked = true;
                        document.getElementById('username').value = user.username || user.email || '';
                    } else {
                        cbPass.checked = false;
                        document.getElementById('username').value = '';
                    }
                    togglePasswordSection();
                    
                    modal.classList.add('open');
                    document.body.style.overflow = 'hidden';
                } else {
                    showToast(data.message || data.error, 'error');
                }
            } catch (error) {
                showToast('Error de conexión', 'error');
            }
        }

        async function deleteUser(el) {
            const row = el.closest('tr');
            const userId = row.dataset.userId;
            const userTable = row.dataset.userTable;
            const userName = row.children[0].textContent.trim();

            if (confirm(`¿Estás seguro de ELIMINAR permanentemente a "${userName}"?`)) {
                try {
                    const formData = new FormData();
                    formData.append('action', 'delete');
                    formData.append('id', userId);
                    formData.append('table', userTable); // Para que la API sepa de dónde borrarlo

                    const response = await fetch(API_URL, { method: 'POST', body: formData });
                    const data = await response.json();

                    if (data.success) {
                        row.remove();
                        updateDashboard();
                        showToast('Usuario eliminado correctamente', 'success');
                    } else {
                        showToast(data.error || data.message, 'error');
                    }
                } catch (error) {
                    showToast('Error de conexión', 'error');
                }
            }
        }
    </script>
</body>
</html>