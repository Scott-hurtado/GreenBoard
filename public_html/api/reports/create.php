<?php
/**
 * API para crear informes manuales - CORREGIDO
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

    // Validar datos recibidos desde el FormData de JS
    $titulo   = trim($_POST['title'] ?? '');
    $tipo     = trim($_POST['tipo'] ?? '');    // Categoría (Comunicación, Avance, etc.)
    $periodo  = trim($_POST['periodo'] ?? ''); // Frecuencia (Mensual, Anual, etc.)
    $estado   = trim($_POST['status'] ?? '');
    $autor    = trim($_POST['author'] ?? '');
    $contenido = $_POST['content'] ?? '';

    // Verificación de campos obligatorios
    if (empty($titulo) || empty($tipo) || empty($periodo) || empty($estado) || empty($autor) || empty($contenido)) {
        throw new Exception("Faltan campos requeridos (Título, Tipo, Periodo, Estado, Autor o Contenido).");
    }

    $db = Database::getInstance()->getConnection();
    
    // SQL actualizado para incluir la columna 'periodo'
    $stmt = $db->prepare("INSERT INTO informes (titulo, creado_por, fecha_creacion, tipo, periodo, estado, contenido, archivo_url) 
                         VALUES (:titulo, :autor, CURDATE(), :tipo, :periodo, :estado, :contenido, NULL)");
    
    $stmt->bindParam(':titulo', $titulo);
    $stmt->bindParam(':autor', $autor);
    $stmt->bindParam(':tipo', $tipo);
    $stmt->bindParam(':periodo', $periodo);
    $stmt->bindParam(':estado', $estado);
    $stmt->bindParam(':contenido', $contenido);
    $stmt->execute();

    $lastId = $db->lastInsertId();

    echo json_encode([
        'success' => true, 
        'message' => 'Informe creado con éxito.', 
        'id' => $lastId, 
        'date' => date('d/M/Y')
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (PDOException $e) {
    error_log("Error de DB en create.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
}