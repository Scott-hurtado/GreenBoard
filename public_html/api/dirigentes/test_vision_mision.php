<?php
// test_vision_mision.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h3>Test de Visión y Misión</h3>";

// Probar bootstrap
echo "<p>1. Probando bootstrap...</p>";
try {
    require_once __DIR__ . '/../../../includes/bootstrap.php';
    echo "✓ Bootstrap cargado correctamente<br>";
} catch (Exception $e) {
    echo "✗ Error en bootstrap: " . $e->getMessage() . "<br>";
    exit();
}

// Probar conexión
echo "<p>2. Probando conexión a base de datos...</p>";
try {
    $db = Database::getInstance()->getConnection();
    echo "✓ Conexión exitosa<br>";
} catch (Exception $e) {
    echo "✗ Error de conexión: " . $e->getMessage() . "<br>";
    exit();
}

// Probar si existe la tabla
echo "<p>3. Verificando si existe la tabla dirigentes_mision_vision...</p>";
try {
    $tableCheck = $db->query("SHOW TABLES LIKE 'dirigentes_mision_vision'");
    if ($tableCheck->rowCount() > 0) {
        echo "✓ Tabla existe<br>";
    } else {
        echo "✗ Tabla NO existe. Ejecuta el archivo SQL primero<br>";
        exit();
    }
} catch (Exception $e) {
    echo "✗ Error verificando tabla: " . $e->getMessage() . "<br>";
}

// Probar sesión
echo "<p>4. Verificando sesión...</p>";
session_start();
$rol = $_SESSION['user_role'] ?? 'sin sesión';
echo "Rol actual: <strong>" . $rol . "</strong><br>";

// Probar inserción
echo "<p>5. Probando inserción de datos de prueba...</p>";
try {
    $stmt = $db->prepare("
        INSERT INTO dirigentes_mision_vision (estado, vision, mision) 
        VALUES (:estado, :vision, :mision)
        ON DUPLICATE KEY UPDATE 
            vision = :vision2, 
            mision = :mision2
    ");
    
    $result = $stmt->execute([
        ':estado' => 'TEST',
        ':vision' => 'Visión de prueba',
        ':mision' => 'Misión de prueba',
        ':vision2' => 'Visión de prueba',
        ':mision2' => 'Misión de prueba'
    ]);
    
    if ($result) {
        echo "✓ Inserción exitosa<br>";
    }
} catch (Exception $e) {
    echo "✗ Error en inserción: " . $e->getMessage() . "<br>";
}

echo "<h3>✓ Test completado</h3>";
?>