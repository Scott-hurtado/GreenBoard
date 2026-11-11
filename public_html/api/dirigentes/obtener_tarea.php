<?php
// api/dirigentes/obtener_tarea.php
require_once __DIR__ . '/../../includes/bootstrap.php';
header('Content-Type: application/json');

$id = $_GET['id'] ?? '';

if (empty($id) || !is_numeric($id)) {
    echo json_encode(['error' => 'ID inválido']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    $sql = "SELECT t.*, 
                   b.titulo as nombre_actividad,
                   b.agenda as categoria
            FROM dirigentes_actividades t 
            LEFT JOIN banco_actividades b ON t.id_actividad_banco = b.id 
            WHERE t.id = :id";
            
    $stmt = $db->prepare($sql);
    $stmt->execute([':id' => $id]);
    $tarea = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($tarea) {
        echo json_encode($tarea);
    } else {
        echo json_encode(['error' => 'Tarea no encontrada']);
    }

} catch (Exception $e) {
    error_log("Error en obtener_tarea: " . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
}
?>