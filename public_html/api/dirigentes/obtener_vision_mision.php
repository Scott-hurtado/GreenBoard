<?php
// Desactivar salida de errores en pantalla para no romper el JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// 1. Carga del sistema (Ajusta la ruta si tu estructura es diferente)
require_once __DIR__ . '/../../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // 2. Seguridad: Verificar sesión y roles permitidos
    // Permitimos acceso tanto a admins (para ver qué editar) como a coordinadores (para ver su plan)
    session_start(); // Asegurar sesión iniciada si bootstrap no lo hizo
    $rol = $_SESSION['user_role'] ?? '';
    
    $roles_permitidos = ['admin', 'administrador', 'superadmin', 'coordinador', 'coordinador_estatal'];

    if (!in_array($rol, $roles_permitidos)) {
        echo json_encode([
            'success' => false,
            'message' => 'No autorizado.',
            'vision' => 'No disponible',
            'mision' => 'No disponible'
        ]);
        exit();
    }

    // 3. Validar parámetro de entrada
    $estado = $_GET['estado'] ?? '';

    if (empty($estado)) {
        echo json_encode([
            'success' => false,
            'message' => 'Parámetro estado faltante.',
            'vision' => '',
            'mision' => ''
        ]);
        exit();
    }

    // 4. Conexión y Consulta
    $db = Database::getInstance()->getConnection();
    
    // Leemos de la tabla unificada 'geo_vision_mision'
    $stmt = $db->prepare("SELECT vision, mision FROM geo_vision_mision WHERE estado = :edo LIMIT 1");
    $stmt->execute([':edo' => $estado]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    // 5. Respuesta
    if ($data) {
        // Si existen datos, los devolvemos
        echo json_encode([
            'success' => true,
            'vision' => $data['vision'],
            'mision' => $data['mision']
        ]);
    } else {
        // Si no existen (fila vacía), devolvemos textos por defecto
        echo json_encode([
            'success' => true, // Es success porque la consulta funcionó, solo que está vacío
            'vision' => "La visión para $estado aún no ha sido definida.",
            'mision' => "La misión para $estado aún no ha sido definida."
        ]);
    }

} catch (PDOException $e) {
    // Error de base de datos
    error_log("Error DB en obtener_vision_mision: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error de conexión a base de datos',
        'vision' => 'Error al cargar',
        'mision' => 'Error al cargar'
    ]);
} catch (Exception $e) {
    // Error general
    error_log("Error General en obtener_vision_mision: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error del servidor',
        'vision' => 'Error al cargar',
        'mision' => 'Error al cargar'
    ]);
}
?>