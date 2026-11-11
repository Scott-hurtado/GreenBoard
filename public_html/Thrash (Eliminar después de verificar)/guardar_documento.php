<?php
header('Content-Type: application/json');
require_once 'db.php';

$db = getDB();

try {
    $id = $_POST['id'] ?? null;
    $titulo = $_POST['titulo'] ?? '';
    $categoria = $_POST['categoria'] ?? '';
    $descripcion = $_POST['descripcion'] ?? '';
    
    // Validar campos requeridos
    if (empty($titulo) || empty($categoria)) {
        echo json_encode(['success' => false, 'message' => 'Faltan campos requeridos']);
        exit;
    }
    
    // Procesar archivo si existe
    $archivoNuevo = isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK;
    
    if ($id) {
        // Actualizar documento existente
        if ($archivoNuevo) {
            // Obtener información del archivo
            $fileName = $_FILES['archivo']['name'];
            $fileSize = $_FILES['archivo']['size'];
            $fileTmp = $_FILES['archivo']['tmp_name'];
            $fileType = strtoupper(pathinfo($fileName, PATHINFO_EXTENSION));
            
            // Validar tamaño (10MB máximo)
            if ($fileSize > 10485760) {
                echo json_encode(['success' => false, 'message' => 'El archivo es demasiado grande (máx. 10MB)']);
                exit;
            }
            
            // Crear directorio uploads si no existe
            if (!file_exists('uploads')) {
                mkdir('uploads', 0777, true);
            }
            
            // Generar nombre único para el archivo
            $newFileName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $fileName);
            $uploadPath = 'uploads/' . $newFileName;
            
            // Mover archivo
            if (move_uploaded_file($fileTmp, $uploadPath)) {
                // Formatear tamaño
                $formattedSize = formatBytes($fileSize);
                
                // Actualizar en base de datos con nuevo archivo
                $stmt = $db->prepare("UPDATE documentos SET 
                    titulo = :titulo,
                    categoria = :categoria,
                    descripcion = :descripcion,
                    tipo_archivo = :tipo,
                    nombre_archivo = :nombre,
                    ruta_archivo = :ruta,
                    tamano_archivo = :tamano,
                    fecha_actualizacion = NOW()
                    WHERE id = :id");
                
                $stmt->execute([
                    'titulo' => $titulo,
                    'categoria' => $categoria,
                    'descripcion' => $descripcion,
                    'tipo' => $fileType,
                    'nombre' => $newFileName,
                    'ruta' => $uploadPath,
                    'tamano' => $formattedSize,
                    'id' => $id
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al subir el archivo']);
                exit;
            }
        } else {
            // Actualizar solo información, sin archivo
            $stmt = $db->prepare("UPDATE documentos SET 
                titulo = :titulo,
                categoria = :categoria,
                descripcion = :descripcion,
                fecha_actualizacion = NOW()
                WHERE id = :id");
            
            $stmt->execute([
                'titulo' => $titulo,
                'categoria' => $categoria,
                'descripcion' => $descripcion,
                'id' => $id
            ]);
        }
        
        echo json_encode(['success' => true, 'message' => 'Documento actualizado correctamente']);
        
    } else {
        // Insertar nuevo documento
        if (!$archivoNuevo) {
            echo json_encode(['success' => false, 'message' => 'Debe seleccionar un archivo']);
            exit;
        }
        
        // Obtener información del archivo
        $fileName = $_FILES['archivo']['name'];
        $fileSize = $_FILES['archivo']['size'];
        $fileTmp = $_FILES['archivo']['tmp_name'];
        $fileType = strtoupper(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // Validar tamaño (10MB máximo)
        if ($fileSize > 10485760) {
            echo json_encode(['success' => false, 'message' => 'El archivo es demasiado grande (máx. 10MB)']);
            exit;
        }
        
        // Crear directorio uploads si no existe
        if (!file_exists('uploads')) {
            mkdir('uploads', 0777, true);
        }
        
        // Generar nombre único para el archivo
        $newFileName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $fileName);
        $uploadPath = 'uploads/' . $newFileName;
        
        // Mover archivo
        if (move_uploaded_file($fileTmp, $uploadPath)) {
            // Formatear tamaño
            $formattedSize = formatBytes($fileSize);
            
            // Insertar en base de datos
            $stmt = $db->prepare("INSERT INTO documentos 
                (titulo, categoria, descripcion, tipo_archivo, nombre_archivo, ruta_archivo, tamano_archivo, activo) 
                VALUES (:titulo, :categoria, :descripcion, :tipo, :nombre, :ruta, :tamano, 1)");
            
            $stmt->execute([
                'titulo' => $titulo,
                'categoria' => $categoria,
                'descripcion' => $descripcion,
                'tipo' => $fileType,
                'nombre' => $newFileName,
                'ruta' => $uploadPath,
                'tamano' => $formattedSize
                ]);
            
            echo json_encode(['success' => true, 'message' => 'Documento guardado correctamente']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al subir el archivo']);
        }
    }
    
} catch (PDOException $e) {
    error_log("Error en guardar_documento.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()]);
}

// Función para formatear bytes
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}
?>