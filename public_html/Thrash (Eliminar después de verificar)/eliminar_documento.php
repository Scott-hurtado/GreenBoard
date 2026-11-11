<?php
require_once 'db.php';

header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? null;
    
    if (!$id) {
        throw new Exception('ID requerido');
    }
    
    $db = getDB();
    $stmt = $db->prepare("UPDATE documentos SET activo = 0 WHERE id = :id");
    $stmt->execute([':id' => $id]);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>