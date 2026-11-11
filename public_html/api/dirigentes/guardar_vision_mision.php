<?php
// Activar reporte de errores para debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// RUTA RELATIVA DE BOOTSTRAP
require_once __DIR__ . '/../../includes/bootstrap.php';

header('Content-Type: application/json');

try {
    // 1. Verificar autenticación (Permitir Coordinadores y Admins)
    $rol = $_SESSION['user_role'] ?? '';
    $permitidos = ['coordinador_estatal', 'admin', 'administrador', 'superadmin'];
    
    if (!in_array($rol, $permitidos)) {
        echo json_encode([
            'success' => false, 
            'message' => 'No autorizado. Tu rol actual es: ' . ($rol ?: 'sin sesión')
        ]);
        exit();
    }

    $db = Database::getInstance()->getConnection();
    
    $estado = $_POST['estado'] ?? '';
    $vision = $_POST['vision'] ?? '';
    $mision = $_POST['mision'] ?? '';
    
    if (empty($estado)) {
        echo json_encode(['success' => false, 'message' => 'El estado es requerido']);
        exit();
    }
    
    // 2. Verificar tabla COMPARTIDA (geo_vision_mision)
    // Nota: Esta es la misma tabla que usa el Admin en gestion_estatal.php
    $tableCheck = $db->query("SHOW TABLES LIKE 'geo_vision_mision'");
    if ($tableCheck->rowCount() === 0) {
        // Intentar crearla si no existe (Fail-safe)
        $db->exec("CREATE TABLE IF NOT EXISTS geo_vision_mision (
            id INT AUTO_INCREMENT PRIMARY KEY,
            estado VARCHAR(100) NOT NULL,
            vision TEXT,
            mision TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_estado (estado)
        )");
    }
    
    // 3. Insertar o actualizar en la tabla COMPARTIDA
    $stmt = $db->prepare("
        INSERT INTO geo_vision_mision (estado, vision, mision) 
        VALUES (:estado, :vision, :mision)
        ON DUPLICATE KEY UPDATE 
            vision = :vision2, 
            mision = :mision2
    ");
    
    $result = $stmt->execute([
        ':estado' => $estado,
        ':vision' => $vision,
        ':mision' => $mision,
        ':vision2' => $vision,
        ':mision2' => $mision
    ]);
    
    if ($result) {
        echo json_encode([
            'success' => true, 
            'message' => 'Visión y misión actualizadas correctamente'
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'No se pudieron guardar los cambios en la base de datos.'
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Error PDO guardando VM: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error de base de datos: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Error guardando VM: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error del servidor: ' . $e->getMessage()
    ]);
}
?>