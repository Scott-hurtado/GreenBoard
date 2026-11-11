<?php
// 1. Carga del sistema y seguridad
require_once __DIR__ . '/../../includes/bootstrap.php';

$rol = $_SESSION['user_role'] ?? '';
// Permitimos acceso a los perfiles operativos
$permitidos = ['coordinador_zona', 'coordinador_regional', 'coordinador_estatal', 'admin', 'superadmin'];

if (!in_array($rol, $permitidos)) {
    header('Location: /public/index.php'); 
    exit();
}

// Variables de sesión operativas
$user_zona = $_SESSION['user_zona_nombre'] ?? 'Zona Asignada'; 
$user_zona_id = $_SESSION['user_zona_id'] ?? null; 
$user_estado = $_SESSION['user_estado'] ?? 'Sonora';

// Conexión
$db = Database::getInstance()->getConnection();

// Función para leer de la tabla central 'usuarios' PERO FILTRADO POR SU ZONA
function obtenerUsuariosDeZona($db, $zona_nombre) {
    $usuarios = [];
    try {
        // Solo traemos usuarios que pertenezcan a la zona del coordinador 
        // y que tengan un rol inferior o igual a sección/operador.
        $sql = "SELECT 
                    id, nombre_completo, nombre_completo AS nombres, telefono, correo AS email, 
                    colonia, direccion AS calle, seccion_electoral, 
                    IF(activo = 1, 'activo', 'inactivo') AS estatus, 
                    estado, region, zona, fecha_registro, rol AS rol_tabla 
                FROM usuarios 
                WHERE zona = :zona 
                AND rol IN ('seccion', 'operador', 'movilizador', 'ciudadano')
                ORDER BY seccion_electoral ASC, nombre_completo ASC";
                
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':zona', $zona_nombre, PDO::PARAM_STR);
        $stmt->execute();
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error al cargar usuarios de zona: " . $e->getMessage());
        return [];
    }

    return $usuarios;
}

$usuarios = obtenerUsuariosDeZona($db, $user_zona);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estructura de Secciones - Proyecto Verde</title>
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

        .grid-overview { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 30px; }
        .stat-card { background: var(--white); border: 1px solid var(--gray-200); padding: 20px 15px; border-radius: 16px; text-align: center; box-shadow: var(--shadow-sm); position: relative; overflow: hidden; display: flex; flex-direction: column; align-items: center; justify-content: center; transition: all 0.3s ease; }
        .stat-card:hover { transform: translateY(-5px); border-color: var(--primary); }
        .stat-card i { font-size: 28px; color: var(--primary); margin-bottom: 15px; opacity: 0.8; }
        .stat-card .number { font-size: 36px; font-weight: 900; color: var(--dark); line-height: 1; }
        .stat-card .label { font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-top: 8px; }

        .search-container { background: var(--white); border: 1px solid var(--gray-200); border-radius: 16px; padding: 20px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: var(--shadow-sm); }
        .form-input-styled { width: 100%; padding: 12px 15px; border-radius: 10px; border: 2px solid var(--gray-200); background: var(--gray-100); font-size: 14px; font-weight: 600; color: var(--dark); transition: all 0.3s; outline: none; }
        .form-input-styled:focus { border-color: var(--primary); background: var(--white); }

        .table-section { background: var(--white); border-radius: 20px; padding: 30px; border: 1px solid var(--gray-200); box-shadow: var(--shadow-sm); }
        .section-header { margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; }
        .modern-table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .modern-table th { text-align: left; padding: 15px 20px; color: var(--text-muted); font-size: 12px; text-transform: uppercase; letter-spacing: 1px; border-bottom: 2px solid var(--gray-200); }
        .modern-table td { padding: 20px; border-bottom: 1px solid var(--gray-200); font-size: 14px; color: var(--dark); font-weight: 500; vertical-align: middle; }
        
        .sortable { cursor: pointer; user-select: none; transition: background-color 0.2s; position: relative; }
        .sortable:hover { background-color: var(--gray-100); border-radius: 8px 8px 0 0; }
        .sort-icon { display: inline-block; margin-left: 6px; font-size: 14px; opacity: 0.3; transition: all 0.2s; }
        .sortable.active { color: var(--primary); font-weight: 800; }
        .sortable.active .sort-icon { opacity: 1; color: var(--primary); }

        .badge { padding: 6px 12px; border-radius: 30px; font-size: 11px; font-weight: 700; text-transform: uppercase; display: inline-block; }
        .badge-seccion { background: #e0f2f1; color: #00796b; }
        .badge-operador { background: #e8f5e9; color: #2e7d32; }
        .badge-movilizador { background: #f3e5f5; color: #7b1fa2; }
        .badge-ciudadano { background: #e0f7fa; color: #00838f; }
        .badge-activo { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        .badge-inactivo { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }

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
            .grid-overview { grid-template-columns: repeat(2, 1fr); }
            .page-header { flex-direction: column; align-items: flex-start; gap: 20px; }
            .design-text { display: none; }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/components/sidebar_cz.php'; ?>
    
    <div class="design-text">SECCIONES ESTRUCTURA</div>

    <div class="container">
        <div class="page-header animate-up">
            <div>
                <div class="ai-badge">ZONA: <?php echo htmlspecialchars($user_zona); ?></div>
                <h1 class="main-title">Estructura de<br>Secciones.</h1>
                <p class="description">Administración y asignación de responsables y brigadistas para tu zona territorial.</p>
            </div>
            <div>
                <button class="btn-primary-action" onclick="openModal('create')">
                    <p class="fas fa-user-plus"></p> Registrar Operador
                </button>
            </div>
        </div>

        <div class="grid-overview animate-up animate-delay-1">
            <div class="stat-card"><i class="fas fa-users"></i><span class="number" id="stat-total">0</span><span class="label">Total en Zona</span></div>
            <div class="stat-card"><i class="fas fa-user-tag"></i><span class="number" id="stat-seccion">0</span><span class="label">Coord. Sección</span></div>
            <div class="stat-card"><i class="fas fa-walking"></i><span class="number" id="stat-operador">0</span><span class="label">Brigadistas</span></div>
            <div class="stat-card"><i class="fas fa-handshake"></i><span class="number" id="stat-ciudadano">0</span><span class="label">Simpatizantes</span></div>
        </div>

        <div class="search-container animate-up animate-delay-2">
            <div style="flex: 2;">
                <div style="position: relative;">
                    <i class="fas fa-search" style="position: absolute; left: 15px; top: 14px; color: #999;"></i>
                    <input type="text" id="searchInput" class="form-input-styled" style="padding-left: 40px;" placeholder="Buscar por nombre, teléfono o sección electoral..." oninput="updateDashboard()">
                </div>
            </div>
        </div>

        <div class="table-section animate-up animate-delay-2">
            <div class="section-header">
                <h3 class="section-title">Directorio de Estructura</h3>
                <div class="ai-badge" style="font-size:10px;"><span id="visible-count">0</span> Registros visibles</div>
            </div>
            
            <div style="overflow-x: auto;">
                <table class="modern-table" id="teamTable">
                    <thead>
                        <tr>
                            <th class="sortable active" onclick="sortTable(0, 'number')">Sección <i class="fas fa-sort-up sort-icon"></i></th>
                            <th class="sortable" onclick="sortTable(1, 'string')">Nombre <i class="fas fa-sort sort-icon"></i></th>
                            <th class="sortable" onclick="sortTable(2, 'string')">Rol Asignado <i class="fas fa-sort sort-icon"></i></th>
                            <th class="sortable" onclick="sortTable(3, 'string')">Teléfono <i class="fas fa-sort sort-icon"></i></th>
                            <th class="sortable" onclick="sortTable(4, 'string')">Estatus <i class="fas fa-sort sort-icon"></i></th>
                            <th style="text-align: right;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="users-table-body">
                        <?php foreach ($usuarios as $usuario): ?>
                        <tr data-user-id="<?php echo $usuario['id']; ?>" 
                            data-user-table="<?php echo $usuario['rol_tabla']; ?>">
                            
                            <td style="color: #666; font-weight: 800; font-size: 16px;">
                                <?php echo htmlspecialchars($usuario['seccion_electoral']); ?>
                            </td>
                            <td style="font-weight: 600;">
                                <?php echo htmlspecialchars(trim($usuario['nombre_completo']) ?: $usuario['nombres']); ?>
                            </td>
                            <td><span class="badge badge-<?php echo $usuario['rol_tabla']; ?>"><?php echo ucfirst(str_replace('_', ' ', $usuario['rol_tabla'])); ?></span></td>
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
            <h2 id="modal-title" style="font-size: 24px; font-weight: 800; margin-bottom: 5px; color: var(--dark);">Registrar Operador</h2>
            <p style="font-size: 14px; color: #999; margin-bottom: 25px;">Agrega un nuevo enlace a tu estructura territorial.</p>
            
            <form id="userForm" onsubmit="handleSubmit(event)">
                <input type="hidden" id="userId" name="id">
                <input type="hidden" id="formAction" name="action" value="create">
                
                <input type="hidden" name="estado" value="<?php echo htmlspecialchars($user_estado); ?>">
                <input type="hidden" name="zona" value="<?php echo htmlspecialchars($user_zona); ?>">
                <div class="form-group" style="margin-bottom: 25px; border-bottom: 1px solid #eee; padding-bottom: 20px;">
                    <label class="form-label" style="color: var(--primary); font-size: 13px;">1. Selecciona el Cargo</label>
                    <select class="form-input-styled" id="userRole" name="rol" required style="border-color: var(--primary); background: #f9fff9;">
                        <option value="" disabled selected>-- Selecciona --</option>
                        <option value="seccion">Coordinador de Sección</option>
                        <option value="operador">Operador / Brigadista</option>
                        <option value="movilizador">Movilizador</option>
                        <option value="ciudadano">Ciudadano / Simpatizante</option>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div style="grid-column: span 2;">
                        <label class="form-label">Nombre Completo *</label>
                        <input type="text" class="form-input-styled" id="userNombre" name="nombre" required placeholder="Ej: Oscar Ramirez García">
                    </div>
                    <div>
                        <label class="form-label">Sección Electoral *</label>
                        <input type="text" class="form-input-styled" id="userSeccion" name="seccion_electoral" required placeholder="Ej: 1234" oninput="this.value = this.value.replace(/[^0-9]/g, '');" maxlength="10">
                    </div>
                    <div>
                        <label class="form-label">Teléfono *</label>
                        <input type="tel" class="form-input-styled" id="userPhone" name="telefono" required placeholder="Ej: 6621234567" maxlength="10">
                    </div>
                    <div>
                        <label class="form-label">Clave de Elector (INE)</label>
                        <input type="text" class="form-input-styled" id="userIne" name="ine" placeholder="18 caracteres" maxlength="18" style="text-transform:uppercase">
                    </div>
                    <div>
                        <label class="form-label">Correo (Opcional)</label>
                        <input type="email" class="form-input-styled" id="userEmail" name="correo" placeholder="tu@correo.com">
                    </div>
                    
                    <div style="grid-column: span 2; margin-top: 15px;">
                        <div style="font-size: 12px; color: #666; background: #f8f9fa; padding: 10px; border-radius: 8px;">
                            <i class="fas fa-info-circle" style="color:var(--primary);"></i> Este usuario será asignado automáticamente a tu Zona: <b><?php echo htmlspecialchars($user_zona); ?></b>
                        </div>
                    </div>

                    <div style="grid-column: span 2; margin-top: 5px; margin-bottom: 5px;">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" id="checkPassword" name="requiere_acceso" value="true" onchange="togglePasswordSection()">
                            <span style="font-size: 14px; font-weight: 600; color: var(--primary);">
                                <i class="fas fa-mobile-alt"></i> Asignar acceso a la App Móvil
                            </span>
                        </label>
                    </div>

                    <div id="passwordContainer" style="display: none; grid-column: span 2; background: #f8f9fa; padding: 15px; border-radius: 10px; border: 1px solid #e9ecef;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div style="grid-column: span 2;">
                                <label class="form-label">Nombre de Usuario *</label>
                                <input type="text" class="form-input-styled" id="username" name="username" placeholder="usuario_app">
                            </div>
                            <div>
                                <label class="form-label">Contraseña</label>
                                <input type="password" class="form-input-styled" id="userPassword" name="password" placeholder="Mínimo 8 caracteres">
                            </div>
                            <div>
                                <label class="form-label">Confirmar Contraseña</label>
                                <input type="password" class="form-input-styled" id="userConfirmPassword" name="confirm_password" placeholder="Repítela">
                            </div>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn-primary-action" style="width: 100%; justify-content: center; margin-top: 30px;">
                    <i class="fas fa-save"></i> Guardar Operador
                </button>
            </form>
        </div>
    </div>

    <div class="modal" id="viewModal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="close-modal" onclick="closeViewModal()"><i class="fas fa-times"></i></div>
            
            <div style="margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px;">
                <div class="ai-badge" style="margin-bottom: 10px;">ESTRUCTURA LOCAL</div>
                <h2 style="font-size: 28px; font-weight: 900; color: var(--dark);">Ficha del Operador</h2>
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

        // Variables de ordenamiento (Sorting)
        let currentSortCol = 0; // Ordenado por sección por defecto
        let isSortAscending = true;
        let currentSortType = 'number';

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

        function updateDashboard() {
            const search = document.getElementById('searchInput').value.toLowerCase();
            const allRows = Array.from(document.querySelectorAll('#users-table-body tr'));
            
            let counts = { total: 0, seccion: 0, operador: 0, ciudadano: 0 };

            filteredRows = allRows.filter(row => {
                const rowRole = row.getAttribute('data-user-table');
                
                const seccion = row.children[0].textContent.toLowerCase();
                const name = row.children[1].textContent.toLowerCase();
                const phone = row.children[3].textContent.toLowerCase();

                const matchesSearch = name.includes(search) || phone.includes(search) || seccion.includes(search);

                if (matchesSearch) {
                    counts.total++;
                    if (rowRole === 'seccion') counts.seccion++;
                    else if (rowRole === 'ciudadano' || rowRole === 'simpatizante') counts.ciudadano++;
                    else counts.operador++; // Operador, brigadista, movilizador
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

            document.getElementById('stat-total').textContent = counts.total;
            document.getElementById('stat-seccion').textContent = counts.seccion;
            document.getElementById('stat-operador').textContent = counts.operador;
            document.getElementById('stat-ciudadano').textContent = counts.ciudadano;
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
                document.getElementById('modal-title').textContent = 'Registrar Operador';
                document.getElementById('formAction').value = 'create';
                form.reset();
                document.getElementById('userId').value = "";
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

        function closeViewModal() {
            viewModal.classList.remove('open');
            document.body.style.overflow = '';
        }

        const API_URL = '../../api/create_users/crud_usuarios.php'; 

        async function viewUser(el) {
            const row = el.closest('tr');
            const userId = row.dataset.userId;
            
            try {
                const response = await fetch(`${API_URL}?action=get&id=${userId}`);
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

                    const rolFormateado = (user.rol || 'N/A').replace(/_/g, ' ').toUpperCase();
                    const accceso = user.requiere_acceso == 1 ? '<i class="fas fa-check-circle" style="color:green;"></i> Sí, con App Móvil' : '<i class="fas fa-times-circle" style="color:#ccc;"></i> No';

                    container.innerHTML = `
                        ${field('Cargo Estructural', `<span class="badge badge-${user.rol}" style="font-size:12px;">${rolFormateado}</span>`, true)}
                        ${field('Sección Electoral', `<strong style="font-size: 18px; color: var(--primary);">${user.seccion_electoral}</strong>`)}
                        ${field('Nombre Completo', user.nombre_completo, true)}
                        ${field('Teléfono', `<a href="tel:${user.telefono}" style="color:var(--primary); text-decoration:none;">${user.telefono}</a>`)}
                        ${field('Identificación', `<b>INE:</b> ${user.ine || 'N/A'}`)}
                        ${field('Acceso al Sistema', accceso)}
                        ${field('Usuario de App', user.username)}
                        ${field('Fecha de Integración', user.fecha_registro || 'N/A', true)}
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

            try {
                const response = await fetch(`${API_URL}?action=get&id=${userId}`);
                const data = await response.json();

                if (data.success) {
                    const user = data.user;
                    document.getElementById('modal-title').textContent = 'Editar Operador';
                    document.getElementById('formAction').value = 'edit';
                    document.getElementById('userId').value = user.id;
                    
                    const roleSelect = document.getElementById('userRole');
                    roleSelect.value = user.rol;
                    roleSelect.disabled = false; 

                    document.getElementById('userNombre').value = user.nombre_completo || '';
                    document.getElementById('userPhone').value = user.telefono;
                    document.getElementById('userEmail').value = user.correo || '';
                    
                    document.getElementById('userIne').value = user.ine || '';
                    document.getElementById('userSeccion').value = user.seccion_electoral || '';
                    
                    const cbPass = document.getElementById('checkPassword');
                    if (user.requiere_acceso == 1) {
                        cbPass.checked = true;
                        document.getElementById('username').value = user.username || '';
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
            const userName = row.children[1].textContent.trim();

            if (confirm(`¿Estás seguro de dar de BAJA permanentemente a "${userName}" de tu estructura?`)) {
                try {
                    const formData = new FormData();
                    formData.append('action', 'delete');
                    formData.append('id', userId);

                    const response = await fetch(API_URL, { method: 'POST', body: formData });
                    const data = await response.json();

                    if (data.success) {
                        row.remove();
                        updateDashboard();
                        showToast('Usuario removido de la estructura', 'success');
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