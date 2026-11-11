<?php
session_start();
require_once 'db.php';

// Verificar que se envió el formulario por POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: registro.php');
    exit();
}

// Obtener y limpiar datos del formulario
$nombre_completo = trim($_POST['nombre_completo'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');
$correo = trim(strtolower($_POST['correo'] ?? ''));
$contrasena = $_POST['contrasena'] ?? '';
$confirmar_contrasena = $_POST['confirmar_contrasena'] ?? '';
$colonia = trim($_POST['colonia'] ?? '');
$direccion = trim($_POST['direccion'] ?? '');
$seccion_electoral = intval($_POST['seccion_electoral'] ?? 0);
$rol = trim($_POST['rol'] ?? '');

// Validar que todos los campos estén completos
if (empty($nombre_completo) || empty($telefono) || empty($correo) || 
    empty($contrasena) || empty($confirmar_contrasena) || empty($colonia) || 
    empty($direccion) || $seccion_electoral <= 0 || empty($rol)) {
    header('Location: registro.php?error=campos_vacios');
    exit();
}

// Validar que las contraseñas coincidan
if ($contrasena !== $confirmar_contrasena) {
    header('Location: registro.php?error=contrasenas_no_coinciden');
    exit();
}

// Validar longitud de contraseña
if (strlen($contrasena) < 8) {
    header('Location: registro.php?error=contrasena_corta');
    exit();
}

try {
    $db = getDB();
    
    // Verificar si el correo ya existe
    $stmt = $db->prepare("SELECT id FROM usuarios WHERE correo = ?");
    $stmt->execute([$correo]);
    
    if ($stmt->rowCount() > 0) {
        header('Location: registro.php?error=correo_existente');
        exit();
    }
    
    // Procesar el archivo de INE
    $ine_ruta = null;
    
    if (isset($_FILES['ine']) && $_FILES['ine']['error'] === UPLOAD_ERR_OK) {
        $archivo = $_FILES['ine'];
        $nombre_archivo = $archivo['name'];
        $tmp_name = $archivo['tmp_name'];
        $tamano = $archivo['size'];
        $extension = strtolower(pathinfo($nombre_archivo, PATHINFO_EXTENSION));
        
        // Validar extensión
        $extensiones_permitidas = ['pdf', 'jpg', 'jpeg', 'png'];
        if (!in_array($extension, $extensiones_permitidas)) {
            header('Location: registro.php?error=archivo_invalido');
            exit();
        }
        
        // Validar tamaño (5MB máximo)
        if ($tamano > 5 * 1024 * 1024) {
            header('Location: registro.php?error=archivo_grande');
            exit();
        }
        
        // Crear carpeta uploads si no existe
        $carpeta_uploads = 'uploads/ines/';
        if (!file_exists($carpeta_uploads)) {
            mkdir($carpeta_uploads, 0755, true);
        }
        
        // Generar nombre único para el archivo
        $nombre_unico = uniqid() . '_' . time() . '.' . $extension;
        $ruta_destino = $carpeta_uploads . $nombre_unico;
        
        // Mover archivo
        if (move_uploaded_file($tmp_name, $ruta_destino)) {
            $ine_ruta = $ruta_destino;
        } else {
            header('Location: registro.php?error=archivo_error');
            exit();
        }
    } else {
        header('Location: registro.php?error=archivo_requerido');
        exit();
    }
    
    // Encriptar contraseña
    $contrasena_hash = password_hash($contrasena, PASSWORD_DEFAULT);
    
    // Insertar usuario en la base de datos
    $sql = "INSERT INTO usuarios (nombre_completo, telefono, correo, contrasena, colonia, direccion, seccion_electoral, rol, ine_ruta, fecha_registro) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $db->prepare($sql);
    $resultado = $stmt->execute([
        $nombre_completo,
        $telefono,
        $correo,
        $contrasena_hash,
        $colonia,
        $direccion,
        $seccion_electoral,
        $rol,
        $ine_ruta
    ]);
    
    if ($resultado) {
        // Obtener el ID del usuario recién creado
        $user_id = $db->lastInsertId();
        
        // Crear sesión automáticamente después del registro
        $_SESSION['user_id'] = $user_id;
        $_SESSION['nombre_completo'] = $nombre_completo;
        $_SESSION['correo'] = $correo;
        $_SESSION['rol'] = $rol;
        $_SESSION['login_time'] = time();
        
        // Regenerar ID de sesión por seguridad
        session_regenerate_id(true);
        
        // Registro exitoso - redirigir al dashboard
        header('Location: ./pages/citizens/dashboard_CiudadanoPage.php?success=registro_exitoso');
        exit();
    } else {
        // Error al insertar
        header('Location: registro.php?error=registro_fallido');
        exit();
    }
    
} catch (PDOException $e) {
    error_log("Error en registro: " . $e->getMessage());
    header('Location: registro.php?error=error_servidor');
    exit();
}
?>