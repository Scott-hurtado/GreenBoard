<?php require_once __DIR__ . '/../includes/bootstrap.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nosotros - Proyecto Verde</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* --- ESTILOS BASE (Heredados del Index) --- */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Arial', sans-serif;
            background: #f0f7f0;
            overflow-x: hidden;
            color: #1a1a1a;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            min-height: 100vh;
            position: relative;
        }

        /* --- COMPONENTES VISUALES --- */
        .ai-badge {
            color: #2d7a3e;
            font-size: 14px; /* Ajustado para que sea sutil */
            font-weight: 700;
            letter-spacing: 6px;
            margin-bottom: 20px;
            display: inline-block;
            text-transform: uppercase;
        }

        .main-title {
            font-size: 64px;
            font-weight: 900;
            line-height: 1;
            margin-bottom: 30px;
            color: #1a1a1a;
        }

        .description {
            color: #666;
            font-size: 16px;
            line-height: 1.8;
            margin-bottom: 40px;
            max-width: 600px;
        }

        /* Texto vertical decorativo */
        .design-text {
            position: absolute;
            left: 40px;
            top: 50%;
            transform: translateY(-50%);
            writing-mode: vertical-rl;
            font-size: 13px;
            font-weight: 500;
            color: #2d7a3e;
            letter-spacing: 2px;
            opacity: 0.5;
        }

        /* Puntos decorativos */
        .dots {
            width: 100px;
            height: 40px;
            background-image: radial-gradient(circle, #ddd 1px, transparent 1px);
            background-size: 8px 8px;
            position: absolute;
            z-index: 0;
        }
        .dots-1 { top: 100px; right: 50px; }
        .dots-2 { bottom: 50px; left: 100px; }

        /* --- LAYOUT ESPECÍFICO DE NOSOTROS --- */
        .about-section {
            padding: 80px 60px 80px 100px; /* Padding izquierdo extra para el texto vertical */
            position: relative;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 80px;
            align-items: center;
        }

        .about-image-container {
            position: relative;
            height: 600px;
            opacity: 0;
            animation: fadeInUp 1s ease forwards 0.2s;
        }

        .about-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 4px;
            box-shadow: 20px 20px 0px #f0f7f0; /* Sombra sólida estilo arquitectónico */
        }

        .text-content {
            opacity: 0;
            animation: slideInRight 1s ease forwards 0.4s;
        }

        /* --- SECCIÓN DE VALORES (GRID) --- */
        .values-section {
            padding: 80px 60px 120px 100px;
            background: #f9fbf9;
        }

        .section-header {
            margin-bottom: 60px;
            opacity: 0;
            animation: fadeInUp 1s ease forwards 0.6s;
        }

        .values-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
        }

        .value-card {
            background: white;
            padding: 40px;
            border-radius: 4px;
            border: 1px solid #eee;
            transition: all 0.3s ease;
            opacity: 0;
            animation: fadeInUp 1s ease forwards 0.8s;
            position: relative;
            overflow: hidden;
        }

        /* Retraso escalonado para las tarjetas */
        .value-card:nth-child(1) { animation-delay: 0.8s; }
        .value-card:nth-child(2) { animation-delay: 0.9s; }
        .value-card:nth-child(3) { animation-delay: 1.0s; }

        .value-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(45, 122, 62, 0.1);
            border-color: #2d7a3e;
        }

        .value-icon {
            font-size: 32px;
            color: #2d7a3e;
            margin-bottom: 20px;
        }

        .value-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 15px;
            color: #1a1a1a;
        }

        .value-text {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
        }

        /* --- ACTION CONTAINER (El diseño nuevo) --- */
        .action-container {
            display: flex;
            align-items: center;
            margin-top: 40px;
        }

        .cta-button {
            display: inline-block;
            padding: 18px 40px;
            background: #2d7a3e;
            color: white;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            text-decoration: none;
            border-radius: 4px;
            box-shadow: 0 4px 15px rgba(45, 122, 62, 0.2);
            transition: all 0.3s ease;
        }

        .cta-button:hover {
            background: #1f5a2d;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(45, 122, 62, 0.3);
        }

        .social-links {
            display: flex;
            align-items: center;
            gap: 25px;
            margin-left: 25px;
            padding-left: 25px;
            border-left: 1px solid #ddd;
            height: 40px;
        }

        .social-icon {
            color: #999;
            font-size: 20px;
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .social-icon:hover {
            color: #2d7a3e;
            transform: translateY(-3px) scale(1.1);
        }

        /* --- ANIMACIONES --- */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(50px); }
            to { opacity: 1; transform: translateX(0); }
        }

        /* --- RESPONSIVE --- */
        @media (max-width: 1024px) {
            .about-section {
                grid-template-columns: 1fr;
                padding: 60px 40px;
                gap: 40px;
            }
            .about-image-container { height: 400px; }
            .values-grid { grid-template-columns: 1fr; }
            .design-text { display: none; } /* Ocultar en móviles */
        }
        
        @media (max-width: 768px) {
             .action-container {
                flex-direction: column;
                align-items: flex-start;
                gap: 20px;
            }
            .social-links {
                margin-left: 0; padding-left: 0; border-left: none; margin-top: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include __DIR__ . '/../includes/components/navbar.php'; ?>

        <div class="design-text">Nuestra Identidad</div>
        <div class="dots dots-1"></div>

        <section class="about-section">
            <div class="text-content">
                <div class="ai-badge">Quiénes Somos</div>
                <h1 class="main-title">La fuerza de la ciudadanía.</h1>
                <p class="description">
                    Proyecto Verde nació no como un partido tradicional, sino como una respuesta urgente a la crisis climática y social de México. Somos un colectivo de científicos, activistas, estudiantes y trabajadores unidos por una visión común.
                </p>
                <p class="description">
                    Creemos que la política debe ser una herramienta de servicio, no de poder. Nuestra estructura es horizontal, transparente y financiada exclusivamente por ciudadanos que creen en el cambio real.
                </p>

                <div class="action-container">
                    <a href="#" class="cta-button">Conoce el plan</a>
                    <div class="social-links">
                        <a href="#" class="social-icon"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="social-icon"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="social-icon"><i class="fab fa-x-twitter"></i></a>
                    </div>
                </div>
            </div>

            <div class="about-image-container">
                <img src="https://images.unsplash.com/photo-1529156069898-49953e39b3ac?w=800&h=1000&fit=crop" alt="Equipo Proyecto Verde" class="about-image">
                <div class="dots dots-2"></div>
            </div>
        </section>

        <section class="values-section">
            <div class="section-header">
                <div class="ai-badge">Nuestros Pilares</div>
                <h2 style="font-size: 40px; color: #1a1a1a;">Lo que nos mueve</h2>
            </div>

            <div class="values-grid">
                <div class="value-card">
                    <i class="fas fa-leaf value-icon"></i>
                    <h3 class="value-title">Sostenibilidad Radical</h3>
                    <p class="value-text">No basta con reciclar. Proponemos una transformación completa de la matriz energética y la protección constitucional de nuestros recursos naturales.</p>
                </div>

                <div class="value-card">
                    <i class="fas fa-users value-icon"></i>
                    <h3 class="value-title">Justicia Social</h3>
                    <p class="value-text">La ecología sin lucha de clases es jardinería. Trabajamos para reducir la brecha de desigualdad y garantizar servicios dignos para todos.</p>
                </div>

                <div class="value-card">
                    <i class="fas fa-eye value-icon"></i>
                    <h3 class="value-title">Transparencia Total</h3>
                    <p class="value-text">Nuestras cuentas son públicas en tiempo real. Cada peso que entra y sale de Proyecto Verde puede ser auditado por cualquier ciudadano.</p>
                </div>
            </div>
        </section>

    </div>
    <?php include __DIR__ . '/../includes/components/footer.php'; ?>
</body>
</html>