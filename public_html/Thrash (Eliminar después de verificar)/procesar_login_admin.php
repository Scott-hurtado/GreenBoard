<?php
session_start();
require_once 'db.php';

// Verificar que se envió el formulario por POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: lo.php');
    exit();
}

// Obtener y limpiar datos del formulario
$correo = trim(strtolower($_POST['correo'] ?? ''));
$contrasena = $_POST['contrasena'] ?? '';

// Validar que los campos no estén vacíos
if (empty($correo) || empty($contrasena)) {
    header('Location: lo.php?error=campos_vacios');
    exit();
}

try {
    $db = getDB();
    
    // Buscar administrador por correo
    $stmt = $db->prepare("SELECT id, nombre_completo, correo, contrasena, rol, activo FROM administradores WHERE correo = ?");
    $stmt->execute([$correo]);
    
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Verificar si el administrador existe
    if (!$admin) {
        // Añadir delay para prevenir ataques de fuerza bruta
        sleep(2);
        header('Location: lo.php?error=credenciales_invalidas');
        exit();
    }
    
    // Verificar la contraseña
    if (!password_verify($contrasena, $admin['contrasena'])) {
        // Añadir delay para prevenir ataques de fuerza bruta
        sleep(2);
        header('Location: lo.php?error=credenciales_invalidas');
        exit();
    }
    
    // Verificar si la cuenta está activa
    if ($admin['activo'] != 1) {
        header('Location: lo.php?error=cuenta_inactiva');
        exit();
    }
    
    // Actualizar último acceso
    $update_stmt = $db->prepare("UPDATE administradores SET ultimo_acceso = NOW() WHERE id = ?");
    $update_stmt->execute([$admin['id']]);
    
    // Crear sesión de administrador
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_nombre'] = $admin['nombre_completo'];
    $_SESSION['admin_correo'] = $admin['correo'];
    $_SESSION['admin_rol'] = $admin['rol'];
    $_SESSION['admin_login_time'] = time();
    $_SESSION['is_admin'] = true;
    
    // Regenerar ID de sesión por seguridad
    session_regenerate_id(true);
    
    // Registrar el inicio de sesión (opcional, para auditoría)
    $log_stmt = $db->prepare("INSERT INTO logs_admin (admin_id, accion, ip, user_agent) VALUES (?, 'login', ?, ?)");
    $log_stmt->execute([
        $admin['id'],
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
    
    // Redirigir al dashboard
    header('Location: dashboard.php');
    exit();
    
} catch (PDOException $e) {
    error_log("Error en login admin: " . $e->getMessage());
    header('Location: lo.php?error=error_servidor');
    exit();
}
?>