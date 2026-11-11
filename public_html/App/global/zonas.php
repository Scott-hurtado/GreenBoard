<?php
/* ════════════════════════════════════════════════════════════════
   global/zonas.php — Estructura Territorial Unificada
   Niveles 1-3 (Admin/Circ/Estatal): CRUD 3 columnas
   Nivel 4 (Regional):               Cards de zonas, solo lectura
   Nivel 5 (Zona):                   Workspace 3 col, solo lectura
════════════════════════════════════════════════════════════════ */
if (file_exists(__DIR__ . '/../../includes/bootstrap.php')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
} else { session_start(); }

$rol = strtolower($_SESSION['user_role'] ?? '');

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
    'regional'                      => 4,
    'coordinador_zona'              => 5,
];

$mi_nivel = $jerarquia_roles[$rol] ?? 6;

$permitidos = ['admin','administrador','superadmin','super admin',
               'circunscripcion','coordinador_estatal',
               'coordinador_regional','regional','coordinador_zona'];
if (!in_array($rol, $permitidos)) { header('Location: /public/index.php'); exit(); }

// Vars de sesión
$user_circ   = $_SESSION['user_circunscripcion'] ?? 'Circunscripción 1';
$user_estado = $_SESSION['user_estado']          ?? 'Sonora';
$user_region = $_SESSION['user_region']          ?? '';
$user_zona   = $_SESSION['user_zona_nombre']     ?? 'Zona Asignada';

// ── DICCIONARIO CIRCUNSCRIPCIONES ─────────────────────────────────
$CIRCS_DISPLAY = [
    'Circunscripción 1' => ['Baja California','Baja California Sur','Chihuahua','Durango','Jalisco','Nayarit','Sinaloa','Sonora'],
    'Circunscripción 2' => ['Aguascalientes','Coahuila','Guanajuato','Nuevo León','San Luis Potosí','Tamaulipas','Zacatecas'],
    'Circunscripción 3' => ['Campeche','Chiapas','Oaxaca','Quintana Roo','Tabasco','Veracruz','Yucatán'],
    'Circunscripción 4' => ['Ciudad de México','Guerrero','Hidalgo','Morelos','Puebla','Tlaxcala'],
    'Circunscripción 5' => ['Colima','Estado de México','Michoacán','Querétaro'],
];
// BD normaliza acentos en algunos estados
$CIRCS_BD = [
    'Circunscripción 1' => ['Baja California','Baja California Sur','Chihuahua','Durango','Jalisco','Nayarit','Sinaloa','Sonora'],
    'Circunscripción 2' => ['Aguascalientes','Coahuila','Guanajuato','Nuevo Leon','San Luis Potosi','Tamaulipas','Zacatecas'],
    'Circunscripción 3' => ['Campeche','Chiapas','Oaxaca','Quintana Roo','Tabasco','Veracruz','Yucatan'],
    'Circunscripción 4' => ['Ciudad de Mexico','Guerrero','Hidalgo','Morelos','Puebla','Tlaxcala'],
    'Circunscripción 5' => ['Colima','Estado de Mexico','Michoacan','Queretaro'],
];

// ── ALCANCE GEOGRÁFICO POR NIVEL ──────────────────────────────────
if ($mi_nivel === 1) {
    // Admin: todos los estados
    $estados_disponibles_display = array_merge(...array_values($CIRCS_DISPLAY));
    $estados_disponibles_bd      = array_merge(...array_values($CIRCS_BD));
    $primer_estado_display       = $CIRCS_DISPLAY['Circunscripción 1'][0];
    $primer_estado_bd            = $CIRCS_BD['Circunscripción 1'][0];
} elseif ($mi_nivel === 2) {
    // Circunscripción: solo sus estados
    $estados_disponibles_display = $CIRCS_DISPLAY[$user_circ] ?? [];
    $estados_disponibles_bd      = $CIRCS_BD[$user_circ]      ?? [];
    $primer_estado_display       = $estados_disponibles_display[0] ?? $user_estado;
    $primer_estado_bd            = $estados_disponibles_bd[0]      ?? $user_estado;
} elseif ($mi_nivel === 3) {
    // Estatal: solo su estado (fijo)
    $estados_disponibles_display = [$user_estado];
    $estados_disponibles_bd      = [$user_estado];
    $primer_estado_display       = $user_estado;
    $primer_estado_bd            = $user_estado;
}

// Mapa display→BD para JS
$display_to_bd = [];
if ($mi_nivel <= 3) {
    foreach ($estados_disponibles_display as $i => $disp) {
        $display_to_bd[$disp] = $estados_disponibles_bd[$i] ?? $disp;
    }
}

// ── CONEXIÓN ──────────────────────────────────────────────────────
try {
    if (!isset($db) && !isset($pdo)) {
        if (class_exists('Database')) {
            $db = Database::getInstance()->getConnection();
        } else {
            $dbCfg = __DIR__ . '/../../includes/database.php';
            $cfg = file_exists($dbCfg) ? require $dbCfg : [
                'host'=>'localhost','database'=>'u881824383_pverde',
                'username'=>'u881824383_proyverde','password'=>'PProyectoverde8#',
                'charset'=>'utf8mb4',
                'options'=>[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
            ];
            $dsn = "mysql:host={$cfg['host']};dbname={$cfg['database']};charset={$cfg['charset']}";
            $db = new PDO($dsn, $cfg['username'], $cfg['password'], $cfg['options'] ?? []);
        }
    }
    if (!isset($db)) $db = $pdo;
} catch (Exception $e) { error_log('zonas.php conn: ' . $e->getMessage()); }

/* ════════════════════════════════════════════════════════════════
   NIVEL 4 (REGIONAL): datos cargados en PHP
════════════════════════════════════════════════════════════════ */
if ($mi_nivel === 4) {
    $region_nombre = '—';
    $zonas_regional = [];
    try {
        if ($user_region) {
            $st = $db->prepare("SELECT nombre FROM regiones_estatales WHERE id=? LIMIT 1");
            $st->execute([$user_region]);
            $r = $st->fetch();
            if ($r) $region_nombre = ucfirst($r['nombre']);

            $st = $db->prepare("
                SELECT gz.id, gz.nombre, gz.clave, gz.foco, gz.colonias, gz.meta, gz.secciones,
                       COALESCE(gz.miembros,0) AS contactos
                FROM zonas_estatales gz
                WHERE gz.estructura_id = ?
                ORDER BY gz.nombre
            ");
            $st->execute([$user_region]);
            foreach ($st->fetchAll() as $z) {
                $secs = json_decode($z['secciones'] ?? '[]', true) ?: [];
                $z['num_secciones']  = count($secs);
                $z['secciones_list'] = implode(',', $secs);
                $z['meta_contactos'] = $z['meta'] > 0 ? $z['meta'] : 1;
                $zonas_regional[] = $z;
            }
        }
    } catch (PDOException $e) { $error_db = $e->getMessage(); }
}

/* ════════════════════════════════════════════════════════════════
   NIVEL 5 (ZONA): API interna async
════════════════════════════════════════════════════════════════ */
if ($mi_nivel === 5 && isset($_GET['api'])) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $stmtZ = $db->prepare("SELECT * FROM zonas_estatales WHERE TRIM(nombre) = TRIM(?) LIMIT 1");
        $stmtZ->execute([$user_zona]);
        $zonaInfo = $stmtZ->fetch(PDO::FETCH_ASSOC);

        $stmtS = $db->prepare("
            SELECT municipio, nombre_seccion, meta_contactos, contactos_conseguidos, padron_electoral
            FROM secciones_electorales
            WHERE TRIM(zona_asignada) = TRIM(?) AND activo = 1
            ORDER BY municipio ASC, CAST(nombre_seccion AS UNSIGNED) ASC
        ");
        $stmtS->execute([$user_zona]);
        $secciones = $stmtS->fetchAll(PDO::FETCH_ASSOC);

        $municipiosMap = [];
        $totalSec = $metaZona = $contZona = 0;
        foreach ($secciones as $sec) {
            $mun = $sec['municipio'] ?: 'Sin Municipio';
            if (!isset($municipiosMap[$mun])) {
                $municipiosMap[$mun] = ['nombre'=>$mun,'secciones'=>[],'meta'=>0,'contactos'=>0,'padron'=>0];
            }
            $municipiosMap[$mun]['secciones'][] = $sec;
            $municipiosMap[$mun]['meta']       += (int)$sec['meta_contactos'];
            $municipiosMap[$mun]['contactos']  += (int)$sec['contactos_conseguidos'];
            $municipiosMap[$mun]['padron']     += (int)$sec['padron_electoral'];
            $metaZona  += (int)$sec['meta_contactos'];
            $contZona  += (int)$sec['contactos_conseguidos'];
            $totalSec++;
        }
        ksort($municipiosMap);
        echo json_encode(['ok'=>true,'zona'=>[
            'nombre'            => $user_zona,
            'info'              => $zonaInfo,
            'total_secciones'   => $totalSec,
            'meta_total'        => $metaZona,
            'contactos_totales' => $contZona,
            'municipios'        => array_values($municipiosMap),
        ]]);
    } catch (Exception $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit();
}

/* ════════════════════════════════════════════════════════════════
   NIVELES 1-3: API CRUD interna
════════════════════════════════════════════════════════════════ */
if ($mi_nivel <= 3 && isset($_GET['api'])) {
    header('Content-Type: application/json; charset=utf-8');
    $accion = $_GET['api'] ?? '';
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];

    // Estado recibido (display). Convertir a BD.
    $estado_display = $_GET['estado'] ?? $body['estado'] ?? $primer_estado_display;
    $estado_req     = $display_to_bd[$estado_display] ?? $estado_display;

    // Circunscripción valida que el estado pertenezca a su circ
    if ($mi_nivel === 2 && !empty($estados_disponibles_bd) && !in_array($estado_req, $estados_disponibles_bd)) {
        echo json_encode(['ok'=>false,'msg'=>'Estado no permitido para esta circunscripción']);
        exit();
    }
    // Estatal solo puede operar sobre su estado
    if ($mi_nivel === 3) {
        $estado_req = $user_estado;
    }

    try {
        /* ── GET_CATALOGOS ── */
        if ($accion === 'get_catalogos') {
            $municipios_base = [];
            $stmt = $db->prepare("SELECT id FROM estados WHERE nombre = ? LIMIT 1");
            $stmt->execute([$estado_req]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $estado_id = $row['id'];
                $stmt = $db->prepare("SELECT id, nombre FROM municipios WHERE estado_id = ? ORDER BY nombre");
                $stmt->execute([$estado_id]);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $mun) {
                    $stmt2 = $db->prepare("SELECT seccion FROM secciones WHERE estado_id = ? AND municipio_id = ? ORDER BY CAST(seccion AS UNSIGNED)");
                    $stmt2->execute([$estado_id, $mun['id']]);
                    $secs = array_column($stmt2->fetchAll(PDO::FETCH_ASSOC), 'seccion');
                    if (!empty($secs)) $municipios_base[$mun['nombre']] = $secs;
                }
            }
            $stmtU = $db->prepare("
                SELECT id, TRIM(CONCAT(COALESCE(nombres,''),' ',COALESCE(ap_paterno,''),' ',COALESCE(ap_materno,''))) AS nombre, 'Regional' as rol
                FROM coordinador_regional WHERE estado=? AND estatus='activo'
                UNION ALL
                SELECT id, TRIM(CONCAT(COALESCE(nombres,''),' ',COALESCE(ap_paterno,''),' ',COALESCE(ap_materno,''))) AS nombre, 'Zona' as rol
                FROM coordinador_zona WHERE estado=? AND estatus='activo'
                UNION ALL
                SELECT id, nombre_completo AS nombre, rol
                FROM usuarios WHERE estado=? AND activo=1 AND rol IN ('regional','zona','coordinador','operador','coordinador_regional','coordinador_zona')
                ORDER BY nombre ASC
            ");
            $stmtU->execute([$estado_req, $estado_req, $estado_req]);
            $usuarios_estado = $stmtU->fetchAll(PDO::FETCH_ASSOC);

            // Todos los coordinadores activos (para mostrar nombre en tarjetas
            // aunque sean de otro estado o circunscripcion)
            $stmtAll = $db->prepare("
                SELECT id, TRIM(CONCAT(COALESCE(nombres,''),' ',COALESCE(ap_paterno,''),' ',COALESCE(ap_materno,''))) AS nombre, 'Regional' as rol
                FROM coordinador_regional WHERE activo=1
                UNION ALL
                SELECT id, TRIM(CONCAT(COALESCE(nombres,''),' ',COALESCE(ap_paterno,''),' ',COALESCE(ap_materno,''))) AS nombre, 'Zona' as rol
                FROM coordinador_zona WHERE activo=1
                ORDER BY nombre ASC
            ");
            $stmtAll->execute();
            $todos_coords = $stmtAll->fetchAll(PDO::FETCH_ASSOC);
            $seen = [];
            $usuarios_merged = [];
            foreach (array_merge($todos_coords, $usuarios_estado) as $u) {
                $key = $u['id'] . '_' . $u['rol'];
                if (!isset($seen[$key])) { $seen[$key] = true; $usuarios_merged[] = $u; }
            }
            echo json_encode(['ok'=>true,'municipios'=>$municipios_base,
                              'usuarios'=>$usuarios_merged,
                              'usuarios_estado'=>$usuarios_estado]);
            exit();
        }

        /* ── CARGAR ── */
        if ($accion === 'cargar') {
            $stmt = $db->prepare("SELECT * FROM regiones_estatales WHERE estado=? ORDER BY id ASC");
            $stmt->execute([$estado_req]);
            $estructuras = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($estructuras as &$est) {
                $est['id']         = (int)$est['id'];
                $est['meta']       = (int)$est['meta'];
                $est['municipios'] = json_decode($est['municipios'] ?? '[]', true) ?: [];
                $est['responsable']= $est['coordinador_id'] ?? null;
                $stmt2 = $db->prepare("SELECT * FROM zonas_estatales WHERE estructura_id=? ORDER BY id ASC");
                $stmt2->execute([$est['id']]);
                $zonas = $stmt2->fetchAll(PDO::FETCH_ASSOC);
                foreach ($zonas as &$z) {
                    $z['id']            = (int)$z['id'];
                    $z['estructura_id'] = (int)$z['estructura_id'];
                    $z['meta']          = (int)$z['meta'];
                    $z['miembros']      = (int)$z['miembros'];
                    $z['secciones']     = json_decode($z['secciones'] ?? '[]', true) ?: [];
                    $z['responsable']   = $z['coordinador_id'] ?? null;
                }
                unset($z);
                $est['zonas'] = $zonas;
            }
            unset($est);
            echo json_encode(['ok'=>true,'regiones'=>$estructuras]);
            exit();
        }

        /* ── GUARDAR REGIÓN ── */
        if ($accion === 'guardar_region') {
            $id         = isset($body['id']) ? (int)$body['id'] : 0;
            $nombre     = trim($body['nombre'] ?? '');
            $desc       = trim($body['desc'] ?? '');
            $meta       = (int)($body['meta'] ?? 0);
            $coord_id   = isset($body['responsable']) && $body['responsable'] !== '' ? (int)$body['responsable'] : null;
            $municipios = json_encode($body['municipios'] ?? [], JSON_UNESCAPED_UNICODE);
            if (!$nombre) { echo json_encode(['ok'=>false,'msg'=>'Nombre requerido']); exit(); }
            if ($id) {
                // Circ/Estatal: verificar que la región pertenezca al estado permitido
                if ($mi_nivel >= 2) {
                    $chk = $db->prepare("SELECT id FROM regiones_estatales WHERE id=? AND estado=?");
                    $chk->execute([$id, $estado_req]);
                    if (!$chk->fetch()) { echo json_encode(['ok'=>false,'msg'=>'Región no válida']); exit(); }
                }
                $db->prepare("UPDATE regiones_estatales SET nombre=?,descripcion=?,meta=?,municipios=?,coordinador_id=? WHERE id=? AND estado=?")
                   ->execute([$nombre,$desc,$meta,$municipios,$coord_id,$id,$estado_req]);
            } else {
                $db->prepare("INSERT INTO regiones_estatales (estado,nombre,descripcion,meta,municipios,coordinador_id) VALUES (?,?,?,?,?,?)")
                   ->execute([$estado_req,$nombre,$desc,$meta,$municipios,$coord_id]);
                $id = (int)$db->lastInsertId();
            }
            echo json_encode(['ok'=>true,'id'=>$id]); exit();
        }

        /* ── ELIMINAR REGIÓN ── */
        if ($accion === 'eliminar_region') {
            $id = (int)($body['id'] ?? 0);
            $db->prepare("DELETE FROM regiones_estatales WHERE id=? AND estado=?")->execute([$id,$estado_req]);
            echo json_encode(['ok'=>true]); exit();
        }

        /* ── GUARDAR ZONA ── */
        if ($accion === 'guardar_zona') {
            $id            = isset($body['id']) ? (int)$body['id'] : 0;
            $estructura_id = (int)($body['region_id'] ?? 0);
            $nombre        = trim($body['nombre'] ?? '');
            $clave         = trim($body['clave'] ?? '');
            $foco          = trim($body['foco'] ?? '');
            $colonias      = trim($body['colonias'] ?? '');
            $meta          = (int)($body['meta'] ?? 0);
            $coord_id      = isset($body['responsable']) && $body['responsable'] !== '' ? (int)$body['responsable'] : null;
            $secciones     = json_encode($body['secciones'] ?? [], JSON_UNESCAPED_UNICODE);
            if (!$nombre)        { echo json_encode(['ok'=>false,'msg'=>'Nombre requerido']); exit(); }
            if (!$estructura_id) { echo json_encode(['ok'=>false,'msg'=>'Región requerida']); exit(); }
            $chk = $db->prepare("SELECT id FROM regiones_estatales WHERE id=? AND estado=?");
            $chk->execute([$estructura_id,$estado_req]);
            if (!$chk->fetch()) { echo json_encode(['ok'=>false,'msg'=>'Región no válida']); exit(); }
            if ($id) {
                $db->prepare("UPDATE zonas_estatales SET nombre=?,clave=?,foco=?,colonias=?,meta=?,secciones=?,coordinador_id=? WHERE id=? AND estructura_id=?")
                   ->execute([$nombre,$clave,$foco,$colonias,$meta,$secciones,$coord_id,$id,$estructura_id]);
            } else {
                $db->prepare("INSERT INTO zonas_estatales (estructura_id,nombre,clave,foco,colonias,meta,secciones,coordinador_id) VALUES (?,?,?,?,?,?,?,?)")
                   ->execute([$estructura_id,$nombre,$clave,$foco,$colonias,$meta,$secciones,$coord_id]);
                $id = (int)$db->lastInsertId();
            }
            echo json_encode(['ok'=>true,'id'=>$id]); exit();
        }

        /* ── ELIMINAR ZONA ── */
        if ($accion === 'eliminar_zona') {
            $id = (int)($body['id'] ?? 0);
            $db->prepare("DELETE z FROM zonas_estatales z INNER JOIN regiones_estatales r ON r.id=z.estructura_id WHERE z.id=? AND r.estado=?")
               ->execute([$id,$estado_req]);
            echo json_encode(['ok'=>true]); exit();
        }

        /* ── GUARDAR SECCIONES ── */
        if ($accion === 'guardar_secciones') {
            $zona_id   = (int)($body['zona_id'] ?? 0);
            $secciones = json_encode($body['secciones'] ?? [], JSON_UNESCAPED_UNICODE);
            $chk = $db->prepare("SELECT z.id FROM zonas_estatales z INNER JOIN regiones_estatales r ON r.id=z.estructura_id WHERE z.id=? AND r.estado=?");
            $chk->execute([$zona_id,$estado_req]);
            if (!$chk->fetch()) { echo json_encode(['ok'=>false,'msg'=>'Zona no válida']); exit(); }
            $db->prepare("UPDATE zonas_estatales SET secciones=? WHERE id=?")->execute([$secciones,$zona_id]);
            echo json_encode(['ok'=>true]); exit();
        }

        echo json_encode(['ok'=>false,'msg'=>'Acción desconocida']);
    } catch (Exception $e) {
        error_log('zonas API error: ' . $e->getMessage());
        echo json_encode(['ok'=>false,'msg'=>'Error del servidor: ' . $e->getMessage()]);
    }
    exit();
}

/* ════════════════════════════════════════════════════════════════
   PRE-CARGA PHP para niveles 1-3: municipios iniciales del primer estado
════════════════════════════════════════════════════════════════ */
$municipios_precarga_json = '{}';
if ($mi_nivel <= 3 && isset($db)) {
    try {
        $est_bd = $primer_estado_bd ?? $user_estado;
        $stmt = $db->prepare("SELECT id FROM estados WHERE nombre=? LIMIT 1");
        $stmt->execute([$est_bd]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $mp = [];
            $stmt2 = $db->prepare("SELECT id, nombre FROM municipios WHERE estado_id=? ORDER BY nombre");
            $stmt2->execute([$row['id']]);
            foreach ($stmt2->fetchAll(PDO::FETCH_ASSOC) as $mun) {
                $stmt3 = $db->prepare("SELECT seccion FROM secciones WHERE estado_id=? AND municipio_id=? ORDER BY CAST(seccion AS UNSIGNED)");
                $stmt3->execute([$row['id'], $mun['id']]);
                $secs = array_column($stmt3->fetchAll(PDO::FETCH_ASSOC), 'seccion');
                if (!empty($secs)) $mp[$mun['nombre']] = $secs;
            }
            $municipios_precarga_json = json_encode($mp, JSON_UNESCAPED_UNICODE);
        }
    } catch (Exception $e) { /* no fatal */ }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estructura Territorial<?php if ($mi_nivel===4) echo ' — Región '.htmlspecialchars($region_nombre ?? ''); elseif ($mi_nivel===5) echo ' — '.htmlspecialchars($user_zona); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    :root {
        --primary:#2d7a3e; --primary-dark:#1f5a2d; --dark:#1a1a1a; --light:#f0f7f0;
        --white:#ffffff; --gray-100:#f8f9fa; --gray-200:#e9ecef; --text-muted:#6c757d;
        --shadow-sm:0 10px 30px rgba(0,0,0,0.05);
    }
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family:'Arial',sans-serif; background:var(--light); color:var(--dark); overflow-x:hidden; }

    .container { max-width:100%; margin:0; background:var(--white); min-height:100vh; position:relative; padding-left:330px; padding-right:50px; padding-bottom:60px; transition:padding-left 0.3s ease; }
    .design-text { position:fixed; left:290px; top:50%; writing-mode:vertical-rl; font-size:13px; font-weight:500; color:var(--primary); opacity:0.3; letter-spacing:2px; z-index:0; pointer-events:none; }

    .page-header { padding:50px 0 20px 0; position:relative; z-index:2; display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:20px; }
    .ai-badge { color:var(--primary); font-size:13px; font-weight:800; letter-spacing:4px; margin-bottom:15px; display:inline-block; text-transform:uppercase; background:rgba(45,122,62,0.1); padding:5px 15px; border-radius:20px; }
    .main-title { font-size:54px; font-weight:900; line-height:1; margin-bottom:15px; color:var(--dark); }
    .description { color:#666; font-size:16px; line-height:1.6; max-width:600px; }

    .btn-primary-action { background:var(--primary); color:white; border:none; padding:12px 25px; border-radius:12px; font-weight:700; cursor:pointer; transition:all 0.3s; display:inline-flex; align-items:center; gap:10px; font-size:14px; box-shadow:0 4px 15px rgba(45,122,62,0.3); font-family:Arial,sans-serif; }
    .btn-primary-action:hover { background:var(--primary-dark); transform:translateY(-2px); }
    .btn-secondary-action { background:var(--white); color:var(--text-muted); border:1px solid var(--gray-200); padding:10px 18px; border-radius:10px; font-weight:600; cursor:pointer; transition:0.3s; display:inline-flex; align-items:center; gap:8px; font-size:13px; font-family:Arial,sans-serif; }
    .btn-secondary-action:hover { border-color:var(--dark); color:var(--dark); }
    .btn-sm { padding:6px 14px; font-size:12px; border-radius:8px; }

    /* ── TABS (admin: circ+estados / circ: solo estados) ── */
    .tabs-bar { display:flex; gap:4px; flex-wrap:wrap; border-bottom:2px solid var(--gray-200); margin-bottom:15px; padding-bottom:0; }
    .tab-btn { padding:10px 20px; border:none; background:transparent; font-family:Arial,sans-serif; font-size:14px; font-weight:600; color:var(--text-muted); cursor:pointer; border-bottom:3px solid transparent; margin-bottom:-2px; transition:0.2s; border-radius:8px 8px 0 0; }
    .tab-btn:hover { color:var(--dark); background:var(--gray-100); }
    .tab-btn.active { color:var(--primary); border-bottom-color:var(--primary); background:var(--light); }
    .sub-tabs-bar { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:25px; padding:5px 0; }
    .sub-tab-btn { background:var(--white); border:1px solid var(--gray-200); padding:8px 16px; border-radius:20px; font-size:13px; font-weight:600; color:var(--text-muted); cursor:pointer; transition:all 0.2s; font-family:Arial,sans-serif; }
    .sub-tab-btn:hover { border-color:var(--primary); color:var(--primary); }
    .sub-tab-btn.active { background:var(--primary); color:white; border-color:var(--primary); box-shadow:0 4px 10px rgba(45,122,62,0.2); }

    /* ── WORKSPACE 3 COLUMNAS (niveles 1-3 y nivel 5) ── */
    .workspace { display:grid; grid-template-columns:280px 1fr 1fr; gap:0; border:1px solid var(--gray-200); border-radius:20px; overflow:hidden; box-shadow:var(--shadow-sm); min-height:600px; }
    .col-panel, .col-regiones, .col-zonas, .col-secciones { background:var(--white); border-right:1px solid var(--gray-200); display:flex; flex-direction:column; }
    .col-panel:last-child, .col-secciones { border-right:none; }
    .col-header { padding:18px 20px; border-bottom:1px solid var(--gray-200); display:flex; align-items:center; justify-content:space-between; background:var(--gray-100); }
    .col-header-title { font-size:14px; font-weight:800; color:var(--dark); display:flex; align-items:center; gap:8px; }
    .col-body { flex:1; overflow-y:auto; padding:12px; }
    .col-body::-webkit-scrollbar { width:4px; }
    .col-body::-webkit-scrollbar-thumb { background:#ddd; border-radius:4px; }

    /* ── CARDS (regiones/zonas en workspace + grid regional) ── */
    .region-card, .zona-card { background:var(--white); border:1px solid var(--gray-200); border-radius:14px; padding:16px 18px; margin-bottom:10px; cursor:pointer; transition:all 0.2s; position:relative; }
    .region-card:hover, .zona-card:hover { border-color:var(--primary); box-shadow:0 4px 14px rgba(45,122,62,0.1); }
    .region-card.active, .zona-card.active { border-color:var(--primary); background:rgba(45,122,62,0.04); box-shadow:0 4px 14px rgba(45,122,62,0.12); }
    .card-name { font-size:16px; font-weight:700; color:var(--dark); margin-bottom:6px; }
    .card-meta { display:flex; justify-content:space-between; align-items:center; font-size:13px; }
    .card-miembros { color:var(--text-muted); }
    .card-pct { color:var(--primary); font-weight:800; font-size:14px; }
    .card-bar-track { height:3px; background:var(--gray-200); border-radius:99px; margin-top:8px; overflow:hidden; }
    .card-bar-fill { height:100%; background:var(--primary); border-radius:99px; transition:width 0.4s; }
    .card-secciones-count { font-size:11px; color:var(--text-muted); margin-top:4px; }
    .card-meta-bottom { margin-top:4px; font-size:11px; color:var(--text-muted); font-style:italic; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .card-actions { position:absolute; top:10px; right:10px; display:flex; gap:4px; opacity:0; transition:0.2s; }
    .region-card:hover .card-actions, .zona-card:hover .card-actions { opacity:1; }
    .card-action-btn { background:var(--gray-100); border:1px solid var(--gray-200); border-radius:6px; padding:4px 8px; font-size:11px; cursor:pointer; color:var(--text-muted); transition:0.2s; font-family:Arial,sans-serif; }
    .card-action-btn:hover { background:var(--primary); color:white; border-color:var(--primary); }
    .card-action-btn.del:hover { background:#d32f2f; border-color:#d32f2f; color:white; }

    /* Grid de cards para nivel 4 (regional) */
    .zonas-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(380px,1fr)); gap:24px; margin-top:10px; }
    .zona-card-regional { background:var(--white); border:1px solid var(--gray-200); border-radius:20px; box-shadow:var(--shadow-sm); overflow:hidden; transition:all 0.3s; }
    .zona-card-regional:hover { transform:translateY(-4px); box-shadow:0 20px 40px rgba(45,122,62,0.12); border-color:var(--primary); }
    .zona-header { padding:22px 24px 18px; border-bottom:1px solid var(--gray-200); display:flex; justify-content:space-between; align-items:flex-start; }
    .zona-title { font-size:18px; font-weight:800; }
    .zona-clave { font-size:12px; color:var(--text-muted); margin-top:3px; }
    .zona-badge { background:rgba(45,122,62,0.1); color:var(--primary); padding:5px 12px; border-radius:20px; font-size:12px; font-weight:700; }
    .zona-body { padding:20px 24px; }
    .zona-stat-row { display:flex; gap:20px; margin-bottom:16px; }
    .zona-stat { flex:1; text-align:center; background:var(--gray-100); border-radius:12px; padding:14px 10px; }
    .zona-stat-val { font-size:22px; font-weight:900; color:var(--primary); }
    .zona-stat-lbl { font-size:11px; color:var(--text-muted); font-weight:600; text-transform:uppercase; margin-top:2px; }
    .progress-wrap { background:#f0f0f0; border-radius:8px; height:8px; overflow:hidden; margin-bottom:6px; }
    .progress-bar { height:100%; background:var(--primary); border-radius:8px; transition:width 0.5s; }
    .zona-detail-label { font-size:12px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:8px; margin-top:14px; }
    .secciones-chips { display:flex; flex-wrap:wrap; gap:6px; }
    .seccion-chip { background:var(--gray-100); border:1px solid var(--gray-200); border-radius:8px; padding:4px 10px; font-size:12px; font-weight:600; }

    /* ── SECCIONES ── */
    .list-scroll { flex:1; overflow-y:auto; }
    .list-scroll::-webkit-scrollbar { width:4px; }
    .list-scroll::-webkit-scrollbar-thumb { background:#ddd; border-radius:4px; }
    .seccion-item { padding:10px 20px; font-size:13px; border-bottom:1px solid var(--gray-200); display:flex; align-items:center; justify-content:space-between; transition:background 0.15s; }
    .seccion-item:hover { background:var(--gray-100); }
    .seccion-num { font-weight:700; font-size:13px; }
    .seccion-assigned { color:var(--primary); font-size:11px; font-weight:700; display:flex; align-items:center; gap:4px; }
    /* Nivel 5: progreso en cada sección */
    .sec-progreso { display:flex; align-items:center; gap:10px; font-size:12px; color:var(--text-muted); }
    .sec-bar { flex:1; height:4px; background:var(--gray-200); border-radius:4px; overflow:hidden; }
    .sec-fill { height:100%; background:var(--primary); }

    .zona-info-box { margin:12px 16px; padding:14px 16px; background:rgba(45,122,62,0.05); border:1px solid rgba(45,122,62,0.2); border-radius:12px; font-size:13px; }
    .zona-info-box .zi-title { font-weight:800; font-size:14px; color:var(--dark); margin-bottom:4px; }
    .zona-info-box .zi-foco { font-style:italic; color:var(--text-muted); margin-bottom:6px; font-size:12px; }
    .zona-info-box .zi-bullet { font-size:12px; color:var(--dark); margin-bottom:3px; display:flex; gap:6px; }
    .zona-info-box .zi-bullet strong { color:var(--primary); }

    /* Zona nivel 5: card resumen */
    .zona-card-n5 { border-color:var(--primary); background:rgba(45,122,62,0.02); cursor:default; }

    .empty-col { text-align:center; padding:40px 20px; color:var(--text-muted); font-size:13px; }
    .empty-col i { display:block; font-size:28px; opacity:0.25; margin-bottom:10px; }
    .hint-text { text-align:center; padding:30px 16px; color:var(--text-muted); font-size:13px; }
    .hint-text i { display:block; font-size:24px; opacity:0.2; margin-bottom:8px; }
    .empty-state { text-align:center; padding:80px 20px; color:var(--text-muted); }
    .empty-state i { font-size:48px; opacity:0.15; display:block; margin-bottom:16px; }

    /* ── TOAST & MODALES ── */
    .toast { position:fixed; bottom:30px; right:30px; background:var(--dark); color:white; padding:12px 20px; border-radius:12px; font-size:13px; font-weight:600; z-index:9999; transform:translateY(80px); opacity:0; transition:all 0.35s cubic-bezier(0.175,0.885,0.32,1.275); pointer-events:none; display:flex; align-items:center; gap:8px; }
    .toast.show { transform:translateY(0); opacity:1; }
    .toast.ok  { background:#2d7a3e; }
    .toast.err { background:#d32f2f; }

    .modal { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(255,255,255,0.9); backdrop-filter:blur(5px); z-index:2000; align-items:center; justify-content:center; opacity:0; transition:opacity 0.3s ease; }
    .modal.open { display:flex; opacity:1; }
    .modal-content { background:var(--white); border-radius:20px; border:1px solid var(--gray-200); box-shadow:0 25px 50px rgba(0,0,0,0.1); width:90%; max-width:560px; padding:40px; position:relative; transform:translateY(30px); opacity:0; transition:all 0.4s ease-out; max-height:90vh; overflow-y:auto; }
    .modal.open .modal-content { transform:translateY(0); opacity:1; }
    .close-modal { position:absolute; top:25px; right:25px; font-size:22px; cursor:pointer; color:#ccc; width:40px; height:40px; display:flex; align-items:center; justify-content:center; border-radius:50%; transition:all 0.3s; }
    .close-modal:hover { color:var(--primary); background:#f0f7f0; transform:rotate(90deg) scale(1.1); }
    .form-group { display:flex; flex-direction:column; gap:8px; margin-bottom:18px; }
    .form-label { font-size:12px; font-weight:700; text-transform:uppercase; color:var(--text-muted); letter-spacing:0.5px; }
    .form-input-styled { width:100%; padding:12px 15px; border-radius:10px; border:2px solid var(--gray-200); background:var(--gray-100); font-size:14px; font-weight:600; color:var(--dark); transition:all 0.3s; outline:none; appearance:none; font-family:Arial,sans-serif; }
    .form-input-styled:focus { border-color:var(--primary); background:var(--white); }
    textarea.form-input-styled { resize:vertical; min-height:80px; font-weight:400; }
    .modal-footer { display:flex; justify-content:flex-end; gap:10px; margin-top:28px; padding-top:22px; border-top:1px solid var(--gray-200); }
    .btn-save { background:var(--primary); color:white; border:none; padding:12px 28px; border-radius:12px; font-weight:700; cursor:pointer; transition:all 0.3s; font-size:14px; font-family:Arial,sans-serif; }
    .btn-save:hover { background:var(--primary-dark); }
    .btn-cancel { background:var(--white); color:var(--text-muted); border:1px solid var(--gray-200); padding:12px 22px; border-radius:12px; font-weight:600; cursor:pointer; transition:0.3s; font-size:13px; font-family:Arial,sans-serif; }
    .btn-cancel:hover { border-color:var(--dark); color:var(--dark); }

    .seccion-picker { display:flex; flex-wrap:wrap; gap:6px; max-height:180px; overflow-y:auto; padding:10px; background:var(--gray-100); border-radius:10px; border:2px solid var(--gray-200); }
    .seccion-tag { padding:4px 10px; border-radius:6px; border:1px solid var(--gray-200); font-size:12px; font-weight:600; cursor:pointer; background:var(--white); color:var(--text-muted); transition:0.15s; }
    .seccion-tag.selected { background:var(--primary); color:white; border-color:var(--primary); }
    .seccion-tag.ocupada { background:#f5f5f5; color:#bbb; border-color:#e0e0e0; cursor:not-allowed; text-decoration:line-through; opacity:0.65; }
    .seccion-picker-search { width:100%; padding:8px 12px; border-radius:8px; border:1px solid var(--gray-200); background:var(--white); font-size:13px; outline:none; margin-bottom:8px; font-family:Arial,sans-serif; }
    .legend-box { font-size:12px; color:var(--primary); background:rgba(45,122,62,0.1); padding:10px 14px; border-radius:8px; margin-bottom:10px; font-weight:600; display:none; line-height:1.4; border:1px solid rgba(45,122,62,0.2); }

    .animate-up { animation:fadeInUp 0.6s ease forwards; opacity:0; }
    @keyframes fadeInUp { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }
    .animate-delay-1 { animation-delay:0.1s; }
    .animate-delay-2 { animation-delay:0.2s; }

    .col-search input { width:100%; padding:10px 15px; border-radius:10px; border:1px solid var(--gray-200); background:var(--gray-100); font-size:13px; outline:none; font-family:Arial,sans-serif; transition:0.2s; }
    .col-search input:focus { border-color:var(--primary); background:var(--white); }
    .col-search { padding:12px 20px; border-bottom:1px solid var(--gray-200); background:var(--white); }

    @media(max-width:1100px){ .container{padding-left:20px;padding-right:20px;} .workspace{grid-template-columns:1fr;} .design-text{display:none;} .col-panel,.col-regiones,.col-zonas{border-right:none;border-bottom:1px solid var(--gray-200);} }
    @media(max-width:768px){ .zonas-grid{grid-template-columns:1fr;} }
    </style>
</head>
<body>
<?php
$path_sb = __DIR__ . '/../../includes/components/';
if      ($mi_nivel === 1) include $path_sb . 'sidebar_admin.php';
elseif  ($mi_nivel === 2) include $path_sb . 'sidebar_circunscripcion.php';
elseif  ($mi_nivel === 3) include $path_sb . 'sidebar_ce.php';
elseif  ($mi_nivel === 4) include $path_sb . 'sidebar_regional.php';
elseif  ($mi_nivel === 5) include $path_sb . 'sidebar_cz.php';
?>

<div class="design-text">ESTRUCTURA TERRITORIAL</div>

<div class="container">

<?php /* ══════════════════════════════════════════════
   NIVEL 4 — REGIONAL: grid de cards, solo lectura
══════════════════════════════════════════════ */
if ($mi_nivel === 4): ?>

    <div class="page-header animate-up">
        <div>
            <div class="ai-badge">REGIÓN <?php echo htmlspecialchars(strtoupper($region_nombre ?? '—')); ?></div>
            <h1 class="main-title">Estructura.</h1>
            <p class="description">Zonas y secciones electorales de tu región en <?php echo htmlspecialchars($user_estado); ?>.</p>
        </div>
    </div>

    <?php if (isset($error_db)): ?>
    <div style="background:#fee2e2;color:#991b1b;padding:14px;border-radius:12px;margin-bottom:20px;">
        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_db); ?>
    </div>
    <?php endif; ?>

    <?php if ($zonas_regional): ?>
    <div class="zonas-grid">
        <?php foreach ($zonas_regional as $z):
            $pct  = $z['meta'] > 0 ? min(100, round($z['contactos'] / $z['meta'] * 100)) : 0;
            $secs = $z['secciones_list'] ? explode(',', $z['secciones_list']) : [];
        ?>
        <div class="zona-card-regional">
            <div class="zona-header">
                <div>
                    <div class="zona-title"><?php echo htmlspecialchars($z['nombre']); ?></div>
                    <?php if ($z['clave']): ?><div class="zona-clave">Clave: <?php echo htmlspecialchars($z['clave']); ?></div><?php endif; ?>
                </div>
                <span class="zona-badge"><?php echo $z['num_secciones']; ?> secc.</span>
            </div>
            <div class="zona-body">
                <div class="zona-stat-row">
                    <div class="zona-stat"><div class="zona-stat-val"><?php echo $z['num_secciones']; ?></div><div class="zona-stat-lbl">Secciones</div></div>
                    <div class="zona-stat"><div class="zona-stat-val"><?php echo number_format($z['contactos']); ?></div><div class="zona-stat-lbl">Contactos</div></div>
                    <div class="zona-stat"><div class="zona-stat-val"><?php echo number_format($z['meta']); ?></div><div class="zona-stat-lbl">Meta</div></div>
                </div>
                <div class="zona-detail-label">Avance de contactos</div>
                <div class="progress-wrap"><div class="progress-bar" style="width:<?php echo $pct; ?>%"></div></div>
                <div style="font-size:12px;color:var(--text-muted);text-align:right;margin-bottom:10px;"><?php echo $pct; ?>% · <?php echo number_format($z['contactos']); ?> / <?php echo number_format($z['meta']); ?></div>
                <?php if ($z['colonias']): ?><div class="zona-detail-label">Colonias</div><p style="font-size:13px;color:#555;line-height:1.5;margin-bottom:10px;"><?php echo htmlspecialchars($z['colonias']); ?></p><?php endif; ?>
                <?php if ($z['foco']): ?><div class="zona-detail-label">Foco de trabajo</div><p style="font-size:13px;color:#555;line-height:1.5;margin-bottom:10px;"><?php echo htmlspecialchars($z['foco']); ?></p><?php endif; ?>
                <?php if ($secs): ?>
                <div class="zona-detail-label">Secciones electorales</div>
                <div class="secciones-chips">
                    <?php foreach (array_slice($secs,0,20) as $s): ?><span class="seccion-chip"><?php echo htmlspecialchars($s); ?></span><?php endforeach; ?>
                    <?php if (count($secs)>20): ?><span class="seccion-chip" style="background:var(--primary);color:white;border-color:var(--primary);">+<?php echo count($secs)-20; ?> más</span><?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <i class="fas fa-map-marked-alt"></i>
        <p>No hay zonas configuradas para tu región.</p>
    </div>
    <?php endif; ?>

<?php /* ══════════════════════════════════════════════
   NIVEL 5 — ZONA: workspace 3 columnas, solo lectura async
══════════════════════════════════════════════ */
elseif ($mi_nivel === 5): ?>

    <div class="page-header animate-up">
        <div>
            <div class="ai-badge">VISTA OPERATIVA</div>
            <h1 class="main-title">Estructura<br>Asignada.</h1>
            <p class="description">Distribución geográfica oficial de tu zona en <strong><?php echo htmlspecialchars($user_estado); ?></strong>.</p>
        </div>
    </div>

    <div class="workspace animate-up animate-delay-1">
        <div class="col-panel">
            <div class="col-header">
                <div class="col-header-title"><i class="fas fa-map-marker-alt" style="color:var(--primary)"></i> Resumen de Zona</div>
            </div>
            <div class="col-body" id="col-zona-body">
                <div class="empty-col"><i class="fas fa-circle-notch fa-spin"></i>Cargando tu zona...</div>
            </div>
        </div>
        <div class="col-panel">
            <div class="col-header">
                <div class="col-header-title">
                    <i class="fas fa-city" style="color:var(--primary)"></i> Municipios
                    <span id="col-munis-subtitle" style="font-weight:400;color:var(--text-muted);font-size:12px;margin-left:4px;"></span>
                </div>
            </div>
            <div id="col-munis-body" style="flex:1;display:flex;flex-direction:column;overflow-y:auto;padding:12px;">
                <div class="hint-text"><i class="fas fa-circle-notch fa-spin"></i>Cargando...</div>
            </div>
        </div>
        <div class="col-panel" style="flex:1;">
            <div class="col-header">
                <div class="col-header-title">
                    <i class="fas fa-list-ol" style="color:var(--primary)"></i> Secciones
                    <span id="col-sec-subtitle" style="font-weight:400;color:var(--text-muted);font-size:12px;margin-left:4px;"></span>
                </div>
            </div>
            <div class="col-search"><input type="text" id="searchInput" placeholder="Filtrar sección por número..." onkeyup="filtrarSecciones()"></div>
            <div id="col-secciones-body" style="flex:1;display:flex;flex-direction:column;">
                <div class="hint-text"><i class="fas fa-arrow-left"></i>Selecciona un municipio.</div>
            </div>
        </div>
    </div>

<?php /* ══════════════════════════════════════════════
   NIVELES 1-3 — CRUD: workspace + tabs
══════════════════════════════════════════════ */
else: ?>

    <div class="page-header animate-up">
        <div>
            <?php
            $badge_labels = [1=>'VISTA ADMINISTRADOR', 2=>'VISTA CIRCUNSCRIPCIÓN', 3=>'VISTA COORDINADOR ESTATAL'];
            $title_labels = [1=>'Configuración<br>de Estructuras.', 2=>'Seleccionador<br>de Estructuras.', 3=>'Seleccionador<br>de Estructuras.'];
            ?>
            <div class="ai-badge"><?php echo $badge_labels[$mi_nivel] ?? 'ESTRUCTURA'; ?></div>
            <h1 class="main-title"><?php echo $title_labels[$mi_nivel] ?? 'Estructuras.'; ?></h1>
            <p class="description">Estructura operativa para: <strong id="desc-estado"><?php echo htmlspecialchars($primer_estado_display ?? ''); ?></strong>
                <?php if ($mi_nivel === 2): ?> &mdash; <?php echo htmlspecialchars($user_circ); ?><?php endif; ?>
            </p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <button class="btn-secondary-action" onclick="abrirModalRegion()">
                <i class="fas fa-plus"></i> Nueva Región
            </button>
        </div>
    </div>

    <?php /* TABS: admin muestra tabs de circ + estados, circ solo estados, estatal nada */ ?>
    <?php if ($mi_nivel === 1): ?>
    <div class="tabs-bar animate-up animate-delay-1" id="tabs-circ"></div>
    <div class="sub-tabs-bar animate-up animate-delay-1" id="tabs-estados"></div>
    <?php elseif ($mi_nivel === 2): ?>
    <div class="sub-tabs-bar animate-up animate-delay-1" id="tabs-estados">
        <?php foreach ($estados_disponibles_display as $i => $est): ?>
        <button class="sub-tab-btn <?php echo $i===0?'active':''; ?>"
                onclick="seleccionarEstado('<?php echo htmlspecialchars($est, ENT_QUOTES); ?>', this)">
            <?php echo htmlspecialchars($est); ?>
        </button>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="workspace animate-up animate-delay-2">
        <div class="col-regiones">
            <div class="col-header">
                <div class="col-header-title"><i class="fas fa-map" style="color:var(--primary)"></i> Regiones</div>
                <button class="btn-primary-action btn-sm" onclick="abrirModalRegion()"><i class="fas fa-plus"></i> Nueva</button>
            </div>
            <div class="col-body" id="col-regiones-body">
                <div class="empty-col"><i class="fas fa-circle-notch fa-spin"></i> Cargando...</div>
            </div>
        </div>
        <div class="col-zonas">
            <div class="col-header">
                <div class="col-header-title">
                    <i class="fas fa-layer-group" style="color:var(--primary)"></i> Zonas
                    <span id="col-zonas-subtitle" style="font-weight:400;color:var(--text-muted);font-size:12px;margin-left:4px;"></span>
                </div>
                <button class="btn-primary-action btn-sm" id="btn-nueva-zona" onclick="abrirModalZona()" style="display:none;"><i class="fas fa-plus"></i> Nueva</button>
            </div>
            <div id="col-zonas-body" style="flex:1;display:flex;flex-direction:column;">
                <div class="hint-text"><i class="fas fa-arrow-left"></i>Selecciona una región.</div>
            </div>
        </div>
        <div class="col-secciones">
            <div class="col-header">
                <div class="col-header-title">
                    <i class="fas fa-list-ol" style="color:var(--primary)"></i> Secciones
                    <span id="col-sec-subtitle" style="font-weight:400;color:var(--text-muted);font-size:12px;margin-left:4px;"></span>
                </div>
                <button class="btn-primary-action btn-sm" id="btn-asignar-sec" onclick="abrirModalAsignarSecciones()" style="display:none;"><i class="fas fa-pen"></i> Editar</button>
            </div>
            <div id="col-secciones-body" style="flex:1;display:flex;flex-direction:column;">
                <div class="hint-text"><i class="fas fa-arrow-left"></i>Selecciona una zona.</div>
            </div>
        </div>
    </div>

    <div class="toast" id="toast"></div>

    <!-- MODAL REGIÓN -->
    <div class="modal" id="modal-region">
        <div class="modal-content" style="max-width:600px;">
            <div class="close-modal" onclick="cerrarModal('modal-region')"><i class="fas fa-times"></i></div>
            <h2 id="modal-region-title" style="font-size:24px;font-weight:800;margin-bottom:5px;">Nueva Región</h2>
            <p style="font-size:14px;color:#999;margin-bottom:28px;">Agrega una región geográfica dentro del estado seleccionado.</p>
            <?php if ($mi_nivel === 2): ?>
            <div class="form-group">
                <label class="form-label">Estado *</label>
                <select id="r-estado" class="form-input-styled" onchange="cambiarEstadoModalRegion()">
                    <?php foreach ($estados_disponibles_display as $est): ?>
                    <option value="<?php echo htmlspecialchars($est); ?>"><?php echo htmlspecialchars($est); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div class="form-group">
                    <label class="form-label">Nombre de la Región *</label>
                    <input type="text" id="r-nombre" class="form-input-styled" placeholder="Ej. Región Norte">
                </div>
                <div class="form-group">
                    <label class="form-label">Meta de Miembros</label>
                    <input type="number" id="r-meta" class="form-input-styled" placeholder="0" min="0">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label"><i class="fas fa-user-tie" style="color:var(--primary);margin-right:5px;"></i> Coordinador / Responsable</label>
                <select id="r-responsable" class="form-input-styled"><option value="">Cargando usuarios...</option></select>
            </div>
            <div class="form-group">
                <label class="form-label">Descripción</label>
                <textarea id="r-desc" class="form-input-styled" placeholder="Descripción general de la región..." style="min-height:60px;"></textarea>
            </div>
            <div class="form-group">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <label class="form-label">Municipios asignados a la Región *</label>
                    <button type="button" class="btn-secondary-action btn-sm" onclick="seleccionarTodosMunicipios()" style="padding:4px 8px;font-size:11px;">
                        <i class="fas fa-check-double"></i> Agregar Todos
                    </button>
                </div>
                <input type="text" class="seccion-picker-search" id="r-muni-search" placeholder="Buscar municipio..." oninput="filtrarMunicipiosPicker()">
                <div class="seccion-picker" id="muni-picker"></div>
                <div style="font-size:11px;color:var(--text-muted);margin-top:5px;" id="muni-counter">0 municipios seleccionados</div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="cerrarModal('modal-region')">Cancelar</button>
                <button class="btn-save" id="btn-save-region" onclick="guardarRegion()"><i class="fas fa-check"></i> Guardar</button>
            </div>
        </div>
    </div>

    <!-- MODAL ZONA -->
    <div class="modal" id="modal-zona">
        <div class="modal-content" style="max-width:620px;">
            <div class="close-modal" onclick="cerrarModal('modal-zona')"><i class="fas fa-times"></i></div>
            <h2 id="modal-zona-title" style="font-size:24px;font-weight:800;margin-bottom:5px;">Nueva Zona</h2>
            <p style="font-size:14px;color:#999;margin-bottom:28px;">Define una zona dentro de la región seleccionada.</p>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div class="form-group"><label class="form-label">Nombre de la Zona *</label><input type="text" id="z-nombre" class="form-input-styled" placeholder="Ej. Zona Norte"></div>
                <div class="form-group"><label class="form-label">Clave / Código</label><input type="text" id="z-clave" class="form-input-styled" placeholder="Ej. ZN-01"></div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div class="form-group"><label class="form-label">Meta de Miembros</label><input type="number" id="z-meta" class="form-input-styled" placeholder="0" min="0"></div>
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-user-tie" style="color:var(--primary);margin-right:5px;"></i> Coordinador / Responsable</label>
                    <select id="z-responsable" class="form-input-styled"><option value="">Cargando usuarios...</option></select>
                </div>
            </div>
            <div class="form-group"><label class="form-label">Foco / Descripción</label><textarea id="z-foco" class="form-input-styled" placeholder="Foco: Vivienda de interés social..."></textarea></div>
            <div class="form-group"><label class="form-label">Colonias Clave</label><input type="text" id="z-colonias" class="form-input-styled" placeholder="Ej. Pitic, Jesús García"></div>
            <div class="form-group">
                <label class="form-label">Secciones Electorales</label>
                <div id="z-muni-legend" class="legend-box"></div>
                <input type="text" class="seccion-picker-search" id="z-sec-search" placeholder="Buscar sección..." oninput="filtrarSeccionesPicker()">
                <div class="seccion-picker" id="seccion-picker"></div>
                <div style="font-size:11px;color:var(--text-muted);margin-top:5px;" id="sec-counter">0 secciones seleccionadas</div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="cerrarModal('modal-zona')">Cancelar</button>
                <button class="btn-save" id="btn-save-zona" onclick="guardarZona()"><i class="fas fa-check"></i> Guardar</button>
            </div>
        </div>
    </div>

    <!-- MODAL SECCIONES -->
    <div class="modal" id="modal-secciones">
        <div class="modal-content" style="max-width:560px;">
            <div class="close-modal" onclick="cerrarModal('modal-secciones')"><i class="fas fa-times"></i></div>
            <h2 style="font-size:22px;font-weight:800;margin-bottom:5px;">Secciones de <span id="modal-sec-zona-name" style="color:var(--primary)">—</span></h2>
            <p style="font-size:14px;color:#999;margin-bottom:20px;">Selecciona o quita secciones electorales de esta zona.</p>
            <div id="edit-muni-legend" class="legend-box"></div>
            <input type="text" class="seccion-picker-search" id="edit-sec-search" style="margin-bottom:10px;" placeholder="Buscar sección..." oninput="filtrarSeccionesEdit()">
            <div class="seccion-picker" id="edit-seccion-picker" style="max-height:260px;"></div>
            <div style="font-size:11px;color:var(--text-muted);margin-top:6px;" id="edit-sec-counter">0 secciones seleccionadas</div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="cerrarModal('modal-secciones')">Cancelar</button>
                <button class="btn-save" id="btn-save-secciones" onclick="guardarSeccionesEdit()"><i class="fas fa-check"></i> Guardar</button>
            </div>
        </div>
    </div>

<?php endif; ?>
</div><!-- /container -->

<script>
const MI_NIVEL = <?php echo $mi_nivel; ?>;

/* ════════════════════════════════════════════════════════════════
   NIVEL 5 — ZONA: workspace async
════════════════════════════════════════════════════════════════ */
<?php if ($mi_nivel === 5): ?>
let appDataZona = { zona: null };
let municipioActivo = null;

async function cargarDatosZona() {
    try {
        const res  = await fetch(window.location.pathname + '?api=cargar');
        const data = await res.json();
        if (!data.ok) throw new Error(data.msg || 'Error');
        appDataZona.zona = data.zona;
        renderZonaN5();
        renderMunicipiosN5();
        if (appDataZona.zona.municipios.length === 1) selectMunicipioN5(appDataZona.zona.municipios[0].nombre);
    } catch(e) {
        document.getElementById('col-zona-body').innerHTML =
            `<div class="empty-col"><i class="fas fa-exclamation-triangle" style="color:#d32f2f"></i>Error al cargar datos.<br><small>${e.message}</small></div>`;
    }
}

function renderZonaN5() {
    const body = document.getElementById('col-zona-body');
    const z = appDataZona.zona;
    const info = z.info || {};
    const pct = z.meta_total > 0 ? Math.min(100, Math.round(z.contactos_totales / z.meta_total * 100)) : 0;
    body.innerHTML = `
        <div class="zona-card zona-card-n5" style="margin-bottom:0;">
            <div class="card-name">${z.nombre}${info.clave ? ` <span style="font-size:12px;color:#888;font-weight:400;">(${info.clave})</span>` : ''}</div>
            <div class="card-meta"><span class="card-miembros">Avance de Contactos</span><span class="card-pct">${pct}%</span></div>
            <div class="card-bar-track"><div class="card-bar-fill" style="width:${pct}%"></div></div>
            <div class="card-secciones-count" style="margin-bottom:15px;">${z.contactos_totales.toLocaleString('es-MX')} de ${z.meta_total.toLocaleString('es-MX')} meta</div>
            <div style="border-top:1px solid rgba(45,122,62,0.2);padding-top:12px;">
                <div class="zona-info-box" style="margin:0;background:transparent;border:none;padding:0;">
                    <div class="zi-bullet"><i class="fas fa-layer-group" style="color:var(--primary);margin-top:2px;"></i><div><strong>${z.total_secciones}</strong> Secciones electorales</div></div>
                    <div class="zi-bullet"><i class="fas fa-city" style="color:var(--primary);margin-top:2px;"></i><div><strong>${z.municipios.length}</strong> Municipio${z.municipios.length!==1?'s':''}</div></div>
                    ${info.foco ? `<div class="zi-bullet"><i class="fas fa-bullseye" style="color:var(--primary);margin-top:2px;"></i><div><strong>Foco:</strong> ${info.foco}</div></div>` : ''}
                </div>
            </div>
        </div>`;
}

function renderMunicipiosN5() {
    const body     = document.getElementById('col-munis-body');
    const subtitle = document.getElementById('col-munis-subtitle');
    if (!appDataZona.zona || !appDataZona.zona.municipios.length) {
        body.innerHTML = '<div class="hint-text"><i class="fas fa-city"></i>Sin municipios registrados.</div>';
        return;
    }
    subtitle.textContent = `(${appDataZona.zona.municipios.length})`;
    body.innerHTML = appDataZona.zona.municipios.map(m => {
        const pct = m.meta > 0 ? Math.min(100, Math.round(m.contactos / m.meta * 100)) : 0;
        return `<div class="zona-card ${municipioActivo===m.nombre?'active':''}" onclick="selectMunicipioN5('${m.nombre.replace(/'/g,"\\'")}')">
            <div class="card-name">${m.nombre}</div>
            <div class="card-meta"><span class="card-miembros">${m.secciones.length} Secc.</span><span class="card-pct">${pct}%</span></div>
            <div class="card-bar-track"><div class="card-bar-fill" style="width:${pct}%"></div></div>
            <div class="card-secciones-count">${m.contactos.toLocaleString('es-MX')} / ${m.meta.toLocaleString('es-MX')} meta</div>
        </div>`;
    }).join('');
}

function selectMunicipioN5(nombre) {
    municipioActivo = nombre;
    renderMunicipiosN5();
    renderSeccionesN5();
    const si = document.getElementById('searchInput');
    if (si) { si.value = ''; filtrarSecciones(); }
}

function renderSeccionesN5() {
    const body     = document.getElementById('col-secciones-body');
    const subtitle = document.getElementById('col-sec-subtitle');
    if (!municipioActivo) {
        body.innerHTML = '<div class="hint-text"><i class="fas fa-arrow-left"></i>Selecciona un municipio.</div>';
        subtitle.textContent = ''; return;
    }
    const m = appDataZona.zona.municipios.find(x => x.nombre === municipioActivo);
    if (!m) return;
    subtitle.textContent = m.nombre;
    if (!m.secciones.length) { body.innerHTML = '<div class="hint-text"><i class="fas fa-list-ol"></i>Sin secciones asignadas.</div>'; return; }
    body.innerHTML = '<div class="list-scroll">' + m.secciones.map(s => {
        const meta = parseInt(s.meta_contactos)||0, cont = parseInt(s.contactos_conseguidos)||0;
        const padron = parseInt(s.padron_electoral)||0;
        const pct = meta>0 ? Math.min(100, Math.round(cont/meta*100)) : 0;
        return `<div class="seccion-item">
            <div style="width:100%;">
                <div class="sec-header" style="display:flex;justify-content:space-between;align-items:center;">
                    <span class="seccion-num">Sección ${s.nombre_seccion}</span>
                    <span class="seccion-assigned"><i class="fas fa-check-circle"></i> Activa</span>
                </div>
                <div class="sec-progreso" style="margin-top:6px;">
                    <span style="width:40px;">${pct}%</span>
                    <div class="sec-bar"><div class="sec-fill" style="width:${pct}%"></div></div>
                    <span style="width:80px;text-align:right;">${cont} / ${meta}</span>
                </div>
                ${padron>0 ? `<div style="font-size:11px;color:#999;margin-top:4px;">Padrón INE: ${padron.toLocaleString('es-MX')}</div>` : ''}
            </div>
        </div>`;
    }).join('') + '</div>';
}

function filtrarSecciones() {
    const filter = document.getElementById('searchInput').value.toLowerCase();
    document.querySelectorAll('.seccion-item').forEach(node => {
        node.style.display = node.querySelector('.seccion-num').textContent.toLowerCase().includes(filter) ? 'flex' : 'none';
    });
}

document.addEventListener('DOMContentLoaded', () => { cargarDatosZona(); });

<?php /* ════════════════════════════════════════════════════════════════
   NIVELES 1-3 — CRUD
════════════════════════════════════════════════════════════════ */
elseif ($mi_nivel <= 3): ?>

/* ── DATOS BASE ── */
const MAPA_CIRCUNSCRIPCIONES = <?php echo json_encode($CIRCS_DISPLAY, JSON_UNESCAPED_UNICODE); ?>;
const DISPLAY_TO_BD          = <?php echo json_encode($display_to_bd, JSON_UNESCAPED_UNICODE); ?>;
const ESTADOS_DISPONIBLES    = <?php echo json_encode($estados_disponibles_display ?? [], JSON_UNESCAPED_UNICODE); ?>;
const MI_NIVEL_PHP           = <?php echo $mi_nivel; ?>;

let circActual    = <?php echo json_encode(array_key_first($CIRCS_DISPLAY), JSON_UNESCAPED_UNICODE); ?>;
let estadoActual  = <?php echo json_encode($primer_estado_display ?? '', JSON_UNESCAPED_UNICODE); ?>;
let estadoModalRegion = estadoActual;

// Caché de municipios por estado (el primer estado viene precargado desde PHP)
let db = {};
db[estadoActual] = { regiones: [] };
let MUNICIPIOS_BASE   = <?php echo $municipios_precarga_json; ?>;
let USUARIOS_BASE     = [];  // todos coordinadores — lookup en tarjetas
let USUARIOS_ESTADO   = [];  // coordinadores del estado actual — selectores

/* ── API HELPER ── */
const API_BASE = window.location.pathname;

function toNombreBD(displayNombre) { return DISPLAY_TO_BD[displayNombre] || displayNombre; }

async function apiGet(accion) {
    const bdEstado = toNombreBD(estadoActual);
    const res = await fetch(`${API_BASE}?api=${accion}&estado=${encodeURIComponent(bdEstado)}`);
    return res.json();
}
async function apiPost(accion, datos, overrideEstado = null) {
    const displayEstado = overrideEstado || estadoActual;
    const bdEstado      = toNombreBD(displayEstado);
    datos.estado = bdEstado;
    const res = await fetch(`${API_BASE}?api=${accion}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(datos)
    });
    return res.json();
}

/* ── ESTADO EN MEMORIA ── */
let regionActiva        = null;
let zonaActiva          = null;
let editandoRegionId    = null;
let editandoZonaId      = null;
let seccionesSelPicker  = new Set();
let seccionesSelEdit    = new Set();
let municipiosSelPicker = new Set();

/* ── TOAST ── */
function toast(msg, tipo='ok') {
    const el = document.getElementById('toast');
    el.className = 'toast ' + tipo;
    el.innerHTML = `<i class="fas fa-${tipo==='ok'?'check-circle':'exclamation-circle'}"></i> ${msg}`;
    el.classList.add('show');
    clearTimeout(window._toastTimer);
    window._toastTimer = setTimeout(() => el.classList.remove('show'), 3000);
}

/* ── TABS (solo admin tiene tabs de circunscripción) ── */
function initTabs() {
    if (MI_NIVEL_PHP === 1) {
        renderCircTabs();
        renderEstadosTabs();
    }
    // circ: tabs ya renderizados en PHP, solo enlazamos el handler
    document.getElementById('desc-estado').textContent = estadoActual;
}

function renderCircTabs() {
    const container = document.getElementById('tabs-circ');
    if (!container) return;
    container.innerHTML = Object.keys(MAPA_CIRCUNSCRIPCIONES).map(circ => {
        const isActive = (circ === circActual) ? 'active' : '';
        return `<button class="tab-btn ${isActive}" onclick="seleccionarCirc('${circ}')">${circ}</button>`;
    }).join('');
}

function renderEstadosTabs() {
    const container = document.getElementById('tabs-estados');
    if (!container) return;
    const estados = MAPA_CIRCUNSCRIPCIONES[circActual] || [];
    container.innerHTML = estados.map(est => {
        const isActive = (est === estadoActual) ? 'active' : '';
        return `<button class="sub-tab-btn ${isActive}" onclick="seleccionarEstado('${est}', this)">${est}</button>`;
    }).join('');
}

async function seleccionarCirc(circ) {
    if (circ === circActual) return;
    circActual   = circ;
    estadoActual = MAPA_CIRCUNSCRIPCIONES[circActual][0];
    renderCircTabs();
    renderEstadosTabs();
    await resetAndLoad();
}

async function seleccionarEstado(est, btn) {
    if (est === estadoActual) return;
    estadoActual = est;
    if (MI_NIVEL_PHP === 1) renderEstadosTabs();
    else {
        // circ: highlight manual
        document.querySelectorAll('#tabs-estados .sub-tab-btn').forEach(b => b.classList.remove('active'));
        if (btn) btn.classList.add('active');
    }
    await resetAndLoad();
}

function cambiarEstadoModalRegion() {
    estadoModalRegion = document.getElementById('r-estado').value;
    // recargar municipios para el nuevo estado seleccionado
    const munis = (MUNICIPIOS_BASE_ALL[estadoModalRegion] || MUNICIPIOS_BASE[estadoModalRegion]) || {};
    municipiosSelPicker = new Set();
    MUNICIPIOS_BASE = Object.keys(munis).length ? munis : MUNICIPIOS_BASE;
    renderMunicipiosPicker();
}

async function resetAndLoad() {
    regionActiva = null; zonaActiva = null;
    document.getElementById('desc-estado').textContent = estadoActual;
    document.getElementById('col-regiones-body').innerHTML = '<div class="empty-col"><i class="fas fa-circle-notch fa-spin"></i> Cargando...</div>';
    document.getElementById('col-zonas-body').innerHTML    = '<div class="hint-text"><i class="fas fa-arrow-left"></i>Selecciona una región.</div>';
    document.getElementById('col-secciones-body').innerHTML= '<div class="hint-text"><i class="fas fa-arrow-left"></i>Selecciona una zona.</div>';
    document.getElementById('col-zonas-subtitle').textContent = '';
    document.getElementById('col-sec-subtitle').textContent   = '';
    document.getElementById('btn-nueva-zona').style.display   = 'none';
    document.getElementById('btn-asignar-sec').style.display  = 'none';
    await cargarCatalogos();
    await cargarDatos();
}

/* ── CATÁLOGOS Y DATOS ── */
async function cargarCatalogos() {
    try {
        const data = await apiGet('get_catalogos');
        if (data.ok) {
            MUNICIPIOS_BASE = data.municipios       || {};
            USUARIOS_BASE   = data.usuarios         || [];  // todos coordinadores
            USUARIOS_ESTADO = data.usuarios_estado  || data.usuarios || [];
        }
    } catch(e) { console.error('Error catálogos', e); }
}

async function cargarDatos() {
    try {
        const data = await apiGet('cargar');
        if (!data.ok) throw new Error(data.msg || 'Error');
        if (!db[estadoActual]) db[estadoActual] = {};
        db[estadoActual].regiones = data.regiones || [];
        renderRegiones(); renderZonas(); renderSecciones();
    } catch(e) {
        document.getElementById('col-regiones-body').innerHTML =
            `<div class="empty-col"><i class="fas fa-exclamation-triangle" style="color:#d32f2f"></i>Error al cargar.<br><small>${e.message}</small></div>`;
    }
}

function getEstadoData() { return db[estadoActual] || { regiones: [] }; }

function getSeccionesOcupadas(excluirZonaId=null) {
    const ocupadas = new Set();
    const region   = getEstadoData().regiones.find(r => r.id === regionActiva);
    if (!region) return ocupadas;
    for (const z of (region.zonas||[])) {
        if (z.id === excluirZonaId) continue;
        for (const s of (z.secciones||[])) ocupadas.add(s);
    }
    return ocupadas;
}

function renderSelectUsuarios(selectId, seleccionado=null) {
    const select = document.getElementById(selectId);
    let html = '<option value="">Sin responsable asignado</option>';
    const lista = (USUARIOS_ESTADO && USUARIOS_ESTADO.length) ? USUARIOS_ESTADO : USUARIOS_BASE;
    lista.forEach(u => {
        const sel      = (u.id == seleccionado) ? 'selected' : '';
        const rolLabel = u.rol ? ` - ${u.rol.replace('coordinador_','').toUpperCase()}` : '';
        html += `<option value="${u.id}" ${sel}>${u.nombre}${rolLabel}</option>`;
    });
    select.innerHTML = html;
}

/* ── RENDER REGIONES ── */
function renderRegiones() {
    const body = document.getElementById('col-regiones-body');
    const regs = getEstadoData().regiones;
    if (!regs.length) { body.innerHTML = '<div class="empty-col"><i class="fas fa-map-marked-alt"></i>No hay regiones.<br>Crea la primera.</div>'; return; }
    body.innerHTML = regs.map(r => {
        const totalMiembros = (r.zonas||[]).reduce((s,z) => s+(z.miembros||0), 0);
        const pct    = r.meta > 0 ? Math.min(100, Math.round(totalMiembros/r.meta*100)) : 0;
        const nZonas = (r.zonas||[]).length;
        const nMunis = (r.municipios||[]).length;
        const user   = USUARIOS_BASE.find(u => u.id == r.responsable);
        return `<div class="region-card ${regionActiva===r.id?'active':''}" onclick="selectRegion(${r.id})">
            <div class="card-actions">
                <button class="card-action-btn" onclick="event.stopPropagation();editarRegion(${r.id})"><i class="fas fa-pen"></i></button>
                <button class="card-action-btn del" onclick="event.stopPropagation();eliminarRegion(${r.id})"><i class="fas fa-trash"></i></button>
            </div>
            <div class="card-name">${r.nombre}</div>
            ${user ? `<div style="font-size:12px;color:var(--text-muted);margin-bottom:8px;"><i class="fas fa-user" style="color:var(--primary);"></i> ${user.nombre}</div>` : ''}
            <div class="card-meta"><span class="card-miembros">${totalMiembros.toLocaleString('es-MX')} Miembros</span><span class="card-pct">${pct}%</span></div>
            <div class="card-bar-track"><div class="card-bar-fill" style="width:${pct}%"></div></div>
            <div class="card-secciones-count">${nZonas} zona${nZonas!==1?'s':''} · ${nMunis} municipio${nMunis!==1?'s':''}</div>
        </div>`;
    }).join('');
}

function selectRegion(id) { regionActiva=id; zonaActiva=null; renderRegiones(); renderZonas(); renderSecciones(); }

/* ── RENDER ZONAS ── */
function renderZonas() {
    const body     = document.getElementById('col-zonas-body');
    const btnNueva = document.getElementById('btn-nueva-zona');
    const subtitle = document.getElementById('col-zonas-subtitle');
    if (!regionActiva) { body.innerHTML='<div class="hint-text"><i class="fas fa-arrow-left"></i>Selecciona una región.</div>'; btnNueva.style.display='none'; subtitle.textContent=''; return; }
    const region = getEstadoData().regiones.find(r => r.id===regionActiva);
    if (!region) return;
    subtitle.textContent = region.nombre;
    btnNueva.style.display = 'flex';
    if (!region.zonas||!region.zonas.length) { body.innerHTML='<div class="hint-text"><i class="fas fa-layer-group"></i>No hay zonas en esta región.</div>'; return; }
    body.innerHTML = '<div class="col-body">' + region.zonas.map(z => {
        const pct  = z.meta>0 ? Math.min(100,Math.round((z.miembros||0)/z.meta*100)) : 0;
        const nSec = (z.secciones||[]).length;
        const user = USUARIOS_BASE.find(u => u.id==z.responsable);
        return `<div class="zona-card ${zonaActiva===z.id?'active':''}" onclick="selectZona(${z.id})">
            <div class="card-actions">
                <button class="card-action-btn" onclick="event.stopPropagation();editarZona(${z.id})"><i class="fas fa-pen"></i></button>
                <button class="card-action-btn del" onclick="event.stopPropagation();eliminarZona(${z.id})"><i class="fas fa-trash"></i></button>
            </div>
            <div class="card-name">${z.nombre}${z.clave?` <span style="font-size:11px;color:var(--text-muted);font-weight:400;">(${z.clave})</span>`:''}</div>
            ${user?`<div style="font-size:12px;color:var(--text-muted);margin-bottom:8px;"><i class="fas fa-user" style="color:var(--primary);"></i> ${user.nombre}</div>`:''}
            <div class="card-meta"><span class="card-miembros">${(z.miembros||0).toLocaleString('es-MX')} Miembros</span><span class="card-pct">${pct}%</span></div>
            <div class="card-bar-track"><div class="card-bar-fill" style="width:${pct}%"></div></div>
            <div class="card-secciones-count">${nSec} sección${nSec!==1?'es':''} · Meta: ${(z.meta||0).toLocaleString('es-MX')}</div>
            ${z.foco?'<div class="card-meta-bottom">'+z.foco+'</div>':''}
        </div>`;
    }).join('') + '</div>';
}

function selectZona(id) { zonaActiva=id; renderZonas(); renderSecciones(); }

/* ── RENDER SECCIONES ── */
function renderSecciones() {
    const body     = document.getElementById('col-secciones-body');
    const btnAsig  = document.getElementById('btn-asignar-sec');
    const subtitle = document.getElementById('col-sec-subtitle');
    if (!zonaActiva) { body.innerHTML='<div class="hint-text"><i class="fas fa-arrow-left"></i>Selecciona una zona.</div>'; btnAsig.style.display='none'; subtitle.textContent=''; return; }
    const region = getEstadoData().regiones.find(r=>r.id===regionActiva);
    const zona   = (region?.zonas||[]).find(z=>z.id===zonaActiva);
    if (!zona) return;
    subtitle.textContent = zona.nombre;
    btnAsig.style.display = 'flex';
    const secs = zona.secciones||[];
    const infoBox = `<div class="zona-info-box">
        <div class="zi-title">${zona.nombre}${zona.clave?' · '+zona.clave:''}</div>
        ${zona.foco?'<div class="zi-foco">'+zona.foco+'</div>':''}
        <div class="zi-bullet"><strong>Secciones (${secs.length}):</strong> ${secs.length?secs.slice(0,8).join(', ')+(secs.length>8?'...':''):'—'}</div>
        ${zona.colonias?'<div class="zi-bullet"><strong>Colonias:</strong> '+zona.colonias+'</div>':''}
    </div>`;
    if (!secs.length) {
        body.innerHTML = infoBox + `<div class="hint-text"><i class="fas fa-list-ol"></i>Sin secciones asignadas.<br>
            <button class="btn-primary-action btn-sm" onclick="abrirModalAsignarSecciones()" style="margin-top:10px;"><i class="fas fa-plus"></i> Asignar</button></div>`;
        return;
    }
    body.innerHTML = infoBox + '<div class="list-scroll">' +
        secs.map(s => `<div class="seccion-item"><span class="seccion-num">Sección ${s}</span><span class="seccion-assigned"><i class="fas fa-check-circle"></i> Asignada</span></div>`).join('') + '</div>';
}

/* ── CRUD REGIONES ── */
function abrirModalRegion(id=null) {
    editandoRegionId = id;
    estadoModalRegion = estadoActual;
    document.getElementById('modal-region-title').textContent = id ? 'Editar Región' : 'Nueva Región';
    ['r-nombre','r-desc','r-meta','r-muni-search'].forEach(f => document.getElementById(f).value='');
    municipiosSelPicker = new Set();
    let idResp = null;
    if (id) {
        const r = getEstadoData().regiones.find(x=>x.id===id);
        if (r) {
            document.getElementById('r-nombre').value = r.nombre||'';
            document.getElementById('r-desc').value   = r.descripcion||'';
            document.getElementById('r-meta').value   = r.meta||'';
            idResp = r.responsable;
            municipiosSelPicker = new Set(r.municipios||[]);
        }
    }
    if (MI_NIVEL_PHP === 2) {
        const sel = document.getElementById('r-estado');
        if (sel) sel.value = estadoModalRegion;
    }
    renderSelectUsuarios('r-responsable', idResp);
    renderMunicipiosPicker();
    document.getElementById('modal-region').classList.add('open');
}

function seleccionarTodosMunicipios() {
    Object.keys(MUNICIPIOS_BASE||{}).forEach(m => municipiosSelPicker.add(m));
    renderMunicipiosPicker();
}

function renderMunicipiosPicker() {
    const q      = document.getElementById('r-muni-search').value.toLowerCase();
    const munis  = Object.keys(MUNICIPIOS_BASE||{}).filter(m => m.toLowerCase().includes(q));
    document.getElementById('muni-picker').innerHTML = munis.map(m => {
        const sel = municipiosSelPicker.has(m);
        return `<span class="seccion-tag ${sel?'selected':''}" data-mun="${m.replace(/"/g,'&quot;')}" onclick="toggleMunicipio(this)">${m}</span>`;
    }).join('');
    document.getElementById('muni-counter').textContent = municipiosSelPicker.size + ' municipios seleccionados';
}

function filtrarMunicipiosPicker() { renderMunicipiosPicker(); }
function toggleMunicipio(el) {
    const m = el.dataset.mun;
    municipiosSelPicker.has(m) ? municipiosSelPicker.delete(m) : municipiosSelPicker.add(m);
    renderMunicipiosPicker();
}

async function guardarRegion() {
    const nombre = document.getElementById('r-nombre').value.trim();
    if (!nombre)                    { toast('El nombre es obligatorio.','err'); return; }
    if (!municipiosSelPicker.size)  { toast('Selecciona al menos un municipio.','err'); return; }
    const btn = document.getElementById('btn-save-region');
    btn.disabled=true; btn.innerHTML='<i class="fas fa-circle-notch fa-spin"></i> Guardando...';
    const overrideEst = (MI_NIVEL_PHP===2) ? (document.getElementById('r-estado')?.value||estadoActual) : null;
    try {
        const res = await apiPost('guardar_region', {
            id: editandoRegionId, nombre,
            desc: document.getElementById('r-desc').value.trim(),
            meta: parseInt(document.getElementById('r-meta').value)||0,
            responsable: document.getElementById('r-responsable').value||null,
            municipios: [...municipiosSelPicker].sort()
        }, overrideEst);
        if (!res.ok) throw new Error(res.msg||'Error');
        if (!editandoRegionId) regionActiva = res.id;
        await cargarCatalogos();
        await cargarDatos();
        cerrarModal('modal-region');
        toast(editandoRegionId ? 'Región actualizada.' : 'Región creada.');
    } catch(e) { toast('Error: '+e.message,'err'); }
    finally { btn.disabled=false; btn.innerHTML='<i class="fas fa-check"></i> Guardar'; }
}

function editarRegion(id) { abrirModalRegion(id); }
async function eliminarRegion(id) {
    if (!confirm('¿Eliminar esta región y todas sus zonas?')) return;
    try {
        const res = await apiPost('eliminar_region',{id});
        if (!res.ok) throw new Error(res.msg||'Error');
        if (regionActiva===id) { regionActiva=null; zonaActiva=null; }
        await cargarDatos(); toast('Región eliminada.');
    } catch(e) { toast('Error: '+e.message,'err'); }
}

/* ── CRUD ZONAS ── */
function abrirModalZona(id=null) {
    editandoZonaId=id;
    document.getElementById('modal-zona-title').textContent = id?'Editar Zona':'Nueva Zona';
    ['z-nombre','z-clave','z-foco','z-colonias','z-meta','z-sec-search'].forEach(f=>document.getElementById(f).value='');
    seccionesSelPicker=new Set();
    let idResp=null;
    const region = getEstadoData().regiones.find(r=>r.id===regionActiva);
    const legend = document.getElementById('z-muni-legend');
    if (region?.municipios?.length) {
        legend.innerHTML='<i class="fas fa-filter"></i> <strong>Municipios de la región:</strong> '+region.municipios.join(', ');
        legend.style.display='block';
    } else {
        legend.innerHTML='<i class="fas fa-exclamation-triangle"></i> La región no tiene municipios asignados.';
        legend.style.display='block';
    }
    if (id&&region) {
        const z=(region.zonas||[]).find(z=>z.id===id);
        if (z) {
            document.getElementById('z-nombre').value  =z.nombre  ||'';
            document.getElementById('z-clave').value   =z.clave   ||'';
            document.getElementById('z-foco').value    =z.foco    ||'';
            document.getElementById('z-colonias').value=z.colonias||'';
            document.getElementById('z-meta').value    =z.meta    ||'';
            idResp=z.responsable;
            seccionesSelPicker=new Set(z.secciones||[]);
        }
    }
    renderSelectUsuarios('z-responsable',idResp);
    renderSeccionesPicker('seccion-picker','z-sec-search',seccionesSelPicker);
    document.getElementById('modal-zona').classList.add('open');
}

function getSeccionesPermitidas() {
    const region = getEstadoData().regiones.find(r=>r.id===regionActiva);
    const munis  = region?.municipios||[];
    let p=[];
    munis.forEach(m=>{ if(MUNICIPIOS_BASE[m]) p=p.concat(MUNICIPIOS_BASE[m]); });
    return p.sort((a,b)=>parseInt(a)-parseInt(b));
}

function renderSeccionesPicker(pickerId,searchId,selSet) {
    const q       = document.getElementById(searchId).value.toLowerCase();
    const secs    = getSeccionesPermitidas().filter(s=>s.includes(q));
    const ocupadas= getSeccionesOcupadas(editandoZonaId);
    document.getElementById(pickerId).innerHTML = secs.map(s => {
        const esOc=ocupadas.has(s)&&!selSet.has(s), esSel=selSet.has(s);
        const cls=esSel?'selected':esOc?'ocupada':'';
        const click=esOc?'':`onclick="toggleSeccion(this,'${pickerId}','${searchId}')"`;
        const title=esOc?'title="Ya asignada a otra zona"':'';
        return `<span class="seccion-tag ${cls}" data-sec="${s}" ${click} ${title}>${s}</span>`;
    }).join('');
    document.getElementById('sec-counter').textContent=selSet.size+' secciones seleccionadas';
}

function filtrarSeccionesPicker(){ renderSeccionesPicker('seccion-picker','z-sec-search',seccionesSelPicker); }
function toggleSeccion(el,pickerId,searchId){
    const s=el.dataset.sec;
    if(seccionesSelPicker.has(s)) seccionesSelPicker.delete(s);
    else { if(getSeccionesOcupadas(editandoZonaId).has(s)) return; seccionesSelPicker.add(s); }
    renderSeccionesPicker(pickerId,searchId,seccionesSelPicker);
}

async function guardarZona() {
    const nombre=document.getElementById('z-nombre').value.trim();
    if (!nombre)       { toast('El nombre es obligatorio.','err'); return; }
    if (!regionActiva) { toast('Selecciona una región primero.','err'); return; }
    const btn=document.getElementById('btn-save-zona');
    btn.disabled=true; btn.innerHTML='<i class="fas fa-circle-notch fa-spin"></i> Guardando...';
    try {
        const res=await apiPost('guardar_zona',{
            id:editandoZonaId, region_id:regionActiva, nombre,
            clave:document.getElementById('z-clave').value.trim(),
            foco:document.getElementById('z-foco').value.trim(),
            colonias:document.getElementById('z-colonias').value.trim(),
            meta:parseInt(document.getElementById('z-meta').value)||0,
            responsable:document.getElementById('z-responsable').value||null,
            secciones:[...seccionesSelPicker].sort((a,b)=>parseInt(a)-parseInt(b))
        });
        if (!res.ok) throw new Error(res.msg||'Error');
        if (!editandoZonaId) zonaActiva=res.id;
        await cargarCatalogos();
        await cargarDatos(); cerrarModal('modal-zona');
        toast(editandoZonaId?'Zona actualizada.':'Zona creada.');
    } catch(e) { toast('Error: '+e.message,'err'); }
    finally { btn.disabled=false; btn.innerHTML='<i class="fas fa-check"></i> Guardar'; }
}

function editarZona(id) { abrirModalZona(id); }
async function eliminarZona(id) {
    if(!confirm('¿Eliminar esta zona permanentemente?')) return;
    try {
        const res=await apiPost('eliminar_zona',{id});
        if(!res.ok) throw new Error(res.msg||'Error');
        if(zonaActiva===id) zonaActiva=null;
        await cargarDatos(); toast('Zona eliminada.');
    } catch(e) { toast('Error: '+e.message,'err'); }
}

/* ── MODAL SECCIONES ── */
function abrirModalAsignarSecciones() {
    const region=getEstadoData().regiones.find(r=>r.id===regionActiva);
    const zona=(region?.zonas||[]).find(z=>z.id===zonaActiva);
    if (!zona) return;
    document.getElementById('modal-sec-zona-name').textContent=zona.nombre;
    document.getElementById('edit-sec-search').value='';
    const legend=document.getElementById('edit-muni-legend');
    if (region.municipios?.length) {
        legend.innerHTML='<i class="fas fa-filter"></i> <strong>Municipios de la región:</strong> '+region.municipios.join(', ');
        legend.style.display='block';
    }
    seccionesSelEdit=new Set(zona.secciones||[]);
    renderSeccionesEdit();
    document.getElementById('modal-secciones').classList.add('open');
}

function renderSeccionesEdit() {
    const q=document.getElementById('edit-sec-search').value.toLowerCase();
    const secs=getSeccionesPermitidas().filter(s=>s.includes(q));
    const ocupadas=getSeccionesOcupadas(zonaActiva);
    document.getElementById('edit-seccion-picker').innerHTML=secs.map(s=>{
        const esOc=ocupadas.has(s)&&!seccionesSelEdit.has(s), esSel=seccionesSelEdit.has(s);
        const cls=esSel?'selected':esOc?'ocupada':'';
        const click=esOc?'':`onclick="toggleSeccionEdit(this)"`;
        const title=esOc?'title="Ya asignada a otra zona"':'';
        return `<span class="seccion-tag ${cls}" data-sec="${s}" ${click} ${title}>${s}</span>`;
    }).join('');
    document.getElementById('edit-sec-counter').textContent=seccionesSelEdit.size+' secciones seleccionadas';
}

function filtrarSeccionesEdit(){ renderSeccionesEdit(); }
function toggleSeccionEdit(el){
    const s=el.dataset.sec;
    if(seccionesSelEdit.has(s)) seccionesSelEdit.delete(s);
    else { if(getSeccionesOcupadas(zonaActiva).has(s)) return; seccionesSelEdit.add(s); }
    renderSeccionesEdit();
}

async function guardarSeccionesEdit(){
    const btn=document.getElementById('btn-save-secciones');
    btn.disabled=true; btn.innerHTML='<i class="fas fa-circle-notch fa-spin"></i> Guardando...';
    try {
        const res=await apiPost('guardar_secciones',{zona_id:zonaActiva,secciones:[...seccionesSelEdit].sort((a,b)=>parseInt(a)-parseInt(b))});
        if(!res.ok) throw new Error(res.msg||'Error');
        await cargarCatalogos();
        await cargarDatos(); cerrarModal('modal-secciones'); toast('Secciones guardadas.');
    } catch(e){ toast('Error: '+e.message,'err'); }
    finally { btn.disabled=false; btn.innerHTML='<i class="fas fa-check"></i> Guardar'; }
}

/* ── INIT ── */
function cerrarModal(id){ document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal').forEach(el=>el.addEventListener('click',e=>{ if(e.target===el) el.classList.remove('open'); }));

document.addEventListener('DOMContentLoaded', async () => {
    initTabs();
    await cargarCatalogos();
    await cargarDatos();
});
<?php endif; ?>
</script>
</body>
</html>
