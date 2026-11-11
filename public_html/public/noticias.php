<?php require_once __DIR__ . '/../includes/bootstrap.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Noticias - Proyecto Verde</title>
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

        .design-text {
            position: absolute;
            left: 40px;
            top: 250px;
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
        .dots-1 { top: 100px; right: 50px; }

        /* --- LAYOUT NOTICIAS --- */
        .news-wrapper {
            padding: 80px 60px 80px 100px;
        }

        /* Noticia Destacada (Hero) */
        .featured-article {
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 0;
            background: white;
            border: 1px solid #eee;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 60px;
            opacity: 0;
            animation: fadeInUp 1s ease forwards 0.2s;
            transition: all 0.3s ease;
        }

        .featured-article:hover {
            box-shadow: 0 15px 30px rgba(0,0,0,0.08);
            transform: translateY(-2px);
        }

        .featured-image {
            width: 100%;
            height: 400px;
            object-fit: cover;
        }

        .featured-content {
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            border-left: 1px solid #eee;
        }

        /* Grid de Noticias Secundarias */
        .news-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            opacity: 0;
            animation: fadeInUp 1s ease forwards 0.4s;
        }

        .news-card {
            background: white;
            border: 1px solid #eee;
            border-radius: 4px;
            overflow: hidden;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        .news-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.05);
            border-color: #2d7a3e; /* Detalle verde al hover */
        }

        .card-image-wrapper {
            height: 200px;
            overflow: hidden;
        }

        .card-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .news-card:hover .card-image {
            transform: scale(1.05);
        }

        .card-body {
            padding: 25px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        /* Elementos de Texto */
        .meta-tag {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #2d7a3e;
            margin-bottom: 10px;
            display: inline-block;
        }

        .news-date {
            font-size: 12px;
            color: #999;
            margin-bottom: 10px;
            display: block;
        }

        .news-title {
            font-size: 20px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 15px;
            line-height: 1.3;
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .news-title:hover {
            color: #2d7a3e;
        }

        .news-excerpt {
            font-size: 14px;
            color: #666;
            line-height: 1.6;
            margin-bottom: 20px;
            flex: 1; /* Empuja el botón hacia abajo */
        }

        /* Enlace Leer Más */
        .read-link {
            font-size: 13px;
            font-weight: 600;
            color: #1a1a1a;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            border-bottom: 2px solid transparent;
            width: fit-content;
            transition: all 0.3s ease;
        }

        .read-link:hover {
            color: #2d7a3e;
            gap: 10px;
        }

        /* Paginación simple */
        .pagination {
            margin-top: 60px;
            display: flex;
            justify-content: center;
            gap: 10px;
            opacity: 0;
            animation: fadeIn 1s ease forwards 0.6s;
        }

        .page-btn {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #eee;
            color: #1a1a1a;
            text-decoration: none;
            border-radius: 4px;
            transition: all 0.3s ease;
        }

        .page-btn.active, .page-btn:hover {
            background: #2d7a3e;
            color: white;
            border-color: #2d7a3e;
        }

        /* --- ANIMACIONES --- */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        /* --- RESPONSIVE --- */
        @media (max-width: 1024px) {
            .news-wrapper { padding: 60px 40px; }
            .featured-article { grid-template-columns: 1fr; }
            .featured-image { height: 250px; }
            .news-grid { grid-template-columns: 1fr 1fr; }
            .design-text { display: none; }
        }

        @media (max-width: 768px) {
            .news-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include __DIR__ . '/../includes/components/navbar.php'; ?>

        <div class="design-text">#Sala de Prensa</div>
        <div class="dots dots-1"></div>

        <div class="news-wrapper">
            
            <div class="ai-badge">ACTUALIDAD</div>
            <h1 class="main-title">Voces del Cambio.</h1>
            <p class="description">
                Mantente informado sobre nuestras actividades, logros legislativos y eventos comunitarios. La transparencia comienza con la información.
            </p>

            <article class="featured-article">
                <img src="https://images.unsplash.com/photo-1544027993-37dbfe43562a?w=800&h=500&fit=crop" alt="Manifestación Pacífica" class="featured-image">
                <div class="featured-content">
                    <span class="meta-tag">Política Nacional</span>
                    <span class="news-date">12 de Octubre, 2025</span>
                    <a href="#" class="news-title" style="font-size: 28px;">Presentamos la Iniciativa de Ley "Bosques Vivos" ante el Congreso</a>
                    <p class="news-excerpt">
                        En una jornada histórica, nuestros representantes entregaron el documento que busca proteger el 30% del territorio nacional bajo esquemas de conservación estricta.
                    </p>
                    <a href="#" class="read-link">Leer artículo completo <i class="fas fa-arrow-right"></i></a>
                </div>
            </article>

            <div class="news-grid">
                
                <article class="news-card">
                    <div class="card-image-wrapper">
                        <img src="https://images.unsplash.com/photo-1528323273322-d81458248d40?w=500&h=300&fit=crop" alt="Reforestación" class="card-image">
                    </div>
                    <div class="card-body">
                        <span class="meta-tag">Comunidad</span>
                        <span class="news-date">05 de Octubre, 2025</span>
                        <a href="#" class="news-title">Jornada de reforestación en la Sierra Madre: Resultados</a>
                        <p class="news-excerpt">Más de 500 voluntarios se sumaron este fin de semana para plantar 2,000 especies endémicas.</p>
                        <a href="#" class="read-link">Leer más <i class="fas fa-arrow-right"></i></a>
                    </div>
                </article>

                <article class="news-card">
                    <div class="card-image-wrapper">
                        <img src="https://images.unsplash.com/photo-1577563908411-5077b6dc7624?w=500&h=300&fit=crop" alt="Debate" class="card-image">
                    </div>
                    <div class="card-body">
                        <span class="meta-tag">Debate</span>
                        <span class="news-date">28 de Septiembre, 2025</span>
                        <a href="#" class="news-title">Nuestra postura frente al presupuesto energético 2026</a>
                        <p class="news-excerpt">Analizamos punto por punto por qué la inversión en fósiles es un error económico y ambiental.</p>
                        <a href="#" class="read-link">Leer más <i class="fas fa-arrow-right"></i></a>
                    </div>
                </article>

                <article class="news-card">
                    <div class="card-image-wrapper">
                        <img src="https://images.unsplash.com/photo-1517048676732-d65bc937f952?w=500&h=300&fit=crop" alt="Reunión" class="card-image">
                    </div>
                    <div class="card-body">
                        <span class="meta-tag">Internacional</span>
                        <span class="news-date">20 de Septiembre, 2025</span>
                        <a href="#" class="news-title">Alianza Verde: Acuerdos con partidos ecológicos de Latam</a>
                        <p class="news-excerpt">Fortalecemos la cooperación regional para enfrentar la crisis climática de manera conjunta.</p>
                        <a href="#" class="read-link">Leer más <i class="fas fa-arrow-right"></i></a>
                    </div>
                </article>

                 <article class="news-card">
                    <div class="card-image-wrapper">
                        <img src="https://images.unsplash.com/photo-1541888946425-d81bb19240f5?w=500&h=300&fit=crop" alt="Construcción Sostenible" class="card-image">
                    </div>
                    <div class="card-body">
                        <span class="meta-tag">Urbanismo</span>
                        <span class="news-date">15 de Septiembre, 2025</span>
                        <a href="#" class="news-title">Propuesta de techos verdes obligatorios en nuevos edificios</a>
                        <p class="news-excerpt">Una medida simple que podría reducir la temperatura de la ciudad en 2 grados centígrados.</p>
                        <a href="#" class="read-link">Leer más <i class="fas fa-arrow-right"></i></a>
                    </div>
                </article>

                <article class="news-card">
                    <div class="card-image-wrapper">
                        <img src="https://images.unsplash.com/photo-1573164713988-8665fc963095?w=500&h=300&fit=crop" alt="Tecnología" class="card-image">
                    </div>
                    <div class="card-body">
                        <span class="meta-tag">Tecnología</span>
                        <span class="news-date">10 de Septiembre, 2025</span>
                        <a href="#" class="news-title">Lanzamos la app "Vigilante Ambiental" para reportes ciudadanos</a>
                        <p class="news-excerpt">Ahora puedes denunciar vertederos ilegales y contaminación directamente desde tu celular.</p>
                        <a href="#" class="read-link">Leer más <i class="fas fa-arrow-right"></i></a>
                    </div>
                </article>

                <article class="news-card">
                    <div class="card-image-wrapper">
                        <img src="https://images.unsplash.com/photo-1551836022-d5d88e9218df?w=500&h=300&fit=crop" alt="Educación" class="card-image">
                    </div>
                    <div class="card-body">
                        <span class="meta-tag">Educación</span>
                        <span class="news-date">01 de Septiembre, 2025</span>
                        <a href="#" class="news-title">Becas para investigación en energías renovables</a>
                        <p class="news-excerpt">Abrimos la convocatoria para estudiantes de posgrado que desarrollen tecnología limpia.</p>
                        <a href="#" class="read-link">Leer más <i class="fas fa-arrow-right"></i></a>
                    </div>
                </article>

            </div>

            <div class="pagination">
                <a href="#" class="page-btn active">1</a>
                <a href="#" class="page-btn">2</a>
                <a href="#" class="page-btn">3</a>
                <a href="#" class="page-btn"><i class="fas fa-chevron-right"></i></a>
            </div>

        </div>
    </div>
    <?php include __DIR__ . '/../includes/components/footer.php'; ?>
</body>
</html>