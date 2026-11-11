<?php
// api/dirigentes/obtener_tema.php
require_once __DIR__ . '/../../includes/bootstrap.php';
header('Content-Type: application/json');

$estado = $_GET['estado'] ?? '';
$mes = $_GET['mes'] ?? '';

if (empty($estado) || empty($mes)) {
    echo json_encode(['tema' => '']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    $sql = "SELECT tema FROM dirigentes_temas WHERE estado = :estado AND mes = :mes";
    $stmt = $db->prepare($sql);
    $stmt->execute([':estado' => $estado, ':mes' => $mes]);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($resultado) {
        echo json_encode(['tema' => $resultado['tema']]);
    } else {
        echo json_encode(['tema' => '']);
    }

} catch (Exception $e) {
    error_log("Error en obtener_tema: " . $e->getMessage());
    echo json_encode(['tema' => '', 'error' => $e->getMessage()]);
}
?>