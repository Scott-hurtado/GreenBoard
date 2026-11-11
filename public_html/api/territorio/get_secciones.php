<?php
/**
 * API Endpoint para obtener secciones desde la base de datos
 * Ubicación: /public_html/api/territorio/get_secciones.php
 */

header('Content-Type: application/json');

// 1. Configuración de la base de datos
$dbConfig = [
    'host'     => 'localhost',
    'database' => 'u881824383_pverde',
    'username' => 'u881824383_proyverde',
    'password' => 'PProyectoverde8#',
    'charset'  => 'utf8mb4'
];

try {
    // Conexión PDO
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // 2. Obtener parámetros
    $estado_get    = $_GET['estado'] ?? '';
    $municipio_get = $_GET['municipio'] ?? '';

    if (empty($estado_get) || empty($municipio_get)) {
        echo json_encode(['success' => false, 'message' => 'Faltan parámetros (estado o municipio)']);
        exit;
    }

    /**
     * 3. Consulta a la tabla 'secciones_electorales'
     * Se ajustaron los nombres de las columnas: nombre_seccion, estado, municipio.
     * Forzamos COLLATE utf8mb4_general_ci para ignorar diferencias de mayúsculas/minúsculas o acentos.
     */
    $query = "SELECT nombre_seccion 
              FROM secciones_electorales 
              WHERE estado COLLATE utf8mb4_general_ci = :estado 
                AND municipio COLLATE utf8mb4_general_ci = :municipio 
              ORDER BY CAST(nombre_seccion AS UNSIGNED) ASC";

    $stmt = $pdo->prepare($query);
    $stmt->execute([
        ':estado'    => $estado_get,
        ':municipio' => $municipio_get
    ]);

    // Obtenemos solo la columna 'nombre_seccion' en un arreglo simple
    $secciones = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // 4. Respuesta
    if (count($secciones) > 0) {
        echo json_encode([
            'success'   => true,
            'estado'    => $estado_get,
            'municipio' => $municipio_get,
            'total'     => count($secciones),
            'secciones' => $secciones
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => "No se encontraron secciones para $municipio_get, $estado_get.",
            'debug_info' => [
                'estado_buscado' => $estado_get,
                'municipio_buscado' => $municipio_get
            ]
        ]);
    }

} catch (PDOException $e) {
    // Si hay error de base de datos, enviamos el error 500 y el mensaje
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos: ' . $e->getMessage()
    ]);
}