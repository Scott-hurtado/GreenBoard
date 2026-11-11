<?php
// FILE: crud_estatal.php 

// 1. CONFIGURACIÓN
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

try {
    // 2. CARGAR BOOTSTRAP
    require_once __DIR__ . '/../../includes/bootstrap.php';

    // 3. VALIDACIÓN DE SESIÓN
    if (session_status() === PHP_SESSION_NONE) session_start();

    $rol = $_SESSION['user_role'] ?? '';
    $user_id = $_SESSION['user_id'] ?? 0;
    $permitidos = ['admin', 'administrador', 'superadmin', 'coordinador', 'coordinador_estatal'];

    if (!in_array($rol, $permitidos)) {
        http_response_code(403);
        throw new Exception('No autorizado.');
    }

    $db = Database::getInstance()->getConnection();

    /**
     * IMPORTANTE: El Diagnóstico Inicial envía archivos, por lo que los datos 
     * no vienen en el JSON body (php://input), sino en $_POST.
     */
    $inputJSON = json_decode(file_get_contents('php://input'), true);
    $data = !empty($_POST) ? $_POST : $inputJSON;

    $accion = $data['accion'] ?? '';
    $tipo = $data['tipo'] ?? '';
    $id = (int)($data['id'] ?? 0);
    $estado_req = $data['estado'] ?? '';

    // ==================================================================
    // LÓGICA DE DIAGNÓSTICO (NUEVA)
    // ==================================================================
    if ($accion === 'guardar_diagnostico') {
        if (empty($estado_req)) throw new Exception("Estado no especificado.");

        $db->beginTransaction();
        try {
            // 1. Guardar en geo_diagnostico (Ficha, Recursos, FODA, KPIs)
            $sqlDiag = "INSERT INTO geo_diagnostico (
                estado, responsable, cargo, tel, mail, contacto_otro,
                mun_totales, mun_presencia, afil_registrados, afil_activos, promo_registrados, promo_confirmados, presupuesto,
                ofi_municipal, ofi_regional, ofi_estatal,
                link_facebook, seg_facebook, link_x, seg_x, link_instagram, seg_instagram, link_otro, seg_otro,
                foda_fortalezas, foda_debilidades, foda_oportunidades, foda_amenazas,
                problema_1, problema_2, problema_3, kpi_1, kpi_2, kpi_3, links_adjuntos
            ) VALUES (
                :estado, :responsable, :cargo, :tel, :mail, :contacto_otro,
                :mun_totales, :mun_presencia, :afil_registrados, :afil_activos, :promo_registrados, :promo_confirmados, :presupuesto,
                :ofi_municipal, :ofi_regional, :ofi_estatal,
                :link_facebook, :seg_facebook, :link_x, :seg_x, :link_instagram, :seg_instagram, :link_otro, :seg_otro,
                :foda_fortalezas, :foda_debilidades, :foda_oportunidades, :foda_amenazas,
                :problema_1, :problema_2, :problema_3, :kpi_1, :kpi_2, :kpi_3, :links_adjuntos
            ) ON DUPLICATE KEY UPDATE 
                responsable=VALUES(responsable), cargo=VALUES(cargo), tel=VALUES(tel), mail=VALUES(mail), contacto_otro=VALUES(contacto_otro),
                mun_totales=VALUES(mun_totales), mun_presencia=VALUES(mun_presencia), afil_registrados=VALUES(afil_registrados), 
                afil_activos=VALUES(afil_activos), promo_registrados=VALUES(promo_registrados), promo_confirmados=VALUES(promo_confirmados), 
                presupuesto=VALUES(presupuesto), ofi_municipal=VALUES(ofi_municipal), ofi_regional=VALUES(ofi_regional), ofi_estatal=VALUES(ofi_estatal),
                link_facebook=VALUES(link_facebook), seg_facebook=VALUES(seg_facebook), link_x=VALUES(link_x), seg_x=VALUES(seg_x), 
                link_instagram=VALUES(link_instagram), seg_instagram=VALUES(seg_instagram), link_otro=VALUES(link_otro), seg_otro=VALUES(seg_otro),
                foda_fortalezas=VALUES(foda_fortalezas), foda_debilidades=VALUES(foda_debilidades), foda_oportunidades=VALUES(foda_oportunidades), foda_amenazas=VALUES(foda_amenazas),
                problema_1=VALUES(problema_1), problema_2=VALUES(problema_2), problema_3=VALUES(problema_3), 
                kpi_1=VALUES(kpi_1), kpi_2=VALUES(kpi_2), kpi_3=VALUES(kpi_3), links_adjuntos=VALUES(links_adjuntos)";
            
            $stmtDiag = $db->prepare($sqlDiag);
            $stmtDiag->execute([
                ':estado' => $estado_req,
                ':responsable' => $data['responsable'] ?? null,
                ':cargo' => $data['cargo'] ?? null,
                ':tel' => $data['tel'] ?? null,
                ':mail' => $data['mail'] ?? null,
                ':contacto_otro' => $data['contacto_otro'] ?? null,
                ':mun_totales' => $data['mun_totales'] ?: 0,
                ':mun_presencia' => $data['mun_presencia'] ?: 0,
                ':afil_registrados' => $data['afil_registrados'] ?: 0,
                ':afil_activos' => $data['afil_activos'] ?: 0,
                ':promo_registrados' => $data['promo_registrados'] ?: 0,
                ':promo_confirmados' => $data['promo_confirmados'] ?: 0,
                ':presupuesto' => $data['presupuesto'] ?? null,
                ':ofi_municipal' => $data['ofi_municipal'] ?: 0,
                ':ofi_regional' => $data['ofi_regional'] ?: 0,
                ':ofi_estatal' => $data['ofi_estatal'] ?: 0,
                ':link_facebook' => $data['link_facebook'] ?? null,
                ':seg_facebook' => $data['seg_facebook'] ?: 0,
                ':link_x' => $data['link_x'] ?? null,
                ':seg_x' => $data['seg_x'] ?: 0,
                ':link_instagram' => $data['link_instagram'] ?? null,
                ':seg_instagram' => $data['seg_instagram'] ?: 0,
                ':link_otro' => $data['link_otro'] ?? null,
                ':seg_otro' => $data['seg_otro'] ?: 0,
                ':foda_fortalezas' => $data['foda_fortalezas'] ?? null,
                ':foda_debilidades' => $data['foda_debilidades'] ?? null,
                ':foda_oportunidades' => $data['foda_oportunidades'] ?? null,
                ':foda_amenazas' => $data['foda_amenazas'] ?? null,
                ':problema_1' => $data['problema_1'] ?? null,
                ':problema_2' => $data['problema_2'] ?? null,
                ':problema_3' => $data['problema_3'] ?? null,
                ':kpi_1' => $data['kpi_1'] ?? null,
                ':kpi_2' => $data['kpi_2'] ?? null,
                ':kpi_3' => $data['kpi_3'] ?? null,
                ':links_adjuntos' => $data['links_adjuntos'] ?? null
            ]);

            // 2. Guardar Datos Electorales por Año
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
                    ':estado' => $estado_req,
                    ':anio' => $anio,
                    ':lista_nominal' => $data["lista_nominal_$anio"] ?: 0,
                    ':participacion' => $data["participacion_$anio"] ?: 0.00,
                    ':votos_est' => $data["votos_est_$anio"] ?: 0,
                    ':porc_est' => $data["porc_est_$anio"] ?: 0.00,
                    ':votos_fed' => $data["votos_fed_$anio"] ?: 0,
                    ':porc_fed' => $data["porc_fed_$anio"] ?: 0.00,
                    ':encuesta_fuente' => $data["encuesta_fuente_$anio"] ?? null,
                    ':encuesta_fecha' => !empty($data["encuesta_fecha_$anio"]) ? $data["encuesta_fecha_$anio"] : null,
                    ':encuesta_intencion' => $data["encuesta_intencion_$anio"] ?: 0.00,
                    ':encuesta_link' => $data["encuesta_link_$anio"] ?? null
                ]);
            }

            // 3. Subida de Archivos
            if (isset($_FILES['documentos']) && !empty($_FILES['documentos']['name'][0])) {
                $uploadDir = __DIR__ . '/../../uploads/diagnostico/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

                $stmtArchivo = $db->prepare("INSERT INTO geo_diag_archivos (estado, nombre_original, nombre_servidor, ruta_archivo) VALUES (?, ?, ?, ?)");

                foreach ($_FILES['documentos']['name'] as $key => $name) {
                    if ($_FILES['documentos']['error'][$key] === UPLOAD_ERR_OK) {
                        $tmpName = $_FILES['documentos']['tmp_name'][$key];
                        $ext = pathinfo($name, PATHINFO_EXTENSION);
                        $servidorNombre = uniqid('diag_' . $estado_req . '_') . '.' . $ext;
                        if (move_uploaded_file($tmpName, $uploadDir . $servidorNombre)) {
                            $stmtArchivo->execute([$estado_req, $name, $servidorNombre, '/uploads/diagnostico/' . $servidorNombre]);
                        }
                    }
                }
            }

            $db->commit();
            echo json_encode(['success' => true, 'message' => 'Diagnóstico guardado con éxito.']);
            exit;

        } catch (Exception $ex) {
            $db->rollBack();
            throw $ex;
        }
    }

    // ==================================================================
    // LÓGICA CRUD ESTÁNDAR (OBJETIVOS, METAS, ESTRATEGIAS, CRONOGRAMA)
    // ==================================================================
    $tablas = [
        'objetivo'      => 'geo_objetivos',
        'estrategia'    => 'geo_estrategias',
        'meta'          => 'geo_metas',
        'cronograma'    => 'geo_cronograma',
        'vision_mision' => 'geo_vision_mision' 
    ];

    if (!array_key_exists($tipo, $tablas)) {
        throw new Exception("Tipo de dato no válido: " . htmlspecialchars($tipo));
    }
    
    $tabla = $tablas[$tipo];

    // --- A. CREAR ---
    if ($accion === 'crear') {
        if ($tipo === 'vision_mision') {
            $vision = trim($data['vision'] ?? '');
            $mision = trim($data['mision'] ?? '');
            $stmt = $db->prepare("INSERT INTO geo_vision_mision (estado, vision, mision) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE vision = ?, mision = ?");
            $stmt->execute([$estado_req, $vision, $mision, $vision, $mision]);
            echo json_encode(['success' => true]);
            exit;
        }

        $titulo = trim($data['titulo'] ?? '');
        $descripcion = trim($data['descripcion'] ?? '');
        if ($tipo === 'meta' && empty($titulo) && !empty($descripcion)) $titulo = $descripcion;
        if (empty($titulo)) throw new Exception("El campo título es obligatorio.");

        if ($tipo === 'cronograma') {
            $fecha_ord = $data['fecha_orden'] ?? date('Y-m-d');
            $fecha_vis = $data['fecha_visual'] ?? '';
            $stmt = $db->prepare("INSERT INTO geo_cronograma (estado, titulo, descripcion, fecha_orden, fecha_visual, activo) VALUES (?, ?, ?, ?, ?, 1)");
            $stmt->execute([$estado_req, $titulo, $descripcion, $fecha_ord, $fecha_vis]);
        } elseif ($tipo === 'meta') {
            $stmt = $db->prepare("INSERT INTO geo_metas (estado, titulo, orden, activo) VALUES (?, ?, 0, 1)");
            $stmt->execute([$estado_req, $titulo]);
        } else {
            $stmt = $db->prepare("INSERT INTO $tabla (estado, titulo, descripcion, activo) VALUES (?, ?, ?, 1)");
            $stmt->execute([$estado_req, $titulo, $descripcion]);
        }
        echo json_encode(['success' => true]);
    }

    // --- B. EDITAR ---
    elseif ($accion === 'editar') {
        $titulo = trim($data['titulo'] ?? '');
        $descripcion = trim($data['descripcion'] ?? '');
        if ($tipo === 'meta' && empty($titulo)) $titulo = $descripcion;

        if ($tipo === 'cronograma') {
            $stmt = $db->prepare("UPDATE geo_cronograma SET titulo = ?, descripcion = ?, fecha_visual = ?, fecha_orden = ? WHERE id = ?");
            $stmt->execute([$titulo, $descripcion, $data['fecha_visual'], $data['fecha_orden'], $id]);
        } elseif ($tipo === 'meta') {
            $stmt = $db->prepare("UPDATE geo_metas SET titulo = ? WHERE id = ?");
            $stmt->execute([$titulo, $id]); 
        } else {
            $stmt = $db->prepare("UPDATE $tabla SET titulo = ?, descripcion = ? WHERE id = ?");
            $stmt->execute([$titulo, $descripcion, $id]);
        }
        echo json_encode(['success' => true]);
    }

    // --- C. ELIMINAR ---
    elseif ($accion === 'eliminar') {
        $stmt = $db->prepare("DELETE FROM $tabla WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
    }

} catch (Exception $e) {
    http_response_code(400); 
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}