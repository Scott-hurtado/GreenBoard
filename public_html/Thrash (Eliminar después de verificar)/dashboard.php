<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Proyecto Verde</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background: #f0f7f0;
            /* Es clave que el overflow horizontal esté oculto para una mejor experiencia */
            overflow-x: hidden; 
        }

        /* AJUSTES CLAVE PARA ACOMODAR EL CONTENIDO JUNTO AL SIDEBAR FIJO */
        .container {
            /* Se eliminó 'width: 100%' para evitar que el margen de 280px cause overflow horizontal. 
               El contenedor ahora toma automáticamente el ancho restante. */
            margin: 0;
            background: white;
            min-height: 100vh;
            
            /* Espacio para el sidebar fijo (280px) */
            margin-left: 280px; 
            transition: margin-left 0.3s ease;
        }
        
        /* Regla para cuando el sidebar colapsa (asumiendo que el sidebar tiene la clase 'collapsed') */
        .sidebar.collapsed ~ .container {
            margin-left: 80px;
        }

        /* Header */
        .dashboard-header {
            background: linear-gradient(135deg, #2d7a3e 0%, #1f5a2d 100%);
            padding: 40px 60px;
            color: white;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .header-title h1 {
            font-size: 42px;
            font-weight: 900;
            margin-bottom: 10px;
        }

        .header-title p {
            font-size: 16px;
            opacity: 0.9;
        }

        .header-actions {
            display: flex;
            gap: 15px;
        }

        .btn {
            padding: 12px 25px;
            border: 2px solid white;
            background: transparent;
            color: white;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            border-radius: 5px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn:hover {
            background: white;
            color: #2d7a3e;
        }

        .btn-primary {
            background: white;
            color: #2d7a3e;
        }

        .btn-primary:hover {
            background: rgba(255, 255, 255, 0.9);
        }

        /* Stats Overview */
        .stats-overview {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-top: -50px;
            padding: 0 60px;
            position: relative;
            z-index: 10;
        }

        .stat-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            animation: fadeInUp 0.8s ease forwards, float 3s ease-in-out infinite;
            opacity: 0;
        }

        .stat-card:nth-child(1) { 
            animation-delay: 0.1s, 0s;
        }
        .stat-card:nth-child(2) { 
            animation-delay: 0.2s, 0.5s;
        }
        .stat-card:nth-child(3) { 
            animation-delay: 0.3s, 1s;
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

        @keyframes float {
            0%, 100% {
                transform: translateY(0px);
            }
            50% {
                transform: translateY(-10px);
            }
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(45, 122, 62, 0.15);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #2d7a3e 0%, #1f5a2d 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            animation: rotateIn 0.6s ease forwards;
            opacity: 0;
        }

        @keyframes rotateIn {
            from {
                opacity: 0;
                transform: rotate(-180deg) scale(0);
            }
            to {
                opacity: 1;
                transform: rotate(0) scale(1);
            }
        }

        .stat-card:hover .stat-icon {
            animation: pulse 0.6s ease infinite;
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.1);
            }
        }

        .stat-trend {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .stat-trend.up {
            background: #e8f5e9;
            color: #2d7a3e;
        }

        .stat-trend.down {
            background: #ffebee;
            color: #d32f2f;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 10px;
        }

        .stat-value {
            font-size: 36px;
            font-weight: 900;
            color: #1a1a1a;
            margin-bottom: 5px;
            animation: countUp 2s ease forwards;
        }

        @keyframes countUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .stat-description {
            color: #999;
            font-size: 13px;
        }

        /* Main Content */
        .dashboard-content {
            padding: 60px;
        }

        .section-title {
            font-size: 28px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .section-title::before {
            content: '';
            width: 5px;
            height: 35px;
            background: linear-gradient(135deg, #2d7a3e 0%, #1f5a2d 100%);
            border-radius: 3px;
        }

        /* Charts Section */
        .charts-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 40px;
        }

        .chart-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            animation: slideInFromBottom 0.8s ease forwards;
            opacity: 0;
            transform: translateY(50px);
        }

        @keyframes slideInFromBottom {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .chart-title {
            font-size: 18px;
            font-weight: 700;
            color: #1a1a1a;
        }

        .chart-filter {
            display: flex;
            gap: 10px;
        }

        .filter-btn {
            padding: 6px 15px;
            border: 1px solid #e0e0e0;
            background: white;
            color: #666;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .filter-btn:hover,
        .filter-btn.active {
            background: #2d7a3e;
            color: white;
            border-color: #2d7a3e;
        }

        .chart-container {
            position: relative;
            height: 300px;
        }

        /* Territory Grid */
        .territory-section {
            margin-bottom: 40px;
        }

        .territory-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .territory-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            border-left: 4px solid #2d7a3e;
            transition: all 0.3s ease;
            animation: slideInLeft 0.6s ease forwards;
            opacity: 0;
            transform: translateX(-50px);
        }

        .territory-card:nth-child(1) { animation-delay: 0.1s; }
        .territory-card:nth-child(2) { animation-delay: 0.2s; }
        .territory-card:nth-child(3) { animation-delay: 0.3s; }
        .territory-card:nth-child(4) { animation-delay: 0.4s; }
        .territory-card:nth-child(5) { animation-delay: 0.5s; }
        .territory-card:nth-child(6) { animation-delay: 0.6s; }

        @keyframes slideInLeft {
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .territory-card:hover {
            transform: translateX(10px) scale(1.02);
            box-shadow: 0 8px 25px rgba(45, 122, 62, 0.15);
        }

        .territory-name {
            font-size: 18px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 15px;
        }

        .territory-stats {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .territory-stat {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 14px;
        }

        .territory-stat-label {
            color: #666;
        }

        .territory-stat-value {
            font-weight: 700;
            color: #2d7a3e;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 10px;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #2d7a3e 0%, #1f5a2d 100%);
            border-radius: 10px;
            transition: width 1s ease;
        }

        /* Meta Section */
        .meta-card {
            background: linear-gradient(135deg, #2d7a3e 0%, #1f5a2d 100%);
            padding: 40px;
            border-radius: 15px;
            color: white;
            text-align: center;
            box-shadow: 0 10px 30px rgba(45, 122, 62, 0.3);
            animation: fadeInScale 0.8s ease forwards, breathe 4s ease-in-out infinite;
            opacity: 0;
        }

        @keyframes fadeInScale {
            from {
                opacity: 0;
                transform: scale(0.9);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        @keyframes breathe {
            0%, 100% {
                box-shadow: 0 10px 30px rgba(45, 122, 62, 0.3);
            }
            50% {
                box-shadow: 0 15px 40px rgba(45, 122, 62, 0.5);
            }
        }

        .meta-title {
            font-size: 16px;
            font-weight: 500;
            margin-bottom: 20px;
            opacity: 0.9;
            letter-spacing: 1px;
        }

        .meta-progress {
            font-size: 72px;
            font-weight: 900;
            margin-bottom: 10px;
        }

        .meta-label {
            font-size: 18px;
            opacity: 0.9;
        }

        .meta-bar {
            width: 100%;
            height: 12px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            overflow: hidden;
            margin-top: 25px;
        }

        .meta-bar-fill {
            height: 100%;
            background: white;
            border-radius: 10px;
            transition: width 1.5s ease;
        }

        /* Recent Activity */
        .activity-section {
            margin-top: 40px;
        }

        .activity-list {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .activity-item {
            padding: 20px 30px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.3s ease;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-item:hover {
            background: #f9f9f9;
        }

        .activity-icon {
            width: 45px;
            height: 45px;
            background: #e8f5e9;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #2d7a3e;
            font-size: 18px;
        }

        .activity-content {
            flex: 1;
        }

        .activity-title {
            font-size: 15px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 5px;
        }

        .activity-description {
            font-size: 13px;
            color: #666;
        }

        .activity-time {
            font-size: 13px;
            color: #999;
            white-space: nowrap;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .dashboard-header,
            .dashboard-content {
                padding: 40px 30px;
            }

            .stats-overview {
                padding: 0 30px;
                grid-template-columns: 1fr;
            }

            .charts-grid {
                grid-template-columns: 1fr;
            }

            .territory-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }
            
            /* En pantallas más pequeñas, el sidebar debe colapsar o el contenido principal debe ocupar todo el espacio */
            .container {
                margin-left: 0; /* Asegura el ancho completo en pantallas de tablet */
            }
        }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                align-items: flex-start;
                gap: 20px;
            }

            .header-title h1 {
                font-size: 32px;
            }

            .section-title {
                font-size: 22px;
            }

            .stat-value {
                font-size: 28px;
            }

            .meta-progress {
                font-size: 56px;
            }

            .dashboard-header,
            .dashboard-content {
                padding: 20px 15px;
            }

            .stats-overview {
                padding: 0 15px;
            }
        }
    </style>
</head>
<body>
    <?php include 'components/sidebar_admin.php'; ?>
    <div class="container">
        <div class="dashboard-header">
            <div class="header-content">
                <div class="header-title">
                    <h1>Dashboard</h1>
                    <p>Panel de control y estadísticas de la red</p>
                </div>
                <div class="header-actions">
                    <a href="#" class="btn">
                        <i class="fas fa-download"></i> Exportar
                    </a>
                    <a href="index.php" class="btn btn-primary">
                        <i class="fas fa-home"></i> Inicio
                    </a>
                </div>
            </div>
        </div>

        <div class="stats-overview">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-trend up">
                        <i class="fas fa-arrow-up"></i> 12.5%
                    </div>
                </div>
                <div class="stat-label">Total de Miembros Activos</div>
                <div class="stat-value">12,847</div>
                <div class="stat-description">+1,432 este mes</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="stat-trend up">
                        <i class="fas fa-arrow-up"></i> 8.3%
                    </div>
                </div>
                <div class="stat-label">Nuevos Miembros (Mes)</div>
                <div class="stat-value">1,432</div>
                <div class="stat-description">365 esta semana</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-trend up">
                        <i class="fas fa-arrow-up"></i> 15.2%
                    </div>
                </div>
                <div class="stat-label">Crecimiento Mensual</div>
                <div class="stat-value">15.2%</div>
                <div class="stat-description">Promedio últimos 3 meses: 13.8%</div>
            </div>
        </div>

        <div class="dashboard-content">
            <h2 class="section-title">Tamaño de la Red</h2>
            <div class="charts-grid">
                <div class="chart-card">
                    <div class="chart-header">
                        <div class="chart-title">Crecimiento de Miembros</div>
                        <div class="chart-filter">
                            <button class="filter-btn active" data-period="week">Semana</button>
                            <button class="filter-btn" data-period="month">Mes</button>
                            <button class="filter-btn" data-period="year">Año</button>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="growthChart"></canvas>
                    </div>
                </div>

                <div class="chart-card">
                    <div class="chart-header">
                        <div class="chart-title">Distribución por Rol</div>
                    </div>
                    <div class="chart-container">
                        <canvas id="distributionChart"></canvas>
                    </div>
                </div>
            </div>

            <h2 class="section-title">Expansión Territorial</h2>
            
            <div class="meta-card">
                <div class="meta-title">META TERRITORIAL ALCANZADA</div>
                <div class="meta-progress">78.5%</div>
                <div class="meta-label">47 de 60 zonas cubiertas</div>
                <div class="meta-bar">
                    <div class="meta-bar-fill" style="width: 78.5%;"></div>
                </div>
            </div>

            <div class="territory-section" style="margin-top: 40px;">
                <div class="territory-grid">
                    <div class="territory-card">
                        <div class="territory-name">Zona Centro</div>
                        <div class="territory-stats">
                            <div class="territory-stat">
                                <span class="territory-stat-label">Miembros:</span>
                                <span class="territory-stat-value">2,458</span>
                            </div>
                            <div class="territory-stat">
                                <span class="territory-stat-label">Cobertura:</span>
                                <span class="territory-stat-value">92%</span>
                            </div>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: 92%;"></div>
                        </div>
                    </div>

                    <div class="territory-card">
                        <div class="territory-name">Zona Norte</div>
                        <div class="territory-stats">
                            <div class="territory-stat">
                                <span class="territory-stat-label">Miembros:</span>
                                <span class="territory-stat-value">1,876</span>
                            </div>
                            <div class="territory-stat">
                                <span class="territory-stat-label">Cobertura:</span>
                                <span class="territory-stat-value">85%</span>
                            </div>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: 85%;"></div>
                        </div>
                    </div>

                    <div class="territory-card">
                        <div class="territory-name">Zona Sur</div>
                        <div class="territory-stats">
                            <div class="territory-stat">
                                <span class="territory-stat-label">Miembros:</span>
                                <span class="territory-stat-value">2,145</span>
                            </div>
                            <div class="territory-stat">
                                <span class="territory-stat-label">Cobertura:</span>
                                <span class="territory-stat-value">88%</span>
                            </div>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: 88%;"></div>
                        </div>
                    </div>

                    <div class="territory-card">
                        <div class="territory-name">Zona Este</div>
                        <div class="territory-stats">
                            <div class="territory-stat">
                                <span class="territory-stat-label">Miembros:</span>
                                <span class="territory-stat-value">1,623</span>
                            </div>
                            <div class="territory-stat">
                                <span class="territory-stat-label">Cobertura:</span>
                                <span class="territory-stat-value">76%</span>
                            </div>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: 76%;"></div>
                        </div>
                    </div>

                    <div class="territory-card">
                        <div class="territory-name">Zona Oeste</div>
                        <div class="territory-stats">
                            <div class="territory-stat">
                                <span class="territory-stat-label">Miembros:</span>
                                <span class="territory-stat-value">1,934</span>
                            </div>
                            <div class="territory-stat">
                                <span class="territory-stat-label">Cobertura:</span>
                                <span class="territory-stat-value">81%</span>
                            </div>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: 81%;"></div>
                        </div>
                    </div>

                    <div class="territory-card">
                        <div class="territory-name">Zona Metropolitana</div>
                        <div class="territory-stats">
                            <div class="territory-stat">
                                <span class="territory-stat-label">Miembros:</span>
                                <span class="territory-stat-value">2,811</span>
                            </div>
                            <div class="territory-stat">
                                <span class="territory-stat-label">Cobertura:</span>
                                <span class="territory-stat-value">94%</span>
                            </div>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: 94%;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="chart-card" style="margin-top: 30px;">
                <div class="chart-header">
                    <div class="chart-title">Miembros por Zona</div>
                </div>
                <div class="chart-container" style="height: 400px;">
                    <canvas id="territoryChart"></canvas>
                </div>
            </div>

            <h2 class="section-title" style="margin-top: 60px;">Actividad Reciente</h2>
            <div class="activity-list">
                <div class="activity-item">
                    <div class="activity-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-title">Nuevos registros en Zona Centro</div>
                        <div class="activity-description">45 nuevos miembros se unieron esta semana</div>
                    </div>
                    <div class="activity-time">Hace 2 horas</div>
                </div>

                <div class="activity-item">
                    <div class="activity-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-title">Meta alcanzada en Zona Metropolitana</div>
                        <div class="activity-description">Se superó el 90% de cobertura territorial</div>
                    </div>
                    <div class="activity-time">Hace 5 horas</div>
                </div>

                <div class="activity-item">
                    <div class="activity-icon">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-title">Récord de crecimiento mensual</div>
                        <div class="activity-description">Mayor número de afiliaciones en un mes</div>
                    </div>
                    <div class="activity-time">Hace 1 día</div>
                </div>

                <div class="activity-item">
                    <div class="activity-icon">
                        <i class="fas fa-map-marked-alt"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-title">Nueva zona activa: Zona Este</div>
                        <div class="activity-description">Se activó la expansión en 3 nuevas colonias</div>
                    </div>
                    <div class="activity-time">Hace 2 días</div>
                </div>

                <div class="activity-item">
                    <div class="activity-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-title">Evento de voluntariado exitoso</div>
                        <div class="activity-description">234 voluntarios participaron en la jornada</div>
                    </div>
                    <div class="activity-time">Hace 3 días</div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        // Growth Chart
        const growthCtx = document.getElementById('growthChart').getContext('2d');
        const growthChart = new Chart(growthCtx, {
            type: 'line',
            data: {
                labels: ['Sem 1', 'Sem 2', 'Sem 3', 'Sem 4', 'Sem 5', 'Sem 6', 'Sem 7', 'Sem 8'],
                datasets: [{
                    label: 'Miembros Nuevos',
                    data: [245, 312, 287, 398, 421, 365, 445, 487],
                    borderColor: '#2d7a3e',
                    backgroundColor: 'rgba(45, 122, 62, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#2d7a3e',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: '#f0f0f0'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Distribution Chart
        const distributionCtx = document.getElementById('distributionChart').getContext('2d');
        const distributionChart = new Chart(distributionCtx, {
            type: 'doughnut',
            data: {
                labels: ['Simpatizantes', 'Voluntarios', 'Promotores', 'Líderes de Zona'],
                datasets: [{
                    data: [6420, 3850, 1927, 650],
                    backgroundColor: [
                        '#2d7a3e',
                        '#4caf50',
                        '#81c784',
                        '#a5d6a7'
                    ],
                    borderWidth: 0
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
                    }
                }
            }
        });

        // Territory Chart
        const territoryCtx = document.getElementById('territoryChart').getContext('2d');
        const territoryChart = new Chart(territoryCtx, {
            type: 'bar',
            data: {
                labels: ['Zona Centro', 'Zona Norte', 'Zona Sur', 'Zona Este', 'Zona Oeste', 'Z. Metropolitana'],
                datasets: [{
                    label: 'Miembros',
                    data: [2458, 1876, 2145, 1623, 1934, 2811],
                    backgroundColor: 'rgba(45, 122, 62, 0.8)',
                    borderColor: '#2d7a3e',
                    borderWidth: 2,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: '#f0f0f0'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Filter buttons functionality
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                const period = this.dataset.period;
                updateGrowthChart(period);
            });
        });

        function updateGrowthChart(period) {
            let labels, data;
            
            if (period === 'week') {
                labels = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
                data = [42, 58, 51, 67, 73, 45, 89];
            } else if (period === 'month') {
                labels = ['Sem 1', 'Sem 2', 'Sem 3', 'Sem 4'];
                data = [854, 967, 1032, 1145];
            } else {
                labels = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
                data = [2340, 2567, 2891, 3234, 3567, 3845, 4123, 4456, 4789, 5123, 5456, 5789];
            }
            
            growthChart.data.labels = labels;
            growthChart.data.datasets[0].data = data;
            growthChart.update();
        }

        // Animate progress bars on load
        window.addEventListener('load', () => {
            document.querySelectorAll('.progress-fill').forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0';
                setTimeout(() => {
                    bar.style.width = width;
                }, 100);
            });

            // Animate meta bar
            const metaBar = document.querySelector('.meta-bar-fill');
            const metaWidth = metaBar.style.width;
            metaBar.style.width = '0';
            setTimeout(() => {
                metaBar.style.width = metaWidth;
            }, 500);
        });
    </script>
</body>
</html>