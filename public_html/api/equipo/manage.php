<?php
header('Content-Type: application/json; charset=utf-8');

try {
    // 1. Carga del sistema y seguridad
    require_once __DIR__ . '/../../includes/bootstrap.php';

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $rol_sesion = strtolower($_SESSION['user_role'] ?? '');
    
    // --- 2. MAPEO EXACTO DE JERARQUÍAS (CANDADO DE SEGURIDAD) ---
    $jerarquia_roles = [
        'admin'                         => 1,
        'administrador'                 => 1,
        'superadmin'                    => 1,
        'super admin'                   => 1,
        'coordinadores_circunscripcion' => 2,
        'coordinador_circunscripcion'   => 2,
        'circunscripcion'               => 2,
        'coordinador_estatal'           => 3,
        'coordinador_regional'          => 4,
        'coordinador_zona'              => 5,
        'coordinador_seccion'           => 6,
        'usuarios'                      => 6 // La tabla general se considera el nivel más bajo
    ];

    $mi_nivel = $jerarquia_roles[$rol_sesion] ?? 99; // Si no existe, nivel bajo sin permisos
    $es_admin = ($mi_nivel === 1);

    // Niveles 1 al 4 pueden editar. Nivel 5 (Zona) y superiores solo lectura.
    $puede_editar = ($mi_nivel <= 4);
    // Nivel 5 (Zona) puede además CREAR perfiles de su equipo (tabla usuarios).
    $puede_crear  = ($mi_nivel <= 5);

    $db = Database::getInstance()->getConnection();
    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    // --- 2.5 CANDADO DE PROPIEDAD (evita IDOR: solo hierarchy no bastaba) ---
    // Resolvemos el alcance real del usuario que hace la petición: qué
    // estados puede tocar y, si aplica, a qué región/zona está limitado.
    $mi_user_id = $_SESSION['user_id'] ?? 0;
    $mis_estados_permitidos = [];
    $mi_region_id_scope = 0;
    $mi_zona_id_scope = 0;

    if (!$es_admin) {
        $mapa_circunscripciones = [
            'Circunscripción 1' => ['Baja California', 'Baja California Sur', 'Chihuahua', 'Durango', 'Jalisco', 'Nayarit', 'Sinaloa', 'Sonora'],
            'Circunscripción 2' => ['Aguascalientes', 'Coahuila', 'Guanajuato', 'Nuevo León', 'San Luis Potosí', 'Tamaulipas', 'Zacatecas'],
            'Circunscripción 3' => ['Campeche', 'Chiapas', 'Oaxaca', 'Quintana Roo', 'Tabasco', 'Veracruz', 'Yucatán'],
            'Circunscripción 4' => ['Ciudad de México', 'Guerrero', 'Hidalgo', 'Morelos', 'Puebla', 'Tlaxcala'],
            'Circunscripción 5' => ['Colima', 'Estado de México', 'Michoacán', 'Querétaro']
        ];

        if (in_array($rol_sesion, ['circunscripcion', 'coordinador_circunscripcion', 'coordinadores_circunscripcion'])) {
            $stmtCirc = $db->prepare("SELECT circunscripcion FROM coordinadores_circunscripcion WHERE id = ?");
            $stmtCirc->execute([$mi_user_id]);
            $mi_circ = trim($stmtCirc->fetchColumn());
            $mis_estados_permitidos = $mapa_circunscripciones[$mi_circ] ?? [];
        } elseif (in_array($rol_sesion, ['coordinador_estatal', 'coordinador_regional', 'coordinador_zona'])) {
            $cols = 'TRIM(estado) as estado';
            if ($rol_sesion !== 'coordinador_estatal') $cols .= ', region';
            if ($rol_sesion === 'coordinador_zona')     $cols .= ', zona';
            $stmtProp = $db->prepare("SELECT $cols FROM `$rol_sesion` WHERE id = ?");
            $stmtProp->execute([$mi_user_id]);
            $propRow = $stmtProp->fetch(PDO::FETCH_ASSOC);
            if ($propRow) {
                $mis_estados_permitidos = [$propRow['estado']];
                if (array_key_exists('region', $propRow)) $mi_region_id_scope = (int)$propRow['region'];
                if (array_key_exists('zona', $propRow))   $mi_zona_id_scope   = (int)$propRow['zona'];
            }
        }
    }

    // Verifica que el registro $userId de la tabla $tabla pertenezca al
    // alcance (estado / región / zona) del usuario que hace la petición.
    // Se llama SIEMPRE antes de get/edit/delete, sin importar la jerarquía,
    // porque la jerarquía por sí sola no evita que un regional toque
    // registros de otra región del mismo estado.
    function validarPropiedadRegistro($db, $tabla, $userId, $es_admin, $rol_sesion, $mis_estados_permitidos, $mi_region_id_scope, $mi_zona_id_scope) {
        if ($es_admin) return true;
        if (empty($userId)) return false;

        if ($tabla === 'usuarios') {
            $stmt = $db->prepare("SELECT estado, region, zona FROM usuarios WHERE id = ?");
        } elseif ($tabla === 'coordinadores_circunscripcion') {
            $stmt = $db->prepare("SELECT circunscripcion as estado, NULL as region, NULL as zona FROM coordinadores_circunscripcion WHERE id = ?");
        } elseif ($tabla === 'coordinador_estatal') {
            $stmt = $db->prepare("SELECT estado, NULL as region, NULL as zona FROM coordinador_estatal WHERE id = ?");
        } elseif ($tabla === 'coordinador_regional') {
            $stmt = $db->prepare("SELECT estado, region, NULL as zona FROM coordinador_regional WHERE id = ?");
        } elseif (in_array($tabla, ['coordinador_zona', 'coordinador_seccion'])) {
            $stmt = $db->prepare("SELECT estado, region, zona FROM `$tabla` WHERE id = ?");
        } else {
            return false; // tabla desconocida: negar por defecto
        }

        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return false; // no existe: no autorizado (evita enumerar IDs)

        if (!empty($mis_estados_permitidos) && !in_array(trim((string)($row['estado'] ?? '')), $mis_estados_permitidos, true)) {
            return false;
        }

        if ($rol_sesion === 'coordinador_regional' && $mi_region_id_scope > 0
            && array_key_exists('region', $row) && $row['region'] !== null
            && (int)$row['region'] !== $mi_region_id_scope) {
            return false;
        }

        if ($rol_sesion === 'coordinador_zona' && $mi_zona_id_scope > 0
            && array_key_exists('zona', $row) && $row['zona'] !== null
            && (int)$row['zona'] !== $mi_zona_id_scope) {
            return false;
        }

        return true;
    }

    // Devuelve las secciones (como strings) configuradas en zonas_estatales
    // para una zona, o null si la zona no existe.
    function obtenerSeccionesZona($db, $zonaId) {
        if (empty($zonaId)) return null;
        $stmt = $db->prepare("SELECT secciones FROM zonas_estatales WHERE id = ?");
        $stmt->execute([(int)$zonaId]);
        $raw = $stmt->fetchColumn();
        if ($raw === false) return null;
        $arr = json_decode((string)$raw, true);
        return is_array($arr) ? array_map('strval', $arr) : [];
    }

    // Permisos por acción:
    // - Nivel 1-4: todo.
    // - Nivel 5 (Zona): ver fichas y crear perfiles de su equipo.
    // - Resto: solo ver fichas.
    if ($action === 'create') {
        if (!$puede_crear) {
            throw new Exception("Solo lectura. No tienes permisos para crear usuarios.");
        }
    } elseif (!$puede_editar && $action !== 'get') {
        throw new Exception("Solo lectura. No tienes permisos para modificar o eliminar usuarios.");
    }

    // Clasificación de tablas permitidas
    $tablas_coordinadores = [
        'coordinadores_circunscripcion', 'coordinador_estatal', 'coordinador_regional', 
        'coordinador_zona', 'coordinador_seccion',
        'coordinador_municipal', 'coordinador_distrital', 'responsable_zona', 'promotor'
    ];

    // Schema por tabla (usado tanto en create como en edit)
    $tablasTienenRegion = ['coordinador_regional', 'coordinador_zona', 'coordinador_seccion'];
    $tablasTienenZona   = ['coordinador_zona', 'coordinador_seccion'];

    // Tablas donde existe el par de columnas es_espejo / espejo_de
    // (requiere la migración que agrega estas columnas a estas 3 tablas)
    $tablasConEspejo = ['coordinadores_circunscripcion', 'coordinador_estatal', 'coordinador_regional'];
    
    // Nombres cortos que viajan desde el frontend y pertenecen a la tabla "usuarios"
    $roles_usuarios_generales = [
        'operador', 'movilizador', 'ciudadano', 'voluntario', 'simpatizante',
        'estatal', 'regional', 'zona', 'seccion' 
    ];

    // Roles (de la tabla usuarios) que pueden tener varias secciones asignadas
    $roles_con_secciones = ['operador', 'movilizador'];

    // --- 3. REGLA ESTRICTA DE VISIBILIDAD EN BACKEND ---
    $tabla_destino = '';
    if ($action === 'create') {
        $tabla_destino = trim($_POST['rol'] ?? '');
    } elseif ($action === 'edit' || $action === 'delete') {
        // Robustez: Buscamos variaciones posibles enviadas por el Frontend
        $tabla_destino = trim($_POST['table'] ?? $_POST['userTable'] ?? $_POST['rol'] ?? '');
    } elseif ($action === 'get') {
        $tabla_destino = trim($_GET['table'] ?? '');
    }

    // Calcular el nivel real del usuario que se quiere afectar
    $rol_evaluar = in_array($tabla_destino, $roles_usuarios_generales) ? 'usuarios' : $tabla_destino;
    $nivel_destino = $jerarquia_roles[$rol_evaluar] ?? 1; // Default a 1 para proteger tablas desconocidas

    // Si no es admin y quiere modificar/crear/borrar, debe ser sobre alguien de menor jerarquía (número mayor)
    if (!$es_admin && in_array($action, ['create', 'edit', 'delete'])) {
        if ($nivel_destino <= $mi_nivel) {
            throw new Exception("Violación de Seguridad: No tienes jerarquía suficiente para modificar a este tipo de usuario.");
        }
    }

    // --- 4. EJECUCIÓN DE ACCIONES (CRUD) ---
    switch ($action) {
        case 'create':
            $nombres = trim($_POST['nombre_pila'] ?? '');
            $apPaterno = trim($_POST['ap_paterno'] ?? '');
            $apMaterno = trim($_POST['ap_materno'] ?? '');
            $nombreCompleto = trim("$nombres $apPaterno $apMaterno");
            
            $telefono = trim($_POST['telefono'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            
            $estado = trim($_POST['estado'] ?? '');
            $colonia = trim($_POST['colonia'] ?? '');
            $calle = trim($_POST['calle'] ?? '');
            $seccion = trim($_POST['seccion_electoral'] ?? '');
            $rolSeleccionado = trim($_POST['rol'] ?? '');

            $esTablaUsuarios = in_array($rolSeleccionado, $roles_usuarios_generales);
            $tablaDestino = $esTablaUsuarios ? 'usuarios' : $rolSeleccionado;

            if (empty($nombres) || empty($apPaterno) || empty($rolSeleccionado)) {
                throw new Exception("Nombre, Apellido Paterno y Rol son obligatorios.");
            }
            // El correo solo es obligatorio para perfiles con acceso al sistema
            if (empty($email) && (!$esTablaUsuarios || !empty($password))) {
                throw new Exception("El correo electrónico es obligatorio para este perfil.");
            }

            // Un Coordinador de Zona solo puede crear perfiles de la tabla usuarios
            if ($rol_sesion === 'coordinador_zona' && !$esTablaUsuarios) {
                throw new Exception("Violación de Seguridad: solo puedes crear perfiles de tu equipo operativo.");
            }

            // --- USUARIO ESPEJO: mismo alcance que un titular existente ---
            // Solo aplica a circunscripción, estatal y regional. El front
            // manda es_espejo=1 y espejo_de=<id del titular>.
            $es_espejo = !empty($_POST['es_espejo']) && $_POST['es_espejo'] == '1';
            $espejo_de = !empty($_POST['espejo_de']) ? (int)$_POST['espejo_de'] : null;
            $titularEspejo = null;

            if ($es_espejo) {
                if (!in_array($tablaDestino, $tablasConEspejo)) {
                    throw new Exception("El tipo de perfil seleccionado no admite usuario espejo.");
                }
                if (empty($espejo_de)) {
                    throw new Exception("Debes seleccionar el titular que se va a reflejar.");
                }

                // El titular debe estar dentro del alcance de quien lo crea
                // (misma regla de propiedad que usamos para editar/eliminar).
                if (!validarPropiedadRegistro($db, $tablaDestino, $espejo_de, $es_admin, $rol_sesion, $mis_estados_permitidos, $mi_region_id_scope, $mi_zona_id_scope)) {
                    throw new Exception("Ese titular no pertenece a tu área de cobertura.");
                }

                $stmtTitular = $db->prepare("SELECT * FROM `$tablaDestino` WHERE id = :id");
                $stmtTitular->execute([':id' => $espejo_de]);
                $titularEspejo = $stmtTitular->fetch(PDO::FETCH_ASSOC);

                if (!$titularEspejo) {
                    throw new Exception("El titular seleccionado no existe.");
                }
                if (!empty($titularEspejo['es_espejo'])) {
                    throw new Exception("No puedes crear un espejo de otro espejo. Selecciona al titular original.");
                }

                $stmtEspejoExistente = $db->prepare("SELECT id FROM `$tablaDestino` WHERE espejo_de = :tid");
                $stmtEspejoExistente->execute([':tid' => $espejo_de]);
                if ($stmtEspejoExistente->fetch()) {
                    throw new Exception("Ese titular ya tiene un usuario espejo asignado.");
                }

                // El espejo hereda el alcance territorial exacto del titular,
                // sin importar lo que haya mandado el formulario.
                $estado = $titularEspejo['estado'] ?? $estado;
                if (array_key_exists('region', $titularEspejo)) {
                    $_POST['region_id'] = $titularEspejo['region'];
                }
            } else {
                $espejo_de = null;
            }

            if (in_array($rolSeleccionado, $tablas_coordinadores) || !empty($password)) {
                if (empty($password)) throw new Exception("La contraseña es obligatoria para este perfil.");
                if ($password !== $confirmPassword) throw new Exception("Las contraseñas no coinciden.");
                if (strlen($password) < 8) throw new Exception("La contraseña debe tener al menos 8 caracteres.");
            }

            $passwordHash = !empty($password) ? password_hash($password, PASSWORD_DEFAULT) : null;

            // VALIDACIÓN DE DUPLICADOS CORREGIDA
            if ($esTablaUsuarios) {
                if (!empty($email)) {
                    $stmt = $db->prepare("SELECT id FROM usuarios WHERE correo = :correo");
                    $stmt->execute([':correo' => $email]);
                    if ($stmt->fetch()) throw new Exception("El correo electrónico ya está registrado en el sistema.");
                }
            } else {
                if(!in_array($tablaDestino, $tablas_coordinadores)) throw new Exception("Rol destino no válido.");
                
                if ($tablaDestino === 'coordinadores_circunscripcion') {
                    $stmt = $db->prepare("SELECT id FROM `$tablaDestino` WHERE correo = :correo");
                    $stmt->execute([':correo' => $email]);
                } else {
                    $stmt = $db->prepare("SELECT id FROM `$tablaDestino` WHERE email = :email");
                    $stmt->execute([':email' => $email]);
                }
                
                if ($stmt->fetch()) throw new Exception("El correo electrónico ya está asignado a otro coordinador.");
            }

            // CREAR EN TABLA USUARIOS
            if ($esTablaUsuarios) {
                $estatus_val = (strtolower(trim($_POST['estatus'] ?? 'activo')) === 'activo') ? 1 : 0;

                $region_id = !empty($_POST['region_id']) ? (int)$_POST['region_id'] : null;
                $zona_id   = !empty($_POST['zona_id'])   ? (int)$_POST['zona_id']   : null;

                // Coordinador de Zona: el alcance territorial se fuerza al suyo,
                // sin importar lo que mande el formulario.
                if ($rol_sesion === 'coordinador_zona') {
                    if ($mi_zona_id_scope <= 0) {
                        throw new Exception("No tienes una zona asignada en el sistema.");
                    }
                    $estado    = $mis_estados_permitidos[0] ?? $estado;
                    $region_id = $mi_region_id_scope > 0 ? $mi_region_id_scope : $region_id;
                    $zona_id   = $mi_zona_id_scope;
                }

                $seccionesZona = $zona_id ? obtenerSeccionesZona($db, $zona_id) : null;
                $seccionesJson = null;

                if (in_array($rolSeleccionado, $roles_con_secciones) && isset($_POST['secciones'])) {
                    // Varias secciones (Operador / Movilizador)
                    $lista = json_decode((string)$_POST['secciones'], true);
                    if (!is_array($lista)) {
                        throw new Exception("Formato de secciones inválido.");
                    }
                    $lista = array_map(fn($s) => preg_replace('/\D/', '', (string)$s), $lista);
                    $lista = array_values(array_unique(array_filter($lista, 'strlen')));

                    if (empty($lista)) {
                        throw new Exception("Asigna al menos una sección.");
                    }
                    if ($seccionesZona === null) {
                        throw new Exception("Para asignar varias secciones se requiere una zona válida.");
                    }
                    $fuera = array_diff($lista, $seccionesZona);
                    if (!empty($fuera)) {
                        throw new Exception("Las secciones " . implode(', ', $fuera) . " no pertenecen a la zona.");
                    }

                    usort($lista, fn($a, $b) => (int)$a - (int)$b);
                    $seccionesJson = json_encode($lista);
                    $seccion = $lista[0]; // compatibilidad con seccion_electoral
                } elseif ($rol_sesion === 'coordinador_zona') {
                    if (in_array($rolSeleccionado, $roles_con_secciones)) {
                        throw new Exception("Asigna al menos una sección.");
                    }
                    // Sección única (ej. Ciudadano): debe pertenecer a su zona
                    if ($seccion !== '' && $seccionesZona !== null && !in_array($seccion, $seccionesZona, true)) {
                        throw new Exception("La sección $seccion no pertenece a tu zona.");
                    }
                }
                
                $sql = "INSERT INTO usuarios (
                            nombre_completo, telefono, correo, username, contrasena, 
                            colonia, direccion, estado, region, zona, seccion_electoral, secciones,
                            rol, activo, fecha_registro
                        ) VALUES (
                            :nombre_completo, :telefono, :correo, :username, :contrasena, 
                            :colonia, :direccion, :estado, :region, :zona, :seccion, :secciones,
                            :rol, :activo, NOW()
                        )";
                
                $stmt = $db->prepare($sql);
                $result = $stmt->execute([
                    ':nombre_completo' => $nombreCompleto, ':telefono' => $telefono,
                    ':correo' => $email !== '' ? $email : null,
                    ':username' => $email !== '' ? $email : null,
                    ':contrasena' => $passwordHash, ':colonia' => $colonia,
                    ':direccion' => $calle, ':estado' => $estado,
                    ':region' => $region_id, ':zona' => $zona_id,
                    ':seccion' => $seccion ?: 0, ':secciones' => $seccionesJson,
                    ':rol' => $rolSeleccionado, ':activo' => $estatus_val
                ]);
            
            // CREAR EN TABLA CIRCUNSCRIPCION (Estructura especial)
            } elseif ($tablaDestino === 'coordinadores_circunscripcion') {
                $estatus_val = (strtolower(trim($_POST['estatus'] ?? 'activo')) === 'activo') ? 1 : 0;

                $espejoSQL = in_array($tablaDestino, $tablasConEspejo) ? ', es_espejo, espejo_de' : '';
                $espejoValoresSQL = in_array($tablaDestino, $tablasConEspejo) ? ', :es_espejo, :espejo_de' : '';

                $sql = "INSERT INTO coordinadores_circunscripcion (
                            nombre_completo, correo, contrasena, circunscripcion, activo, creado_por
                            $espejoSQL
                        ) VALUES (
                            :nombre_completo, :correo, :contrasena, :circunscripcion, :activo, :creado_por
                            $espejoValoresSQL
                        )";
                $params = [
                    ':nombre_completo' => $nombreCompleto, ':correo' => $email, ':contrasena' => $passwordHash, 
                    ':circunscripcion' => $estado, // El front manda la circunscripcion en el campo estado
                    ':activo' => $estatus_val, ':creado_por' => $_SESSION['user_id'] ?? null
                ];
                if (in_array($tablaDestino, $tablasConEspejo)) {
                    $params[':es_espejo'] = $es_espejo ? 1 : 0;
                    $params[':espejo_de'] = $espejo_de;
                }
                $stmt = $db->prepare($sql);
                $result = $stmt->execute($params);

            // CREAR EN RESTO DE COORDINADORES (Estatal, Regional, Zona, etc)
            } else {
                $telefonoAlterno = trim($_POST['telefono_alterno'] ?? '');
                $estatus = trim($_POST['estatus'] ?? 'activo');
                // Prevenir crash con ENUM si mandan "baja" al crear
                $estatus_enum = (strtolower($estatus) === 'activo') ? 'activo' : 'inactivo';

                // Capturar Region y Zona enviadas desde el frontend
                $region_id = !empty($_POST['region_id']) ? (int)$_POST['region_id'] : null;
                $zona_id = !empty($_POST['zona_id']) ? (int)$_POST['zona_id'] : null;

                // Schema por tabla: no todas las tablas de coordinador tienen
                // columnas region/zona (ej. coordinador_estatal no las tiene).
                $regionColSQL = in_array($tablaDestino, $tablasTienenRegion) ? ', region' : '';
                $regionValSQL = in_array($tablaDestino, $tablasTienenRegion) ? ', :region' : '';
                $zonaColSQL   = in_array($tablaDestino, $tablasTienenZona)   ? ', zona'   : '';
                $zonaValSQL   = in_array($tablaDestino, $tablasTienenZona)   ? ', :zona'  : '';
                $espejoColSQL = in_array($tablaDestino, $tablasConEspejo)    ? ', es_espejo, espejo_de' : '';
                $espejoValSQL = in_array($tablaDestino, $tablasConEspejo)    ? ', :es_espejo, :espejo_de' : '';

                $sql = "INSERT INTO `$tablaDestino` (
                            nombres, ap_paterno, ap_materno, nombre, telefono, telefono_alterno, email, password, 
                            estado, colonia, calle, seccion_electoral, estatus, fecha_registro
                            $regionColSQL $zonaColSQL $espejoColSQL
                        ) VALUES (
                            :nombres, :ap_paterno, :ap_materno, :nombre_completo, :telefono, :telefono_alterno, :email, :password, 
                            :estado, :colonia, :calle, :seccion, :estatus, NOW()
                            $regionValSQL $zonaValSQL $espejoValSQL
                        )";
                
                $params = [
                    ':nombres' => $nombres, ':ap_paterno' => $apPaterno, ':ap_materno' => $apMaterno, 
                    ':nombre_completo' => $nombreCompleto, ':telefono' => $telefono, ':telefono_alterno' => $telefonoAlterno, 
                    ':email' => $email, ':password' => $passwordHash, ':estado' => $estado, ':colonia' => $colonia, 
                    ':calle' => $calle, ':seccion' => $seccion, ':estatus' => $estatus_enum
                ];
                if (in_array($tablaDestino, $tablasTienenRegion)) $params[':region'] = $region_id;
                if (in_array($tablaDestino, $tablasTienenZona))   $params[':zona']   = $zona_id;
                if (in_array($tablaDestino, $tablasConEspejo)) {
                    $params[':es_espejo'] = $es_espejo ? 1 : 0;
                    $params[':espejo_de'] = $espejo_de;
                }

                $stmt = $db->prepare($sql);
                $result = $stmt->execute($params);
            }

            // RESPUESTA CON LAST_INSERT_ID PARA EL FRONTEND
            if ($result) {
                $nuevoId = $db->lastInsertId();
                echo json_encode([
                    'success' => true, 
                    'message' => 'Usuario creado exitosamente',
                    'insert_id' => $nuevoId
                ]);
            } else {
                throw new Exception("Error al crear el usuario en la base de datos.");
            }
            break;

        case 'edit':
            // ROBUSTEZ: Capturar variaciones de IDs desde el frontend
            $userId = $_POST['id'] ?? $_POST['userId'] ?? '';
            $tablaActual = trim($_POST['table'] ?? $_POST['userTable'] ?? $_POST['rol'] ?? '');

            // CANDADO DE PROPIEDAD: el registro que se va a editar debe
            // pertenecer al estado/región/zona del usuario que hace la
            // petición (la jerarquía por sí sola no lo garantiza).
            $tablaActualReal = in_array($tablaActual, $roles_usuarios_generales) ? 'usuarios' : $tablaActual;
            if (!validarPropiedadRegistro($db, $tablaActualReal, $userId, $es_admin, $rol_sesion, $mis_estados_permitidos, $mi_region_id_scope, $mi_zona_id_scope)) {
                throw new Exception("Violación de Seguridad: ese registro no pertenece a tu área de cobertura.");
            }
            
            $nombres = trim($_POST['nombre_pila'] ?? '');
            $apPaterno = trim($_POST['ap_paterno'] ?? '');
            $apMaterno = trim($_POST['ap_materno'] ?? '');
            $nombreCompleto = trim("$nombres $apPaterno $apMaterno");

            $telefono = trim($_POST['telefono'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $estado = trim($_POST['estado'] ?? '');
            $colonia = trim($_POST['colonia'] ?? '');
            $calle = trim($_POST['calle'] ?? '');
            $seccion = trim($_POST['seccion_electoral'] ?? '');
            $estatusFront = trim($_POST['estatus'] ?? 'Activo');
            
            // NUEVO: Capturar Regiones y Zonas
            $region_id = !empty($_POST['region_id']) ? (int)$_POST['region_id'] : null;
            $zona_id = !empty($_POST['zona_id']) ? (int)$_POST['zona_id'] : null;

            $password = $_POST['password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (empty($userId) || empty($nombres) || empty($apPaterno) || empty($email) || empty($tablaActual)) {
                throw new Exception("Faltan datos obligatorios para editar (Revisa nombres, correo, y permisos de rol).");
            }

            // Validar password
            $passwordSQL = ""; 
            $passwordParams = [];
            if (!empty($password)) {
                if ($password !== $confirmPassword) throw new Exception("Las contraseñas no coinciden");
                if (strlen($password) < 8) throw new Exception("La contraseña es muy corta");
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                
                $columnaPass = ($tablaActual === 'usuarios' || in_array($tablaActual, $roles_usuarios_generales) || $tablaActual === 'coordinadores_circunscripcion') ? 'contrasena' : 'password';
                $passwordSQL = ", $columnaPass = :password";
                $passwordParams = [':password' => $passwordHash];
            }

            // EDITAR TABLA USUARIOS
            if ($tablaActual === 'usuarios' || in_array($tablaActual, $roles_usuarios_generales)) {
                $tablaReal = 'usuarios';
                
                $stmtCheck = $db->prepare("SELECT id FROM usuarios WHERE correo = :correo AND id != :id");
                $stmtCheck->execute([':correo' => $email, ':id' => $userId]);
                if ($stmtCheck->fetch()) throw new Exception("El correo ya pertenece a otro usuario.");

                $estatus_val = (strtolower($estatusFront) === 'activo') ? 1 : 0;

                $sql = "UPDATE usuarios SET 
                            nombre_completo = :nombre_completo, telefono = :telefono, correo = :correo, 
                            estado = :estado, colonia = :colonia, direccion = :direccion, 
                            seccion_electoral = :seccion, activo = :activo,
                            region = :region, zona = :zona
                            $passwordSQL 
                        WHERE id = :id";
                
                $params = [
                    ':nombre_completo' => $nombreCompleto, ':telefono' => $telefono, ':correo' => $email,
                    ':estado' => $estado, ':colonia' => $colonia, ':direccion' => $calle, 
                    ':seccion' => $seccion ?: 0, ':activo' => $estatus_val, 
                    ':region' => $region_id, ':zona' => $zona_id, ':id' => $userId
                ];
                
                $params = array_merge($params, $passwordParams);
                $db->prepare($sql)->execute($params);

            // EDITAR TABLA CIRCUNSCRIPCION (Estructura especial)
            } elseif ($tablaActual === 'coordinadores_circunscripcion') {
                $stmtCheck = $db->prepare("SELECT id FROM coordinadores_circunscripcion WHERE correo = :correo AND id != :id");
                $stmtCheck->execute([':correo' => $email, ':id' => $userId]);
                if ($stmtCheck->fetch()) throw new Exception("El correo ya pertenece a otro coordinador.");

                $estatus_val = (strtolower($estatusFront) === 'activo') ? 1 : 0;

                $sql = "UPDATE coordinadores_circunscripcion SET 
                            nombre_completo = :nombre_completo, correo = :correo, 
                            circunscripcion = :circunscripcion, activo = :activo
                            $passwordSQL 
                        WHERE id = :id";
                
                $params = [
                    ':nombre_completo' => $nombreCompleto, ':correo' => $email,
                    ':circunscripcion' => $estado, // El front lo envía en el campo estado
                    ':activo' => $estatus_val, ':id' => $userId
                ];
                $params = array_merge($params, $passwordParams);
                $db->prepare($sql)->execute($params);

            // EDITAR RESTO DE COORDINADORES
            } else {
                if (!in_array($tablaActual, $tablas_coordinadores)) throw new Exception("Tabla de coordinador no válida ($tablaActual).");

                $stmtCheck = $db->prepare("SELECT id FROM `$tablaActual` WHERE email = :email AND id != :id");
                $stmtCheck->execute([':email' => $email, ':id' => $userId]);
                if ($stmtCheck->fetch()) throw new Exception("El correo ya pertenece a otro coordinador.");

                $telefonoAlterno = trim($_POST['telefono_alterno'] ?? '');

                // FIX ENUM: Mapeo de seguridad para evitar Crash de MySQL con "Baja"
                $estatus_enum = (strtolower($estatusFront) === 'activo') ? 'activo' : 'inactivo';

                // Schema por tabla: coordinador_estatal NO tiene region/zona
                // (arrays $tablasTienenRegion / $tablasTienenZona definidos al inicio del archivo)
                $regionSQL = in_array($tablaActual, $tablasTienenRegion) ? ', region = :region' : '';
                $zonaSQL   = in_array($tablaActual, $tablasTienenZona)   ? ', zona = :zona'     : '';

                $sql = "UPDATE `$tablaActual` SET 
                            nombres = :nombres, ap_paterno = :ap_paterno, ap_materno = :ap_materno, nombre = :nombre_completo,
                            telefono = :telefono, telefono_alterno = :telefono_alterno, email = :email, estado = :estado, 
                            colonia = :colonia, calle = :calle, seccion_electoral = :seccion, estatus = :estatus
                            $regionSQL $zonaSQL $passwordSQL 
                        WHERE id = :id";

                $params = [
                    ':nombres' => $nombres, ':ap_paterno' => $apPaterno, ':ap_materno' => $apMaterno, 
                    ':nombre_completo' => $nombreCompleto, ':telefono' => $telefono, ':telefono_alterno' => $telefonoAlterno, 
                    ':email' => $email, ':estado' => $estado, ':colonia' => $colonia, ':calle' => $calle, 
                    ':seccion' => $seccion, ':estatus' => $estatus_enum, ':id' => $userId
                ];

                if (in_array($tablaActual, $tablasTienenRegion)) $params[':region'] = $region_id;
                if (in_array($tablaActual, $tablasTienenZona))   $params[':zona']   = $zona_id;

                $params = array_merge($params, $passwordParams);
                $db->prepare($sql)->execute($params);
            }

            echo json_encode(['success' => true, 'message' => 'Usuario actualizado exitosamente']);
            break;

        case 'delete':
            $userId = $_POST['id'] ?? $_POST['userId'] ?? '';
            $tabla = $_POST['table'] ?? $_POST['userTable'] ?? $_POST['rol'] ?? '';
            
            // Forzamos nombre de tabla si el frontend envía el nombre del rol general
            if (in_array($tabla, $roles_usuarios_generales)) {
                $tabla = 'usuarios';
            }

            if ($tabla !== 'usuarios' && !in_array($tabla, $tablas_coordinadores)) {
                throw new Exception("Tabla inválida ($tabla)");
            }

            // CANDADO DE PROPIEDAD: no basta con la jerarquía, el registro
            // debe pertenecer al estado/región/zona de quien lo elimina.
            if (!validarPropiedadRegistro($db, $tabla, $userId, $es_admin, $rol_sesion, $mis_estados_permitidos, $mi_region_id_scope, $mi_zona_id_scope)) {
                throw new Exception("Violación de Seguridad: ese registro no pertenece a tu área de cobertura.");
            }
            
            $stmt = $db->prepare("DELETE FROM `$tabla` WHERE id = :id");
            $result = $stmt->execute([':id' => $userId]);
            echo json_encode(['success' => $result]);
            break;

        case 'get':
            $userId = $_GET['id'] ?? '';
            $tabla = $_GET['table'] ?? '';
            
            if (in_array($tabla, $roles_usuarios_generales)) {
                $tabla = 'usuarios';
            }

            // CANDADO DE PROPIEDAD: antes esta acción no tenía NINGUNA
            // restricción (ni de jerarquía ni de estado/región), por lo que
            // cualquier usuario logueado podía leer la ficha completa de
            // cualquier otro registro solo conociendo su id y tabla.
            if (!validarPropiedadRegistro($db, $tabla, $userId, $es_admin, $rol_sesion, $mis_estados_permitidos, $mi_region_id_scope, $mi_zona_id_scope)) {
                throw new Exception("Violación de Seguridad: ese registro no pertenece a tu área de cobertura.");
            }
            
            $espejoSelect = in_array($tabla, $tablasConEspejo) ? 'es_espejo, espejo_de' : '0 as es_espejo, NULL as espejo_de';

            if ($tabla === 'usuarios') {
                $sql = "SELECT id, nombre_completo, telefono, correo as email, 
                               estado, colonia, direccion as calle, seccion_electoral, secciones,
                               IF(activo=1, 'Activo', 'Inactivo') as estatus, rol as rol_tabla, region, zona,
                               0 as es_espejo, NULL as espejo_de
                        FROM usuarios WHERE id = :id";
            } else if ($tabla === 'coordinadores_circunscripcion') {
                $sql = "SELECT id, nombre_completo, '' as telefono, correo as email, 
                               circunscripcion as estado, IF(activo=1, 'Activo', 'Inactivo') as estatus, 
                               '$tabla' as rol_tabla, NULL as region, NULL as zona, $espejoSelect
                        FROM `$tabla` WHERE id = :id";
            } else if (in_array($tabla, $tablas_coordinadores)) {
                $sql = "SELECT id, nombres, ap_paterno, ap_materno, nombre as nombre_completo, telefono, telefono_alterno, email, 
                               estado, colonia, calle, seccion_electoral, estatus, region, zona,
                               '$tabla' as rol_tabla, $espejoSelect
                        FROM `$tabla` WHERE id = :id";
            } else {
                throw new Exception("Tabla inválida");
            }
            
            $stmt = $db->prepare($sql);
            $stmt->execute([':id' => $userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if($user) {
                // Rellenamos campos vacíos para evitar que truene el JS del frontend viejo
                if (!isset($user['nombres'])) {
                    $partes = explode(' ', trim($user['nombre_completo']));
                    $user['nombres'] = $partes[0] ?? '';
                    $user['ap_paterno'] = $partes[1] ?? '';
                    $user['ap_materno'] = $partes[2] ?? '';
                }
                if (!isset($user['telefono_alterno'])) $user['telefono_alterno'] = '';

                // Secciones múltiples como arreglo (solo tabla usuarios)
                if (array_key_exists('secciones', $user)) {
                    $sec = json_decode((string)$user['secciones'], true);
                    $user['secciones'] = is_array($sec) ? $sec : [];
                }
                
                // Mantenemos estas claves vacías por si algún script JS viejo las sigue buscando al editar
                $user['capacitaciones'] = '';
                $user['disponibilidad'] = '';
                $user['horario'] = '';
                $user['dias_disponibles'] = '';
                $user['movilidad'] = '';
                $user['fecha_alta'] = '';
                $user['fecha_baja'] = '';

                echo json_encode(['success' => true, 'user' => $user]);
            } else {
                throw new Exception("Usuario no encontrado");
            }
            break;

        default:
            throw new Exception("Acción no válida");
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>