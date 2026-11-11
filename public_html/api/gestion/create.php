<?php
/**
 * API GESTIÓN - CREATE
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../includes/bootstrap.php';

// Verificar Admin
$rol = $_SESSION['user_role'] ?? '';
if (!in_array($rol, ['admin', 'administrador', 'superadmin', 'super admin'])) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Recibir datos JSON
    $input = json_decode(file_get_contents('php://input'), true);
    
    $type = $input['type'] ?? '';
    $title = $input['title'] ?? '';
    $desc = $input['desc'] ?? '';
    
    // Datos específicos de Cronograma
    $date = $input['date'] ?? ''; // Etiqueta visual (Ej: Enero)
    $sortDate = $input['sort_date'] ?? ''; // Fecha real (Ej: 2026-01-17)

    // Validación básica (Visión y Misión no siempre requieren título visible, pero la DB sí)
    if (empty($type)) {
        throw new Exception("Tipo de elemento no definido");
    }
    
    // Si es objetivo, estrategia, meta o cronograma, el título es obligatorio
    if (in_array($type, ['objetivo', 'estrategia', 'meta', 'cronograma']) && empty($title)) {
        throw new Exception("El título es obligatorio");
    }

    $id = 0;

    if ($type === 'objetivo') {
        $sql = "INSERT INTO geo_objetivos (titulo, descripcion, activo, fecha_creacion) VALUES (?, ?, 1, NOW())";
        $stmt = $db->prepare($sql);
        $stmt->execute([$title, $desc]);
        $id = $db->lastInsertId();

    } elseif ($type === 'estrategia') {
        $sql = "INSERT INTO geo_estrategias (titulo, descripcion, activo, fecha_creacion) VALUES (?, ?, 1, NOW())";
        $stmt = $db->prepare($sql);
        $stmt->execute([$title, $desc]);
        $id = $db->lastInsertId();

    } elseif ($type === 'meta') {
        // CORRECCIÓN: geo_metas NO tiene columna 'descripcion', solo 'titulo'
        $sql = "INSERT INTO geo_metas (titulo, activo, fecha_registro) VALUES (?, 1, NOW())";
        $stmt = $db->prepare($sql);
        $stmt->execute([$title]);
        $id = $db->lastInsertId();

    } elseif ($type === 'cronograma') {
        $sql = "INSERT INTO geo_cronograma (titulo, descripcion, fecha_visual, fecha_orden, activo) 
                VALUES (?, ?, ?, ?, 1)";
        
        // CORRECCIÓN AQUÍ: Usamos $sortDate y las variables definidas arriba ($title, $desc)
        // en lugar de $data['...'] que no existía.
        $fechaOrden = !empty($sortDate) ? $sortDate : date('Y-m-d');
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $title, 
            $desc, 
            $date,       // Etiqueta Visual
            $fechaOrden  // Fecha SQL
        ]);
        $id = $db->lastInsertId();

    } elseif ($type === 'vision' || $type === 'mision') {
        // Lógica para visión/misión (update o insert en config)
        $clave = $type; // 'vision' o 'mision'
        // Primero borramos o actualizamos. Asumiremos UPDATE o INSERT ON DUPLICATE
        $sql = "INSERT INTO geo_configuracion (clave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$clave, $desc, $desc]); // Usamos descripción como el valor
        $id = 1;
        
    } else {
        throw new Exception("Tipo inválido: " . $type);
    }

    echo json_encode(['success' => true, 'id' => $id, 'message' => 'Guardado correctamente']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>