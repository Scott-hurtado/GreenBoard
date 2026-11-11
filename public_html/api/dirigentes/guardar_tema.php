<?php
// api/dirigentes/guardar_tema.php
require_once __DIR__ . '/../../includes/bootstrap.php';

header('Content-Type: application/json');

// Verificar sesión
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'administrador', 'superadmin', 'super admin'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = Database::getInstance()->getConnection();
        
        $estado = $_POST['estado'] ?? '';
        $mes = $_POST['mes'] ?? '';
        $tema = $_POST['tema'] ?? '';
        
        if (empty($estado) || empty($mes)) {
            echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
            exit;
        }
        
        // Verificar si ya existe un registro para este estado y mes
        $sqlCheck = "SELECT id FROM dirigentes_temas WHERE estado = :estado AND mes = :mes";
        $stmtCheck = $db->prepare($sqlCheck);
        $stmtCheck->execute([':estado' => $estado, ':mes' => $mes]);
        $existe = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        
        if ($existe) {
            // Actualizar
            $sql = "UPDATE dirigentes_temas SET tema = :tema WHERE estado = :estado AND mes = :mes";
        } else {
            // Insertar
            $sql = "INSERT INTO dirigentes_temas (estado, mes, tema) VALUES (:estado, :mes, :tema)";
        }
        
        $stmt = $db->prepare($sql);
        $result = $stmt->execute([
            ':estado' => $estado,
            ':mes' => $mes,
            ':tema' => $tema
        ]);

        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Tema guardado correctamente']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al guardar']);
        }

    } catch (Exception $e) {
        error_log("Error en guardar_tema: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}
?>