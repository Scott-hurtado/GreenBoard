<?php
// ==========================================
// 1. CONFIGURACIÓN Y SEGURIDAD
// ==========================================
require_once __DIR__ . '/../../includes/bootstrap.php';

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) session_start();

$rol = $_SESSION['user_role'] ?? '';
$usuario_id = $_SESSION['user_id'] ?? 0;

// Solo permitimos al coordinador estatal
$permitidos = ['coordinador_estatal'];

if (!in_array($rol, $permitidos)) {
    header('Location: /public/index.php'); 
    exit();
}

$db = Database::getInstance()->getConnection();

// ==========================================
// 2. DETECCIÓN DEL ESTADO (Automática y Blindada)
// ==========================================
$estado_actual = '';
$nombre_usuario = 'Coordinador';

try {
    // Buscamos el estado asignado a este usuario específico
    $stmt = $db->prepare("SELECT nombre, estado FROM coordinador_estatal WHERE id = :uid LIMIT 1");
    $stmt->execute([':uid' => $usuario_id]);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($resultado) {
        $estado_actual = $resultado['estado'];
        $nombre_usuario = $resultado['nombre'];
    } else {
        // Si es coordinador pero no está en la tabla, error o estado por defecto
        die("Error: Usuario no asignado a un estado.");
    }
} catch (PDOException $e) {
    error_log("Error DB Usuario: " . $e->getMessage());
}

// ==========================================
// 3. PROCESAMIENTO AJAX (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];
    
    try {
        $accion = $_POST['accion'] ?? '';
        
        // --- CREAR ---
        if ($accion === 'crear') {
            $tipo = $_POST['tipo'] ?? '';
            $titulo = $_POST['titulo'] ?? ''; 
            $descripcion = $_POST['descripcion'] ?? '';
            
            // Para Metas, usamos la descripción como titulo si viene vacio
            if ($tipo === 'meta' && empty($titulo)) $titulo = $descripcion; 
            
            $tabla = '';
            if ($tipo === 'objetivo') $tabla = 'geo_objetivos';
            elseif ($tipo === 'estrategia') $tabla = 'geo_estrategias';
            elseif ($tipo === 'meta') $tabla = 'geo_metas';
            elseif ($tipo === 'cronograma') $tabla = 'geo_cronograma';
            
            if ($tabla) {
                $extra_cols = ""; $extra_vals = "";
                $params = [':edo' => $estado_actual, ':tit' => $titulo, ':desc' => $descripcion];
                
                if ($tipo === 'cronograma') {
                    $extra_cols = ", fecha_visual, fecha_orden";
                    $extra_vals = ", :fvis, :ford";
                    $params[':fvis'] = $_POST['fecha_visual'] ?? '';
                    $params[':ford'] = $_POST['fecha_orden'] ?? date('Y-m-d');
                }
                
                $sql = "INSERT INTO $tabla (estado, titulo, descripcion, activo $extra_cols) VALUES (:edo, :tit, :desc, 1 $extra_vals)";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $response['success'] = true;
            }
            // Guardar Visión / Misión
            elseif ($tipo === 'vision_mision') {
                $vis = $_POST['vision'];
                $mis = $_POST['mision'];
                $sql = "INSERT INTO geo_vision_mision (estado, vision, mision) VALUES (:edo, :vis, :mis) 
                        ON DUPLICATE KEY UPDATE vision = :vis2, mision = :mis2";
                $stmt = $db->prepare($sql);
                $stmt->execute([':edo'=>$estado_actual, ':vis'=>$vis, ':mis'=>$mis, ':vis2'=>$vis, ':mis2'=>$mis]);
                $response['success'] = true;
            }
        }
        
        // --- EDITAR ---
        elseif ($accion === 'editar') {
            $id = $_POST['id'];
            $tipo = $_POST['tipo'];
            $titulo = $_POST['titulo'] ?? '';
            $descripcion = $_POST['descripcion'] ?? '';
            
            $tabla = '';
            if ($tipo === 'objetivo') $tabla = 'geo_objetivos';
            elseif ($tipo === 'estrategia') $tabla = 'geo_estrategias';
            elseif ($tipo === 'meta') $tabla = 'geo_metas';
            elseif ($tipo === 'cronograma') $tabla = 'geo_cronograma';
            
            if ($tabla) {
                $sql = "UPDATE $tabla SET titulo = :tit, descripcion = :desc";
                $params = [':tit' => $titulo, ':desc' => $descripcion, ':id' => $id, ':edo' => $estado_actual];
                
                if ($tipo === 'cronograma') {
                    $sql .= ", fecha_visual = :fvis, fecha_orden = :ford";
                    $params[':fvis'] = $_POST['fecha_visual'];
                    $params[':ford'] = $_POST['fecha_orden'];
                }
                
                $sql .= " WHERE id = :id AND estado = :edo"; 
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $response['success'] = true;
            }
        }
        
        // --- ELIMINAR ---
        elseif ($accion === 'eliminar') {
            $id = $_POST['id'];
            $tipo = $_POST['tipo'];
            
            $tabla = '';
            if ($tipo === 'objetivo') $tabla = 'geo_objetivos';
            elseif ($tipo === 'estrategia') $tabla = 'geo_metas';
            elseif ($tipo === 'meta') $tabla = 'geo_metas';
            elseif ($tipo === 'cronograma') $tabla = 'geo_cronograma';
            
            if ($tabla) {
                $stmt = $db->prepare("DELETE FROM $tabla WHERE id = :id AND estado = :edo");
                $stmt->execute([':id' => $id, ':edo' => $estado_actual]);
                $response['success'] = true;
            }
        }

        // --- GUARDAR DIAGNÓSTICO INICIAL ---
        elseif ($accion === 'guardar_diagnostico') {
            $db->beginTransaction(); 
            try {
                // Función auxiliar para limpiar números con formato (Quita comas, $, letras)
                $cleanNum = function($val) {
                    $val = str_replace([',', '$', ' '], '', $val ?? '');
                    return is_numeric($val) ? $val : 0;
                };

                // 1. Guardar en geo_diagnostico
                $sqlDiag = "INSERT INTO geo_diagnostico (
                    estado, responsable, cargo, tel, mail, contacto_otro,
                    mun_totales, mun_presencia, afil_registrados, afil_activos, promo_registrados, promo_confirmados, presupuesto,
                    ofi_municipal, ofi_regional, ofi_estatal,
                    link_facebook, seg_facebook, link_x, seg_x, link_instagram, seg_instagram, link_otro, seg_otro,
                    foda_fortalezas, foda_debilidades, foda_oportunidades, foda_amenazas,
                    problema_1_titulo, problema_1_desc, kpi_1_1, kpi_1_2, kpi_1_3,
                    problema_2_titulo, problema_2_desc, kpi_2_1, kpi_2_2, kpi_2_3,
                    problema_3_titulo, problema_3_desc, kpi_3_1, kpi_3_2, kpi_3_3, 
                    links_adjuntos
                ) VALUES (
                    :estado, :responsable, :cargo, :tel, :mail, :contacto_otro,
                    :mun_totales, :mun_presencia, :afil_registrados, :afil_activos, :promo_registrados, :promo_confirmados, :presupuesto,
                    :ofi_municipal, :ofi_regional, :ofi_estatal,
                    :link_facebook, :seg_facebook, :link_x, :seg_x, :link_instagram, :seg_instagram, :link_otro, :seg_otro,
                    :foda_fortalezas, :foda_debilidades, :foda_oportunidades, :foda_amenazas,
                    :p1_tit, :p1_desc, :k1_1, :k1_2, :k1_3,
                    :p2_tit, :p2_desc, :k2_1, :k2_2, :k2_3,
                    :p3_tit, :p3_desc, :k3_1, :k3_2, :k3_3, 
                    :links_adjuntos
                ) ON DUPLICATE KEY UPDATE 
                    responsable=VALUES(responsable), cargo=VALUES(cargo), tel=VALUES(tel), mail=VALUES(mail), contacto_otro=VALUES(contacto_otro),
                    mun_totales=VALUES(mun_totales), mun_presencia=VALUES(mun_presencia), afil_registrados=VALUES(afil_registrados), 
                    afil_activos=VALUES(afil_activos), promo_registrados=VALUES(promo_registrados), promo_confirmados=VALUES(promo_confirmados), 
                    presupuesto=VALUES(presupuesto), ofi_municipal=VALUES(ofi_municipal), ofi_regional=VALUES(ofi_regional), ofi_estatal=VALUES(ofi_estatal),
                    link_facebook=VALUES(link_facebook), seg_facebook=VALUES(seg_facebook), link_x=VALUES(link_x), seg_x=VALUES(seg_x), 
                    link_instagram=VALUES(link_instagram), seg_instagram=VALUES(seg_instagram), link_otro=VALUES(link_otro), seg_otro=VALUES(seg_otro),
                    foda_fortalezas=VALUES(foda_fortalezas), foda_debilidades=VALUES(foda_debilidades), foda_oportunidades=VALUES(foda_oportunidades), foda_amenazas=VALUES(foda_amenazas),
                    problema_1_titulo=VALUES(problema_1_titulo), problema_1_desc=VALUES(problema_1_desc), kpi_1_1=VALUES(kpi_1_1), kpi_1_2=VALUES(kpi_1_2), kpi_1_3=VALUES(kpi_1_3),
                    problema_2_titulo=VALUES(problema_2_titulo), problema_2_desc=VALUES(problema_2_desc), kpi_2_1=VALUES(kpi_2_1), kpi_2_2=VALUES(kpi_2_2), kpi_2_3=VALUES(kpi_2_3),
                    problema_3_titulo=VALUES(problema_3_titulo), problema_3_desc=VALUES(problema_3_desc), kpi_3_1=VALUES(kpi_3_1), kpi_3_2=VALUES(kpi_3_2), kpi_3_3=VALUES(kpi_3_3), 
                    links_adjuntos=VALUES(links_adjuntos)";
                
                $stmtDiag = $db->prepare($sqlDiag);
                $stmtDiag->execute([
                    ':estado' => $estado_actual,
                    ':responsable' => $_POST['responsable'] ?? null,
                    ':cargo' => $_POST['cargo'] ?? null,
                    ':tel' => $_POST['tel'] ?? null,
                    ':mail' => $_POST['mail'] ?? null,
                    ':contacto_otro' => $_POST['contacto_otro'] ?? null,
                    ':mun_totales' => $cleanNum($_POST['mun_totales']),
                    ':mun_presencia' => $cleanNum($_POST['mun_presencia']),
                    ':afil_registrados' => $cleanNum($_POST['afil_registrados']),
                    ':afil_activos' => $cleanNum($_POST['afil_activos']),
                    ':promo_registrados' => $cleanNum($_POST['promo_registrados']),
                    ':promo_confirmados' => $cleanNum($_POST['promo_confirmados']),
                    ':presupuesto' => $cleanNum($_POST['presupuesto']),
                    ':ofi_municipal' => $cleanNum($_POST['ofi_municipal']),
                    ':ofi_regional' => $cleanNum($_POST['ofi_regional']),
                    ':ofi_estatal' => $cleanNum($_POST['ofi_estatal']),
                    ':link_facebook' => $_POST['link_facebook'] ?? null,
                    ':seg_facebook' => $cleanNum($_POST['seg_facebook']),
                    ':link_x' => $_POST['link_x'] ?? null,
                    ':seg_x' => $cleanNum($_POST['seg_x']),
                    ':link_instagram' => $_POST['link_instagram'] ?? null,
                    ':seg_instagram' => $cleanNum($_POST['seg_instagram']),
                    ':link_otro' => $_POST['link_otro'] ?? null,
                    ':seg_otro' => $cleanNum($_POST['seg_otro']),
                    ':foda_fortalezas' => $_POST['foda_fortalezas'] ?? null,
                    ':foda_debilidades' => $_POST['foda_debilidades'] ?? null,
                    ':foda_oportunidades' => $_POST['foda_oportunidades'] ?? null,
                    ':foda_amenazas' => $_POST['foda_amenazas'] ?? null,
                    ':p1_tit' => $_POST['problema_1_titulo'] ?? null,
                    ':p1_desc' => $_POST['problema_1_desc'] ?? null,
                    ':k1_1' => $_POST['kpi_1_1'] ?? null,
                    ':k1_2' => $_POST['kpi_1_2'] ?? null,
                    ':k1_3' => $_POST['kpi_1_3'] ?? null,
                    ':p2_tit' => $_POST['problema_2_titulo'] ?? null,
                    ':p2_desc' => $_POST['problema_2_desc'] ?? null,
                    ':k2_1' => $_POST['kpi_2_1'] ?? null,
                    ':k2_2' => $_POST['kpi_2_2'] ?? null,
                    ':k2_3' => $_POST['kpi_2_3'] ?? null,
                    ':p3_tit' => $_POST['problema_3_titulo'] ?? null,
                    ':p3_desc' => $_POST['problema_3_desc'] ?? null,
                    ':k3_1' => $_POST['kpi_3_1'] ?? null,
                    ':k3_2' => $_POST['kpi_3_2'] ?? null,
                    ':k3_3' => $_POST['kpi_3_3'] ?? null, 
                    ':links_adjuntos' => $_POST['links_adjuntos'] ?? null
                ]);

                // 2. Guardar en geo_diag_electoral (con limpieza de miles)
                $anios = [2012, 2015, 2018, 2021, 2024];
                $sqlElec = "INSERT INTO geo_diag_electoral (
                    estado, anio, lista_nominal, participacion, votos_est, porc_est, votos_fed, porc_fed,
                    encuesta_fuente, encuesta_fecha, encuesta_intencion, encuesta_link
                ) VALUES (
                    :estado, :anio, :lista_nominal, :participacion, :votos_est, :porc_est, :votos_fed, :porc_fed,
                    :encuesta_fuente, :encuesta_fecha, :encuesta_intencion, :encuesta_link
                ) ON DUPLICATE KEY UPDATE
                    lista_nominal=VALUES(lista_nominal), participacion=VALUES(participacion), 
                    votos_est=VALUES(votos_est), porc_est=VALUES(porc_est), votos_fed=VALUES(votos_fed), porc_fed=VALUES(porc_fed),
                    encuesta_fuente=VALUES(encuesta_fuente), encuesta_fecha=VALUES(encuesta_fecha), 
                    encuesta_intencion=VALUES(encuesta_intencion), encuesta_link=VALUES(encuesta_link)";
                
                $stmtElec = $db->prepare($sqlElec);

                foreach ($anios as $anio) {
                    $stmtElec->execute([
                        ':estado' => $estado_actual,
                        ':anio' => $anio,
                        ':lista_nominal' => $cleanNum($_POST["lista_nominal_$anio"]),
                        ':participacion' => $_POST["participacion_$anio"] ?: 0.00,
                        ':votos_est' => $cleanNum($_POST["votos_est_$anio"]),
                        ':porc_est' => $_POST["porc_est_$anio"] ?: 0.00,
                        ':votos_fed' => $cleanNum($_POST["votos_fed_$anio"]),
                        ':porc_fed' => $_POST["porc_fed_$anio"] ?: 0.00,
                        ':encuesta_fuente' => $_POST["encuesta_fuente_$anio"] ?? null,
                        ':encuesta_fecha' => !empty($_POST["encuesta_fecha_$anio"]) ? $_POST["encuesta_fecha_$anio"] : null,
                        ':encuesta_intencion' => $_POST["encuesta_intencion_$anio"] ?: 0.00,
                        ':encuesta_link' => $_POST["encuesta_link_$anio"] ?? null
                    ]);
                }

                // 3. Manejar subida de archivos (geo_diag_archivos)
                if (isset($_FILES['documentos']) && !empty($_FILES['documentos']['name'][0])) {
                    $uploadDir = __DIR__ . '/../../uploads/diagnostico/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

                    $sqlArchivo = "INSERT INTO geo_diag_archivos (estado, nombre_original, nombre_servidor, ruta_archivo) 
                                   VALUES (:estado, :original, :servidor, :ruta)";
                    $stmtArchivo = $db->prepare($sqlArchivo);

                    foreach ($_FILES['documentos']['name'] as $key => $name) {
                        if ($_FILES['documentos']['error'][$key] === UPLOAD_ERR_OK) {
                            $tmpName = $_FILES['documentos']['tmp_name'][$key];
                            $ext = pathinfo($name, PATHINFO_EXTENSION);
                            $servidorNombre = uniqid('diag_' . $estado_actual . '_') . '.' . $ext;
                            $rutaDestino = $uploadDir . $servidorNombre;
                            $rutaDB = '/uploads/diagnostico/' . $servidorNombre;

                            if (move_uploaded_file($tmpName, $rutaDestino)) {
                                $stmtArchivo->execute([
                                    ':estado' => $estado_actual,
                                    ':original' => $name,
                                    ':servidor' => $servidorNombre,
                                    ':ruta' => $rutaDB
                                ]);
                            }
                        }
                    }
                }

                $db->commit();
                $response['success'] = true;
                $response['message'] = 'Diagnóstico guardado correctamente.';

            } catch (Exception $ex) {
                $db->rollBack();
                $response['message'] = "Error DB: " . $ex->getMessage();
            }
        }
        
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
    
    echo json_encode($response);
    exit;
}

// ==========================================
// 4. CONSULTAS DE DATOS (READ)
// ==========================================
$data = [
    'vision_mision' => ['vision' => '', 'mision' => ''], 
    'objetivos' => [], 
    'estrategias' => [], 
    'metas' => [], 
    'cronograma' => [],
    'diagnostico' => null,     
    'diag_electoral' => [],
    'archivos' => []
];

try {
    // Visión/Misión
    $stmt = $db->prepare("SELECT vision, mision FROM geo_vision_mision WHERE estado = ? LIMIT 1");
    $stmt->execute([$estado_actual]);
    $vm = $stmt->fetch(PDO::FETCH_ASSOC);
    if($vm) $data['vision_mision'] = $vm;
    else $data['vision_mision'] = ['vision' => "Sin definir.", 'mision' => "Sin definir."];

    // Objetivos
    $stmt = $db->prepare("SELECT * FROM geo_objetivos WHERE estado = ? AND activo = 1 ORDER BY id DESC");
    $stmt->execute([$estado_actual]);
    $data['objetivos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Estrategias
    $stmt = $db->prepare("SELECT * FROM geo_estrategias WHERE estado = ? AND activo = 1 ORDER BY id DESC");
    $stmt->execute([$estado_actual]);
    $data['estrategias'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Metas
    $stmt = $db->prepare("SELECT * FROM geo_metas WHERE estado = ? AND activo = 1 ORDER BY id ASC");
    $stmt->execute([$estado_actual]);
    $data['metas'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Cronograma
    $stmt = $db->prepare("SELECT * FROM geo_cronograma WHERE estado = ? AND activo = 1 ORDER BY fecha_orden ASC");
    $stmt->execute([$estado_actual]);
    $data['cronograma'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- CARGAR DIAGNÓSTICO ---
    $stmt = $db->prepare("SELECT * FROM geo_diagnostico WHERE estado = ? LIMIT 1");
    $stmt->execute([$estado_actual]);
    $data['diagnostico'] = $stmt->fetch(PDO::FETCH_ASSOC);

    // --- CARGAR DATOS ELECTORALES ---
    $stmt = $db->prepare("SELECT * FROM geo_diag_electoral WHERE estado = ? ORDER BY anio ASC");
    $stmt->execute([$estado_actual]);
    $electoral = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach($electoral as $row) {
        $data['diag_electoral'][$row['anio']] = $row;
    }

    // --- CARGAR ARCHIVOS ADJUNTOS ---
    $stmt = $db->prepare("SELECT * FROM geo_diag_archivos WHERE estado = ? ORDER BY fecha_subida DESC");
    $stmt->execute([$estado_actual]);
    $data['archivos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) { $error_msg = "Error DB: " . $e->getMessage(); }

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coordinación Estatal - <?php echo htmlspecialchars($estado_actual); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ESTILOS IDÉNTICOS A LA VISTA ADMIN */
        :root { --primary: #2d7a3e; --primary-dark: #1f5a2d; --dark: #1a1a1a; --light: #f0f7f0; --white: #ffffff; --gray-100: #f8f9fa; --gray-200: #e9ecef; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; background: var(--light); color: var(--dark); overflow-x: hidden; }
        .container { max-width: 100%; margin: 0; background: var(--white); min-height: 100vh; padding-left: 330px; padding-right: 50px; padding-bottom: 40px; transition: padding-left 0.3s ease; }
        
        .page-header { padding: 40px 0 20px 0; }
        .ai-badge { 
            display: inline-block; 
            color: var(--primary); 
            font-size: 13px; 
            font-weight: 800; 
            letter-spacing: 4px; 
            background: rgba(45,122,62,0.1); 
            padding: 5px 15px; 
            border-radius: 20px; 
            text-transform: uppercase; 
            margin-bottom: 10px; 
        }
        .main-title { font-size: 42px; font-weight: 900; margin: 10px 0; color: var(--dark); }
        .subtitle { color: #666; font-size: 16px; margin-bottom: 30px; }

        .coord-card { background: #e8f5e9; border-left: 5px solid var(--primary); padding: 20px; border-radius: 10px; margin-bottom: 30px; display: flex; align-items: center; gap: 20px; }
        .coord-avatar { width: 60px; height: 60px; background: var(--white); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; color: var(--primary); box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .coord-info h4 { margin: 0; font-size: 18px; font-weight: 800; color: var(--dark); }
        .coord-info p { margin: 5px 0 0 0; font-size: 13px; color: #555; }

        .tabs-container { background: var(--gray-100); border-radius: 16px; padding: 5px; display: inline-flex; gap: 5px; margin-bottom: 30px; flex-wrap: wrap; }
        .tab-btn { padding: 10px 20px; border: none; background: transparent; border-radius: 12px; font-weight: 600; color: #666; cursor: pointer; }
        .tab-btn.active { background: var(--white); color: var(--primary); box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .tab-content { display: none; }
        .tab-content.active { display: block; animation: fadeIn 0.5s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        .content-card { background: var(--white); border-radius: 16px; padding: 25px; border: 1px solid var(--gray-200); margin-bottom: 20px; position: relative; box-shadow: 0 5px 15px rgba(0,0,0,0.05); transition: 0.3s; }
        .content-card:hover { transform: translateY(-3px); border-color: var(--primary); }
        .card-tag { position: absolute; top: 20px; left: 20px; font-size: 10px; font-weight: 800; text-transform: uppercase; color: var(--white); background: var(--primary); padding: 4px 10px; border-radius: 20px; }
        .content-card h4 { margin-top: 25px; }

        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        @media (max-width: 1024px) { .grid-3 { grid-template-columns: 1fr; } }
        
        .btn-admin-add { background: var(--dark); color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 700; cursor: pointer; float: right; font-size: 12px; display: flex; align-items: center; gap: 8px; }
        
        .card-menu { position: absolute; top: 20px; right: 20px; z-index: 10; }
        .menu-btn { background: none; border: none; font-size: 18px; color: #999; cursor: pointer; padding: 5px 10px; border-radius: 50%; }
        .menu-btn:hover { background: #f0f0f0; color: var(--dark); }
        .menu-content { display: none; position: absolute; right: 0; top: 30px; background: white; border: 1px solid #ddd; box-shadow: 0 5px 15px rgba(0,0,0,0.1); border-radius: 8px; width: 140px; z-index: 20; }
        .menu-content a { display: block; padding: 10px 15px; color: #333; text-decoration: none; font-size: 13px; transition: 0.2s; cursor: pointer; }
        .menu-content a:hover { background: var(--gray-100); color: var(--primary); }
        .menu-content a.delete { color: #d32f2f; }
        .menu-content.show { display: block; }

        .table-actions-container { background: #fff; border-radius: 12px; border: 1px solid var(--gray-200); overflow: visible; margin-top: 15px; min-height: 200px; }
        .custom-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .custom-table th { background: #f4f6f8; color: #555; font-weight: 700; text-align: left; padding: 15px; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; }
        .custom-table td { padding: 15px; border-bottom: 1px solid #eee; vertical-align: top; color: #333; }
        .custom-table tr:hover { background: #fafafa; }
        .date-badge { background: #e3f2fd; color: #1565c0; padding: 4px 8px; border-radius: 4px; font-weight: 600; font-size: 11px; display: inline-block; white-space: nowrap; }

        .kpi-block { background: #f8f9fa; padding: 20px; border-radius: 12px; border-left: 4px solid var(--primary); margin-bottom: 20px; }
        .kpi-block-title { font-size: 14px; font-weight: 800; color: var(--dark); margin-bottom: 10px; }

        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 3000; align-items: center; justify-content: center; }
        .modal-content { background: white; width: 500px; padding: 30px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); max-height: 90vh; overflow-y: auto; }
        .close-modal { position: absolute; top: 20px; right: 20px; font-size: 20px; cursor: pointer; color: #999; }

        /* NUEVAS CLASES PARA EL CONTADOR DE CARACTERES */
        .char-counter {
            font-size: 10px;
            color: #888;
            text-align: right;
            margin-top: 4px;
            display: block;
            line-height: 1;
            transition: color 0.3s;
        }
        .char-counter.limit-reached {
            color: #d32f2f;
            font-weight: bold;
        }

        @media (max-width: 1024px) { .container { padding-left: 20px; padding-right: 20px; } .grid-2 { grid-template-columns: 1fr; } .table-actions-container { overflow-x: auto; } }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/components/sidebar_ce.php'; ?>

    <script>
        const currentVision = <?php echo json_encode($data['vision_mision']['vision']); ?>;
        const currentMision = <?php echo json_encode($data['vision_mision']['mision']); ?>;
    </script>

    <div class="container">
        
        <div class="page-header">
            <div class="ai-badge"><?php echo strtoupper($estado_actual); ?></div>
            <h1 class="main-title">Mi Gestión Estatal</h1>
            <p class="subtitle">Estructura operativa para <strong><?php echo htmlspecialchars($estado_actual); ?></strong>.</p>
        </div>

        <?php if(isset($error_msg)): ?>
            <div style="background: #ffebee; color: #c62828; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error_msg; ?>
            </div>
        <?php endif; ?>

        <div class="coord-card">
            <div class="coord-avatar"><i class="fas fa-user-circle"></i></div>
            <div class="coord-info">
                <h4><?php echo htmlspecialchars($nombre_usuario); ?></h4>
                <p>Coordinador Estatal - <strong><?php echo $estado_actual; ?></strong></p>
            </div>
            <button class="btn-admin-add" style="margin-left: auto;" onclick="openModal()">
                <i class="fas fa-plus"></i> Gestión Operativa
            </button>
        </div>

        <div class="tabs-container">
            <button class="tab-btn" data-tab="vision" onclick="showTab('vision')">Visión & Misión</button>
            <button class="tab-btn" data-tab="objetivos" onclick="showTab('objetivos')">Objetivos</button>
            <button class="tab-btn" data-tab="metas" onclick="showTab('metas')">Metas</button>
            <button class="tab-btn" data-tab="estrategias" onclick="showTab('estrategias')">Estrategias</button>
            <button class="tab-btn" data-tab="cronograma" onclick="showTab('cronograma')">Acciones</button>
            <button class="tab-btn" data-tab="diagnostico" onclick="showTab('diagnostico')">Diagnóstico Inicial</button>
        </div>

        <div id="tab-vision" class="tab-content">
            <div class="grid-2">
                <div class="content-card">
                    <h3 style="margin-bottom: 15px; color: var(--dark); font-size: 18px;"><i class="fas fa-eye"></i> Visión</h3>
                    <p style="color: #666; font-size: 15px; line-height: 1.6; white-space: pre-line;">
                        <?php echo nl2br(htmlspecialchars($data['vision_mision']['vision'])); ?>
                    </p>
                </div>
                <div class="content-card">
                    <h3 style="margin-bottom: 15px; color: var(--dark); font-size: 18px;"><i class="fas fa-bullseye"></i> Misión</h3>
                    <p style="color: #666; font-size: 15px; line-height: 1.6; white-space: pre-line;">
                        <?php echo nl2br(htmlspecialchars($data['vision_mision']['mision'])); ?>
                    </p>
                </div>
            </div>
        </div>

        <div id="tab-objetivos" class="tab-content">
            <h3 style="margin-bottom: 15px; color: var(--dark); font-size: 18px;"><i class="fas fa-flag"></i> Objetivos del Estado</h3>
            <?php if (empty($data['objetivos'])): ?>
                <div class="content-card" style="text-align: center; color: #999;">No hay objetivos registrados.</div>
            <?php else: ?>
                <div class="grid-2">
                    <?php foreach ($data['objetivos'] as $obj): ?>
                        <div class="content-card">
                            <div class="card-menu">
                                <button class="menu-btn" onclick="toggleMenu(event, 'menu-obj-<?php echo $obj['id']; ?>')"><i class="fas fa-ellipsis-v"></i></button>
                                <div class="menu-content" id="menu-obj-<?php echo $obj['id']; ?>">
                                    <a onclick="openEditModal(<?php echo $obj['id']; ?>, 'objetivo', <?php echo htmlspecialchars(json_encode($obj['titulo']), ENT_QUOTES); ?>, <?php echo htmlspecialchars(json_encode($obj['descripcion']), ENT_QUOTES); ?>)"><i class="fas fa-edit"></i> Modificar</a>
                                    <a class="delete" onclick="deleteItem(<?php echo $obj['id']; ?>, 'objetivo')"><i class="fas fa-trash-alt"></i> Eliminar</a>
                                </div>
                            </div>
                            <span class="card-tag">Objetivo</span>
                            <h4 style="font-weight: 800; margin-bottom: 5px; padding-right: 30px;"><?php echo htmlspecialchars($obj['titulo']); ?></h4>
                            <p style="font-size: 14px; color: #666;"><?php echo htmlspecialchars($obj['descripcion']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div id="tab-metas" class="tab-content">
            <h3 style="margin-bottom: 15px; color: var(--dark); font-size: 18px;"><i class="fas fa-crosshairs"></i> Metas Estatales</h3>
            <?php if (empty($data['metas'])): ?>
                <div class="content-card" style="text-align: center; color: #999;">No hay metas visibles.</div>
            <?php else: ?>
                <div class="grid-3">
                    <?php $contador = 1; foreach ($data['metas'] as $meta): ?>
                        <div class="content-card" style="border-left: 4px solid var(--primary); padding: 15px 20px; min-height: 80px; display: flex; align-items: center;">
                            <div class="card-menu" style="top: 10px; right: 10px;">
                                <button class="menu-btn" onclick="toggleMenu(event, 'menu-meta-<?php echo $meta['id']; ?>')"><i class="fas fa-ellipsis-v"></i></button>
                                <div class="menu-content" id="menu-meta-<?php echo $meta['id']; ?>">
                                    <a onclick='openEditModal(<?php echo $meta['id']; ?>, "meta", "Meta #<?php echo $contador; ?>", <?php echo json_encode($meta['titulo']); ?>)'>
                                        <i class="fas fa-edit"></i> Modificar
                                    </a>
                                    <a class="delete" onclick="deleteItem(<?php echo $meta['id']; ?>, 'meta')"><i class="fas fa-trash-alt"></i> Eliminar</a>
                                </div>
                            </div>
                            <p style="font-weight: 600; font-size: 14px; margin: 0; color: #1a1a1a; line-height: 1.4; padding-right: 25px; width: 100%;"><?php echo nl2br(htmlspecialchars($meta['titulo'])); ?></p>
                        </div>
                    <?php $contador++; endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div id="tab-estrategias" class="tab-content">
            <h3 style="margin-bottom: 15px; color: var(--dark); font-size: 18px;"><i class="fas fa-chess"></i> Estrategias Locales</h3>
            <?php if (empty($data['estrategias'])): ?>
                <div class="content-card" style="text-align: center; color: #999;">No hay estrategias registradas.</div>
            <?php else: ?>
                <div class="grid-2">
                    <?php foreach ($data['estrategias'] as $est): ?>
                        <div class="content-card" style="border-left: 4px solid var(--primary);">
                            <div class="card-menu">
                                <button class="menu-btn" onclick="toggleMenu(event, 'menu-est-<?php echo $est['id']; ?>')"><i class="fas fa-ellipsis-v"></i></button>
                                <div class="menu-content" id="menu-est-<?php echo $est['id']; ?>">
                                    <a onclick="openEditModal(<?php echo $est['id']; ?>, 'estrategia', <?php echo htmlspecialchars(json_encode($est['titulo']), ENT_QUOTES); ?>, <?php echo htmlspecialchars(json_encode($est['descripcion']), ENT_QUOTES); ?>)"><i class="fas fa-edit"></i> Modificar</a>
                                    <a class="delete" onclick="deleteItem(<?php echo $est['id']; ?>, 'estrategia')"><i class="fas fa-trash-alt"></i> Eliminar</a>
                                </div>
                            </div>
                            <h4 style="font-weight: 800; margin-bottom: 5px; padding-right: 30px;"><?php echo htmlspecialchars($est['titulo']); ?></h4>
                            <p style="font-size: 14px; color: #666;"><?php echo htmlspecialchars($est['descripcion']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div id="tab-cronograma" class="tab-content">
            <h3 style="margin:0; color: var(--dark); font-size: 18px;"><i class="fas fa-calendar-alt"></i> Agenda de Acciones</h3>
            <?php if (empty($data['cronograma'])): ?>
                <div class="content-card" style="text-align: center; color: #999; margin-top: 15px;">No hay acciones registradas.</div>
            <?php else: ?>
                <div class="table-actions-container">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th style="width: 20%;">Fecha (Visual)</th>
                                <th style="width: 25%;">Acción</th>
                                <th style="width: 45%;">Detalles</th>
                                <th style="width: 10%;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data['cronograma'] as $crono): ?>
                                <tr>
                                    <td><span class="date-badge"><?php echo htmlspecialchars($crono['fecha_visual'] ?? 'Sin fecha'); ?></span></td>
                                    <td><div style="font-weight: 700; color: #333;"><?php echo htmlspecialchars($crono['titulo']); ?></div></td>
                                    <td><div style="font-size: 13px; color: #666;"><?php echo htmlspecialchars($crono['descripcion']); ?></div></td>
                                    <td style="text-align: center; position: relative;">
                                        <button class="menu-btn" onclick="toggleMenu(event, 'menu-crono-<?php echo $crono['id']; ?>')"><i class="fas fa-ellipsis-h"></i></button>
                                        <div class="menu-content" id="menu-crono-<?php echo $crono['id']; ?>" style="right: 100%; top: 0; margin-right: 10px;">
                                            <a onclick="openEditModal(<?php echo $crono['id']; ?>, 'cronograma', <?php echo htmlspecialchars(json_encode($crono['titulo']), ENT_QUOTES); ?>, <?php echo htmlspecialchars(json_encode($crono['descripcion']), ENT_QUOTES); ?>, '', '', '', '', <?php echo htmlspecialchars(json_encode($crono['fecha_visual'] ?? ''), ENT_QUOTES); ?>, <?php echo htmlspecialchars(json_encode($crono['fecha_orden'] ?? ''), ENT_QUOTES); ?>)"><i class="fas fa-edit"></i> Editar</a>
                                            <a class="delete" onclick="deleteItem(<?php echo $crono['id']; ?>, 'cronograma')"><i class="fas fa-trash-alt"></i> Eliminar</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div id="tab-diagnostico" class="tab-content">
            <h3 style="margin-bottom: 15px; color: var(--dark); font-size: 18px;"><i class="fas fa-file-signature"></i> Diagnóstico Inicial</h3>
            
            <form id="formDiagnostico" onsubmit="guardarDiagnostico(event)">
                
                <div class="content-card">
                    <h4 style="margin-top: 0; margin-bottom: 15px; color: var(--primary);">Ficha Identificativa</h4>
                    <div class="grid-3">
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: bold; margin-bottom: 5px;">Entidad</label>
                            <input type="text" name="entidad" value="<?php echo htmlspecialchars($estado_actual); ?>" readonly maxlength="50" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; background: #eee;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: bold; margin-bottom: 5px;">Responsable de la ficha</label>
                            <input type="text" name="responsable" value="<?php echo htmlspecialchars($data['diagnostico']['responsable'] ?? $nombre_usuario); ?>" maxlength="100" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: bold; margin-bottom: 5px;">Cargo</label>
                            <input type="text" name="cargo" value="<?php echo htmlspecialchars($data['diagnostico']['cargo'] ?? ''); ?>" maxlength="100" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
                        </div>
                    </div>
                    <h5 style="margin-top: 15px; margin-bottom: 10px; font-size: 14px;">Contacto</h5>
                    <div class="grid-3">
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: bold; margin-bottom: 5px;">Teléfono</label>
                            <input type="text" name="tel" value="<?php echo htmlspecialchars($data['diagnostico']['tel'] ?? ''); ?>" maxlength="10" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: bold; margin-bottom: 5px;">Mail</label>
                            <input type="email" name="mail" value="<?php echo htmlspecialchars($data['diagnostico']['mail'] ?? ''); ?>" maxlength="100" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: bold; margin-bottom: 5px;">Otro</label>
                            <input type="text" name="contacto_otro" value="<?php echo htmlspecialchars($data['diagnostico']['contacto_otro'] ?? ''); ?>" maxlength="100" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
                        </div>
                    </div>
                </div>

                <div class="content-card">
                    <h4 style="margin-top: 0; margin-bottom: 15px; color: var(--primary);">Datos Electorales (Resumen)</h4>
                    
                    <div class="grid-2">
                        <div class="table-actions-container" style="min-height: auto;">
                            <table class="custom-table">
                                <thead>
                                    <tr>
                                        <th>Año</th>
                                        <th>Lista Nominal</th>
                                        <th>Participación (%)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach([2012, 2015, 2018, 2021, 2024] as $anio): 
                                        $e = $data['diag_electoral'][$anio] ?? null; ?>
                                    <tr>
                                        <td><strong><?php echo $anio; ?></strong></td>
                                        <td><input type="text" class="number-format" name="lista_nominal_<?php echo $anio; ?>" value="<?php echo htmlspecialchars($e['lista_nominal'] ?? ''); ?>" maxlength="20" style="width: 100%; padding: 5px; border: 1px solid #ddd; border-radius: 4px;"></td>
                                        <td><input type="number" step="0.01" max="100" name="participacion_<?php echo $anio; ?>" value="<?php echo htmlspecialchars($e['participacion'] ?? ''); ?>" style="width: 100%; padding: 5px; border: 1px solid #ddd; border-radius: 4px;"></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="table-actions-container" style="min-height: auto; overflow-x: auto;">
                            <table class="custom-table" style="min-width: 400px;">
                                <thead>
                                    <tr>
                                        <th rowspan="2" style="vertical-align: middle;">Año</th>
                                        <th colspan="2" style="text-align: center; border-left: 1px solid #ddd;">ESTATAL</th>
                                        <th colspan="2" style="text-align: center; border-left: 1px solid #ddd;">FEDERAL</th>
                                    </tr>
                                    <tr>
                                        <th style="border-left: 1px solid #ddd;">Votos</th>
                                        <th>%</th>
                                        <th style="border-left: 1px solid #ddd;">Votos</th>
                                        <th>%</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach([2012, 2015, 2018, 2021, 2024] as $anio): 
                                        $e = $data['diag_electoral'][$anio] ?? null; ?>
                                    <tr>
                                        <td><strong><?php echo $anio; ?></strong></td>
                                        <td style="border-left: 1px solid #eee;"><input type="text" class="number-format" name="votos_est_<?php echo $anio; ?>" value="<?php echo htmlspecialchars($e['votos_est'] ?? ''); ?>" maxlength="20" style="width: 100%; padding: 5px; border: 1px solid #ddd; border-radius: 4px;"></td>
                                        <td><input type="number" step="0.01" max="100" name="porc_est_<?php echo $anio; ?>" value="<?php echo htmlspecialchars($e['porc_est'] ?? ''); ?>" style="width: 100%; padding: 5px; border: 1px solid #ddd; border-radius: 4px;"></td>
                                        <td style="border-left: 1px solid #eee;"><input type="text" class="number-format" name="votos_fed_<?php echo $anio; ?>" value="<?php echo htmlspecialchars($e['votos_fed'] ?? ''); ?>" maxlength="20" style="width: 100%; padding: 5px; border: 1px solid #ddd; border-radius: 4px;"></td>
                                        <td><input type="number" step="0.01" max="100" name="porc_fed_<?php echo $anio; ?>" value="<?php echo htmlspecialchars($e['porc_fed'] ?? ''); ?>" style="width: 100%; padding: 5px; border: 1px solid #ddd; border-radius: 4px;"></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="content-card">
                    <h4 style="margin-top: 0; margin-bottom: 15px; color: var(--primary);">Encuestas e intención de voto</h4>
                    <div class="table-actions-container" style="min-height: auto; overflow-x: auto;">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>Año</th>
                                    <th>Fuente</th>
                                    <th>Fecha (dd/mm/yyyy)</th>
                                    <th>% Intención PVEM</th>
                                    <th>Link Fuente</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach([2012, 2015, 2018, 2021, 2024] as $anio): 
                                    $e = $data['diag_electoral'][$anio] ?? null; 
                                    ?>
                                <tr>
                                    <td><strong><?php echo $anio; ?></strong></td>
                                    <td><input type="text" name="encuesta_fuente_<?php echo $anio; ?>" value="<?php echo htmlspecialchars($e['encuesta_fuente'] ?? ''); ?>" maxlength="100" style="width: 100%; padding: 5px; border: 1px solid #ddd; border-radius: 4px;"></td>
                                    <td><input type="date" name="encuesta_fecha_<?php echo $anio; ?>" value="<?php echo htmlspecialchars($e['encuesta_fecha'] ?? ''); ?>" style="width: 100%; padding: 5px; border: 1px solid #ddd; border-radius: 4px;"></td>
                                    <td><input type="number" step="0.01" max="100" name="encuesta_intencion_<?php echo $anio; ?>" value="<?php echo htmlspecialchars($e['encuesta_intencion'] ?? ''); ?>" style="width: 100%; padding: 5px; border: 1px solid #ddd; border-radius: 4px;"></td>
                                    <td><input type="url" name="encuesta_link_<?php echo $anio; ?>" value="<?php echo htmlspecialchars($e['encuesta_link'] ?? ''); ?>" maxlength="255" style="width: 100%; padding: 5px; border: 1px solid #ddd; border-radius: 4px;"></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="content-card">
                    <h4 style="margin-top: 0; margin-bottom: 15px; color: var(--primary);">Estructura Organizativa y Recursos</h4>
                    <div class="grid-2" style="margin-bottom: 20px;">
                        <div><label style="font-size: 12px; font-weight: bold;">Número de municipios totales:</label> <input type="text" class="number-format" name="mun_totales" value="<?php echo htmlspecialchars($data['diagnostico']['mun_totales'] ?? ''); ?>" maxlength="20" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; margin-top: 5px;"></div>
                        <div><label style="font-size: 12px; font-weight: bold;">Con presencia Verde:</label> <input type="text" class="number-format" name="mun_presencia" value="<?php echo htmlspecialchars($data['diagnostico']['mun_presencia'] ?? ''); ?>" maxlength="20" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; margin-top: 5px;"></div>
                        
                        <div><label style="font-size: 12px; font-weight: bold;">Afiliados registrados:</label> <input type="text" class="number-format" name="afil_registrados" value="<?php echo htmlspecialchars($data['diagnostico']['afil_registrados'] ?? ''); ?>" maxlength="20" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; margin-top: 5px;"></div>
                        <div><label style="font-size: 12px; font-weight: bold;">Afiliados activos confirmados:</label> <input type="text" class="number-format" name="afil_activos" value="<?php echo htmlspecialchars($data['diagnostico']['afil_activos'] ?? ''); ?>" maxlength="20" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; margin-top: 5px;"></div>
                        
                        <div><label style="font-size: 12px; font-weight: bold;">Promotores registrados:</label> <input type="text" class="number-format" name="promo_registrados" value="<?php echo htmlspecialchars($data['diagnostico']['promo_registrados'] ?? ''); ?>" maxlength="20" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; margin-top: 5px;"></div>
                        <div><label style="font-size: 12px; font-weight: bold;">Promotores confirmados:</label> <input type="text" class="number-format" name="promo_confirmados" value="<?php echo htmlspecialchars($data['diagnostico']['promo_confirmados'] ?? ''); ?>" maxlength="20" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; margin-top: 5px;"></div>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <label style="font-size: 12px; font-weight: bold;">Presupuesto anual aproximado:</label>
                        <input type="text" name="presupuesto" class="currency-format" value="<?php echo htmlspecialchars($data['diagnostico']['presupuesto'] ?? ''); ?>" maxlength="20" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; margin-top: 5px;">
                    </div>

                    <div class="grid-2">
                        <div>
                            <h5 style="margin-bottom: 10px;">Oficinas / Infraestructura</h5>
                            <div style="margin-bottom: 10px;"><label style="font-size: 11px;">Municipal</label> <input type="text" class="number-format" name="ofi_municipal" value="<?php echo htmlspecialchars($data['diagnostico']['ofi_municipal'] ?? ''); ?>" maxlength="20" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></div>
                            <div style="margin-bottom: 10px;"><label style="font-size: 11px;">Regional</label> <input type="text" class="number-format" name="ofi_regional" value="<?php echo htmlspecialchars($data['diagnostico']['ofi_regional'] ?? ''); ?>" maxlength="20" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></div>
                            <div style="margin-bottom: 10px;"><label style="font-size: 11px;">Estatal</label> <input type="text" class="number-format" name="ofi_estatal" value="<?php echo htmlspecialchars($data['diagnostico']['ofi_estatal'] ?? ''); ?>" maxlength="20" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></div>
                        </div>
                        <div>
                            <h5 style="margin-bottom: 10px;">Plataformas Digitales</h5>
                            <table class="custom-table" style="font-size: 11px;">
                                <thead><tr><th>Plataforma</th><th>Link</th><th>Seguidores</th></tr></thead>
                                <tbody>
                                    <?php foreach(['Facebook', 'X.com', 'Instagram', 'Otro'] as $plat): 
                                        $key = strtolower(str_replace('.com', '', $plat)); ?>
                                    <tr>
                                        <td><?php echo $plat; ?></td>
                                        <td><input type="url" name="link_<?php echo $key; ?>" value="<?php echo htmlspecialchars($data['diagnostico']["link_$key"] ?? ''); ?>" maxlength="255" style="width: 100%; padding: 4px; border: 1px solid #ddd;"></td>
                                        <td><input type="text" class="number-format" name="seg_<?php echo $key; ?>" value="<?php echo htmlspecialchars($data['diagnostico']["seg_$key"] ?? ''); ?>" maxlength="20" style="width: 100%; padding: 4px; border: 1px solid #ddd;"></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="content-card">
                    <h4 style="margin-top: 0; margin-bottom: 15px; color: var(--primary);">Análisis FODA <small style="color: #666; font-weight: normal;">(Máx. 200 palabras por campo)</small></h4>
                    <div class="grid-2">
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: bold; margin-bottom: 5px;">Fortalezas internas (1 a 4):</label>
                            <textarea name="foda_fortalezas" rows="4" maxlength="1500" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;"><?php echo htmlspecialchars($data['diagnostico']['foda_fortalezas'] ?? ''); ?></textarea>
                        </div>
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: bold; margin-bottom: 5px;">Debilidades internas (1 a 4):</label>
                            <textarea name="foda_debilidades" rows="4" maxlength="1500" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;"><?php echo htmlspecialchars($data['diagnostico']['foda_debilidades'] ?? ''); ?></textarea>
                        </div>
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: bold; margin-bottom: 5px;">Oportunidades externas (1 a 4):</label>
                            <textarea name="foda_oportunidades" rows="4" maxlength="1500" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;"><?php echo htmlspecialchars($data['diagnostico']['foda_oportunidades'] ?? ''); ?></textarea>
                        </div>
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: bold; margin-bottom: 5px;">Amenazas externas (1 a 4):</label>
                            <textarea name="foda_amenazas" rows="4" maxlength="1500" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;"><?php echo htmlspecialchars($data['diagnostico']['foda_amenazas'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="content-card">
                    <h4 style="margin-top: 0; margin-bottom: 15px; color: var(--primary);">Prioridades y KPI's</h4>
                    <div style="background: #e3f2fd; padding: 10px; border-radius: 8px; font-size: 12px; margin-bottom: 15px; color: #1565c0;">
                        <i class="fas fa-info-circle"></i> Un KPI es una medida concreta y verificable. Para cada KPI definir línea base, meta temporal, responsable y fuente.
                    </div>
                    
                    <div class="kpi-block">
                        <div class="kpi-block-title">Problema Crítico 1</div>
                        <input type="text" name="problema_1_titulo" value="<?php echo htmlspecialchars($data['diagnostico']['problema_1_titulo'] ?? ''); ?>" placeholder="Ej: Baja votación y movilización electoral..." maxlength="50" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 10px;">
                        
                        <label style="font-size: 11px; font-weight: 700; color: #666; margin-bottom: 5px; display: block;">Descripción breve</label>
                        <textarea name="problema_1_desc" rows="2" placeholder="Resultados electorales por debajo de la meta..." maxlength="200" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 15px;"><?php echo htmlspecialchars($data['diagnostico']['problema_1_desc'] ?? ''); ?></textarea>
                        
                        <label style="font-size: 11px; font-weight: 700; color: #666; margin-bottom: 5px; display: block;">3 KPIs propuestos</label>
                        <input type="text" name="kpi_1_1" value="<?php echo htmlspecialchars($data['diagnostico']['kpi_1_1'] ?? ''); ?>" placeholder="KPI 1..." maxlength="150" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 8px;">
                        <input type="text" name="kpi_1_2" value="<?php echo htmlspecialchars($data['diagnostico']['kpi_1_2'] ?? ''); ?>" placeholder="KPI 2..." maxlength="150" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 8px;">
                        <input type="text" name="kpi_1_3" value="<?php echo htmlspecialchars($data['diagnostico']['kpi_1_3'] ?? ''); ?>" placeholder="KPI 3..." maxlength="150" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
                    </div>

                    <div class="kpi-block">
                        <div class="kpi-block-title">Problema Crítico 2</div>
                        <input type="text" name="problema_2_titulo" value="<?php echo htmlspecialchars($data['diagnostico']['problema_2_titulo'] ?? ''); ?>" placeholder="Título del problema..." maxlength="50" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 10px;">
                        
                        <label style="font-size: 11px; font-weight: 700; color: #666; margin-bottom: 5px; display: block;">Descripción breve</label>
                        <textarea name="problema_2_desc" rows="2" placeholder="Detalla el problema aquí..." maxlength="200" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 15px;"><?php echo htmlspecialchars($data['diagnostico']['problema_2_desc'] ?? ''); ?></textarea>
                        
                        <label style="font-size: 11px; font-weight: 700; color: #666; margin-bottom: 5px; display: block;">3 KPIs propuestos</label>
                        <input type="text" name="kpi_2_1" value="<?php echo htmlspecialchars($data['diagnostico']['kpi_2_1'] ?? ''); ?>" placeholder="KPI 1..." maxlength="150" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 8px;">
                        <input type="text" name="kpi_2_2" value="<?php echo htmlspecialchars($data['diagnostico']['kpi_2_2'] ?? ''); ?>" placeholder="KPI 2..." maxlength="150" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 8px;">
                        <input type="text" name="kpi_2_3" value="<?php echo htmlspecialchars($data['diagnostico']['kpi_2_3'] ?? ''); ?>" placeholder="KPI 3..." maxlength="150" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
                    </div>

                    <div class="kpi-block">
                        <div class="kpi-block-title">Problema Crítico 3</div>
                        <input type="text" name="problema_3_titulo" value="<?php echo htmlspecialchars($data['diagnostico']['problema_3_titulo'] ?? ''); ?>" placeholder="Título del problema..." maxlength="50" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 10px;">
                        
                        <label style="font-size: 11px; font-weight: 700; color: #666; margin-bottom: 5px; display: block;">Descripción breve</label>
                        <textarea name="problema_3_desc" rows="2" placeholder="Detalla el problema aquí..." maxlength="200" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 15px;"><?php echo htmlspecialchars($data['diagnostico']['problema_3_desc'] ?? ''); ?></textarea>
                        
                        <label style="font-size: 11px; font-weight: 700; color: #666; margin-bottom: 5px; display: block;">3 KPIs propuestos</label>
                        <input type="text" name="kpi_3_1" value="<?php echo htmlspecialchars($data['diagnostico']['kpi_3_1'] ?? ''); ?>" placeholder="KPI 1..." maxlength="150" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 8px;">
                        <input type="text" name="kpi_3_2" value="<?php echo htmlspecialchars($data['diagnostico']['kpi_3_2'] ?? ''); ?>" placeholder="KPI 2..." maxlength="150" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 8px;">
                        <input type="text" name="kpi_3_3" value="<?php echo htmlspecialchars($data['diagnostico']['kpi_3_3'] ?? ''); ?>" placeholder="KPI 3..." maxlength="150" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
                    </div>
                </div>

                <div class="content-card">
                    <h4 style="margin-top: 0; margin-bottom: 15px; color: var(--primary);">Documentos Adjuntos</h4>
                    <p style="font-size: 12px; color: #666; margin-bottom: 15px;">Actas/Resultados INE, Encuestas, Otros documentos, etc.</p>
                    
                    <div class="grid-2">
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: bold; margin-bottom: 5px;">Cargar Documento (PDF, etc.)</label>
                            
                            <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                                <input type="file" name="documentos[]" id="inputDocumentos" multiple style="display: none;" onchange="previsualizarArchivos()">
                                <button type="button" onclick="document.getElementById('inputDocumentos').click()" style="padding: 10px 15px; background: #e3f2fd; color: #1565c0; border: 1px dashed #1565c0; border-radius: 8px; font-size: 12px; font-weight: bold; cursor: pointer; width: 100%; transition: 0.2s;">
                                    <i class="fas fa-file-upload"></i> Seleccionar archivos para subir
                                </button>
                            </div>
                        
                            <h5 style="margin-bottom: 10px; color: var(--dark); font-size: 13px;">Archivos en el servidor y pendientes</h5>
                            
                            <div id="contenedorTablaArchivos" style="overflow-x: auto; border-radius: 8px; border: 1px solid #eee; <?php echo empty($data['archivos']) ? 'display: none;' : ''; ?>">
                                <table class="custom-table" style="font-size: 11px; margin: 0;">
                                    <thead style="background: #f4f6f8;">
                                        <tr>
                                            <th style="padding: 10px;">Nombre</th>
                                            <th style="padding: 10px; text-align: center;">Fecha</th>
                                            <th style="padding: 10px; text-align: center;">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tablaArchivosBody">
                                        <?php if(!empty($data['archivos'])): ?>
                                            <?php foreach($data['archivos'] as $archivo): ?>
                                                <tr>
                                                    <td style="padding: 10px; max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($archivo['nombre_original']); ?>">
                                                        <i class="fas fa-file-alt" style="color: #888; margin-right: 5px;"></i> <?php echo htmlspecialchars($archivo['nombre_original']); ?>
                                                    </td>
                                                    <td style="padding: 10px; text-align: center; color: #888;"><?php echo date('d/m/Y', strtotime($archivo['fecha_subida'])); ?></td>
                                                    <td style="padding: 10px; text-align: center;">
                                                        <a href="<?php echo htmlspecialchars($archivo['ruta_archivo']); ?>" target="_blank" style="color: var(--primary); text-decoration: none; font-weight: bold; background: #e8f5e9; padding: 4px 8px; border-radius: 4px;">
                                                            <i class="fas fa-download"></i> Bajar
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: bold; margin-bottom: 5px;">Enlaces Externos Relacionados</label>
                            
                            <div id="links-container" style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 10px;">
                                </div>
                            
                            <button type="button" onclick="addLinkField()" style="padding: 10px 15px; background: #e3f2fd; color: #1565c0; border: 1px dashed #1565c0; border-radius: 8px; font-size: 12px; font-weight: bold; cursor: pointer; width: 100%; transition: 0.2s;">
                                <i class="fas fa-plus"></i> Agregar otro enlace
                            </button>

                            <input type="hidden" name="links_adjuntos" id="hidden_links_adjuntos">
                        </div>
                    </div>
                </div>

                <div style="text-align: right; margin-top: 20px;">
                    <button type="submit" style="padding: 15px 30px; background: var(--primary); color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: bold; cursor: pointer; box-shadow: 0 4px 6px rgba(45,122,62,0.3);">
                        <i class="fas fa-save"></i> Guardar Diagnóstico
                    </button>
                </div>

            </form>
        </div>
    </div>

    <div class="modal" id="stateModal">
        <div class="modal-content">
            <div class="close-modal" onclick="closeModal()"><i class="fas fa-times"></i></div>
            <h2 style="margin-bottom: 15px;">Gestión Operativa</h2>
            <p style="font-size: 13px; color: #666; margin-bottom: 20px;">Agregar nuevo registro para: <strong><?php echo htmlspecialchars($estado_actual); ?></strong></p>
            
            <form id="createForm" onsubmit="handleCreateSubmit(event)">
                <input type="hidden" name="accion" value="crear">
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 12px; font-weight: bold; margin-bottom: 5px;">Tipo de Registro</label>
                    <select id="createType" name="tipo" style="width: 100%; padding: 12px; border: 1px solid var(--primary); border-radius: 8px; font-weight: bold; color: var(--primary);" onchange="toggleCreateFields()">
                        <option value="objetivo">Objetivo</option>
                        <option value="meta">Meta</option>
                        <option value="estrategia">Estrategia</option>
                        <option value="cronograma">Acción</option>
                        <option value="vision_mision">Editar Visión y Misión</option>
                    </select>
                </div>

                <div id="divVMFields" style="display: none; background: #f0f7f0; padding: 15px; border-radius: 10px; margin-bottom: 15px;">
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; font-size: 12px; font-weight: bold; margin-bottom: 5px;">Visión</label>
                        <textarea id="vmVision" name="vision" rows="4" maxlength="1000" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 8px;"></textarea>
                    </div>
                    <div style="margin-bottom: 0;">
                        <label style="display: block; font-size: 12px; font-weight: bold; margin-bottom: 5px;">Misión</label>
                        <textarea id="vmMision" name="mision" rows="4" maxlength="1000" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 8px;"></textarea>
                    </div>
                </div>

                <div id="divCronoFields" style="display: none; background: #e3f2fd; padding: 15px; border-radius: 10px; margin-bottom: 15px;">
                    <div style="margin-bottom: 10px;">
                        <label style="display: block; font-size: 11px; font-weight: bold;">Fecha Visual (Texto, ej: "Enero 2026")</label>
                        <input type="text" name="fecha_visual" class="crono-input" maxlength="50" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px;">
                    </div>
                    <div style="margin-bottom: 10px;">
                        <label style="display: block; font-size: 11px; font-weight: bold;">Fecha Orden</label>
                        <input type="date" name="fecha_orden" class="crono-input" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px;">
                    </div>
                </div>

                <div id="divMetaFields" style="display: none; background: #fff8e1; padding: 15px; border-radius: 10px; margin-bottom: 15px;">
                    <div class="alert-info" style="font-size: 11px; margin-bottom: 5px; color: #888;"><i class="fas fa-info-circle"></i> Escribe el texto de la meta aquí.</div>
                    <label style="display: block; font-size: 12px; font-weight: bold; margin-bottom: 5px;">Descripción de la Meta</label>
                    <textarea name="descripcion" class="meta-input" rows="3" maxlength="200" placeholder="Ej: Lograr el 11% de..." style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;"></textarea>
                </div>

                <div id="divGenericFields">
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; font-size: 12px; font-weight: bold; margin-bottom: 5px;">Título</label>
                        <input type="text" name="titulo" class="generic-input" maxlength="50" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; font-size: 12px; font-weight: bold; margin-bottom: 5px;">Descripción</label>
                        <textarea name="descripcion" class="generic-input" rows="3" maxlength="200" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;"></textarea>
                    </div>
                </div>

                <div style="text-align: right; gap: 10px; display: flex; justify-content: flex-end;">
                    <button type="button" onclick="closeModal()" style="padding: 10px 20px; background: #eee; border: none; border-radius: 5px; cursor: pointer;">Cancelar</button>
                    <button type="submit" style="padding: 10px 20px; background: var(--primary); color: white; border: none; border-radius: 5px; cursor: pointer;">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal" id="editModal" style="z-index: 3001;">
        <div class="modal-content">
            <h2 style="margin-bottom: 15px;">Modificar Elemento</h2>
            <form id="editForm" onsubmit="handleEditSubmit(event)">
                <input type="hidden" id="editId" name="id">
                <input type="hidden" id="editType" name="tipo">
                <input type="hidden" name="accion" value="editar">
                
                <div style="margin-bottom: 15px;" id="divEditTitle">
                    <label style="display: block; font-size: 12px; font-weight: bold; margin-bottom: 5px;">Título</label>
                    <input type="text" id="editTitle" name="titulo" maxlength="50" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
                </div>
                
                <div id="divEditCronoFields" style="display: none; background: #f9f9f9; padding: 10px; border-radius: 8px; margin-bottom: 15px;">
                     <div style="margin-bottom: 10px;"><label style="font-size: 11px; font-weight: bold;">Fecha Visual</label><input type="text" id="editFechaVis" name="fecha_visual" maxlength="50" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px;"></div>
                     <div style="margin-bottom: 10px;"><label style="font-size: 11px; font-weight: bold;">Fecha Orden</label><input type="date" id="editFechaOrd" name="fecha_orden" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px;"></div>
                </div>

                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-size: 12px; font-weight: bold; margin-bottom: 5px;">Descripción</label>
                    <textarea id="editDesc" name="descripcion" rows="4" maxlength="200" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;" required></textarea>
                </div>

                <div style="text-align: right; gap: 10px; display: flex; justify-content: flex-end; margin-top: 15px;">
                    <button type="button" onclick="closeEditModal()" style="padding: 10px 20px; background: #eee; border: none; border-radius: 5px; cursor: pointer;">Cancelar</button>
                    <button type="submit" style="padding: 10px 20px; background: var(--primary); color: white; border: none; border-radius: 5px; cursor: pointer;">Actualizar</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function showTab(tabName) {
            localStorage.setItem('coordActiveTab', tabName);
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            
            const activeContent = document.getElementById('tab-' + tabName);
            if(activeContent) activeContent.classList.add('active');
            
            const activeBtn = document.querySelector(`.tab-btn[data-tab="${tabName}"]`);
            if(activeBtn) activeBtn.classList.add('active');
        }

        // --- SISTEMA DINÁMICO DE CONTADOR DE CARACTERES ---
        function initCharCounters() {
            document.querySelectorAll('input[maxlength], textarea[maxlength]').forEach(field => {
                // Saltamos los de los enlaces dinámicos porque esos se manejan diferente
                if(field.classList.contains('dynamic-link-input')) return;
                // Si ya tiene el contador, no agregamos otro
                if(field.nextElementSibling && field.nextElementSibling.classList.contains('char-counter')) return;
                
                const counter = document.createElement('div');
                counter.className = 'char-counter';
                counter.innerText = `${field.value.length} / ${field.maxLength}`;
                
                field.parentNode.insertBefore(counter, field.nextSibling);
                
                field.addEventListener('input', () => {
                    counter.innerText = `${field.value.length} / ${field.maxLength}`;
                    if(field.value.length >= field.maxLength) counter.classList.add('limit-reached');
                    else counter.classList.remove('limit-reached');
                });
                
                // Disparamos el evento una vez para establecer colores y textos iniciales
                field.dispatchEvent(new Event('input'));
            });
        }

        // --- FUNCIONES DE FORMATO MIENTRAS EL USUARIO ESCRIBE ---
        function applyMasks() {
            document.querySelectorAll('.number-format').forEach(input => {
                input.addEventListener('input', function() {
                    let val = this.value.replace(/[^0-9]/g, '');
                    if(val !== '') {
                        this.value = parseInt(val, 10).toLocaleString('en-US');
                    } else {
                        this.value = '';
                    }
                });
                let initialVal = input.value.replace(/[^0-9]/g, '');
                if(initialVal) input.value = parseInt(initialVal, 10).toLocaleString('en-US');
            });

            document.querySelectorAll('.currency-format').forEach(input => {
                input.addEventListener('input', function() {
                    let val = this.value.replace(/[^0-9]/g, '');
                    if(val !== '') {
                        this.value = '$ ' + parseInt(val, 10).toLocaleString('en-US');
                    } else {
                        this.value = '';
                    }
                });
                let initialVal = input.value.replace(/[^0-9]/g, '');
                if(initialVal) input.value = '$ ' + parseInt(initialVal, 10).toLocaleString('en-US');
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            const savedTab = localStorage.getItem('coordActiveTab') || 'vision';
            showTab(savedTab);
            toggleCreateFields(); 
            applyMasks();
            initCharCounters(); // Iniciar los contadores de texto
            initLinks();
        });

        const modal = document.getElementById('stateModal');
        const editModal = document.getElementById('editModal');

        function openModal() { modal.style.display = 'flex'; toggleCreateFields(); }
        function closeModal() { modal.style.display = 'none'; }
        function closeEditModal() { editModal.style.display = 'none'; }

        function toggleCreateFields() {
            const type = document.getElementById('createType').value;
            const cronoDiv = document.getElementById('divCronoFields');
            const metaDiv = document.getElementById('divMetaFields');
            const vmDiv = document.getElementById('divVMFields');
            const genericDiv = document.getElementById('divGenericFields');
            
            const cronoInputs = cronoDiv.querySelectorAll('input, select, textarea');
            const metaInputs = metaDiv.querySelectorAll('input, select, textarea');
            const vmInputs = vmDiv.querySelectorAll('input, select, textarea');
            const genericInputs = genericDiv.querySelectorAll('input, select, textarea');

            const setInputState = (nodeList, isActive) => {
                nodeList.forEach(input => { input.disabled = !isActive; });
            };

            cronoDiv.style.display = 'none'; metaDiv.style.display = 'none';
            vmDiv.style.display = 'none'; genericDiv.style.display = 'none';

            setInputState(cronoInputs, false); setInputState(metaInputs, false);
            setInputState(vmInputs, false); setInputState(genericInputs, false);

            if(type === 'cronograma') {
                cronoDiv.style.display = 'block'; genericDiv.style.display = 'block';
                setInputState(cronoInputs, true); setInputState(genericInputs, true);
            } 
            else if (type === 'meta') {
                metaDiv.style.display = 'block'; setInputState(metaInputs, true);
            }
            else if (type === 'vision_mision') {
                vmDiv.style.display = 'block'; setInputState(vmInputs, true);
                
                // Actualizar los inputs y forzar evento input para actualizar contadores
                const visionInput = document.getElementById('vmVision');
                const misionInput = document.getElementById('vmMision');
                visionInput.value = currentVision;
                misionInput.value = currentMision;
                visionInput.dispatchEvent(new Event('input'));
                misionInput.dispatchEvent(new Event('input'));
            }
            else {
                genericDiv.style.display = 'block'; setInputState(genericInputs, true);
            }
        }

        function toggleMenu(event, menuId) {
            event.stopPropagation();
            document.querySelectorAll('.menu-content').forEach(m => { if(m.id !== menuId) m.classList.remove('show'); });
            document.getElementById(menuId).classList.toggle('show');
        }

        window.onclick = function(event) {
            if (!event.target.matches('.menu-btn') && !event.target.matches('.menu-btn i')) {
                document.querySelectorAll('.menu-content').forEach(m => m.classList.remove('show'));
            }
            if (event.target === modal) closeModal();
            if (event.target === editModal) closeEditModal();
        }

        async function handleCreateSubmit(e) {
            e.preventDefault();
            const formData = new FormData(e.target);
            try {
                const response = await fetch(window.location.href, { method: 'POST', body: formData });
                const res = await response.json();
                if(res.success) location.reload(); 
                else alert('Error: ' + res.message);
            } catch(e) { console.error(e); alert('Error de conexión'); }
        }

        async function deleteItem(id, type) {
            if(!confirm('¿Eliminar elemento?')) return;
            const formData = new FormData();
            formData.append('accion', 'eliminar'); formData.append('id', id); formData.append('tipo', type);

            try {
                const response = await fetch(window.location.href, { method: 'POST', body: formData });
                const res = await response.json();
                if(res.success) location.reload();
                else alert('Error: ' + res.message);
            } catch(e) { console.error(e); alert('Error de conexión'); }
        }

        function openEditModal(id, type, title, desc, r, p, rec, ind, fvis = '', ford = '') {
            document.getElementById('editId').value = id;
            document.getElementById('editType').value = type;
            document.getElementById('editDesc').value = desc;
            
            const divTitle = document.getElementById('divEditTitle');
            const inpTitle = document.getElementById('editTitle');
            const divCrono = document.getElementById('divEditCronoFields');
            const inpFvis = document.getElementById('editFechaVis');
            
            if(type === 'meta') {
                divTitle.style.display = 'none'; inpTitle.required = false;
            } else {
                divTitle.style.display = 'block'; inpTitle.value = title; inpTitle.required = true;
            }

            if(type === 'cronograma') {
                divCrono.style.display = 'block';
                inpFvis.value = fvis;
                document.getElementById('editFechaOrd').value = ford;
            } else {
                divCrono.style.display = 'none';
            }
            
            // Forzar actualización de contadores al abrir el modal
            [document.getElementById('editDesc'), inpTitle, inpFvis].forEach(el => {
                if(el) el.dispatchEvent(new Event('input'));
            });
            
            editModal.style.display = 'flex';
        }

        async function handleEditSubmit(e) {
            e.preventDefault();
            const formData = new FormData(e.target);
            if(document.getElementById('editType').value === 'meta') {
                formData.set('titulo', document.getElementById('editDesc').value);
            }
            try {
                const response = await fetch(window.location.href, { method: 'POST', body: formData });
                const res = await response.json();
                if(res.success) location.reload();
                else alert('Error: ' + res.message);
            } catch(e) { console.error(e); alert('Error de conexión'); }
        }

        async function guardarDiagnostico(e) {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);
            formData.append('accion', 'guardar_diagnostico');
            
            const btn = form.querySelector('button[type="submit"]');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
            btn.disabled = true;

            try {
                const response = await fetch(window.location.href, { method: 'POST', body: formData });
                const res = await response.json();
                
                if(res.success) {
                    alert('¡Diagnóstico guardado con éxito!');
                    location.reload(); 
                } else {
                    alert('Error: ' + res.message);
                }
            } catch(error) { 
                console.error(error); 
                alert('Ocurrió un error al intentar guardar. Revisa tu conexión.'); 
            } finally {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        }

        // --- GESTOR DE ENLACES DINÁMICOS ---
        const linksGuardados = <?php echo json_encode(explode("\n", $data['diagnostico']['links_adjuntos'] ?? '')); ?>;

        function initLinks() {
            const container = document.getElementById('links-container');
            container.innerHTML = '';
            let tieneLinks = false;
            
            if (linksGuardados && linksGuardados.length > 0) {
                linksGuardados.forEach(link => {
                    const textoLimpio = link.trim();
                    if (textoLimpio) {
                        addLinkField(textoLimpio);
                        tieneLinks = true;
                    }
                });
            }
            
            if (!tieneLinks) addLinkField();
            
            actualizarLinksOcultos();
        }

        function addLinkField(valor = '') {
            const container = document.getElementById('links-container');
            
            // Validar límite de 4 enlaces
            if (container.children.length >= 4) {
                alert('Máximo 4 enlaces permitidos.');
                return;
            }
        
            const div = document.createElement('div');
            div.style.display = 'flex';
            div.style.gap = '10px';
            div.style.alignItems = 'center';
            
            // Wrapper para agrupar input + contador sin romper el flex
            const inputWrapper = document.createElement('div');
            inputWrapper.style.flex = '1';
            inputWrapper.style.position = 'relative';
            
            const input = document.createElement('input');
            input.type = 'text';
            input.className = 'dynamic-link-input';
            input.maxLength = 255; 
            input.value = valor;
            input.placeholder = 'https://ejemplo.com/documento-o-noticia';
            input.style.width = '100%';
            input.style.padding = '10px';
            input.style.border = '1px solid #ddd';
            input.style.borderRadius = '8px';
            
            // Creación de contador individual para campo dinámico
            const counter = document.createElement('div');
            counter.className = 'char-counter';
            counter.innerText = `${input.value.length} / 255`;
            
            input.addEventListener('input', () => {
                actualizarLinksOcultos();
                counter.innerText = `${input.value.length} / 255`;
                if(input.value.length >= 255) counter.classList.add('limit-reached');
                else counter.classList.remove('limit-reached');
            });
            
            const btnDelete = document.createElement('button');
            btnDelete.type = 'button';
            btnDelete.innerHTML = '<i class="fas fa-trash-alt"></i>';
            btnDelete.style.padding = '10px 15px';
            btnDelete.style.background = '#ffebee';
            btnDelete.style.color = '#c62828';
            btnDelete.style.border = 'none';
            btnDelete.style.borderRadius = '8px';
            btnDelete.style.cursor = 'pointer';
            btnDelete.style.alignSelf = 'flex-start'; // Para alinear botón arriba
            btnDelete.title = "Eliminar enlace";
            
            btnDelete.onclick = function() { 
                div.remove(); 
                actualizarLinksOcultos(); 
            };
            
            inputWrapper.appendChild(input);
            inputWrapper.appendChild(counter);
            
            div.appendChild(inputWrapper);
            div.appendChild(btnDelete);
            container.appendChild(div);
            
            actualizarLinksOcultos();
        }

        function actualizarLinksOcultos() {
            const inputs = document.querySelectorAll('.dynamic-link-input');
            const linksActivos = Array.from(inputs)
                                      .map(inp => inp.value.trim())
                                      .filter(val => val !== '');
            
            document.getElementById('hidden_links_adjuntos').value = linksActivos.join('\n');
        }

        // --- PREVISUALIZACIÓN DE ARCHIVOS ANTES DE SUBIR ---
        function previsualizarArchivos() {
            const input = document.getElementById('inputDocumentos');
            const tbody = document.getElementById('tablaArchivosBody');
            const contenedorTabla = document.getElementById('contenedorTablaArchivos');
        
            // 1. Limpiar archivos pendientes anteriores (por si el usuario vuelve a darle click al botón y selecciona otros)
            document.querySelectorAll('.fila-pendiente').forEach(row => row.remove());
        
            // 2. Si hay archivos seleccionados, mostrar la tabla
            if (input.files.length > 0) {
                contenedorTabla.style.display = 'block';
            } else if (tbody.children.length === 0) {
                // Si no hay archivos nuevos ni antiguos, ocultar la tabla
                contenedorTabla.style.display = 'none';
                return;
            }
        
            // 3. Crear las filas para los archivos nuevos
            Array.from(input.files).forEach(file => {
                const tr = document.createElement('tr');
                tr.className = 'fila-pendiente';
                tr.style.background = '#fff8e1'; // Fondo amarillento para destacar que no están guardados
        
                const tdName = document.createElement('td');
                tdName.style.padding = '10px';
                tdName.style.maxWidth = '150px';
                tdName.style.overflow = 'hidden';
                tdName.style.textOverflow = 'ellipsis';
                tdName.style.whiteSpace = 'nowrap';
                tdName.title = file.name;
                tdName.innerHTML = `<i class="fas fa-cloud-upload-alt" style="color: #f57f17; margin-right: 5px;"></i> ${file.name} <br><span style="font-size: 9px; color: #f57f17; font-weight: bold;">(Pendiente de guardar)</span>`;
        
                const tdDate = document.createElement('td');
                tdDate.style.padding = '10px';
                tdDate.style.textAlign = 'center';
                tdDate.style.color = '#888';
                tdDate.textContent = 'Ahora';
        
                const tdAction = document.createElement('td');
                tdAction.style.padding = '10px';
                tdAction.style.textAlign = 'center';
                tdAction.innerHTML = `<span style="color: #f57f17; font-size: 11px; font-weight: bold;">Por subir</span>`;
        
                tr.appendChild(tdName);
                tr.appendChild(tdDate);
                tr.appendChild(tdAction);
                
                // Agregar al principio de la tabla para que destaquen
                tbody.insertBefore(tr, tbody.firstChild);
            });
        }
    </script>
</body>
</html>