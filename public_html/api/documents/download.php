<?php

try {
    require_once __DIR__ . '/../../includes/bootstrap.php';

    // Validación de acceso: ¿Quién puede descargar?
    // Aquí puedes ajustar: si es público, quita este IF. Si es solo usuarios, déjalo.
    if (!isset($_SESSION['user_id'])) {
        die("Acceso denegado. Debes iniciar sesión.");
    }

    $id = $_GET['id'] ?? null;
    if (!$id) die("ID inválido");

    $db = Database::getInstance()->getConnection();
    
    // Obtener info del archivo
    $stmt = $db->prepare("SELECT * FROM documentos WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$doc) die("Documento no encontrado");

    // Construir ruta del archivo: usar nombre_archivo si existe, sino extraer de ruta_archivo
    if (!empty($doc['nombre_archivo'])) {
        $filePath = STORAGE_PATH . '/documents/' . $doc['nombre_archivo'];
    } elseif (!empty($doc['ruta_archivo'])) {
        // Extraer solo el nombre del archivo de la ruta
        $fileName = basename($doc['ruta_archivo']);
        $filePath = STORAGE_PATH . '/documents/' . $fileName;
    } else {
        die("No se encontró información del archivo");
    }

    if (!file_exists($filePath)) die("El archivo físico no existe: " . $filePath);

    // Preparar cabeceras para descarga
    $mimeType = mime_content_type($filePath);
    $originalName = $doc['nombre_archivo'] ?? basename($filePath);
    $downloadName = $doc['titulo'] . '.' . pathinfo($originalName, PATHINFO_EXTENSION);
    
    header("Content-Type: " . $mimeType);
    header("Content-Disposition: attachment; filename=\"" . $downloadName . "\"");
    header("Content-Length: " . filesize($filePath));
    
    // Actualizar contador de descargas
    try {
        $stmtUpdate = $db->prepare("UPDATE documentos SET descargas = descargas + 1 WHERE id = :id");
        $stmtUpdate->execute([':id' => $id]);
    } catch (Exception $e) {
        // Ignorar error de actualización de contador
    }

    // Limpiar buffer y enviar archivo
    ob_clean();
    flush();
    readfile($filePath);
    exit;

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}