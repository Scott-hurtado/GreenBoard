<?php
// public_html/api/reports/upload.php
header('Content-Type: application/json; charset=utf-8');

try {
    // 1. Cargar configuración de BD
    require_once __DIR__ . '/../../includes/database.php';
    $dbConfig = require __DIR__ . '/../../includes/database.php';
    
    // Conexión PDO
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 2. Verificar que se recibió un archivo
    if (!isset($_FILES['pdfFile']) || $_FILES['pdfFile']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No se recibió ningún archivo válido o hubo un error en la subida.');
    }

    // 3. Configuración de Directorios
    // Asegúrate de crear esta carpeta manualmente si no existe y darle permisos de escritura (chmod 777 o 755)
    $uploadDir = __DIR__ . '/../../uploads/informes/';
    $webPath = '/uploads/informes/'; // Ruta para acceder desde el navegador

    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception("No se pudo crear el directorio de subida.");
        }
    }

    // 4. Procesar el archivo
    $file = $_FILES['pdfFile'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['pdf', 'doc', 'docx'];

    if (!in_array($ext, $allowed)) {
        throw new Exception("Formato de archivo no permitido. Solo PDF y Word.");
    }

    // Generar nombre único para evitar sobrescribir
    $cleanTitle = preg_replace('/[^a-zA-Z0-9]/', '_', $_POST['pdfTitle'] ?? 'doc');
    $fileName = uniqid() . '_' . $cleanTitle . '.' . $ext;
    $targetPath = $uploadDir . $fileName;
    $finalUrl = $webPath . $fileName;

    // 5. Mover el archivo
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new Exception("Error al mover el archivo al directorio final.");
    }

    // 6. Recibir datos del formulario (Texto)
    $titulo = $_POST['pdfTitle'] ?? 'Sin título';
    $tipo = $_POST['pdfTipo'] ?? 'General';
    $periodo = $_POST['pdfPeriodo'] ?? 'Mensual';
    $estado = $_POST['pdfStatus'] ?? 'Borrador';
    $autor = $_POST['pdfAuthor'] ?? 'Admin';
    // Contenido vacío porque es un archivo adjunto, o puedes poner un texto por defecto
    $contenido = "Documento adjunto: " . $file['name']; 

    // 7. Guardar en Base de Datos
    $sql = "INSERT INTO informes (titulo, tipo, periodo, estado, creado_por, contenido, archivo_url, fecha_creacion) 
            VALUES (:titulo, :tipo, :periodo, :estado, :autor, :contenido, :url, NOW())";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        ':titulo' => $titulo,
        ':tipo' => $tipo,
        ':periodo' => $periodo,
        ':estado' => $estado,
        ':autor' => $autor,
        ':contenido' => $contenido,
        ':url' => $finalUrl
    ]);

    if ($result) {
        $id = $pdo->lastInsertId();
        echo json_encode([
            'success' => true,
            'message' => 'Archivo subido y guardado correctamente.',
            'id' => $id,
            'url' => $finalUrl,
            'date' => date('d/M/Y') // Fecha formateada para el frontend
        ]);
    } else {
        throw new Exception("Error al guardar en la base de datos.");
    }

} catch (Exception $e) {
    http_response_code(400); // Bad Request
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>