<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

try {
    require_once __DIR__ . '/../../includes/bootstrap.php';
} catch (Exception $e) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error en bootstrap: ' . $e->getMessage()]);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    if (!isset($pdo)) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'ERROR: Variable $pdo no está definida']);
        exit;
    }

    $estado = trim($_POST['estado'] ?? '');
    $municipio = trim($_POST['municipio'] ?? '');

    if (empty($estado) || empty($municipio)) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Estado y municipio son requeridos']);
        exit;
    }

    // Verificar si ya existe
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM gestion_qr WHERE estado = ? AND municipio = ?");
    $stmt->execute([$estado, $municipio]);
    $exists = $stmt->fetchColumn();

    if ($exists > 0) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Esta combinación de Estado y Municipio ya existe']);
        exit;
    }

    // Insertar sin QR (archivo_path NULL)
    $stmt = $pdo->prepare("INSERT INTO gestion_qr (estado, municipio, archivo_path) VALUES (?, ?, NULL)");
    $stmt->execute([$estado, $municipio]);

    ob_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Ubicación agregada correctamente. Ahora puedes subir su QR.',
        'estado' => $estado,
        'municipio' => $municipio
    ]);

} catch (Exception $e) {
    ob_clean();
    error_log('Error en add_location.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor',
        'error' => $e->getMessage()
    ]);
}
?>