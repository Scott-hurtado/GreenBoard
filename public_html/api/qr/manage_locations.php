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

    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'delete_qr':
            $estado = trim($_POST['estado'] ?? '');
            $municipio = trim($_POST['municipio'] ?? '');

            if (empty($estado) || empty($municipio)) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Estado y municipio son requeridos']);
                exit;
            }

            // Obtener la ruta del archivo antes de eliminar
            $stmt = $pdo->prepare("SELECT archivo_path FROM gestion_qr WHERE estado = ? AND municipio = ?");
            $stmt->execute([$estado, $municipio]);
            $qr = $stmt->fetch();

            if ($qr && $qr['archivo_path']) {
                // Eliminar archivo físico
                $filePath = __DIR__ . '/../../' . ltrim($qr['archivo_path'], '/');
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }

            // Eliminar de la base de datos
            $stmt = $pdo->prepare("DELETE FROM gestion_qr WHERE estado = ? AND municipio = ?");
            $stmt->execute([$estado, $municipio]);

            ob_clean();
            echo json_encode(['success' => true, 'message' => 'QR eliminado correctamente']);
            break;

        case 'update_location':
            $oldEstado = trim($_POST['old_estado'] ?? '');
            $oldMunicipio = trim($_POST['old_municipio'] ?? '');
            $newEstado = trim($_POST['new_estado'] ?? '');
            $newMunicipio = trim($_POST['new_municipio'] ?? '');

            if (empty($oldEstado) || empty($oldMunicipio) || empty($newEstado) || empty($newMunicipio)) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Todos los campos son requeridos']);
                exit;
            }

            // Actualizar en la base de datos
            $stmt = $pdo->prepare("UPDATE gestion_qr SET estado = ?, municipio = ? WHERE estado = ? AND municipio = ?");
            $stmt->execute([$newEstado, $newMunicipio, $oldEstado, $oldMunicipio]);

            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Ubicación actualizada correctamente']);
            break;

        default:
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
            break;
    }

} catch (Exception $e) {
    ob_clean();
    error_log('Error en manage_locations.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor',
        'error' => $e->getMessage()
    ]);
}
?>