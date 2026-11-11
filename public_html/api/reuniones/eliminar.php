<?php
ini_set('display_errors', 0); 
ini_set('log_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

try {
    // 1. CARGA DEL SISTEMA (Igual que en tus otros archivos)
    if (file_exists(__DIR__ . '/../../includes/bootstrap.php')) {
        require_once __DIR__ . '/../../includes/bootstrap.php';
    } else {
        // Fallback manual si no encuentra el bootstrap
        require_once __DIR__ . '/../../includes/database.php';
        if (session_status() === PHP_SESSION_NONE) session_start();
    }

    // 2. SEGURIDAD
    $rol = $_SESSION['user_role'] ?? '';
    $permitidos = ['admin', 'administrador', 'superadmin', 'super admin', 'coordinador_estatal'];

    if (!in_array($rol, $permitidos)) {
        throw new Exception('No tienes permisos para realizar esta acción.');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido.');
    }

    // 3. OBTENER DATOS
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? null;

    if (!$id) {
        throw new Exception('ID de reunión no proporcionado.');
    }

    // 4. LÓGICA DE BASE DE DATOS
    $db = Database::getInstance()->getConnection();
    
    // Iniciar transacción
    $db->beginTransaction();

    // A. Verificar que la reunión existe
    $checkStmt = $db->prepare("SELECT * FROM reuniones WHERE id = ?");
    $checkStmt->execute([$id]);
    $reunion = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$reunion) {
        throw new Exception('La reunión no existe o ya fue eliminada.');
    }

    // B. Copiar a la tabla de eliminadas
    // Usamos una sintaxis dinámica para insertar exactamente las columnas que tenga la tabla original
    // Esto evita errores si en el futuro agregas columnas a 'reuniones' y olvidas actualizar este archivo.
    
    // Obtenemos las columnas de la tabla original dinámicamente
    $columns = array_keys($reunion);
    $columnList = implode(", ", $columns);
    $placeholders = implode(", ", array_fill(0, count($columns), "?"));
    
    // Construimos el INSERT usando los valores obtenidos en el paso A
    $sqlCopy = "INSERT INTO reuniones_eliminadas ($columnList, fecha_eliminacion) VALUES ($placeholders, NOW())";
    
    $stmtCopy = $db->prepare($sqlCopy);
    // Ejecutamos pasando los valores del array original
    $stmtCopy->execute(array_values($reunion));

    // C. Eliminar de la tabla principal
    $stmtDelete = $db->prepare("DELETE FROM reuniones WHERE id = ?");
    $stmtDelete->execute([$id]);

    // Confirmar cambios
    $db->commit();

    echo json_encode(['success' => true, 'message' => 'Reunión enviada a la papelera.']);

} catch (Exception $e) {
    // Revertir cambios si hubo error en la DB
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    // Enviar error como JSON válido (código 200 para que JS lo lea, pero success false)
    // Opcional: http_response_code(500) si prefieres manejarlo como error de servidor
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?>