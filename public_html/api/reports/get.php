<?php
/**
 * API para obtener un informe por ID
 */
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../../includes/bootstrap.php';

    // Seguridad: Solo administradores
    $rol = $_SESSION['user_role'] ?? '';
    if (!in_array($rol, ['admin', 'administrador', 'superadmin'])) {
        throw new Exception("Acceso denegado. Se requieren permisos de administrador.");
    }

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($id <= 0) {
        throw new Exception("ID de informe no válido.");
    }

    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->prepare("SELECT id, titulo, creado_por, tipo, estado, contenido, archivo_url FROM informes WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $report = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$report) {
        throw new Exception("Informe no encontrado.");
    }

    if ($report['archivo_url'] !== NULL) {
        echo json_encode([
            'success' => false, 
            'message' => 'Este informe es un archivo PDF, no tiene contenido editable.', 
            'is_pdf' => true
        ]);
    } else {
        echo json_encode(['success' => true, 'report' => $report]);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (PDOException $e) {
    error_log("Error de DB en get.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Error de base de datos.']);
}

