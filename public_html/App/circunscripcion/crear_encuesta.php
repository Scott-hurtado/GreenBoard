<?php
// app/admin/crear_encuesta.php

if (file_exists(__DIR__ . '/../../includes/bootstrap.php')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
} else {
    session_start();
}

$rol = $_SESSION['user_role'] ?? '';
$permitidos = ['admin', 'administrador', 'superadmin', 'super admin'];

if (!in_array($rol, $permitidos)) {
    header('Location: /public/index.php'); 
    exit();
}

// Conexión a la base de datos
try {
    $dbConfigPath = __DIR__ . '/../../includes/database.php';
    if (file_exists($dbConfigPath)) {
        $dbConfig = require $dbConfigPath;
    } else {
        $dbConfig = [
            'host' => 'localhost', 
            'database' => 'u881824383_pverde', 
            'username' => 'u881824383_proyverde',
            'password' => 'PProyectoverde8#', 
            'charset' => 'utf8mb4',
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, 
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        ];
    }

    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options'] ?? []);

    // Obtener todas las encuestas
    $stmt = $pdo->query("SELECT * FROM encuestas ORDER BY fecha_creacion DESC");
    $encuestas = $stmt->fetchAll();

} catch (PDOException $e) {
    $error_db = "Error de conexión: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Encuesta - Circunscripción</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
    :root { 
        --primary: #2d7a3e; 
        --primary-dark: #1f5a2d; 
        --dark: #1a1a1a; 
        --light: #f0f7f0; 
        --white: #ffffff; 
        --gray-100: #f8f9fa; 
        --gray-200: #e9ecef; 
        --text-muted: #6c757d; 
        --shadow-sm: 0 10px 30px rgba(0,0,0,0.05); 
        --danger: #d32f2f;
        --success: #2d7a3e;
        --info: #0288d1;
    }
    
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Arial', sans-serif; background: var(--light); color: var(--dark); overflow-x: hidden; }
    
    .container { 
        max-width: 100%; 
        margin: 0; 
        background: var(--white); 
        min-height: 100vh; 
        position: relative; 
        padding-left: 330px; 
        padding-right: 50px; 
        padding-bottom: 40px; 
        transition: padding-left 0.3s ease; 
    }
    
    .page-header { 
        padding: 50px 0 30px 0; 
        position: relative; 
        z-index: 2; 
        display: flex; 
        justify-content: space-between; 
        align-items: flex-end; 
        flex-wrap: wrap; 
        gap: 20px; 
    }
    
    .ai-badge { 
        color: var(--primary); 
        font-size: 13px; 
        font-weight: 800; 
        letter-spacing: 4px; 
        margin-bottom: 15px; 
        display: inline-block; 
        text-transform: uppercase; 
        background: rgba(45,122,62,0.1); 
        padding: 5px 15px; 
        border-radius: 20px; 
    }
    
    .main-title { 
        font-size: 54px; 
        font-weight: 900; 
        line-height: 1; 
        margin-bottom: 15px; 
        color: var(--dark); 
    }
    
    .description { 
        color: #666; 
        font-size: 16px; 
        line-height: 1.6; 
        max-width: 600px; 
    }

    .btn-primary-action { 
        background: var(--primary); 
        color: white; 
        border: none; 
        padding: 12px 25px; 
        border-radius: 12px; 
        font-weight: 700; 
        cursor: pointer; 
        transition: all 0.3s; 
        display: flex; 
        align-items: center; 
        gap: 10px; 
        font-size: 14px; 
        box-shadow: 0 4px 15px rgba(45, 122, 62, 0.3); 
    }
    
    .btn-primary-action:hover { 
        background: var(--primary-dark); 
        transform: translateY(-2px); 
    }

    .survey-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 25px;
        margin-top: 30px;
    }

    .survey-card {
        background: var(--white);
        border: 2px solid var(--gray-200);
        border-radius: 16px;
        padding: 25px;
        transition: all 0.3s;
        cursor: pointer;
        position: relative;
        overflow: hidden;
    }

    .survey-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 4px;
        background: linear-gradient(90deg, var(--primary), var(--success));
    }

    .survey-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(45, 122, 62, 0.15);
        border-color: var(--primary);
    }

    .survey-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 15px;
    }

    .survey-title {
        font-size: 20px;
        font-weight: 800;
        color: var(--dark);
        margin-bottom: 8px;
    }

    .survey-actions {
        display: flex;
        gap: 8px;
    }

    .btn-icon {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s;
        font-size: 14px;
    }

    /* Nuevo botón de Ver */
    .btn-view {
        background: var(--gray-100);
        color: var(--info);
    }
    .btn-view:hover {
        background: var(--info);
        color: white;
    }

    .btn-edit {
        background: var(--gray-100);
        color: var(--primary);
    }

    .btn-edit:hover {
        background: var(--primary);
        color: white;
    }

    .btn-delete {
        background: var(--gray-100);
        color: var(--danger);
    }

    .btn-delete:hover {
        background: var(--danger);
        color: white;
    }

    .survey-meta {
        display: flex;
        gap: 15px;
        margin-top: 15px;
        flex-wrap: wrap;
    }

    .meta-item {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 13px;
        color: var(--text-muted);
    }

    .meta-item i {
        color: var(--primary);
    }

    /* Modal */
    .modal {
        display: none;
        position: fixed;
        z-index: 10000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(5px);
    }

    .modal-content {
        background: var(--white);
        margin: 2% auto;
        padding: 0;
        border-radius: 20px;
        width: 90%;
        max-width: 900px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    }

    .modal-header {
        padding: 30px 35px 20px;
        border-bottom: 2px solid var(--gray-200);
        position: sticky;
        top: 0;
        background: var(--white);
        z-index: 10;
    }

    .modal-title {
        font-size: 28px;
        font-weight: 900;
        color: var(--dark);
    }

    .modal-body {
        padding: 30px 35px;
    }

    .close-modal {
        color: var(--text-muted);
        float: right;
        font-size: 32px;
        font-weight: 700;
        cursor: pointer;
        transition: color 0.3s;
        line-height: 1;
    }

    .close-modal:hover {
        color: var(--danger);
    }

    .form-group {
        margin-bottom: 25px;
    }

    .form-label {
        display: block;
        font-size: 14px;
        font-weight: 700;
        color: var(--dark);
        margin-bottom: 8px;
        display: flex;
        justify-content: space-between;
    }
    
    .char-count {
        font-size: 11px;
        color: var(--text-muted);
        font-weight: 400;
    }

    .form-input {
        width: 100%;
        padding: 12px 16px;
        border: 2px solid var(--gray-200);
        border-radius: 10px;
        font-size: 15px;
        transition: all 0.3s;
        font-family: inherit;
    }

    .form-input:focus {
        outline: none;
        border-color: var(--primary);
        background: var(--gray-100);
    }

    textarea.form-input {
        resize: vertical;
        min-height: 80px;
    }

    .questions-container {
        margin-top: 30px;
    }

    .question-item {
        background: var(--gray-100);
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 20px;
        border: 2px solid transparent;
        transition: all 0.3s;
    }

    .question-item:hover {
        border-color: var(--primary);
    }

    .question-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 15px;
    }

    .question-number {
        background: var(--primary);
        color: white;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 14px;
    }

    .question-controls {
        display: flex;
        gap: 8px;
    }

    .btn-sm {
        padding: 6px 12px;
        border-radius: 8px;
        border: none;
        cursor: pointer;
        font-size: 13px;
        font-weight: 600;
        transition: all 0.3s;
    }

    .btn-remove {
        background: var(--danger);
        color: white;
    }

    .btn-remove:hover {
        background: #b71c1c;
    }

    .question-type-selector {
        display: flex;
        gap: 10px;
        margin-bottom: 15px;
    }

    .type-btn {
        flex: 1;
        padding: 10px;
        border: 2px solid var(--gray-200);
        border-radius: 8px;
        background: var(--white);
        cursor: pointer;
        transition: all 0.3s;
        font-weight: 600;
        font-size: 13px;
    }

    .type-btn.active {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
    }

    .options-list {
        margin-top: 15px;
    }

    .option-item {
        display: flex;
        gap: 10px;
        margin-bottom: 10px;
    }

    .option-item input {
        flex: 1;
    }

    .btn-add-option {
        background: var(--gray-200);
        color: var(--dark);
        padding: 8px 16px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 600;
        font-size: 13px;
        transition: all 0.3s;
        margin-top: 10px;
    }

    .btn-add-option:hover {
        background: var(--primary);
        color: white;
    }

    .btn-add-question {
        width: 100%;
        padding: 15px;
        background: var(--white);
        border: 2px dashed var(--primary);
        color: var(--primary);
        border-radius: 12px;
        cursor: pointer;
        font-weight: 700;
        font-size: 15px;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }

    .btn-add-question:hover {
        background: var(--primary);
        color: white;
    }

    .modal-footer {
        padding: 20px 35px 30px;
        border-top: 2px solid var(--gray-200);
        display: flex;
        gap: 15px;
        justify-content: flex-end;
        position: sticky;
        bottom: 0;
        background: var(--white);
    }

    .btn-secondary {
        background: var(--gray-200);
        color: var(--dark);
        padding: 12px 25px;
        border: none;
        border-radius: 10px;
        cursor: pointer;
        font-weight: 700;
        font-size: 14px;
        transition: all 0.3s;
    }

    .btn-secondary:hover {
        background: var(--gray-300);
    }

    /* Estilos para Vista Previa (View Modal) */
    .view-question {
        background: var(--white);
        border: 1px solid var(--gray-200);
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 15px;
        box-shadow: var(--shadow-sm);
    }
    
    .view-question h4 {
        margin-bottom: 15px;
        color: var(--dark);
        font-size: 16px;
    }
    
    .view-option-list {
        list-style: none;
        padding: 0;
    }
    
    .view-option-item {
        padding: 8px 12px;
        background: var(--gray-100);
        margin-bottom: 5px;
        border-radius: 6px;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .view-badge-type {
        font-size: 11px;
        padding: 3px 8px;
        border-radius: 12px;
        background: var(--gray-200);
        color: var(--text-muted);
        float: right;
    }

    .empty-state {
        text-align: center;
        padding: 80px 20px;
    }

    .empty-state i {
        font-size: 80px;
        color: var(--gray-200);
        margin-bottom: 20px;
    }

    .empty-state h3 {
        font-size: 24px;
        font-weight: 800;
        color: var(--dark);
        margin-bottom: 10px;
    }

    .empty-state p {
        color: var(--text-muted);
        font-size: 16px;
    }

    @media (max-width: 768px) {
        .container {
            padding-left: 20px;
            padding-right: 20px;
        }

        .main-title {
            font-size: 36px;
        }

        .survey-grid {
            grid-template-columns: 1fr;
        }

        .modal-content {
            width: 95%;
            margin: 5% auto;
        }
    }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/components/sidebar_circunscripcion.php'; ?>

    <div class="container">
        <div class="page-header">
            <div>
                <div class="ai-badge">ENCUESTAS</div>
                <h1 class="main-title">Crear Encuestas</h1>
                <p class="description">Crea encuestas personalizadas con preguntas de opción múltiple o abiertas</p>
            </div>
            <button class="btn-primary-action" onclick="openCreateModal()">
                <i class="fas fa-plus"></i>
                Nueva Encuesta
            </button>
        </div>

        <?php if(empty($encuestas)): ?>
            <div class="empty-state">
                <i class="fas fa-clipboard-list"></i>
                <h3>No hay encuestas creadas</h3>
                <p>Comienza creando tu primera encuesta</p>
            </div>
        <?php else: ?>
            <div class="survey-grid" id="surveysGrid">
                <?php foreach($encuestas as $encuesta): ?>
                    <?php
                    // Obtener preguntas de la encuesta
                    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM preguntas WHERE id_encuesta = ?");
                    $stmt->execute([$encuesta['id']]);
                    $totalPreguntas = $stmt->fetch()['total'];
                    ?>
                    <div class="survey-card" data-survey-id="<?php echo $encuesta['id']; ?>">
                        <div class="survey-header">
                            <div>
                                <div class="survey-title"><?php echo htmlspecialchars($encuesta['titulo']); ?></div>
                                <?php if($encuesta['descripcion']): ?>
                                    <p style="color: var(--text-muted); font-size: 14px; margin-top: 5px;">
                                        <?php echo htmlspecialchars(mb_strimwidth($encuesta['descripcion'], 0, 60, "...")); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <div class="survey-actions">
                                <button class="btn-icon btn-view" onclick="viewSurvey(<?php echo $encuesta['id']; ?>); event.stopPropagation();" title="Ver detalle">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn-icon btn-edit" onclick="editSurvey(<?php echo $encuesta['id']; ?>); event.stopPropagation();" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn-icon btn-delete" onclick="deleteSurvey(<?php echo $encuesta['id']; ?>); event.stopPropagation();" title="Eliminar">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <div class="survey-meta">
                            <div class="meta-item">
                                <i class="fas fa-question-circle"></i>
                                <span><?php echo $totalPreguntas; ?> preguntas</span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-calendar"></i>
                                <span><?php echo date('d/m/Y', strtotime($encuesta['fecha_creacion'])); ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div id="viewSurveyModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="close-modal" onclick="closeViewModal()">&times;</span>
                <h2 class="modal-title" id="viewTitle">Detalle de Encuesta</h2>
            </div>
            <div class="modal-body">
                <p id="viewDescription" style="color: #666; margin-bottom: 25px; line-height: 1.6;"></p>
                
                <h3 style="font-size: 18px; margin-bottom: 15px; color: var(--primary);">Preguntas</h3>
                <div id="viewQuestionsList" style="background: var(--gray-100); padding: 20px; border-radius: 12px;">
                    </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeViewModal()">Cerrar</button>
            </div>
        </div>
    </div>

    <div id="surveyModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="close-modal" onclick="closeModal()">&times;</span>
                <h2 class="modal-title" id="modalTitle">Nueva Encuesta</h2>
            </div>
            <form id="surveyForm" onsubmit="saveSurvey(event)">
                <div class="modal-body">
                    <input type="hidden" id="surveyId" value="">
                    
                    <div class="form-group">
                        <label class="form-label">
                            Título de la encuesta *
                            <span class="char-count">Máx 40 caracteres</span>
                        </label>
                        <input type="text" id="surveyTitle" class="form-input" placeholder="Ej: Encuesta de Satisfacción 2025" required maxlength="40">
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            Descripción (opcional)
                            <span class="char-count">Máx 200 caracteres</span>
                        </label>
                        <textarea id="surveyDescription" class="form-input" placeholder="Descripción breve de la encuesta" rows="3" maxlength="200"></textarea>
                    </div>

                    <div class="questions-container">
                        <h3 style="font-size: 18px; font-weight: 800; margin-bottom: 20px; color: var(--dark);">Preguntas</h3>
                        <div id="questionsList"></div>
                        <button type="button" class="btn-add-question" onclick="addQuestion()">
                            <i class="fas fa-plus"></i>
                            Agregar Pregunta
                        </button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeModal()">Cancelar</button>
                    <button type="submit" class="btn-primary-action">
                        <i class="fas fa-save"></i>
                        Guardar Encuesta
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let questionCounter = 0;
        let currentQuestions = [];

        function openCreateModal() {
            document.getElementById('modalTitle').textContent = 'Nueva Encuesta';
            document.getElementById('surveyId').value = '';
            document.getElementById('surveyForm').reset();
            document.getElementById('questionsList').innerHTML = '';
            currentQuestions = [];
            questionCounter = 0;
            document.getElementById('surveyModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('surveyModal').style.display = 'none';
        }

        function closeViewModal() {
            document.getElementById('viewSurveyModal').style.display = 'none';
        }

        function addQuestion() {
            questionCounter++;
            const questionId = `q${questionCounter}`;
            
            // Límite de 80 caracteres en la pregunta (maxlength="80")
            const questionHTML = `
                <div class="question-item" id="${questionId}">
                    <div class="question-header">
                        <div class="question-number">${questionCounter}</div>
                        <div class="question-controls">
                            <button type="button" class="btn-sm btn-remove" onclick="removeQuestion('${questionId}')">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            Pregunta *
                            <span class="char-count">Máx 80 caracteres</span>
                        </label>
                        <input type="text" class="form-input question-text" placeholder="Escribe tu pregunta aquí" required maxlength="80">
                    </div>

                    <div class="question-type-selector">
                        <button type="button" class="type-btn active" onclick="setQuestionType('${questionId}', 'multiple')">
                            <i class="fas fa-list-ul"></i> Opción Múltiple
                        </button>
                        <button type="button" class="type-btn" onclick="setQuestionType('${questionId}', 'abierta')">
                            <i class="fas fa-align-left"></i> Pregunta Abierta
                        </button>
                    </div>

                    <div class="options-container" id="${questionId}-options">
                        <div class="options-list">
                            <div class="option-item">
                                <input type="text" class="form-input option-text" placeholder="Opción 1" maxlength="80">
                                <button type="button" class="btn-sm btn-remove" onclick="removeOption(this)">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <div class="option-item">
                                <input type="text" class="form-input option-text" placeholder="Opción 2" maxlength="80">
                                <button type="button" class="btn-sm btn-remove" onclick="removeOption(this)">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:10px;">
                            <button type="button" class="btn-add-option" onclick="addOption('${questionId}')">
                                <i class="fas fa-plus"></i> Agregar Opción
                            </button>
                            <span class="char-count">Máx 8 opciones por pregunta (80 chars c/u)</span>
                        </div>
                    </div>
                </div>
            `;

            document.getElementById('questionsList').insertAdjacentHTML('beforeend', questionHTML);
        }

        function removeQuestion(questionId) {
            if(confirm('¿Eliminar esta pregunta?')) {
                document.getElementById(questionId).remove();
                renumberQuestions();
            }
        }

        function renumberQuestions() {
            const questions = document.querySelectorAll('.question-item');
            questions.forEach((q, index) => {
                q.querySelector('.question-number').textContent = index + 1;
            });
        }

        function setQuestionType(questionId, type) {
            const question = document.getElementById(questionId);
            const buttons = question.querySelectorAll('.type-btn');
            const optionsContainer = document.getElementById(`${questionId}-options`);
            
            buttons.forEach(btn => btn.classList.remove('active'));
            event.target.closest('.type-btn').classList.add('active');

            if(type === 'abierta') {
                optionsContainer.style.display = 'none';
            } else {
                optionsContainer.style.display = 'block';
            }
        }

        function addOption(questionId) {
            const optionsList = document.querySelector(`#${questionId} .options-list`);
            const currentOptions = optionsList.querySelectorAll('.option-item').length;

            // Límite de 8 opciones
            if (currentOptions >= 8) {
                alert("Solo puedes agregar hasta 8 opciones por pregunta.");
                return;
            }

            const optionCount = currentOptions + 1;
            
            // Límite de 80 caracteres en la opción (maxlength="80")
            const optionHTML = `
                <div class="option-item">
                    <input type="text" class="form-input option-text" placeholder="Opción ${optionCount}" maxlength="80">
                    <button type="button" class="btn-sm btn-remove" onclick="removeOption(this)">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            optionsList.insertAdjacentHTML('beforeend', optionHTML);
        }

        function removeOption(button) {
            const optionsList = button.closest('.options-list');
            if(optionsList.querySelectorAll('.option-item').length > 2) {
                button.closest('.option-item').remove();
            } else {
                alert('Debe haber al menos 2 opciones');
            }
        }

        async function saveSurvey(event) {
            event.preventDefault();

            const surveyId = document.getElementById('surveyId').value;
            const title = document.getElementById('surveyTitle').value;
            const description = document.getElementById('surveyDescription').value;

            // Recopilar preguntas
            const questions = [];
            const questionItems = document.querySelectorAll('.question-item');

            questionItems.forEach((item, index) => {
                const questionText = item.querySelector('.question-text').value;
                const typeBtn = item.querySelector('.type-btn.active');
                const type = typeBtn.textContent.includes('Opción Múltiple') ? 'multiple' : 'abierta';
                
                const question = {
                    orden: index + 1,
                    pregunta: questionText,
                    tipo: type,
                    opciones: []
                };

                if(type === 'multiple') {
                    const options = item.querySelectorAll('.option-text');
                    options.forEach(opt => {
                        if(opt.value.trim()) {
                            question.opciones.push(opt.value.trim());
                        }
                    });
                }

                questions.push(question);
            });

            if(questions.length === 0) {
                alert('Debes agregar al menos una pregunta');
                return;
            }

            const data = {
                id: surveyId,
                titulo: title,
                descripcion: description,
                preguntas: questions
            };

            try {
                const response = await fetch('../../api/encuestas/get.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if(result.success) {
                    alert('Encuesta guardada exitosamente');
                    closeModal();
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch(error) {
                console.error('Error:', error);
                alert('Error al guardar la encuesta');
            }
        }

        // Función para visualizar Encuesta (solo lectura)
        async function viewSurvey(id) {
            try {
                const response = await fetch(`../../api/encuestas/get.php?id=${id}`);
                const data = await response.json();

                if(data.success) {
                    const survey = data.encuesta;
                    
                    document.getElementById('viewTitle').textContent = survey.titulo;
                    document.getElementById('viewDescription').textContent = survey.descripcion || 'Sin descripción';

                    const listContainer = document.getElementById('viewQuestionsList');
                    listContainer.innerHTML = '';

                    survey.preguntas.forEach((pregunta, index) => {
                        let html = `
                            <div class="view-question">
                                <span class="view-badge-type">${pregunta.tipo === 'multiple' ? 'Opción Múltiple' : 'Abierta'}</span>
                                <h4>${index + 1}. ${pregunta.pregunta}</h4>
                        `;

                        if (pregunta.tipo === 'multiple' && pregunta.opciones && pregunta.opciones.length > 0) {
                            html += `<ul class="view-option-list">`;
                            pregunta.opciones.forEach(op => {
                                html += `<li class="view-option-item"><i class="far fa-circle" style="color:var(--primary)"></i> ${op}</li>`;
                            });
                            html += `</ul>`;
                        } else if (pregunta.tipo === 'abierta') {
                            html += `<div style="background:#f9f9f9; border:1px dashed #ccc; padding:10px; border-radius:8px; color:#999; font-size:13px; font-style:italic;">Espacio para respuesta de texto...</div>`;
                        }

                        html += `</div>`;
                        listContainer.insertAdjacentHTML('beforeend', html);
                    });

                    document.getElementById('viewSurveyModal').style.display = 'block';
                }
            } catch(error) {
                console.error('Error:', error);
                alert('Error al cargar la encuesta');
            }
        }

        async function editSurvey(id) {
            try {
                const response = await fetch(`../../api/encuestas/get.php?id=${id}`);
                const data = await response.json();

                if(data.success) {
                    const survey = data.encuesta;
                    
                    document.getElementById('modalTitle').textContent = 'Editar Encuesta';
                    document.getElementById('surveyId').value = survey.id;
                    document.getElementById('surveyTitle').value = survey.titulo;
                    document.getElementById('surveyDescription').value = survey.descripcion || '';

                    // Cargar preguntas
                    document.getElementById('questionsList').innerHTML = '';
                    questionCounter = 0;

                    survey.preguntas.forEach(pregunta => {
                        addQuestion();
                        const questionItem = document.querySelectorAll('.question-item')[questionCounter - 1];
                        
                        questionItem.querySelector('.question-text').value = pregunta.pregunta;
                        
                        if(pregunta.tipo === 'abierta') {
                            const abiertaBtn = questionItem.querySelectorAll('.type-btn')[1];
                            abiertaBtn.click();
                        } else {
                            const optionsList = questionItem.querySelector('.options-list');
                            optionsList.innerHTML = '';
                            
                            pregunta.opciones.forEach((opcion, idx) => {
                                const optionHTML = `
                                    <div class="option-item">
                                        <input type="text" class="form-input option-text" value="${opcion}" placeholder="Opción ${idx + 1}" maxlength="80">
                                        <button type="button" class="btn-sm btn-remove" onclick="removeOption(this)">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                `;
                                optionsList.insertAdjacentHTML('beforeend', optionHTML);
                            });
                        }
                    });

                    document.getElementById('surveyModal').style.display = 'block';
                }
            } catch(error) {
                console.error('Error:', error);
                alert('Error al cargar la encuesta');
            }
        }

        async function deleteSurvey(id) {
            if(!confirm('¿Estás seguro de eliminar esta encuesta? Esta acción no se puede deshacer.')) {
                return;
            }

            try {
                const response = await fetch(`../../api/encuestas/get.php?id=${id}`, {
                    method: 'DELETE'
                });

                const result = await response.json();

                if(result.success) {
                    alert('Encuesta eliminada exitosamente');
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch(error) {
                console.error('Error:', error);
                alert('Error al eliminar la encuesta');
            }
        }

        // Cerrar modal al hacer clic fuera
        window.onclick = function(event) {
            const modal = document.getElementById('surveyModal');
            const viewModal = document.getElementById('viewSurveyModal');
            if(event.target === modal) {
                closeModal();
            }
            if(event.target === viewModal) {
                closeViewModal();
            }
        }
    </script>
</body>
</html>