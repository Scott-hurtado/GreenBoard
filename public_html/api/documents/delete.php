<?php

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../../includes/bootstrap.php';

    // Seguridad: Solo admin
    $rol = $_SESSION['user_role'] ?? '';
    if (!in_array($rol, ['admin', 'administrador', 'superadmin'])) {
        throw new Exception("Acceso denegado");
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Método no permitido");
    }

    // Recibir ID (Soporta JSON raw o POST form)
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? $_POST['id'] ?? null;
    $hardDelete = isset($input['hard']) ? (bool)$input['hard'] : (isset($_POST['hard']) ? (bool)$_POST['hard'] : false);

    if (!$id) {
        throw new Exception("ID de documento no proporcionado");
    }

    $db = Database::getInstance()->getConnection();

    // 1. Obtener información del documento
    $stmt = $db->prepare("SELECT nombre_archivo, ruta_archivo FROM documentos WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$doc) {
        throw new Exception("Documento no encontrado");
    }

    // Función helper para eliminar archivo físico
    $deletePhysicalFile = function() use ($doc, $id) {
        // Obtener nombre del archivo de diferentes formas
        $fileName = null;
        
        if (!empty($doc['nombre_archivo'])) {
            $fileName = $doc['nombre_archivo'];
        } elseif (!empty($doc['ruta_archivo'])) {
            // Extraer nombre del archivo de la ruta
            $fileName = basename($doc['ruta_archivo']);
        }
        
        if (empty($fileName)) {
            error_log("Delete ID $id: No se encontró nombre de archivo. Datos: " . json_encode($doc));
            return false;
        }
        
        // Construir ruta completa
        $filePath = STORAGE_PATH . '/documents/' . $fileName;
        
        error_log("Delete ID $id: Intentando eliminar archivo en: $filePath");
        
        if (!file_exists($filePath)) {
            error_log("Delete ID $id: Archivo no existe en ruta: $filePath");
            
            // Intentar con ruta absoluta si ruta_archivo tiene ruta completa
            if (!empty($doc['ruta_archivo']) && strpos($doc['ruta_archivo'], '/') === 0) {
                $altPath = PUBLIC_PATH . $doc['ruta_archivo'];
                if (file_exists($altPath)) {
                    $filePath = $altPath;
                    error_log("Delete ID $id: Archivo encontrado en ruta alternativa: $filePath");
                } else {
                    // Listar archivos en el directorio para debugging
                    $dir = STORAGE_PATH . '/documents/';
                    if (is_dir($dir)) {
                        $files = scandir($dir);
                        error_log("Delete ID $id: Archivos en directorio: " . implode(', ', array_filter($files, function($f) { return $f !== '.' && $f !== '..'; })));
                    }
                    return false;
                }
            } else {
                // Listar archivos en el directorio para debugging
                $dir = STORAGE_PATH . '/documents/';
                if (is_dir($dir)) {
                    $files = scandir($dir);
                    error_log("Delete ID $id: Archivos en directorio: " . implode(', ', array_filter($files, function($f) { return $f !== '.' && $f !== '..'; })));
                }
                return false;
            }
        }
        
        // Verificar permisos
        if (!is_writable($filePath) && !is_writable(dirname($filePath))) {
            error_log("Delete ID $id: Sin permisos para eliminar archivo: $filePath");
            throw new Exception("Sin permisos para eliminar el archivo físico");
        }
        
        // Eliminar archivo
        if (unlink($filePath)) {
            error_log("Delete ID $id: Archivo eliminado exitosamente: $filePath");
            return true;
        } else {
            error_log("Delete ID $id: Error al eliminar archivo: $filePath");
            throw new Exception("Error al eliminar el archivo físico");
        }
    };

    // Intentar eliminar el archivo físico primero (siempre)
    $fileDeleted = false;
    $fileError = null;
    try {
        $fileDeleted = $deletePhysicalFile();
    } catch (Exception $e) {
        // Si falla la eliminación del archivo, guardamos el error pero continuamos
        $fileError = $e->getMessage();
        error_log("Delete ID $id: Error al eliminar archivo físico: " . $fileError);
    }

    if ($hardDelete) {
        // HARD DELETE: Eliminar registro de BD
        $stmt = $db->prepare("DELETE FROM documentos WHERE id = :id");
        $stmt->execute([':id' => $id]);
        
        if ($fileDeleted) {
            $message = 'Documento eliminado permanentemente';
        } elseif ($fileError) {
            $message = 'Registro eliminado, pero hubo un error al eliminar el archivo: ' . $fileError;
        } else {
            $message = 'Registro eliminado (archivo no encontrado en el servidor)';
        }
    } else {
        // SOFT DELETE: Solo marcar como inactivo
        // Verificar si la tabla tiene columna 'activo'
        $stmt = $db->prepare("SHOW COLUMNS FROM documentos LIKE 'activo'");
        $stmt->execute();
        $hasActivoColumn = $stmt->fetch() !== false;

        if ($hasActivoColumn) {
            $stmt = $db->prepare("UPDATE documentos SET activo = 0 WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $message = $fileDeleted ? 'Documento desactivado y archivo eliminado' : 'Documento desactivado correctamente';
        } else {
            // Si no tiene columna activo, hacer hard delete
            $stmt = $db->prepare("DELETE FROM documentos WHERE id = :id");
            $stmt->execute([':id' => $id]);
            if ($fileDeleted) {
                $message = 'Documento eliminado correctamente';
            } elseif ($fileError) {
                $message = 'Registro eliminado, pero hubo un error al eliminar el archivo: ' . $fileError;
            } else {
                $message = 'Registro eliminado (archivo no encontrado)';
            }
        }
    }

    echo json_encode([
        'success' => true, 
        'message' => $message,
        'file_deleted' => $fileDeleted,
        'file_error' => $fileError
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}