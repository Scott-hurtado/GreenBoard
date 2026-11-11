<?php
// 1. Cargar el sistema
require_once __DIR__ . '/../../includes/bootstrap.php';

// 2. Seguridad: Verificar sesión
if (!isset($_SESSION['user_id'])) {
    header('Location: /iniciar-sesion.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Actividades - Proyecto Verde</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ESTILOS BASE */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; background: #f0f7f0; }

        /* AJUSTE PARA SIDEBAR FIJO (280px) */
        .container {
            margin-left: 280px; /* Margen fijo para el sidebar */
            min-height: 100vh;
            background: white;
            transition: margin-left 0.3s ease;
        }
        
        .content-padding { padding: 40px 60px; }
        
        /* HEADER DE PÁGINA */
        .page-header { margin-bottom: 30px; }
        .page-header h1 { color: #2d7a3e; font-size: 32px; margin-bottom: 10px; font-weight: 800; }
        .page-header p { color: #666; font-size: 16px; }

        /* LISTA DE ACTIVIDADES (Tu estilo de tarjetas) */
        .activities-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .activity-card {
            background: white;
            border: 1px solid #eee;
            border-radius: 12px;
            padding: 25px;
            display: flex;
            align-items: center;
            gap: 25px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
        }

        .activity-card:hover {
            transform: translateX(5px);
            border-left: 5px solid #2d7a3e;
            box-shadow: 0 5px 20px rgba(45, 122, 62, 0.15);
        }

        /* CAJA DE FECHA (Verde) */
        .date-box {
            background: #e8f5e9;
            color: #2d7a3e;
            padding: 15px;
            border-radius: 12px;
            text-align: center;
            min-width: 90px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .date-day { font-size: 28px; font-weight: 900; display: block; line-height: 1; margin-bottom: 5px; }
        .date-month { font-size: 13px; text-transform: uppercase; font-weight: 700; letter-spacing: 1px; }

        /* CONTENIDO DE LA TARJETA */
        .activity-info { flex: 1; }
        .activity-info h3 { color: #1a1a1a; font-size: 20px; margin-bottom: 8px; font-weight: 700; }
        .activity-info p { color: #666; font-size: 14px; margin-bottom: 10px; line-height: 1.5; }
        
        .activity-meta { display: flex; gap: 15px; font-size: 13px; color: #888; }
        .activity-meta span { display: flex; align-items: center; gap: 6px; }
        .activity-meta i { color: #2d7a3e; }

        /* BOTÓN DE ACCIÓN */
        .btn-join {
            padding: 10px 20px;
            background: #2d7a3e;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-join:hover { background: #1f5a2d; transform: translateY(-2px); }
        .btn-secondary { background: white; border: 2px solid #2d7a3e; color: #2d7a3e; }
        .btn-secondary:hover { background: #f0f7f0; }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .container { margin-left: 0; }
            .content-padding { padding: 30px 20px; }
            .activity-card { flex-direction: column; align-items: flex-start; gap: 15px; }
            .date-box { flex-direction: row; width: 100%; justify-content: space-between; align-items: center; padding: 10px 20px; }
            .date-day { margin-bottom: 0; margin-right: 10px; }
            .btn-join { width: 100%; text-align: center; }
        }
    </style>
</head>
<body>

    <?php include __DIR__ . '/../../includes/components/sidebar.php'; ?>

    <div class="container">
        <div class="content-padding">
            
            <div class="page-header">
                <h1>Próximas Actividades</h1>
                <p>Explora y participa en los eventos disponibles para transformar tu comunidad.</p>
            </div>

            <div class="activities-list">
                
                <div class="activity-card">
                    <div class="date-box">
                        <span class="date-day">15</span>
                        <span class="date-month">DIC</span>
                    </div>
                    <div class="activity-info">
                        <h3>Limpieza del Parque Central</h3>
                        <p>Únete a nuestra brigada de voluntarios para recolección de residuos y reforestación de áreas verdes.</p>
                        <div class="activity-meta">
                            <span><i class="fas fa-clock"></i> 09:00 AM - 12:00 PM</span>
                            <span><i class="fas fa-map-marker-alt"></i> Parque Central, Zona Norte</span>
                        </div>
                    </div>
                    <div>
                        <a href="#" class="btn-join">Inscribirme</a>
                    </div>
                </div>

                <div class="activity-card">
                    <div class="date-box">
                        <span class="date-day">22</span>
                        <span class="date-month">DIC</span>
                    </div>
                    <div class="activity-info">
                        <h3>Reunión Anual de Cierre</h3>
                        <p>Presentación de resultados anuales y convivio navideño con todos los miembros de la red.</p>
                        <div class="activity-meta">
                            <span><i class="fas fa-clock"></i> 06:00 PM - 09:00 PM</span>
                            <span><i class="fas fa-users"></i> Auditorio Municipal</span>
                        </div>
                    </div>
                    <div>
                        <a href="#" class="btn-join btn-secondary">Ver Detalles</a>
                    </div>
                </div>

                <div class="activity-card">
                    <div class="date-box">
                        <span class="date-day">05</span>
                        <span class="date-month">ENE</span>
                    </div>
                    <div class="activity-info">
                        <h3>Taller de Liderazgo Comunitario</h3>
                        <p>Capacitación para nuevos promotores y líderes de zona. Aprende a gestionar proyectos locales.</p>
                        <div class="activity-meta">
                            <span><i class="fas fa-clock"></i> 10:00 AM - 02:00 PM</span>
                            <span><i class="fas fa-certificate"></i> Certificado Incluido</span>
                        </div>
                    </div>
                    <div>
                        <a href="#" class="btn-join">Inscribirme</a>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../../includes/components/footer-citizen.php'; ?>

</body>
</html>