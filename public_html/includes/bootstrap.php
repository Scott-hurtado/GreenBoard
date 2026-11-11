<?php
/**
 * Bootstrap - Inicialización del sistema (VERSIÓN ROBUSTA)
 */

// 1. Definir rutas base
define('INCLUDES_PATH', __DIR__);             
define('PUBLIC_PATH', dirname(__DIR__)); // Esto es public_html

// 2. DETECCIÓN AUTOMÁTICA DE APP_PRIVATE
// Primero busca DENTRO de public_html
if (is_dir(PUBLIC_PATH . '/app_private')) {
    define('PRIVATE_PATH', PUBLIC_PATH . '/app_private');
} 
// Si no, busca FUERA de public_html (un nivel arriba)
elseif (is_dir(dirname(PUBLIC_PATH) . '/app_private')) {
    define('PRIVATE_PATH', dirname(PUBLIC_PATH) . '/app_private');
} 
// Si falla, asumimos dentro para mostrar el error en la ruta esperada
else {
    define('PRIVATE_PATH', PUBLIC_PATH . '/app_private');
}

define('STORAGE_PATH', PUBLIC_PATH . '/storage');

// 3. Cargar configuración
$configFile = PRIVATE_PATH . '/config/app.php';

// Debug preventivo: Si no encuentra la carpeta private, avisar aquí mismo
if (!is_dir(PRIVATE_PATH)) {
    die("<h1>Error de Configuración</h1><p>No encuentro la carpeta <b>app_private</b>.</p><p>El sistema la buscó en: " . PRIVATE_PATH . "</p>");
}

// 4. Autoloader de clases (Aquí estaba fallando)
spl_autoload_register(function ($class) {
    // Convertir nombre de clase a ruta de archivo
    $file = PRIVATE_PATH . '/classes/' . $class . '.php';
    
    if (file_exists($file)) {
        require_once $file;
    } else {
        // DEBUG SILENCIOSO: Si falla, descomenta la siguiente línea para ver qué busca
        // echo "";
    }
});

// Cargar configuración si existe
if (file_exists($configFile)) {
    $appConfig = require $configFile;
} else {
    $appConfig = ['environment' => 'development', 'timezone' => 'America/Mexico_City'];
}

// Configurar entorno
if (isset($appConfig['environment']) && $appConfig['environment'] === 'production') {
    ini_set('display_errors', '0');
    error_reporting(0);
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

// Timezone
date_default_timezone_set($appConfig['timezone'] ?? 'America/Mexico_City');

// Inicializar sesión
if (session_status() === PHP_SESSION_NONE) {
    // Forzar cookie para todo el sitio (fix: sesión se pierde al navegar entre módulos)
    ini_set("session.cookie_path", "/");
    ini_set("session.cookie_httponly", "1");
    ini_set("session.use_strict_mode", "1");
    ini_set("session.gc_maxlifetime", "7200");
    ini_set("session.cookie_lifetime", "0");
    session_name("GBSESSION");
    session_start();
}

// ============================================
// 5. CREAR CONEXIÓN PDO (NUEVAS LÍNEAS)
// ============================================
try {
    $pdo = Database::getInstance()->getConnection();
} catch (Exception $e) {
    if (isset($appConfig['environment']) && $appConfig['environment'] === 'production') {
        die('Error de conexión a la base de datos.');
    } else {
        die('<h1>Error de Conexión</h1><p>' . $e->getMessage() . '</p>');
    }
}