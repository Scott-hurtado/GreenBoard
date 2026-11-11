<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dirigentes - Proyecto Verde</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background: #f5f5f5;
            overflow-x: hidden;
        }

        /* Main Content Area */
        .container {
            background: #f5f5f5;
            min-height: 100vh;
            padding: 40px 60px;
            margin-left: 280px;
            transition: margin-left 0.3s ease;
        }

        .sidebar.collapsed ~ .container {
            margin-left: 80px;
        }
        
        @media (max-width: 768px) {
            .container {
                margin-left: 0;
                padding: 20px;
            }
        }
        
        @media (max-width: 1024px) {
            .container {
                padding: 30px 40px;
            }
        }

        .page-title {
            font-size: 42px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 10px;
            animation: fadeIn 0.6s ease forwards;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .page-title i {
            color: #2d7a3e;
        }

        .page-subtitle {
            color: #666;
            font-size: 16px;
            margin-bottom: 40px;
            animation: fadeIn 0.6s ease forwards 0.2s;
            opacity: 0;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Estados Grid */
        .estados-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }

        .estado-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            cursor: pointer;
            text-align: center;
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.8s ease forwards;
            opacity: 0;
        }

        .estado-card:nth-child(1) { animation-delay: 0.1s; }
        .estado-card:nth-child(2) { animation-delay: 0.15s; }
        .estado-card:nth-child(3) { animation-delay: 0.2s; }
        .estado-card:nth-child(4) { animation-delay: 0.25s; }
        .estado-card:nth-child(5) { animation-delay: 0.3s; }
        .estado-card:nth-child(6) { animation-delay: 0.35s; }
        .estado-card:nth-child(7) { animation-delay: 0.4s; }
        .estado-card:nth-child(8) { animation-delay: 0.45s; }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .estado-card:hover {
            transform: translateY(-10px) scale(1.05);
            box-shadow: 0 20px 60px rgba(45, 122, 62, 0.2);
        }

        .estado-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #2d7a3e 0%, #1f5a2d 100%);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .estado-card:hover::before {
            transform: scaleX(1);
        }

        .estado-img {
            width: 120px;
            height: 120px;
            margin: 0 auto 20px;
            object-fit: contain;
            transition: all 0.3s ease;
            filter: drop-shadow(0 2px 8px rgba(45, 122, 62, 0.2));
        }

        .estado-card:hover .estado-img {
            transform: scale(1.1);
            filter: drop-shadow(0 4px 12px rgba(45, 122, 62, 0.4));
        }

        .estado-nombre {
            font-size: 20px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 10px;
        }

        .estado-dirigente {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }

        .estado-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            background: #e8f5e9;
            color: #2d7a3e;
            margin-top: 10px;
        }

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
            animation: fadeInModal 0.3s ease;
        }

        @keyframes fadeInModal {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 20px;
            width: 90%;
            max-width: 1000px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: modalSlideIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        @keyframes modalSlideIn {
            from {
                transform: translateY(-100px) scale(0.9);
                opacity: 0;
            }
            to {
                transform: translateY(0) scale(1);
                opacity: 1;
            }
        }

        .modal-header {
            padding: 30px 40px;
            background: linear-gradient(135deg, #2d7a3e 0%, #1f5a2d 100%);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 20px 20px 0 0;
        }

        .modal-header-content {
            flex: 1;
        }

        .modal-header h2 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .modal-header p {
            font-size: 14px;
            opacity: 0.9;
        }

        .close-modal {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            font-size: 28px;
            cursor: pointer;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .close-modal:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 40px;
        }

        .mision-vision-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 40px;
        }

        .info-card {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 15px;
            border-left: 5px solid #2d7a3e;
        }

        .info-card h3 {
            font-size: 18px;
            font-weight: 700;
            color: #2d7a3e;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-card p {
            font-size: 15px;
            color: #666;
            line-height: 1.6;
        }

        .acciones-section {
            margin-top: 30px;
        }

        .acciones-title {
            font-size: 22px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Table */
        .table-container {
            overflow-x: auto;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        .data-table thead {
            background: #f8f9fa;
        }

        .data-table th {
            padding: 18px 20px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .data-table th:last-child {
            text-align: center;
            width: 100px;
        }

        .data-table tbody tr {
            border-bottom: 1px solid #f0f0f0;
            transition: all 0.3s ease;
        }

        .data-table tbody tr:hover {
            background: #f8f9fa;
            transform: translateX(5px);
        }

        .data-table tbody tr:last-child {
            border-bottom: none;
        }

        .data-table td {
            padding: 20px;
            font-size: 15px;
            color: #1a1a1a;
        }

        .data-table td:last-child {
            text-align: center;
        }

        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-badge.pendiente {
            background: #fff8e1;
            color: #f57c00;
        }

        .status-badge.proceso {
            background: #e3f2fd;
            color: #1565c0;
        }

        .status-badge.completado {
            background: #e8f5e9;
            color: #2d7a3e;
        }

        /* Dropdown */
        .action-dropdown {
            position: relative;
            display: inline-block;
        }
        
        .action-button {
            background: none;
            border: none;
            color: #666;
            font-size: 20px;
            cursor: pointer;
            padding: 5px 8px;
            border-radius: 5px;
            transition: color 0.2s ease, background-color 0.2s ease;
        }

        .action-button:hover {
            color: #2d7a3e;
            background-color: #f0f0f0;
        }

        .dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            min-width: 180px;
            z-index: 100;
            
            max-height: 0;
            opacity: 0;
            overflow: hidden;
            transform: translateY(10px);
            transition: max-height 0.3s ease-out, opacity 0.3s ease-out, transform 0.3s ease-out;
            pointer-events: none;
        }
        
        .dropdown-menu.show {
            max-height: 300px;
            opacity: 1;
            transform: translateY(0);
            pointer-events: auto;
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            text-decoration: none;
            color: #1a1a1a;
            font-size: 14px;
            transition: background-color 0.2s ease, color 0.2s ease;
            cursor: pointer;
        }

        .dropdown-item:hover {
            background-color: #e8f5e9;
            color: #2d7a3e;
        }
        
        .dropdown-item i {
            margin-right: 10px;
            width: 16px;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .mision-vision-section {
                grid-template-columns: 1fr;
            }

            .estados-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 20px;
            }

            .modal-content {
                max-width: 95%;
            }

            .modal-body {
                padding: 20px;
            }

            .data-table {
                font-size: 13px;
            }

            .data-table th,
            .data-table td {
                padding: 12px 10px;
            }
        }
    </style>
</head>
<body>
    <?php include 'components/sidebar_admin.php'; ?>
    
    <div class="container">
        <h1 class="page-title">
            <i class="fas fa-user-tie"></i>
            Dirigentes
        </h1>
        <p class="page-subtitle">Gestión de dirigentes estatales y sus acciones</p>

        <div class="estados-grid">
            <!-- Sonora -->
            <div class="estado-card" onclick="openModal('Sonora', 'Lic. María González')">
                <img src="mapas/sonora.jpg" alt="Mapa de Sonora" class="estado-img">
                <h3 class="estado-nombre">Sonora</h3>
                <p class="estado-dirigente">Lic. María González</p>
                <span class="estado-badge">8 Acciones</span>
            </div>

            <!-- Chihuahua -->
            <div class="estado-card" onclick="openModal('Chihuahua', 'Ing. Juan Pérez')">
                <img src="mapas/chihuahua.jpg" alt="Mapa de Chihuahua" class="estado-img">
                <h3 class="estado-nombre">Chihuahua</h3>
                <p class="estado-dirigente">Ing. Juan Pérez</p>
                <span class="estado-badge">12 Acciones</span>
            </div>

            <!-- Sinaloa -->
            <div class="estado-card" onclick="openModal('Sinaloa', 'Dr. Carlos Ruiz')">
                <img src="mapas/sinaloa.jpg" alt="Mapa de Sinaloa" class="estado-img">
                <h3 class="estado-nombre">Sinaloa</h3>
                <p class="estado-dirigente">Dr. Carlos Ruiz</p>
                <span class="estado-badge">6 Acciones</span>
            </div>

            <!-- Jalisco -->
            <div class="estado-card" onclick="openModal('Jalisco', 'Mtra. Ana López')">
                <img src="mapas/jalisco.jpg" alt="Mapa de Jalisco" class="estado-img">
                <h3 class="estado-nombre">Jalisco</h3>
                <p class="estado-dirigente">Mtra. Ana López</p>
                <span class="estado-badge">15 Acciones</span>
            </div>

            <!-- Nuevo León -->
            <div class="estado-card" onclick="openModal('Nuevo León', 'Lic. Roberto Sánchez')">
                <img src="mapas/nuevo.jpg" alt="Mapa de Nuevo León" class="estado-img">
                <h3 class="estado-nombre">Nuevo León</h3>
                <p class="estado-dirigente">Lic. Roberto Sánchez</p>
                <span class="estado-badge">10 Acciones</span>
            </div>

            <!-- Veracruz -->
            <div class="estado-card" onclick="openModal('Veracruz', 'Dra. Patricia Ramírez')">
                <img src="mapas/veracruz.jpg" alt="Mapa de Veracruz" class="estado-img">
                <h3 class="estado-nombre">Veracruz</h3>
                <p class="estado-dirigente">Dra. Patricia Ramírez</p>
                <span class="estado-badge">9 Acciones</span>
            </div>

            <!-- Puebla -->
            <div class="estado-card" onclick="openModal('Puebla', 'Ing. Fernando Torres')">
                <img src="mapas/puebla.jpg" alt="Mapa de Puebla" class="estado-img">
                <h3 class="estado-nombre">Puebla</h3>
                <p class="estado-dirigente">Ing. Fernando Torres</p>
                <span class="estado-badge">11 Acciones</span>
            </div>

            <!-- Guanajuato -->
            <div class="estado-card" onclick="openModal('Guanajuato', 'Mtro. Luis Mendoza')">
                <img src="mapas/guanajuato.jpg" alt="Mapa de Guanajuato" class="estado-img">
                <h3 class="estado-nombre">Guanajuato</h3>
                <p class="estado-dirigente">Mtro. Luis Mendoza</p>
                <span class="estado-badge">7 Acciones</span>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <!-- Modal -->
    <div class="modal" id="dirigentesModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-header-content">
                    <h2 id="modalEstado">Estado</h2>
                    <p id="modalDirigente">Dirigente</p>
                </div>
                <button class="close-modal" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="mision-vision-section">
                    <div class="info-card">
                        <h3><i class="fas fa-bullseye"></i> Misión</h3>
                        <p id="modalMision">Fortalecer el liderazgo político y social en el estado mediante la coordinación efectiva de brigadas comunitarias, promoviendo la participación ciudadana y el desarrollo sostenible de nuestras comunidades.</p>
                    </div>
                    <div class="info-card">
                        <h3><i class="fas fa-eye"></i> Visión</h3>
                        <p id="modalVision">Ser reconocidos como el referente estatal en organización política y social, construyendo una red sólida de líderes comprometidos con el bienestar de la población y el fortalecimiento democrático de nuestro estado.</p>
                    </div>
                </div>

                <div class="acciones-section">
                    <h3 class="acciones-title">
                        <i class="fas fa-tasks"></i>
                        Listado de Acciones
                    </h3>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Acción</th>
                                    <th>Responsable</th>
                                    <th>Estado</th>
                                    <th>Fecha de Entrega</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="tableBody">
                                <tr>
                                    <td>001</td>
                                    <td>Reunión con líderes comunitarios zona norte</td>
                                    <td>Coord. Norte</td>
                                    <td><span class="status-badge completado">Completado</span></td>
                                    <td>15/Nov/2024</td>
                                    <td>
                                        <div class="action-dropdown">
                                            <button class="action-button" onclick="toggleDropdown(this)">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <div class="dropdown-menu">
                                                <a href="#" class="dropdown-item" onclick="viewAction(1); closeAllDropdowns()">
                                                    <i class="fas fa-eye"></i> Ver Detalles
                                                </a>
                                                <a href="#" class="dropdown-item" onclick="editAction(1); closeAllDropdowns()">
                                                    <i class="fas fa-edit"></i> Editar
                                                </a>
                                                <a href="#" class="dropdown-item" onclick="deleteAction(1); closeAllDropdowns()">
                                                    <i class="fas fa-trash-alt"></i> Eliminar
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>002</td>
                                    <td>Capacitación de nuevos brigadistas</td>
                                    <td>Coord. Capacitación</td>
                                    <td><span class="status-badge proceso">En Proceso</span></td>
                                    <td>20/Dic/2024</td>
                                    <td>
                                        <div class="action-dropdown">
                                            <button class="action-button" onclick="toggleDropdown(this)">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <div class="dropdown-menu">
                                                <a href="#" class="dropdown-item" onclick="viewAction(2); closeAllDropdowns()">
                                                    <i class="fas fa-eye"></i> Ver Detalles
                                                </a>
                                                <a href="#" class="dropdown-item" onclick="editAction(2); closeAllDropdowns()">
                                                    <i class="fas fa-edit"></i> Editar
                                                </a>
                                                <a href="#" class="dropdown-item" onclick="deleteAction(2); closeAllDropdowns()">
                                                    <i class="fas fa-trash-alt"></i> Eliminar
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>003</td>
                                    <td>Jornada de afiliación zona centro</td>
                                    <td>Coord. Centro</td>
                                    <td><span class="status-badge proceso">En Proceso</span></td>
                                    <td>25/Dic/2024</td>
                                    <td>
                                        <div class="action-dropdown">
                                            <button class="action-button" onclick="toggleDropdown(this)">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <div class="dropdown-menu">
                                                <a href="#" class="dropdown-item" onclick="viewAction(3); closeAllDropdowns()">
                                                    <i class="fas fa-eye"></i> Ver Detalles
                                                </a>
                                                <a href="#" class="dropdown-item" onclick="editAction(3); closeAllDropdowns()">
                                                    <i class="fas fa-edit"></i> Editar
                                                </a>
                                                <a href="#" class="dropdown-item" onclick="deleteAction(3); closeAllDropdowns()">
                                                    <i class="fas fa-trash-alt"></i> Eliminar
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>004</td>
                                    <td>Entrega de informes trimestrales</td>
                                    <td>Secretario General</td>
                                    <td><span class="status-badge pendiente">Pendiente</span></td>
                                    <td>31/Dic/2024</td>
                                    <td>
                                        <div class="action-dropdown">
                                            <button class="action-button" onclick="toggleDropdown(this)">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <div class="dropdown-menu">
                                                <a href="#" class="dropdown-item" onclick="viewAction(4); closeAllDropdowns()">
                                                    <i class="fas fa-eye"></i> Ver Detalles
                                                </a>
                                                <a href="#" class="dropdown-item" onclick="editAction(4); closeAllDropdowns()">
                                                    <i class="fas fa-edit"></i> Editar
                                                </a>
                                                <a href="#" class="dropdown-item" onclick="deleteAction(4); closeAllDropdowns()">
                                                    <i class="fas fa-trash-alt"></i> Eliminar
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>005</td>
                                    <td>Reunión estratégica con dirigencia nacional</td>
                                    <td>Dirigente Estatal</td>
                                    <td><span class="status-badge pendiente">Pendiente</span></td>
                                    <td>10/Ene/2025</td>
                                    <td>
                                        <div class="action-dropdown">
                                            <button class="action-button" onclick="toggleDropdown(this)">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <div class="dropdown-menu">
                                                <a href="#" class="dropdown-item" onclick="viewAction(5); closeAllDropdowns()">
                                                    <i class="fas fa-eye"></i> Ver Detalles
                                                </a>
                                                <a href="#" class="dropdown-item" onclick="editAction(5); closeAllDropdowns()">
                                                    <i class="fas fa-edit"></i> Editar
                                                </a>
                                                <a href="#" class="dropdown-item" onclick="deleteAction(5); closeAllDropdowns()">
                                                    <i class="fas fa-trash-alt"></i> Eliminar
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Función para abrir el modal
        function openModal(estado, dirigente) {
            document.getElementById('modalEstado').textContent = estado;
            document.getElementById('modalDirigente').textContent = 'Dirigente: ' + dirigente;
            
            // Aquí podrías cargar dinámicamente la misión, visión y acciones específicas del estado
            // Por ahora usa valores por defecto
            
            document.getElementById('dirigentesModal').classList.add('show');
        }

        // Función para cerrar el modal
        function closeModal() {
            document.getElementById('dirigentesModal').classList.remove('show');
        }

        // Dropdown Functions
        function toggleDropdown(button) {
            closeAllDropdowns();
            const menu = button.nextElementSibling;
            if (menu) {
                menu.classList.toggle('show');
            }
        }

        function closeAllDropdowns() {
            document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                menu.classList.remove('show');
            });
        }

        document.addEventListener('click', (e) => {
            if (!e.target.closest('.action-dropdown')) {
                closeAllDropdowns();
            }
        });

        // Action Functions
        function viewAction(id) {
            alert('Ver detalles de acción #' + id);
            // Aquí iría la lógica para mostrar detalles
        }

        function editAction(id) {
            alert('Editar acción #' + id);
            // Aquí iría la lógica para editar
        }

        function deleteAction(id) {
            if (confirm('¿Estás seguro de que deseas eliminar esta acción?')) {
                alert('Acción #' + id + ' eliminada');
                // Aquí iría la lógica para eliminar de la base de datos
            }
        }

        // Cerrar modal al hacer clic fuera
        document.getElementById('dirigentesModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>