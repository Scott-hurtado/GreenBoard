<?php
/**
 * API para eliminar informes
 */
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../../includes/bootstrap.php';

    // Seguridad: Solo administradores
    $rol = $_SESSION['user_role'] ?? '';
    if (!in_array($rol, ['admin', 'administrador', 'superadmin'])) {
        throw new Exception("Acceso denegado. Se requieren permisos de administrador.");
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Método no permitido");
    }

    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    if (!$id) {
        throw new Exception("ID de informe no válido.");
    }

    $db = Database::getInstance()->getConnection();

    // Obtener información del archivo antes de borrar
    $stmtFile = $db->prepare("SELECT archivo_url FROM informes WHERE id = :id");
    $stmtFile->bindParam(':id', $id, PDO::PARAM_INT);
    $stmtFile->execute();
    $fileResult = $stmtFile->fetch(PDO::FETCH_ASSOC);

    if ($fileResult && $fileResult['archivo_url']) {
        // Eliminar el archivo físico
        // La URL puede ser /informes/... o /storage/reports/...
        $fileUrl = $fileResult['archivo_url'];
        
        // Si es una URL relativa que empieza con /storage/reports/, usar STORAGE_PATH
        if (strpos($fileUrl, '/storage/reports/') === 0) {
            $fileName = basename($fileUrl);
            $filePath = STORAGE_PATH . '/reports/' . $fileName;
        } elseif (strpos($fileUrl, '/informes/') === 0) {
            // Compatibilidad con rutas antiguas
            $fileName = basename($fileUrl);
            $filePath = dirname(dirname(dirname(__DIR__))) . '/informes/' . $fileName;
        } else {
            // Intentar extraer el nombre del archivo
            $fileName = basename($fileUrl);
            $filePath = STORAGE_PATH . '/reports/' . $fileName;
        }
        
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    // Eliminar de BD
    $stmt = $db->prepare("DELETE FROM informes WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Informe eliminado correctamente.']);
    } else {
        throw new Exception("Informe no encontrado.");
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (PDOException $e) {
    error_log("Error de DB en delete.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Error de base de datos.']);
}

