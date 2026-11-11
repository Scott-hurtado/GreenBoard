<?php
// Activar reporte de errores COMPLETO
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Iniciar output buffering para capturar cualquier salida inesperada
ob_start();

try {
    require_once __DIR__ . '/../../includes/bootstrap.php';
} catch (Exception $e) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error en bootstrap: ' . $e->getMessage()]);
    exit;
}

header('Content-Type: application/json');

// Solo permitir método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    // DEBUG: Verificar que $pdo existe
    if (!isset($pdo)) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'ERROR: Variable $pdo no está definida. Verifica bootstrap.php']);
        exit;
    }

    // Obtener estado y municipio SOLO de los selects
    $estado = trim($_POST['estado_select'] ?? '');
    $municipio = trim($_POST['municipio_select'] ?? '');

    // DEBUG: Mostrar datos recibidos
    if (empty($estado) || empty($municipio)) {
        ob_clean();
        echo json_encode([
            'success' => false, 
            'message' => 'Debes seleccionar un estado y municipio',
            'debug' => [
                'estado_select' => $_POST['estado_select'] ?? 'no enviado',
                'municipio_select' => $_POST['municipio_select'] ?? 'no enviado',
            ]
        ]);
        exit;
    }

    // Verificar archivo subido
    if (!isset($_FILES['qr_file'])) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'No se recibió ningún archivo', 'debug' => 'qr_file no existe en $_FILES']);
        exit;
    }

    if ($_FILES['qr_file']['error'] !== UPLOAD_ERR_OK) {
        ob_clean();
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'El archivo excede upload_max_filesize en php.ini',
            UPLOAD_ERR_FORM_SIZE => 'El archivo excede MAX_FILE_SIZE en el formulario',
            UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente',
            UPLOAD_ERR_NO_FILE => 'No se subió ningún archivo',
            UPLOAD_ERR_NO_TMP_DIR => 'Falta la carpeta temporal',
            UPLOAD_ERR_CANT_WRITE => 'Error al escribir el archivo en disco',
            UPLOAD_ERR_EXTENSION => 'Una extensión de PHP detuvo la carga',
        ];
        $errorCode = $_FILES['qr_file']['error'];
        $errorMsg = $errorMessages[$errorCode] ?? 'Error desconocido: ' . $errorCode;
        
        echo json_encode(['success' => false, 'message' => 'Error de carga: ' . $errorMsg]);
        exit;
    }

    // Validar tipo de imagen
    $allowed = ['image/png', 'image/jpeg', 'image/jpg'];
    $tmpPath = $_FILES['qr_file']['tmp_name'];
    
    if (!file_exists($tmpPath)) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'El archivo temporal no existe']);
        exit;
    }
    
    $mime = mime_content_type($tmpPath);
    if (!in_array($mime, $allowed)) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Solo se permiten imágenes PNG o JPEG. Tipo detectado: ' . $mime]);
        exit;
    }

    // Generar nombre único seguro
    $ext = strtolower(pathinfo($_FILES['qr_file']['name'], PATHINFO_EXTENSION));
    $fileName = 'QR_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $targetDir = __DIR__ . '/';
    $targetFile = $targetDir . $fileName;
    $dbPath = '/api/qr/' . $fileName;

    // Verificar permisos de escritura
    if (!is_writable($targetDir)) {
        ob_clean();
        echo json_encode([
            'success' => false, 
            'message' => 'La carpeta no tiene permisos de escritura',
            'debug' => [
                'targetDir' => $targetDir,
                'exists' => file_exists($targetDir) ? 'sí' : 'no',
                'writable' => is_writable($targetDir) ? 'sí' : 'no',
                'permissions' => substr(sprintf('%o', fileperms($targetDir)), -4)
            ]
        ]);
        exit;
    }

    // Mover archivo
    if (!move_uploaded_file($tmpPath, $targetFile)) {
        ob_clean();
        echo json_encode([
            'success' => false, 
            'message' => 'Error al guardar el archivo en el servidor',
            'debug' => [
                'from' => $tmpPath,
                'to' => $targetFile,
                'dir_exists' => file_exists($targetDir) ? 'sí' : 'no',
                'dir_writable' => is_writable($targetDir) ? 'sí' : 'no'
            ]
        ]);
        exit;
    }

    // Insertar o actualizar en la base de datos
    $stmt = $pdo->prepare("
        INSERT INTO gestion_qr (estado, municipio, archivo_path) 
        VALUES (?, ?, ?) 
        ON DUPLICATE KEY UPDATE 
            archivo_path = VALUES(archivo_path),
            fecha_creacion = CURRENT_TIMESTAMP
    ");
    
    $result = $stmt->execute([$estado, $municipio, $dbPath]);
    
    if (!$result) {
        ob_clean();
        echo json_encode([
            'success' => false, 
            'message' => 'Error al guardar en la base de datos',
            'debug' => $stmt->errorInfo()
        ]);
        exit;
    }

    ob_clean();
    echo json_encode([
        'success' => true,
        'message' => 'QR guardado correctamente',
        'path' => $dbPath,
        'estado' => $estado,
        'municipio' => $municipio
    ]);

} catch (Exception $e) {
    ob_clean();
    error_log('Error en save_qr.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor',
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>