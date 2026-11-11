<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- 1. SEGURIDAD Y PERMISOS ---
$rol_sesion = $_SESSION['user_role'] ?? '';
$permitidos = ['admin', 'administrador', 'superadmin', 'super admin', 'circunscripcion', 'coordinador_estatal'];
if (!in_array($rol_sesion, $permitidos)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No tienes permisos para realizar esta acción.']);
    exit();
}

global $pdo; 
if (!$pdo && class_exists('Database')) {
    $pdo = Database::getInstance()->getConnection();
}

$method = $_SERVER['REQUEST_METHOD'];

// Lista blanca de tablas permitidas
$tablas_validas = ['usuarios', 'coordinador_estatal', 'coordinador_regional', 'coordinador_zona', 'coordinador_seccion'];

// ==========================================================
// 2. PETICIONES GET (LEER / OBTENER UN USUARIO)
// ==========================================================
if ($method === 'GET') {
    $action = $_GET['action'] ?? '';
    
    if ($action === 'get') {
        $id = intval($_GET['id'] ?? 0);
        $tabla = $_GET['table'] ?? 'usuarios';

        if (!in_array($tabla, $tablas_validas)) {
            echo json_encode(['success' => false, 'error' => 'Tabla no autorizada.']);
            exit();
        }
        
        try {
            $stmt = $pdo->prepare("SELECT * FROM `$tabla` WHERE id = ?");
            $stmt->execute([$id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                unset($user['contrasena']); 
                unset($user['password']);
                echo json_encode(['success' => true, 'user' => $user]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Usuario no encontrado en la base de datos.']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Error SQL: ' . $e->getMessage()]);
        }
        exit();
    }
}

// ==========================================================
// 3. PETICIONES POST (CREAR, EDITAR, ELIMINAR)
// ==========================================================
if ($method === 'POST') {
    $contentType = $_SERVER["CONTENT_TYPE"] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        $data = json_decode(file_get_contents('php://input'), true);
    } else {
        $data = $_POST;
    }

    if (!$data) {
        echo json_encode(['success' => false, 'error' => 'No se recibieron datos válidos.']);
        exit();
    }

    $action = $data['action'] ?? 'create'; 
    $id     = intval($data['userId'] ?? ($data['id'] ?? 0));
    $tabla  = $data['userTable'] ?? ($data['table'] ?? 'usuarios');

    if (!in_array($tabla, $tablas_validas) && $action !== 'create') {
        echo json_encode(['success' => false, 'error' => 'Tabla destino no válida.']);
        exit();
    }

    try {
        if ($action === 'delete') {
            if ($id <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID inválido para eliminar.']);
                exit();
            }
            $stmt = $pdo->prepare("DELETE FROM `$tabla` WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Usuario eliminado correctamente.']);
            exit();
        }

        // Normalización de variables
        $tipo            = trim($data['rol'] ?? ($data['tipo'] ?? ''));
        $requiere_acceso = isset($data['requiere_acceso']) && ($data['requiere_acceso'] === true || $data['requiere_acceso'] === 'true' || $data['requiere_acceso'] == 1);
        
        $nombre          = trim($data['nombre_pila'] ?? ($data['nombre'] ?? ''));
        $apellido_p      = trim($data['ap_paterno'] ?? ($data['apellido_p'] ?? ''));
        $apellido_m      = trim($data['ap_materno'] ?? ($data['apellido_m'] ?? ''));
        $nombre_completo = trim("$nombre $apellido_p $apellido_m");
        
        $telefono        = trim($data['telefono'] ?? '');
        $telefono_alt    = trim($data['telefono_alterno'] ?? '');
        $correo          = trim($data['email'] ?? ($data['correo'] ?? ''));
        $correo          = $correo !== '' ? $correo : null;
        
        $curp            = strtoupper(trim($data['curp'] ?? ''));
        $curp            = $curp !== '' ? $curp : null;
        $ine             = strtoupper(trim($data['ine'] ?? '')); 
        $ine             = $ine !== '' ? $ine : null;
        
        $username        = trim($data['username'] ?? '');
        if ($username === '' && $correo !== null) { $username = $correo; }
        $username        = $username !== '' ? $username : null;
        
        $estado_asignado = trim($data['estado'] ?? ''); 
        if (empty($estado_asignado) && !empty($data['estados'])) {
            $estado_asignado = is_array($data['estados']) ? $data['estados'][0] : $data['estados'];
        }
        
        // Formatear a NULL si están vacíos para evitar errores de Foreign Key en MySQL
        $region          = !empty($data['region']) ? intval($data['region']) : null;
        $zona            = !empty($data['zona']) ? intval($data['zona']) : null;
        
        $colonia         = trim($data['colonia'] ?? '');
        $calle           = trim($data['calle'] ?? '');
        $seccion         = trim($data['seccion_electoral'] ?? '');
        $password        = $data['password'] ?? null;
        
        // Campos extra
        $capacitaciones  = trim($data['capacitaciones'] ?? '');
        $disponibilidad  = trim($data['disponibilidad'] ?? '');
        $horario         = trim($data['horario'] ?? '');
        $dias_disp       = trim($data['dias_disponibles'] ?? '');
        $movilidad       = trim($data['movilidad'] ?? '');
        $estatus         = trim($data['estatus'] ?? 'Activo');
        $fecha_alta      = !empty($data['fecha_alta']) ? $data['fecha_alta'] : null;
        $fecha_baja      = !empty($data['fecha_baja']) ? $data['fecha_baja'] : null;

        if (empty($nombre) || empty($telefono) || empty($tipo) || empty($estado_asignado)) {
            echo json_encode(['success' => false, 'error' => 'Faltan campos obligatorios.']);
            exit();
        }

        if ($action === 'create') {
            $tabla_destino = $tipo;
            if (!in_array($tabla_destino, $tablas_validas)) {
                $tabla_destino = 'usuarios'; 
            }
        } else {
            $tabla_destino = $tabla; 
        }

        // Validación de Accesos
        if ($requiere_acceso || $tabla_destino !== 'usuarios') {
            if ($action === 'create' && (empty($username) || empty($password) || empty($correo))) {
                echo json_encode(['success' => false, 'error' => 'El correo y la contraseña son obligatorios.']);
                exit();
            }
            if (!empty($password) && strlen($password) < 8) {
                echo json_encode(['success' => false, 'error' => 'La contraseña debe tener al menos 8 caracteres.']);
                exit();
            }
        } elseif (!$requiere_acceso && $tabla_destino === 'usuarios') {
            $username = null;
            $password = null;
        }

        // --------------------------------------------------------
        // ACCIÓN: CREAR
        // --------------------------------------------------------
        if ($action === 'create') {
            
            $hashPassword = $password ? password_hash($password, PASSWORD_DEFAULT) : null;

            if ($tabla_destino === 'usuarios') {
                // Lógica original para la tabla usuarios
                if ($requiere_acceso) {
                    $stmtCheck = $pdo->prepare("SELECT id FROM usuarios WHERE username = ? OR correo = ?");
                    $stmtCheck->execute([$username, $correo]);
                    if ($stmtCheck->rowCount() > 0) {
                        echo json_encode(['success' => false, 'error' => 'El usuario o el correo ya están en uso.']);
                        exit();
                    }
                }
                $sql = "INSERT INTO usuarios (
                            rol, requiere_acceso, nombre_completo, telefono, correo, 
                            curp, ine, username, contrasena, estado, region, zona, 
                            colonia, direccion, seccion_electoral, fecha_registro, activo
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 1)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $tipo, $requiere_acceso ? 1 : 0, $nombre_completo, $telefono, $correo, 
                    $curp, $ine, $username, $hashPassword, $estado_asignado, $region, $zona, 
                    $colonia, $calle, $seccion
                ]);

            } elseif ($tabla_destino === 'coordinador_regional') {
                
                $stmtCheck = $pdo->prepare("SELECT id FROM coordinador_regional WHERE email = ?");
                $stmtCheck->execute([$correo]);
                if ($stmtCheck->rowCount() > 0) {
                    echo json_encode(['success' => false, 'error' => "El correo ya está en uso en coordinador regional."]);
                    exit();
                }

                $sql = "INSERT INTO coordinador_regional (
                            nombres, ap_paterno, ap_materno, nombre, telefono, 
                            email, estado, password, colonia, calle, seccion_electoral, estatus, region, fecha_registro
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $nombre, $apellido_p, $apellido_m, $nombre_completo, $telefono,
                    $correo, $estado_asignado, $hashPassword, $colonia, $calle, $seccion, strtolower($estatus), $region
                ]);

            } elseif ($tabla_destino === 'coordinador_zona') {
                
                $stmtCheck = $pdo->prepare("SELECT id FROM coordinador_zona WHERE email = ?");
                $stmtCheck->execute([$correo]);
                if ($stmtCheck->rowCount() > 0) {
                    echo json_encode(['success' => false, 'error' => "El correo ya está en uso en coordinador de zona."]);
                    exit();
                }

                $sql = "INSERT INTO coordinador_zona (
                            nombres, ap_paterno, ap_materno, nombre, telefono, 
                            email, estado, password, colonia, calle, seccion_electoral, estatus, region, zona, fecha_registro
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $nombre, $apellido_p, $apellido_m, $nombre_completo, $telefono,
                    $correo, $estado_asignado, $hashPassword, $colonia, $calle, $seccion, strtolower($estatus), $region, $zona
                ]);

            } else {
                // Fallback original para otras tablas (ej. coordinador_seccion) que sí puedan tener los campos extendidos
                $stmtCheck = $pdo->prepare("SELECT id FROM `$tabla_destino` WHERE email = ?");
                $stmtCheck->execute([$correo]);
                if ($stmtCheck->rowCount() > 0) {
                    echo json_encode(['success' => false, 'error' => "El correo ya está en uso."]);
                    exit();
                }
                $sql = "INSERT INTO `$tabla_destino` (
                            nombres, ap_paterno, ap_materno, nombre, telefono, telefono_alterno, 
                            email, estado, password, colonia, calle, seccion_electoral, estatus, 
                            capacitaciones, disponibilidad, horario, dias_disponibles, movilidad, 
                            fecha_alta, fecha_baja, fecha_registro
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $nombre, $apellido_p, $apellido_m, $nombre_completo, $telefono, $telefono_alt,
                    $correo, $estado_asignado, $hashPassword, $colonia, $calle, $seccion, strtolower($estatus),
                    $capacitaciones, $disponibilidad, $horario, $dias_disp, $movilidad, $fecha_alta, $fecha_baja
                ]);
            }

            echo json_encode(['success' => true, 'message' => 'Usuario creado exitosamente.', 'insert_id' => $pdo->lastInsertId()]);
            exit();
        }

        // --------------------------------------------------------
        // ACCIÓN: EDITAR
        // --------------------------------------------------------
        if ($action === 'edit') {
            if ($id <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID inválido para editar.']);
                exit();
            }

            if ($tabla_destino === 'usuarios') {
                if ($requiere_acceso && (!empty($username) || !empty($correo))) {
                    $stmtCheck = $pdo->prepare("SELECT id FROM usuarios WHERE (username = ? OR correo = ?) AND id != ?");
                    $stmtCheck->execute([$username, $correo, $id]);
                    if ($stmtCheck->rowCount() > 0) {
                        echo json_encode(['success' => false, 'error' => 'El usuario o correo ya está en uso.']);
                        exit();
                    }
                }
                // Detectar columnas disponibles para no fallar si region/zona no existen
                $colsStmt = $pdo->query("SHOW COLUMNS FROM usuarios");
                $colsUsuarios = array_column($colsStmt->fetchAll(PDO::FETCH_ASSOC), 'Field');

                $sql = "UPDATE usuarios SET 
                            rol = ?, requiere_acceso = ?, nombre_completo = ?, 
                            telefono = ?, correo = ?, curp = ?, ine = ?, username = ?, 
                            estado = ?, colonia = ?, direccion = ?, seccion_electoral = ?";
                $params = [
                    $tipo, $requiere_acceso ? 1 : 0, $nombre_completo, 
                    $telefono, $correo, $curp, $ine, $username, 
                    $estado_asignado, $colonia, $calle, $seccion
                ];
                if (in_array('region', $colsUsuarios)) {
                    $sql .= ', region = ?';
                    $params[] = $region;
                }
                if (in_array('zona', $colsUsuarios)) {
                    $sql .= ', zona = ?';
                    $params[] = $zona;
                }

                if (!empty($password)) {
                    $sql .= ", contrasena = ?";
                    $params[] = password_hash($password, PASSWORD_DEFAULT);
                }
                $sql .= " WHERE id = ?";
                $params[] = $id;

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

            } elseif ($tabla_destino === 'coordinador_estatal') {
                if (!empty($correo)) {
                    $stmtCheck = $pdo->prepare("SELECT id FROM coordinador_estatal WHERE email = ? AND id != ?");
                    $stmtCheck->execute([$correo, $id]);
                    if ($stmtCheck->rowCount() > 0) {
                        echo json_encode(['success' => false, 'error' => 'El correo ya está en uso.']);
                        exit();
                    }
                }
                // coordinador_estatal: NO tiene region ni zona
                $sql = "UPDATE coordinador_estatal SET 
                            nombres = ?, ap_paterno = ?, ap_materno = ?, nombre = ?,
                            telefono = ?, telefono_alterno = ?, email = ?, estado = ?,
                            colonia = ?, calle = ?, seccion_electoral = ?, estatus = ?,
                            capacitaciones = ?, disponibilidad = ?, horario = ?,
                            dias_disponibles = ?, movilidad = ?, fecha_alta = ?, fecha_baja = ?";
                $params = [
                    $nombre, $apellido_p, $apellido_m, $nombre_completo,
                    $telefono, $telefono_alt, $correo, $estado_asignado,
                    $colonia, $calle, $seccion, strtolower($estatus),
                    $capacitaciones, $disponibilidad, $horario,
                    $dias_disp, $movilidad, $fecha_alta, $fecha_baja
                ];
                if (!empty($password)) {
                    $sql .= ', password = ?';
                    $params[] = password_hash($password, PASSWORD_DEFAULT);
                }
                $sql .= " WHERE id = ?";
                $params[] = $id;

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

            } elseif ($tabla_destino === 'coordinador_regional') {
                if (!empty($correo)) {
                    $stmtCheck = $pdo->prepare("SELECT id FROM coordinador_regional WHERE email = ? AND id != ?");
                    $stmtCheck->execute([$correo, $id]);
                    if ($stmtCheck->rowCount() > 0) {
                        echo json_encode(['success' => false, 'error' => 'El correo ya está en uso.']);
                        exit();
                    }
                }
                // coordinador_regional: tiene region pero zona siempre NULL
                $sql = "UPDATE coordinador_regional SET 
                            nombres = ?, ap_paterno = ?, ap_materno = ?, nombre = ?,
                            telefono = ?, telefono_alterno = ?, email = ?, estado = ?,
                            colonia = ?, calle = ?, seccion_electoral = ?, estatus = ?,
                            region = ?";
                $params = [
                    $nombre, $apellido_p, $apellido_m, $nombre_completo,
                    $telefono, $telefono_alt, $correo, $estado_asignado,
                    $colonia, $calle, $seccion, strtolower($estatus),
                    $region
                ];
                if (!empty($password)) {
                    $sql .= ", password = ?";
                    $params[] = password_hash($password, PASSWORD_DEFAULT);
                }
                $sql .= " WHERE id = ?";
                $params[] = $id;

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

            } elseif ($tabla_destino === 'coordinador_zona') {
                if (!empty($correo)) {
                    $stmtCheck = $pdo->prepare("SELECT id FROM coordinador_zona WHERE email = ? AND id != ?");
                    $stmtCheck->execute([$correo, $id]);
                    if ($stmtCheck->rowCount() > 0) {
                        echo json_encode(['success' => false, 'error' => 'El correo ya está en uso.']);
                        exit();
                    }
                }
                // coordinador_zona: tiene region Y zona
                $sql = "UPDATE coordinador_zona SET 
                            nombres = ?, ap_paterno = ?, ap_materno = ?, nombre = ?,
                            telefono = ?, telefono_alterno = ?, email = ?, estado = ?,
                            colonia = ?, calle = ?, seccion_electoral = ?, estatus = ?,
                            region = ?, zona = ?";
                $params = [
                    $nombre, $apellido_p, $apellido_m, $nombre_completo,
                    $telefono, $telefono_alt, $correo, $estado_asignado,
                    $colonia, $calle, $seccion, strtolower($estatus),
                    $region, $zona
                ];
                if (!empty($password)) {
                    $sql .= ", password = ?";
                    $params[] = password_hash($password, PASSWORD_DEFAULT);
                }
                $sql .= " WHERE id = ?";
                $params[] = $id;

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

            } else {
                // Fallback para las otras tablas
                if (!empty($correo)) {
                    $stmtCheck = $pdo->prepare("SELECT id FROM `$tabla_destino` WHERE email = ? AND id != ?");
                    $stmtCheck->execute([$correo, $id]);
                    if ($stmtCheck->rowCount() > 0) {
                        echo json_encode(['success' => false, 'error' => 'El correo ya está en uso.']);
                        exit();
                    }
                }
                $sql = "UPDATE `$tabla_destino` SET 
                            nombres = ?, ap_paterno = ?, ap_materno = ?, nombre = ?, 
                            telefono = ?, telefono_alterno = ?, email = ?, estado = ?, 
                            colonia = ?, calle = ?, seccion_electoral = ?, estatus = ?,
                            capacitaciones = ?, disponibilidad = ?, horario = ?, 
                            dias_disponibles = ?, movilidad = ?, fecha_alta = ?, fecha_baja = ?";
                $params = [
                    $nombre, $apellido_p, $apellido_m, $nombre_completo, $telefono, $telefono_alt, 
                    $correo, $estado_asignado, $colonia, $calle, $seccion, strtolower($estatus),
                    $capacitaciones, $disponibilidad, $horario, $dias_disp, $movilidad, $fecha_alta, $fecha_baja
                ];
                if (!empty($password)) {
                    $sql .= ", password = ?";
                    $params[] = password_hash($password, PASSWORD_DEFAULT);
                }
                $sql .= " WHERE id = ?";
                $params[] = $id;

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            }

            echo json_encode(['success' => true, 'message' => 'Usuario actualizado correctamente.']);
            exit();
        }

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Error SQL: ' . $e->getMessage()]);
    }
}
?>