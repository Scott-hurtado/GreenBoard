<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
session_start();
header('Content-Type: application/json');

try {
    $db = Database::getInstance()->getConnection();
    
    // 1. Validar datos de entrada
    $estado = $_POST['estado'] ?? '';
    $id_actividad = $_POST['id_actividad'] ?? ''; 
    $mes = $_POST['mes'] ?? '';
    
    if (empty($estado) || empty($id_actividad) || empty($mes)) {
        throw new Exception("Datos incompletos (Estado, Actividad o Mes faltantes).");
    }

    // 2. Obtener datos de la actividad desde el Banco
    $stmtB = $db->prepare("SELECT titulo FROM banco_actividades WHERE id = ?");
    $stmtB->execute([$id_actividad]);
    $actividad = $stmtB->fetch(PDO::FETCH_ASSOC);
    $nombre_actividad = $actividad['titulo'] ?? 'Actividad Asignada';

    // 3. DATOS DEL CREADOR (Sesión actual)
    $creado_por_id = $_SESSION['user_id'] ?? 0;
    $creado_por_nombre = $_SESSION['nombre_completo'] ?? 'Usuario Sistema';
    $creado_por_rol = $_SESSION['user_role'] ?? 'sistema';

    // 4. Insertar en la Base de Datos
    // CORRECCIÓN: Se eliminó cualquier referencia a 'formulario_vinculado'
    $sql = "INSERT INTO dirigentes_actividades 
            (estado, id_actividad_banco, nombre_actividad, mes_objetivo, impacto_estimado, categoria, estatus, comentarios, creado_por_id, creado_por_nombre, creado_por_rol, fecha_creacion) 
            VALUES (?, ?, ?, ?, ?, ?, 'pendiente', ?, ?, ?, ?, NOW())";
            
    $stmt = $db->prepare($sql);
    $stmt->execute([
        $estado,
        $id_actividad,
        $nombre_actividad,
        $mes,
        $_POST['impacto'] ?? '0',
        $_POST['categoria'] ?? 'social',
        $_POST['comentarios'] ?? '',
        $creado_por_id,
        $creado_por_nombre,
        $creado_por_rol
    ]);

    echo json_encode(['success' => true, 'message' => 'Actividad asignada correctamente']);

} catch (Exception $e) {
    error_log("Error en procesar_asignacion.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>