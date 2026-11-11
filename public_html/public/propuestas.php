<?php require_once __DIR__ . '/../includes/bootstrap.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Propuestas - Proyecto Verde</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* --- ESTILOS BASE (Consistentes) --- */
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

        /* --- COMPONENTES COMUNES --- */
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

        /* --- DECORACIÓN --- */
        .design-text {
            position: absolute;
            left: 40px;
            top: 200px;
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
        .dots-1 { top: 150px; right: 80px; }
        
        /* --- HEADER DE SECCIÓN --- */
        .page-header {
            padding: 80px 60px 40px 100px;
            opacity: 0;
            animation: fadeIn 1s ease forwards 0.2s;
        }

        /* --- GRID DE PROPUESTAS --- */
        .proposals-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 40px;
            padding: 0 60px 80px 100px;
        }

        .proposal-card {
            background: white;
            border: 1px solid #eee;
            border-top: 4px solid #2d7a3e; /* Acento de color */
            padding: 40px;
            border-radius: 4px;
            transition: all 0.3s ease;
            position: relative;
            opacity: 0;
            animation: fadeInUp 1s ease forwards;
        }

        /* Animación escalonada */
        .proposal-card:nth-child(1) { animation-delay: 0.4s; }
        .proposal-card:nth-child(2) { animation-delay: 0.5s; }
        .proposal-card:nth-child(3) { animation-delay: 0.6s; }
        .proposal-card:nth-child(4) { animation-delay: 0.7s; }

        .proposal-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.08);
        }

        .card-icon {
            width: 60px;
            height: 60px;
            background: #f0f7f0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #2d7a3e;
            font-size: 24px;
            margin-bottom: 25px;
            transition: background 0.3s ease;
        }

        .proposal-card:hover .card-icon {
            background: #2d7a3e;
            color: white;
        }

        .card-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 15px;
            color: #1a1a1a;
        }

        .card-text {
            color: #666;
            font-size: 15px;
            line-height: 1.6;
            margin-bottom: 25px;
        }

        .card-meta {
            border-top: 1px solid #eee;
            padding-top: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            color: #999;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .read-more {
            color: #2d7a3e;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: gap 0.3s ease;
        }

        .read-more:hover {
            gap: 10px;
        }

        /* --- SECCIÓN DESCARGA (CTA) --- */
        .download-section {
            background: #f9fbf9;
            padding: 80px 60px 80px 100px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid #eee;
        }

        /* Reusando el botón del index pero adaptado */
        .cta-button {
            display: inline-block;
            padding: 18px 40px;
            background: #1a1a1a; /* Negro para variar jerarquía */
            color: white;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            text-decoration: none;
            border-radius: 4px;
            transition: all 0.3s ease;
        }

        .cta-button:hover {
            background: #2d7a3e;
            transform: translateY(-2px);
        }

        /* --- ANIMACIONES --- */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* --- RESPONSIVE --- */
        @media (max-width: 1024px) {
            .proposals-grid {
                grid-template-columns: 1fr;
                padding: 0 40px 60px 40px;
            }
            .page-header {
                padding: 60px 40px;
            }
            .download-section {
                flex-direction: column;
                text-align: center;
                gap: 30px;
                padding: 60px 40px;
            }
            .design-text { display: none; }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include __DIR__ . '/../includes/components/navbar.php'; ?>

        <div class="design-text">#Plan 2030</div>
        <div class="dots dots-1"></div>

        <div class="page-header">
            <div class="ai-badge">NUESTRA VISIÓN</div>
            <h1 class="main-title">Propuestas<br>para el Futuro.</h1>
            <p class="description">
                No hacemos promesas vacías. Presentamos un plan estructurado en cuatro ejes fundamentales para transformar la realidad nacional desde la raíz.
            </p>
        </div>

        <div class="proposals-grid">
            
            <div class="proposal-card">
                <div class="card-icon">
                    <i class="fas fa-solar-panel"></i>
                </div>
                <h3 class="card-title">Energía Soberana</h3>
                <p class="card-text">
                    Transición acelerada a energías renovables comunitarias. Proponemos que el 60% de la energía pública provenga de fuentes limpias para 2030, descentralizando la producción.
                </p>
                <div class="card-meta">
                    <span>Eje Ambiental</span>
                    <a href="#" class="read-more">Detalles <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>

            <div class="proposal-card">
                <div class="card-icon">
                    <i class="fas fa-bus"></i>
                </div>
                <h3 class="card-title">Movilidad Digna</h3>
                <p class="card-text">
                    Inversión prioritaria en transporte público eléctrico y redes de ciclovías interconectadas. El objetivo es reducir el uso del automóvil particular en un 30% en zonas urbanas.
                </p>
                <div class="card-meta">
                    <span>Eje Urbano</span>
                    <a href="#" class="read-more">Detalles <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>

            <div class="proposal-card">
                <div class="card-icon">
                    <i class="fas fa-hand-holding-water"></i>
                </div>
                <h3 class="card-title">Justicia Hídrica</h3>
                <p class="card-text">
                    Ley de protección de acuíferos que prohíbe el uso industrial de agua potable en zonas de estrés hídrico. El agua es un derecho humano, no una mercancía.
                </p>
                <div class="card-meta">
                    <span>Eje Social</span>
                    <a href="#" class="read-more">Detalles <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>

            <div class="proposal-card">
                <div class="card-icon">
                    <i class="fas fa-search-dollar"></i>
                </div>
                <h3 class="card-title">Economía Circular</h3>
                <p class="card-text">
                    Incentivos fiscales agresivos para empresas que eliminen plásticos de un solo uso y adopten modelos de cero residuos. El que contamina, paga el triple.
                </p>
                <div class="card-meta">
                    <span>Eje Económico</span>
                    <a href="#" class="read-more">Detalles <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>

        </div>

        <div class="download-section">
            <div style="max-width: 600px;">
                <h2 style="font-size: 32px; font-weight: 800; margin-bottom: 15px;">Documentación Completa</h2>
                <p style="color: #666;">Descarga nuestro Plan de Gobierno 2025-2030 en formato PDF con el desglose presupuestal y técnico.</p>
            </div>
            <a href="#" class="cta-button">
                <i class="fas fa-file-pdf" style="margin-right: 10px;"></i> Descargar PDF
            </a>
        </div>

    </div>
    <?php include __DIR__ . '/../includes/components/footer.php'; ?>
</body>
</html>