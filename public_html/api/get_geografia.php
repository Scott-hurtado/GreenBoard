<?php
require_once __DIR__ . '/../includes/bootstrap.php'; // Ajusta la ruta según tu proyecto
header('Content-Type: application/json; charset=utf-8');

// Inicializar la conexión a la base de datos
global $pdo; 
if (!$pdo && class_exists('Database')) {
    $pdo = Database::getInstance()->getConnection();
}

$action = $_GET['action'] ?? '';

try {
    // Obtener Regiones por Estado
    if ($action === 'get_regiones') {
        $estado = trim($_GET['estado'] ?? '');
        $stmt = $pdo->prepare("SELECT id, nombre FROM geo_regiones WHERE estado = ? ORDER BY nombre ASC");
        $stmt->execute([$estado]);
        $regiones = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'regiones' => $regiones]);
        exit();
    }

    // Obtener Zonas por Región
    if ($action === 'get_zonas') {
        $region_id = intval($_GET['region_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT id, nombre, clave FROM geo_zonas WHERE region_id = ? ORDER BY nombre ASC");
        $stmt->execute([$region_id]);
        $zonas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'zonas' => $zonas]);
        exit();
    }
    
    echo json_encode(['success' => false, 'error' => 'Acción no válida']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error SQL: ' . $e->getMessage()]);
}
?>