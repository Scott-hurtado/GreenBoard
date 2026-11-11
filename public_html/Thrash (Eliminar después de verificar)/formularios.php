<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formularios - Proyecto Verde</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Roboto', 'Arial', sans-serif; /* Google Forms usa fuentes sans-serif limpias */
            background: #f5f5f5;
            overflow-x: hidden;
        }

        /* --- ESTILOS GENERALES (MANTENIDOS) --- */
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
        }

        /* Action Bar */
        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
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
            border-color: #2d7a3e;
            box-shadow: 0 0 0 3px rgba(45, 122, 62, 0.1);
        }

        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }

        .btn-primary {
            background: linear-gradient(135deg, #2d7a3e 0%, #1f5a2d 100%);
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
            box-shadow: 0 4px 15px rgba(45, 122, 62, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(45, 122, 62, 0.4);
        }

        /* Cards Grid */
        .forms-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 25px;
        }

        .form-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }

        .form-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(45, 122, 62, 0.15);
        }

        .form-card-header {
            padding: 25px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-bottom: 3px solid #2d7a3e;
        }

        .form-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #2d7a3e 0%, #1f5a2d 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 28px;
            margin-bottom: 15px;
        }

        .form-card-title {
            font-size: 20px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 8px;
        }

        .form-card-meta {
            display: flex;
            gap: 15px;
            font-size: 13px;
            color: #666;
            flex-wrap: wrap;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .meta-item i {
            color: #2d7a3e;
        }

        .form-card-body {
            padding: 25px;
        }

        .form-description {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .form-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 20px;
            padding-top: 20px;
            border-top: 1px solid #f0f0f0;
        }

        .stat-item {
            text-align: center;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: #2d7a3e;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
        }

        .form-status {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .status-activo { background: #e8f5e9; color: #2d7a3e; }
        .status-borrador { background: #fff3e0; color: #ef6c00; }
        .status-cerrado { background: #eeeeee; color: #616161; }

        .form-actions {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }

        .btn-action {
            padding: 10px 15px;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .btn-view { background: #e3f2fd; color: #1565c0; }
        .btn-share { background: #f3e5f5; color: #7b1fa2; }
        .btn-download { background: #fff3e0; color: #ef6c00; }
        .btn-edit { background: #e8f5e9; color: #2d7a3e; }
        .btn-results { background: #fce4ec; color: #c2185b; grid-column: span 2; }
        .btn-delete { background: #ffebee; color: #c62828; grid-column: span 2; }

        /* --- GOOGLE FORMS CLONE STYLES (NUEVO) --- */
        
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

        .modal.show { display: flex; }

        .modal-content.google-style {
            background: #f0f2f5; /* Fondo gris claro tipo Google */
            width: 100%;
            max-width: 900px;
            height: 95vh;
            border-radius: 15px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .g-header {
            background: white;
            padding: 15px 25px;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 10;
        }

        .g-header-left { display: flex; align-items: center; gap: 15px; }
        .g-back-btn { background: none; border: none; font-size: 20px; cursor: pointer; color: #5f6368; }
        .g-title { font-size: 18px; color: #202124; }
        
        .g-body {
            flex: 1;
            overflow-y: auto;
            padding: 25px;
            position: relative;
            display: flex;
            justify-content: center;
        }

        .g-workspace {
            width: 100%;
            max-width: 770px;
            padding-bottom: 60px;
            position: relative;
        }

        /* Form Header Card */
        .g-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.12);
            margin-bottom: 15px;
            border: 1px solid transparent;
            position: relative;
            transition: all 0.2s ease;
        }

        .g-card-top-border {
            height: 10px;
            background: #2d7a3e;
            border-radius: 8px 8px 0 0;
        }

        .g-card-content {
            padding: 24px;
        }

        /* Focus state mimic */
        .g-card.active {
            box-shadow: 0 4px 5px 0 rgba(0,0,0,0.14), 0 1px 10px 0 rgba(0,0,0,0.12), 0 2px 4px -1px rgba(0,0,0,0.2);
            border-left: 6px solid #4285f4; /* Blue bar indicator like Google */
            border-left-color: #2d7a3e; /* Changed to green */
        }

        /* Inputs within cards */
        .g-input-title {
            width: 100%;
            font-size: 32px;
            border: none;
            border-bottom: 1px solid #e0e0e0;
            padding: 8px 0;
            margin-bottom: 10px;
            outline: none;
            font-family: inherit;
        }

        .g-input-title:focus { border-bottom: 2px solid #2d7a3e; }

        .g-input-desc {
            width: 100%;
            font-size: 14px;
            border: none;
            border-bottom: 1px solid #e0e0e0;
            padding: 4px 0;
            outline: none;
            font-family: inherit;
            color: #666;
            resize: none;
        }
        .g-input-desc:focus { border-bottom: 2px solid #2d7a3e; }

        /* Question Card Specifics */
        .g-question-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            align-items: flex-start;
        }

        .g-question-input {
            flex: 1;
            padding: 15px;
            background: #f8f9fa;
            border-bottom: 1px solid #808080;
            border-top: none; border-left: none; border-right: none;
            font-size: 16px;
            transition: background 0.2s;
        }
        .g-question-input:focus { background: #e8f5e9; border-bottom: 2px solid #2d7a3e; outline: none; }

        .g-select-type {
            width: 220px;
            padding: 10px 35px 10px 15px;
            border: 1px solid #dadce0;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            appearance: none;
            background: url("data:image/svg+xml;charset=US-ASCII,%3Csvg%20width%3D%2210%22%20height%3D%225%22%20viewBox%3D%220%200%2010%205%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%3E%3Cpath%20d%3D%22M0%200l5%205%205-5z%22%20fill%3D%22%23666%22%2F%3E%3C%2Fsvg%3E") no-repeat right 15px center;
            background-color: white;
        }

        /* Answer Area Preview */
        .g-answer-area { margin-bottom: 25px; }

        .g-option-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .g-radio-circle {
            width: 20px; height: 20px;
            border: 2px solid #dadce0;
            border-radius: 50%;
        }

        .g-checkbox-sq {
            width: 20px; height: 20px;
            border: 2px solid #dadce0;
            border-radius: 2px;
        }

        .g-option-input {
            border: none;
            font-size: 14px;
            width: 80%;
            padding: 5px;
        }
        .g-option-input:focus { border-bottom: 1px solid #2d7a3e; outline: none; }
        .g-option-input:hover { border-bottom: 1px solid #e0e0e0; }

        .g-add-option {
            color: #666;
            cursor: pointer;
            font-size: 14px;
            margin-left: 30px;
            display: inline-block;
            margin-top: 5px;
        }
        .g-add-option:hover { color: #2d7a3e; }

        .g-short-text-preview {
            border-bottom: 1px dotted #dadce0;
            width: 50%;
            padding: 8px 0;
            color: #70757a;
            font-size: 14px;
        }

        /* Footer of Card */
        .g-card-footer {
            border-top: 1px solid #dadce0;
            padding-top: 15px;
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 15px;
        }

        .g-icon-btn {
            background: none;
            border: none;
            cursor: pointer;
            color: #5f6368;
            font-size: 18px;
            width: 35px; height: 35px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            transition: background 0.2s;
        }
        .g-icon-btn:hover { background: #f1f3f4; color: #202124; }
        
        .g-divider-vert { width: 1px; height: 25px; background: #dadce0; margin: 0 5px; }

        .g-toggle {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            color: #202124;
            cursor: pointer;
        }

        /* Floating Sidebar */
        .g-sidebar {
            position: absolute;
            right: -60px;
            top: 0;
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.24);
            display: flex;
            flex-direction: column;
            padding: 5px 0;
            transition: top 0.2s;
            width: 48px;
        }

        .g-sidebar-btn {
            width: 48px; height: 48px;
            display: flex; align-items: center; justify-content: center;
            background: none; border: none;
            cursor: pointer;
            color: #5f6368;
            font-size: 20px;
            transition: color 0.2s;
        }
        .g-sidebar-btn:hover { color: #202124; background: #f8f9fa; }
        .g-sidebar-btn.add-q { color: #2d7a3e; font-size: 26px; }

        @media (max-width: 900px) {
            .g-sidebar {
                position: fixed;
                bottom: 20px;
                right: 20px;
                top: auto;
                flex-direction: row;
                width: auto;
                border-radius: 30px;
                padding: 0 10px;
                z-index: 100;
            }
            .g-workspace { max-width: 100%; padding: 0 15px 80px 15px; }
            .g-sidebar-btn { width: 40px; height: 50px; }
        }

        .g-footer {
            background: white;
            padding: 15px 25px;
            border-top: 1px solid #ddd;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

    </style>
</head>
<body>
    <?php include 'components/sidebar_admin.php'; ?>
    
    <div class="container">
        <h1 class="page-title">
            <i class="fas fa-file-invoice"></i>
            Formularios
        </h1>
        <p class="page-subtitle">Crea y gestiona formularios personalizados para recolectar información</p>

        <div class="action-bar">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Buscar formularios...">
            </div>
            <button class="btn-primary" onclick="openCreateModal()">
                <i class="fas fa-plus"></i>
                Nuevo Formulario
            </button>
        </div>

        <div class="forms-grid" id="formsGrid">
            <div class="form-card">
                <div class="form-card-header">
                    <div class="form-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <h3 class="form-card-title">Registro de Nuevos Miembros</h3>
                    <div class="form-card-meta">
                        <div class="meta-item">
                            <i class="far fa-calendar"></i>
                            <span>Creado: 01/Dic/2024</span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-user"></i>
                            <span>Super Admin</span>
                        </div>
                    </div>
                </div>
                <div class="form-card-body">
                    <span class="form-status status-activo">Activo</span>
                    <p class="form-description">
                        Formulario para registrar nuevos miembros de la red.
                    </p>
                    <div class="form-stats">
                        <div class="stat-item">
                            <div class="stat-value">247</div>
                            <div class="stat-label">Respuestas</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value">12</div>
                            <div class="stat-label">Campos</div>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button class="btn-action btn-view"><i class="fas fa-eye"></i> Ver</button>
                        <button class="btn-action btn-edit" onclick="editForm(1)"><i class="fas fa-edit"></i> Editar</button>
                        <button class="btn-action btn-results"><i class="fas fa-chart-bar"></i> Resultados</button>
                        <button class="btn-action btn-delete"><i class="fas fa-trash"></i> Eliminar</button>
                    </div>
                </div>
            </div>
             <div class="form-card">
                <div class="form-card-header">
                    <div class="form-icon">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <h3 class="form-card-title">Encuesta de Satisfacción</h3>
                    <div class="form-card-meta">
                        <div class="meta-item">
                            <i class="far fa-calendar"></i>
                            <span>Creado: 28/Nov/2024</span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-user"></i>
                            <span>Coord. Norte</span>
                        </div>
                    </div>
                </div>
                <div class="form-card-body">
                    <span class="form-status status-activo">Activo</span>
                    <p class="form-description">
                        Evaluación del nivel de satisfacción de los miembros.
                    </p>
                    <div class="form-stats">
                        <div class="stat-item">
                            <div class="stat-value">189</div>
                            <div class="stat-label">Respuestas</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value">8</div>
                            <div class="stat-label">Campos</div>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button class="btn-action btn-view"><i class="fas fa-eye"></i> Ver</button>
                        <button class="btn-action btn-edit" onclick="editForm(2)"><i class="fas fa-edit"></i> Editar</button>
                        <button class="btn-action btn-results"><i class="fas fa-chart-bar"></i> Resultados</button>
                        <button class="btn-action btn-delete"><i class="fas fa-trash"></i> Eliminar</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal" id="formModal">
        <div class="modal-content google-style">
            <div class="g-header">
                <div class="g-header-left">
                    <button class="g-back-btn" onclick="closeModal()" title="Cerrar y Volver">
                        <i class="fas fa-arrow-left"></i>
                    </button>
                    <div class="g-title">
                        <span id="modalModeTitle">Formulario sin título</span>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom:0; width: 150px;">
                    <select id="formStatus" style="padding: 8px; border-radius: 4px; border: 1px solid #ddd;">
                        <option value="activo">Activo</option>
                        <option value="borrador">Borrador</option>
                        <option value="cerrado">Cerrado</option>
                    </select>
                </div>
            </div>

            <div class="g-body">
                <div class="g-workspace">
                    
                    <div class="g-sidebar" id="floatingSidebar">
                        <button class="g-sidebar-btn add-q" onclick="addNewQuestion()" title="Añadir pregunta">
                            <i class="fas fa-plus-circle"></i>
                        </button>
                        <button class="g-sidebar-btn" title="Importar preguntas">
                            <i class="fas fa-file-import"></i>
                        </button>
                        <button class="g-sidebar-btn" title="Añadir título y descripción">
                            <i class="fas fa-heading"></i>
                        </button>
                        <button class="g-sidebar-btn" title="Añadir imagen">
                            <i class="far fa-image"></i>
                        </button>
                        <button class="g-sidebar-btn" title="Añadir vídeo">
                            <i class="fab fa-youtube"></i>
                        </button>
                        <button class="g-sidebar-btn" title="Añadir sección">
                            <i class="fas fa-grip-lines"></i>
                        </button>
                    </div>

                    <div class="g-card active" id="headerCard" onclick="activateCard('headerCard')">
                        <div class="g-card-top-border"></div>
                        <div class="g-card-content">
                            <input type="text" id="formTitleInput" class="g-input-title" placeholder="Formulario sin título" value="Formulario sin título">
                            <textarea id="formDescInput" class="g-input-desc" placeholder="Descripción del formulario" rows="1"></textarea>
                        </div>
                    </div>

                    <div id="questionsContainer">
                        </div>

                </div>
            </div>

            <div class="g-footer">
                <button class="btn-secondary" onclick="closeModal()">Cancelar</button>
                <button class="btn-primary" onclick="saveForm()">
                    <i class="fas fa-save"></i>
                    Guardar
                </button>
            </div>
        </div>
    </div>

    <div class="modal" id="shareModal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header" style="background: linear-gradient(135deg, #2d7a3e 0%, #1f5a2d 100%); padding: 20px; color: white; border-radius: 15px 15px 0 0; display:flex; justify-content:space-between;">
                <h2>Compartir</h2>
                <button class="close-modal" onclick="closeShareModal()" style="background:none; border:none; color:white; font-size:20px; cursor:pointer;"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body" style="padding: 20px;">
               <p>Opciones de compartir aquí...</p>
            </div>
        </div>
    </div>

    <script>
        // State
        let questions = [];
        let currentFormId = null;

        // --- CORE FUNCTIONS FOR GOOGLE-LIKE BUILDER ---

        function openCreateModal() {
            document.getElementById('formModal').classList.add('show');
            resetBuilder();
            // Start with one empty question
            addNewQuestion();
        }

        function editForm(id) {
            currentFormId = id;
            document.getElementById('formModal').classList.add('show');
            resetBuilder();
            // Simulate loading data
            document.getElementById('formTitleInput').value = "Formulario Cargado " + id;
            document.getElementById('formDescInput').value = "Descripción cargada...";
            
            // Example existing questions
            addQuestionToDOM({id: 1, title: "Nombre Completo", type: "text", required: true});
            addQuestionToDOM({id: 2, title: "Género", type: "radio", options: ["Masculino", "Femenino"], required: false});
        }

        function resetBuilder() {
            document.getElementById('questionsContainer').innerHTML = '';
            document.getElementById('formTitleInput').value = 'Formulario sin título';
            document.getElementById('formDescInput').value = '';
            questions = [];
        }

        function closeModal() {
            document.getElementById('formModal').classList.remove('show');
        }

        // --- QUESTION CARD LOGIC ---

        function addNewQuestion() {
            const newQ = {
                id: Date.now(),
                title: "",
                type: "radio", // default google forms
                options: ["Opción 1"],
                required: false
            };
            addQuestionToDOM(newQ);
        }

        function addQuestionToDOM(data) {
            const container = document.getElementById('questionsContainer');
            const qId = data.id;
            
            const cardDiv = document.createElement('div');
            cardDiv.className = 'g-card question-card';
            cardDiv.id = `q_${qId}`;
            cardDiv.onclick = (e) => {
                // Prevent triggering when clicking inputs directly
                if(e.target.tagName !== 'INPUT' && e.target.tagName !== 'SELECT' && e.target.tagName !== 'TEXTAREA') {
                   activateCard(`q_${qId}`);
                }
            };

            // HTML template for a question card
            cardDiv.innerHTML = `
                <div class="g-card-content">
                    <div class="g-question-row">
                        <input type="text" class="g-question-input" value="${data.title}" placeholder="Pregunta" oninput="updateFloatingSidebar(this)">
                        <select class="g-select-type" onchange="changeQuestionType(${qId}, this.value)">
                            <option value="text" ${data.type === 'text' ? 'selected' : ''}>Respuesta corta</option>
                            <option value="paragraph" ${data.type === 'paragraph' ? 'selected' : ''}>Párrafo</option>
                            <option value="radio" ${data.type === 'radio' ? 'selected' : ''}>Varias opciones</option>
                            <option value="checkbox" ${data.type === 'checkbox' ? 'selected' : ''}>Casillas</option>
                            <option value="dropdown" ${data.type === 'dropdown' ? 'selected' : ''}>Desplegable</option>
                            <option value="date" ${data.type === 'date' ? 'selected' : ''}>Fecha</option>
                        </select>
                    </div>

                    <div class="g-answer-area" id="answers_${qId}">
                        </div>

                    <div class="g-card-footer">
                        <button class="g-icon-btn" title="Duplicar" onclick="duplicateQuestion(${qId})"><i class="far fa-copy"></i></button>
                        <button class="g-icon-btn" title="Eliminar" onclick="deleteQuestion(${qId})"><i class="far fa-trash-alt"></i></button>
                        <div class="g-divider-vert"></div>
                        <label class="g-toggle">
                            Obligatorio
                            <input type="checkbox" ${data.required ? 'checked' : ''} style="margin-left:8px;">
                        </label>
                        <button class="g-icon-btn" title="Más opciones"><i class="fas fa-ellipsis-v"></i></button>
                    </div>
                </div>
            `;

            container.appendChild(cardDiv);
            
            // Render the specific answer type interface
            renderAnswerArea(qId, data.type, data.options);
            
            // Activate newly created card
            activateCard(`q_${qId}`);
            
            // Scroll to bottom
            cardDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        function renderAnswerArea(qId, type, existingOptions = null) {
            const area = document.getElementById(`answers_${qId}`);
            let html = '';

            if (type === 'text') {
                html = `<div class="g-short-text-preview">Texto de respuesta corta</div>`;
            } else if (type === 'paragraph') {
                html = `<div class="g-short-text-preview" style="width:80%">Texto de respuesta larga</div>`;
            } else if (type === 'date') {
                html = `<div style="color:#666;"><i class="far fa-calendar-alt"></i> Mes, día, año</div>`;
            } else if (['radio', 'checkbox', 'dropdown'].includes(type)) {
                const options = existingOptions || ["Opción 1"];
                const iconClass = type === 'radio' ? 'g-radio-circle' : (type === 'checkbox' ? 'g-checkbox-sq' : 'fas fa-caret-down'); // simplified for dropdown
                
                // Render Options
                options.forEach((opt, idx) => {
                    html += `
                        <div class="g-option-row">
                            <div class="${type === 'dropdown' ? '' : iconClass}" style="${type==='dropdown'?'width:20px;text-align:center;color:#666':''}">
                                ${type==='dropdown' ? (idx+1)+'.' : ''}
                            </div>
                            <input type="text" class="g-option-input" value="${opt}" placeholder="Opción ${idx + 1}">
                            <button class="g-icon-btn" onclick="removeOption(this)" style="width:24px;height:24px;margin-left:auto;"><i class="fas fa-times"></i></button>
                        </div>
                    `;
                });

                // "Add Option" link
                html += `
                    <div class="g-option-row">
                         <div class="${type === 'dropdown' ? '' : iconClass}" style="opacity:0.5; ${type==='dropdown'?'width:20px;text-align:center;':''}">
                            ${type==='dropdown' ? (options.length+1)+'.' : ''}
                         </div>
                        <div class="g-add-option" onclick="addOptionToQuestion(${qId}, '${type}')">Añadir opción</div>
                    </div>
                `;
            }

            area.innerHTML = html;
        }

        function changeQuestionType(qId, newType) {
            // Get current options if applicable
            let currentOptions = [];
            const inputs = document.querySelectorAll(`#answers_${qId} .g-option-input`);
            if (inputs.length > 0) {
                inputs.forEach(i => currentOptions.push(i.value));
            } else {
                currentOptions = ["Opción 1"];
            }
            
            renderAnswerArea(qId, newType, currentOptions);
        }

        function addOptionToQuestion(qId, type) {
            // Find the container
            const area = document.getElementById(`answers_${qId}`);
            // Get current options to rebuild or just append
            const inputs = area.querySelectorAll('.g-option-input');
            const newIndex = inputs.length + 1;
            
            const newRow = document.createElement('div');
            newRow.className = 'g-option-row';
            
            const iconClass = type === 'radio' ? 'g-radio-circle' : (type === 'checkbox' ? 'g-checkbox-sq' : '');
            const iconHtml = type === 'dropdown' 
                ? `<div style="width:20px;text-align:center;color:#666">${newIndex}.</div>` 
                : `<div class="${iconClass}"></div>`;

            newRow.innerHTML = `
                ${iconHtml}
                <input type="text" class="g-option-input" value="Opción ${newIndex}" placeholder="Opción ${newIndex}">
                <button class="g-icon-btn" onclick="removeOption(this)" style="width:24px;height:24px;margin-left:auto;"><i class="fas fa-times"></i></button>
            `;
            
            // Insert before the "Add Option" button (last child)
            area.insertBefore(newRow, area.lastElementChild);
        }

        function removeOption(btn) {
            btn.parentElement.remove();
        }

        function deleteQuestion(qId) {
            const card = document.getElementById(`q_${qId}`);
            if(document.querySelectorAll('.question-card').length > 1) {
                card.remove();
                // Move sidebar to the last remaining card
                const lastCard = document.querySelector('.question-card:last-child');
                if(lastCard) activateCard(lastCard.id);
            } else {
                alert("No puedes eliminar la última pregunta.");
            }
        }

        function duplicateQuestion(qId) {
            // Simply grabbing values and calling addQuestionToDOM
            const originalCard = document.getElementById(`q_${qId}`);
            const title = originalCard.querySelector('.g-question-input').value;
            const type = originalCard.querySelector('.g-select-type').value;
            
            let options = [];
            originalCard.querySelectorAll('.g-option-input').forEach(i => options.push(i.value));
            
            addQuestionToDOM({
                id: Date.now(),
                title: title,
                type: type,
                options: options.length ? options : null,
                required: false
            });
        }

        // --- UX INTERACTIONS ---

        function activateCard(cardId) {
            // Remove active class from all
            document.querySelectorAll('.g-card').forEach(c => c.classList.remove('active'));
            // Add to current
            const current = document.getElementById(cardId);
            current.classList.add('active');
            
            // Move Sidebar
            const sidebar = document.getElementById('floatingSidebar');
            // If screen is wide, move sidebar next to card
            if (window.innerWidth > 900) {
                const rect = current.getBoundingClientRect();
                const containerRect = document.querySelector('.g-workspace').getBoundingClientRect();
                const offsetTop = current.offsetTop;
                
                sidebar.style.top = `${offsetTop}px`;
            }
        }

        function updateFloatingSidebar(input) {
            // Just ensure sidebar stays aligned if content grows
            const card = input.closest('.g-card');
            if(window.innerWidth > 900 && card.classList.contains('active')) {
                const sidebar = document.getElementById('floatingSidebar');
                sidebar.style.top = `${card.offsetTop}px`;
            }
        }

        function saveForm() {
            // Logic to scrape DOM and build object
            const formTitle = document.getElementById('formTitleInput').value;
            const formDesc = document.getElementById('formDescInput').value;
            const status = document.getElementById('formStatus').value;
            
            const questionCards = document.querySelectorAll('.question-card');
            const questionsData = [];

            questionCards.forEach(card => {
                const title = card.querySelector('.g-question-input').value;
                const type = card.querySelector('.g-select-type').value;
                const isRequired = card.querySelector('.g-toggle input').checked;
                
                let options = [];
                if(['radio', 'checkbox', 'dropdown'].includes(type)) {
                    card.querySelectorAll('.g-option-input').forEach(opt => {
                        options.push(opt.value);
                    });
                }

                questionsData.push({
                    title, type, required: isRequired, options
                });
            });

            console.log("Saving Form:", { title: formTitle, desc: formDesc, status, questions: questionsData });
            alert("Formulario guardado (Simulado). Revisa la consola para ver los datos JSON.");
            closeModal();
        }

        // Initialize Search (Old functionality)
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const cards = document.querySelectorAll('.form-card');
            cards.forEach(card => {
                const title = card.querySelector('.form-card-title').textContent.toLowerCase();
                const description = card.querySelector('.form-description').textContent.toLowerCase();
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