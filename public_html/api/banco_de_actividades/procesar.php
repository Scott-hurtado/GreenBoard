<?php
// Archivo: /public_html/api/banco_de_actividades/procesar.php

// 1. CONFIGURACIÓN Y SEGURIDAD
if (file_exists(__DIR__ . '/../../includes/bootstrap.php')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
} else {
    session_start();
}

// Obtener datos de sesión
$rol_usuario = $_SESSION['user_role'] ?? 'sistema';
$estado_sesion = $_SESSION['user_estado'] ?? ''; 
$id_usuario = $_SESSION['user_id'] ?? null;

// Validar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../../App/admin/banco_de_actividades.php?status=error&message=Acceso no autorizado.");
    exit();
}

// 2. CONEXIÓN BD
$dbConfigPath = __DIR__ . '/../../includes/database.php';
$pdo = null;
$redirect_status = 'success';
$redirect_message = 'Operación realizada con éxito.';

try {
    if (file_exists($dbConfigPath)) {
        $dbConfig = require $dbConfigPath;
    } else {
        $dbConfig = [
            'host' => 'localhost', 'database' => 'u881824383_pverde', 'username' => 'u881824383_proyverde',
            'password' => 'PProyectoverde8#', 'charset' => 'utf8mb4',
            'options' => [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        ];
    }
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options'] ?? []);

} catch (PDOException $e) {
    die("Error de conexión BD: " . $e->getMessage());
}

// 3. HELPER PARA SUBIDA DE ARCHIVOS
function handleFileUpload($file, $documentoActual = '') {
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) return $documentoActual;
    
    if ($file['error'] !== UPLOAD_ERR_OK) throw new Exception("Error en subida de archivo (Código: {$file['error']})");
    
    $allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($ext, $allowed)) throw new Exception("Formato de archivo no permitido ($ext).");
    
    $uploadDir = __DIR__ . '/documentos/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    
    // Nombre único para evitar colisiones
    $safeName = preg_replace('/[^a-zA-Z0-9_.-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
    $newFilename = uniqid('banco_') . '_' . $safeName . '.' . $ext;
    
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $newFilename)) {
        throw new Exception("No se pudo guardar el archivo en el servidor.");
    }
    
    // Borrar anterior si existe y es diferente
    if (!empty($documentoActual) && file_exists($uploadDir . $documentoActual)) {
        @unlink($uploadDir . $documentoActual);
    }
    
    return $newFilename;
}

// 4. LÓGICA PRINCIPAL
$action = $_POST['action'] ?? '';
$id = $_POST['db_id'] ?? null; 
$documentoActual = $_POST['documento_actual'] ?? '';

// Datos comunes para CREATE y UPDATE del Banco
$data = [
    'titulo' => trim($_POST['titulo'] ?? ''),
    'descripcion' => $_POST['descripcion'] ?? '',
    'agenda' => $_POST['agenda'] ?? 'social', 
    'nivel_impacto' => $_POST['nivel_impacto'] ?? 'bajo',
    'impacto_personas' => (int)($_POST['impacto_personas'] ?? 0),
    'costo' => (float)($_POST['costo'] ?? 0.00),
    'fecha_actividad' => empty($_POST['fecha_actividad']) ? null : $_POST['fecha_actividad'],
    'ubicacion' => (!empty($estado_sesion)) ? $estado_sesion : 'Central'
];

try {
    
    // ==========================================
    // 1. DELETE ASSIGNMENT (Borrado Físico de PLANES)
    // ==========================================
    // Esta acción elimina la actividad de la lista personal del coordinador, no del banco global.
    if ($action === 'delete_assignment') {
        
        if (empty($id)) throw new Exception("ID de asignación no válido.");

        // Usamos DELETE FROM para borrar el registro físico de la asignación
        $sql = "DELETE FROM dirigentes_actividades WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $id]);

        $redirect_message = "Actividad eliminada de tu plan correctamente.";


    // ==========================================
    // 2. CREATE (Crear en BANCO GLOBAL)
    // ==========================================
    } elseif ($action === 'create') {
        
        $pdo->beginTransaction();

        if (empty($data['titulo'])) throw new Exception("El título es obligatorio.");

        // Generar ID Visual (AAMMDD-XXX)
        $anioMes = date('ym');       
        $sqlCheck = "SELECT codigo_referencia FROM banco_actividades WHERE codigo_referencia LIKE :pattern ORDER BY codigo_referencia DESC LIMIT 1";
        $stmtCheck = $pdo->prepare($sqlCheck);
        $stmtCheck->execute(['pattern' => $anioMes . '%']);
        $ultimoCodigo = $stmtCheck->fetchColumn();

        $consecutivo = 1;
        if ($ultimoCodigo) {
            $partes = explode('-', $ultimoCodigo);
            if (count($partes) === 2) $consecutivo = (int)end($partes) + 1; 
        }
        $nuevoCodigo = date('ymd') . '-' . str_pad($consecutivo, 3, '0', STR_PAD_LEFT);
        $data['codigo_referencia'] = $nuevoCodigo;

        // Subir Archivo
        $data['documento_adjunto'] = '';
        if (isset($_FILES['documento'])) {
            $data['documento_adjunto'] = handleFileUpload($_FILES['documento']);
        }

        // Autoría
        $data['creado_por_id'] = $id_usuario;
        $data['creado_por_rol'] = $rol_usuario;
        
        $sql = "INSERT INTO banco_actividades 
                (codigo_referencia, titulo, descripcion, agenda, nivel_impacto, 
                 impacto_personas, ubicacion, costo, fecha_actividad, 
                 documento_adjunto, fecha_creacion, creado_por_id, creado_por_rol, activo) 
                VALUES 
                (:codigo_referencia, :titulo, :descripcion, :agenda, :nivel_impacto, 
                 :impacto_personas, :ubicacion, :costo, :fecha_actividad, 
                 :documento_adjunto, NOW(), :creado_por_id, :creado_por_rol, 1)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($data);

        $pdo->commit();
        $redirect_message = "Propuesta enviada al banco con ID: {$nuevoCodigo}.";

    // ==========================================
    // 3. UPDATE (Editar en BANCO GLOBAL)
    // ==========================================
    } elseif ($action === 'update' && !empty($id)) {
        
        if (empty($data['titulo'])) throw new Exception("El título es obligatorio.");

        // Archivo
        $documento = $documentoActual;
        if (isset($_FILES['documento']) && $_FILES['documento']['error'] !== UPLOAD_ERR_NO_FILE) {
            $documento = handleFileUpload($_FILES['documento'], $documentoActual);
        }

        $sql = "UPDATE banco_actividades SET 
                    titulo = :titulo, descripcion = :descripcion, agenda = :agenda, 
                    nivel_impacto = :nivel_impacto, impacto_personas = :impacto_personas, 
                    costo = :costo, fecha_actividad = :fecha_actividad, 
                    documento_adjunto = :documento_adjunto, ubicacion = :ubicacion,
                    fecha_actualizacion = NOW()
                WHERE id = :id";
        
        $stmt = $pdo->prepare($sql);
        
        // Array específico para UPDATE (evitamos enviar claves extra como 'creado_por')
        $updateData = [
            'titulo' => $data['titulo'],
            'descripcion' => $data['descripcion'],
            'agenda' => $data['agenda'],
            'nivel_impacto' => $data['nivel_impacto'],
            'impacto_personas' => $data['impacto_personas'],
            'costo' => $data['costo'],
            'fecha_actividad' => $data['fecha_actividad'],
            'documento_adjunto' => $documento,
            'ubicacion' => $data['ubicacion'],
            'id' => $id
        ];
        
        $stmt->execute($updateData);
        $redirect_message = 'Actividad actualizada correctamente.';

    // ==========================================
    // 4. DELETE (Soft Delete en BANCO GLOBAL)
    // ==========================================
    // Esto es para cuando el ADMIN elimina una actividad del catálogo global.
    } elseif ($action === 'delete') {
        
        if (empty($id)) throw new Exception('ID inválido.');
        $sql = "UPDATE banco_actividades SET activo = 0 WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        
        if ($stmt->rowCount() > 0) {
            $redirect_message = 'Actividad archivada del catálogo.';
        } else {
            $redirect_message = 'No se encontró la actividad o ya estaba archivada.';
            $redirect_status = 'warning';
        }
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $redirect_status = 'error';
    $redirect_message = 'Error: ' . $e->getMessage();
}

// 5. REDIRECCIÓN INTELIGENTE
$referer = $_SERVER['HTTP_REFERER'] ?? '../../App/admin/banco_de_actividades.php';
$url_parts = explode('?', $referer);
$clean_url = $url_parts[0];

header("Location: {$clean_url}?status={$redirect_status}&message=" . urlencode($redirect_message));
exit();
?>