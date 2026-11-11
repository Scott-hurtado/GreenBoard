<?php
// reset.php - Mata cualquier sesión activa
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 1. Cargar configuración básica
$bootstrap = __DIR__ . '/includes/bootstrap.php';
if (file_exists($bootstrap)) {
    require_once $bootstrap;
} else {
    // Si no encuentra el bootstrap, iniciamos sesión manual para poder matarla
    if (session_status() === PHP_SESSION_NONE) session_start();
}

// 2. Vaciar la sesión
$_SESSION = array();

// 3. Matar la cookie de sesión
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. Destruir la sesión
session_destroy();

echo "<h1>✅ SISTEMA REINICIADO</h1>";
echo "<p>Se han borrado todas las sesiones y cookies.</p>";
echo "<p><a href='public/iniciar-sesion.php'>Haz clic aquí para intentar entrar de nuevo</a></p>";
?>