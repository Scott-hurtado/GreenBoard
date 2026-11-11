<?php
// app/api/documents/delete_folder.php

require_once __DIR__ . '/../../includes/bootstrap.php';
header('Content-Type: application/json');

// Recibir JSON
$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? null;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID no proporcionado']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    // Gracias al "ON DELETE CASCADE" en el SQL del Paso 1, 
    // al borrar la carpeta se borrarán las subcarpetas automáticamente.
    // Los documentos pasarán a carpeta_id = NULL (Raíz) o se borrarán según tu configuración.
    
    // Opción A: Si quieres que los archivos dentro se borren también, 
    // deberías borrarlos físicamente primero o actualizar la tabla documentos.
    // Aquí haremos un borrado simple de la carpeta lógica:
    
    $stmt = $db->prepare("DELETE FROM carpetas WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>