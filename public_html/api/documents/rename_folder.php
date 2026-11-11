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
    
    $id = $_POST['id'] ?? null;
    $nombre = trim($_POST['nombre'] ?? '');

    if (!$id || empty($nombre)) {
        throw new Exception("Datos incompletos.");
    }

    $stmt = $db->prepare("UPDATE carpetas SET nombre = ? WHERE id = ?");
    $stmt->execute([$nombre, $id]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>