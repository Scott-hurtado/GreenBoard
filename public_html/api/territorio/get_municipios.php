<?php
/**
 * API Endpoint para obtener municipios por estado
 * Ubicación: /public_html/api/territorio/get_municipios.php
 */

header('Content-Type: application/json');

// Incluir conexión a base de datos
// Ajusta esta ruta según tu estructura de directorios
require_once __DIR__ . '/../../../includes/bootstrap.php';

if (!isset($_GET['estado']) || empty($_GET['estado'])) {
    echo json_encode(['success' => false, 'message' => 'Estado no proporcionado']);
    exit;
}

$estado = $_GET['estado'];

// Mapeo de nombres de estados a nombres de tablas
$tablasEstados = [
    'Baja California' => 'territorio_baja_california',
    'Baja California Sur' => 'territorio_baja_california_sur',
    'Sonora' => 'territorio_sonora',
    'Chihuahua' => 'territorio_chihuahua',
    'Sinaloa' => 'territorio_sinaloa',
    'Durango' => 'territorio_durango',
    'Nayarit' => 'territorio_nayarit',
    'Jalisco' => 'territorio_jalisco'
];

if (!isset($tablasEstados[$estado])) {
    echo json_encode(['success' => false, 'message' => 'Estado no válido']);
    exit;
}

$tabla = $tablasEstados[$estado];
$municipios = [];

try {
    $query = "SELECT municipio, es_capital FROM `{$tabla}` ORDER BY municipio ASC";
    $result = $conn->query($query);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $municipios[] = [
                'nombre' => $row['municipio'],
                'es_capital' => (bool)$row['es_capital']
            ];
        }
        
        echo json_encode([
            'success' => true,
            'municipios' => $municipios
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error al consultar la base de datos'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}