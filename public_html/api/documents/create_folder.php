<?php
// app/api/documents/rename_folder.php
require_once __DIR__ . '/../../includes/bootstrap.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    $id     = $_POST['id'] ?? null;
    $nombre = trim($_POST['nombre'] ?? '');
    $roles  = $_POST['roles_acceso'] ?? [];
    $roles  = array_values(array_filter($roles, fn($r) => in_array($r, ['estatal','regional','zona'])));
    $roles_json = json_encode($roles, JSON_UNESCAPED_UNICODE);

    if (!$id || empty($nombre)) {
        throw new Exception("Datos incompletos.");
    }

    $stmt = $db->prepare("UPDATE carpetas SET nombre = ?, roles_acceso = ? WHERE id = ?");
    $stmt->execute([$nombre, $roles_json, $id]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>