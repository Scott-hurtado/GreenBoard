<?php
// api/dirigentes/editar_tarea.php
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
        
        $id = $_POST['id'] ?? null;
        
        if (empty($id) || !is_numeric($id)) {
            echo json_encode(['success' => false, 'message' => 'ID inválido']);
            exit;
        }
        
        // Obtener la categoría de la actividad del banco
        $stmtAgenda = $db->prepare("SELECT agenda FROM banco_actividades WHERE id = :id");
        $stmtAgenda->execute([':id' => $_POST['id_actividad']]);
        $actividad = $stmtAgenda->fetch(PDO::FETCH_ASSOC);
        
        $categoria = $actividad['agenda'] ?? 'social';
        
        // CORRECCIÓN: Se eliminó 'formulario_vinculado = :formulario' del SQL y del array execute
        $sql = "UPDATE dirigentes_actividades SET
                estado = :estado,
                id_actividad_banco = :id_actividad,
                mes_objetivo = :mes,
                impacto_estimado = :impacto,
                comentarios = :comentarios,
                categoria = :categoria
                WHERE id = :id";
        
        $stmt = $db->prepare($sql);
        $result = $stmt->execute([
            ':id' => $id,
            ':estado' => $_POST['estado'],
            ':id_actividad' => $_POST['id_actividad'],
            ':mes' => $_POST['mes'],
            ':impacto' => $_POST['impacto'],
            ':comentarios' => $_POST['comentarios'],
            ':categoria' => $categoria
        ]);

        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Tarea actualizada correctamente']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al actualizar en BD']);
        }

    } catch (Exception $e) {
        error_log("Error en editar_tarea: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}
?>