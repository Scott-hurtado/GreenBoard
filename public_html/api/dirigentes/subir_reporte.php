<?php
// 1. Carga del sistema
require_once __DIR__ . '/../../includes/bootstrap.php';

header('Content-Type: application/json');

// 2. Validaciones básicas
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$id = $_POST['id'] ?? null;
$archivo = $_FILES['reporte'] ?? null;

if (!$id || !$archivo) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos o archivo no recibido']);
    exit;
}

// 3. Validación del archivo
$allowedTypes = ['application/pdf'];
$fileType = mime_content_type($archivo['tmp_name']);

if (!in_array($fileType, $allowedTypes)) {
    echo json_encode(['success' => false, 'message' => 'Solo se permiten archivos PDF']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    // 4. DEFINIR LA RUTA DE DESTINO (Aquí estaba el problema)
    // __DIR__ es: .../public_html/api/dirigentes
    // Subimos dos niveles (../../) para llegar a public_html
    // Luego entramos a storage/planes_estatales/
    $directorioDestino = __DIR__ . '/../../storage/planes_estatales/';

    // Verificar si la carpeta existe, si no, crearla
    if (!file_exists($directorioDestino)) {
        if (!mkdir($directorioDestino, 0755, true)) {
            throw new Exception("No se pudo crear el directorio de almacenamiento.");
        }
    }

    // 5. Generar nombre único para evitar sobrescribir
    // Formato: reporte_[ID_TAREA]_[TIMESTAMP].pdf
    $extension = 'pdf'; // Forzamos pdf ya que validamos el mime type
    $nombreArchivo = 'reporte_' . $id . '_' . time() . '.' . $extension;
    $rutaFinal = $directorioDestino . $nombreArchivo;

    // 6. Mover el archivo
    if (move_uploaded_file($archivo['tmp_name'], $rutaFinal)) {
        
        // 7. Actualizar base de datos
        // Guardamos SOLO el nombre del archivo, no la ruta completa
        $stmt = $db->prepare("UPDATE dirigentes_actividades SET reporte_pdf = ?, reporte_adjunto = ?, estatus = 'en_proceso' WHERE id = ?");
        
        // Actualizamos ambos campos (reporte_pdf y reporte_adjunto) por si usas uno u otro en diferentes vistas
        $stmt->execute([$nombreArchivo, $nombreArchivo, $id]);

        echo json_encode([
            'success' => true, 
            'message' => 'Archivo subido correctamente',
            'file' => $nombreArchivo
        ]);
    } else {
        throw new Exception("Error al mover el archivo al directorio final. Verifica permisos de carpeta.");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>