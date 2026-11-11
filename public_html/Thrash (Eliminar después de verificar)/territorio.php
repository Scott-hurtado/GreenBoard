<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Territorio - Proyecto Verde</title>
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

        /* Main Content Area - Adjusted for included sidebar */
        .container {
            background: #f5f5f5;
            min-height: 100vh;
            padding: 40px 60px;
            margin-left: 280px; /* Default margin for standard sidebar width */
            transition: margin-left 0.3s ease;
        }

        /* Responsive style for when the included sidebar is collapsed (adjust based on sidebar's internal logic) */
        .sidebar.collapsed ~ .container {
            margin-left: 80px;
        }
        
        /* Responsive adjustment for mobile */
        @media (max-width: 768px) {
            .container {
                margin-left: 0; /* Full width on mobile when sidebar is closed/hidden */
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
            margin-bottom: 40px;
            animation: fadeIn 0.6s ease forwards;
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

        /* Filters Section */
        .filters-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 40px;
            animation: slideInDown 0.6s ease forwards;
        }

        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .filter-group label {
            font-size: 16px;
            font-weight: 600;
            color: #666;
        }

        .filter-group select {
            padding: 14px 18px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            background: white;
            color: #1a1a1a;
            cursor: pointer;
            transition: all 0.3s ease;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23333' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 15px center;
            padding-right: 40px;
        }

        .filter-group select:hover {
            border-color: #2d7a3e;
        }

        .filter-group select:focus {
            outline: none;
            border-color: #2d7a3e;
            box-shadow: 0 0 0 3px rgba(45, 122, 62, 0.1);
        }

        /* Main Content Area */
        .content-area {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            animation: fadeInUp 0.8s ease forwards;
        }

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

        /* Map and Progress Container */
        .map-container-wrapper {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 40px;
            margin-bottom: 50px;
        }

        /* Map Container */
        .map-container {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 500px;
            position: relative;
            overflow: hidden;
            cursor: grab;
        }

        .map-container:active {
            cursor: grabbing;
        }

        .map-wrapper {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.3s ease;
            transform-origin: center;
        }

        #mx-map {
            max-width: 100%;
            height: auto;
            animation: zoomIn 1s ease forwards;
            transition: transform 0.3s ease;
        }

        @keyframes zoomIn {
            from {
                opacity: 0;
                transform: scale(0.8);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        /* Map controls */
        .map-controls {
            position: absolute;
            top: 20px;
            right: 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            z-index: 10;
            animation: fadeIn 1s ease forwards 0.5s;
            opacity: 0;
        }

        .map-control-btn {
            width: 40px;
            height: 40px;
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 18px;
            color: #2d7a3e;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .map-control-btn:hover {
            background: #2d7a3e;
            color: white;
            border-color: #2d7a3e;
            transform: scale(1.1);
        }

        .map-control-btn:active {
            transform: scale(0.95);
        }

        /* SVG Path styles */
        #mx-map path {
            transition: all 0.3s ease;
            cursor: pointer;
        }

        /* Progress Section */
        .progress-section {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
        }

        .progress-title {
            font-size: 20px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 30px;
            animation: fadeIn 0.8s ease forwards 0.3s;
            opacity: 0;
        }

        .progress-wrapper {
            width: 100%;
            max-width: 300px;
        }

        .progress-bar-container {
            width: 100%;
            height: 20px;
            background: #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
            position: relative;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #2d7a3e 0%, #4caf50 100%);
            border-radius: 10px;
            transition: width 2s cubic-bezier(0.65, 0, 0.35, 1);
            width: 0;
            position: relative;
            overflow: hidden;
        }

        .progress-bar-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            bottom: 0;
            right: 0;
            background: linear-gradient(
                90deg,
                transparent,
                rgba(255, 255, 255, 0.4),
                transparent
            );
            animation: shimmer 2s infinite;
        }

        @keyframes shimmer {
            0% {
                transform: translateX(-100%);
            }
            100% {
                transform: translateX(100%);
            }
        }

        .progress-stats {
            margin-top: 20px;
            animation: fadeIn 0.8s ease forwards 0.5s;
            opacity: 0;
        }

        .progress-percentage {
            font-size: 18px;
            font-weight: 700;
            color: #2d7a3e;
            margin-bottom: 8px;
        }

        .progress-details {
            font-size: 14px;
            color: #666;
        }

        /* Table Section */
        .table-container {
            overflow-x: auto;
            animation: fadeInUp 0.8s ease forwards 0.4s;
            opacity: 0;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table thead {
            background: #f8f9fa;
        }

        .data-table th {
            padding: 18px 20px;
            text-align: left;
            font-size: 14px;
            font-weight: 600;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        /* Ajuste para la columna de Acciones (que debe ser centrado y más estrecha) */
        .data-table th:last-child {
            text-align: center;
            width: 80px;
        }

        .data-table tbody tr {
            border-bottom: 1px solid #f0f0f0;
            transition: all 0.3s ease;
            animation: fadeInRight 0.6s ease forwards;
            opacity: 0;
        }

        .data-table tbody tr:nth-child(1) { animation-delay: 0.1s; }
        .data-table tbody tr:nth-child(2) { animation-delay: 0.2s; }
        .data-table tbody tr:nth-child(3) { animation-delay: 0.3s; }
        .data-table tbody tr:nth-child(4) { animation-delay: 0.4s; }

        @keyframes fadeInRight {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
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

        .data-table td:first-child {
            font-weight: 600;
        }
        
        /* Centrar contenido en la columna de Acciones */
        .data-table td:last-child {
            text-align: center;
        }

        /* Role Badge */
        .role-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            background: #e8f5e9;
            color: #2d7a3e;
        }

        /* Back Button (Existing styles, keep for completeness) */
        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 25px;
            background: #2d7a3e;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            margin-bottom: 30px;
            animation: slideInLeft 0.6s ease forwards;
        }

        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .back-button:hover {
            background: #1f5a2d;
            transform: translateX(-5px);
            box-shadow: 0 5px 15px rgba(45, 122, 62, 0.3);
        }

        /* Tooltip (Existing styles, keep for completeness) */
        .map-tooltip {
            position: absolute;
            background: rgba(0, 0, 0, 0.85);
            color: white;
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 1000;
            white-space: nowrap;
        }

        .map-tooltip.show {
            opacity: 1;
        }
        
        /* --- Dropdown / Menu de Acciones Styles --- */
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
            top: 100%; /* Posiciona debajo del botón */
            right: 0; /* Alinea a la derecha del botón */
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            min-width: 180px;
            z-index: 100;
            
            /* Smooth Effect Properties (Height, Opacity, Transform) */
            max-height: 0;
            opacity: 0;
            overflow: hidden;
            transform: translateY(10px);
            transition: max-height 0.3s ease-out, opacity 0.3s ease-out, transform 0.3s ease-out;
            pointer-events: none; /* Desactiva interacción cuando está oculto */
        }
        
        .dropdown-menu.show {
            max-height: 200px; /* Suficiente altura para mostrar todos los elementos */
            opacity: 1;
            transform: translateY(0);
            pointer-events: auto; /* Activa interacción cuando está visible */
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
            width: 16px; /* Para asegurar alineación consistente de íconos */
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .map-container-wrapper {
                grid-template-columns: 1fr;
            }

            .filters-section {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'components/sidebar_admin.php'; ?>
    
    <div class="container">
        

        <h1 class="page-title">Territorio</h1>

        <div class="filters-section">
            <div class="filter-group">
                <label for="estado">Estado</label>
                <select id="estado" name="estado">
                    <option value="sonora">Sonora</option>
                </select>
            </div>

            <div class="filter-group">
                <label for="seccion">Sección</label>
                <select id="seccion" name="seccion">
                    <option value="all">Todas las secciones</option>
                    <option value="2580">2580</option>
                    <option value="2581">2581</option>
                    <option value="2582">2582</option>
                    <option value="2583">2583</option>
                    <option value="2584">2584</option>
                    <option value="2585">2585</option>
                    <option value="2586">2586</option>
                    <option value="2587">2587</option>
                    <option value="2588">2588</option>
                    <option value="2589">2589</option>
                </select>
            </div>
        </div>

        <div class="content-area">
            <div class="map-container-wrapper">
                <div class="map-container" id="mapContainer">
                    <div class="map-controls">
                        <button class="map-control-btn" id="zoomIn" title="Acercar">
                            <i class="fas fa-plus"></i>
                        </button>
                        <button class="map-control-btn" id="zoomOut" title="Alejar">
                            <i class="fas fa-minus"></i>
                        </button>
                        <button class="map-control-btn" id="resetZoom" title="Restablecer">
                            <i class="fas fa-sync"></i>
                        </button>
                    </div>

                    <div class="map-wrapper" id="mapWrapper">
                        <object data="mx.svg" type="image/svg+xml" id="mx-map" style="width: 100%; height: auto;"></object>
                    </div>
                </div>

                <div class="progress-section">
                    <h3 class="progress-title">15 % de la meta</h3>
                    <div class="progress-wrapper">
                        <div class="progress-bar-container">
                            <div class="progress-bar-fill" id="progressBar" data-progress="15"></div>
                        </div>
                        <div class="progress-stats">
                            <div class="progress-percentage" id="progressText">15%</div>
                            <div class="progress-details">1,850 de 12,000 afiliados</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Rol</th>
                            <th>Sección</th>
                            <th>Estado</th>
                            <th>Acciones</th> 
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <?php 
                        // Simulación de datos con campos de contacto para acciones
                        $users = [
                            ['nombre' => 'Juan Pérez', 'rol' => 'Coordinador', 'seccion' => '2580', 'estado' => 'Sonora', 'telefono' => '526621234567', 'email' => 'juan.perez@example.com'],
                            ['nombre' => 'María Soto', 'rol' => 'Promotor', 'seccion' => '2580', 'estado' => 'Sonora', 'telefono' => '526629876543', 'email' => 'maria.soto@example.com'],
                            ['nombre' => 'Carlos Ruíz', 'rol' => 'Promo', 'seccion' => '2580', 'estado' => 'Sonora', 'telefono' => '526621112233', 'email' => 'carlos.ruiz@example.com'],
                            ['nombre' => 'Ana Moreno', 'rol' => 'Asigento', 'seccion' => '2580', 'estado' => 'Sonora', 'telefono' => '526625554444', 'email' => 'ana.moreno@example.com'],
                        ];
                        
                        foreach ($users as $user) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($user['nombre']) . "</td>";
                            echo "<td><span class='role-badge'>" . htmlspecialchars($user['rol']) . "</span></td>";
                            echo "<td>" . htmlspecialchars($user['seccion']) . "</td>";
                            echo "<td>" . htmlspecialchars($user['estado']) . "</td>";
                            // Columna de Acciones con Dropdown
                            echo "<td>";
                            echo "<div class='action-dropdown'>";
                            // Botón de tres puntos
                            echo "<button class='action-button' onclick='toggleDropdown(this)'>";
                            echo "<i class='fas fa-ellipsis-v'></i>";
                            echo "</button>";
                            // Menú del Dropdown
                            echo "<div class='dropdown-menu'>";
                            // Opciones del menú (con datos de ejemplo para WhatsApp/Email)
                            echo "<a href='https://wa.me/{$user['telefono']}' target='_blank' class='dropdown-item'>";
                            echo "<i class='fab fa-whatsapp'></i> Mandar WhatsApp";
                            echo "</a>";
                            echo "<a href='mailto:{$user['email']}' class='dropdown-item'>";
                            echo "<i class='fas fa-envelope'></i> Enviar Email";
                            echo "</a>";
                            echo "<a href='#' class='dropdown-item' onclick='alert(\"Eliminar a " . htmlspecialchars($user['nombre']) . "\")'>";
                            echo "<i class='fas fa-trash-alt'></i> Eliminar";
                            echo "</a>";
                            echo "<a href='#' class='dropdown-item' onclick='alert(\"Editar a " . htmlspecialchars($user['nombre']) . "\")'>";
                            echo "<i class='fas fa-edit'></i> Editar";
                            echo "</a>";
                            echo "</div>"; // .dropdown-menu
                            echo "</div>"; // .action-dropdown
                            echo "</td>";
                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <div class="map-tooltip" id="mapTooltip"></div>

    <script>
        // Variable global para guardar el estado seleccionado
        let selectedState = 'MXSON'; // Por defecto Sonora está seleccionado
        
        // Función para mostrar/ocultar el dropdown
        function toggleDropdown(button) {
            // Cierra todos los demás dropdowns abiertos
            document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                if (menu !== button.nextElementSibling) {
                    menu.classList.remove('show');
                }
            });

            // Muestra u oculta el dropdown asociado al botón clicado
            const menu = button.nextElementSibling;
            if (menu) {
                menu.classList.toggle('show');
            }
        }
        
        // Cierra el dropdown si se hace clic fuera de él
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.action-dropdown')) {
                document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                    menu.classList.remove('show');
                });
            }
        });
        
        // --- FUNCIONALIDAD DE MAPA ---
        // Zoom functionality
        let currentZoom = 1;
        const zoomStep = 0.2;
        const minZoom = 0.5;
        const maxZoom = 3;
        const mapWrapper = document.getElementById('mapWrapper');
        const mapContainer = document.getElementById('mapContainer');

        // Función para aplicar estilos según el estado
        function applyStateStyle(path, stateId) {
            if (stateId === selectedState) {
                // Estado seleccionado - azul
                path.style.fill = '#4169e1';
                path.style.stroke = '#2d5aa8';
                path.style.strokeWidth = '1.5';
                path.style.filter = 'drop-shadow(0 4px 6px rgba(65, 105, 225, 0.3))';
            } else {
                // Estado no seleccionado - gris
                path.style.fill = '#d3d3d3';
                path.style.stroke = '#ffffff';
                path.style.strokeWidth = '0.5';
                path.style.filter = 'none';
            }
        }

        // Wait for SVG to load
        const svgObject = document.getElementById('mx-map');
        
        svgObject.addEventListener('load', function() {
            const svgDoc = svgObject.contentDocument;
            const svgElement = svgDoc.querySelector('svg');
            
            // Apply styles to SVG paths
            const paths = svgDoc.querySelectorAll('path');
            paths.forEach(path => {
                const stateId = path.getAttribute('id');
                
                // Aplicar estilo inicial
                applyStateStyle(path, stateId);
                
                path.style.cursor = 'pointer';
                path.style.transition = 'all 0.3s ease';
                
                // Add hover effect
                path.addEventListener('mouseenter', function() {
                    if (stateId === selectedState) {
                        this.style.fill = '#5a7fe8';
                        this.style.filter = 'drop-shadow(0 6px 10px rgba(65, 105, 225, 0.5))';
                    } else {
                        this.style.fill = '#b8b8b8';
                    }
                    
                    // Show tooltip
                    const stateName = this.getAttribute('name');
                    if (stateName) {
                        const tooltip = document.getElementById('mapTooltip');
                        tooltip.textContent = stateName;
                        tooltip.classList.add('show');
                    }
                });
                
                path.addEventListener('mousemove', function(e) {
                    const tooltip = document.getElementById('mapTooltip');
                    tooltip.style.left = e.pageX + 15 + 'px';
                    tooltip.style.top = e.pageY + 15 + 'px';
                });
                
                path.addEventListener('mouseleave', function() {
                    // Volver al estilo según si está seleccionado o no
                    applyStateStyle(this, stateId);
                    
                    // Hide tooltip
                    const tooltip = document.getElementById('mapTooltip');
                    tooltip.classList.remove('show');
                });
                
                // Add click event - Seleccionar estado
                path.addEventListener('click', function() {
                    const stateName = this.getAttribute('name');
                    
                    // Actualizar el estado seleccionado
                    selectedState = stateId;
                    
                    // Reaplica estilos a todos los paths
                    paths.forEach(p => {
                        applyStateStyle(p, p.getAttribute('id'));
                    });
                    
                    // Mostrar mensaje solo para Sonora
                    if (stateId === 'MXSON') {
                        alert(`Has seleccionado: ${stateName}\n\nMostrando información detallada de Sonora...`);
                    }
                });
            });
        });

        // Zoom controls
        document.getElementById('zoomIn').addEventListener('click', () => {
            if (currentZoom < maxZoom) {
                currentZoom += zoomStep;
                applyZoom();
            }
        });

        document.getElementById('zoomOut').addEventListener('click', () => {
            if (currentZoom > minZoom) {
                currentZoom -= zoomStep;
                applyZoom();
            }
        });

        document.getElementById('resetZoom').addEventListener('click', () => {
            currentZoom = 1;
            translateX = 0;
            translateY = 0;
            mapWrapper.style.transform = `scale(1)`;
        });

        function applyZoom() {
            mapWrapper.style.transform = `scale(${currentZoom}) translate(${translateX / currentZoom}px, ${translateY / currentZoom}px)`;
        }

        // Pan functionality
        let isPanning = false;
        let startX, startY;
        let translateX = 0, translateY = 0;

        mapContainer.addEventListener('mousedown', (e) => {
            if (e.target.closest('.map-controls')) return;
            isPanning = true;
            startX = e.clientX - translateX;
            startY = e.clientY - translateY;
            mapContainer.style.cursor = 'grabbing';
        });

        document.addEventListener('mousemove', (e) => {
            if (!isPanning) return;
            translateX = e.clientX - startX;
            translateY = e.clientY - startY;
            mapWrapper.style.transform = `scale(${currentZoom}) translate(${translateX / currentZoom}px, ${translateY / currentZoom}px)`;
        });

        document.addEventListener('mouseup', () => {
            if (isPanning) {
                isPanning = false;
                mapContainer.style.cursor = 'grab';
            }
        });

// Mouse wheel zoom
        mapContainer.addEventListener('wheel', (e) => {
            e.preventDefault();
            
            if (e.deltaY < 0 && currentZoom < maxZoom) {
                currentZoom += zoomStep * 0.5;
            } else if (e.deltaY > 0 && currentZoom > minZoom) {
                currentZoom -= zoomStep * 0.5;
            }
            
            applyZoom();
        });

        // Animate progress bar on load
        window.addEventListener('load', () => {
            const progressBar = document.getElementById('progressBar');
            const progress = progressBar.dataset.progress;
            
            setTimeout(() => {
                progressBar.style.width = progress + '%';
            }, 500);
        });

        // Filter functionality
        const seccionSelect = document.getElementById('seccion');
        
        seccionSelect.addEventListener('change', (e) => {
            const selectedSeccion = e.target.value;
            filterTableBySeccion(selectedSeccion);
        });

        function filterTableBySeccion(seccion) {
            const rows = document.querySelectorAll('#tableBody tr');
            
            rows.forEach(row => {
                // La sección está en la celda 2 (índice 2)
                const rowSeccion = row.cells[2].textContent; 
                
                if (seccion === 'all' || rowSeccion === seccion) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>