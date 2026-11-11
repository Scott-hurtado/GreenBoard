<?php
/**
 * API para actualizar informes manuales
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

    // Validar datos
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $titulo = trim($_POST['title'] ?? '');
    $tipo = trim($_POST['type'] ?? '');
    $estado = trim($_POST['status'] ?? '');
    $contenido = $_POST['content'] ?? '';
    $autor = trim($_POST['author'] ?? '');

    if ($id <= 0 || empty($titulo) || empty($tipo) || empty($estado) || empty($autor) || empty($contenido)) {
        throw new Exception("Faltan campos requeridos o ID no válido.");
    }

    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->prepare("UPDATE informes SET titulo = :titulo, tipo = :tipo, estado = :estado, contenido = :contenido, creado_por = :autor WHERE id = :id AND archivo_url IS NULL");
    
    $stmt->bindParam(':titulo', $titulo);
    $stmt->bindParam(':tipo', $tipo);
    $stmt->bindParam(':estado', $estado);
    $stmt->bindParam(':contenido', $contenido);
    $stmt->bindParam(':autor', $autor);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true, 
            'message' => 'Informe actualizado con éxito.', 
            'id' => $id, 
            'date' => date('d/M/Y')
        ]);
    } else {
        throw new Exception('El informe no pudo ser actualizado. Puede que no exista o que sea un PDF (no editable).');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (PDOException $e) {
    error_log("Error de DB en update.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Error de base de datos.']);
}

