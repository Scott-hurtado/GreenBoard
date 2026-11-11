<?php
/**
 * API GESTIÓN - DELETE
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../includes/bootstrap.php';

// Verificar Admin
$rol = $_SESSION['user_role'] ?? '';
if (!in_array($rol, ['admin', 'administrador', 'superadmin', 'super admin'])) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? 0;
    $type = $input['type'] ?? ''; // 'objetivo', 'estrategia', 'cronograma', 'meta'

    if (!$id || !$type) {
        throw new Exception("ID o Tipo faltante");
    }

    $table = '';
    
    // CORRECCIÓN: Convertir a minúsculas para comparación consistente
    $typeLower = strtolower($type);
    
    if ($typeLower === 'objetivos' || $typeLower === 'objetivo') {
        $table = 'geo_objetivos';
    } elseif ($typeLower === 'estrategias' || $typeLower === 'estrategia') {
        $table = 'geo_estrategias';
    } elseif ($typeLower === 'cronograma') {
        $table = 'geo_cronograma';
    } elseif ($typeLower === 'metas' || $typeLower === 'meta') {
        $table = 'geo_metas';
    } else {
        throw new Exception("Tipo desconocido: $type");
    }

    // Borrado lógico (activo = 0) - RECOMENDADO para mantener historial
    $sql = "UPDATE $table SET activo = 0 WHERE id = ?";
    $stmt = $db->prepare($sql);
    $result = $stmt->execute([$id]);
    
    if (!$result) {
        throw new Exception("Error al eliminar el registro de la base de datos");
    }
    
    $rowsAffected = $stmt->rowCount();
    if ($rowsAffected === 0) {
        throw new Exception("No se encontró el registro con ID: $id");
    }

    echo json_encode([
        'success' => true, 
        'message' => 'Elemento eliminado correctamente',
        'id' => $id,
        'type' => $type
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage(),
        'debug' => [
            'id' => $id ?? null,
            'type' => $type ?? null,
            'table' => $table ?? null
        ]
    ]);
}
?>