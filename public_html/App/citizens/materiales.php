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
    <title>Materiales - Proyecto Verde</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; background: #f0f7f0; }
        
        /* AJUSTE PARA SIDEBAR FIJO */
        .container {
            margin-left: 280px; /* Debe coincidir con el ancho de la sidebar */
            min-height: 100vh;
            background: white;
            transition: margin-left 0.3s ease;
        }
        
        .content-padding { padding: 40px 60px; }
        h1 { color: #2d7a3e; margin-bottom: 20px; }

        .materials-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }

        .material-card {
            background: #fff;
            border: 1px solid #eee;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            transition: transform 0.2s;
        }
        .material-card:hover { transform: translateY(-5px); box-shadow: 0 5px 15px rgba(45,122,62,0.15); }
        
        .icon-box {
            font-size: 40px;
            color: #2d7a3e;
            margin-bottom: 15px;
        }
        
        .btn-download {
            display: inline-block;
            margin-top: 15px;
            padding: 8px 20px;
            background: #f0f7f0;
            color: #2d7a3e;
            text-decoration: none;
            border-radius: 20px;
            font-weight: bold;
            font-size: 13px;
        }
        .btn-download:hover { background: #2d7a3e; color: white; }

        @media (max-width: 768px) { 
            .container { margin-left: 0; } 
            .content-padding { padding: 20px; } 
        }
    </style>
</head>
<body>

    <?php include __DIR__ . '/../../includes/components/sidebar.php'; ?>

    <div class="container">
        <div class="content-padding">
            <h1>Materiales de Descarga</h1>
            <p>Recursos gráficos y documentos oficiales para compartir.</p>

            <div class="materials-grid">
                <div class="material-card">
                    <div class="icon-box"><i class="fas fa-file-pdf"></i></div>
                    <h4>Manual de Identidad</h4>
                    <p class="text-muted">PDF - 2.5 MB</p>
                    <a href="#" class="btn-download"><i class="fas fa-download"></i> Descargar</a>
                </div>

                <div class="material-card">
                    <div class="icon-box"><i class="fas fa-image"></i></div>
                    <h4>Logos Oficiales</h4>
                    <p class="text-muted">ZIP - 15 MB</p>
                    <a href="#" class="btn-download"><i class="fas fa-download"></i> Descargar</a>
                </div>

                <div class="material-card">
                    <div class="icon-box"><i class="fas fa-share-alt"></i></div>
                    <h4>Kit Redes Sociales</h4>
                    <p class="text-muted">Plantillas Editables</p>
                    <a href="#" class="btn-download"><i class="fas fa-download"></i> Descargar</a>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../../includes/components/footer-citizen.php'; ?>

</body>
</html>