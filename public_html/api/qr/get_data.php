<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
header('Content-Type: application/json');

try {
    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'get_estados':
            // Obtener todos los estados únicos de la tabla
            $stmt = $pdo->query("SELECT DISTINCT estado FROM gestion_qr ORDER BY estado ASC");
            $estados = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo json_encode($estados);
            break;

        case 'get_municipios':
            // Obtener municipios de un estado específico
            $estado = $_GET['estado'] ?? '';
            if (empty($estado)) {
                echo json_encode([]);
                exit;
            }
            $stmt = $pdo->prepare("SELECT DISTINCT municipio FROM gestion_qr WHERE estado = ? ORDER BY municipio ASC");
            $stmt->execute([$estado]);
            $municipios = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo json_encode($municipios);
            break;

        case 'get_qr':
            // Obtener el QR de un estado y municipio específico
            $estado = $_GET['estado'] ?? '';
            $municipio = $_GET['municipio'] ?? '';
            
            if (empty($estado) || empty($municipio)) {
                echo json_encode(['error' => 'Estado y municipio son requeridos']);
                exit;
            }

            $stmt = $pdo->prepare("SELECT archivo_path, fecha_creacion FROM gestion_qr WHERE estado = ? AND municipio = ? LIMIT 1");
            $stmt->execute([$estado, $municipio]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                echo json_encode($result);
            } else {
                echo json_encode(['error' => 'No se encontró QR para esta combinación']);
            }
            break;

        default:
            echo json_encode(['error' => 'Acción no válida']);
            break;
    }

} catch (Exception $e) {
    error_log('Error en get_data.php: ' . $e->getMessage());
    echo json_encode(['error' => 'Error interno del servidor: ' . $e->getMessage()]);
}
?>