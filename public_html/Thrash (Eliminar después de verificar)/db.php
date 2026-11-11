<?php
/**
 * Archivo de conexión a la base de datos
 * Configurado para Hostinger
 */

// Configuración de la base de datos para Hostinger
define('DB_HOST', 'localhost'); // En Hostinger generalmente es 'localhost'
define('DB_NAME', 'u881824383_pverde'); // Cambia por tu nombre de base de datos
define('DB_USER', 'u881824383_proyverde'); // Cambia por tu usuario de base de datos
define('DB_PASS', 'PProyectoverde8#'); // Cambia por tu contraseña

// Configuración adicional
define('DB_CHARSET', 'utf8mb4');

// Clase de conexión a la base de datos
class Database {
    private static $instance = null;
    private $conn;
    
    // Constructor privado para el patrón Singleton
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];
            
            $this->conn = new PDO($dsn, DB_USER, DB_PASS, $options);
            
        } catch (PDOException $e) {
            error_log("Error de conexión a la base de datos: " . $e->getMessage());
            die("Error al conectar con la base de datos. Por favor, contacta al administrador.");
        }
    }
    
    // Método para obtener la instancia única
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    // Método para obtener la conexión
    public function getConnection() {
        return $this->conn;
    }
    
    // Prevenir clonación
    private function __clone() {}
    
    // Prevenir deserialización
    public function __wakeup() {
        throw new Exception("No se puede deserializar un singleton");
    }
}

// Función helper para obtener la conexión rápidamente
function getDB() {
    return Database::getInstance()->getConnection();
}

?>