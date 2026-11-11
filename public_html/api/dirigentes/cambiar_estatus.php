<?php
// Asegúrate de que esta ruta apunte a tu bootstrap correctamente
// __DIR__ es public_html/api/dirigentes
// ../.. nos lleva a public_html
require_once __DIR__ . '/../../includes/bootstrap.php';

// Si bootstrap.php NO inicia sesión, descomenta la siguiente línea:
// if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$id = $_POST['id'] ?? null;
$estatus = $_POST['estatus'] ?? null;
$validos = ['pendiente', 'en_proceso', 'completado', 'por_revisar'];

if (!$id || !in_array($estatus, $validos)) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit;
}

try {
    // Aseguramos que la clase Database existe antes de llamarla
    if (!class_exists('Database')) {
        throw new Exception('Clase Database no encontrada. Revisa los includes.');
    }

    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("UPDATE dirigentes_actividades SET estatus = ? WHERE id = ?");
    $stmt->execute([$estatus, $id]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500); // Opcional: enviar código de error servidor
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>