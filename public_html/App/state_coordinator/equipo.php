<?php
// 1. Carga del sistema y seguridad
require_once __DIR__ . '/../../includes/bootstrap.php';

// Asegurar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$usuario_id = $_SESSION['user_id'] ?? 0;
$rol_sesion = strtolower($_SESSION['user_role'] ?? '');

// --- CONFIGURACIÓN DE PERMISOS ---
$permitidos = ['admin', 'administrador', 'superadmin', 'super admin', 'coordinador_estatal'];

if (!in_array($rol_sesion, $permitidos)) {
    header('Location: /public/index.php'); 
    exit();
}

$es_admin = in_array($rol_sesion, ['admin', 'administrador', 'superadmin', 'super admin']);

// Conexión
$db = Database::getInstance()->getConnection();

// 2. Obtener el estado del coordinador actual para filtrar (Con TRIM por seguridad)
$mi_estado = '';
if (!$es_admin) {
    try {
        $stmtE = $db->prepare("SELECT TRIM(estado) FROM coordinador_estatal WHERE id = ?");
        $stmtE->execute([$usuario_id]);
        $mi_estado = $stmtE->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error obteniendo estado: " . $e->getMessage());
    }
    
    // Validar que el CE tenga un estado configurado
    if (empty($mi_estado)) {
        die("Error: No tienes un estado asignado en el sistema. Contacta al administrador.");
    }
}

// Listas de apoyo
$estados_disponibles = [
    'Baja California', 'Baja California Sur', 'Sonora', 'Chihuahua', 
    'Sinaloa', 'Durango', 'Nayarit', 'Jalisco'
];

// 3. Función para obtener usuarios filtrados por estado y roles correspondientes
function obtenerUsuariosFiltrados($db, $es_admin, $mi_estado) {
    $usuarios = [];
    
    // Tablas de coordinadores a consultar (Excluimos coordinador_estatal para el CE)
    $tablas_coord = ['coordinador_regional', 'coordinador_zona', 'coordinador_seccion'];
    
    if ($es_admin) {
        $tablas_coord[] = 'coordinador_estatal';
    }
    
    foreach ($tablas_coord as $tabla) {
        try {
            // Se agrega NULL as region para evitar error en caso de que estas tablas no tengan esa columna
            $sql = "SELECT id, nombres, ap_paterno, ap_materno, 
                           CONCAT(COALESCE(nombres,''), ' ', COALESCE(ap_paterno,''), ' ', COALESCE(ap_materno,'')) as nombre_completo,
                           telefono, telefono_alterno, email, colonia, calle, seccion_electoral, estatus, estado, NULL as region,
                           capacitaciones, disponibilidad, horario, dias_disponibles, movilidad, fecha_alta, fecha_baja,
                           fecha_registro, '$tabla' as rol_tabla 
                    FROM `$tabla` ";
            
            if (!$es_admin && !empty($mi_estado)) {
                $sql .= " WHERE TRIM(estado) = :estado ";
            }
            $sql .= " ORDER BY fecha_registro DESC";
            
            $stmt = $db->prepare($sql);
            if (!$es_admin && !empty($mi_estado)) {
                $stmt->bindValue(':estado', trim($mi_estado));
            }
            $stmt->execute();
            $usuarios = array_merge($usuarios, $stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (PDOException $e) { continue; }
    }

    // Consultar también la tabla general "usuarios"
    try {
        // APLICACIÓN DEL JOIN PARA TRAER EL NOMBRE DE LA REGIÓN EN LUGAR DEL ID
        $sqlU = "SELECT u.id, u.nombre_completo, u.rol as rol_tabla, u.telefono, u.correo as email, 
                        u.seccion_electoral, IF(u.activo=1, 'Activo', 'Inactivo') as estatus, u.estado, 
                        r.nombre as region, u.fecha_registro,
                        u.direccion as calle, u.colonia
                 FROM usuarios u
                 LEFT JOIN regiones_estatales r ON u.region = r.id ";
        
        if (!$es_admin && !empty($mi_estado)) {
            $sqlU .= " WHERE TRIM(u.estado) = :estado ";
        }
        $sqlU .= " ORDER BY u.fecha_registro DESC";
        
        $stmtU = $db->prepare($sqlU);
        if (!$es_admin && !empty($mi_estado)) {
            $stmtU->bindValue(':estado', trim($mi_estado));
        }
        $stmtU->execute();
        $resU = $stmtU->fetchAll(PDO::FETCH_ASSOC);
        
        // Formatear datos de la tabla "usuarios" para que sean compatibles con el Modal JS
        foreach($resU as &$u) {
            if(empty($u['nombres'])) {
                $partes = explode(' ', trim($u['nombre_completo']));
                $u['nombres'] = $partes[0] ?? '';
                $u['ap_paterno'] = $partes[1] ?? '';
                $u['ap_materno'] = $partes[2] ?? '';
            }
            $u['telefono_alterno'] = '';
            $u['capacitaciones'] = '';
            $u['disponibilidad'] = 'Tiempo completo';
            $u['horario'] = 'Matutino';
            $u['dias_disponibles'] = '';
            $u['movilidad'] = 'A pie';
            $u['fecha_alta'] = '';
            $u['fecha_baja'] = '';
        }
        $usuarios = array_merge($usuarios, $resU);
    } catch (PDOException $e) { 
        error_log("Error tabla usuarios: " . $e->getMessage()); 
    }

    return $usuarios;
}

$usuarios = obtenerUsuariosFiltrados($db, $es_admin, $mi_estado);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Equipo - <?php echo $es_admin ? 'Global' : htmlspecialchars($mi_estado); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* --- ESTILOS MODERNOS (BASE GREENBOARD) --- */
        :root { 
            --primary: #2d7a3e; --primary-dark: #1f5a2d; --accent: #e8a020; --dark: #1a1a1a; 
            --light: #f0f7f0; --white: #ffffff; --danger: #ef4444; --danger-dark: #b91c1c;
            --gray-100: #f8f9fa; --gray-200: #e9ecef; --text-muted: #6c757d; 
            --shadow-sm: 0 10px 30px rgba(0,0,0,0.05); --radius: 20px; 
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; background: var(--light); color: var(--dark); overflow-x: hidden; }
        
        .container { max-width: 100%; margin: 0; background: var(--white); min-height: 100vh; position: relative; padding-left: 330px; padding-right: 50px; padding-bottom: 40px; transition: padding-left 0.3s ease; }
        .design-text { position: fixed; left: 290px; top: 50%; writing-mode: vertical-rl; font-size: 13px; font-weight: 500; color: var(--primary); opacity: 0.3; letter-spacing: 2px; z-index: 0; pointer-events: none; }
        
        .page-header { padding: 50px 0 30px 0; display: flex; justify-content: space-between; align-items: flex-end; position: relative; z-index: 2; flex-wrap: wrap; gap: 20px; }
        .ai-badge { color: var(--primary); font-size: 13px; font-weight: 800; letter-spacing: 4px; margin-bottom: 15px; display: inline-block; text-transform: uppercase; background: rgba(45,122,62,0.1); padding: 5px 15px; border-radius: 20px; }
        .main-title { font-size: 54px; font-weight: 900; line-height: 1; margin-bottom: 15px; color: var(--dark); }
        .description { color: #666; font-size: 16px; line-height: 1.6; max-width: 600px; }
        .header-actions { display: flex; gap: 12px; align-items: center; }
        
        .btn-primary-action { background: var(--primary); color: white; border: none; padding: 12px 25px; border-radius: 12px; font-weight: 700; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; gap: 10px; font-size: 14px; box-shadow: 0 4px 15px rgba(45, 122, 62, 0.3); }
        .btn-primary-action:hover { background: var(--primary-dark); transform: translateY(-2px); }

        .grid-overview { display: grid; grid-template-columns: repeat(5, 1fr); gap: 15px; margin-bottom: 30px; }
        .stat-card { background: var(--white); border: 1px solid var(--gray-200); padding: 20px; border-radius: 16px; text-align: center; box-shadow: var(--shadow-sm); position: relative; overflow: hidden; display: flex; flex-direction: column; align-items: center; justify-content: center; transition: all 0.3s ease; }
        .stat-card:hover { transform: translateY(-5px); border-color: var(--primary); }
        .stat-card i { font-size: 24px; color: var(--primary); margin-bottom: 10px; opacity: 0.8; }
        .stat-card .number { font-size: 28px; font-weight: 900; color: var(--dark); line-height: 1; }
        .stat-card .label { font-size: 10px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-top: 5px; }

        .filters-bar { background: var(--white); border: 1px solid var(--gray-200); border-radius: 16px; padding: 20px; margin-bottom: 30px; display: flex; gap: 15px; align-items: flex-end; box-shadow: var(--shadow-sm); flex-wrap: wrap; }
        .filter-group { display: flex; flex-direction: column; gap: 8px; flex: 1; min-width: 160px; }
        .form-label { font-size: 12px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.5px; }
        .form-input-styled { width: 100%; padding: 12px 15px; border-radius: 10px; border: 2px solid var(--gray-200); background: var(--gray-100); font-size: 14px; font-weight: 600; color: var(--dark); transition: all 0.3s; outline: none; font-family: Arial, sans-serif; appearance: none; }
        .form-input-styled:focus { border-color: var(--primary); background: var(--white); }
        .select-wrap { position: relative; }
        .select-wrap::after { content: '\f107'; font-family: 'Font Awesome 6 Free'; font-weight: 900; position: absolute; right: 14px; top: 50%; transform: translateY(-50%); color: var(--text-muted); pointer-events: none; }

        .table-section { background: var(--white); border-radius: var(--radius); padding: 30px; border: 1px solid var(--gray-200); box-shadow: var(--shadow-sm); }
        .section-header { margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; }
        .section-title { font-size: 20px; font-weight: 800; }
        .table-wrapper { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .modern-table { width: 100%; min-width: 1000px; border-collapse: separate; border-spacing: 0; }
        
        .modern-table th { text-align: left; padding: 12px 15px; color: var(--text-muted); font-size: 11px; text-transform: uppercase; letter-spacing: 1px; border-bottom: 2px solid var(--gray-200); white-space: nowrap; user-select: none; cursor: pointer; transition: background-color 0.2s; }
        .modern-table th:hover { background-color: var(--gray-100); border-radius: 8px 8px 0 0; }
        .modern-table th i { margin-left: 6px; font-size: 14px; opacity: 0.3; transition: all 0.2s; }
        .modern-table th.active-sort { color: var(--primary); font-weight: 800; }
        .modern-table th.active-sort i { opacity: 1; color: var(--primary); }
        
        .modern-table td { padding: 15px; border-bottom: 1px solid var(--gray-200); font-size: 13px; color: var(--dark); font-weight: 500; vertical-align: middle; transition: background 0.2s; }
        .modern-table tr:hover td { background: var(--gray-100); }
        .modern-table tr:last-child td { border-bottom: none; }

        /* Badges */
        .badge { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; display: inline-block; }
        .badge-coordinador_estatal, .badge-estatal { background: #e8f5e9; color: #2d7a3e; border: 1px solid #a5d6a7; }
        .badge-coordinador_regional, .badge-regional { background: #e3f2fd; color: #1565c0; border: 1px solid #90caf9; }
        .badge-coordinador_zona, .badge-zona { background: #fff8e1; color: #f57f17; border: 1px solid #ffe082; }
        .badge-coordinador_seccion, .badge-seccion { background: #f3e5f5; color: #7b1fa2; border: 1px solid #ce93d8; }
        .badge-operador { background: #e0f2f1; color: #1565c0; border: 1px solid #bbdefb; }
        .badge-movilizador { background: #fbe9e7; color: #e65100; border: 1px solid #ffcc80; }
        .badge-ciudadano, .badge-simpatizante { background: #e8eaf6; color: #6a1b9a; border: 1px solid #d1c4e9; }
        .badge-voluntario { background: #fff3e0; color: #e65100; border: 1px solid #ffe0b2; }
        
        .badge-activo { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0;}
        .badge-inactivo { background: #fef9c3; color: #a16207; border: 1px solid #fef08a;}
        .badge-baja { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca;}

        .actions-cell { display: flex; gap: 8px; align-items: center; }
        .btn-inline { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 8px; border: 2px solid transparent; font-size: 13px; cursor: pointer; transition: all 0.2s; background: var(--white); }
        .btn-inline-view { color: #3b82f6; border-color: #bfdbfe; }
        .btn-inline-view:hover { background: #3b82f6; color: white; border-color: #3b82f6; }
        .btn-inline-edit { color: var(--primary); border-color: #bbf7d0; }
        .btn-inline-edit:hover { background: var(--primary); color: white; border-color: var(--primary); }
        .btn-inline-delete { color: var(--danger); border-color: #fecaca; }
        .btn-inline-delete:hover { background: var(--danger); color: white; border-color: var(--danger); }

        .pagination-controls { display: flex; justify-content: center; align-items: center; gap: 10px; margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--gray-200); }
        .pagination-btn { background: var(--white); border: 2px solid var(--gray-200); color: var(--dark); padding: 10px 18px; border-radius: 10px; font-weight: 600; cursor: pointer; transition: all 0.3s; font-size: 14px; }
        .pagination-btn:hover:not(:disabled) { background: var(--primary); color: white; border-color: var(--primary); }
        .pagination-btn:disabled { opacity: 0.4; cursor: not-allowed; }

        /* MODALES */
        .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 2000; display: flex; align-items: center; justify-content: center; opacity: 0; pointer-events: none; transition: opacity 0.25s; backdrop-filter: blur(3px); }
        .modal-overlay.open { opacity: 1; pointer-events: all; }
        .modal { background: var(--white); border-radius: var(--radius); width: 100%; max-width: 850px; max-height: 90vh; overflow-y: auto; box-shadow: 0 30px 80px rgba(0,0,0,0.25); transform: translateY(30px); transition: transform 0.25s; }
        .modal-overlay.open .modal { transform: translateY(0); }
        
        .modal-header { padding: 28px 32px 20px; border-bottom: 1px solid var(--gray-200); display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; background: var(--white); z-index: 10; border-radius: var(--radius) var(--radius) 0 0; }
        .modal-title { font-size: 22px; font-weight: 900; color: var(--dark); }
        .modal-subtitle { font-size: 13px; color: var(--text-muted); margin-top: 4px; }
        .modal-close { width: 36px; height: 36px; border-radius: 10px; border: none; background: var(--gray-100); cursor: pointer; font-size: 16px; color: var(--text-muted); display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
        .modal-close:hover { background: var(--gray-200); color: var(--danger); }
        
        .modal-body { padding: 28px 32px; }
        .modal-footer { padding: 20px 32px; border-top: 1px solid var(--gray-200); display: flex; justify-content: flex-end; gap: 12px; position: sticky; bottom: 0; background: var(--white); border-radius: 0 0 var(--radius) var(--radius); z-index: 10; }
        
        .m-grid { display: grid; gap: 18px; }
        .m-grid-3 { grid-template-columns: 1fr 1fr 1fr; }
        .m-grid-2 { grid-template-columns: 1fr 1fr; }
        .m-group { display: flex; flex-direction: column; gap: 6px; }
        .m-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #555; }
        .m-label .req { color: var(--danger); margin-left: 2px; }
        .m-input, .m-select, .m-textarea { padding: 11px 14px; border: 2px solid #eee; border-radius: 10px; font-size: 14px; font-weight: 500; color: var(--dark); background: var(--white); outline: none; transition: all 0.2s; width: 100%; font-family: Arial, sans-serif; }
        .m-select { appearance: none; }
        .m-textarea { resize: vertical; min-height: 80px; }
        .m-input:focus, .m-select:focus, .m-textarea:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(45,122,62,0.1); }
        
        .section-sep { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: var(--primary); margin: 30px 0 15px; display: flex; align-items: center; gap: 10px; }
        .section-sep::after { content: ''; flex: 1; height: 1px; background: var(--gray-200); }

        .m-alert { padding: 12px 16px; border-radius: 10px; font-size: 13px; font-weight: 600; display: none; align-items: center; gap: 8px; margin-bottom: 16px; }
        .m-alert.show { display: flex; }
        .m-alert-error { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }
        .toast { position: fixed; bottom: 30px; right: 30px; background: var(--dark); color: white; padding: 15px 30px; border-radius: 50px; z-index: 3000; display: none; box-shadow: 0 10px 25px rgba(0,0,0,0.2); font-weight: 600; }
        .toast.show { display: block; animation: slideUp 0.3s ease forwards; }
        .toast.success { background: var(--primary); }
        .toast.error { background: var(--danger); }
        @keyframes slideUp { from { transform: translateY(100px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        .btn-m { padding: 11px 24px; border-radius: 10px; border: none; font-weight: 700; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; gap: 8px; font-size: 14px; font-family: Arial, sans-serif; }
        .btn-m-primary { background: var(--primary); color: white; }
        .btn-m-primary:hover { background: var(--primary-dark); }
        .btn-m-outline { background: white; color: var(--dark); border: 2px solid #eee; }
        .btn-m-outline:hover { border-color: var(--primary); color: var(--primary); }

        @media (max-width: 1024px) {
            .container { padding-left: 20px; padding-right: 20px; }
            .grid-overview { grid-template-columns: repeat(3, 1fr); }
            .design-text { display: none; }
        }
        @media (max-width: 660px) {
            .grid-overview { grid-template-columns: repeat(2, 1fr); }
            .m-grid-3, .m-grid-2 { grid-template-columns: 1fr; }
            .modal { margin: 0 12px; max-width: 100%; }
        }
    </style>
</head>
<body>
    <?php 
    if ($es_admin) {
        include __DIR__ . '/../../includes/components/sidebar_admin.php'; 
    } else {
        include __DIR__ . '/../../includes/components/sidebar_ce.php'; 
    }
    ?>
    
    <div class="design-text">EQUIPO 2025</div>

    <div class="container">
        <div class="page-header">
            <div>
                <div class="ai-badge">
                    <?php echo $es_admin ? 'GESTIÓN GLOBAL' : 'GESTIÓN ESTATAL - ' . htmlspecialchars($mi_estado); ?>
                </div>
                <h1 class="main-title">Directorio de<br>Equipo de Trabajo.</h1>
                <p class="description">Administración, seguimiento y asignación de roles a todo el personal operativo.</p>
            </div>
            <div class="header-actions">
                <button class="btn-primary-action" onclick="window.location.href='crear_usuario.php'">
                    <i class="fas fa-user-plus"></i> Nuevo Usuario
                </button>
            </div>
        </div>

        <div class="grid-overview">
            <div class="stat-card"><i class="fas fa-users"></i><span class="number" id="stat-total">0</span><span class="label">Total</span></div>
            <div class="stat-card"><i class="fas fa-crown"></i><span class="number" id="stat-coordinador_estatal">0</span><span class="label">Estatal</span></div>
            <div class="stat-card"><i class="fas fa-map"></i><span class="number" id="stat-coordinador_regional">0</span><span class="label">Regional</span></div>
            <div class="stat-card"><i class="fas fa-map-pin"></i><span class="number" id="stat-coordinador_zona">0</span><span class="label">Zona</span></div>
            <div class="stat-card"><i class="fas fa-street-view"></i><span class="number" id="stat-coordinador_seccion">0</span><span class="label">Sección</span></div>
        </div>

        <div class="filters-bar">
            <div class="filter-group" style="flex:2">
                <label class="form-label">Buscar en el listado</label>
                <div style="position: relative;">
                    <i class="fas fa-search" style="position: absolute; left: 15px; top: 14px; color: #999;"></i>
                    <input type="text" id="searchInput" class="form-input-styled" style="padding-left: 40px;" placeholder="Buscar por nombre, teléfono, región o sección..." oninput="updateDashboard()">
                </div>
            </div>
            
            <div class="filter-group">
                <label class="form-label">Filtro de Estado</label>
                <div class="select-wrap">
                    <select id="stateFilter" class="form-input-styled m-select" onchange="updateDashboard()">
                        <?php if ($es_admin): ?>
                            <option value="all">Todos los Estados</option>
                            <?php foreach ($estados_disponibles as $estado): ?>
                                <option value="<?php echo strtolower(trim($estado)); ?>"><?php echo $estado; ?></option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="<?php echo strtolower(trim($mi_estado)); ?>"><?php echo $mi_estado; ?> (Mi Estado)</option>
                        <?php endif; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="table-section">
            <div class="section-header">
                <h3 class="section-title">Directorio de Equipo</h3>
                <div id="visible-count" style="font-size:13px;color:var(--text-muted);font-weight:600;">0 Registros</div>
            </div>
            
            <div class="table-wrapper">
                <table class="modern-table" id="teamTable">
                    <thead>
                        <tr>
                            <th onclick="sortTable(0)">Nombre <i id="icon-sort-0" class="fas fa-sort"></i></th>
                            <th onclick="sortTable(1)">Nivel <i id="icon-sort-1" class="fas fa-sort"></i></th>
                            <th onclick="sortTable(2)">Rol <i id="icon-sort-2" class="fas fa-sort"></i></th>
                            <th onclick="sortTable(3)">Estado <i id="icon-sort-3" class="fas fa-sort"></i></th>
                            <th onclick="sortTable(4)">Región <i id="icon-sort-4" class="fas fa-sort"></i></th>
                            <th onclick="sortTable(5)">Sección <i id="icon-sort-5" class="fas fa-sort"></i></th>
                            <th onclick="sortTable(6)">Teléfono <i id="icon-sort-6" class="fas fa-sort"></i></th>
                            <th onclick="sortTable(7)">Estatus <i id="icon-sort-7" class="fas fa-sort"></i></th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="users-table-body">
                        <?php foreach ($usuarios as $usuario): 
                            $nivel = '';
                            $rolStr = $usuario['rol_tabla'];
                            
                            // Determinación visual del "Nivel" y formateo de Rol
                            if (strpos($rolStr, 'estatal') !== false) $nivel = 'Estado';
                            elseif (strpos($rolStr, 'regional') !== false) $nivel = 'Región';
                            elseif (strpos($rolStr, 'zona') !== false) $nivel = 'Zona';
                            elseif (strpos($rolStr, 'seccion') !== false) $nivel = 'Sección';
                            else $nivel = 'General';
                            
                            $estatus = strtolower($usuario['estatus'] ?? 'activo');
                            if(empty($estatus)) $estatus = 'activo';
                            
                            // Limpieza de datos para JS
                            $userData = json_encode($usuario, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                        ?>
                        <tr data-user-state="<?php echo strtolower(trim($usuario['estado'])); ?>" data-user-table="<?php echo $rolStr; ?>">
                            
                            <td>
                                <div style="font-weight: 700;"><?php echo htmlspecialchars(trim($usuario['nombre_completo']) ?: ($usuario['nombres'] ?? '')); ?></div>
                                <div style="font-size:11px; color:var(--text-muted); margin-top:3px;"><?php echo htmlspecialchars($usuario['email'] ?? ''); ?></div>
                            </td>
                            <td style="color: #666; font-weight: 600;"><?php echo $nivel; ?></td>
                            <td><span class="badge badge-<?php echo $rolStr; ?>"><?php echo ucfirst(str_replace('coordinador_', '', $rolStr)); ?></span></td>
                            <td style="color: #444;"><?php echo htmlspecialchars($usuario['estado'] ?? 'N/A'); ?></td>
                            <td style="color: #444; font-weight: 500;"><?php echo htmlspecialchars($usuario['region'] ?? 'N/A'); ?></td>
                            <td style="color: #666; font-weight: bold;"><?php echo htmlspecialchars($usuario['seccion_electoral'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($usuario['telefono'] ?? ''); ?></td>
                            <td><span class="badge badge-<?php echo $estatus; ?>"><?php echo ucfirst($estatus); ?></span></td>
                            
                            <td class="actions-cell">
                                <button class="btn-inline btn-inline-view" onclick='viewUserFromData(<?php echo $userData; ?>)' title="Ver Ficha">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn-inline btn-inline-edit" onclick='openEditModal(<?php echo $userData; ?>)' title="Editar Usuario">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <button class="btn-inline btn-inline-delete" onclick="deleteUser(<?php echo $usuario['id']; ?>, '<?php echo $rolStr; ?>')" title="Eliminar Usuario">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="pagination-controls">
                <button class="pagination-btn" id="prevBtn" onclick="changePage(-1)"><i class="fas fa-chevron-left"></i></button>
                <span class="pagination-info">Página <strong id="currentPage">1</strong> de <strong id="totalPages">1</strong></span>
                <button class="pagination-btn" id="nextBtn" onclick="changePage(1)"><i class="fas fa-chevron-right"></i></button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="formModalOverlay" onclick="closeModalOutside(event, 'formModalOverlay')">
        <div class="modal">
            <div class="modal-header">
                <div>
                    <div class="modal-title" id="modal-title">Editar Usuario</div>
                    <div class="modal-subtitle" id="modal-subtitle">Modifica los datos del registro.</div>
                </div>
                <button type="button" class="modal-close" onclick="closeFormModal()"><i class="fas fa-times"></i></button>
            </div>
            
            <form id="userForm" onsubmit="handleSubmit(event)">
                <div class="modal-body">
                    <div class="m-alert m-alert-error" id="formAlertError"><i class="fas fa-exclamation-circle"></i> <span id="formAlertMsg">Error.</span></div>
                    
                    <input type="hidden" id="userId" name="userId">
                    <input type="hidden" id="userTable" name="userTable"> 
                    <input type="hidden" id="formAction" name="action" value="edit">

                    <div class="section-sep"><i class="fas fa-user"></i> 1. Datos Personales</div>
                    <div class="m-grid m-grid-3">
                        <div class="m-group">
                            <label class="m-label">Nombre(s) <span class="req">*</span></label>
                            <input type="text" class="m-input" id="userNombrePila" name="nombre_pila" required>
                        </div>
                        <div class="m-group">
                            <label class="m-label">Apellido Paterno <span class="req">*</span></label>
                            <input type="text" class="m-input" id="userApPaterno" name="ap_paterno" required>
                        </div>
                        <div class="m-group">
                            <label class="m-label">Apellido Materno</label>
                            <input type="text" class="m-input" id="userApMaterno" name="ap_materno">
                        </div>
                    </div>

                    <div class="section-sep"><i class="fas fa-address-book"></i> 2. Contacto</div>
                    <div class="m-grid m-grid-3">
                        <div class="m-group">
                            <label class="m-label">Teléfono Principal <span class="req">*</span></label>
                            <input type="tel" class="m-input" id="userPhone" name="telefono" required>
                        </div>
                        <div class="m-group">
                            <label class="m-label">Teléfono Alterno</label>
                            <input type="tel" class="m-input" id="userPhoneAlt" name="telefono_alterno">
                        </div>
                        <div class="m-group">
                            <label class="m-label">Correo <span class="req">*</span></label>
                            <input type="email" class="m-input" id="userEmail" name="email" required>
                        </div>
                    </div>

                    <div class="section-sep"><i class="fas fa-map-marked-alt"></i> 3. Cargo y Ubicación</div>
                    <div class="m-grid m-grid-2">
                        <div class="m-group" style="grid-column: span 2;">
                            <label class="m-label" style="color: var(--primary);">Cargo / Rol Asignado <span class="req">*</span></label>
                            <div class="select-wrap">
                                <select class="m-select" id="userRole" name="rol" required style="border-color: var(--primary); background: #f9fff9;" disabled>
                                    <option value="" disabled selected>-- Selecciona --</option>
                                    <option value="estatal">Coordinador Estatal</option>
                                    <option value="regional">Coordinador Regional</option>
                                    <option value="zona">Coordinador de Zona</option>
                                    <option value="seccion">Coordinador de Sección</option>
                                    <option value="operador">Operador / Brigadista</option>
                                    <option value="movilizador">Movilizador / Enlace</option>
                                    <option value="ciudadano">Ciudadano Afín</option>
                                    <option value="voluntario">Voluntario</option>
                                    <option value="simpatizante">Simpatizante</option>
                                </select>
                            </div>
                        </div>
                        <div class="m-group">
                            <label class="m-label">Estado <span class="req">*</span></label>
                            <div class="select-wrap">
                                <select class="m-select" id="userEstado" name="estado" required>
                                    <option value="" disabled selected>Selecciona...</option>
                                    <?php foreach ($estados_disponibles as $estado): ?>
                                    <option value="<?php echo $estado; ?>"><?php echo $estado; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="m-group">
                            <label class="m-label">Sección Electoral</label>
                            <input type="text" class="m-input" id="userSeccion" name="seccion_electoral" oninput="this.value = this.value.replace(/[^0-9]/g, '');" maxlength="10">
                        </div>
                        <div class="m-group">
                            <label class="m-label">Calle y Número</label>
                            <input type="text" class="m-input" id="userCalle" name="calle">
                        </div>
                        <div class="m-group">
                            <label class="m-label">Colonia</label>
                            <input type="text" class="m-input" id="userColonia" name="colonia">
                        </div>
                    </div>

                    <div class="section-sep"><i class="fas fa-clock"></i> 4. Capacidades y Disponibilidad</div>
                    <div class="m-group">
                        <label class="m-label">Capacitaciones y Certificaciones</label>
                        <textarea class="m-textarea" id="userCapacitaciones" name="capacitaciones" placeholder="Ingresa las capacitaciones que tiene..."></textarea>
                    </div>
                    <div class="m-grid m-grid-2" style="margin-top: 15px;">
                        <div class="m-group">
                            <label class="m-label">Disponibilidad</label>
                            <div class="select-wrap">
                                <select class="m-select" id="userDisponibilidad" name="disponibilidad">
                                    <option value="Tiempo completo">Tiempo completo</option>
                                    <option value="Medio tiempo">Medio tiempo / Parcial</option>
                                </select>
                            </div>
                        </div>
                        <div class="m-group">
                            <label class="m-label">Horario</label>
                            <div class="select-wrap">
                                <select class="m-select" id="userHorario" name="horario">
                                    <option value="Matutino">Matutino</option>
                                    <option value="Vespertino">Vespertino</option>
                                    <option value="Mixto">Mixto</option>
                                </select>
                            </div>
                        </div>
                        <div class="m-group">
                            <label class="m-label">Días Disponibles</label>
                            <input type="text" class="m-input" id="userDias" name="dias_disponibles" placeholder="Ej. Lunes a Viernes, L-D">
                        </div>
                        <div class="m-group">
                            <label class="m-label">Movilidad</label>
                            <div class="select-wrap">
                                <select class="m-select" id="userMovilidad" name="movilidad">
                                    <option value="A pie">A pie</option>
                                    <option value="Vehículo propio">Vehículo propio</option>
                                    <option value="Transporte público">Transporte público</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="section-sep"><i class="fas fa-cog"></i> 5. Administración</div>
                    <div class="m-grid m-grid-3">
                        <div class="m-group">
                            <label class="m-label">Estatus <span class="req">*</span></label>
                            <div class="select-wrap">
                                <select class="m-select" id="userEstatus" name="estatus" required>
                                    <option value="Activo">Activo</option>
                                    <option value="Inactivo">Inactivo</option>
                                    <option value="Baja">Baja</option>
                                </select>
                            </div>
                        </div>
                        <div class="m-group">
                            <label class="m-label">Fecha de Alta</label>
                            <input type="date" class="m-input" id="userFechaAlta" name="fecha_alta">
                        </div>
                        <div class="m-group">
                            <label class="m-label">Fecha de Baja</label>
                            <input type="date" class="m-input" id="userFechaBaja" name="fecha_baja">
                        </div>
                    </div>

                    <div class="m-group" style="margin-top: 25px; border-top: 1px dashed var(--gray-200); padding-top: 20px;">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" id="checkPassword" onchange="togglePasswordSection()" style="width:16px; height:16px;">
                            <span style="font-size: 14px; font-weight: 700; color: var(--primary);"><i class="fas fa-key"></i> Cambiar contraseña de acceso al sistema</span>
                        </label>
                    </div>

                    <div id="passwordContainer" style="display: none; background: #f8f9fa; padding: 20px; border-radius: 12px; border: 1px solid #e9ecef; margin-top: 15px;">
                        <div class="m-grid m-grid-2">
                            <div class="m-group">
                                <label class="m-label">Contraseña</label>
                                <input type="password" class="m-input" id="userPassword" name="password" placeholder="Mínimo 8 caracteres">
                            </div>
                            <div class="m-group">
                                <label class="m-label">Confirmar Contraseña</label>
                                <input type="password" class="m-input" id="userConfirmPassword" name="confirm_password" placeholder="Repite la contraseña">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn-m btn-m-outline" onclick="closeFormModal()"><i class="fas fa-times"></i> Cancelar</button>
                    <button type="submit" class="btn-m btn-m-primary" id="btnSaveSubmit">
                        <i class="fas fa-save"></i> Guardar Usuario
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="viewModalOverlay" onclick="closeModalOutside(event, 'viewModalOverlay')">
        <div class="modal">
            <div class="modal-header">
                <div style="display: flex; align-items: center; gap: 20px;">
                    <div id="viewFotoContainer" style="width: 60px; height: 60px; border-radius: 50%; background: #eee; overflow: hidden; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <i class="fas fa-user" style="font-size: 30px; color: #ccc;"></i>
                    </div>
                    <div>
                        <div class="modal-title" id="viewNombreTitulo">Cargando...</div>
                        <div class="modal-subtitle" id="viewSubtitulo">—</div>
                    </div>
                </div>
                <button type="button" class="modal-close" onclick="closeViewModal()"><i class="fas fa-times"></i></button>
            </div>
            
            <div class="modal-body" id="viewContent" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; row-gap: 25px;">
                </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-m btn-m-primary" id="btnEditInline" onclick="triggerEditFromView()"><i class="fas fa-pen"></i> Editar Info</button>
                <button type="button" class="btn-m btn-m-outline" onclick="closeViewModal()">Cerrar</button>
            </div>
        </div>
    </div>

    <div id="toast" class="toast"></div>

    <script>
        // --- PAGINACIÓN, FILTROS Y ORDENAMIENTO ---
        let currentPage = 1;
        const rowsPerPage = 25;
        let filteredRows = [];
        let sortCol = -1;
        let sortAsc = true;

        function updateDashboard() {
            const stateFilter = document.getElementById('stateFilter').value.trim().toLowerCase();
            const search = document.getElementById('searchInput').value.toLowerCase();
            const allRows = Array.from(document.querySelectorAll('#users-table-body tr'));
            
            let counts = { total: 0, coordinador_estatal: 0, coordinador_regional: 0, coordinador_zona: 0, coordinador_seccion: 0 };

            filteredRows = allRows.filter(row => {
                const rowState = row.getAttribute('data-user-state').trim().toLowerCase();
                const rowRole = row.getAttribute('data-user-table');
                const name = row.children[0].textContent.toLowerCase();
                const region = row.children[4].textContent.toLowerCase(); // Extraído de la columna nueva
                const section = row.children[5].textContent.toLowerCase();
                const phone = row.children[6].textContent.toLowerCase();

                const matchesState = stateFilter === 'all' || rowState === stateFilter;
                const matchesSearch = name.includes(search) || phone.includes(search) || section.includes(search) || region.includes(search);

                if (matchesState && matchesSearch) {
                    counts.total++;
                    if (counts.hasOwnProperty(rowRole)) counts[rowRole]++;
                    return true;
                }
                return false;
            });

            ['total', 'coordinador_estatal', 'coordinador_regional', 'coordinador_zona', 'coordinador_seccion'].forEach(id => {
                const el = document.getElementById('stat-'+id);
                if(el) el.textContent = counts[id];
            });
            document.getElementById('visible-count').textContent = counts.total + ' Registros';

            if(sortCol !== -1) applySort();

            currentPage = 1;
            displayPage();
        }

        function sortTable(columnIndex) {
            if (sortCol === columnIndex) sortAsc = !sortAsc;
            else { sortCol = columnIndex; sortAsc = true; }

            document.querySelectorAll('.modern-table th i').forEach(icon => {
                icon.className = 'fas fa-sort';
                icon.parentElement.classList.remove('active-sort');
            });
            
            const activeIcon = document.getElementById(`icon-sort-${columnIndex}`);
            if (activeIcon) {
                activeIcon.className = sortAsc ? 'fas fa-sort-up' : 'fas fa-sort-down';
                activeIcon.parentElement.classList.add('active-sort');
            }

            applySort();
            currentPage = 1;
            displayPage();
        }

        function applySort() {
            filteredRows.sort((a, b) => {
                let valA = a.children[sortCol].innerText.trim().toLowerCase();
                let valB = b.children[sortCol].innerText.trim().toLowerCase();
                
                let numA = parseFloat(valA.replace(/[^0-9.-]+/g,""));
                let numB = parseFloat(valB.replace(/[^0-9.-]+/g,""));
                
                if (!isNaN(numA) && !isNaN(numB) && valA !== "" && valB !== "") {
                    return sortAsc ? numA - numB : numB - numA;
                }
                if (valA < valB) return sortAsc ? -1 : 1;
                if (valA > valB) return sortAsc ? 1 : -1;
                return 0;
            });
        }

        function displayPage() {
            const allRows = document.querySelectorAll('#users-table-body tr');
            allRows.forEach(row => row.style.display = 'none');
            
            const start = (currentPage - 1) * rowsPerPage;
            const end = start + rowsPerPage;
            const pageRows = filteredRows.slice(start, end);

            const tbody = document.getElementById('users-table-body');
            filteredRows.forEach(row => tbody.appendChild(row));

            pageRows.forEach(row => row.style.display = '');

            const totalPages = Math.ceil(filteredRows.length / rowsPerPage);
            document.getElementById('currentPage').textContent = currentPage;
            document.getElementById('totalPages').textContent = totalPages || 1;

            document.getElementById('prevBtn').disabled = currentPage === 1;
            document.getElementById('nextBtn').disabled = currentPage >= totalPages || totalPages === 0;
        }

        function changePage(direction) {
            const totalPages = Math.ceil(filteredRows.length / rowsPerPage);
            const newPage = currentPage + direction;
            if (newPage >= 1 && newPage <= totalPages) {
                currentPage = newPage;
                displayPage();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        }

        window.addEventListener('load', updateDashboard);

        // --- MANEJO DE MODALES Y FORMULARIOS ---
        const formOverlay = document.getElementById('formModalOverlay');
        const viewOverlay = document.getElementById('viewModalOverlay');
        const form = document.getElementById('userForm');
        let currentUserDataToEdit = null;

        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = 'toast show ' + type;
            setTimeout(() => toast.classList.remove('show'), 3500);
        }

        function closeModalOutside(e, id) {
            if (e.target.id === id) {
                if(id === 'formModalOverlay') closeFormModal();
                if(id === 'viewModalOverlay') closeViewModal();
            }
        }

        function togglePasswordSection() {
            const isChecked = document.getElementById('checkPassword').checked;
            const container = document.getElementById('passwordContainer');
            const passInput = document.getElementById('userPassword');
            const confirmInput = document.getElementById('userConfirmPassword');
            
            if (isChecked) {
                container.style.display = 'block';
            } else {
                container.style.display = 'none';
                passInput.required = false; confirmInput.required = false;
                passInput.value = ''; confirmInput.value = '';
            }
        }

        function openModal(mode = 'edit') {
            document.getElementById('formAlertError').classList.remove('show');
            formOverlay.classList.add('open');
            document.body.style.overflow = 'hidden';
        }

        function closeFormModal() {
            formOverlay.classList.remove('open');
            document.body.style.overflow = '';
            setTimeout(() => form.reset(), 300);
        }

        function closeViewModal() {
            viewOverlay.classList.remove('open');
            document.body.style.overflow = '';
        }

        // --- VER FICHA TÉCNICA ---
        function viewUserFromData(user) {
            currentUserDataToEdit = user; 
            
            const container = document.getElementById('viewContent');
            const field = (label, value, cols = 1) => `
                <div style="grid-column: span ${cols}; background: #fcfcfc; padding: 12px 15px; border-radius: 10px; border: 1px solid #eee;">
                    <div class="m-label" style="margin-bottom: 5px;">${label}</div>
                    <div style="font-size: 14px; color: var(--dark); font-weight: 500;">
                        ${value || '<span style="color:#ccc; font-style:italic;">No registrado</span>'}
                    </div>
                </div>`;
            
            const nombreCompleto = user.nombre_completo || `${user.nombres} ${user.ap_paterno} ${user.ap_materno}`;
            const rolFormateado = user.rol_tabla.replace('coordinador_', '').replace(/_/g, ' ').toUpperCase();
            
            document.getElementById('viewNombreTitulo').textContent = nombreCompleto;
            document.getElementById('viewSubtitulo').textContent = `${rolFormateado} · ${user.estado}`;

            container.innerHTML = `
                ${field('Rol / Cargo', `<span class="badge badge-${user.rol_tabla}">${rolFormateado}</span>`, 3)}
                ${field('Teléfono', `<a href="tel:${user.telefono}" style="color:var(--primary); font-weight:bold; text-decoration:none;"><i class="fas fa-phone-alt"></i> ${user.telefono}</a>`)}
                ${field('Tel. Alterno', user.telefono_alterno)}
                ${field('Correo', user.email)}
                ${field('Ubicación', `${user.estado} - Región: ${user.region || 'N/A'} - Sec: ${user.seccion_electoral || 'N/A'}`, 2)}
                ${field('Dirección', `${user.calle || ''}, ${user.colonia || ''}`)}
                
                <div style="grid-column: span 3; border-top: 1px solid #eee; margin-top:10px; padding-top:15px; font-weight: 800; color:var(--primary); font-size:11px; letter-spacing:1px; text-transform:uppercase;">CAPACIDADES Y DISPONIBILIDAD</div>
                
                ${field('Capacitaciones', user.capacitaciones, 3)}
                ${field('Disponibilidad', user.disponibilidad)}
                ${field('Horario', user.horario)}
                ${field('Días Disponibles', user.dias_disponibles)}
                ${field('Movilidad', user.movilidad, 3)}
                
                <div style="grid-column: span 3; border-top: 1px solid #eee; margin-top:10px; padding-top:15px; font-weight: 800; color:var(--primary); font-size:11px; letter-spacing:1px; text-transform:uppercase;">ADMINISTRACIÓN</div>
                
                ${field('Estatus', `<span class="badge badge-${(user.estatus || 'activo').toLowerCase()}">${(user.estatus || 'ACTIVO').toUpperCase()}</span>`)}
                ${field('Fecha Alta', user.fecha_alta)}
                ${field('Fecha Baja', user.fecha_baja)}
            `;
            
            viewOverlay.classList.add('open');
            document.body.style.overflow = 'hidden';
        }

        // --- EDITAR USUARIO ---
        function triggerEditFromView() {
            closeViewModal();
            if(currentUserDataToEdit) openEditModal(currentUserDataToEdit);
        }

        function openEditModal(user) {
            document.getElementById('formAlertError').classList.remove('show');
            document.getElementById('modal-title').textContent = 'Editar Usuario';
            document.getElementById('modal-subtitle').textContent = 'Modifica los datos del registro.';
            document.getElementById('formAction').value = 'edit';
            
            document.getElementById('userId').value = user.id;
            document.getElementById('userTable').value = user.rol_tabla; 
            
            // PARCHE VISUAL: Limpiamos el prefijo para que coincida con los 'value' cortos del <select>
            let rolLimpio = user.rol_tabla.replace('coordinador_', '');
            document.getElementById('userRole').value = rolLimpio; 
            document.getElementById('userRole').disabled = true; 
            
            document.getElementById('userNombrePila').value = user.nombres || '';
            document.getElementById('userApPaterno').value = user.ap_paterno || '';
            document.getElementById('userApMaterno').value = user.ap_materno || '';
            document.getElementById('userPhone').value = user.telefono || '';
            document.getElementById('userPhoneAlt').value = user.telefono_alterno || '';
            document.getElementById('userEmail').value = user.email || '';
            document.getElementById('userEstado').value = user.estado || '';
            document.getElementById('userColonia').value = user.colonia || '';
            document.getElementById('userCalle').value = user.calle || '';
            document.getElementById('userSeccion').value = user.seccion_electoral || '';
            
            document.getElementById('userCapacitaciones').value = user.capacitaciones || '';
            document.getElementById('userDisponibilidad').value = user.disponibilidad || 'Tiempo completo';
            document.getElementById('userHorario').value = user.horario || 'Matutino';
            document.getElementById('userDias').value = user.dias_disponibles || '';
            document.getElementById('userMovilidad').value = user.movilidad || 'A pie';
            document.getElementById('userEstatus').value = user.estatus || 'Activo';
            document.getElementById('userFechaAlta').value = user.fecha_alta || '';
            document.getElementById('userFechaBaja').value = user.fecha_baja || '';

            document.getElementById('checkPassword').checked = false; 
            togglePasswordSection();
            
            formOverlay.classList.add('open'); 
            document.body.style.overflow = 'hidden';
        }

        // --- SUBMIT FORMULARIO (Editar) ---
        async function handleSubmit(e) {
            e.preventDefault();
            const btn = document.getElementById('btnSaveSubmit');
            const pass = document.getElementById('userPassword').value;
            const confirm = document.getElementById('userConfirmPassword').value;
            const hasPassword = document.getElementById('checkPassword').checked;

            if (hasPassword) {
                if (pass !== confirm) { 
                    document.getElementById('formAlertMsg').textContent = 'Las contraseñas no coinciden';
                    document.getElementById('formAlertError').classList.add('show');
                    return; 
                }
                if (pass.length > 0 && pass.length < 8) { 
                    document.getElementById('formAlertMsg').textContent = 'La contraseña debe tener mínimo 8 caracteres';
                    document.getElementById('formAlertError').classList.add('show');
                    return; 
                }
            }

            const roleSelect = document.getElementById('userRole');
            const roleDisabledStatus = roleSelect.disabled;
            roleSelect.disabled = false;

            const formData = new FormData(form);
            roleSelect.disabled = roleDisabledStatus;

            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
            btn.disabled = true;

            try {
                const response = await fetch('../../api/equipo/manage.php', { method: 'POST', body: formData });
                const data = await response.json();
                
                if (data.success) { 
                    showToast('Operación exitosa', 'success'); 
                    closeFormModal(); 
                    setTimeout(() => location.reload(), 1000); 
                } else { 
                    document.getElementById('formAlertMsg').textContent = data.message || data.error || 'Ocurrió un error al guardar.';
                    document.getElementById('formAlertError').classList.add('show');
                }
            } catch (error) { 
                document.getElementById('formAlertMsg').textContent = 'Error de conexión con el servidor.';
                document.getElementById('formAlertError').classList.add('show');
            } finally { 
                btn.innerHTML = originalText; 
                btn.disabled = false; 
            }
        }

        // --- ELIMINAR USUARIO ---
        async function deleteUser(userId, userTable) {
            if(!confirm('¿Estás seguro de que deseas eliminar este usuario?\nEsta acción no se puede deshacer.')) return;

            try {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', userId);
                formData.append('table', userTable);

                const response = await fetch('../../api/equipo/manage.php', { method: 'POST', body: formData });
                const data = await response.json();
                
                if (data.success) { 
                    showToast('Usuario eliminado correctamente.', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(data.message || data.error || 'Ocurrió un error al eliminar.', 'error');
                }
            } catch(e) {
                showToast('Error de conexión al intentar eliminar.', 'error');
            }
        }
    </script>
</body>
</html>