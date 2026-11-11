<?php

header('Content-Type: application/json; charset=utf-8');

// Función helper para formatear bytes
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}

try {
    // 1. Carga del sistema
    require_once __DIR__ . '/../../includes/bootstrap.php';

    // 2. Seguridad: Solo administradores
    $rol = $_SESSION['user_role'] ?? '';
    if (!in_array($rol, ['admin', 'administrador', 'superadmin'])) {
        throw new Exception("Acceso denegado. Se requieren permisos de administrador.");
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Método no permitido");
    }

    // 3. Validar Datos
    $id = $_POST['id'] ?? null;
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $categoria = trim($_POST['categoria'] ?? 'General');

    // --- CORRECCIÓN 1: Capturar y validar carpeta_id ---
    $carpetaInput = $_POST['carpeta_id'] ?? '';
    $carpetaId = (is_numeric($carpetaInput) && $carpetaInput > 0) ? (int)$carpetaInput : null;
    // ---------------------------------------------------

    if (empty($titulo) || empty($categoria)) {
        throw new Exception("El título y la categoría son obligatorios");
    }

    $db = Database::getInstance()->getConnection();
    $isUpdate = !empty($id);

    // 4. Procesar Archivo (soporta 'documento' y 'archivo' para compatibilidad)
    $archivoNuevo = false;
    $fileTmpPath = null;
    $fileName = null;
    $fileSize = 0;
    $fileExtension = null;
    $newFileName = null;
    $destPath = null;

    // Verificar si se subió un archivo (campo 'documento' o 'archivo')
    if (isset($_FILES['documento']) && $_FILES['documento']['error'] === UPLOAD_ERR_OK) {
        $archivoNuevo = true;
        $fileTmpPath = $_FILES['documento']['tmp_name'];
        $fileName = $_FILES['documento']['name'];
        $fileSize = $_FILES['documento']['size'];
    } elseif (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
        $archivoNuevo = true;
        $fileTmpPath = $_FILES['archivo']['tmp_name'];
        $fileName = $_FILES['archivo']['name'];
        $fileSize = $_FILES['archivo']['size'];
    }

    // Si es creación, el archivo es obligatorio
    if (!$isUpdate && !$archivoNuevo) {
        throw new Exception("Debe seleccionar un archivo para crear un nuevo documento");
    }

    // Si hay archivo, procesarlo
    if ($archivoNuevo) {
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));

        // Validar extensiones permitidas
        $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'txt'];
        if (!in_array($fileExtension, $allowedExtensions)) {
            throw new Exception("Tipo de archivo no permitido: " . $fileExtension);
        }

        // 5. Mover a Storage (Carpeta protegida)
        $uploadDir = STORAGE_PATH . '/documents/';
        
        // Crear directorio si no existe
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Generar nombre único: timestamp_nombre_limpio.ext
        $newFileName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $fileName);
        $destPath = $uploadDir . $newFileName;

        if (!move_uploaded_file($fileTmpPath, $destPath)) {
            throw new Exception("Error al mover el archivo al almacenamiento seguro");
        }
    }

    // 6. Guardar/Actualizar en Base de Datos
    if ($isUpdate) {
        // ACTUALIZACIÓN
        if ($archivoNuevo) {
            // Obtener archivo anterior para eliminarlo
            $stmtOld = $db->prepare("SELECT nombre_archivo, ruta_archivo FROM documentos WHERE id = :id");
            $stmtOld->execute([':id' => $id]);
            $oldDoc = $stmtOld->fetch(PDO::FETCH_ASSOC);
            
            if ($oldDoc) {
                $oldFileName = $oldDoc['nombre_archivo'] ?? basename($oldDoc['ruta_archivo'] ?? '');
                if (!empty($oldFileName)) {
                    $oldFilePath = STORAGE_PATH . '/documents/' . $oldFileName;
                    if (file_exists($oldFilePath)) {
                        unlink($oldFilePath);
                    }
                }
            }

            // Actualizar con nuevo archivo
            // --- CORRECCIÓN 2: Agregar carpeta_id al UPDATE ---
            $stmt = $db->prepare("UPDATE documentos SET 
                titulo = :titulo,
                categoria = :categoria,
                carpeta_id = :carpeta_id,
                descripcion = :descripcion,
                nombre_archivo = :nombre,
                ruta_archivo = :ruta,
                tipo_archivo = :tipo,
                tamano_archivo = :tamano,
                fecha_actualizacion = NOW()
                WHERE id = :id");
            
            $stmt->execute([
                ':titulo' => $titulo,
                ':categoria' => $categoria,
                ':carpeta_id' => $carpetaId, // Bind de carpeta
                ':descripcion' => $descripcion,
                ':nombre' => $newFileName,
                ':ruta' => '/storage/documents/' . $newFileName,
                ':tipo' => strtoupper($fileExtension),
                ':tamano' => $fileSize,
                ':id' => $id
            ]);
        } else {
            // Actualizar solo información, sin archivo
            // --- CORRECCIÓN 2b: Agregar carpeta_id al UPDATE sin archivo ---
            $stmt = $db->prepare("UPDATE documentos SET 
                titulo = :titulo,
                categoria = :categoria,
                carpeta_id = :carpeta_id,
                descripcion = :descripcion,
                fecha_actualizacion = NOW()
                WHERE id = :id");
            
            $stmt->execute([
                ':titulo' => $titulo,
                ':categoria' => $categoria,
                ':carpeta_id' => $carpetaId, // Bind de carpeta
                ':descripcion' => $descripcion,
                ':id' => $id
            ]);
        }

        $message = 'Documento actualizado correctamente';
    } else {
        // CREACIÓN
        // --- CORRECCIÓN 3: Agregar carpeta_id al INSERT ---
        $stmt = $db->prepare("INSERT INTO documentos 
            (titulo, categoria, carpeta_id, descripcion, nombre_archivo, ruta_archivo, tipo_archivo, tamano_archivo, fecha_subida) 
            VALUES (:titulo, :categoria, :carpeta_id, :descripcion, :nombre, :ruta, :tipo, :tamano, NOW())");
        
        $stmt->execute([
            ':titulo' => $titulo,
            ':categoria' => $categoria,
            ':carpeta_id' => $carpetaId, // Bind de carpeta
            ':descripcion' => $descripcion,
            ':nombre' => $newFileName,
            ':ruta' => '/storage/documents/' . $newFileName,
            ':tipo' => strtoupper($fileExtension),
            ':tamano' => $fileSize
        ]);

        $message = 'Documento guardado correctamente';
    }

    echo json_encode([
        'success' => true,
        'message' => $message
    ]);

} catch (Exception $e) {
    // Si falló, intentar borrar el archivo si se llegó a subir
    if (isset($destPath) && file_exists($destPath)) {
        unlink($destPath);
    }
    
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (PDOException $e) {
    // Si falló, intentar borrar el archivo si se llegó a subir
    if (isset($destPath) && file_exists($destPath)) {
        unlink($destPath);
    }
    
    error_log("Error en create.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Error en la base de datos']);
}