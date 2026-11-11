<?php
// api/banco_de_actividades/obtener_asignaciones.php
require_once __DIR__ . '/../../includes/bootstrap.php';
header('Content-Type: application/json');

$id_actividad = $_GET['id'] ?? '';

if (empty($id_actividad)) {
    echo json_encode([]);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Buscamos en tareas_estatales donde coincida el ID del banco
    $sql = "SELECT estado, estatus, mes_objetivo, fecha_asignacion 
            FROM tareas_estatales 
            WHERE id_actividad_banco = :id 
            ORDER BY fecha_asignacion DESC";
            
    $stmt = $db->prepare($sql);
    $stmt->execute([':id' => $id_actividad]);
    $asignaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($asignaciones);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>