<?php
/**
 * API para generar vista HTML imprimible de un informe
 */
require_once __DIR__ . '/../../includes/bootstrap.php';

try {
    // Seguridad: Solo administradores
    $rol = $_SESSION['user_role'] ?? '';
    if (!in_array($rol, ['admin', 'administrador', 'superadmin'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
        exit;
    }

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($id <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'ID de informe no válido.']);
        exit;
    }
    
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->prepare("SELECT titulo, contenido, archivo_url, creado_por, fecha_creacion, tipo FROM informes WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $report = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$report) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Informe no encontrado.']);
        exit;
    }
    
    // Si ya es un PDF subido, redirigimos
    if ($report['archivo_url']) {
        header('Location: ' . $report['archivo_url']);
        exit;
    }
    
    // Generar vista HTML limpia para imprimir como PDF
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Informe: <?php echo htmlspecialchars($report['titulo']); ?></title>
        <style>
            body { font-family: 'Arial', sans-serif; padding: 40px; color: #333; line-height: 1.6; }
            .header { border-bottom: 2px solid #2d7a3e; padding-bottom: 20px; margin-bottom: 30px; }
            .header h1 { margin: 0; color: #1a1a1a; font-size: 28px; }
            .meta { margin-top: 10px; color: #666; font-size: 14px; }
            .meta span { margin-right: 20px; }
            .content { font-size: 14px; }
            .footer { margin-top: 50px; font-size: 12px; color: #999; text-align: center; border-top: 1px solid #eee; padding-top: 20px; }
            @media print {
                body { padding: 0; }
                .no-print { display: none; }
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1><?php echo htmlspecialchars($report['titulo']); ?></h1>
            <div class="meta">
                <span><strong>Autor:</strong> <?php echo htmlspecialchars($report['creado_por']); ?></span>
                <span><strong>Tipo:</strong> <?php echo htmlspecialchars($report['tipo']); ?></span>
                <span><strong>Fecha:</strong> <?php echo htmlspecialchars($report['fecha_creacion']); ?></span>
            </div>
        </div>
        
        <div class="content">
            <?php echo $report['contenido']; ?>
        </div>

        <div class="footer">
            Generado por Sistema de Gestión - Proyecto Verde
        </div>

        <script>
            // Abrir diálogo de impresión automáticamente
            window.onload = function() {
                setTimeout(function(){ window.print(); }, 500);
            };
        </script>
    </body>
    </html>
    <?php
    exit;

} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

