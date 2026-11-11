<?php

header('Content-Type: application/json; charset=utf-8');

try {
    // 1. Carga del sistema
    if (!file_exists(__DIR__ . '/../../includes/bootstrap.php')) {
        throw new Exception("Error crítico: No se encuentra bootstrap.php");
    }
    require_once __DIR__ . '/../../includes/bootstrap.php';

    // 2. Verificar método
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Método no permitido");
    }

    // 3. Obtener datos
    $identificador = trim($_POST['correo']    ?? ''); // Ahora puede ser correo o username
    $password      = $_POST['contrasena'] ?? '';

    if (empty($identificador) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Faltan datos']);
        exit;
    }

    // 4. Conexión y Búsqueda Automática del Rol
    $db = Database::getInstance()->getConnection();

    $usuario      = null;
    $tipo_usuario = null;

    // --- Búsqueda 1: Administradores (Solo por correo) ---
    $stmt = $db->prepare("SELECT * FROM administradores WHERE correo = :id AND activo = 1 LIMIT 1");
    $stmt->execute([':id' => $identificador]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($admin) {
        $usuario      = $admin;
        $tipo_usuario = 'administrador';
    }

    // --- Búsqueda 2: Coordinadores de Circunscripción (Solo por correo) ---
    if (!$usuario) {
        $stmt = $db->prepare("SELECT * FROM coordinadores_circunscripcion WHERE correo = :id AND activo = 1 LIMIT 1");
        $stmt->execute([':id' => $identificador]);
        $circ = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($circ) {
            $usuario      = $circ;
            $tipo_usuario = 'circunscripcion';
        }
    }

    // --- Búsqueda 3: Equipo de Trabajo Unificado (Tabla: usuarios) ---
    if (!$usuario) {
        try {
            // SOLUCIÓN APLICADA: Buscamos por correo O por username usando :id1 e :id2
            $stmt = $db->prepare("SELECT * FROM usuarios WHERE (correo = :id1 OR username = :id2) AND activo = 1 LIMIT 1");
            $stmt->execute([
                ':id1' => $identificador,
                ':id2' => $identificador
            ]);
            $user_equipo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user_equipo) {
                // Verificamos que tenga permiso de acceso
                if ($user_equipo['requiere_acceso'] == 1) {
                    $usuario = $user_equipo;
                    // Asignamos el tipo_usuario basado en su rol real en la tabla
                    $tipo_usuario = strtolower(trim($user_equipo['rol'] ?? ''));
                    // Normalizamos los roles de administrador que caen aquí
                    if (in_array($tipo_usuario, ['admin', 'administrador', 'superadmin', 'super admin'])) {
                        $tipo_usuario = 'usuario_admin';
                    }
                }
            }
        } catch (Exception $e) { 
            // Registra el error en los logs de PHP por si hay algún problema estructural con la tabla
            error_log("Error SQL en Búsqueda 3 (Login): " . $e->getMessage()); 
        }
    }

    // --- Búsqueda 4: Coordinadores de Estructura (Tablas específicas) ---
    if (!$usuario) {
        $tablas_coord = [
            'coordinador_estatal'  => 'coordinador_estatal',
            'coordinador_regional' => 'coordinador_regional',
            'coordinador_zona'     => 'coordinador_zona'
        ];

        foreach ($tablas_coord as $tipo => $tabla) {
            try {
                $stmt = $db->prepare("SELECT * FROM `$tabla` WHERE email = :id AND estatus = 'activo' LIMIT 1");
                $stmt->execute([':id' => $identificador]);
                $res = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($res) {
                    $usuario = $res;
                    $tipo_usuario = $tipo;
                    break;
                }
            } catch (Exception $e) {
                error_log("Error SQL buscando en $tabla: " . $e->getMessage());
            }
        }
    }

    // --- Búsqueda 5: Ciudadano ---
    if (!$usuario) {
        try {
            $stmt = $db->prepare("SELECT * FROM ciudadanos WHERE correo = :correo LIMIT 1");
            $stmt->execute([':correo' => $identificador]);
            $ciudadano = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($ciudadano) {
                $usuario      = $ciudadano;
                $tipo_usuario = 'ciudadano';
            }
        } catch (Exception $e) { }
    }

    // --- Búsqueda 6: Colectivo ---
    if (!$usuario) {
        try {
            $stmt = $db->prepare("SELECT * FROM colectivos WHERE correo = :correo LIMIT 1");
            $stmt->execute([':correo' => $identificador]);
            $colectivo = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($colectivo) {
                $usuario      = $colectivo;
                $tipo_usuario = 'colectivo';
            }
        } catch (Exception $e) { }
    }

    // 5. Validar si se encontró en alguna tabla
    if (!$usuario) {
        echo json_encode(['success' => false, 'message' => 'Usuario/Correo no registrado, sin acceso o cuenta inactiva']);
        exit;
    }

    // 6. Verificar Contraseña
    $passValida     = false;
    
    // MEJORA: Usamos array_key_exists para evitar fallos si la columna contrasena existe pero está en NULL
    $campo_password = array_key_exists('contrasena', $usuario) ? 'contrasena' : 'password';

    if (!empty($usuario[$campo_password])) {
        if (password_verify($password, $usuario[$campo_password])) {
            $passValida = true;
        } elseif ($password === $usuario[$campo_password]) {
            $passValida = true; // Compatibilidad texto plano temporal
        } elseif (md5($password) === $usuario[$campo_password]) {
            $passValida = true; // Compatibilidad MD5
        }
    }

    // 7. Configuración de Sesión y Redirección
    if ($passValida) {
        // Misma config de sesión que bootstrap.php
        ini_set("session.cookie_path", "/");
        ini_set("session.cookie_httponly", "1");
        ini_set("session.use_strict_mode", "1");
        ini_set("session.gc_maxlifetime", "7200");
        ini_set("session.cookie_lifetime", "0");
        session_name("GBSESSION");
        session_regenerate_id(true);
        $redirect = '';
        $mensaje  = '';

        if ($tipo_usuario === 'administrador') {
            $rol_limpio = strtolower(trim($usuario['rol'] ?? 'superadmin'));

            $_SESSION['user_id']   = $usuario['id'];
            $_SESSION['user_name'] = $usuario['nombre_completo'];
            $_SESSION['user_role'] = $rol_limpio;
            $_SESSION['is_admin']  = true;
            $_SESSION['admin_id']  = $usuario['id'];
            $_SESSION['admin_rol'] = $usuario['rol'];

            $db->prepare("UPDATE administradores SET ultimo_acceso = NOW() WHERE id = ?")->execute([$usuario['id']]);

            $redirect = '/App/admin/dashboard.php';
            $mensaje  = 'Bienvenido Administrador';
        }
        elseif ($tipo_usuario === 'circunscripcion') {
            $_SESSION['user_id']              = $usuario['id'];
            $_SESSION['user_name']            = $usuario['nombre_completo'];
            $_SESSION['user_role']            = 'circunscripcion';
            $_SESSION['user_circunscripcion'] = $usuario['circunscripcion'];

            $db->prepare("UPDATE coordinadores_circunscripcion SET ultimo_acceso = NOW() WHERE id = ?")->execute([$usuario['id']]);

            $redirect = '/App/circunscripcion/dashboard.php';
            $mensaje  = 'Bienvenido ' . $usuario['circunscripcion'];
        }
        elseif ($tipo_usuario === 'usuario_admin') {
            $rol_limpio = strtolower(trim($usuario['rol'] ?? 'admin'));

            $_SESSION['user_id']   = $usuario['id'];
            $_SESSION['user_name'] = $usuario['nombre_completo'];
            $_SESSION['user_role'] = $rol_limpio;

            $redirect = '/App/admin/dashboard.php';
            $mensaje  = 'Bienvenido Admin';
        }
        elseif ($tipo_usuario === 'coordinador_estatal' || $tipo_usuario === 'estatal') {
            $_SESSION['user_id']                = $usuario['id'];
            $_SESSION['user_name']              = $usuario['nombre'] ?? $usuario['nombre_completo'];
            $_SESSION['user_role']              = 'coordinador_estatal';
            $_SESSION['user_estado']            = $usuario['estado'];
            $_SESSION['user_email']             = $usuario['email'] ?? $usuario['correo'];
            $_SESSION['coordinador_estatal_id'] = $usuario['id'];

            try { $db->prepare("UPDATE coordinador_estatal SET ultima_actualizacion = NOW() WHERE id = ?")->execute([$usuario['id']]); } catch(Exception $e){}

            $redirect = '/App/state_coordinator/dashboard.php';
            $mensaje  = 'Bienvenido Coordinador Estatal';
        }
        elseif ($tipo_usuario === 'coordinador_regional' || $tipo_usuario === 'regional') {
            $_SESSION['user_id']                 = $usuario['id'];
            $_SESSION['user_name']               = $usuario['nombre'] ?? $usuario['nombre_completo'];
            $_SESSION['user_role']               = 'coordinador_regional';
            $_SESSION['user_estado']             = $usuario['estado'];
            
            // CORRECCIÓN CLAVE: Asignar la región a la sesión para que sus vistas funcionen
            $_SESSION['user_region']             = $usuario['region'] ?? null; 
            
            $_SESSION['user_email']              = $usuario['email'] ?? $usuario['correo'];
            $_SESSION['coordinador_regional_id'] = $usuario['id'];

            try { $db->prepare("UPDATE coordinador_regional SET ultima_actualizacion = NOW() WHERE id = ?")->execute([$usuario['id']]); } catch(Exception $e){}

            $redirect = '/App/regional_coordinator/dashboard.php';
            $mensaje  = 'Bienvenido Coordinador Regional';
        }
        elseif ($tipo_usuario === 'coordinador_zona' || $tipo_usuario === 'zona') {
            $_SESSION['user_id']                 = $usuario['id'];
            $_SESSION['user_name']               = $usuario['nombre'] ?? $usuario['nombre_completo'];
            $_SESSION['user_role']               = 'coordinador_zona';
            $_SESSION['user_estado']             = $usuario['estado'];
            
            // CORRECCIÓN CLAVE: Asignar región y zona
            $_SESSION['user_region']             = $usuario['region'] ?? null;
            $_SESSION['user_zona']               = $usuario['zona'] ?? null;
            
            $_SESSION['user_email']              = $usuario['email'] ?? $usuario['correo'];
            $_SESSION['coordinador_zona_id']     = $usuario['id'];

            try { $db->prepare("UPDATE coordinador_zona SET ultima_actualizacion = NOW() WHERE id = ?")->execute([$usuario['id']]); } catch(Exception $e){}

            $redirect = '/App/zone_coordinator/dashboard.php';
            $mensaje  = 'Bienvenido Coordinador Zona';
        }
        elseif ($tipo_usuario === 'seccion') {
            $_SESSION['user_id']             = $usuario['id'];
            $_SESSION['user_name']           = $usuario['nombre_completo'];
            $_SESSION['user_role']           = 'seccion';
            $_SESSION['user_estado']         = $usuario['estado'];
            $_SESSION['user_region']         = $usuario['region'] ?? null;
            $_SESSION['user_zona']           = $usuario['zona'] ?? null;

            $redirect = '/App/section_coordinator/dashboard.php';
            $mensaje  = 'Bienvenido Coordinador de Sección';
        }
        elseif ($tipo_usuario === 'ciudadano') {
            $_SESSION['user_id']   = $usuario['id'];
            $_SESSION['user_name'] = $usuario['nombre'] ?? 'Ciudadano';
            $_SESSION['user_role'] = 'ciudadano';

            $redirect = '/App/citizens/dashboard.php';
            $mensaje  = 'Bienvenido Ciudadano';
        }
        elseif ($tipo_usuario === 'colectivo') {
            $_SESSION['user_id']   = $usuario['id'];
            $_SESSION['user_name'] = $usuario['nombre'] ?? 'Colectivo';
            $_SESSION['user_role'] = 'colectivo';

            $redirect = '/App/collectives/dashboard.php';
            $mensaje  = 'Bienvenido Colectivo';
        }
        else {
            // Fallback por si hay un rol en la tabla usuarios que no tiene dashboard aún
            echo json_encode(['success' => false, 'message' => 'Tu perfil de '. strtoupper($tipo_usuario) .' no tiene un panel asignado todavía.']);
            exit;
        }

        echo json_encode([
            'success'  => true,
            'message'  => $mensaje,
            'redirect' => $redirect
        ]);

    } else {
        echo json_encode(['success' => false, 'message' => 'Contraseña incorrecta']);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error del Servidor: ' . $e->getMessage()
    ]);
}
?>