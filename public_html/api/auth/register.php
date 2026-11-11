<?php

header('Content-Type: application/json; charset=utf-8');

try {
    // 1. Carga del sistema
    if (!file_exists(__DIR__ . '/../../includes/bootstrap.php')) {
        throw new Exception("Error de configuración interna (bootstrap no encontrado)");
    }
    require_once __DIR__ . '/../../includes/bootstrap.php';

    // 2. Verificar método
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Método no permitido");
    }

    // 3. Obtener conexión
    $db = Database::getInstance()->getConnection();

    // 4. Recibir y limpiar datos básicos
    $nombre = trim($_POST['nombre'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $password = $_POST['contrasena'] ?? '';
    $telefono = trim($_POST['telefono'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    
    // Validaciones básicas
    if (empty($nombre) || empty($correo) || empty($password)) {
        throw new Exception("Nombre, correo y contraseña son obligatorios");
    }

    // 5. Validar si el correo ya existe
    $stmt = $db->prepare("SELECT id FROM usuarios WHERE correo = :correo");
    $stmt->execute([':correo' => $correo]);
    if ($stmt->fetch()) {
        throw new Exception("El correo electrónico ya está registrado");
    }

    // 6. Manejo de Subida de Archivo (INE)
    $ruta_ine_guardada = null;

    if (isset($_FILES['ine_file']) && $_FILES['ine_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['ine_file']['tmp_name'];
        $fileName = $_FILES['ine_file']['name'];
        $fileSize = $_FILES['ine_file']['size'];
        $fileType = $_FILES['ine_file']['type'];
        
        // Verificar extensión
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));
        $allowedfileExtensions = ['jpg', 'jpeg', 'png', 'pdf'];

        if (!in_array($fileExtension, $allowedfileExtensions)) {
            throw new Exception("Tipo de archivo no permitido. Solo JPG, PNG o PDF.");
        }

        // Definir ruta de destino usando la constante STORAGE_PATH definida en bootstrap
        // Nota: Asegúrate que la carpeta /storage/ines/ exista y tenga permisos
        $uploadFileDir = STORAGE_PATH . '/ines/';
        
        // Crear carpeta si no existe (por seguridad)
        if (!is_dir($uploadFileDir)) {
            mkdir($uploadFileDir, 0755, true);
        }

        // Generar nombre único para evitar colisiones
        $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
        $dest_path = $uploadFileDir . $newFileName;

        if(move_uploaded_file($fileTmpPath, $dest_path)) {
            // Guardamos la ruta relativa para la base de datos (o solo el nombre del archivo)
            $ruta_ine_guardada = $newFileName; 
        } else {
            throw new Exception("Error al mover el archivo al directorio de almacenamiento.");
        }
    }

    // 7. Hashear contraseña
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // 8. Insertar en Base de Datos
    // Ajusta los nombres de columnas según tu tabla real
    $sql = "INSERT INTO usuarios (nombre_completo, correo, contrasena, telefono, direccion, ine_archivo, rol, fecha_registro) 
            VALUES (:nombre, :correo, :pass, :tel, :dir, :ine, 'ciudadano', NOW())";
            
    $stmt = $db->prepare($sql);
    $result = $stmt->execute([
        ':nombre' => $nombre,
        ':correo' => $correo,
        ':pass' => $passwordHash,
        ':tel' => $telefono,
        ':dir' => $direccion,
        ':ine' => $ruta_ine_guardada
    ]);

    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Registro exitoso',
            'redirect' => '/Public/iniciar-sesion.php'
        ]);
    } else {
        throw new Exception("Error al guardar en la base de datos");
    }

} catch (Exception $e) {
    // Si hubo error y se subió archivo, intentar borrarlo para no dejar basura
    if (isset($dest_path) && file_exists($dest_path)) {
        unlink($dest_path);
    }

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}