<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Actividades - Super Admin</title>
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

        /* Se mantiene el estilo para el contenido principal 
           para que se posicione correctamente al lado del sidebar incluido.
        */
        .main-content {
            margin-left: 280px; /* Espacio para el sidebar (Ancho predeterminado) */
            padding: 40px;
            transition: margin-left 0.3s ease;
            min-height: 100vh;
        }

        /* Regla CLAVE: El selector de hermanos (~) mueve el main-content
           cuando el sidebar (asumido con clase 'sidebar') tiene la clase 'collapsed'.
           Esto replica la lógica del dashboard. */
        .sidebar.collapsed ~ .main-content {
            margin-left: 80px; /* Espacio cuando el sidebar está colapsado */
        }

        /* La clase .main-content.expanded ya no es necesaria con la lógica del selector de hermanos. */

        /* Header */
        .page-header {
            margin-bottom: 30px;
        }

        .page-header h1 {
            color: #1a1a2e;
            font-size: 32px;
            margin-bottom: 10px;
        }

        .page-header p {
            color: #666;
            font-size: 14px;
        }

        /* Action Bar */
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
            min-width: 250px;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 12px 45px 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .search-box input:focus {
            outline: none;
            border-color: #667eea;
        }

        .search-box i {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }

        .btn-primary {
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        /* Activities Grid */
        .activities-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .activity-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .activity-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .activity-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }

        .activity-title {
            font-size: 18px;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 5px;
        }

        .activity-date {
            font-size: 12px;
            color: #999;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .activity-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        /* Estilos ajustados para los nuevos estados y los existentes */
        .status-active { /* Anteriormente Activa, ahora En Proceso */
            background: #fff8e1;
            color: #ff8f00;
        }

        .status-pending { /* Por realizar */
            background: #e3f2fd;
            color: #1565c0;
        }

        .status-completed { /* Terminada */
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        /* El estilo .status-pending del código original lo renombré a .status-on-hold para distinguir */
        /* .status-on-hold { 
            background: #fff3e0;
            color: #ef6c00;
        } */


        .activity-description {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .activity-meta {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        
        /* Nuevo estilo para el item de impacto */
        .meta-item.impact {
            color: #c62828; /* Rojo para destacar el impacto */
            font-weight: 600;
        }
        .meta-item.impact i {
            color: #c62828;
        }


        .meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 13px;
            color: #666;
        }

        .meta-item i {
            color: #667eea;
        }

        .activity-actions {
            display: grid;
            /* Esto crea 2 columnas del mismo ancho. */
            grid-template-columns: repeat(2, 1fr); 
            gap: 10px;
            padding-top: 15px;
            border-top: 1px solid #f0f0f0;
        }
        
        .btn-action {
            width: 100%; /* Ocupa todo el espacio de su celda */
            padding: 10px;
            border: none;
            border-radius: 8px;
            font-size: 12px; /* Un poco más pequeño para que quepa bien */
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        
        .btn-edit {
            background: #e3f2fd;
            color: #1565c0;
        }
        .btn-edit:hover { background: #bbdefb; }
        
        /* Eliminar (Rojo) */
        .btn-delete {
            background: #ffebee;
            color: #c62828;
        }
        .btn-delete:hover { background: #ffcdd2; }
        
        /* Agregar/Verde (Para acciones positivas) */
        .btn-success {
            background: #e8f5e9;
            color: #2e7d32;
        }
        .btn-success:hover { background: #c8e6c9; }
        
        /* Enviar/Info (Para correos o envíos) */
        .btn-info {
            background: #ede7f6;
            color: #673ab7;
        }
        .btn-info:hover { background: #d1c4e9; }
        
        /* Zona/Advertencia (Naranja) */
        .btn-warning {
            background: #fff3e0;
            color: #ef6c00;
        }
        .btn-warning:hover { background: #ffe0b2; }
        
        /* General (Gris) */
        .btn-secondary {
            background: #f5f5f5;
            color: #616161;
        }
        .btn-secondary:hover { background: #e0e0e0; }

        .btn-edit {
            background: #e3f2fd;
            color: #1565c0;
        }

        .btn-edit:hover {
            background: #bbdefb;
        }

        .btn-delete {
            background: #ffebee;
            color: #c62828;
        }

        .btn-delete:hover {
            background: #ffcdd2;
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
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 15px;
            padding: 30px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            margin-bottom: 25px;
        }

        .modal-header h2 {
            color: #1a1a2e;
            font-size: 24px;
            margin-bottom: 5px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        /* Nuevo estilo para hacer dos inputs en una sola línea en el modal */
        .form-row {
            display: flex;
            gap: 20px;
        }
        
        .form-row .form-group {
            flex: 1; /* Distribuye el espacio equitativamente */
        }

        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 25px;
        }

        .btn-cancel {
            flex: 1;
            padding: 12px;
            background: #f5f5f5;
            color: #666;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-cancel:hover {
            background: #e0e0e0;
        }

        .btn-submit {
            flex: 2;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        /* Responsive */
        @media (max-width: 768px) {
            
            /* En móviles, el main-content no debe tener margin para ocupar todo el ancho */
            .main-content { 
                margin-left: 0;
                padding: 20px; /* Ajustar padding para móviles para mejor vista */
            }
            
            /* Es clave desactivar el desplazamiento del sidebar en móviles. */
            .sidebar.collapsed ~ .main-content {
                margin-left: 0;
            }

            .activities-grid {
                grid-template-columns: 1fr;
            }

            .action-bar {
                flex-direction: column;
                align-items: stretch;
            }
            
            /* En móviles, los campos de fecha/impacto deben ir apilados */
            .form-row {
                flex-direction: column;
                gap: 0;
            }
        }
    </style>
</head>
<body>
    <?php include 'components/sidebar_admin.php'; ?>

    <main class="main-content" id="mainContent">
        <div class="page-header">
            <h1>Gestión de Actividades</h1>
            <p>Crea y asigna actividades a coordinadores</p>
        </div>

        <div class="action-bar">
            <div class="search-box">
                <input type="text" placeholder="Buscar actividades..." id="searchInput">
                <i class="fas fa-search"></i>
            </div>
            <button class="btn-primary" onclick="openModal()">
                <i class="fas fa-plus"></i> Nueva Actividad
            </button>
        </div>

        <div class="activities-grid" id="activitiesGrid">
            <div class="activity-card">
                <div class="activity-header">
                    <div>
                        <div class="activity-title">Jornada de Reforestación</div>
                        <div class="activity-date">
                            <i class="far fa-calendar"></i> 15 Dic 2024
                        </div>
                    </div>
                    <span class="activity-status status-active">En Proceso</span>
                </div>
                <p class="activity-description">
                    Plantar 500 árboles en la zona norte del municipio con apoyo de la comunidad.
                </p>
                <div class="activity-meta">
                    <div class="meta-item">
                        <i class="fas fa-user-tie"></i> Coord. Norte
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-users"></i> 25 participantes
                    </div>
                    <div class="meta-item impact">
                        <i class="fas fa-bullseye"></i> Impacto: 500 personas
                    </div>
                </div>
                 <div class="activity-actions">
                    <button class="btn-action btn-success">
                        <i class="fas fa-plus-circle"></i> Agregar
                    </button>
                
                    <button class="btn-action btn-edit">
                        <i class="fas fa-edit"></i> Editar
                    </button>
                
                    <button class="btn-action btn-info">
                        <i class="fas fa-paper-plane"></i> Enviar
                    </button>
                
                    <button class="btn-action btn-secondary">
                        <i class="fas fa-envelope-open-text"></i> Mailing
                    </button>
                
                    <button class="btn-action btn-warning">
                        <i class="fas fa-map-marker-alt"></i> Zona
                    </button>
                
                    <button class="btn-action btn-delete">
                        <i class="fas fa-trash"></i> Eliminar
                    </button>
                </div>
            </div>

            <div class="activity-card">
                <div class="activity-header">
                    <div>
                        <div class="activity-title">Campaña de Reciclaje</div>
                        <div class="activity-date">
                            <i class="far fa-calendar"></i> 20 Dic 2024
                        </div>
                    </div>
                    <span class="activity-status status-pending">Por Realizar</span>
                </div>
                <p class="activity-description">
                    Recolección y clasificación de materiales reciclables en escuelas del sector.
                </p>
                <div class="activity-meta">
                    <div class="meta-item">
                        <i class="fas fa-user-tie"></i> Coord. Sur
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-users"></i> 15 participantes
                    </div>
                    <div class="meta-item impact">
                        <i class="fas fa-bullseye"></i> Impacto: 200 personas
                    </div>
                </div>
                 <div class="activity-actions">
                    <button class="btn-action btn-success">
                        <i class="fas fa-plus-circle"></i> Agregar
                    </button>
                
                    <button class="btn-action btn-edit">
                        <i class="fas fa-edit"></i> Editar
                    </button>
                
                    <button class="btn-action btn-info">
                        <i class="fas fa-paper-plane"></i> Enviar
                    </button>
                
                    <button class="btn-action btn-secondary">
                        <i class="fas fa-envelope-open-text"></i> Mailing
                    </button>
                
                    <button class="btn-action btn-warning">
                        <i class="fas fa-map-marker-alt"></i> Zona
                    </button>
                
                    <button class="btn-action btn-delete">
                        <i class="fas fa-trash"></i> Eliminar
                    </button>
                </div>
            </div>

            <div class="activity-card">
                <div class="activity-header">
                    <div>
                        <div class="activity-title">Taller de Compostaje</div>
                        <div class="activity-date">
                            <i class="far fa-calendar"></i> 10 Dic 2024
                        </div>
                    </div>
                    <span class="activity-status status-completed">Terminada</span>
                </div>
                <p class="activity-description">
                    Capacitación sobre técnicas de compostaje doméstico para la comunidad.
                </p>
                <div class="activity-meta">
                    <div class="meta-item">
                        <i class="fas fa-user-tie"></i> Coord. Este
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-users"></i> 30 participantes
                    </div>
                    <div class="meta-item impact">
                        <i class="fas fa-bullseye"></i> Impacto: 90 personas
                    </div>
                </div>
                <div class="activity-actions">
                    <button class="btn-action btn-success">
                        <i class="fas fa-plus-circle"></i> Agregar
                    </button>
                
                    <button class="btn-action btn-edit">
                        <i class="fas fa-edit"></i> Editar
                    </button>
                
                    <button class="btn-action btn-info">
                        <i class="fas fa-paper-plane"></i> Enviar
                    </button>
                
                    <button class="btn-action btn-secondary">
                        <i class="fas fa-envelope-open-text"></i> Mailing
                    </button>
                
                    <button class="btn-action btn-warning">
                        <i class="fas fa-map-marker-alt"></i> Zona
                    </button>
                
                    <button class="btn-action btn-delete">
                        <i class="fas fa-trash"></i> Eliminar
                    </button>
                </div>
            </div>
        </div>
    </main>

    <div class="modal" id="activityModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Nueva Actividad</h2>
                <p style="color: #666; font-size: 14px;">Completa los datos de la actividad</p>
            </div>
            <form id="activityForm">
                <div class="form-group">
                    <label>Título de la Actividad</label>
                    <input type="text" placeholder="Ej: Jornada de Limpieza" required>
                </div>
                <div class="form-group">
                    <label>Descripción</label>
                    <textarea placeholder="Describe la actividad..." required></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Fecha</label>
                        <input type="date" required>
                    </div>
                    <div class="form-group">
                        <label>Estatus</label>
                        <select required>
                            <option value="pending">Por realizar</option>
                            <option value="active">En proceso</option>
                            <option value="completed">Terminada</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Asignar a Coordinador</label>
                    <select required>
                        <option value="">Selecciona un coordinador</option>
                        <option value="1">Coordinador Norte</option>
                        <option value="2">Coordinador Sur</option>
                        <option value="3">Coordinador Este</option>
                        <option value="4">Coordinador Oeste</option>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Participantes Esperados</label>
                        <input type="number" placeholder="0" required>
                    </div>
                    <div class="form-group">
                        <label>Impacto (# personas)</label>
                        <input type="number" placeholder="0" required>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeModal()">Cancelar</button>
                    <button type="submit" class="btn-submit">Crear Actividad</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Los selectores de sidebar y mainContent se mantienen por si el sidebar_admin.php
        // inyecta el script para manejar el colapso.

        const menuLinks = document.querySelectorAll('.menu-link');
        menuLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                if (this.getAttribute('href') === '#') {
                    e.preventDefault();
                }
                menuLinks.forEach(l => l.classList.remove('active'));
                this.classList.add('active');
                // Se elimina la lógica de sidebar.classList.remove('mobile-open')
            });
        });

        function logout() {
            if (confirm('¿Estás seguro de que deseas cerrar sesión?')) {
                window.location.href = 'index.php';
            }
        }

        function openModal() {
            document.getElementById('activityModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('activityModal').classList.remove('active');
        }

        document.getElementById('activityModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        document.getElementById('activityForm').addEventListener('submit', function(e) {
            e.preventDefault();
            alert('Actividad creada exitosamente');
            closeModal();
            this.reset();
        });

        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const cards = document.querySelectorAll('.activity-card');
            
            cards.forEach(card => {
                const title = card.querySelector('.activity-title').textContent.toLowerCase();
                const description = card.querySelector('.activity-description').textContent.toLowerCase();
                
                if (title.includes(searchTerm) || description.includes(searchTerm)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>