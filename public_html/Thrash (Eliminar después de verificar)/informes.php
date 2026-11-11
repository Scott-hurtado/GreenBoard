<?php
// Asegúrate de que 'db.php' esté en la ruta correcta
require_once 'db.php'; 

// Función para obtener todos los informes desde la base de datos
function getReports() {
    try {
        $db = getDB();
        $stmt = $db->query("SELECT id, titulo, creado_por, DATE_FORMAT(fecha_creacion, '%d/%b/%Y') AS fecha_creacion, tipo, estado, archivo_url FROM informes ORDER BY id DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error al cargar informes: " . $e->getMessage());
        return [];
    }
}

$informes = getReports();

// Mapeo para clases CSS
$tipo_class = [
    'mensual' => 'tipo-mensual',
    'trimestral' => 'tipo-trimestral',
    'anual' => 'tipo-anual',
    'especial' => 'tipo-especial'
];

$estado_class = [
    'borrador' => 'estado-borrador',
    'publicado' => 'estado-publicado',
    'archivado' => 'estado-archivado'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informes - Proyecto Verde</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
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
        
        .action-buttons {
            display: flex;
            gap: 10px;
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
        
        .btn-secondary-action {
            background: #e0e0e0;
            color: #333;
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
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .btn-secondary-action:hover {
            background: #d0d0d0;
            transform: translateY(-1px);
        }

        /* Content Area */
        .content-area {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
        }

        /* Table */
        .table-container {
            overflow-x: auto;
            width: 100%;
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
        
        .data-table th:last-child {
            text-align: center;
            width: 80px;
        }

        .data-table tbody tr {
            border-bottom: 1px solid #f0f0f0;
            transition: background-color 0.2s ease;
        }
        
        .data-table tbody tr.new-report {
            animation: newRowHighlight 2s ease-out;
        }
        
        @keyframes newRowHighlight {
            0% { background-color: #e6ffed; }
            100% { background-color: transparent; }
        }

        .data-table tbody tr:hover {
            background: #f8f9fa;
        }

        .data-table tbody tr:last-child {
            border-bottom: none;
        }

        .data-table td {
            padding: 20px;
            font-size: 15px;
            color: #1a1a1a;
            vertical-align: middle;
        }

        .data-table td:first-child {
            font-weight: 600;
        }
        
        .data-table td:last-child {
            text-align: center;
        }

        /* Badges */
        .badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }

        .badge.tipo-mensual { background: #e3f2fd; color: #1565c0; }
        .badge.tipo-trimestral { background: #f3e5f5; color: #7b1fa2; }
        .badge.tipo-anual { background: #fff3e0; color: #ef6c00; }
        .badge.tipo-especial { background: #fce4ec; color: #c2185b; }
        .badge.estado-borrador { background: #eeeeee; color: #616161; }
        .badge.estado-publicado { background: #e8f5e9; color: #2d7a3e; }
        .badge.estado-archivado { background: #fff8e1; color: #f57c00; }

        /* Dropdown corregido */
        .action-dropdown {
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
            position: fixed; 
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            min-width: 180px;
            z-index: 9999;
            
            display: none; 
            
            top: 0;
            left: 0;
            margin: 0;
        }
        
        .dropdown-menu.show {
            display: block;
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
        
        .dropdown-item.is-pdf a {
            color: #c2185b;
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

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 15px;
            width: 90%;
            max-width: 900px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            animation: modalSlideIn 0.3s ease;
        }
        
        #uploadModal .modal-content {
            max-width: 500px;
        }

        @keyframes modalSlideIn {
            from { transform: translateY(-50px) scale(0.95); opacity: 0; }
            to { transform: translateY(0) scale(1); opacity: 1; }
        }

        .modal-header {
            padding: 25px 30px;
            background: linear-gradient(135deg, #2d7a3e 0%, #1f5a2d 100%);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 15px 15px 0 0;
        }

        .modal-header h2 {
            font-size: 24px;
            font-weight: 600;
        }

        .close-modal {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .close-modal:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }

        .form-group label .required {
            color: #d32f2f;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .form-group input[type="file"] {
             padding: 10px 15px;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #2d7a3e;
            box-shadow: 0 0 0 3px rgba(45, 122, 62, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        /* Editor Container */
        .editor-container {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .editor-container:focus-within {
            border-color: #2d7a3e;
            box-shadow: 0 0 0 3px rgba(45, 122, 62, 0.1);
        }

        #editor {
            min-height: 300px;
        }

        .ql-toolbar {
            background: #f8f9fa;
            border: none !important;
            border-bottom: 1px solid #e0e0e0 !important;
        }

        .ql-container {
            border: none !important;
            font-size: 15px;
            font-family: 'Arial', sans-serif;
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
        @media (max-width: 1024px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .action-bar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 5px;
            }

            .search-box {
                max-width: 100%;
            }

            .modal-content {
                max-width: 95%;
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
            <i class="fas fa-file-alt"></i>
            Informes
        </h1>
        <p class="page-subtitle">Crea y gestiona informes detallados de actividades y resultados</p>

        <div class="action-bar">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Buscar informes...">
            </div>
            <div class="action-buttons">
                 <button class="btn-secondary-action" onclick="openUploadModal()">
                    <i class="fas fa-upload"></i>
                    Subir PDF
                </button>
                <button class="btn-primary" onclick="openReportModal()">
                    <i class="fas fa-plus"></i>
                    Nuevo Informe
                </button>
            </div>
        </div>

        <div class="content-area">
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Título del Informe</th>
                            <th>Creado Por</th>
                            <th>Fecha de Creación</th>
                            <th>Tipo</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <?php if (empty($informes)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: #999;">No hay informes registrados.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($informes as $informe): ?>
                            <tr id="report-row-<?php echo $informe['id']; ?>">
                                <td><?php echo $informe['id']; ?></td>
                                <td><?php echo htmlspecialchars($informe['titulo']); ?></td>
                                <td><?php echo htmlspecialchars($informe['creado_por']); ?></td>
                                <td><?php echo $informe['fecha_creacion']; ?></td>
                                <td><span class="badge <?php echo $tipo_class[strtolower($informe['tipo'])]; ?>"><?php echo ucfirst($informe['tipo']); ?></span></td>
                                <td><span class="badge <?php echo $estado_class[strtolower($informe['estado'])]; ?>"><?php echo ucfirst($informe['estado']); ?></span></td>
                                <td>
                                    <div class="action-dropdown">
                                        <button class="action-button" onclick="toggleDropdown(this, event)">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <div class="dropdown-menu">
                                            <?php if ($informe['archivo_url']): ?>
                                                <a href="<?php echo htmlspecialchars($informe['archivo_url']); ?>" 
                                                    target="_blank" 
                                                    class="dropdown-item" onclick="closeAllDropdowns()">
                                                    <i class="fas fa-eye"></i> Ver PDF
                                                </a>
                                                <a href="<?php echo htmlspecialchars($informe['archivo_url']); ?>" 
                                                   download="<?php echo htmlspecialchars(preg_replace('/[^a-z0-9]/i', '_', $informe['titulo'])) . '.pdf'; ?>" 
                                                   class="dropdown-item is-pdf" onclick="closeAllDropdowns()">
                                                    <i class="fas fa-download"></i> Descargar PDF
                                                </a>
                                            <?php else: ?>
                                                <a href="#" class="dropdown-item" onclick="viewReport(<?php echo $informe['id']; ?>); closeAllDropdowns()">
                                                    <i class="fas fa-eye"></i> Ver Contenido
                                                </a>
                                                <a href="#" class="dropdown-item" onclick="editReport(<?php echo $informe['id']; ?>); closeAllDropdowns()">
                                                    <i class="fas fa-edit"></i> Editar
                                                </a>
                                                <a href="#" class="dropdown-item" onclick="downloadReport(<?php echo $informe['id']; ?>); closeAllDropdowns()">
                                                    <i class="fas fa-download"></i> Descargar PDF
                                                </a>
                                            <?php endif; ?>
                                            <a href="#" class="dropdown-item" onclick="deleteReport(<?php echo $informe['id']; ?>); closeAllDropdowns()">
                                                <i class="fas fa-trash-alt"></i> Eliminar
                                            </a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <div class="modal" id="reportModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Nuevo Informe</h2>
                <button type="button" class="close-modal" onclick="closeModal('reportModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="reportForm">
                    <div class="form-group">
                        <label for="reportTitle">Título del Informe <span class="required">*</span></label>
                        <input type="text" id="reportTitle" required placeholder="Ej: Informe Mensual - Diciembre 2024">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="reportType">Tipo de Informe <span class="required">*</span></label>
                            <select id="reportType" required>
                                <option value="">Selecciona un tipo</option>
                                <option value="Mensual">Mensual</option>
                                <option value="Trimestral">Trimestral</option>
                                <option value="Anual">Anual</option>
                                <option value="Especial">Especial</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="reportStatus">Estado <span class="required">*</span></label>
                            <select id="reportStatus" required>
                                <option value="">Selecciona un estado</option>
                                <option value="Borrador">Borrador</option>
                                <option value="Publicado">Publicado</option>
                                <option value="Archivado">Archivado</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="reportAuthor">Creado Por <span class="required">*</span></label>
                        <input type="text" id="reportAuthor" required placeholder="Nombre del autor" value="Super Admin">
                    </div>

                    <div class="form-group">
                        <label>Contenido del Informe <span class="required">*</span></label>
                        <div class="editor-container">
                            <div id="editor"></div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('reportModal')">Cancelar</button>
                <button type="button" class="btn-primary" onclick="saveReport()">
                    <i class="fas fa-save"></i>
                    Guardar Informe
                </button>
            </div>
        </div>
    </div>
    
    <div class="modal" id="uploadModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="uploadModalTitle"><i class="fas fa-upload"></i> Subir Informe PDF</h2>
                <button type="button" class="close-modal" onclick="closeModal('uploadModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="uploadForm" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="pdfTitle">Título del Informe PDF <span class="required">*</span></label>
                        <input type="text" id="pdfTitle" name="pdfTitle" required placeholder="Ej: Reporte Trimestral Q1 2025 (PDF)">
                    </div>

                    <div class="form-group">
                        <label for="pdfFile">Seleccionar Archivo PDF <span class="required">*</span></label>
                        <input type="file" id="pdfFile" name="pdfFile" accept="application/pdf" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="pdfType">Tipo de Informe <span class="required">*</span></label>
                            <select id="pdfType" name="pdfType" required>
                                <option value="">Selecciona un tipo</option>
                                <option value="Mensual">Mensual</option>
                                <option value="Trimestral">Trimestral</option>
                                <option value="Anual">Anual</option>
                                <option value="Especial">Especial</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="pdfStatus">Estado <span class="required">*</span></label>
                            <select id="pdfStatus" name="pdfStatus" required>
                                <option value="">Selecciona un estado</option>
                                <option value="Borrador">Borrador</option>
                                <option value="Publicado">Publicado</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="pdfAuthor">Creado Por <span class="required">*</span></label>
                        <input type="text" id="pdfAuthor" name="pdfAuthor" required placeholder="Nombre del autor" value="Super Admin">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('uploadModal')">Cancelar</button>
                <button type="button" class="btn-primary" onclick="uploadPdf()">
                    <i class="fas fa-cloud-upload-alt"></i>
                    Subir y Guardar
                </button>
            </div>
        </div>
    </div>

    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>

    <script>
        // Mapeo para clases CSS
        const TYPE_CLASSES = {
            'mensual': 'tipo-mensual',
            'trimestral': 'tipo-trimestral',
            'anual': 'tipo-anual',
            'especial': 'tipo-especial'
        };

        const STATUS_CLASSES = {
            'borrador': 'estado-borrador',
            'publicado': 'estado-publicado',
            'archivado': 'estado-archivado'
        };
        
        let currentReportId = null; 

        // Función para generar la fila de la tabla
        function createReportRow(report) {
            const typeLower = report.tipo.toLowerCase();
            const statusLower = report.estado.toLowerCase();
            const isPdf = report.archivo_url !== null && report.archivo_url !== '';
            const sanitizedTitle = report.titulo.replace(/[^a-z0-9]/gi, '_'); // Para el nombre de archivo

            let actionsHtml;
            if (isPdf) {
                // MODIFICACIÓN JS: Enlace para VISUALIZAR
                actionsHtml = `
                    <a href="${report.archivo_url}" 
                       target="_blank"
                       class="dropdown-item" onclick="closeAllDropdowns()">
                        <i class="fas fa-eye"></i> Ver PDF
                    </a>
                    <a href="${report.archivo_url}" 
                       download="${sanitizedTitle}.pdf"
                       class="dropdown-item is-pdf" onclick="closeAllDropdowns()">
                        <i class="fas fa-download"></i> Descargar PDF
                    </a>
                `;
            } else {
                actionsHtml = `
                    <a href="#" class="dropdown-item" onclick="viewReport(${report.id}); closeAllDropdowns()">
                        <i class="fas fa-eye"></i> Ver Contenido
                    </a>
                    <a href="#" class="dropdown-item" onclick="editReport(${report.id}); closeAllDropdowns()">
                        <i class="fas fa-edit"></i> Editar
                    </a>
                    <a href="#" class="dropdown-item" onclick="downloadReport(${report.id}); closeAllDropdowns()">
                        <i class="fas fa-download"></i> Descargar PDF
                    </a>
                `;
            }
            
            actionsHtml += `
                <a href="#" class="dropdown-item" onclick="deleteReport(${report.id}); closeAllDropdowns()">
                    <i class="fas fa-trash-alt"></i> Eliminar
                </a>
            `;

            return `
                <tr id="report-row-${report.id}" class="new-report">
                    <td>${report.id}</td>
                    <td>${report.titulo}</td>
                    <td>${report.creado_por}</td>
                    <td>${report.fecha_creacion}</td>
                    <td><span class="badge ${TYPE_CLASSES[typeLower]}">${report.tipo}</span></td>
                    <td><span class="badge ${STATUS_CLASSES[statusLower]}">${report.estado}</span></td>
                    <td>
                        <div class="action-dropdown">
                            <button class="action-button" onclick="toggleDropdown(this, event)">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <div class="dropdown-menu">
                                ${actionsHtml}
                            </div>
                        </div>
                    </td>
                </tr>
            `;
        }

        function loadReports(newReportId = null) {
            $.ajax({
                url: 'procesar_informe.php',
                type: 'GET',
                data: { action: 'obtener_informes' },
                dataType: 'json',
                success: function(response) {
                    const $tableBody = $('#tableBody');
                    $tableBody.empty(); 

                    if (response.success && response.informes.length > 0) {
                        response.informes.forEach(report => {
                            $tableBody.append(createReportRow(report));
                        });
                        
                        if (newReportId) {
                            const $newRow = $(`#report-row-${newReportId}`);
                            if ($newRow.length) {
                                $newRow.addClass('new-report');
                                setTimeout(() => $newRow.removeClass('new-report'), 2000);
                            }
                        }
                    } else {
                        $tableBody.html('<tr><td colspan="7" style="text-align: center; color: #999;">No hay informes registrados.</td></tr>');
                    }
                },
                error: function() {
                    console.log('Error al cargar la lista de informes.');
                }
            });
        }
        
        let quill;
        function initializeEditor() {
            quill = new Quill('#editor', {
                theme: 'snow',
                placeholder: 'Escribe el contenido del informe aquí...',
                modules: {
                    toolbar: [
                        [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
                        ['bold', 'italic', 'underline', 'strike'],
                        [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                        ['link', 'image'],
                        [{ 'color': [] }, { 'background': [] }],
                        ['clean']
                    ]
                }
            });
        }
        
        // --- DROPDOWN LOGIC ---
        function toggleDropdown(button, event) {
            if (event) event.stopPropagation();

            const menu = button.nextElementSibling;
            const isShown = menu.classList.contains('show');
            
            closeAllDropdowns();

            if (!isShown) {
                const rect = button.getBoundingClientRect();
                
                menu.style.position = 'fixed';
                menu.style.top = (rect.bottom + 5) + 'px'; 
                
                const menuWidth = 180;
                menu.style.left = (rect.right - menuWidth) + 'px'; 
                
                menu.classList.add('show');
            }
        }

        function closeAllDropdowns() {
            document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                menu.classList.remove('show');
            });
        }

        document.addEventListener('click', (e) => {
            if (!e.target.closest('.action-dropdown') && !e.target.closest('.dropdown-menu')) {
                closeAllDropdowns();
            }
        });

        window.addEventListener('scroll', closeAllDropdowns, true); 

        // --- MODAL & FORM FUNCTIONS ---

        function openModal(modalId) {
            document.getElementById(modalId).classList.add('show');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }
        
        function openReportModal() {
            openModal('reportModal');
            document.getElementById('modalTitle').textContent = 'Nuevo Informe';
            document.getElementById('reportForm').reset();
            
            currentReportId = null;
            document.querySelector('#reportModal .modal-footer').style.display = 'flex';
            document.getElementById('reportTitle').disabled = false;
            document.getElementById('reportType').disabled = false;
            document.getElementById('reportStatus').disabled = false;
            document.getElementById('reportAuthor').disabled = false;

            if (quill) {
                quill.setText('');
                quill.enable();
            } else {
                initializeEditor();
            }
        }

        function openUploadModal() {
            openModal('uploadModal');
            document.getElementById('uploadForm').reset();
        }

        function saveReport() {
            const title = document.getElementById('reportTitle').value;
            const type = document.getElementById('reportType').value;
            const status = document.getElementById('reportStatus').value;
            const author = document.getElementById('reportAuthor').value;
            const content = quill.root.innerHTML;

            if (!title || !type || !status || !author || !content || content === '<p><br></p>') {
                alert('Por favor completa todos los campos requeridos');
                return;
            }
            
            const action = currentReportId ? 'actualizar_informe' : 'crear_informe';
            const data = {
                action: action,
                id: currentReportId, 
                title: title,
                type: type,
                status: status,
                author: author,
                content: content
            };

            $.ajax({
                url: 'procesar_informe.php',
                type: 'POST',
                data: data,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert(response.message);
                        closeModal('reportModal');
                        loadReports(response.id);
                        currentReportId = null; 
                    } else {
                        alert('Error al guardar informe: ' + response.message);
                    }
                },
                error: function() {
                    alert('Error de comunicación con el servidor al guardar informe.');
                }
            });
        }
        
        function uploadPdf() {
            const form = document.getElementById('uploadForm');
            
            const pdfFile = document.getElementById('pdfFile').files[0];
            const pdfTitle = document.getElementById('pdfTitle').value;
            const pdfType = document.getElementById('pdfType').value;
            const pdfStatus = document.getElementById('pdfStatus').value;
            const pdfAuthor = document.getElementById('pdfAuthor').value;

            if (!pdfFile) {
                alert('Por favor selecciona un archivo PDF.');
                return;
            }
            if (!pdfTitle || !pdfType || !pdfStatus || !pdfAuthor) {
                 alert('Por favor completa todos los campos requeridos.');
                 return;
            }

            const formData = new FormData(form);
            formData.append('action', 'subir_pdf');

            const btn = document.querySelector('#uploadModal .btn-primary');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Subiendo...';
            btn.disabled = true;

            $.ajax({
                url: 'procesar_informe.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert(response.message);
                        closeModal('uploadModal');
                        loadReports(response.id);
                    } else {
                        alert('Error al subir PDF: ' + response.message);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    alert('Error de comunicación con el servidor al subir PDF.');
                    console.error("AJAX Error: ", textStatus, errorThrown, jqXHR.responseText);
                },
                complete: function() {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }
            });
        }
        
        function viewReport(id) {
            currentReportId = id;
            openModal('reportModal');
            document.getElementById('modalTitle').textContent = 'Ver Informe #' + id;
            
            document.querySelector('#reportModal .modal-footer').style.display = 'none';
            document.getElementById('reportTitle').disabled = true;
            document.getElementById('reportType').disabled = true;
            document.getElementById('reportStatus').disabled = true;
            document.getElementById('reportAuthor').disabled = true;
            
            if (!quill) initializeEditor();
            quill.disable(); 

            $.ajax({
                url: 'procesar_informe.php',
                type: 'GET',
                data: { action: 'obtener_informe_por_id', id: id },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const report = response.report;
                        document.getElementById('reportTitle').value = report.titulo;
                        document.getElementById('reportType').value = report.tipo;
                        document.getElementById('reportStatus').value = report.estado;
                        document.getElementById('reportAuthor').value = report.creado_por;
                        quill.root.innerHTML = report.contenido;
                    } else {
                        alert('Error al cargar informe: ' + response.message);
                        closeModal('reportModal');
                    }
                },
                error: function() {
                    alert('Error de comunicación al cargar informe.');
                    closeModal('reportModal');
                }
            });
        }

        function editReport(id) {
            currentReportId = id;
            openModal('reportModal');
            document.getElementById('modalTitle').textContent = 'Editar Informe #' + id;

            document.querySelector('#reportModal .modal-footer').style.display = 'flex';
            document.getElementById('reportTitle').disabled = false;
            document.getElementById('reportType').disabled = false;
            document.getElementById('reportStatus').disabled = false;
            document.getElementById('reportAuthor').disabled = false;
            
            if (!quill) initializeEditor();
            quill.enable(); 

            $.ajax({
                url: 'procesar_informe.php',
                type: 'GET',
                data: { action: 'obtener_informe_por_id', id: id },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const report = response.report;
                        document.getElementById('reportTitle').value = report.titulo;
                        document.getElementById('reportType').value = report.tipo;
                        document.getElementById('reportStatus').value = report.estado;
                        document.getElementById('reportAuthor').value = report.creado_por;
                        quill.root.innerHTML = report.contenido;
                    } else {
                        alert('Error al cargar informe para edición: ' + response.message);
                        closeModal('reportModal');
                    }
                },
                error: function() {
                    alert('Error de comunicación al cargar informe.');
                    closeModal('reportModal');
                }
            });
        }
        
        // Función Descargar Informe (solo llamada para informes manuales)
        function downloadReport(id) {
            // Si es un informe manual, abrimos la vista de impresión en nueva pestaña.
            // El usuario podrá usar "Guardar como PDF" del navegador.
            window.open('procesar_informe.php?action=generar_pdf&id=' + id, '_blank');
        }

        function deleteReport(id) {
            if (confirm('¿Estás seguro de que deseas eliminar este informe? (ID: ' + id + ')')) {
                $.ajax({
                    url: 'procesar_informe.php',
                    type: 'POST',
                    data: { action: 'eliminar_informe', id: id },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            alert(response.message);
                            const row = document.getElementById(`report-row-${id}`);
                            if (row) {
                                row.style.display = 'none';
                                setTimeout(() => {
                                    row.remove();
                                    if ($('#tableBody tr').length === 0) {
                                        loadReports(); 
                                    }
                                }, 100);
                            }
                        } else {
                            alert('Error al eliminar: ' + response.message);
                        }
                    },
                    error: function() {
                        alert('Error de comunicación con el servidor al eliminar.');
                    }
                });
            }
        }

        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('#tableBody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (row.cells.length > 1) {
                    row.style.display = text.includes(searchTerm) ? '' : 'none';
                }
            });
        });

        document.getElementById('reportModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal('reportModal');
        });
        
        document.getElementById('uploadModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal('uploadModal');
        });

    </script>
</body>
</html>