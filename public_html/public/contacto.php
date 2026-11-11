<?php require_once __DIR__ . '/../includes/bootstrap.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contacto - Proyecto Verde</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* --- ESTILOS BASE --- */
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
            font-size: 14px;
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

        /* Texto y Puntos Decorativos */
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

        .dots {
            width: 100px;
            height: 40px;
            background-image: radial-gradient(circle, #ddd 1px, transparent 1px);
            background-size: 8px 8px;
            position: absolute;
            z-index: 0;
        }
        .dots-1 { top: 120px; right: 100px; }

        /* --- LAYOUT CONTACTO --- */
        .contact-wrapper {
            padding: 80px 60px 80px 100px;
            display: grid;
            grid-template-columns: 1.2fr 0.8fr; /* Formulario más ancho */
            gap: 80px;
            align-items: start;
        }

        /* Columna Izquierda: Formulario */
        .form-section {
            opacity: 0;
            animation: fadeInUp 1s ease forwards 0.2s;
        }

        .contact-form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group {
            margin-bottom: 5px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        input, textarea {
            width: 100%;
            padding: 15px;
            background: #f9fbf9;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            font-family: inherit;
            font-size: 14px;
            color: #333;
            transition: all 0.3s ease;
        }

        input:focus, textarea:focus {
            outline: none;
            border-color: #2d7a3e;
            background: white;
            box-shadow: 0 4px 10px rgba(45, 122, 62, 0.1);
        }

        textarea {
            resize: vertical;
            min-height: 150px;
        }

        /* Botón Submit (Estilo CTA) */
        .submit-btn {
            display: inline-block;
            padding: 18px 50px;
            background: #2d7a3e;
            color: white;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 20px;
            box-shadow: 0 4px 15px rgba(45, 122, 62, 0.2);
        }

        .submit-btn:hover {
            background: #1f5a2d;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(45, 122, 62, 0.3);
        }

        /* Columna Derecha: Info */
        .info-section {
            background: #f9fbf9; /* Caja gris sutil */
            padding: 40px;
            border-radius: 4px;
            border: 1px solid #eee;
            opacity: 0;
            animation: slideInRight 1s ease forwards 0.4s;
        }

        .info-item {
            margin-bottom: 30px;
            display: flex;
            gap: 20px;
            align-items: flex-start;
        }

        .info-icon {
            width: 40px;
            height: 40px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #2d7a3e;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            flex-shrink: 0;
        }

        .info-content h4 {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 5px;
            color: #1a1a1a;
        }

        .info-content p {
            font-size: 14px;
            color: #666;
            line-height: 1.5;
        }

        .info-content a {
            color: #2d7a3e;
            text-decoration: none;
            font-weight: 500;
        }

        /* Redes Sociales (Reutilizando estilo "Clean") */
        .social-area {
            margin-top: 40px;
            padding-top: 30px;
            border-top: 1px solid #e0e0e0;
        }
        
        .social-links {
            display: flex;
            gap: 15px;
            margin-top: 15px;
        }

        .social-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: white;
            border: 1px solid #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .social-icon:hover {
            background: #2d7a3e;
            color: white;
            border-color: #2d7a3e;
            transform: translateY(-3px);
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
            .contact-wrapper {
                grid-template-columns: 1fr;
                gap: 60px;
                padding: 60px 40px;
            }
            .design-text { display: none; }
        }

        @media (max-width: 768px) {
            .contact-form { grid-template-columns: 1fr; }
            .info-section { padding: 30px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include __DIR__ . '/../includes/components/navbar.php'; ?>

        <div class="design-text">Participación Ciudadana</div>
        <div class="dots dots-1"></div>

        <div class="contact-wrapper">
            
            <div class="form-section">
                <div class="ai-badge">CONTACTO</div>
                <h1 class="main-title">Hablemos.</h1>
                <p class="description">
                    ¿Tienes una propuesta para tu comunidad o quieres sumarte como voluntario? Tu voz es el motor de este proyecto. Escríbenos.
                </p>

                <form action="#" method="POST" class="contact-form">
                    <div class="form-group">
                        <label>Nombre Completo</label>
                        <input type="text" placeholder="Ej. Juan Pérez" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" placeholder="tucorreo@ejemplo.com" required>
                    </div>
                    <div class="form-group full-width">
                        <label>Asunto</label>
                        <input type="text" placeholder="¿Sobre qué quieres hablar?" required>
                    </div>
                    <div class="form-group full-width">
                        <label>Mensaje</label>
                        <textarea placeholder="Cuéntanos tu idea o inquietud..." required></textarea>
                    </div>
                    <div class="form-group full-width">
                        <button type="submit" class="submit-btn">Enviar Mensaje</button>
                    </div>
                </form>
            </div>

            <div class="info-section">
                <div class="info-item">
                    <div class="info-icon"><i class="fas fa-map-marker-alt"></i></div>
                    <div class="info-content">
                        <h4>Sede Central</h4>
                        <p>Av. Reforma 222, Piso 8<br>Ciudad de México, CDMX</p>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-icon"><i class="fas fa-envelope"></i></div>
                    <div class="info-content">
                        <h4>Contacto Digital</h4>
                        <p><a href="mailto:hola@proyectoverde.mx">hola@proyectoverde.mx</a></p>
                        <p><a href="mailto:prensa@proyectoverde.mx">prensa@proyectoverde.mx</a></p>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-icon"><i class="fas fa-phone"></i></div>
                    <div class="info-content">
                        <h4>Teléfono</h4>
                        <p>+52 (55) 1234 5678</p>
                        <p style="font-size: 12px; margin-top: 5px;">Lun-Vie: 9:00 - 18:00</p>
                    </div>
                </div>

                <div class="social-area">
                    <h4 style="font-size: 14px; text-transform: uppercase; letter-spacing: 1px;">Síguenos</h4>
                    <div class="social-links">
                        <a href="#" class="social-icon"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="social-icon"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="social-icon"><i class="fab fa-x-twitter"></i></a>
                        <a href="#" class="social-icon"><i class="fab fa-tiktok"></i></a>
                    </div>
                </div>
            </div>

        </div>
    </div>
    <?php include __DIR__ . '/../includes/components/footer.php'; ?>
</body>
</html>