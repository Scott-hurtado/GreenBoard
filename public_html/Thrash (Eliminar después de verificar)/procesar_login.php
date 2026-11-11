<?php
session_start();
require_once 'db.php';

// Verificar que se envió el formulario por POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: iniciar_sesion.php');
    exit();
}

// Obtener y limpiar datos del formulario
$correo = trim(strtolower($_POST['correo'] ?? ''));
$contrasena = $_POST['contrasena'] ?? '';

// Validar que los campos no estén vacíos
if (empty($correo) || empty($contrasena)) {
    header('Location: iniciar_sesion.php?error=campos_vacios');
    exit();
}

try {
    $db = getDB();
    
    // Buscar usuario por correo
    $stmt = $db->prepare("SELECT id, nombre_completo, correo, contrasena, rol, activo FROM usuarios WHERE correo = ?");
    $stmt->execute([$correo]);
    
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Verificar si el usuario existe
    if (!$usuario) {
        header('Location: iniciar_sesion.php?error=credenciales_invalidas');
        exit();
    }
    
    // Verificar la contraseña
    if (!password_verify($contrasena, $usuario['contrasena'])) {
        header('Location: iniciar_sesion.php?error=credenciales_invalidas');
        exit();
    }
    
    // Verificar si la cuenta está activa
    if ($usuario['activo'] != 1) {
        header('Location: iniciar_sesion.php?error=cuenta_inactiva');
        exit();
    }
    
    // Actualizar último acceso
    $update_stmt = $db->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?");
    $update_stmt->execute([$usuario['id']]);
    
    // LIMPIAR CUALQUIER SESIÓN ANTERIOR
    $_SESSION = array();
    
    // Crear sesión SOLO con datos de usuario
    $_SESSION['user_id'] = $usuario['id'];
    $_SESSION['nombre_completo'] = $usuario['nombre_completo'];
    $_SESSION['correo'] = $usuario['correo'];
    $_SESSION['rol'] = $usuario['rol'];
    $_SESSION['login_time'] = time();
    // NO establecer is_admin para usuarios normales
    
    // Regenerar ID de sesión por seguridad
    session_regenerate_id(true);
    
    // Redirigir al dashboard
    header('Location: ./pages/citizens/dashboard_CiudadanoPage.php');
    exit();
    
} catch (PDOException $e) {
    error_log("Error en login: " . $e->getMessage());
    header('Location: iniciar_sesion.php?error=error_servidor');
    exit();
}
?>