<?php
// 1. Cargar el sistema (Subimos 2 niveles desde App/citizens/ hasta includes/)
require_once __DIR__ . '/../../includes/bootstrap.php';

// 2. Seguridad: Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['user_id'])) {
    header('Location: /iniciar-sesion.php');
    exit();
}

// 3. Obtener datos para el saludo
$rol = $_SESSION['user_role'] ?? '';
$rolesAdmin = ['admin', 'administrador', 'superadmin', 'super admin'];

if (in_array(strtolower($rol), array_map('strtolower', $rolesAdmin))) {
    // Si es admin, redirigir al dashboard de admin
    header('Location: /App/admin/dashboard.php');
    exit();
}
// Las funciones obtenerIniciales() y formatearRol() ya están definidas en sidebar.php
// Solo las usamos después de incluir la sidebar
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - Proyecto Verde</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* --- ESTILOS BASE --- */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Arial', sans-serif; 
            background: #f0f7f0; 
            overflow-x: hidden; 
        }

        /* --- CONTENEDOR PRINCIPAL CON MARGEN PARA SIDEBAR --- */
        .container { 
            min-height: 100vh; 
            background: white;
            transition: margin-left 0.3s ease;
            margin-left: 280px; /* Ancho fijo de la sidebar */
        }
        
        /* En móvil, sin margen */
        @media (max-width: 768px) {
            .container {
                margin-left: 0 !important;
            }
        }

        /* --- HEADER --- */
        .dashboard-header {
            background: linear-gradient(135deg, #2d7a3e 0%, #1f5a2d 100%);
            padding: 40px 60px 80px 60px;
            color: white;
            border-bottom-left-radius: 30px;
            border-bottom-right-radius: 30px;
        }

        .header-content { display: flex; justify-content: space-between; align-items: center; }
        .header-title h1 { font-size: 38px; font-weight: 900; margin-bottom: 5px; }
        .header-title p { font-size: 16px; opacity: 0.9; }
        
        /* Avatar del Usuario en el Header */
        .header-user-profile { display: flex; align-items: center; gap: 15px; }
        .header-user-avatar {
            width: 60px; height: 60px; background: white; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: #2d7a3e; font-size: 24px; font-weight: bold; border: 3px solid rgba(255,255,255,0.3);
        }
        .header-user-info div:first-child { font-weight: 700; font-size: 18px; }
        .header-user-info div:last-child { font-size: 14px; opacity: 0.8; }

        /* --- STATS GRID (Personal) --- */
        .stats-overview {
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;
            margin-top: -50px; padding: 0 60px; position: relative; z-index: 10;
        }

        .stat-card {
            background: white; padding: 25px; border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease; animation: fadeInUp 0.8s ease forwards; opacity: 0;
            display: flex; align-items: center; gap: 20px;
        }
        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stat-card:nth-child(3) { animation-delay: 0.3s; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 15px 40px rgba(45, 122, 62, 0.15); }

        .stat-icon-circle {
            width: 60px; height: 60px; border-radius: 50%;
            background: #e8f5e9; color: #2d7a3e;
            display: flex; align-items: center; justify-content: center; font-size: 24px;
        }
        .stat-data h3 { font-size: 28px; font-weight: 800; color: #1a1a1a; }
        .stat-data p { font-size: 14px; color: #666; font-weight: 500; }

        /* --- MAIN LAYOUT --- */
        .dashboard-content {
            padding: 40px 60px; display: grid; grid-template-columns: 2fr 1fr; gap: 40px;
        }

        .section-title {
            font-size: 22px; font-weight: 700; color: #1a1a1a; margin-bottom: 20px;
            display: flex; align-items: center; gap: 10px;
        }
        .section-title i { color: #2d7a3e; }

        /* --- CARDS GENERALES --- */
        .content-card {
            background: white; border-radius: 15px; padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05); margin-bottom: 30px;
            border: 1px solid #eee;
        }

        /* --- VOTING / PROPOSALS --- */
        .proposal-item {
            margin-bottom: 25px; padding-bottom: 25px; border-bottom: 1px solid #f0f0f0;
        }
        .proposal-item:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
        .proposal-tags { margin-bottom: 10px; }
        .tag {
            font-size: 11px; font-weight: 700; text-transform: uppercase;
            padding: 4px 10px; border-radius: 20px; background: #e8f5e9; color: #2d7a3e;
        }
        .proposal-title { font-size: 18px; font-weight: 700; margin-bottom: 8px; color: #1a1a1a; }
        .proposal-desc { font-size: 14px; color: #666; margin-bottom: 15px; line-height: 1.5; }
        
        .vote-bar-container { background: #f0f0f0; height: 30px; border-radius: 15px; position: relative; overflow: hidden; margin-bottom: 10px; }
        .vote-bar-fill { height: 100%; background: linear-gradient(90deg, #2d7a3e 0%, #4caf50 100%); width: 0%; border-radius: 15px; transition: width 1.5s ease; }
        .vote-text { position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; align-items: center; justify-content: space-between; padding: 0 15px; font-size: 12px; font-weight: 700; color: #fff; text-shadow: 0 1px 2px rgba(0,0,0,0.2); z-index: 2; }
        .vote-actions { display: flex; gap: 10px; }
        .btn-vote {
            padding: 8px 15px; border-radius: 6px; border: 1px solid #ddd; background: white;
            cursor: pointer; font-size: 13px; font-weight: 600; color: #555; transition: all 0.2s;
        }
        .btn-vote:hover, .btn-vote.active { background: #2d7a3e; color: white; border-color: #2d7a3e; }

        /* --- DIGITAL ID CARD --- */
        .digital-id {
            background: linear-gradient(135deg, #1a1a1a 0%, #333 100%);
            border-radius: 15px; padding: 25px; color: white; position: relative; overflow: hidden;
            box-shadow: 0 15px 30px rgba(0,0,0,0.2);
            animation: slideInRight 0.8s ease forwards;
        }
        .id-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 30px; }
        .id-logo { font-weight: 900; letter-spacing: 2px; color: #4caf50; }
        .id-chip { width: 40px; height: 30px; background: linear-gradient(135deg, #ffd700, #b8860b); border-radius: 5px; opacity: 0.8; }
        .id-user-name { font-size: 22px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 5px; }
        .id-role { font-size: 14px; color: #aaa; text-transform: uppercase; margin-bottom: 20px; }
        .id-footer { display: flex; justify-content: space-between; align-items: end; }
        .id-qr { width: 50px; height: 50px; background: white; padding: 3px; border-radius: 4px; }
        .id-number { font-family: monospace; letter-spacing: 2px; opacity: 0.5; font-size: 12px; }
        
        /* --- GAMIFICATION / BADGES --- */
        .badges-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; }
        .badge-item {
            text-align: center; padding: 15px 5px; border-radius: 10px; background: #f9fbf9;
            transition: all 0.3s; border: 1px solid transparent; cursor: help;
        }
        .badge-item:hover { background: white; border-color: #2d7a3e; transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .badge-icon { font-size: 24px; color: #ccc; margin-bottom: 8px; }
        .badge-item.earned .badge-icon { color: #f9a825; filter: drop-shadow(0 2px 4px rgba(249, 168, 37, 0.4)); }
        .badge-name { font-size: 11px; font-weight: 600; color: #555; }

        /* --- UPCOMING EVENTS --- */
        .event-card {
            display: flex; gap: 15px; border-bottom: 1px solid #f0f0f0; padding-bottom: 15px; margin-bottom: 15px;
        }
        .event-card:last-child { border-bottom: none; margin-bottom: 0; }
        .event-date {
            background: #e8f5e9; color: #2d7a3e; padding: 10px; border-radius: 8px;
            text-align: center; min-width: 60px; display: flex; flex-direction: column; justify-content: center;
        }
        .date-day { font-size: 20px; font-weight: 800; line-height: 1; }
        .date-month { font-size: 11px; text-transform: uppercase; font-weight: 700; }
        .event-info h4 { font-size: 15px; margin-bottom: 5px; color: #1a1a1a; }
        .event-loc { font-size: 12px; color: #666; margin-bottom: 8px; }
        .btn-join {
            padding: 5px 12px; font-size: 11px; background: #1a1a1a; color: white; border: none;
            border-radius: 4px; cursor: pointer; transition: background 0.3s;
        }
        .btn-join:hover { background: #2d7a3e; }

        /* ANIMATIONS */
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes slideInRight { from { opacity: 0; transform: translateX(50px); } to { opacity: 1; transform: translateX(0); } }

        /* RESPONSIVE */
        @media (max-width: 968px) {
            .dashboard-content { grid-template-columns: 1fr; padding: 40px 30px; }
            .stats-overview { grid-template-columns: 1fr; padding: 0 30px; }
            .dashboard-header { padding: 30px 30px 60px 30px; }
        }

        @media (max-width: 768px) {
            .dashboard-header { 
                padding: 80px 20px 60px 20px; /* Espacio para botón móvil */
            }
            .header-content {
                flex-direction: column;
                align-items: flex-start;
                gap: 20px;
            }
            .header-user-profile {
                width: 100%;
                justify-content: flex-start;
            }
            .header-user-info {
                text-align: left !important;
            }
        }
    </style>
</head>
<body>
    
    <?php include __DIR__ . '/../../includes/components/sidebar.php'; ?>
    
    <?php
    // Ahora que la sidebar está incluida, podemos usar las funciones
    $iniciales = obtenerIniciales($nombreUsuario);
    $rolFormateado = formatearRol($rolUsuario);
    
    // Obtener primer nombre para el saludo
    $nombrePartes = explode(' ', $nombreUsuario);
    $primerNombre = !empty($nombrePartes) ? $nombrePartes[0] : 'Ciudadano';
    ?>

   <div class="container">
        <div class="dashboard-header">
            <div class="header-content">
                <div class="header-title">
                    <h1>Hola, <?php echo htmlspecialchars($primerNombre); ?></h1>
                    <p>Bienvenido a tu panel de participación ciudadana</p>
                </div>
                <div class="header-user-profile">
                    <div class="header-user-info" style="text-align: right; color: white;">
                        <div><?php echo htmlspecialchars($nombreUsuario); ?></div>
                        <div><?php echo htmlspecialchars($rolFormateado); ?></div>
                    </div>
                    <div class="header-user-avatar"><?php echo $iniciales; ?></div>
                </div>
            </div>
        </div>

        <div class="stats-overview">
            <div class="stat-card">
                <div class="stat-icon-circle">
                    <i class="fas fa-hand-holding-heart"></i>
                </div>
                <div class="stat-data">
                    <h3>12</h3>
                    <p>Horas Voluntariado</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-circle">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-data">
                    <h3>5</h3>
                    <p>Eventos Asistidos</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-circle">
                    <i class="fas fa-leaf"></i>
                </div>
                <div class="stat-data">
                    <h3>850</h3>
                    <p>Puntos de Impacto</p>
                </div>
            </div>
        </div>

        <div class="dashboard-content">
            
            <div class="left-col">
                
                <div class="content-card">
                    <h2 class="section-title"><i class="fas fa-vote-yea"></i> Propuestas Activas</h2>
                    <p style="margin-bottom: 20px; font-size: 14px; color: #666;">Tu voz cuenta. Vota en las iniciativas prioritarias de este mes.</p>

                    <div class="proposal-item">
                        <div class="proposal-tags"><span class="tag">Ecología</span> <span class="tag">Urgente</span></div>
                        <div class="proposal-title">Reforestación del Parque Central</div>
                        <div class="proposal-desc">Propuesta para plantar 200 árboles nativos en la zona norte del parque para reducir la temperatura urbana.</div>
                        
                        <div class="vote-bar-container">
                            <div class="vote-text">
                                <span>Apoyo Actual</span>
                                <span>82%</span>
                            </div>
                            <div class="vote-bar-fill" style="width: 82%;"></div>
                        </div>
                        <div class="vote-actions">
                            <button class="btn-vote active"><i class="fas fa-thumbs-up"></i> Apoyo</button>
                            <button class="btn-vote"><i class="fas fa-comment"></i> Comentar</button>
                        </div>
                    </div>

                    <div class="proposal-item">
                        <div class="proposal-tags"><span class="tag">Infraestructura</span></div>
                        <div class="proposal-title">Ciclovía Segura Av. Reforma</div>
                        <div class="proposal-desc">Solicitud al ayuntamiento para instalar bolardos de protección en el carril de bicicletas existente.</div>
                        
                        <div class="vote-bar-container">
                            <div class="vote-text">
                                <span>Apoyo Actual</span>
                                <span>45%</span>
                            </div>
                            <div class="vote-bar-fill" style="width: 45%;"></div>
                        </div>
                        <div class="vote-actions">
                            <button class="btn-vote"><i class="fas fa-thumbs-up"></i> Apoyo</button>
                            <button class="btn-vote"><i class="fas fa-comment"></i> Comentar</button>
                        </div>
                    </div>
                </div>

                <div class="content-card">
                    <h2 class="section-title"><i class="fas fa-chart-pie"></i> Mi Impacto vs Comunidad</h2>
                    <div style="height: 250px;">
                        <canvas id="impactChart"></canvas>
                    </div>
                </div>

            </div>

            <div class="right-col">
                
                <div class="digital-id">
                    <div class="id-header">
                        <div class="id-logo">PROYECTO VERDE</div>
                        <div class="id-chip"></div>
                    </div>
                    <div class="id-user-name"><?php echo htmlspecialchars(strtoupper($nombreUsuario)); ?></div>
                    <div class="id-role"><?php echo htmlspecialchars(strtoupper($rolFormateado)); ?></div>
                    <div class="id-footer">
                        <div class="id-number">ID: <?php echo strtoupper(substr($iniciales, 0, 2) . '-' . substr(md5($_SESSION['user_id'] ?? '0000'), 0, 4) . '-' . substr($rolUsuario, 0, 1)); ?></div>
                        <div class="id-qr" style="background-image: url('data:image/svg+xml;utf8, <svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 10 10\'><path d=\'M0,0 h4 v4 h-4 z M6,0 h4 v4 h-4 z M0,6 h4 v4 h-4 z\' fill=\'%23000\'/></svg>'); background-size: cover;"></div>
                    </div>
                </div>

                <div class="content-card" style="margin-top: 30px;">
                    <h2 class="section-title" style="font-size: 18px;"><i class="far fa-calendar-alt"></i> Agenda</h2>
                    
                    <div class="event-card">
                        <div class="event-date">
                            <span class="date-day">12</span>
                            <span class="date-month">OCT</span>
                        </div>
                        <div class="event-info">
                            <h4>Limpieza de Playa</h4>
                            <div class="event-loc"><i class="fas fa-map-marker-alt"></i> Bahía Kino</div>
                            <button class="btn-join">Confirmar</button>
                        </div>
                    </div>

                    <div class="event-card">
                        <div class="event-date">
                            <span class="date-day">25</span>
                            <span class="date-month">OCT</span>
                        </div>
                        <div class="event-info">
                            <h4>Taller de Reciclaje</h4>
                            <div class="event-loc"><i class="fas fa-map-marker-alt"></i> Centro Cívico</div>
                            <button class="btn-join">Confirmar</button>
                        </div>
                    </div>
                </div>

                <div class="content-card">
                    <h2 class="section-title" style="font-size: 18px;"><i class="fas fa-medal"></i> Mis Insignias</h2>
                    <div class="badges-grid">
                        <div class="badge-item earned" title="Te uniste a la plataforma">
                            <div class="badge-icon"><i class="fas fa-flag-checkered"></i></div>
                            <div class="badge-name">Pionero</div>
                        </div>
                        <div class="badge-item earned" title="Participaste en 5 eventos">
                            <div class="badge-icon"><i class="fas fa-hands-helping"></i></div>
                            <div class="badge-name">Activo</div>
                        </div>
                        <div class="badge-item" title="Planta un árbol (Pendiente)">
                            <div class="badge-icon"><i class="fas fa-seedling"></i></div>
                            <div class="badge-name">Sembrador</div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
        // Animaciones iniciales para barras de progreso
        window.addEventListener('load', () => {
            document.querySelectorAll('.vote-bar-fill').forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0';
                setTimeout(() => { bar.style.width = width; }, 300);
            });
        });

        // Configuración simple del Chart para el ciudadano
        const ctx = document.getElementById('impactChart').getContext('2d');
        const impactChart = new Chart(ctx, {
            type: 'radar',
            data: {
                labels: ['Eventos', 'Donaciones', 'Votos', 'Reclutamiento', 'Horas'],
                datasets: [{
                    label: 'Tú',
                    data: [80, 50, 90, 40, 70],
                    fill: true,
                    backgroundColor: 'rgba(45, 122, 62, 0.4)',
                    borderColor: '#2d7a3e',
                    pointBackgroundColor: '#2d7a3e',
                    pointBorderColor: '#fff'
                }, {
                    label: 'Promedio Comunidad',
                    data: [60, 60, 50, 30, 50],
                    fill: true,
                    backgroundColor: 'rgba(200, 200, 200, 0.2)',
                    borderColor: '#ccc',
                    pointBackgroundColor: '#ccc',
                    pointBorderColor: '#fff',
                    borderDash: [5, 5]
                }]
            },
            options: {
                elements: { line: { borderWidth: 2 } },
                scales: {
                    r: {
                        angleLines: { display: true },
                        suggestedMin: 0,
                        suggestedMax: 100,
                        ticks: { display: false }
                    }
                },
                plugins: { legend: { position: 'bottom' } }
            }
        });

        // Simular voto
        document.querySelectorAll('.btn-vote').forEach(btn => {
            btn.addEventListener('click', function() {
                if(this.innerHTML.includes('Apoyo')) {
                    this.classList.toggle('active');
                }
            });
        });
    </script>
</body>
</html>