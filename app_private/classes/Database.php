<?php

class Database {
    private static $instance = null;
    private $conn;
    
    private function __construct() {
        $config = require dirname(__DIR__) . '/config/database.php';
        
        $dsn = "mysql:host={$config['host']};dbname={$config['database']};charset={$config['charset']}";
        
        try {
            $this->conn = new PDO($dsn, $config['username'], $config['password'], $config['options']);
        } catch (PDOException $e) {
            error_log("Error de conexión a la base de datos: " . $e->getMessage());
            throw new Exception("Error al conectar con la base de datos. Por favor, contacta al administrador.");
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->conn;
    }
    
    private function __clone() {}
    
    public function __wakeup() {
        throw new Exception("No se puede deserializar un singleton");
    }
}

// Función helper para mantener compatibilidad temporal
function getDB() {
    return Database::getInstance()->getConnection();
}