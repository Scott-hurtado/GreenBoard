<?php
// api/reuniones/procesar.php

// 1. CARGA DEL SISTEMA (Igual que en banco de actividades)
// Esto carga automáticamente la clase Database y la sesión
require_once __DIR__ . '/../../includes/bootstrap.php';

// 2. CONFIGURACIÓN DE CABECERAS
header('Content-Type: application/json; charset=utf-8');

// 3. SEGURIDAD (Verificar permisos)
// bootstrap.php ya inicia la sesión, así que podemos acceder a $_SESSION directamente
$rol = $_SESSION['user_role'] ?? '';
$permitidos = ['admin', 'administrador', 'superadmin', 'super admin', 'coordinador_estatal'];

if (!in_array($rol, $permitidos)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No tienes permiso para realizar esta acción.']);
    exit;
}

// 4. RECIBIR DATOS
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (empty($data)) {
    echo json_encode(['success' => false, 'message' => 'No se recibieron datos válidos.']);
    exit;
}

try {
    // 5. CONEXIÓN A BASE DE DATOS (ESTILO BANCO DE ACTIVIDADES)
    // Usamos el Singleton Database::getInstance() tal como en tu otro archivo
    $db = Database::getInstance()->getConnection();

    // 6. INSERTAR DATOS
    $sql = "INSERT INTO reuniones (titulo, fecha, asistentes, temas, descripcion, acuerdos, creado_por) 
            VALUES (:titulo, :fecha, :asistentes, :temas, :descripcion, :acuerdos, :creado_por)";
    
    $stmt = $db->prepare($sql);

    // Obtener usuario actual de la sesión
    $usuario_actual = $_SESSION['user_name'] ?? $_SESSION['nombre'] ?? 'Admin';

    // Ejecutar consulta
    $stmt->execute([
        ':titulo'      => $data['titulo'],
        ':fecha'       => $data['fecha'],
        ':asistentes'  => $data['asistentes'] ?? '',
        ':temas'       => $data['temas'] ?? '',
        ':descripcion' => $data['descripcion'] ?? '',
        ':acuerdos'    => $data['acuerdos'] ?? '[]',
        ':creado_por'  => $usuario_actual
    ]);

    // 7. RESPUESTA EXITOSA
    echo json_encode([
        'success' => true, 
        'message' => 'Reunión guardada correctamente',
        'id' => $db->lastInsertId()
    ]);

} catch (Exception $e) {
    // Manejo de errores
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Error del servidor: ' . $e->getMessage()
    ]);
}
?>