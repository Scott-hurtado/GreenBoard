<?php
// Habilitar visualización de errores para debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// 1. Carga del sistema y seguridad
require_once __DIR__ . '/../../includes/bootstrap.php';

// Configuración Google Sheets
define('GOOGLE_SHEET_ID', '1PB10SQiyOBoRoD7lIoA4dhSC9ITLvixMP5VrOoIRQ-E');
define('GOOGLE_SHEET_NAME', 'PULL-2');

$rol = $_SESSION['user_role'] ?? '';
$permitidos = ['admin', 'administrador', 'superadmin', 'super admin'];

if (!in_array($rol, $permitidos)) {
    header('Location: /login-admin.php');
    exit();
}

// Función para obtener datos de Google Sheets
function getGoogleSheetData() {
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
                continue;
            }
            
            // Mapeo de columnas según la estructura del Google Sheet PULL 2:
            // A = FECHA
            // B = CELULAR
            // C = NOMBRE
            // D = APELLIDO
            // E = SECCCÍÓN
            // F = ¿En que rango de edad te encuentras?
            // G = ¿Sabes tu Sección Electoral?
            // H = La situación actual de México me parece:
            // I = ¿Cómo veo el futuro de mi país?
            // J = Al ver a México en estos momentos:
            // K = Hablando del acceso a la salud para ti y tu familia:
            // L = En cuanto al costo de vida:
            // M = ¿Consideras que un partido político debe defender como prioridad la vida desde su concepción y la familia?
            
            // Asegurarse de que tengamos al menos 13 columnas (A-M)
            $row = array_pad($row, 13, '');
            
            $asistentes[] = [
                'fecha' => trim($row[0] ?? ''),
                'celular' => trim($row[1] ?? ''),
                'nombre' => trim($row[2] ?? ''),
                'apellido' => trim($row[3] ?? ''),
                'seccion' => trim($row[4] ?? ''),
                'edad_rango' => trim($row[5] ?? ''),
                'sabes_seccion' => trim($row[6] ?? ''),
                'situacion_mexico' => trim($row[7] ?? ''),
                'futuro_pais' => trim($row[8] ?? ''),
                'ver_mexico_momentos' => trim($row[9] ?? ''),
                'acceso_salud' => trim($row[10] ?? ''),
                'costo_vida' => trim($row[11] ?? ''),
                'partido_vida_familia' => trim($row[12] ?? '')
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
    'edad_rangos' => [],
    'sabes_seccion' => [],
    'situacion_mexico' => [],
    'futuro_pais' => [],
    'ver_mexico_momentos' => [],
    'acceso_salud' => [],
    'costo_vida' => [],
    'partido_vida_familia' => []
];

foreach ($asistentes as $asistente) {
    // Rango de edad
    if (!empty($asistente['edad_rango'])) {
        $stats['edad_rangos'][$asistente['edad_rango']] = ($stats['edad_rangos'][$asistente['edad_rango']] ?? 0) + 1;
    }
    
    // Sabes sección electoral
    if (!empty($asistente['sabes_seccion'])) {
        $stats['sabes_seccion'][$asistente['sabes_seccion']] = ($stats['sabes_seccion'][$asistente['sabes_seccion']] ?? 0) + 1;
    }
    
    // Situación de México
    if (!empty($asistente['situacion_mexico'])) {
        $stats['situacion_mexico'][$asistente['situacion_mexico']] = ($stats['situacion_mexico'][$asistente['situacion_mexico']] ?? 0) + 1;
    }
    
    // Futuro del país
    if (!empty($asistente['futuro_pais'])) {
        $stats['futuro_pais'][$asistente['futuro_pais']] = ($stats['futuro_pais'][$asistente['futuro_pais']] ?? 0) + 1;
    }
    
    // Ver a México en estos momentos
    if (!empty($asistente['ver_mexico_momentos'])) {
        $stats['ver_mexico_momentos'][$asistente['ver_mexico_momentos']] = ($stats['ver_mexico_momentos'][$asistente['ver_mexico_momentos']] ?? 0) + 1;
    }
    
    // Acceso a la salud
    if (!empty($asistente['acceso_salud'])) {
        $stats['acceso_salud'][$asistente['acceso_salud']] = ($stats['acceso_salud'][$asistente['acceso_salud']] ?? 0) + 1;
    }
    
    // Costo de vida
    if (!empty($asistente['costo_vida'])) {
        $stats['costo_vida'][$asistente['costo_vida']] = ($stats['costo_vida'][$asistente['costo_vida']] ?? 0) + 1;
    }
    
    // Partido político y vida/familia
    if (!empty($asistente['partido_vida_familia'])) {
        $stats['partido_vida_familia'][$asistente['partido_vida_familia']] = ($stats['partido_vida_familia'][$asistente['partido_vida_familia']] ?? 0) + 1;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
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
            background: #ffffff;
            padding-left: 280px;
            min-height: 100vh;
        }

        .content-area {
            padding: 40px;
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
        @media (max-width: 991px) {
            body {
                padding-left: 0; /* Quita el espacio del sidebar en móvil */
            }

            .content-area {
                padding: 20px 15px; /* Reduce el padding del contenido */
            }

            .page-header {
                padding: 20px;
            }

            .page-header h1 {
                font-size: 24px;
                flex-wrap: wrap;
            }

            /* Ajustar grid de gráficas para que no se desborden */
            .charts-grid {
                grid-template-columns: 1fr; /* Una sola columna */
                gap: 20px;
            }

            .chart-container {
                position: relative;
                width: 100%; /* Asegura que no exceda el ancho */
            }

            /* Ajustes para la tabla y cabecera de tabla */
            .table-header {
                flex-direction: column;
                align-items: stretch;
                gap: 15px;
            }

            .search-box {
                width: 100%;
            }

            /* IMPORTANTE: Hace que la tabla tenga scroll interno horizontal */
            .table-section {
                padding: 15px;
                overflow-x: auto; 
            }

            .modern-table {
                min-width: 800px; /* Fuerza un ancho mínimo para que aparezca el scroll */
            }

            /* Ajustes del Modal */
            .modal-content {
                width: 95%;
                margin: 10px;
                padding: 20px;
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
                Dashboard - Encuesta 2026-1
            </h1>
            <p>Análisis de respuestas del registro</p>
        </div>

        <div class="stats-row">
            <div class="stat-card">
                <h3>Total Registros</h3>
                <div class="value"><?php echo $stats['total']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Rangos de Edad</h3>
                <div class="value"><?php echo count($stats['edad_rangos']); ?></div>
            </div>
        </div>

        <div class="charts-grid">
            <div class="chart-card">
                <h2><i class="fas fa-users"></i> Rango de Edad</h2>
                <div class="chart-container">
                    <canvas id="edadChart"></canvas>
                </div>
            </div>

            <div class="chart-card">
                <h2><i class="fas fa-vote-yea"></i> ¿Sabes tu Sección Electoral?</h2>
                <div class="chart-container">
                    <canvas id="seccionChart"></canvas>
                </div>
            </div>

            <div class="chart-card">
                <h2><i class="fas fa-flag"></i> Situación Actual de México</h2>
                <div class="chart-container">
                    <canvas id="situacionChart"></canvas>
                </div>
            </div>

            <div class="chart-card">
                <h2><i class="fas fa-star"></i> Futuro del País</h2>
                <div class="chart-container">
                    <canvas id="futuroChart"></canvas>
                </div>
            </div>

            <div class="chart-card">
                <h2><i class="fas fa-eye"></i> Ver a México Hoy</h2>
                <div class="chart-container">
                    <canvas id="mexicoChart"></canvas>
                </div>
            </div>

            <div class="chart-card">
                <h2><i class="fas fa-heartbeat"></i> Acceso a la Salud</h2>
                <div class="chart-container">
                    <canvas id="saludChart"></canvas>
                </div>
            </div>

            <div class="chart-card">
                <h2><i class="fas fa-dollar-sign"></i> Costo de Vida</h2>
                <div class="chart-container">
                    <canvas id="costoChart"></canvas>
                </div>
            </div>

            <div class="chart-card">
                <h2><i class="fas fa-home"></i> Partido y Vida/Familia</h2>
                <div class="chart-container">
                    <canvas id="partidoChart"></canvas>
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
            'rgba(45, 122, 62, 0.6)',
            'rgba(76, 175, 80, 0.6)',
            'rgba(139, 195, 74, 0.6)',
            'rgba(33, 150, 243, 0.6)',
            'rgba(255, 193, 7, 0.6)',
            'rgba(244, 67, 54, 0.6)',
            'rgba(156, 39, 176, 0.6)'
        ];

        // Gráfica: Edad
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

        // Gráfica: Sección Electoral
        new Chart(document.getElementById('seccionChart'), {
            type: 'doughnut',
            data: {
                labels: Object.keys(statsData.sabes_seccion),
                datasets: [{
                    data: Object.values(statsData.sabes_seccion),
                    backgroundColor: backgroundColors
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
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

        // Gráfica: Situación de México
        new Chart(document.getElementById('situacionChart'), {
            type: 'bar',
            data: {
                labels: Object.keys(statsData.situacion_mexico),
                datasets: [{
                    label: 'Respuestas',
                    data: Object.values(statsData.situacion_mexico),
                    backgroundColor: backgroundColors[5],
                    borderColor: chartColors.danger,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    datalabels: {
                        anchor: 'end',
                        align: 'top',
                        color: '#f44336',
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

        // Gráfica: Futuro del País
        new Chart(document.getElementById('futuroChart'), {
            type: 'pie',
            data: {
                labels: Object.keys(statsData.futuro_pais),
                datasets: [{
                    data: Object.values(statsData.futuro_pais),
                    backgroundColor: backgroundColors
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
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

        // Gráfica: Ver a México
        new Chart(document.getElementById('mexicoChart'), {
            type: 'bar',
            data: {
                labels: Object.keys(statsData.ver_mexico_momentos),
                datasets: [{
                    label: 'Respuestas',
                    data: Object.values(statsData.ver_mexico_momentos),
                    backgroundColor: backgroundColors[3],
                    borderColor: chartColors.info,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    datalabels: {
                        anchor: 'end',
                        align: 'top',
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

        // Gráfica: Acceso a la Salud
        new Chart(document.getElementById('saludChart'), {
            type: 'bar',
            data: {
                labels: Object.keys(statsData.acceso_salud),
                datasets: [{
                    label: 'Respuestas',
                    data: Object.values(statsData.acceso_salud),
                    backgroundColor: backgroundColors[1],
                    borderColor: chartColors.secondary,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    datalabels: {
                        anchor: 'end',
                        align: 'top',
                        color: '#4caf50',
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

        // Gráfica: Costo de Vida
        new Chart(document.getElementById('costoChart'), {
            type: 'bar',
            data: {
                labels: Object.keys(statsData.costo_vida),
                datasets: [{
                    label: 'Respuestas',
                    data: Object.values(statsData.costo_vida),
                    backgroundColor: backgroundColors[4],
                    borderColor: chartColors.warning,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: {
                    legend: { display: false },
                    datalabels: {
                        anchor: 'end',
                        align: 'right',
                        color: '#ffc107',
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

        // Gráfica: Partido y Vida/Familia
        new Chart(document.getElementById('partidoChart'), {
            type: 'doughnut',
            data: {
                labels: Object.keys(statsData.partido_vida_familia),
                datasets: [{
                    data: Object.values(statsData.partido_vida_familia),
                    backgroundColor: backgroundColors
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
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
                    <strong>Rango de Edad:</strong>
                    <span>${asistente.edad_rango}</span>
                </div>
                <div class="detail-item">
                    <strong>¿Sabes tu Sección Electoral?</strong>
                    <span>${asistente.sabes_seccion}</span>
                </div>
                <div class="detail-item">
                    <strong>La situación actual de México me parece:</strong>
                    <span>${asistente.situacion_mexico}</span>
                </div>
                <div class="detail-item">
                    <strong>¿Cómo veo el futuro de mi país?</strong>
                    <span>${asistente.futuro_pais}</span>
                </div>
                <div class="detail-item">
                    <strong>Al ver a México en estos momentos:</strong>
                    <span>${asistente.ver_mexico_momentos}</span>
                </div>
                <div class="detail-item">
                    <strong>Hablando del acceso a la salud para ti y tu familia:</strong>
                    <span>${asistente.acceso_salud}</span>
                </div>
                <div class="detail-item">
                    <strong>En cuanto al costo de vida:</strong>
                    <span>${asistente.costo_vida}</span>
                </div>
                <div class="detail-item">
                    <strong>¿Consideras que un partido político debe defender como prioridad la vida desde su concepción y la familia?</strong>
                    <span>${asistente.partido_vida_familia}</span>
                </div>
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