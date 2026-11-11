<?php
// ==========================================
// 1. SEGURIDAD Y SESIÓN
// ==========================================
if (file_exists(__DIR__ . '/../../includes/bootstrap.php')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
} else {
    session_start();
}

$rol = $_SESSION['user_role'] ?? '';
$estado_sesion = $_SESSION['user_estado'] ?? 'Sin Asignar';
$nombre_coordinador = $_SESSION['nombre_completo'] ?? 'Coordinador';

// ==========================================
// 2. DATOS INICIALES
// ==========================================
$lista_actividades_banco = [];
$tema_mes = "Cargando...";
$mes_actual_num = date('n');
$meses_nombres = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
$nombre_mes_actual = $meses_nombres[$mes_actual_num - 1];
$anio_actual = date('Y');

try {
    $db = Database::getInstance()->getConnection();

    // A. CARGAR BANCO GLOBAL
    $stmtBanco = $db->query("SELECT id, titulo, codigo_referencia, agenda FROM banco_actividades ORDER BY titulo ASC");
    $lista_actividades_banco = $stmtBanco->fetchAll(PDO::FETCH_ASSOC);

    // B. CARGAR TEMA (Se carga inicialmente, JS lo actualizará al navegar)
    $stmtTema = $db->prepare("SELECT tema FROM dirigentes_temas WHERE estado = ? AND mes = ? LIMIT 1");
    $stmtTema->execute([$estado_sesion, $nombre_mes_actual]);
    $resTema = $stmtTema->fetch(PDO::FETCH_ASSOC);
    $tema_mes = $resTema ? $resTema['tema'] : "Sin tema asignado";

} catch (Exception $e) {
    $error_db = "Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Actividades - <?php echo htmlspecialchars($estado_sesion); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        /* --- ESTILOS BASE --- */
        :root { --primary: #2d7a3e; --primary-dark: #1f5a2d; --dark: #1a1a1a; --light: #f0f7f0; --white: #ffffff; --gray-100: #f8f9fa; --gray-200: #e9ecef; --text-muted: #6c757d; --shadow-sm: 0 10px 30px rgba(0,0,0,0.05); }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; background: var(--light); color: var(--dark); overflow-x: hidden; }
        .container { max-width: 100%; margin: 0; background: var(--white); min-height: 100vh; position: relative; padding-left: 330px; padding-right: 50px; padding-bottom: 100px; transition: padding-left 0.3s ease; }
        .design-text { position: fixed; left: 290px; top: 50%; writing-mode: vertical-rl; font-size: 13px; font-weight: 500; color: var(--primary); opacity: 0.3; letter-spacing: 2px; z-index: 0; pointer-events: none; }
        
        .page-header { padding: 50px 0 30px 0; display: flex; justify-content: space-between; align-items: flex-end; }
        .ai-badge { color: var(--primary); font-size: 13px; font-weight: 800; letter-spacing: 4px; margin-bottom: 15px; display: inline-block; text-transform: uppercase; background: rgba(45,122,62,0.1); padding: 5px 15px; border-radius: 20px; }
        .main-title { font-size: 54px; font-weight: 900; line-height: 1; margin-bottom: 10px; color: var(--dark); }
        .description { color: #666; font-size: 16px; line-height: 1.6; max-width: 600px; }

        /* HEADER DEL ESTADO */
        .state-header { 
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-radius: 20px; padding: 30px 40px; margin-bottom: 30px;
            color: white; box-shadow: var(--shadow-sm);
        }
        .state-title { font-size: 32px; font-weight: 900; margin: 0; }
        .state-subtitle { font-size: 16px; opacity: 0.9; font-weight: 600; margin-top: 5px; }

        /* SECCIÓN VISIÓN Y MISIÓN */
        .vm-section { margin-bottom: 30px; }
        .section-header-flex { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .section-title { font-size: 18px; font-weight: 800; color: var(--dark); margin: 0; }
        .vm-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .vm-box { background: var(--gray-100); padding: 25px; border-radius: 15px; border: 1px solid var(--gray-200); }
        .vm-box h4 { font-size: 14px; font-weight: 700; color: var(--primary); margin-bottom: 15px; text-transform: uppercase; display: flex; align-items: center; gap: 10px; }
        .vm-text-content { font-size: 15px; color: #555; line-height: 1.6; white-space: pre-line; }
        .vm-textarea { width: 100%; padding: 15px; border-radius: 10px; border: 2px solid var(--primary); font-family: inherit; font-size: 15px; resize: vertical; display: none; }

        /* NAVEGACIÓN MES Y TEMA */
        .month-theme-container { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 30px; align-items: stretch; }
        .month-navigation { display: flex; align-items: center; justify-content: space-between; padding: 15px 25px; background: var(--gray-100); border-radius: 12px; border: 1px solid var(--gray-200); }
        .month-nav-btn { background: white; border: 1px solid var(--gray-200); color: var(--dark); padding: 10px 15px; border-radius: 8px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: all 0.2s; font-size: 13px; }
        .month-nav-btn:hover { background: var(--primary); color: white; border-color: var(--primary); }
        .current-month { font-size: 20px; font-weight: 900; color: var(--dark); text-align: center; }

        .theme-box { background: #e3f2fd; border: 1px solid #bbdefb; border-left: 5px solid #2196f3; padding: 20px; border-radius: 12px; display: flex; flex-direction: column; justify-content: center; }
        .theme-label { font-size: 12px; font-weight: 800; text-transform: uppercase; color: #1565c0; margin-bottom: 5px; display: block;}
        .theme-value { font-size: 20px; font-weight: 900; color: #0d47a1; line-height: 1.2; }

        /* GRÁFICA */
        .chart-section {
            background: var(--white); border-radius: 20px; padding: 25px; 
            border: 1px solid var(--gray-200); box-shadow: var(--shadow-sm);
            margin-bottom: 30px; height: 300px; position: relative;
        }
        .chart-title { font-size: 14px; font-weight: 800; color: #666; text-transform: uppercase; margin-bottom: 15px; }

        /* TABLA DE TAREAS */
        .table-section { 
            background: var(--white); 
            border-radius: 20px; 
            padding: 30px; 
            border: 1px solid var(--gray-200); 
            box-shadow: var(--shadow-sm); 
        }
        .table-header-actions { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .table-title-sm { font-weight: 800; font-size: 18px; margin: 0; color: var(--dark); }
        
        .table-wrapper-fixed {
            min-height: 450px; 
            position: relative;
            overflow-x: auto;
        }

        .modern-table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .modern-table th { text-align: left; padding: 15px; color: var(--text-muted); font-size: 11px; text-transform: uppercase; letter-spacing: 1px; border-bottom: 2px solid var(--gray-200); }
        .modern-table td { padding: 15px; border-bottom: 1px solid var(--gray-200); font-size: 13px; color: var(--dark); vertical-align: middle; }
        
        /* OVERLAY DE CARGA */
        .loading-overlay {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(255, 255, 255, 0.85);
            display: none; 
            align-items: center; justify-content: center;
            flex-direction: column;
            z-index: 10;
            backdrop-filter: blur(3px);
            border-radius: 10px;
        }
        .loading-overlay.active { display: flex; }
        
        .empty-state {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            height: 300px; color: #999;
            width: 100%; position: absolute; top: 0; left: 0;
        }

        /* STATUS DOTS */
        .status-dot { height: 10px; width: 10px; border-radius: 50%; display: inline-block; margin-right: 5px; }
        .status-dot.completed { background-color: #2ecc71; }
        .status-dot.progress { background-color: #f1c40f; }
        .status-dot.review { background-color: #fd7e14; }
        .status-dot.pending { background-color: #e74c3c; }

        /* BADGES AGENDA */
        .badge-agenda { padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; color: white; display: inline-block; }
        .agenda-mediatica { background-color: #e91e63; }
        .agenda-tematica { background-color: #9c27b0; }
        .agenda-social { background-color: #4caf50; }
        .agenda-politica { background-color: #2196f3; }
        .agenda-default { background-color: #999; }
        
        /* BADGE ORIGEN */
        .badge-origen {
            padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; display: inline-block; white-space: nowrap;
        }
        .badge-origen.admin { background-color: #e3f2fd; color: #1565c0; border: 1px solid #bbdefb; }
        .badge-origen.user { background-color: #f0f7f0; color: #2d7a3e; border: 1px solid #c8e6c9; }

        /* BOTONES GENÉRICOS */
        .btn-primary-action { background: var(--primary); color: white; border: none; padding: 12px 25px; border-radius: 12px; font-weight: 700; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; gap: 10px; font-size: 14px; box-shadow: 0 4px 15px rgba(45, 122, 62, 0.3); text-decoration: none; }
        .btn-primary-action:hover { background: var(--primary-dark); transform: translateY(-2px); }
        .btn-sm { padding: 8px 15px; font-size: 12px; }
        
        .action-cell { text-align: right; position: relative; }
        .action-btn { background: none; border: none; cursor: pointer; color: var(--text-muted); font-size: 16px; padding: 8px 12px; border-radius: 50%; transition: all 0.2s; }
        .action-btn:hover, .action-btn.active { background: var(--gray-200); color: var(--primary); }

        /* MENUS Y MODALES */
        .dropdown-menu { display: none; position: absolute; right: 0; top: 100%; background: white; border: 1px solid var(--gray-200); border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); z-index: 100; min-width: 180px; overflow: hidden; }
        .dropdown-menu.show { display: block; }
        .dropdown-item { display: flex; align-items: center; gap: 10px; padding: 12px 15px; width: 100%; border: none; background: none; text-align: left; cursor: pointer; font-size: 13px; font-weight: 600; color: var(--dark); transition: background 0.2s; }
        .dropdown-item:hover { background: var(--gray-100); color: var(--primary); }

        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 2000; align-items: center; justify-content: center; }
        .modal.open { display: flex; }
        .modal-content { background: var(--white); border-radius: 20px; width: 90%; max-width: 600px; padding: 40px; position: relative; max-height: 90vh; overflow-y: auto; }
        .close-modal { position: absolute; top: 20px; right: 20px; font-size: 20px; cursor: pointer; color: #ccc; }
        
        .form-group { margin-bottom: 15px; }
        .form-label { display: block; font-size: 12px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 5px; }
        .form-input-styled { width: 100%; padding: 12px; border-radius: 8px; border: 2px solid var(--gray-200); font-size: 14px; }
        
        .file-download-link { display: inline-flex; align-items: center; gap: 5px; color: var(--primary); text-decoration: none; font-weight: 600; font-size: 12px; background: var(--light); padding: 5px 10px; border-radius: 5px; }

        .loading-spinner { display: inline-block; width: 30px; height: 30px; border: 3px solid rgba(45, 122, 62, 0.3); border-radius: 50%; border-top-color: var(--primary); animation: spin 0.8s linear infinite; margin-bottom: 10px;}
        @keyframes spin { to { transform: rotate(360deg); } }

        @media (max-width: 1024px) {
            .container { padding-left: 20px; padding-right: 20px; }
            .page-header { flex-direction: column; align-items: flex-start; gap: 20px; }
            .design-text { display: none; }
            .vm-grid, .month-theme-container { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/components/sidebar_ce.php'; ?>
    
    <div class="design-text">PLAN <?php echo strtoupper($estado_sesion); ?></div>

    <div class="container">
        
        <div class="page-header">
            <div>
                <div class="ai-badge"><?php echo strtoupper($estado_sesion); ?></div>
                <h1 class="main-title">Gestión de Actividades</h1>
                <p class="description">Gestión y seguimiento de actividades para el estado de <strong><?php echo htmlspecialchars($estado_sesion); ?></strong>.</p>
            </div>
            <div>
                <button class="btn-primary-action" onclick="openAssignModal()">
                    <i class="fas fa-plus-circle"></i> Agregar del Banco
                </button>
            </div>
        </div>

<div class="state-header">
    <h2 class="state-title"><?php echo htmlspecialchars($estado_sesion); ?></h2>
    <p class="state-subtitle"><?php echo htmlspecialchars($nombre_coordinador); ?></p>
</div>

        <div class="month-theme-container">
            <div class="month-navigation">
                <button class="month-nav-btn" onclick="changeMonth(-1)">
                    <i class="fas fa-chevron-left"></i> Anterior
                </button>
                <div class="current-month" id="currentMonthDisplay">Enero 2026</div>
                <button class="month-nav-btn" onclick="changeMonth(1)">
                    Siguiente <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            <div class="theme-box">
                <span class="theme-label">Eje Temático del Mes</span>
                <div class="theme-value" id="themeDisplayMonth"><?php echo htmlspecialchars($tema_mes); ?></div>
            </div>
        </div>

        <div class="chart-section">
            <h4 class="chart-title">Distribución de Agenda (%)</h4>
            <div style="height: 240px; position: relative; width: 100%;">
                <canvas id="agendaChart"></canvas>
            </div>
        </div>

        <div class="table-section">
            <div class="table-header-actions">
                <h4 class="table-title-sm">Actividades Programadas</h4>
            </div>

            <div class="table-wrapper-fixed">
                
                <div id="loadingOverlay" class="loading-overlay">
                    <div class="loading-spinner"></div>
                    <span style="font-weight: 600; color: var(--primary);">Cargando plan...</span>
                </div>

                <table class="modern-table" id="mainTable">
                    <thead>
                        <tr>
                            <th>Origen</th>
                            <th>Actividad</th>
                            <th>Agenda</th>
                            <th>Estatus</th>
                            <th>Impacto Meta</th>
                            <th>Evidencia</th>
                            <th style="text-align: right;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tasksTableBody">
                    </tbody>
                </table>
                
                <div id="emptyState" class="empty-state" style="display: none;">
                    <i class="fas fa-calendar-times" style="font-size: 40px; margin-bottom: 10px; opacity: 0.5;"></i>
                    <p>No hay actividades programadas para este mes.</p>
                    <button class="btn-primary-action btn-sm" onclick="openAssignModal()" style="margin-top: 10px;">
                        + Agregar Actividad
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal" id="assignTaskModal">
        <div class="modal-content">
            <div class="close-modal" onclick="closeAssignModal()"><i class="fas fa-times"></i></div>
            <h2 style="font-size: 24px; font-weight: 800; margin-bottom: 20px; color: var(--dark);">Agregar Actividad al Plan</h2>
            
            <form id="assignTaskForm" onsubmit="handleAssignTask(event)">
                <input type="hidden" id="assignState" name="estado" value="<?php echo htmlspecialchars($estado_sesion); ?>">
                
                <div class="form-group">
                    <label class="form-label">Seleccionar Actividad (Banco Global)</label>
                    <select class="form-input-styled" id="assignTaskSelect" name="id_actividad" required>
                        <option value="">-- Buscar en el Banco --</option>
                        <?php foreach($lista_actividades_banco as $act): ?>
                            <option value="<?php echo htmlspecialchars($act['id']); ?>" 
                                    data-agenda="<?php echo htmlspecialchars($act['agenda'] ?? 'social'); ?>">
                                <?php echo htmlspecialchars($act['codigo_referencia'] . ' - ' . $act['titulo']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label class="form-label">Mes de Ejecución</label>
                        <select class="form-input-styled" id="assignMonth" name="mes" required>
                            <?php foreach($meses_nombres as $m): ?>
                                <option value="<?php echo $m; ?>"><?php echo $m; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Meta de Impacto</label>
                        <select class="form-input-styled" id="assignImpact" name="impacto" required>
                            <option value="50">50 Personas</option>
                            <option value="100">100 Personas</option>
                            <option value="250">250 Personas</option>
                            <option value="500">500 Personas</option>
                            <option value="1000">1,000+ Personas</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Notas Internas</label>
                    <textarea class="form-input-styled" id="assignComments" name="comentarios" rows="2" placeholder="Detalles específicos para esta ejecución..."></textarea>
                </div>

                <button type="submit" class="btn-primary-action" style="width: 100%; justify-content: center;">
                    <i class="fas fa-plus-circle"></i> Agregar al Plan
                </button>
            </form>
        </div>
    </div>

    <div class="modal" id="uploadReportModal">
        <div class="modal-content">
            <div class="close-modal" onclick="closeUploadModal()"><i class="fas fa-times"></i></div>
            <h2 style="font-size: 20px; font-weight: 800; margin-bottom: 5px;">Subir Evidencia</h2>
            <p style="color: #666; font-size: 13px; margin-bottom: 20px;">Carga el reporte PDF de la actividad realizada.</p>
            
            <form id="uploadReportForm" onsubmit="handleUploadReport(event)" enctype="multipart/form-data">
                <input type="hidden" id="uploadTaskId" name="id">
                
                <div class="form-group">
                    <label class="form-label">Actividad</label>
                    <input type="text" id="uploadTaskName" class="form-input-styled" readonly style="background: #e9ecef;">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Archivo PDF</label>
                    <input type="file" id="reportFile" name="reporte" class="form-input-styled" accept=".pdf" required>
                </div>
                
                <button type="submit" class="btn-primary-action" style="width: 100%; justify-content: center; margin-top: 10px;">
                    <i class="fas fa-upload"></i> Subir PDF
                </button>
            </form>
        </div>
    </div>

    <div class="modal" id="statusModal">
        <div class="modal-content" style="max-width: 400px;">
            <div class="close-modal" onclick="closeStatusModal()"><i class="fas fa-times"></i></div>
            <h2 style="font-size: 20px; font-weight: 800; margin-bottom: 20px;">Actualizar Estatus</h2>
            <form onsubmit="handleChangeStatus(event)">
                <input type="hidden" id="statusTaskId">
                <div class="form-group">
                    <label class="form-label">Nuevo Estatus</label>
                    <select class="form-input-styled" id="newStatusSelect">
                        <option value="pendiente">Pendiente</option>
                        <option value="en_proceso">En Proceso</option>
                        <option value="por_revisar">Por Revisar</option>
                        <option value="completado">Completado</option>
                    </select>
                </div>
                <button type="submit" class="btn-primary-action" style="width: 100%; justify-content: center;">
                    Guardar
                </button>
            </form>
        </div>
    </div>

    <script>
        // --- VARIABLES GLOBALES ---
        const currentEstado = '<?php echo addslashes($estado_sesion); ?>';
        
        // CONFIGURADO PARA INICIAR EN ENERO 2026
        let currentMonth = 'Enero';
        let currentYear = 2026;
        
        const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        let isEditingVM = false;
        let agendaChartInstance = null; 

        // --- INICIALIZACIÓN ---
        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('currentMonthDisplay').textContent = `${currentMonth} ${currentYear}`;
            loadVisionMision();
            loadTasks();
        });

        // ==========================================
        // FUNCIONES DE VISIÓN Y MISIÓN
        // ==========================================
        async function loadVisionMision() {
            const vDisplay = document.getElementById('visionDisplay');
            const mDisplay = document.getElementById('misionDisplay');
            try {
                const res = await fetch(`../../api/dirigentes/obtener_vision_mision.php?estado=${encodeURIComponent(currentEstado)}`);
                const data = await res.json();
                if(vDisplay) vDisplay.textContent = data.vision || 'Sin visión definida. Haz clic en editar para agregarla.';
                if(mDisplay) mDisplay.textContent = data.mision || 'Sin misión definida. Haz clic en editar para agregarla.';
            } catch (e) {
                console.error(e);
            }
        }

        async function toggleVMEdit() {
            const btn = document.getElementById('btn EditVM');
            const vDisplay = document.getElementById('visionDisplay');
            const vInput = document.getElementById('visionInput');
            const mDisplay = document.getElementById('misionDisplay');
            const mInput = document.getElementById('misionInput');

            if (!isEditingVM) {
                // Entrar en modo edición
                vInput.value = vDisplay.textContent.includes('Sin visión definida') ? '' : vDisplay.textContent;
                mInput.value = mDisplay.textContent.includes('Sin misión definida') ? '' : mDisplay.textContent;
                
                vDisplay.style.display = 'none'; mDisplay.style.display = 'none';
                vInput.style.display = 'block'; mInput.style.display = 'block';
                
                btn.innerHTML = '<i class="fas fa-save"></i> Guardar';
                isEditingVM = true;
            } else {
                // Guardar cambios
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...'; btn.disabled = true;
                try {
                    const formData = new FormData();
                    formData.append('estado', currentEstado);
                    formData.append('vision', vInput.value);
                    formData.append('mision', mInput.value);
                    const res = await fetch('../../api/dirigentes/guardar_vision_mision.php', { method: 'POST', body: formData });
                    const json = await res.json();
                    if(json.success) {
                        vDisplay.textContent = vInput.value || 'Sin visión definida. Haz clic en editar para agregarla.';
                        mDisplay.textContent = mInput.value || 'Sin misión definida. Haz clic en editar para agregarla.';
                    } else { alert('Error al guardar: ' + json.message); }
                } catch(e) { alert('Error de conexión al guardar.'); }
                finally {
                    vDisplay.style.display = 'block'; mDisplay.style.display = 'block';
                    vInput.style.display = 'none'; mInput.style.display = 'none';
                    btn.innerHTML = '<i class="fas fa-edit"></i> Editar';
                    btn.disabled = false;
                    isEditingVM = false;
                }
            }
        }

        // ==========================================
        // FUNCIONES DE TAREAS (PLAN)
        // ==========================================
        async function loadTasks() {
            const tableBody = document.getElementById('tasksTableBody');
            const loadingOverlay = document.getElementById('loadingOverlay');
            const mainTable = document.getElementById('mainTable');
            const emptyState = document.getElementById('emptyState');
            
            loadingOverlay.classList.add('active');
            loadTheme(currentMonth);

            try {
                // Se envía también el año para asegurar que el backend filtre correctamente si tiene soporte para años
                const response = await fetch(`../../api/dirigentes/obtener_tareas.php?estado=${encodeURIComponent(currentEstado)}&anio=${currentYear}`);
                
                if (!response.ok) { throw new Error('Error en el servidor'); }

                const allTasks = await response.json();
                
                if (!Array.isArray(allTasks)) {
                    if(allTasks.error) throw new Error(allTasks.message);
                    throw new Error("Formato de respuesta inválido");
                }
                
                // --- CORRECCIÓN CRÍTICA DE FILTRADO ---
                const tasks = allTasks.filter(t => {
                    const mesMatch = t.mes_objetivo === currentMonth;
                    
                    // LÓGICA DE AÑO:
                    // 1. Si la tarea tiene año ('t.anio'), debe coincidir exactamente.
                    // 2. Si la tarea NO tiene año (null/undefined), SOLO se muestra en 2026.
                    //    Esto evita que las tareas viejas aparezcan en 2027.
                    const anioMatch = t.anio ? (t.anio == currentYear) : (currentYear === 2026);
                    
                    return mesMatch && anioMatch;
                });
                
                tableBody.innerHTML = '';
                const agendaCounts = { 'Mediática': 0, 'Temática': 0, 'Social': 0, 'Política': 0 };

                if (tasks.length === 0) {
                    mainTable.style.display = 'none';
                    emptyState.style.display = 'flex';
                    updateChart(agendaCounts, 0);
                } else {
                    mainTable.style.display = 'table';
                    emptyState.style.display = 'none';

                    tasks.forEach(t => {
                    let rawName = t.nombre_actividad || 'Actividad sin nombre';
                    let safeName = rawName.replace(/'/g, "\\'");

                    // 1. Categoría y Agenda
                    let cat = t.categoria ? t.categoria.toLowerCase() : 'social';
                    let catName = 'Social';
                    let badgeClass = 'agenda-social';

                    if(cat.includes('mediatica')) { catName = 'Mediática'; badgeClass = 'agenda-mediatica'; }
                    else if(cat.includes('tematica')) { catName = 'Temática'; badgeClass = 'agenda-tematica'; }
                    else if(cat.includes('politica')) { catName = 'Política'; badgeClass = 'agenda-politica'; }
                    
                    if (agendaCounts[catName] !== undefined) agendaCounts[catName]++;

                    // 2. Estatus
                    let dotClass = 'pending'; let statusText = 'Pendiente';
                    if(t.estatus === 'en_proceso') { dotClass = 'progress'; statusText = 'En Proceso'; }
                    if(t.estatus === 'por_revisar') { dotClass = 'review'; statusText = 'Por Revisar'; }
                    if(t.estatus === 'completado') { dotClass = 'completed'; statusText = 'Completado'; }

                    // 3. Origen
                    let origenHtml = '';
                    if (t.tipo_origen === 'admin') {
                        origenHtml = `<span class="badge-origen admin"><i class="fas fa-user-shield"></i> ${t.creado_por_nombre}</span>`;
                    } else {
                        origenHtml = `<span class="badge-origen user"><i class="fas fa-user"></i> ${t.creado_por_nombre}</span>`;
                    }

                    // 4. Guía
                    let guiaLink = '';
                    if (t.guia_pdf) {
                        guiaLink = `<div style="margin-top:4px;"><a href="../../api/banco_de_actividades/documentos/${t.guia_pdf}" target="_blank" style="font-size:11px; color:#555; text-decoration:underline;"><i class="fas fa-download"></i> Descargar Guía</a></div>`;
                    }

                    // 5. Evidencia
                    const evidenciaLink = t.reporte_adjunto 
                        ? `<a href="../../storage/planes_estatales/${t.reporte_adjunto}" target="_blank" class="file-download-link" style="color:#2d7a3e"><i class="fas fa-check-circle"></i> Ver Evidencia</a>`
                        : '<span style="color:#ccc; font-size:11px;">Pendiente</span>';

                    const row = `
                        <tr>
                            <td class="center">${origenHtml}</td>
                            <td style="font-weight:600;">
                                ${rawName}
                                ${guiaLink} </td>
                            <td><span class="badge-agenda ${badgeClass}">${catName}</span></td>
                            <td><span class="status-dot ${dotClass}"></span> ${statusText}</td>
                            <td>${t.impacto_estimado}</td>
                            <td>${evidenciaLink}</td> <td class="action-cell">
                                <button class="action-btn" onclick="toggleDropdown(this)"><i class="fas fa-ellipsis-v"></i></button>
                                <div class="dropdown-menu">
                                    <button class="dropdown-item" onclick="openStatusModal(${t.id}, '${t.estatus}')">
                                        <i class="fas fa-sync-alt"></i> Cambiar Estatus
                                    </button>
                                    <button class="dropdown-item" onclick="openUploadModal(${t.id}, '${safeName}')">
                                        <i class="fas fa-upload"></i> Subir Evidencia
                                    </button>
                                </div>
                            </td>
                        </tr>`;
                    
                    tableBody.innerHTML += row;
                });
                    
                    updateChart(agendaCounts, tasks.length);
                }
            } catch (error) {
                console.error(error);
                tableBody.innerHTML = `<tr><td colspan="7" style="color:red; text-align:center; padding:20px;">
                    <i class="fas fa-exclamation-triangle"></i> Error al cargar datos: ${error.message}
                </td></tr>`;
                mainTable.style.display = 'table';
                emptyState.style.display = 'none';
            } finally { 
                setTimeout(() => { loadingOverlay.classList.remove('active'); }, 300);
            }
        }

        async function loadTheme(mes) {
            try {
                // Se envía el año también para ser precisos
                const res = await fetch(`../../api/dirigentes/obtener_tema.php?estado=${encodeURIComponent(currentEstado)}&mes=${encodeURIComponent(mes)}&anio=${currentYear}`);
                const data = await res.json();
                document.getElementById('themeDisplayMonth').textContent = data.tema || "Sin tema asignado";
            } catch(e) { console.error(e); }
        }

        // --- FUNCIÓN DE NAVEGACIÓN RESTRINGIDA (Ene 2026 - Ene 2027) ---
        function changeMonth(dir) {
            const idx = meses.indexOf(currentMonth);
            let newIdx = idx + dir;
            let newYear = currentYear;

            // Calcular el cambio de año hipotético
            if(newIdx < 0) { 
                newIdx = 11; 
                newYear--; 
            } else if(newIdx > 11) { 
                newIdx = 0; 
                newYear++; 
            }

            // --- LÓGICA DE BLOQUEO DE FECHAS ---
            // 1. No permitir navegar antes de 2026
            if (newYear < 2026) return;
            
            // 2. No permitir navegar después de 2027
            if (newYear > 2027) return;

            // 3. Si estamos en 2027, solo permitir Enero (índice 0)
            if (newYear === 2027 && newIdx > 0) return;

            // Si pasa las validaciones, aplicar cambio
            currentYear = newYear;
            currentMonth = meses[newIdx];
            
            document.getElementById('currentMonthDisplay').textContent = `${currentMonth} ${currentYear}`;
            document.getElementById('assignMonth').value = currentMonth; // Sincronizar select del modal
            
            loadTasks();
        }

        // --- GRÁFICA (Chart.js) ---
        function updateChart(counts, total) {
            const ctx = document.getElementById('agendaChart').getContext('2d');
            const dataValues = [counts['Mediática'], counts['Temática'], counts['Social'], counts['Política']];
            const dataPercentages = total > 0 ? dataValues.map(v => ((v/total)*100).toFixed(1)) : [0,0,0,0];

            if (agendaChartInstance) {
                agendaChartInstance.destroy();
            }

            agendaChartInstance = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Mediática', 'Temática', 'Social', 'Política'],
                    datasets: [{
                        label: '% de Carga',
                        data: dataPercentages,
                        backgroundColor: ['#e91e63', '#9c27b0', '#4caf50', '#2196f3'],
                        borderWidth: 0,
                        borderRadius: 6,
                        barPercentage: 0.5
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: { beginAtZero: true, max: 100, grid: { display: false }, ticks: { display: false } },
                        y: { grid: { display: false }, ticks: { font: { weight: '700', size: 12 } } }
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `${context.raw}%`;
                                }
                            }
                        }
                    }
                }
            });
        }

        // ==========================================
        // MODALES Y CRUD
        // ==========================================
        
        // 1. Asignar Tarea
        const assignModal = document.getElementById('assignTaskModal');
        function openAssignModal() { 
            document.getElementById('assignMonth').value = currentMonth; // Preseleccionar mes actual
            assignModal.classList.add('open'); 
        }
        function closeAssignModal() { assignModal.classList.remove('open'); document.getElementById('assignTaskForm').reset(); }

        async function handleAssignTask(e) {
            e.preventDefault();
            const btn = e.target.querySelector('button'); const originalText = btn.innerHTML;
            btn.innerHTML = 'Guardando...'; btn.disabled = true;
            const select = document.getElementById('assignTaskSelect');
            const agenda = select.options[select.selectedIndex].getAttribute('data-agenda');
            const formData = new FormData(e.target);
            formData.append('categoria', agenda);
            // Agregamos el año explícitamente para guardar en 2026/2027
            formData.append('anio', currentYear); 

            try {
                const res = await fetch('../../api/dirigentes/procesar_asignacion.php', { method: 'POST', body: formData });
                const json = await res.json();
                if(json.success) { closeAssignModal(); loadTasks(); alert('Actividad agregada correctamente.'); } 
                else { alert('Error: ' + json.message); }
            } catch(err) { alert('Error de conexión'); }
            finally { btn.innerHTML = originalText; btn.disabled = false; }
        }

        // 2. Subir Reporte
        const uploadModal = document.getElementById('uploadReportModal');
        function openUploadModal(id, nombre) {
            document.getElementById('uploadTaskId').value = id;
            document.getElementById('uploadTaskName').value = nombre;
            uploadModal.classList.add('open');
        }
        function closeUploadModal() { uploadModal.classList.remove('open'); document.getElementById('uploadReportForm').reset(); }

        async function handleUploadReport(e) {
            e.preventDefault();
            const formData = new FormData(e.target);
            try {
                const res = await fetch('../../api/dirigentes/subir_reporte.php', { method: 'POST', body: formData });
                const json = await res.json();
                if(json.success) { closeUploadModal(); loadTasks(); alert('Reporte subido con éxito.'); } 
                else { alert(json.message); }
            } catch(err) { alert('Error al subir archivo'); }
        }

        // 3. Cambiar Estatus
        const statusModal = document.getElementById('statusModal');
        function openStatusModal(id, estatusActual) {
            document.getElementById('statusTaskId').value = id;
            document.getElementById('newStatusSelect').value = estatusActual;
            statusModal.classList.add('open');
        }
        function closeStatusModal() { statusModal.classList.remove('open'); }

        async function handleChangeStatus(e) {
            e.preventDefault();
            const formData = new FormData();
            formData.append('id', document.getElementById('statusTaskId').value);
            formData.append('estatus', document.getElementById('newStatusSelect').value);
            try {
                const res = await fetch('../../api/dirigentes/cambiar_estatus.php', { method: 'POST', body: formData });
                const json = await res.json();
                if(json.success) { closeStatusModal(); loadTasks(); } 
                else { alert(json.message); }
            } catch(err) { alert('Error de conexión'); }
        }

        // --- UTILIDADES ---
        function toggleDropdown(btn) {
            document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.remove('show'));
            btn.nextElementSibling.classList.toggle('show');
            event.stopPropagation();
        }
        window.onclick = function(event) {
            if (!event.target.matches('.action-btn') && !event.target.matches('.action-btn i')) {
                document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.remove('show'));
            }
            if (event.target == assignModal) closeAssignModal();
            if (event.target == uploadModal) closeUploadModal();
            if (event.target == statusModal) closeStatusModal();
        }
    </script>
</body>
</html>