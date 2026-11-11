<?php
// Habilitar visualización de errores para debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// 1. Carga del sistema y seguridad
require_once __DIR__ . '/../../includes/bootstrap.php';

// Configuración Google Sheets
define('GOOGLE_SHEET_ID', '1PB10SQiyOBoRoD7lIoA4dhSC9ITLvixMP5VrOoIRQ-E');
define('GOOGLE_SHEET_NAME', 'PULL-EVENTO');

$rol = $_SESSION['user_role'] ?? '';
$permitidos = ['admin', 'administrador', 'superadmin', 'super admin'];

if (!in_array($rol, $permitidos)) {
    header('Location: /login-admin.php');
    exit();
}

// Variables globales para los encabezados de las columnas N, O, P
$headerN = '';
$headerO = '';
$headerP = '';

// Función para obtener datos de Google Sheets
function getGoogleSheetData() {
    global $headerN, $headerO, $headerP;
    
    $sheetId = GOOGLE_SHEET_ID;
    $sheetName = GOOGLE_SHEET_NAME;
    
    // URL para obtener TODAS las columnas del sheet (A-P)
    $url = "https://docs.google.com/spreadsheets/d/{$sheetId}/gviz/tq?tqx=out:csv&sheet={$sheetName}";
    
    $asistentes = [];
    
    try {
        // Configurar contexto para la petición
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]
        ]);
        
        $csvData = @file_get_contents($url, false, $context);
        
        if ($csvData === false) {
            throw new Exception("No se pudo conectar con Google Sheets");
        }
        
        // Parsear CSV
        $lines = explode("\n", $csvData);
        $headers = null;
        
        foreach ($lines as $index => $line) {
            if (empty(trim($line))) continue;
            
            // Parsear la línea CSV manualmente para manejar comillas
            $row = str_getcsv($line);
            
            // Primera fila son los encabezados
            if ($index === 0) {
                $headers = $row;
                // Guardar los encabezados de las columnas N, O, P (índices 13, 14, 15)
                $headerN = $row[13] ?? '¿Cuál consideras que es el principal problema que enfrenta tu municipio o localidad?';
                $headerO = $row[14] ?? '¿Te gustaría compartir alguna idea o propuesta sobre cómo podría solucionarse?';
                $headerP = $row[15] ?? '¿Cómo pondrías "de moda" al verde en tu estado?';
                continue;
            }
            
            // Asegurarse de que tengamos al menos 16 columnas (A-P)
            $row = array_pad($row, 16, '');
            
            $asistentes[] = [
                'fecha' => trim($row[0] ?? ''),
                'celular' => trim($row[1] ?? ''),
                'nombre' => trim($row[2] ?? ''),
                'apellido' => trim($row[3] ?? ''),
                'seccion' => trim($row[4] ?? ''),
                'estado' => trim($row[5] ?? ''),
                'edad_rango' => trim($row[6] ?? ''),
                'sabes_seccion' => trim($row[7] ?? ''),
                'temas_interes' => trim($row[8] ?? ''),
                'proteger_animales' => trim($row[9] ?? ''),
                'energias_limpias' => trim($row[10] ?? ''),
                'mejorar_colonia' => trim($row[11] ?? ''),
                'identificacion_partido' => trim($row[12] ?? ''),
                'problema_municipio' => trim($row[13] ?? ''),
                'propuesta_solucion' => trim($row[14] ?? ''),
                'otra_pregunta' => trim($row[15] ?? '')
            ];
        }
        
    } catch (Exception $e) {
        error_log("Error al obtener datos de Google Sheets: " . $e->getMessage());
        $asistentes = [];
    }
    
    return $asistentes;
}

$asistentes = getGoogleSheetData();

// Calcular estadísticas para las gráficas
$stats = [
    'total' => count($asistentes),
    'estados' => [],
    'edad_rangos' => [],
    'temas_interes' => [],
    'proteger_animales' => [],
    'energias_limpias' => [],
    'mejorar_colonia' => [],
    'identificacion' => [],
    'problema_municipio' => [],
    'propuesta_solucion' => [],
    'otra_pregunta' => []
];

foreach ($asistentes as $asistente) {
    // Estados
    if (!empty($asistente['estado'])) {
        $stats['estados'][$asistente['estado']] = ($stats['estados'][$asistente['estado']] ?? 0) + 1;
    }
    
    // Rango de edad
    if (!empty($asistente['edad_rango'])) {
        $stats['edad_rangos'][$asistente['edad_rango']] = ($stats['edad_rangos'][$asistente['edad_rango']] ?? 0) + 1;
    }
    
    // Temas de interés
    if (!empty($asistente['temas_interes'])) {
        $stats['temas_interes'][$asistente['temas_interes']] = ($stats['temas_interes'][$asistente['temas_interes']] ?? 0) + 1;
    }
    
    // Proteger animales
    if (!empty($asistente['proteger_animales'])) {
        $stats['proteger_animales'][$asistente['proteger_animales']] = ($stats['proteger_animales'][$asistente['proteger_animales']] ?? 0) + 1;
    }
    
    // Energías limpias
    if (!empty($asistente['energias_limpias'])) {
        $stats['energias_limpias'][$asistente['energias_limpias']] = ($stats['energias_limpias'][$asistente['energias_limpias']] ?? 0) + 1;
    }
    
    // Mejorar colonia
    if (!empty($asistente['mejorar_colonia'])) {
        $stats['mejorar_colonia'][$asistente['mejorar_colonia']] = ($stats['mejorar_colonia'][$asistente['mejorar_colonia']] ?? 0) + 1;
    }
    
    // Identificación partido
    if (!empty($asistente['identificacion_partido'])) {
        $stats['identificacion'][$asistente['identificacion_partido']] = ($stats['identificacion'][$asistente['identificacion_partido']] ?? 0) + 1;
    }
    
    // Problema municipio
    if (!empty($asistente['problema_municipio'])) {
        $stats['problema_municipio'][$asistente['problema_municipio']] = ($stats['problema_municipio'][$asistente['problema_municipio']] ?? 0) + 1;
    }
    
    // Propuesta solución
    if (!empty($asistente['propuesta_solucion'])) {
        $stats['propuesta_solucion'][$asistente['propuesta_solucion']] = ($stats['propuesta_solucion'][$asistente['propuesta_solucion']] ?? 0) + 1;
    }
    
    // Otra pregunta
    if (!empty($asistente['otra_pregunta'])) {
        $stats['otra_pregunta'][$asistente['otra_pregunta']] = ($stats['otra_pregunta'][$asistente['otra_pregunta']] ?? 0) + 1;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Pull de Evento</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #2d7a3e;
            --primary-dark: #1f5a2d;
            --light: #f0f7f0;
            --dark: #1a1a1a;
            --white: #ffffff;
            --light-green: #4caf50;
            --accent-green: #8bc34a;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f7f6;
            margin: 0;
            padding: 10px;
            overflow-x: hidden;
            width: 100%;
            box-sizing: border-box;
        }

.content-area {
    padding: 40px;
    margin-left: 280px; /* Ancho del sidebar expandido */
    transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Cuando el sidebar está colapsado */
.content-area.expanded-content {
    margin-left: 80px; /* Ancho del sidebar colapsado */
}

/* En móvil, sin margen izquierdo */
@media (max-width: 768px) {
    .content-area {
        margin-left: 0 !important;
    }
}

        .page-header {
            background: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        .page-header h1 {
            color: var(--dark);
            font-size: 32px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .page-header h1 i {
            color: var(--light-green);
        }

        .page-header p {
            color: #666;
            font-size: 16px;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card h3 {
            color: #666;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .stat-card .value {
            color: var(--primary);
            font-size: 36px;
            font-weight: 700;
        }

        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }

        .chart-card {
            background: white;
            padding: 25px;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }

        .chart-card h2 {
            color: var(--dark);
            font-size: 18px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .chart-card h2 i {
            color: var(--light-green);
        }

        .chart-container {
            position: relative;
            height: 300px;
        }

        .table-section {
            background: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow-x: auto;
            width: 100%;
            -webkit-overflow-scrolling: touch;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .table-header h2 {
            color: var(--dark);
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .search-box {
            position: relative;
            width: 300px;
        }

        .search-box input {
            width: 100%;
            padding: 12px 40px 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--light-green);
        }

        .search-box i {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }

        .modern-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .modern-table thead {
            background: var(--light);
        }

        .modern-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--dark);
            font-size: 14px;
            white-space: nowrap;
        }

        .modern-table td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            color: #555;
        }

        .modern-table tbody tr {
            transition: background 0.2s;
        }

        .modern-table tbody tr:hover {
            background: #f9f9f9;
        }

        .action-btn {
            background: var(--light-green);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .action-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .whatsapp-btn {
            background: #25D366;
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 8px;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
        }

        .whatsapp-btn:hover {
            background: #128C7E;
            transform: translateY(-2px);
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .modal.open {
            opacity: 1;
        }

        .modal-content {
            background: white;
            padding: 40px;
            border-radius: 20px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            transform: scale(0.9);
            transition: transform 0.3s;
        }

        .modal.open .modal-content {
            transform: scale(1);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--light);
        }

        .modal-header h2 {
            color: var(--dark);
            font-size: 24px;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 28px;
            color: #666;
            cursor: pointer;
            transition: color 0.3s;
        }

        .close-btn:hover {
            color: var(--light-green);
        }

        .detail-item {
            margin-bottom: 20px;
            padding: 15px;
            background: var(--light);
            border-radius: 10px;
        }

        .detail-item strong {
            display: block;
            color: var(--primary);
            margin-bottom: 8px;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .detail-item span {
            color: var(--dark);
            font-size: 15px;
        }

        .open-questions-section {
            margin-top: 40px;
        }

        .open-question-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        .open-question-card h3 {
            color: var(--dark);
            font-size: 20px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--light-green);
        }

        .answers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 15px;
        }

        .answer-box {
            background: var(--light);
            padding: 15px;
            border-radius: 10px;
            border-left: 3px solid var(--light-green);
        }

        .answer-box p {
            color: var(--dark);
            font-size: 14px;
            line-height: 1.6;
        }

        /* Media Queries para Responsive */
        @media (max-width: 768px) {
            body {
                padding: 5px;
            }

            .content-area {
                padding: 15px;
            }

            .page-header {
                padding: 20px;
            }

            .page-header h1 {
                font-size: 22px;
                flex-direction: column;
                align-items: flex-start;
            }

            .page-header p {
                font-size: 14px;
            }

            .stats-row {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .charts-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .chart-card {
                padding: 15px;
            }

            .chart-container {
                height: 250px;
            }

            .table-section {
                padding: 15px;
            }

            .table-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }

            .table-header h2 {
                font-size: 18px;
            }

            .search-box {
                width: 100%;
            }

            .modern-table {
                font-size: 12px;
            }

            .modern-table th,
            .modern-table td {
                padding: 10px 8px;
                font-size: 12px;
            }

            .action-btn,
            .whatsapp-btn {
                padding: 6px 10px;
                font-size: 11px;
            }

            .modal-content {
                padding: 20px;
                width: 95%;
            }

            .modal-header h2 {
                font-size: 18px;
            }

            .open-question-card {
                padding: 20px;
            }

            .open-question-card h3 {
                font-size: 16px;
            }

            .answers-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .page-header h1 {
                font-size: 18px;
            }

            .stat-card .value {
                font-size: 28px;
            }

            .chart-container {
                height: 220px;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/components/sidebar_admin.php'; ?>

    <div class="content-area">
        <div class="page-header">
            <h1>
                <i class="fas fa-chart-bar"></i>
                Dashboard - Taller Construyendo el Triunfo
            </h1>
            <p>Análisis de respuestas del evento de registro vía WhatsApp</p>
        </div>

        <div class="stats-row">
            <div class="stat-card">
                <h3>Total Registros</h3>
                <div class="value"><?php echo $stats['total']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Estados</h3>
                <div class="value"><?php echo count($stats['estados']); ?></div>
            </div>
            <div class="stat-card">
                <h3>Temas Identificados</h3>
                <div class="value"><?php echo count($stats['temas_interes']); ?></div>
            </div>
        </div>

        <div class="charts-grid">
            <div class="chart-card">
                <h2><i class="fas fa-map-marker-alt"></i> Distribución por Estado</h2>
                <div class="chart-container">
                    <canvas id="estadosChart"></canvas>
                </div>
            </div>

            <div class="chart-card">
                <h2><i class="fas fa-users"></i> Rango de Edad</h2>
                <div class="chart-container">
                    <canvas id="edadChart"></canvas>
                </div>
            </div>

            <div class="chart-card">
                <h2><i class="fas fa-heart"></i> Temas de Interés</h2>
                <div class="chart-container">
                    <canvas id="temasChart"></canvas>
                </div>
            </div>

            <div class="chart-card">
                <h2><i class="fas fa-paw"></i> Proteger Animales</h2>
                <div class="chart-container">
                    <canvas id="animalesChart"></canvas>
                </div>
            </div>

            <div class="chart-card">
                <h2><i class="fas fa-sun"></i> Energías Limpias</h2>
                <div class="chart-container">
                    <canvas id="energiasChart"></canvas>
                </div>
            </div>

            <div class="chart-card">
                <h2><i class="fas fa-home"></i> Prioridades en Colonias</h2>
                <div class="chart-container">
                    <canvas id="coloniaChart"></canvas>
                </div>
            </div>

            <div class="chart-card">
                <h2><i class="fas fa-id-badge"></i> Identificación con el Partido Verde</h2>
                <div class="chart-container">
                    <canvas id="identificacionChart"></canvas>
                </div>
            </div>
        </div>

        <div class="table-section">
            <div class="table-header">
                <h2><i class="fas fa-table"></i> Base de Datos de Asistentes</h2>
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Buscar...">
                </div>
            </div>

            <table class="modern-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Celular</th>
                        <th>Nombre</th>
                        <th>Apellido</th>
                        <th>Sección</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <?php foreach ($asistentes as $index => $asistente): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($asistente['fecha']); ?></td>
                        <td><?php echo htmlspecialchars($asistente['celular']); ?></td>
                        <td><?php echo htmlspecialchars($asistente['nombre']); ?></td>
                        <td><?php echo htmlspecialchars($asistente['apellido']); ?></td>
                        <td><?php echo htmlspecialchars($asistente['seccion']); ?></td>
                        <td><?php echo htmlspecialchars($asistente['estado']); ?></td>
                        <td>
                            <button class="action-btn" onclick='mostrarDetalles(<?php echo json_encode($asistente); ?>)'>
                                <i class="fas fa-eye"></i> Detalles
                            </button>
                            <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $asistente['celular']); ?>" 
                               target="_blank" 
                               class="whatsapp-btn">
                                <i class="fab fa-whatsapp"></i> WhatsApp
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="open-questions-section">
            <div class="open-question-card">
                <h3><?php echo htmlspecialchars($headerN); ?></h3>
                <div class="answers-grid">
                    <?php foreach ($stats['problema_municipio'] as $respuesta => $count): ?>
                        <div class="answer-box">
                            <p><?php echo htmlspecialchars($respuesta); ?> (<?php echo $count; ?>)</p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="open-question-card">
                <h3><?php echo htmlspecialchars($headerO); ?></h3>
                <div class="answers-grid">
                    <?php foreach ($stats['propuesta_solucion'] as $respuesta => $count): ?>
                        <div class="answer-box">
                            <p><?php echo htmlspecialchars($respuesta); ?> (<?php echo $count; ?>)</p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if (!empty($stats['otra_pregunta'])): ?>
            <div class="open-question-card">
                <h3><?php echo htmlspecialchars($headerP); ?></h3>
                <div class="answers-grid">
                    <?php foreach ($stats['otra_pregunta'] as $respuesta => $count): ?>
                        <div class="answer-box">
                            <p><?php echo htmlspecialchars($respuesta); ?> (<?php echo $count; ?>)</p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div id="detallesModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Detalles del Asistente</h2>
                <button class="close-btn" onclick="cerrarDetalles()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="detallesContent"></div>
        </div>
    </div>

    <script>
        const statsData = <?php echo json_encode($stats); ?>;

        const chartColors = {
            primary: 'rgba(45, 122, 62, 1)',
            secondary: 'rgba(76, 175, 80, 1)',
            accent: 'rgba(139, 195, 74, 1)',
            info: 'rgba(33, 150, 243, 1)',
            warning: 'rgba(255, 193, 7, 1)',
            danger: 'rgba(244, 67, 54, 1)',
            purple: 'rgba(156, 39, 176, 1)'
        };

        const backgroundColors = [
            'rgba(45, 122, 62, 0.8)',
            'rgba(76, 175, 80, 0.8)',
            'rgba(139, 195, 74, 0.8)',
            'rgba(33, 150, 243, 0.8)',
            'rgba(255, 193, 7, 0.8)',
            'rgba(244, 67, 54, 0.8)',
            'rgba(156, 39, 176, 0.8)'
        ];

        // Gráfica: Distribución por Estado
        new Chart(document.getElementById('estadosChart'), {
            type: 'bar',
            data: {
                labels: Object.keys(statsData.estados),
                datasets: [{
                    label: 'Asistentes',
                    data: Object.values(statsData.estados),
                    backgroundColor: backgroundColors[0],
                    borderColor: chartColors.primary,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    datalabels: {
                        anchor: 'end',
                        align: 'top',
                        color: chartColors.primary,
                        font: {
                            weight: 'bold',
                            size: 12
                        },
                        formatter: function(value) {
                            return value;
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            },
            plugins: [ChartDataLabels]
        });

        // Gráfica: Rango de Edad
        new Chart(document.getElementById('edadChart'), {
            type: 'doughnut',
            data: {
                labels: Object.keys(statsData.edad_rangos),
                datasets: [{
                    data: Object.values(statsData.edad_rangos),
                    backgroundColor: backgroundColors
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            font: {
                                size: 12
                            }
                        }
                    },
                    datalabels: {
                        color: '#fff',
                        font: {
                            weight: 'bold',
                            size: 14
                        },
                        formatter: function(value, context) {
                            const total = context.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                            const percentage = ((value / total) * 100).toFixed(1);
                            return value + '\n(' + percentage + '%)';
                        }
                    }
                }
            },
            plugins: [ChartDataLabels]
        });

        // Gráfica: Temas de Interés
        new Chart(document.getElementById('temasChart'), {
            type: 'bar',
            data: {
                labels: Object.keys(statsData.temas_interes),
                datasets: [{
                    label: 'Respuestas',
                    data: Object.values(statsData.temas_interes),
                    backgroundColor: backgroundColors[0],
                    borderColor: chartColors.primary,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    datalabels: {
                        anchor: 'end',
                        align: 'top',
                        color: chartColors.primary,
                        font: {
                            weight: 'bold',
                            size: 12
                        },
                        formatter: function(value) {
                            return value;
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            },
            plugins: [ChartDataLabels]
        });

        // Gráfica: Proteger Animales
        new Chart(document.getElementById('animalesChart'), {
            type: 'doughnut',
            data: {
                labels: Object.keys(statsData.proteger_animales),
                datasets: [{
                    data: Object.values(statsData.proteger_animales),
                    backgroundColor: backgroundColors
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            font: {
                                size: 12
                            }
                        }
                    },
                    datalabels: {
                        color: '#fff',
                        font: {
                            weight: 'bold',
                            size: 14
                        },
                        formatter: function(value, context) {
                            const total = context.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                            const percentage = ((value / total) * 100).toFixed(1);
                            return value + '\n(' + percentage + '%)';
                        }
                    }
                }
            },
            plugins: [ChartDataLabels]
        });

        // Gráfica: Energías Limpias
        new Chart(document.getElementById('energiasChart'), {
            type: 'doughnut',
            data: {
                labels: Object.keys(statsData.energias_limpias),
                datasets: [{
                    data: Object.values(statsData.energias_limpias),
                    backgroundColor: backgroundColors
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            font: {
                                size: 12
                            }
                        }
                    },
                    datalabels: {
                        color: '#fff',
                        font: {
                            weight: 'bold',
                            size: 14
                        },
                        formatter: function(value, context) {
                            const total = context.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                            const percentage = ((value / total) * 100).toFixed(1);
                            return value + '\n(' + percentage + '%)';
                        }
                    }
                }
            },
            plugins: [ChartDataLabels]
        });

        // Gráfica: Mejorar Colonia
        new Chart(document.getElementById('coloniaChart'), {
            type: 'bar',
            data: {
                labels: Object.keys(statsData.mejorar_colonia),
                datasets: [{
                    label: 'Prioridades',
                    data: Object.values(statsData.mejorar_colonia),
                    backgroundColor: backgroundColors[3],
                    borderColor: chartColors.info,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: {
                    legend: {
                        display: false
                    },
                    datalabels: {
                        anchor: 'end',
                        align: 'right',
                        color: '#2196f3',
                        font: {
                            weight: 'bold',
                            size: 12
                        },
                        formatter: function(value) {
                            return value;
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            },
            plugins: [ChartDataLabels]
        });

        // Gráfica: Identificación
        new Chart(document.getElementById('identificacionChart'), {
            type: 'doughnut',
            data: {
                labels: Object.keys(statsData.identificacion),
                datasets: [{
                    data: Object.values(statsData.identificacion),
                    backgroundColor: backgroundColors
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            font: {
                                size: 12
                            }
                        }
                    },
                    datalabels: {
                        color: '#fff',
                        font: {
                            weight: 'bold',
                            size: 14
                        },
                        formatter: function(value, context) {
                            const total = context.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                            const percentage = ((value / total) * 100).toFixed(1);
                            return value + '\n(' + percentage + '%)';
                        }
                    }
                }
            },
            plugins: [ChartDataLabels]
        });

        // Función para mostrar detalles
        function mostrarDetalles(asistente) {
            const headerN = <?php echo json_encode($headerN); ?>;
            const headerO = <?php echo json_encode($headerO); ?>;
            const headerP = <?php echo json_encode($headerP); ?>;
            
            const content = `
                <div class="detail-item">
                    <strong>Fecha de Registro:</strong>
                    <span>${asistente.fecha}</span>
                </div>
                <div class="detail-item">
                    <strong>Celular:</strong>
                    <span>${asistente.celular}</span>
                </div>
                <div class="detail-item">
                    <strong>Nombre Completo:</strong>
                    <span>${asistente.nombre} ${asistente.apellido}</span>
                </div>
                <div class="detail-item">
                    <strong>Sección Electoral:</strong>
                    <span>${asistente.seccion}</span>
                </div>
                <div class="detail-item">
                    <strong>Estado:</strong>
                    <span>${asistente.estado}</span>
                </div>
                <div class="detail-item">
                    <strong>Rango de Edad:</strong>
                    <span>${asistente.edad_rango}</span>
                </div>
                <div class="detail-item">
                    <strong>¿Sabes tu Sección Electoral?</strong>
                    <span>${asistente.sabes_seccion}</span>
                </div>
                <div class="detail-item">
                    <strong>Temas de Interés:</strong>
                    <span>${asistente.temas_interes}</span>
                </div>
                <div class="detail-item">
                    <strong>¿Proteger más a los animales?</strong>
                    <span>${asistente.proteger_animales}</span>
                </div>
                <div class="detail-item">
                    <strong>¿Invertir en energías limpias?</strong>
                    <span>${asistente.energias_limpias}</span>
                </div>
                <div class="detail-item">
                    <strong>Mejorar en la Colonia:</strong>
                    <span>${asistente.mejorar_colonia}</span>
                </div>
                <div class="detail-item">
                    <strong>Identificación con el Partido Verde:</strong>
                    <span>${asistente.identificacion_partido}</span>
                </div>
                <div class="detail-item">
                    <strong>${headerN}</strong>
                    <span>${asistente.problema_municipio}</span>
                </div>
                <div class="detail-item">
                    <strong>${headerO}</strong>
                    <span>${asistente.propuesta_solucion}</span>
                </div>
                ${asistente.otra_pregunta ? `
                <div class="detail-item">
                    <strong>${headerP}</strong>
                    <span>${asistente.otra_pregunta}</span>
                </div>
                ` : ''}
                <div style="margin-top: 25px;">
                    <a href="https://wa.me/${asistente.celular.replace(/[^0-9]/g, '')}" 
                       target="_blank" 
                       class="whatsapp-btn" 
                       style="display: inline-flex; padding: 12px 20px; font-size: 15px;">
                        <i class="fab fa-whatsapp"></i> Enviar WhatsApp
                    </a>
                </div>
            `;

            document.getElementById('detallesContent').innerHTML = content;
            const modal = document.getElementById('detallesModal');
            modal.style.display = 'flex';
            setTimeout(() => {
                modal.classList.add('open');
                document.body.style.overflow = 'hidden';
            }, 10);
        }

        function cerrarDetalles() {
            const modal = document.getElementById('detallesModal');
            modal.classList.remove('open');
            document.body.style.overflow = '';
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300);
        }

        // Cerrar modal al hacer click fuera
        document.getElementById('detallesModal').addEventListener('click', function(e) {
            if (e.target === this) {
                cerrarDetalles();
            }
        });

        // Búsqueda en la tabla
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('#tableBody tr');

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    </script>
</body>
</html>