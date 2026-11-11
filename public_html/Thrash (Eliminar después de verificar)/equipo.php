<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Equipo - Proyecto Verde</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background: #f0f7f0;
        }

        /* Main Content */
        .main-content {
            margin-left: 280px; /* Margen para asumir que el sidebar incluido tiene 280px de ancho */
            padding: 30px;
            transition: margin-left 0.3s ease;
        }

        .sidebar.collapsed ~ .main-content {
            margin-left: 80px;
        }

        .page-header {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }

        .page-title {
            font-size: 32px;
            color: #1a1a2e;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .page-title i {
            /* CAMBIO: Color del icono del título de la página */
            color: #4caf50;
        }

        .page-subtitle {
            color: #666;
            font-size: 16px;
        }

        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            gap: 15px;
            flex-wrap: wrap;
        }

        .search-box {
            flex: 1;
            max-width: 400px;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 12px 45px 12px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .search-box input:focus {
            outline: none;
            /* CAMBIO: Borde de focus de input */
            border-color: #4caf50;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
        }

        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }

        .btn-primary {
            /* CAMBIO: Gradiente del botón principal a verde */
            background: linear-gradient(135deg, #004d40 0%, #4caf50 100%);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            /* CAMBIO: Sombra del botón principal */
            box-shadow: 0 4px 15px rgba(0, 77, 64, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            /* CAMBIO: Sombra hover del botón principal */
            box-shadow: 0 6px 20px rgba(0, 77, 64, 0.4);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        /* CAMBIO: Los iconos de stats usan gradientes de verde */
        .stat-icon.blue {
            background: linear-gradient(135deg, #004d40 0%, #4caf50 100%); /* Verde oscuro a medio */
        }

        .stat-icon.green {
            background: linear-gradient(135deg, #4caf50 0%, #8bc34a 100%); /* Verde medio a claro/lima */
        }

        .stat-icon.orange {
            background: linear-gradient(135deg, #8bc34a 0%, #00796b 100%); /* Lima a verde azulado */
        }

        .stat-content h3 {
            font-size: 28px;
            color: #1a1a2e;
            margin-bottom: 4px;
        }

        .stat-content p {
            color: #666;
            font-size: 14px;
        }

        .users-table {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .table-header {
            padding: 20px 25px;
            /* CAMBIO: Encabezado de tabla con gradiente de verde */
            background: linear-gradient(135deg, #004d40 0%, #4caf50 100%);
            color: white;
        }

        .table-header h3 {
            font-size: 18px;
            font-weight: 600;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: #f8f9fa;
        }

        thead th {
            padding: 15px 20px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        tbody tr {
            border-bottom: 1px solid #f0f0f0;
            transition: all 0.3s ease;
        }

        tbody tr:hover {
            background: #f8f9fa;
        }

        tbody td {
            padding: 18px 20px;
            font-size: 14px;
            color: #333;
        }

        .user-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar-small {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            /* CAMBIO: Avatar pequeño con gradiente de verde */
            background: linear-gradient(135deg, #004d40 0%, #4caf50 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 14px;
        }

        .user-details h4 {
            font-size: 14px;
            color: #1a1a2e;
            margin-bottom: 3px;
        }

        .user-details p {
            font-size: 12px;
            color: #999;
        }

        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        /* CAMBIO: Badges con tonalidades de verde */
        .badge.coordinador {
            background: rgba(76, 175, 80, 0.15); /* Verde claro */
            color: #4caf50;
        }

        .badge.brigadista {
            background: rgba(0, 121, 107, 0.15); /* Verde azulado/oscuro */
            color: #00796b;
        }

        .badge.activo {
            background: rgba(139, 195, 74, 0.15); /* Verde lima/brillante */
            color: #8bc34a;
        }

        .badge.inactivo {
            background: rgba(255, 82, 82, 0.15); /* Rojo para Inactivo (mantener contraste) */
            color: #ff5252;
        }

        /* --- Estilos para Dropdown de Acciones con Efecto Smooth --- */
        .action-dropdown {
            position: relative;
            display: inline-block;
        }

        .dropdown-toggle {
            background: none;
            border: none;
            color: #999;
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            transition: color 0.3s ease, background 0.3s ease;
            font-size: 16px;
        }

        .dropdown-toggle:hover {
            color: #333;
            background: #e0e0e0;
        }

        .dropdown-menu {
            /* Propiedades iniciales para el efecto smooth */
            position: absolute;
            right: 0;
            top: 100%;
            background-color: white;
            min-width: 180px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            border-radius: 8px;
            overflow: hidden;
            margin-top: 10px; 
            
            /* Oculto por defecto y transicionado */
            visibility: hidden;
            opacity: 0;
            transform: translateY(-10px);
            transition: opacity 0.3s ease, transform 0.3s ease, visibility 0s 0.3s; 
        }

        .dropdown-menu.show {
            visibility: visible;
            opacity: 1;
            transform: translateY(0);
            transition: opacity 0.3s ease, transform 0.3s ease; /* Transiciona solo las propiedades que cambian */
        }

        .dropdown-item {
            color: #333;
            padding: 10px 15px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        .dropdown-item:hover {
            background-color: #f0f7f0;
        }

        .dropdown-item i.fa-edit { color: #4caf50; } /* CAMBIO: Icono de editar a verde */
        .dropdown-item i.fa-trash-alt { color: #ff5252; }
        .dropdown-item i.fa-whatsapp { color: #25d366; }
        /* --- Fin Estilos para Dropdown de Acciones --- */


        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 15px;
            width: 90%;
            max-width: 650px; /* Aumentado para mejor visualización de 2 columnas */
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            /* Animación smooth para la apertura */
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            padding: 25px;
            /* CAMBIO: Encabezado del modal con gradiente de verde */
            background: linear-gradient(135deg, #004d40 0%, #4caf50 100%);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 15px 15px 0 0;
        }

        .modal-header h2 {
            font-size: 20px;
            font-weight: 600;
        }

        .close-modal {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .close-modal:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .modal-body {
            padding: 30px;
        }
        
        /* Estilos de Formulario traídos de registro (5).php */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }

        .form-group label .required { color: #d32f2f; }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            /* CAMBIO: Borde de focus de form input */
            border-color: #4caf50;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
        }
        
        .password-container {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #666;
            user-select: none;
            font-size: 18px;
        }

        .modal-footer {
            padding: 20px 30px;
            background: #f8f9fa;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            border-radius: 0 0 15px 15px;
        }

        .btn-secondary {
            background: #e0e0e0;
            color: #666;
            border: none;
            padding: 12px 25px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-secondary:hover {
            background: #d0d0d0;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .action-bar {
                flex-direction: column;
            }

            .search-box {
                max-width: 100%;
            }

            table {
                font-size: 12px;
            }

            thead th,
            tbody td {
                padding: 10px;
            }
            
            .modal-content {
                max-width: 95%;
            }

            .form-row { 
                grid-template-columns: 1fr; 
            }
        }
    </style>
</head>
<body>
    <?php include 'components/sidebar_admin.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-users-cog"></i>
                Gestión de Equipo
            </h1>
            <p class="page-subtitle">Crea y administra usuarios del sistema</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3>45</h3>
                    <p>Total de Usuarios</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-content">
                    <h3>38</h3>
                    <p>Usuarios Activos</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-user-tie"></i>
                </div>
                <div class="stat-content">
                    <h3>12</h3>
                    <p>Coordinadores</p>
                </div>
            </div>
        </div>

        <div class="action-bar">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Buscar por nombre, email o sección...">
            </div>
            <button class="btn-primary" onclick="openModal()">
                <i class="fas fa-user-plus"></i>
                Nuevo Usuario
            </button>
        </div>

        <div class="users-table">
            <div class="table-header">
                <h3>Lista de Usuarios</h3>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Rol</th>
                        <th>Sección Asignada</th>
                        <th>Estado</th>
                        <th>Fecha de Registro</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="usersTableBody">
                    <tr>
                        <td>
                            <div class="user-cell">
                                <div class="user-avatar-small">MC</div>
                                <div class="user-details">
                                    <h4>María Cruz</h4>
                                    <p>maria.cruz@ejemplo.com</p>
                                </div>
                            </div>
                        </td>
                        <td><span class="badge coordinador">Coordinador</span></td>
                        <td>Sección 001 - Centro</td>
                        <td><span class="badge activo">Activo</span></td>
                        <td>15/Nov/2024</td>
                        <td>
                            <div class="action-dropdown">
                                <button class="dropdown-toggle" onclick="toggleDropdown(this)">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item" onclick="editUser(1); closeAllDropdowns()">
                                        <i class="fas fa-edit"></i> Editar
                                    </a>
                                    <a class="dropdown-item" onclick="deleteUser(1); closeAllDropdowns()">
                                        <i class="fas fa-trash-alt"></i> Eliminar
                                    </a>
                                    <a class="dropdown-item" onclick="sendWhatsApp(1)">
                                        <i class="fab fa-whatsapp"></i> Enviar WhatsApp
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="user-cell">
                                <div class="user-avatar-small">JL</div>
                                <div class="user-details">
                                    <h4>Juan López</h4>
                                    <p>juan.lopez@ejemplo.com</p>
                                </div>
                            </div>
                        </td>
                        <td><span class="badge brigadista">Brigadista</span></td>
                        <td>Sección 002 - Norte</td>
                        <td><span class="badge activo">Activo</span></td>
                        <td>18/Nov/2024</td>
                        <td>
                            <div class="action-dropdown">
                                <button class="dropdown-toggle" onclick="toggleDropdown(this)">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item" onclick="editUser(2); closeAllDropdowns()">
                                        <i class="fas fa-edit"></i> Editar
                                    </a>
                                    <a class="dropdown-item" onclick="deleteUser(2); closeAllDropdowns()">
                                        <i class="fas fa-trash-alt"></i> Eliminar
                                    </a>
                                    <a class="dropdown-item" onclick="sendWhatsApp(2)">
                                        <i class="fab fa-whatsapp"></i> Enviar WhatsApp
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="user-cell">
                                <div class="user-avatar-small">AR</div>
                                <div class="user-details">
                                    <h4>Ana Ramírez</h4>
                                    <p>ana.ramirez@ejemplo.com</p>
                                </div>
                            </div>
                        </td>
                        <td><span class="badge coordinador">Coordinador</span></td>
                        <td>Sección 003 - Sur</td>
                        <td><span class="badge activo">Activo</span></td>
                        <td>20/Nov/2024</td>
                        <td>
                            <div class="action-dropdown">
                                <button class="dropdown-toggle" onclick="toggleDropdown(this)">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item" onclick="editUser(3); closeAllDropdowns()">
                                        <i class="fas fa-edit"></i> Editar
                                    </a>
                                    <a class="dropdown-item" onclick="deleteUser(3); closeAllDropdowns()">
                                        <i class="fas fa-trash-alt"></i> Eliminar
                                    </a>
                                    <a class="dropdown-item" onclick="sendWhatsApp(3)">
                                        <i class="fab fa-whatsapp"></i> Enviar WhatsApp
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="user-cell">
                                <div class="user-avatar-small">PG</div>
                                <div class="user-details">
                                    <h4>Pedro García</h4>
                                    <p>pedro.garcia@ejemplo.com</p>
                                </div>
                            </div>
                        </td>
                        <td><span class="badge brigadista">Brigadista</span></td>
                        <td>Sección 004 - Este</td>
                        <td><span class="badge inactivo">Inactivo</span></td>
                        <td>22/Nov/2024</td>
                        <td>
                            <div class="action-dropdown">
                                <button class="dropdown-toggle" onclick="toggleDropdown(this)">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item" onclick="editUser(4); closeAllDropdowns()">
                                        <i class="fas fa-edit"></i> Editar
                                    </a>
                                    <a class="dropdown-item" onclick="deleteUser(4); closeAllDropdowns()">
                                        <i class="fas fa-trash-alt"></i> Eliminar
                                    </a>
                                    <a class="dropdown-item" onclick="sendWhatsApp(4)">
                                        <i class="fab fa-whatsapp"></i> Enviar WhatsApp
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </main>

    <div class="modal" id="userModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Nuevo Usuario</h2>
                <button class="close-modal" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="userForm">
                    <div class="form-group">
                        <label for="userName">Nombre Completo <span class="required">*</span></label>
                        <input type="text" id="userName" required placeholder="Ej: Juan Pérez García">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="userPhone">Teléfono Celular <span class="required">*</span></label>
                            <input type="tel" id="userPhone" pattern="[0-9]{10}" required placeholder="Ej: 6621234567">
                        </div>

                        <div class="form-group">
                            <label for="userEmail">Correo Electrónico <span class="required">*</span></label>
                            <input type="email" id="userEmail" required placeholder="tu@correo.com">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="userPassword">Contraseña <span class="required">*</span></label>
                            <div class="password-container">
                                <input type="password" id="password" minlength="8" required placeholder="Mínimo 8 caracteres">
                                <span class="password-toggle" onclick="togglePassword('password')">👁️</span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="confirmPassword">Confirmar Contraseña <span class="required">*</span></label>
                            <div class="password-container">
                                <input type="password" id="confirm-password" minlength="8" required placeholder="Repite tu contraseña">
                                <span class="password-toggle" onclick="togglePassword('confirm-password')">👁️</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="userColonia">Colonia / Barrio <span class="required">*</span></label>
                            <input type="text" id="userColonia" required placeholder="Ej: Centro">
                        </div>

                        <div class="form-group">
                            <label for="userAddress">Calle y Número <span class="required">*</span></label>
                            <input type="text" id="userAddress" required placeholder="Ej: Av. Principal #123">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="userSection">Sección Electoral <span class="required">*</span></label>
                            <input type="number" id="userSection" min="1" required placeholder="Ej: 1234">
                        </div>

                        <div class="form-group">
                            <label for="userRole">Rol <span class="required">*</span></label>
                            <select id="userRole" required>
                                <option value="">Selecciona un rol</option>
                                <option value="coordinador">Coordinador</option>
                                <option value="brigadista">Brigadista</option>
                                <option value="simpatizante">Simpatizante</option>
                                <option value="voluntario">Voluntario</option>
                                <option value="promotor">Promotor</option>
                                <option value="lider_zona">Líder de Zona</option>
                            </select>
                        </div>
                    </div>
                    
                    </form>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeModal()">Cancelar</button>
                <button class="btn-primary" onclick="saveUser()">
                    <i class="fas fa-save"></i>
                    Guardar
                </button>
            </div>
        </div>
    </div>

    <script>
        // --- Funciones de Dropdown ---
        function closeAllDropdowns() {
            document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                menu.classList.remove('show');
            });
        }

        function toggleDropdown(button) {
            const menu = button.nextElementSibling;
            
            // Cierra todos los demás dropdowns
            document.querySelectorAll('.dropdown-menu').forEach(item => {
                if (item !== menu) {
                    item.classList.remove('show');
                }
            });

            // Muestra/Oculta el dropdown actual
            menu.classList.toggle('show');
        }

        // Cierra los dropdowns si se hace clic fuera
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.action-dropdown')) {
                closeAllDropdowns();
            }
        });
        // --- Fin Funciones de Dropdown ---


        // Logout function
        function logout() {
            if (confirm('¿Estás seguro de que deseas cerrar sesión?')) {
                window.location.href = 'index.php';
            }
        }

        // --- Modal Functions ---
        function openModal() {
            document.getElementById('userModal').classList.add('show');
            document.getElementById('modalTitle').textContent = 'Nuevo Usuario';
            document.getElementById('userForm').reset();
            // Restablece los mensajes de error del formulario si los hay
            document.getElementById('password').setCustomValidity('');
            document.getElementById('confirm-password').setCustomValidity('');
        }

        function closeModal() {
            document.getElementById('userModal').classList.remove('show');
        }

        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.nextElementSibling;
            if (input.type === 'password') {
                input.type = 'text';
                icon.textContent = '🙈'; // O usa un icono de FontAwesome si prefieres
            } else {
                input.type = 'password';
                icon.textContent = '👁️';
            }
        }


        function saveUser() {
            const form = document.getElementById('userForm');
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm-password').value;
            
            // Validación de contraseñas
            if (password !== confirmPassword) {
                document.getElementById('confirm-password').setCustomValidity('Las contraseñas no coinciden.');
            } else {
                document.getElementById('confirm-password').setCustomValidity('');
            }
            
            if (form.checkValidity()) {
                alert('Usuario guardado/actualizado exitosamente');
                closeModal();
                // Aquí irían las funciones AJAX para guardar/actualizar en la base de datos
            } else {
                form.reportValidity();
            }
        }

        function editUser(id) {
            openModal();
            document.getElementById('modalTitle').textContent = 'Editar Usuario #' + id;
            // Aquí cargarías los datos del usuario para rellenar los campos
            alert('Cargando datos para editar usuario #' + id);
        }

        function deleteUser(id) {
            if (confirm('¿Estás seguro de que deseas eliminar este usuario?')) {
                alert('Usuario #' + id + ' eliminado');
                // Aquí irían las funciones AJAX para eliminar de la base de datos
            }
        }
        
        function sendWhatsApp(id) {
            const phoneNumber = '6623032238'; // Número fijo solicitado
            const message = encodeURIComponent(`Hola, te contacto desde el panel de administración de Proyecto Verde. Tu ID de usuario es ${id}.`);
            
            // Abre una nueva pestaña o ventana con el enlace de WhatsApp
            window.open(`https://wa.me/${phoneNumber}?text=${message}`, '_blank');
            closeAllDropdowns();
        }
        // --- Fin Modal Functions ---


        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('#usersTableBody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });

        // Close modal when clicking outside
        document.getElementById('userModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>