<?php
// api/documents/update.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/bootstrap.php';

// Verificar sesión
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'administrador', 'superadmin'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Obtener datos
    $id = $_POST['id'] ?? null;
    $titulo = $_POST['titulo'] ?? '';
    $categoria = $_POST['categoria'] ?? '';
    $descripcion = $_POST['descripcion'] ?? '';
    
    // --- NUEVO: Recibir la carpeta ---
    // Si viene vacío "", lo convertimos a NULL (Raíz). Si trae número, se guarda.
    $carpeta_id = !empty($_POST['carpeta_id']) ? $_POST['carpeta_id'] : null;

    if (!$id || !$titulo || !$categoria) {
        throw new Exception("Datos incompletos");
    }

    // 1. Lógica para el archivo (si se subió uno nuevo)
    $sqlFile = "";
    
    // --- MODIFICADO: Agregamos $carpeta_id al array de parámetros iniciales ---
    $params = [$titulo, $categoria, $descripcion, $carpeta_id];

    if (isset($_FILES['documento']) && $_FILES['documento']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['documento'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg'];
        
        if (!in_array($ext, $allowed)) {
            throw new Exception("Tipo de archivo no permitido");
        }

        // Generar nombre único
        $newFileName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file['name']);
        $uploadDir = __DIR__ . '/../../storage/documents/'; 
        
        // Asegurar que existe el directorio
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        if (move_uploaded_file($file['tmp_name'], $uploadDir . $newFileName)) {
            // Borrar archivo viejo (Opcional)
            $stmtOld = $db->prepare("SELECT nombre_archivo FROM documentos WHERE id = ?");
            $stmtOld->execute([$id]);
            $oldFile = $stmtOld->fetchColumn();
            
            // Solo borrar si no es nulo y existe
            if($oldFile && file_exists($uploadDir . $oldFile)) {
                @unlink($uploadDir . $oldFile);
            }

            // Agregar campos de archivo a la consulta SQL
            $sqlFile = ", nombre_archivo = ?, ruta_archivo = ?, tipo_archivo = ?";
            
            // Los parámetros del archivo se agregan al final del array existente
            $params[] = $newFileName;
            $params[] = 'storage/documents/' . $newFileName; 
            $params[] = $ext;
        } else {
            throw new Exception("Error al mover el archivo al servidor");
        }
    }

    // 2. Actualizar Base de Datos
    $params[] = $id; // El ID va al final absoluto para el WHERE

    // --- MODIFICADO: Agregamos `carpeta_id = ?` a la consulta SQL ---
    $sql = "UPDATE documentos SET titulo = ?, categoria = ?, descripcion = ?, carpeta_id = ? $sqlFile WHERE id = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    echo json_encode(['success' => true, 'message' => 'Documento actualizado correctamente']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>