<?php
/**
 * API para subir informes PDF - CORREGIDO
 */
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../../includes/bootstrap.php';

    // Seguridad: Solo administradores
    $rol = $_SESSION['user_role'] ?? '';
    if (!in_array($rol, ['admin', 'administrador', 'superadmin'])) {
        throw new Exception("Acceso denegado. Se requieren permisos de administrador.");
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Método no permitido");
    }

    // Validar que se subió un archivo
    if (!isset($_FILES['pdfFile']) || $_FILES['pdfFile']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("No se ha subido ningún archivo válido.");
    }

    $pdfFile   = $_FILES['pdfFile'];
    $tituloPdf = trim($_POST['pdfTitle'] ?? '');
    $tipoPdf   = trim($_POST['pdfTipo'] ?? '');    // Categoría
    $periodoPdf = trim($_POST['pdfPeriodo'] ?? ''); // Frecuencia
    $estadoPdf = trim($_POST['pdfStatus'] ?? '');
    $autorPdf  = trim($_POST['pdfAuthor'] ?? '');

    // Validación extendida de campos obligatorios
    if (empty($tituloPdf) || empty($tipoPdf) || empty($periodoPdf) || empty($estadoPdf) || empty($autorPdf)) {
        throw new Exception("Faltan campos requeridos para el PDF (Título, Tipo, Periodo, Estado o Autor).");
    }

    // Validar que sea PDF
    $fileExtension = pathinfo($pdfFile['name'], PATHINFO_EXTENSION);
    if (strtolower($fileExtension) !== 'pdf') {
        throw new Exception("Solo se permiten archivos PDF.");
    }

    // Mover a Storage (Carpeta protegida)
    $uploadDir = STORAGE_PATH . '/reports/';
    
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            error_log("Fallo al intentar crear el directorio de subida: " . $uploadDir);
            throw new Exception("Fallo al crear el directorio de subida.");
        }
    }

    // Generar nombre único
    $fileName = uniqid('informe_') . '.' . $fileExtension;
    $filePath = $uploadDir . $fileName;
    
    // URL pública para acceso
    $publicUrl = '/storage/reports/' . $fileName;

    if (!move_uploaded_file($pdfFile['tmp_name'], $filePath)) {
        error_log("Fallo al mover archivo subido a: " . $filePath);
        throw new Exception("Fallo al mover el archivo subido. Verifique permisos de carpeta.");
    }

    // Guardar en base de datos
    $db = Database::getInstance()->getConnection();
    
    // SQL actualizado para incluir la columna 'periodo'
    $stmt = $db->prepare("INSERT INTO informes (titulo, creado_por, fecha_creacion, tipo, periodo, estado, contenido, archivo_url) 
                         VALUES (:titulo, :autor, CURDATE(), :tipo, :periodo, :estado, NULL, :archivo_url)");
    
    $stmt->bindParam(':titulo', $tituloPdf);
    $stmt->bindParam(':autor', $autorPdf);
    $stmt->bindParam(':tipo', $tipoPdf);
    $stmt->bindParam(':periodo', $periodoPdf);
    $stmt->bindParam(':estado', $estadoPdf);
    $stmt->bindParam(':archivo_url', $publicUrl);
    $stmt->execute();
    
    $lastId = $db->lastInsertId();

    echo json_encode([
        'success' => true, 
        'message' => 'PDF subido y registrado con éxito.', 
        'id' => $lastId, 
        'date' => date('d/M/Y'), 
        'url' => $publicUrl
    ]);

} catch (Exception $e) {
    if (isset($filePath) && file_exists($filePath)) {
        unlink($filePath);
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (PDOException $e) {
    if (isset($filePath) && file_exists($filePath)) {
        unlink($filePath);
    }
    error_log("Error de DB en upload.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Error de base de datos SQL.']);
}