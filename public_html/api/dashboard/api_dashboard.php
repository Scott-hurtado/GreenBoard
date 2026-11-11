<?php
// api_dashboard.php
require_once __DIR__ . '/../../includes/bootstrap.php';
header('Content-Type: application/json');

$rol = $_SESSION['user_role'] ?? '';
$user_id = $_SESSION['user_id'] ?? null;

// 1. Autorizar el rol de zona
$permitidos = ['admin', 'administrador', 'superadmin', 'super admin', 'circunscripcion', 'coordinador_estatal', 'coordinador_zona', 'coordinador_regional'];

if (!in_array($rol, $permitidos)) {
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

try {
    // 1. CONEXIÓN A BASE DE DATOS
    $dbConfigPath = __DIR__ . '/../../includes/database.php';
    if (file_exists($dbConfigPath)) {
        $cfg = require $dbConfigPath;
    } else {
        $cfg = ['host' => 'localhost', 'database' => 'u881824383_pverde', 'username' => 'u881824383_proyverde', 'password' => 'PProyectoverde8#', 'charset' => 'utf8mb4'];
    }
    $pdo = new PDO("mysql:host={$cfg['host']};dbname={$cfg['database']};charset={$cfg['charset']}", $cfg['username'], $cfg['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

    // 2. PARÁMETROS DE LA PETICIÓN
    $tipo_visor = $_GET['tipo_visor'] ?? '';
    $context    = $_GET['context'] ?? ($tipo_visor === 'zona' ? 'zona' : 'circunscripcion'); 
    $level      = $_GET['level'] ?? 'resumen'; 
    $id_filtro  = $_GET['id'] ?? '';
    $mesParam   = $_GET['mes'] ?? date('m');
    $semana     = $_GET['semana'] ?? 'todas';
    $anio       = (int)($_GET['anio'] ?? date('Y'));
    $todoElAnio = ($mesParam === 'todas' || $mesParam === '');
    $mes        = $todoElAnio ? 0 : (int)$mesParam;

    // 3. SEGURIDAD Y FILTROS TERRITORIALES
    $estados_permitidos = [];
    $estado_coordinador = null;
    $zona_coordinador = null;
    $zona_coordinador_nombre = '';

    if ($rol === 'coordinador_estatal') {
        $stmt = $pdo->prepare("SELECT estado FROM coordinador_estatal WHERE id = ?");
        $stmt->execute([$user_id]);
        $estado_coordinador = $stmt->fetchColumn();
        if (!$estado_coordinador) throw new Exception('Coordinador sin estado asignado');
        $estados_permitidos[] = $estado_coordinador;
        $context = 'estatal'; 
    } elseif ($rol === 'coordinador_regional') {
        // El coordinador regional recibe estado via GET (session no tiene DB lookup propio)
        $estado_coordinador = $_GET['estado'] ?? '';
        if (empty($estado_coordinador)) throw new Exception('Estado requerido para coordinador_regional');
        $estados_permitidos[] = $estado_coordinador;
        $context = 'regional';
    } elseif ($rol === 'coordinador_zona') {
        // Los coordinadores de zona viven en coordinador_zona, no en usuarios
        $stmt = $pdo->prepare("
            SELECT cz.zona AS zona_id, cz.estado
            FROM coordinador_zona cz
            WHERE cz.id = ? LIMIT 1
        ");
        $stmt->execute([$user_id]);
        $res = $stmt->fetch();
        if (!$res || empty($res['zona_id'])) throw new Exception('Coordinador de zona sin zona asignada');
        $estado_coordinador = $res['estado'];
        $zona_coordinador   = $res['zona_id']; // ID numérico de zonas_estatales
        $estados_permitidos[] = $estado_coordinador;
        $context = 'zona';

        // FIX: agenda_eventos.zona se guarda como NOMBRE ("Z1.1 Zona 1") cuando
        // el propio coordinador de zona crea el evento (agenda_php_zn.php manda
        // el nombre en el campo, no el ID). Resolvemos también el nombre para
        // poder buscar por ambos formatos y no perder esos eventos en los KPIs.
        $stZN = $pdo->prepare("SELECT nombre FROM zonas_estatales WHERE id = ? LIMIT 1");
        $stZN->execute([$zona_coordinador]);
        $zona_coordinador_nombre = (string)($stZN->fetchColumn() ?: '');
    } elseif ($rol === 'circunscripcion') {
        $user_circ = $_SESSION['user_circunscripcion'] ?? '';
        $CIRCS = [
            'Circunscripción 1' => ['Baja California','Baja California Sur','Chihuahua','Durango','Jalisco','Nayarit','Sinaloa','Sonora'],
            'Circunscripción 2' => ['Aguascalientes','Coahuila','Guanajuato','Nuevo León','San Luis Potosí','Tamaulipas','Zacatecas'],
            'Circunscripción 3' => ['Campeche','Chiapas','Oaxaca','Quintana Roo','Tabasco','Veracruz','Yucatán'],
            'Circunscripción 4' => ['Ciudad de México','Guerrero','Hidalgo','Morelos','Puebla','Tlaxcala'],
            'Circunscripción 5' => ['Colima','Estado de México','Michoacán','Querétaro'],
        ];
        $estados_permitidos = $CIRCS[$user_circ] ?? [];
        if (empty($estados_permitidos)) throw new Exception('Circunscripción no configurada');
    }

    // 4. CONSTRUCCIÓN DE CONSULTAS DINÁMICAS
    $whereAg  = "WHERE activo = 1";
    $whereAct = "WHERE activo = 1";
    $params   = [];
    
    $groupByAg = "estado"; 
    $groupByAct = "estado";

    // ── LÓGICA PARA ESTATAL ──
    if ($context === 'estatal') {
        $whereAg  .= " AND estado = :est_coord";
        $whereAct .= " AND estado = :est_coord";
        $params[':est_coord'] = $estado_coordinador;

        if ($level === 'region') {
            // Se arregló el bug validando que no busque la región 'todas'
            if (!empty($id_filtro) && $id_filtro !== 'todas') {
                $whereAg  .= " AND region = :filtro_id";
                $whereAct .= " AND municipio IN (SELECT DISTINCT municipio FROM agenda_eventos WHERE region = :filtro_id AND estado = :est_coord AND municipio != '' AND municipio IS NOT NULL)"; 
                $params[':filtro_id'] = $id_filtro;
                $groupByAg = "zona"; 
                $groupByAct = "COALESCE((SELECT zona FROM agenda_eventos ae WHERE ae.seccion = activaciones.seccion_electoral AND ae.estado = activaciones.estado LIMIT 1), 'Sin Zona Asignada')";
            } else {
                $groupByAg = "region";
                $groupByAct = "COALESCE((SELECT region FROM agenda_eventos ae WHERE ae.seccion = activaciones.seccion_electoral AND ae.estado = activaciones.estado LIMIT 1), 'Sin Región Asignada')";
            }
        } 
        elseif ($level === 'zona') {
            // Se arregló el bug validando que no busque la zona 'todas'
            if (!empty($id_filtro) && $id_filtro !== 'todas') {
                $whereAg  .= " AND zona = :filtro_id";
                $whereAct .= " AND municipio IN (SELECT DISTINCT municipio FROM agenda_eventos WHERE zona = :filtro_id AND estado = :est_coord AND municipio != '' AND municipio IS NOT NULL)";
                $params[':filtro_id'] = $id_filtro;
                $groupByAg = "seccion";
                $groupByAct = "seccion_electoral";
            } else {
                $groupByAg = "zona";
                $groupByAct = "COALESCE((SELECT zona FROM agenda_eventos ae WHERE ae.seccion = activaciones.seccion_electoral AND ae.estado = activaciones.estado LIMIT 1), 'Sin Zona Asignada')";
            }
        } 
        else {
            $groupByAg = "municipio"; 
            $groupByAct = "municipio"; 
        }
    } 
    // ── LÓGICA PARA ZONA ──
    elseif ($context === 'zona') {
        // FIX: aceptar tanto el ID numérico como el nombre de zona, porque
        // agenda_eventos.zona puede tener cualquiera de los dos según quién
        // creó el evento (ver resolución de $zona_coordinador_nombre arriba).
        // Para activaciones se revisa primero su propio campo `zona` (ya se
        // guarda correctamente desde procesar.php) y, como respaldo para
        // registros antiguos que quedaron sin zona, se conserva la búsqueda
        // por sección coincidente en agenda_eventos.
        if (!empty($zona_coordinador_nombre)) {
            $whereAg  .= " AND (zona = :zona_coord OR zona = :zona_coord_nombre)";
            $whereAct .= " AND (zona = :zona_coord OR zona = :zona_coord_nombre
                                 OR seccion_electoral IN (SELECT seccion FROM agenda_eventos WHERE (zona = :zona_coord OR zona = :zona_coord_nombre)))";
            $params[':zona_coord']        = $zona_coordinador;
            $params[':zona_coord_nombre'] = $zona_coordinador_nombre;
        } else {
            $whereAg  .= " AND zona = :zona_coord";
            $whereAct .= " AND (zona = :zona_coord
                                 OR seccion_electoral IN (SELECT seccion FROM agenda_eventos WHERE zona = :zona_coord))";
            $params[':zona_coord'] = $zona_coordinador;
        }

        if ($level === 'detalle' && !empty($id_filtro) && $id_filtro !== 'GLOBAL_ZONA' && $id_filtro !== 'todas') {
            $whereAg  .= " AND seccion = :filtro_id";
            $whereAct .= " AND seccion_electoral = :filtro_id";
            $params[':filtro_id'] = $id_filtro;
        }
        
        $groupByAg = "seccion";
        $groupByAct = "seccion_electoral";
    } 
    // ── LÓGICA PARA REGIONAL ──
    elseif ($context === 'regional') {
        $estado_r = $_GET['estado'] ?? $estado_coordinador ?? '';
        $whereAg  .= " AND estado = :est_r";
        $whereAct .= " AND estado = :est_r";
        $params[':est_r'] = $estado_r;

        if ($level === 'region' && !empty($id_filtro) && $id_filtro !== 'todas') {
            $whereAg  .= " AND region = :filtro_id";
            // FIX: antes se buscaba el municipio vía una subquery contra
            // agenda_eventos, lo que dejaba fuera cualquier activación cuyo
            // municipio no tuviera ademas algún evento de agenda registrado
            // para esa región (por ejemplo, activaciones creadas por Zona en
            // un municipio sin eventos previos). activaciones.region ya se
            // guarda directamente desde procesar.php (igual que en el
            // contexto de zona más arriba), así que filtramos por ahí.
            $whereAct .= " AND region = :filtro_id";
            $params[':filtro_id'] = $id_filtro;
            $groupByAg  = "zona";
            $groupByAct = "municipio";
        } elseif ($level === 'zona' && !empty($id_filtro) && $id_filtro !== 'todas') {
            // El id_filtro puede ser ID numérico (desde el select del dashboard CR)
            // o nombre de zona (desde otros contextos). Resolver siempre a nombre.
            $zona_nombre_filtro = $id_filtro;
            if (is_numeric($id_filtro)) {
                $stZN = $pdo->prepare("SELECT nombre, secciones FROM zonas_estatales WHERE id = ? LIMIT 1");
                $stZN->execute([$id_filtro]);
                $rowZN = $stZN->fetch(PDO::FETCH_ASSOC);
                if ($rowZN) {
                    $zona_nombre_filtro = $rowZN['nombre'];
                    // Filtrar también por secciones de la zona para capturar eventos
                    // del CR que no tengan el campo zona guardado
                    $secs_zona = json_decode($rowZN['secciones'] ?? '[]', true) ?: [];
                }
            }
            $params[':filtro_id'] = $zona_nombre_filtro;

            // FIX: mismo criterio que arriba — activaciones.zona y
            // activaciones.seccion_electoral ya son campos propios y
            // confiables (los llena procesar.php), no hace falta inferirlos
            // por coincidencia de municipio contra agenda_eventos.
            if (!empty($secs_zona)) {
                $secs_in = implode(',', array_map('intval', $secs_zona));
                $whereAg  .= " AND (zona = :filtro_id OR seccion IN ($secs_in))";
                $whereAct .= " AND (zona = :filtro_id OR seccion_electoral IN ($secs_in))";
            } else {
                $whereAg  .= " AND zona = :filtro_id";
                $whereAct .= " AND zona = :filtro_id";
            }
            $groupByAg  = "seccion";
            $groupByAct = "municipio";
        } else {
            // Blindaje: si no se pudo identificar una región/zona válida
            // (id vacío o sin match), NO se debe mostrar el estado completo.
            // Es preferible mostrar 0 resultados a filtrar de más y exponer
            // datos de otras regiones al coordinador regional.
            $whereAg  .= " AND 1=0";
            $whereAct .= " AND 1=0";
            $groupByAg  = "zona";
            $groupByAct = "municipio";
        }
    }
    // ── LÓGICA PARA CIRCUNSCRIPCIÓN ──
    else {
        if ($level === 'detalle' && !empty($id_filtro)) {
            if (!empty($estados_permitidos) && !in_array($id_filtro, $estados_permitidos)) {
                throw new Exception('No tienes permiso para ver este estado');
            }
            $whereAg  .= " AND estado = :est";
            $whereAct .= " AND estado = :est";
            $params[':est'] = $id_filtro;
            $groupByAg = "municipio";
            $groupByAct = "municipio";
        } elseif (!empty($estados_permitidos)) {
            $estadosList = "'" . implode("','", $estados_permitidos) . "'";
            $whereAg  .= " AND estado IN ($estadosList)";
            $whereAct .= " AND estado IN ($estadosList)";
            $groupByAg = "estado";
            $groupByAct = "estado";
        }
    }

    // Filtro de Tiempo — el año siempre aplica
    $whereAg  .= " AND YEAR(fecha) = :anio";
    $whereAct .= " AND YEAR(fecha_actividad) = :anio";
    $params[':anio'] = $anio;

    if (!$todoElAnio) {
        $whereAg  .= " AND MONTH(fecha) = :mes";
        $whereAct .= " AND MONTH(fecha_actividad) = :mes";
        $params[':mes'] = $mes;

        if ($semana !== 'todas') {
            $whereAg  .= " AND WEEK(fecha, 1) = :sem";
            $whereAct .= " AND WEEK(fecha_actividad, 1) = :sem";
            $params[':sem'] = $semana;
        }
    }

    // ── OBTENER META REAL DE LA BASE DE DATOS ──
    $target_meta = null;
    if ($level === 'estatal' || empty($id_filtro) || $id_filtro === 'todas') {
        $target_meta = $estado_coordinador; 
    } else {
        $target_meta = $id_filtro; 
    }

    $meta_final = 100; $meta_voto_final = 0; $meta_com_final = 0; $meta_org_final = 0;

    if ($target_meta) {
        $stMeta = $pdo->prepare("SELECT meta, meta_voto, meta_com, meta_org FROM metas_estados WHERE estado = ? LIMIT 1");
        $stMeta->execute([$target_meta]);
        $meta_row = $stMeta->fetch(PDO::FETCH_ASSOC);
        
        if ($meta_row) {
            $meta_final      = (int)$meta_row['meta'];
            $meta_voto_final = (int)$meta_row['meta_voto'];
            $meta_com_final  = (int)$meta_row['meta_com'];
            $meta_org_final  = (int)$meta_row['meta_org'];
        }
    }

    // 5. ESTRUCTURA DE RESPUESTA JSON
    $response = [
        'kpis' => ['eventos'=>0, 'activaciones'=>0, 'contactos'=>0, 'inversion'=>0, 'asistentes'=>0, 'cumplimiento'=>0],
        'charts' => ['ejes' => [], 'status' => [], 'tipos' => [], 'tendencia' => []],
        'table' => [],
        'meta_global'      => $meta_final,
        'meta_voto'        => $meta_voto_final,
        'meta_com'         => $meta_com_final,
        'meta_org'         => $meta_org_final
    ];

    // B) KPIS DE AGENDA Y EJES (Eventos Programados)
    $sqlAg = "SELECT eje, COUNT(*) as total FROM agenda_eventos $whereAg GROUP BY eje";
    $stAg = $pdo->prepare($sqlAg);
    $stAg->execute($params);
    foreach ($stAg->fetchAll() as $r) {
        $eje_bruto = strtolower($r['eje'] ?: 'voto');
        if (strpos($eje_bruto, 'voto') !== false || strpos($eje_bruto, 'territorio') !== false) $eje_key = 'voto';
        else if (strpos($eje_bruto, 'comunic') !== false) $eje_key = 'comunicacion';
        else if (strpos($eje_bruto, 'org') !== false) $eje_key = 'organizacion';
        else $eje_key = $eje_bruto;

        $response['charts']['ejes'][$eje_key] = ($response['charts']['ejes'][$eje_key] ?? 0) + (int)$r['total'];
        $response['kpis']['eventos'] += (int)$r['total'];
    }

    // C) KPIS DE ACTIVACIONES (Ejecutadas)
    $sqlAct = "SELECT estatus, tipo, COUNT(*) as qty, SUM(contactos_estimados) as cont, SUM(asistentes_estimados) as asist, SUM(costo) as inv 
               FROM activaciones $whereAct GROUP BY estatus, tipo";
    $stAct = $pdo->prepare($sqlAct);
    $stAct->execute($params);
    foreach ($stAct->fetchAll() as $r) {
        $response['kpis']['activaciones'] += (int)$r['qty'];
        $response['kpis']['contactos']    += (int)$r['cont'];
        $response['kpis']['inversion']    += (float)$r['inv'];
        $response['kpis']['asistentes']   += (int)$r['asist'];
        
        $estatus_key = strtolower($r['estatus'] ?: 'programada');
        $tipo_key    = strtolower($r['tipo'] ?: 'territorial');
        
        $response['charts']['status'][$estatus_key] = ($response['charts']['status'][$estatus_key] ?? 0) + (int)$r['qty'];
        $response['charts']['tipos'][$tipo_key]     = ($response['charts']['tipos'][$tipo_key] ?? 0) + (int)$r['qty'];
    }

    // Cálculo Global de Cumplimiento
    if ($response['kpis']['eventos'] > 0) {
        $completadas = $response['charts']['status']['completada'] ?? 0;
        $response['kpis']['cumplimiento'] = round(($completadas / $response['kpis']['eventos']) * 100);
    }

    // D) TENDENCIA (Últimas 4 semanas, dentro del año seleccionado)
    // Si el año elegido es el actual, la referencia es "hoy"; si es otro año, se toma el 31-dic de ese año.
    $refTrend = ($anio === (int)date('Y')) ? 'CURDATE()' : "'" . $anio . "-12-31'";
    $whereTrend = "WHERE activo = 1 AND YEAR(fecha) = " . (int)$anio . " AND fecha >= DATE_SUB($refTrend, INTERVAL 4 WEEK)";
    if ($context === 'estatal') {
        $whereTrend .= " AND estado = '" . $estado_coordinador . "'";
    } elseif ($context === 'zona') {
        $zn = addslashes((string)$zona_coordinador);
        if (!empty($zona_coordinador_nombre)) {
            $znn = addslashes($zona_coordinador_nombre);
            $whereTrend .= " AND (zona = '$zn' OR zona = '$znn')";
        } else {
            $whereTrend .= " AND zona = '$zn'";
        }
    } elseif (!empty($estados_permitidos)) {
        $estadosList = "'" . implode("','", $estados_permitidos) . "'";
        $whereTrend .= " AND estado IN ($estadosList)";
    }
    
    $sqlTrend = "SELECT eje, WEEK(fecha, 1) as sem, COUNT(*) as total 
                 FROM agenda_eventos $whereTrend 
                 GROUP BY eje, sem ORDER BY sem ASC";
    $stTrend = $pdo->query($sqlTrend);
    $response['charts']['tendencia'] = $stTrend->fetchAll();

    // E) DATOS PARA LA TABLA
    // Usamos HAVING para que SQL no de errores con subconsultas ni valores nulos y permita la visualización
    $sqlTablaAg = "SELECT $groupByAg as nombre, COUNT(*) as eventos_prog 
                   FROM agenda_eventos $whereAg 
                   GROUP BY nombre 
                   HAVING nombre IS NOT NULL AND nombre != ''";
    $stTAg = $pdo->prepare($sqlTablaAg);
    $stTAg->execute($params);
    $tablaAgenda = $stTAg->fetchAll(PDO::FETCH_KEY_PAIR);

    $sqlTablaAct = "SELECT $groupByAct as nombre, COUNT(*) as acts, SUM(contactos_estimados) as cont, SUM(asistentes_estimados) as asist, SUM(costo) as inv 
                    FROM activaciones $whereAct 
                    GROUP BY nombre 
                    HAVING nombre IS NOT NULL AND nombre != ''";
    $stTAct = $pdo->prepare($sqlTablaAct);
    $stTAct->execute($params);
    
    foreach ($stTAct->fetchAll() as $row) {
        $nombre = $row['nombre'];
        $evts_programados = $tablaAgenda[$nombre] ?? 1; 
        $cumplimiento = min(100, round(($row['acts'] / $evts_programados) * 100));

        $response['table'][] = [
            'nombre'       => $nombre,
            'eventos'      => $evts_programados,
            'activaciones' => (int)$row['acts'],
            'contactos'    => (int)$row['cont'],
            'asistentes'   => (int)$row['asist'],
            'costo'        => (float)$row['inv'],
            'cumplimiento' => $cumplimiento,
            'sub_entidades'=> '-'
        ];
    }
    
    echo json_encode($response);

} catch (Exception $e) { 
    echo json_encode(['error' => $e->getMessage()]); 
}