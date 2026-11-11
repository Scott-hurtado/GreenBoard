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

// Listas de apoyo
$estados_disponibles = [
    'Baja California', 'Baja California Sur', 'Sonora', 'Chihuahua', 
    'Sinaloa', 'Durango', 'Nayarit', 'Jalisco'
];

// Función para obtener usuarios unificando las 5 tablas
function obtenerTodosLosUsuarios($db) {
    $usuarios = [];
    $tablas = [
        'coordinador_estatal', 'coordinador_municipal', 
        'coordinador_distrital', 'responsable_zona', 'promotor'
    ];
    
    foreach ($tablas as $tabla) {
        try {
            // Concatenamos nombre completo para visualización
            $sql = "SELECT id, nombres, ap_paterno, ap_materno, 
                           CONCAT(COALESCE(nombres,''), ' ', COALESCE(ap_paterno,''), ' ', COALESCE(ap_materno,'')) as nombre_completo,
                           telefono, email, colonia, calle, seccion_electoral, estatus, estado, fecha_registro,
                           '$tabla' as rol_tabla 
                    FROM $tabla 
                    ORDER BY fecha_registro DESC";
            $stmt = $db->query($sql);
            $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $usuarios = array_merge($usuarios, $resultados);
        } catch (PDOException $e) {
            continue;
        }
    }
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

        .state-selector-container { background: #fcfdfc; border: 2px solid var(--primary); border-radius: 16px; padding: 20px; margin-bottom: 30px; box-shadow: var(--shadow-sm); z-index: 5; position: relative; display: flex; align-items: center; gap: 20px; }
        .state-select-input { width: 100%; padding: 15px 20px; border-radius: 10px; border: 2px solid var(--gray-200); background: var(--white); font-size: 16px; font-weight: 700; color: var(--dark); transition: all 0.3s; outline: none; cursor: pointer; }
        
        .grid-overview { display: grid; grid-template-columns: repeat(6, 1fr); gap: 15px; margin-bottom: 30px; }
        .stat-card { background: var(--white); border: 1px solid var(--gray-200); padding: 20px; border-radius: 16px; text-align: center; box-shadow: var(--shadow-sm); position: relative; overflow: hidden; display: flex; flex-direction: column; align-items: center; justify-content: center; transition: all 0.3s ease; }
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
        
        .badge { padding: 6px 12px; border-radius: 30px; font-size: 11px; font-weight: 700; text-transform: uppercase; display: inline-block; }
        .badge-coordinador_estatal { background: #ffebee; color: #c62828; }
        .badge-coordinador_municipal { background: #e3f2fd; color: #1565c0; }
        .badge-coordinador_distrital { background: #e8f5e9; color: #2e7d32; }
        .badge-responsable_zona { background: #fff3e0; color: #ef6c00; }
        .badge-promotor { background: #f3e5f5; color: #7b1fa2; }
        .badge-activo { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }

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
        .pagination-btn.active { background: var(--primary); color: white; border-color: var(--primary); }
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
            .state-selector-container, .search-container { flex-direction: column; align-items: stretch; }
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
                <button class="btn-primary-action" onclick="openModal()">
                    <i class="fas fa-user-plus"></i> Nuevo Usuario
                </button>
            </div>
        </div>

        <div class="state-selector-container animate-up animate-delay-1">
            <div style="flex: 1;">
                <label class="form-label" style="margin-bottom: 10px; display: block;">Selecciona Región o Estado</label>
                <select id="stateFilter" class="state-select-input" onchange="updateDashboard()">
                    <option value="all">Noroeste (Todos los estados)</option>
                    <?php foreach ($estados_disponibles as $estado): ?>
                    <option value="<?php echo strtolower($estado); ?>"><?php echo $estado; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="grid-overview animate-up animate-delay-1">
            <div class="stat-card"><i class="fas fa-users"></i><span class="number" id="stat-total">0</span><span class="label">Total</span></div>
            <div class="stat-card"><i class="fas fa-crown"></i><span class="number" id="stat-coordinador_estatal">0</span><span class="label">Estatal</span></div>
            <div class="stat-card"><i class="fas fa-city"></i><span class="number" id="stat-coordinador_municipal">0</span><span class="label">Municipal</span></div>
            <div class="stat-card"><i class="fas fa-map-marked-alt"></i><span class="number" id="stat-coordinador_distrital">0</span><span class="label">Distrital</span></div>
            <div class="stat-card"><i class="fas fa-map-pin"></i><span class="number" id="stat-responsable_zona">0</span><span class="label">Resp. Zona</span></div>
            <div class="stat-card"><i class="fas fa-bullhorn"></i><span class="number" id="stat-promotor">0</span><span class="label">Promotores</span></div>
        </div>

        <div class="search-container animate-up animate-delay-2">
            <div style="flex: 2; margin-right: 20px;">
                <label class="form-label" style="margin-bottom: 5px;">Buscar en el listado</label>
                <div style="position: relative;">
                    <i class="fas fa-search" style="position: absolute; left: 15px; top: 14px; color: #999;"></i>
                    <input type="text" id="searchInput" class="form-input-styled" style="padding-left: 40px;" placeholder="Buscar por nombre, teléfono o sección..." oninput="updateDashboard()">
                </div>
            </div>
        </div>

        <div class="table-section animate-up animate-delay-2">
            <div class="section-header">
                <h3 class="section-title">Directorio de Equipo</h3>
                <div class="ai-badge" style="font-size:10px;"><span id="visible-count">0</span> Registros visibles</div>
            </div>
            
            <div style="overflow-x: auto;">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Rol Asignado</th>
                            <th>Estado</th> 
                            <th>Sección</th>
                            <th>Teléfono</th>
                            <th>Estatus</th>
                            <th style="text-align: right;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="users-table-body">
                        <?php foreach ($usuarios as $usuario): ?>
                        <tr data-user-id="<?php echo $usuario['id']; ?>" 
                            data-user-table="<?php echo $usuario['rol_tabla']; ?>"
                            data-user-state="<?php echo strtolower($usuario['estado']); ?>">
                            
                            <td style="font-weight: 600;">
                                <?php echo htmlspecialchars(trim($usuario['nombre_completo']) ?: $usuario['nombres']); ?>
                            </td>
                            <td><span class="badge badge-<?php echo $usuario['rol_tabla']; ?>"><?php echo ucfirst(str_replace('_', ' ', $usuario['rol_tabla'])); ?></span></td>
                            <td style="color: #444;"><?php echo htmlspecialchars($usuario['estado'] ?? 'N/A'); ?></td>
                            <td style="color: #666;"><?php echo htmlspecialchars($usuario['seccion_electoral']); ?></td>
                            <td><?php echo htmlspecialchars($usuario['telefono']); ?></td>
                            <td><span class="badge badge-<?php echo $usuario['estatus']; ?>"><?php echo ucfirst($usuario['estatus']); ?></span></td>
                            <td style="text-align: right; position: relative;">
                                <button class="action-btn" onclick="toggleMenuUi(this, 'user-<?php echo $usuario['id']; ?>')">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <div class="dropdown-menu" id="menu-user-<?php echo $usuario['id']; ?>">
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
                <input type="hidden" id="userId" name="userId">
                <input type="hidden" id="userTable" name="userTable"> 
                <input type="hidden" id="formAction" name="action" value="create">

                <div class="form-group" style="margin-bottom: 25px; border-bottom: 1px solid #eee; padding-bottom: 20px;">
                    <label class="form-label" style="color: var(--primary); font-size: 13px;">1. Selecciona el Cargo / Rol</label>
                    <select class="form-input-styled" id="userRole" name="rol" required style="border-color: var(--primary); background: #f9fff9;">
                        <option value="" disabled selected>-- Selecciona --</option>
                        <option value="coordinador_municipal">Coordinador Municipal</option>
                        <option value="coordinador_distrital">Coordinador Distrital</option>
                        <option value="responsable_zona">Responsable de Zona</option>
                        <option value="promotor">Promotor</option>
                    </select>
                    <small style="color: #999; font-size: 11px; margin-top: 5px;">
                        * Los Coordinadores Estatales no se gestionan en este módulo.
                    </small>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div>
                        <label class="form-label">Nombre *</label>
                        <input type="text" class="form-input-styled" id="userNombrePila" name="nombre_pila" required placeholder="Ej: Oscar">
                    </div>
                    <div>
                        <label class="form-label">Apellido Paterno *</label>
                        <input type="text" class="form-input-styled" id="userApPaterno" name="ap_paterno" required placeholder="Ej: Ramirez">
                    </div>
                    <div>
                        <label class="form-label">Apellido Materno *</label>
                        <input type="text" class="form-input-styled" id="userApMaterno" name="ap_materno" required placeholder="Ej: García">
                    </div>

                    <div>
                        <label class="form-label">Teléfono *</label>
                        <input type="tel" class="form-input-styled" id="userPhone" name="telefono" required placeholder="Ej: 6621234567">
                    </div>
                    <div>
                        <label class="form-label">Correo *</label>
                        <input type="email" class="form-input-styled" id="userEmail" name="email" required placeholder="tu@correo.com">
                    </div>

                    <div style="grid-column: span 2; margin-top: 5px; margin-bottom: 5px;">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" id="checkPassword" onchange="togglePasswordSection()">
                            <span style="font-size: 14px; font-weight: 600; color: var(--primary);">
                                <i class="fas fa-key"></i> Asignar contraseña de acceso
                            </span>
                        </label>
                    </div>

                    <div id="passwordContainer" style="display: none; grid-column: span 2; background: #f8f9fa; padding: 15px; border-radius: 10px; border: 1px solid #e9ecef;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
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
                        <select class="form-input-styled" id="userEstado" name="estado" required>
                            <option value="" disabled selected>Selecciona...</option>
                            <?php foreach ($estados_disponibles as $estado): ?>
                            <option value="<?php echo $estado; ?>"><?php echo $estado; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Colonia *</label>
                        <input type="text" class="form-input-styled" id="userColonia" name="colonia" required placeholder="Ej: Centro">
                    </div>
                    <div>
                        <label class="form-label">Calle y Número *</label>
                        <input type="text" class="form-input-styled" id="userCalle" name="calle" required placeholder="Ej: Av. Principal #123">
                    </div>
                    <div>
                        <label class="form-label">Sección Electoral *</label>
                        <input type="text" class="form-input-styled" id="userSeccion" name="seccion_electoral" required placeholder="Ej: 1234" oninput="this.value = this.value.replace(/[^0-9]/g, '');" maxlength="10">
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

        function updateDashboard() {
            const stateFilter = document.getElementById('stateFilter').value;
            const search = document.getElementById('searchInput').value.toLowerCase();
            const allRows = Array.from(document.querySelectorAll('#users-table-body tr'));
            
            let counts = { total: 0, coordinador_estatal: 0, coordinador_municipal: 0, coordinador_distrital: 0, responsable_zona: 0, promotor: 0 };

            filteredRows = allRows.filter(row => {
                const rowState = row.getAttribute('data-user-state');
                const rowRole = row.getAttribute('data-user-table');
                const name = row.children[0].textContent.toLowerCase();
                const section = row.children[3].textContent.toLowerCase();
                const phone = row.children[4].textContent.toLowerCase();

                const matchesState = stateFilter === 'all' || rowState === stateFilter;
                const matchesSearch = name.includes(search) || phone.includes(search) || section.includes(search);

                if (matchesState && matchesSearch) {
                    counts.total++;
                    if (counts.hasOwnProperty(rowRole)) counts[rowRole]++;
                    return true;
                }
                return false;
            });

            ['total', 'coordinador_estatal', 'coordinador_municipal', 'coordinador_distrital', 'responsable_zona', 'promotor'].forEach(id => {
                document.getElementById('stat-'+id).textContent = counts[id];
            });
            document.getElementById('visible-count').textContent = counts.total;

            currentPage = 1;
            displayPage();
        }

        function displayPage() {
            const allRows = document.querySelectorAll('#users-table-body tr');
            allRows.forEach(row => row.style.display = 'none');

            const start = (currentPage - 1) * rowsPerPage;
            const end = start + rowsPerPage;
            const pageRows = filteredRows.slice(start, end);

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

        // LÓGICA DE CONTRASEÑA
        function togglePasswordSection() {
            const isChecked = document.getElementById('checkPassword').checked;
            const container = document.getElementById('passwordContainer');
            const passInput = document.getElementById('userPassword');
            const confirmInput = document.getElementById('userConfirmPassword');

            if (isChecked) {
                container.style.display = 'block';
                const action = document.getElementById('formAction').value;
                if (action === 'create') {
                    passInput.required = true;
                    confirmInput.required = true;
                }
            } else {
                container.style.display = 'none';
                passInput.required = false;
                confirmInput.required = false;
                passInput.value = '';
                confirmInput.value = '';
            }
        }

        function openModal(mode = 'create') {
            if (mode === 'create') {
                document.getElementById('modal-title').textContent = 'Nuevo Usuario';
                document.getElementById('formAction').value = 'create';
                form.reset();
                
                document.getElementById('userRole').value = ""; 
                document.getElementById('userRole').disabled = false; 
                
                document.getElementById('checkPassword').checked = false; 
                togglePasswordSection();
            }
            modal.classList.add('open');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            modal.classList.remove('open');
            document.body.style.overflow = '';
            setTimeout(() => form.reset(), 300);
        }

        // FUNCIONES PARA EL MODAL DE DETALLES (NUEVO)
        function closeViewModal() {
            viewModal.classList.remove('open');
            document.body.style.overflow = '';
        }

        async function viewUser(el) {
            const row = el.closest('tr');
            const userId = row.dataset.userId;
            const userTable = row.dataset.userTable;
            
            // NO hay restricción de rol aquí, permite ver Coordinadores Estatales

            try {
                const response = await fetch(`../../api/equipo/manage.php?action=get&id=${userId}&table=${userTable}`);
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

                    const nombreCompleto = `${user.nombres || ''} ${user.ap_paterno || ''} ${user.ap_materno || ''}`;
                    const rolFormateado = userTable.replace(/_/g, ' ').toUpperCase();

                    container.innerHTML = `
                        ${field('Rol / Cargo', `<span class="badge badge-${userTable}" style="font-size:12px;">${rolFormateado}</span>`, true)}
                        ${field('Nombre Completo', nombreCompleto, true)}
                        ${field('Teléfono', `<a href="tel:${user.telefono}" style="color:var(--primary); text-decoration:none;">${user.telefono}</a>`)}
                        ${field('Correo Electrónico', user.email)}
                        ${field('Estado', user.estado)}
                        ${field('Sección Electoral', user.seccion_electoral)}
                        ${field('Dirección', `${user.calle || ''}, ${user.colonia || ''}`, true)}
                        ${field('Estatus en Sistema', `<span class="badge badge-${user.estatus}">${user.estatus ? user.estatus.toUpperCase() : 'ACTIVO'}</span>`)}
                        ${field('Fecha de Registro', user.fecha_registro || 'N/A')}
                    `;

                    viewModal.classList.add('open');
                    document.body.style.overflow = 'hidden';
                } else {
                    showToast(data.message || 'No se pudieron cargar los datos', 'error');
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

            if (hasPassword) {
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
                const response = await fetch('../../api/equipo/manage.php', { method: 'POST', body: formData });
                const data = await response.json();

                if (data.success) {
                    showToast('Operación exitosa', 'success');
                    closeModal();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(data.message, 'error');
                }
            } catch (error) {
                showToast('Error de conexión', 'error');
            } finally {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        }

        async function editUser(el) {
            const row = el.closest('tr');
            const userId = row.dataset.userId;
            const userTable = row.dataset.userTable;

            if(userTable === 'coordinador_estatal') {
                alert("Los Coordinadores Estatales no se pueden editar desde aquí.");
                return;
            }

            try {
                const response = await fetch(`../../api/equipo/manage.php?action=get&id=${userId}&table=${userTable}`);
                const data = await response.json();

                if (data.success) {
                    const user = data.user;
                    document.getElementById('modal-title').textContent = 'Editar Usuario';
                    document.getElementById('formAction').value = 'edit';
                    document.getElementById('userId').value = user.id;
                    
                    document.getElementById('userTable').value = userTable;
                    const roleSelect = document.getElementById('userRole');
                    roleSelect.value = userTable;
                    roleSelect.disabled = true; 

                    document.getElementById('userNombrePila').value = user.nombres || '';
                    document.getElementById('userApPaterno').value = user.ap_paterno || '';
                    document.getElementById('userApMaterno').value = user.ap_materno || '';
                    document.getElementById('userPhone').value = user.telefono;
                    document.getElementById('userEmail').value = user.email;
                    document.getElementById('userEstado').value = user.estado;
                    document.getElementById('userColonia').value = user.colonia;
                    document.getElementById('userCalle').value = user.calle;
                    document.getElementById('userSeccion').value = user.seccion_electoral;
                    
                    document.getElementById('checkPassword').checked = false; 
                    togglePasswordSection();
                    
                    modal.classList.add('open');
                    document.body.style.overflow = 'hidden';
                } else {
                    showToast(data.message, 'error');
                }
            } catch (error) {
                showToast('Error de conexión', 'error');
            }
        }

        async function deleteUser(el) {
            const row = el.closest('tr');
            const userId = row.dataset.userId;
            const userTable = row.dataset.userTable;
            const userName = row.children[0].textContent;

            if(userTable === 'coordinador_estatal') {
                alert("No tienes permisos para eliminar Coordinadores Estatales.");
                return;
            }

            if (confirm(`¿Estás seguro de ELIMINAR a "${userName}"?`)) {
                try {
                    const formData = new FormData();
                    formData.append('action', 'delete');
                    formData.append('id', userId);
                    formData.append('table', userTable);

                    const response = await fetch('../../api/equipo/manage.php', { method: 'POST', body: formData });
                    const data = await response.json();

                    if (data.success) {
                        row.remove();
                        updateDashboard();
                        showToast('Usuario eliminado', 'success');
                    } else {
                        showToast(data.message, 'error');
                    }
                } catch (error) {
                    showToast('Error de conexión', 'error');
                }
            }
        }
    </script>
</body>
</html>