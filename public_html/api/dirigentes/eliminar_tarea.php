<?php
// api/dirigentes/eliminar_tarea.php
require_once __DIR__ . '/../../includes/bootstrap.php';

header('Content-Type: application/json');

// 1. Verificar sesión y permisos
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'administrador', 'superadmin', 'super admin'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// 2. Verificar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// 3. Obtener ID
$id_tarea = $_POST['id'] ?? null;

if (empty($id_tarea) || !is_numeric($id_tarea)) {
    echo json_encode(['success' => false, 'message' => 'ID de tarea inválido']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // 4. Eliminar de la tabla correcta: 'dirigentes_actividades'
    $sql = "DELETE FROM dirigentes_actividades WHERE id = :id";
    $stmt = $db->prepare($sql);
    $stmt->execute([':id' => $id_tarea]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Tarea eliminada correctamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'La tarea no existe o ya fue eliminada']);
    }

} catch (Exception $e) {
    error_log("Error al eliminar tarea: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
}
?>