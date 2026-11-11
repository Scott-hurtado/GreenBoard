<?php
/**
 * API para listar todos los informes
 */
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../../includes/bootstrap.php';

    // Seguridad: Solo administradores
    $rol = $_SESSION['user_role'] ?? '';
    if (!in_array($rol, ['admin', 'administrador', 'superadmin'])) {
        throw new Exception("Acceso denegado. Se requieren permisos de administrador.");
    }

    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->query("SELECT id, titulo, creado_por, DATE_FORMAT(fecha_creacion, '%d/%b/%Y') AS fecha_creacion, tipo, estado, archivo_url FROM informes ORDER BY id DESC");
    $informes = $stmt->fetchAll();

    echo json_encode(['success' => true, 'informes' => $informes]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (PDOException $e) {
    error_log("Error de DB en list.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Error de base de datos.']);
}

