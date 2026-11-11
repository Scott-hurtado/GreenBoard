<?php
/**
 * API GESTIÓN - UPDATE
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../includes/bootstrap.php';

// 1. Seguridad: Verificar Admin
$rol = $_SESSION['user_role'] ?? '';
if (!in_array($rol, ['admin', 'administrador', 'superadmin', 'super admin'])) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // 2. Recibir datos JSON
    $input = json_decode(file_get_contents('php://input'), true);
    
    $id = $input['id'] ?? null;
    $type = $input['type'] ?? '';
    $title = $input['title'] ?? '';
    $desc = $input['desc'] ?? '';
    
    // Datos específicos de Cronograma
    $date = $input['date'] ?? ''; 
    $sortDate = $input['sort_date'] ?? ''; 

    // 3. Validaciones
    if (empty($id) && $type !== 'vision' && $type !== 'mision') {
        throw new Exception("ID del elemento no proporcionado");
    }

    // 4. Lógica de Actualización según Tipo
    if ($type === 'objetivo') {
        $sql = "UPDATE geo_objetivos SET titulo = ?, descripcion = ? WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$title, $desc, $id]);

    } elseif ($type === 'estrategia') {
        $sql = "UPDATE geo_estrategias SET titulo = ?, descripcion = ? WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$title, $desc, $id]);

    } elseif ($type === 'meta') {
        // CORRECCIÓN: geo_metas NO tiene columna 'descripcion', solo 'titulo'
        $sql = "UPDATE geo_metas SET titulo = ? WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$title, $id]);

    } elseif ($type === 'cronograma') {
        // Para cronograma actualizamos también las fechas
        $sql = "UPDATE geo_cronograma SET titulo = ?, descripcion = ?, fecha_visual = ?, fecha_orden = ? WHERE id = ?";
        
        // Si no envía fecha de orden, mantenemos la que ya estaba o usamos hoy (depende de tu lógica, aquí forzamos valor si viene vacío)
        // Lo ideal es que el frontend siempre mande el valor.
        if (empty($sortDate)) {
            // Si el usuario borró la fecha, no actualizamos ese campo (query alternativo)
            $sql = "UPDATE geo_cronograma SET titulo = ?, descripcion = ?, fecha_visual = ? WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$title, $desc, $date, $id]);
        } else {
            $stmt = $db->prepare($sql);
            $stmt->execute([$title, $desc, $date, $sortDate, $id]);
        }

    } elseif ($type === 'vision' || $type === 'mision') {
        // Visión y Misión suelen ser registros únicos en una tabla de configuración
        // Usamos ON DUPLICATE KEY UPDATE por si acaso no existía
        $sql = "INSERT INTO geo_configuracion (clave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$type, $desc, $desc]);
        
    } else {
        throw new Exception("Tipo de elemento inválido para editar.");
    }

    echo json_encode(['success' => true, 'message' => 'Elemento actualizado correctamente']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>