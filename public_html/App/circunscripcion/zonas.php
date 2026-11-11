<?php
if (file_exists(__DIR__ . '/../../includes/bootstrap.php')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
} else { 
    session_start(); 
}

$rol       = $_SESSION['user_role']            ?? '';
$user_circ = $_SESSION['user_circunscripcion'] ?? 'Circunscripción 1';
$permitidos = ['admin','administrador','superadmin','super admin', 'circunscripcion'];

if (!in_array($rol, $permitidos)) { 
    header('Location: /public/index.php'); 
    exit(); 
}

// ── Número de circunscripción (1-5) ─────────────────────────────────────────
$circ_num = (int) filter_var($user_circ, FILTER_SANITIZE_NUMBER_INT);

// ── Mapas de circunscripción ─────────────────────────────────────────────────
$CIRCS = [
    'Circunscripción 1' => ['Baja California','Baja California Sur','Chihuahua','Durango','Jalisco','Nayarit','Sinaloa','Sonora'],
    'Circunscripción 2' => ['Aguascalientes','Coahuila','Guanajuato','Nuevo Leon','San Luis Potosi','Tamaulipas','Zacatecas'],
    'Circunscripción 3' => ['Campeche','Chiapas','Oaxaca','Quintana Roo','Tabasco','Veracruz','Yucatan'],
    'Circunscripción 4' => ['Ciudad de Mexico','Guerrero','Hidalgo','Morelos','Puebla','Tlaxcala'],
    'Circunscripción 5' => ['Colima','Estado de Mexico','Michoacan','Queretaro'],
];

$CIRCS_DISPLAY = [
    'Circunscripción 1' => ['Baja California','Baja California Sur','Chihuahua','Durango','Jalisco','Nayarit','Sinaloa','Sonora'],
    'Circunscripción 2' => ['Aguascalientes','Coahuila','Guanajuato','Nuevo León','San Luis Potosí','Tamaulipas','Zacatecas'],
    'Circunscripción 3' => ['Campeche','Chiapas','Oaxaca','Quintana Roo','Tabasco','Veracruz','Yucatán'],
    'Circunscripción 4' => ['Ciudad de México','Guerrero','Hidalgo','Morelos','Puebla','Tlaxcala'],
    'Circunscripción 5' => ['Colima','Estado de México','Michoacán','Querétaro'],
];

$estados_circ         = $CIRCS[$user_circ]         ?? [];
$estados_circ_display = $CIRCS_DISPLAY[$user_circ] ?? $estados_circ;

// ── HANDLER AJAX (mismo archivo) ─────────────────────────────────────────────
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');

    // GET: devolver regiones de un estado
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $est = $_GET['estado'] ?? '';
        if (!empty($estados_circ) && !in_array($est, $estados_circ)) {
            echo json_encode(['regiones' => [], 'error' => 'Estado no permitido para esta circunscripción']);
            exit;
        }
        try {
            $regiones = cargarRegionesDB($pdo, $est);
            echo json_encode(['regiones' => $regiones]);
        } catch(Exception $e) {
            echo json_encode(['regiones' => [], 'error' => $e->getMessage()]);
        }
        exit;
    }

    // POST: guardar/eliminar
    $body   = json_decode(file_get_contents('php://input'), true);
    $accion = $body['accion'] ?? '';
    $datos  = $body['datos']  ?? [];
    $est    = $body['estado'] ?? '';

    if (!empty($estados_circ) && !empty($est) && !in_array($est, $estados_circ)) {
        echo json_encode(['success' => false, 'error' => 'Estado no permitido para esta circunscripción']);
        exit;
    }

    try {
        switch ($accion) {
            case 'guardar_region':
                $id             = isset($datos['id']) && $datos['id'] ? (int)$datos['id'] : null;
                $nombre         = trim($datos['nombre'] ?? '');
                $desc           = trim($datos['desc']   ?? '');
                $meta           = (int)($datos['meta']  ?? 0);
                $municipios     = json_encode($datos['municipios'] ?? [], JSON_UNESCAPED_UNICODE);
                $coordinador_id = isset($datos['coordinador_id']) && $datos['coordinador_id'] ? (int)$datos['coordinador_id'] : null;
                $tipoIntegr     = trim($datos['tipo_integracion'] ?? 'municipios');
                if (!in_array($tipoIntegr, ['municipios','distrito_local','distrito_federal'], true)) {
                    $tipoIntegr = 'municipios';
                }

                if (!$nombre) throw new Exception('Nombre requerido');

                if ($id) {
                    $stmt = $pdo->prepare("UPDATE regiones_estatales SET nombre=?, descripcion=?, meta=?, municipios=?, tipo_integracion=?, coordinador_id=? WHERE id=? AND estado=?");
                    $stmt->execute([$nombre, $desc, $meta, $municipios, $tipoIntegr, $coordinador_id, $id, $est]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO regiones_estatales (estado, nombre, descripcion, meta, municipios, tipo_integracion, coordinador_id) VALUES (?,?,?,?,?,?,?)");
                    $stmt->execute([$est, $nombre, $desc, $meta, $municipios, $tipoIntegr, $coordinador_id]);
                }
                echo json_encode(['success' => true, 'regiones' => cargarRegionesDB($pdo, $est)]);
                break;

            case 'eliminar_region':
                $id = (int)($datos['id'] ?? 0);
                $pdo->prepare("DELETE FROM regiones_estatales WHERE id=? AND estado=?")->execute([$id, $est]);
                echo json_encode(['success' => true, 'regiones' => cargarRegionesDB($pdo, $est)]);
                break;

            case 'guardar_zona':
                $id             = isset($datos['id']) && $datos['id'] ? (int)$datos['id'] : null;
                $regionId       = (int)($datos['region_id'] ?? 0);
                $nombre         = trim($datos['nombre']   ?? '');
                $clave          = trim($datos['clave']    ?? '');
                $foco           = trim($datos['foco']     ?? '');
                $colonias       = trim($datos['colonias'] ?? '');
                $meta           = (int)($datos['meta']    ?? 0);
                $secciones      = json_encode($datos['secciones'] ?? [], JSON_UNESCAPED_UNICODE);
                $coordinador_id = isset($datos['coordinador_id']) && $datos['coordinador_id'] ? (int)$datos['coordinador_id'] : null;
                
                if (!$nombre) throw new Exception('Nombre requerido');

                if ($id) {
                    $stmt = $pdo->prepare("UPDATE zonas_estatales SET nombre=?,clave=?,foco=?,colonias=?,meta=?,secciones=?,coordinador_id=? WHERE id=?");
                    $stmt->execute([$nombre,$clave,$foco,$colonias,$meta,$secciones,$coordinador_id,$id]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO zonas_estatales (estructura_id,nombre,clave,foco,colonias,meta,secciones,coordinador_id) VALUES (?,?,?,?,?,?,?,?)");
                    $stmt->execute([$regionId,$nombre,$clave,$foco,$colonias,$meta,$secciones,$coordinador_id]);
                }
                echo json_encode(['success' => true, 'regiones' => cargarRegionesDB($pdo, $est)]);
                break;

            case 'eliminar_zona':
                $id = (int)($datos['id'] ?? 0);
                $pdo->prepare("DELETE FROM zonas_estatales WHERE id=?")->execute([$id]);
                echo json_encode(['success' => true, 'regiones' => cargarRegionesDB($pdo, $est)]);
                break;

            case 'guardar_secciones':
                $zonaId    = (int)($datos['zona_id']  ?? 0);
                $secciones = json_encode($datos['secciones'] ?? [], JSON_UNESCAPED_UNICODE);
                $pdo->prepare("UPDATE zonas_estatales SET secciones=? WHERE id=?")->execute([$secciones, $zonaId]);
                echo json_encode(['success' => true, 'regiones' => cargarRegionesDB($pdo, $est)]);
                break;

            case 'cargar_secciones':
                $municipios = $datos['municipios'] ?? [];
                $tipoIntegrSec = trim($datos['tipo_integracion'] ?? 'municipios');
                if (!in_array($tipoIntegrSec, ['municipios','distrito_local','distrito_federal'], true)) {
                    $tipoIntegrSec = 'municipios';
                }
                if (empty($municipios)) {
                    echo json_encode(['success' => true, 'secciones' => []]);
                    break;
                }
                $placeholders = implode(',', array_fill(0, count($municipios), '?'));
                $params = array_merge($municipios, [$est]);

                if ($tipoIntegrSec === 'distrito_local') {
                    $stmtS = $pdo->prepare("
                        SELECT s.seccion
                        FROM secciones s
                        WHERE s.distrito_local IN ($placeholders)
                        AND s.estado_nombre = ?
                        ORDER BY CAST(s.seccion AS UNSIGNED) ASC
                    ");
                } elseif ($tipoIntegrSec === 'distrito_federal') {
                    $stmtS = $pdo->prepare("
                        SELECT s.seccion
                        FROM secciones s
                        WHERE s.distrito_federal IN ($placeholders)
                        AND s.estado_nombre = ?
                        ORDER BY CAST(s.seccion AS UNSIGNED) ASC
                    ");
                } else {
                    $stmtS = $pdo->prepare("
                        SELECT s.seccion
                        FROM secciones s
                        JOIN municipios m ON m.id = s.municipio_id
                        WHERE m.nombre IN ($placeholders)
                        AND s.estado_nombre = ?
                        ORDER BY CAST(s.seccion AS UNSIGNED) ASC
                    ");
                }
                $stmtS->execute($params);
                $secciones = array_column($stmtS->fetchAll(PDO::FETCH_ASSOC), 'seccion');
                echo json_encode(['success' => true, 'secciones' => $secciones]);
                break;

            case 'cargar_usuarios':
                $estUsuarios = $est ?: ($datos['estado'] ?? '');
                $stmtU = $pdo->prepare("
                    SELECT id, TRIM(CONCAT(COALESCE(nombres,''),' ',COALESCE(ap_paterno,''),' ',COALESCE(ap_materno,''))) AS nombre,
                           'coordinador_regional' AS rol, email
                    FROM coordinador_regional WHERE estado=? AND estatus='activo'
                    UNION ALL
                    SELECT id, TRIM(CONCAT(COALESCE(nombres,''),' ',COALESCE(ap_paterno,''),' ',COALESCE(ap_materno,''))) AS nombre,
                           'coordinador_zona' AS rol, email
                    FROM coordinador_zona WHERE estado=? AND estatus='activo'
                    UNION ALL
                    SELECT id, nombre_completo AS nombre, rol, correo AS email
                    FROM usuarios
                    WHERE estado=? AND activo=1
                      AND rol IN ('regional','zona','coordinador','operador', 'coordinador_regional', 'coordinador_zona')
                    ORDER BY rol, nombre ASC
                ");
                $stmtU->execute([$estUsuarios, $estUsuarios, $estUsuarios]);
                $usuariosDisp = $stmtU->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'usuarios' => $usuariosDisp]);
                break;

            default:
                echo json_encode(['success' => false, 'error' => 'Acción no reconocida']);
        }
    } catch(Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ── Función helper: cargar regiones con zonas ────────────────────────────────
function cargarRegionesDB(PDO $pdo, string $estadoNombre): array {
    if (empty($estadoNombre)) return [];

    $existe = $pdo->query("SHOW TABLES LIKE 'regiones_estatales'")->fetchAll();
    if (empty($existe)) return [];

    $stmtR = $pdo->prepare("
        SELECT id, estado, nombre, descripcion AS `desc`, meta, municipios, tipo_integracion, coordinador_id
        FROM regiones_estatales
        WHERE estado=?
        ORDER BY nombre ASC
    ");
    $stmtR->execute([$estadoNombre]);
    $regiones = $stmtR->fetchAll(PDO::FETCH_ASSOC);

    if (empty($regiones)) return [];

    $ids = array_column($regiones, 'id');
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $stmtZ = $pdo->prepare("
        SELECT id, estructura_id AS region_id, nombre, clave, foco, colonias, meta, secciones, miembros, coordinador_id
        FROM zonas_estatales
        WHERE estructura_id IN ($in)
        ORDER BY nombre ASC
    ");
    $stmtZ->execute($ids);
    $zonas = $stmtZ->fetchAll(PDO::FETCH_ASSOC);

    $zonasPorRegion = [];
    foreach ($zonas as $z) {
        $z['id']             = (int)$z['id'];
        $z['region_id']      = (int)$z['region_id'];
        $z['meta']           = (int)$z['meta'];
        $z['miembros']       = (int)$z['miembros'];
        $z['coordinador_id'] = $z['coordinador_id'] !== null ? (int)$z['coordinador_id'] : null;
        $z['secciones']      = json_decode($z['secciones'] ?? '[]', true) ?: [];
        $zonasPorRegion[$z['region_id']][] = $z;
    }

    foreach ($regiones as &$r) {
        $r['id']               = (int)$r['id'];
        $r['meta']             = (int)$r['meta'];
        $r['coordinador_id']   = $r['coordinador_id'] !== null ? (int)$r['coordinador_id'] : null;
        $r['municipios']       = json_decode($r['municipios'] ?? '[]', true) ?: [];
        $r['tipo_integracion'] = $r['tipo_integracion'] ?: 'municipios';
        $r['zonas']            = $zonasPorRegion[$r['id']] ?? [];
    }
    
    return $regiones;
}

// ── Cargar datos para render inicial ─────────────────────────────────────────
$municipios_por_estado         = [];
$distritos_locales_por_estado  = [];
$distritos_federales_por_estado = [];
$regiones_db                   = [];

try {
    if (!empty($estados_circ)) {
        $placeholders = implode(',', array_fill(0, count($estados_circ), '?'));
        $stmtM = $pdo->prepare("
            SELECT e.nombre AS estado_nombre, m.nombre AS municipio_nombre
            FROM municipios m
            JOIN estados e ON e.id = m.estado_id
            WHERE e.nombre IN ($placeholders)
            ORDER BY e.nombre ASC, m.nombre ASC
        ");
        $stmtM->execute($estados_circ);
        foreach ($stmtM->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $municipios_por_estado[$row['estado_nombre']][] = $row['municipio_nombre'];
        }

        $stmtDL = $pdo->prepare("
            SELECT DISTINCT estado_nombre, distrito_local
            FROM secciones
            WHERE estado_nombre IN ($placeholders) AND distrito_local <> ''
            ORDER BY estado_nombre ASC, CAST(distrito_local AS UNSIGNED) ASC
        ");
        $stmtDL->execute($estados_circ);
        foreach ($stmtDL->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $distritos_locales_por_estado[$row['estado_nombre']][] = $row['distrito_local'];
        }

        $stmtDF = $pdo->prepare("
            SELECT DISTINCT estado_nombre, distrito_federal
            FROM secciones
            WHERE estado_nombre IN ($placeholders) AND distrito_federal <> ''
            ORDER BY estado_nombre ASC, CAST(distrito_federal AS UNSIGNED) ASC
        ");
        $stmtDF->execute($estados_circ);
        foreach ($stmtDF->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $distritos_federales_por_estado[$row['estado_nombre']][] = $row['distrito_federal'];
        }
    }

    $tablaExiste = $pdo->query("SHOW TABLES LIKE 'regiones_estatales'")->fetchAll();
    
    if (!empty($tablaExiste) && !empty($estados_circ)) {
        try {
            $cols = array_column($pdo->query("SHOW COLUMNS FROM regiones_estatales")->fetchAll(PDO::FETCH_ASSOC), 'Field');
            if (!in_array('coordinador_id', $cols)) {
                $pdo->exec("ALTER TABLE regiones_estatales ADD COLUMN coordinador_id INT(11) DEFAULT NULL");
            }
            $colsZ = array_column($pdo->query("SHOW COLUMNS FROM zonas_estatales")->fetchAll(PDO::FETCH_ASSOC), 'Field');
            if (!in_array('coordinador_id', $colsZ)) {
                $pdo->exec("ALTER TABLE zonas_estatales ADD COLUMN coordinador_id INT(11) DEFAULT NULL");
            }
            if (!in_array('tipo_integracion', $cols)) {
                $pdo->exec("ALTER TABLE regiones_estatales ADD COLUMN tipo_integracion ENUM('municipios','distrito_local','distrito_federal') NOT NULL DEFAULT 'municipios' AFTER municipios");
            }
        } catch(Exception $eMig) { /* silencioso */ }
        
        $placeholders = implode(',', array_fill(0, count($estados_circ), '?'));
        $params = $estados_circ; 
        
        $stmtR = $pdo->prepare("
            SELECT id, estado, nombre, descripcion AS `desc`, meta, municipios, tipo_integracion, coordinador_id
            FROM regiones_estatales
            WHERE estado IN ($placeholders)
            ORDER BY estado ASC, nombre ASC
        ");
        $stmtR->execute($params);
        $todasRegiones = $stmtR->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($todasRegiones)) {
            $ids = array_column($todasRegiones, 'id');
            $in  = implode(',', array_fill(0, count($ids), '?'));
            $stmtZ = $pdo->prepare("
                SELECT id, estructura_id AS region_id, nombre, clave, foco, colonias, meta, secciones, miembros, coordinador_id
                FROM zonas_estatales WHERE estructura_id IN ($in) ORDER BY nombre ASC
            ");
            $stmtZ->execute($ids);
            $zonasPorRegion = [];
            
            foreach ($stmtZ->fetchAll(PDO::FETCH_ASSOC) as $z) {
                $z['id']             = (int)$z['id'];
                $z['region_id']      = (int)$z['region_id'];
                $z['meta']           = (int)$z['meta'];
                $z['miembros']       = (int)$z['miembros'];
                $z['coordinador_id'] = $z['coordinador_id'] !== null ? (int)$z['coordinador_id'] : null;
                $z['secciones']      = json_decode($z['secciones'] ?? '[]', true) ?: [];
                $zonasPorRegion[$z['region_id']][] = $z;
            }
            
            foreach ($todasRegiones as &$r) {
                $r['id']               = (int)$r['id'];
                $r['meta']             = (int)$r['meta'];
                $r['coordinador_id']   = $r['coordinador_id'] !== null ? (int)$r['coordinador_id'] : null;
                $r['municipios']       = json_decode($r['municipios'] ?? '[]', true) ?: [];
                $r['tipo_integracion'] = $r['tipo_integracion'] ?: 'municipios';
                $r['zonas']            = $zonasPorRegion[$r['id']] ?? [];
            }
            unset($r);
            
            foreach ($todasRegiones as $r) {
                $regiones_db[$r['estado']][] = $r;
            }
        }
    }

    $estados = !empty($estados_circ_display) ? $estados_circ_display : ['Sonora'];

} catch (PDOException $e) {
    $estados = !empty($estados_circ_display) ? $estados_circ_display : ['Sonora'];
}

// ── USUARIOS BASE (Para renderizar nombres en las tarjetas) ─────────────
$usuarios_base = [];
try {
    if (!empty($estados_circ)) {
        $placeholders = implode(',', array_fill(0, count($estados_circ), '?'));
        // Repetimos los estados 3 veces porque hay 3 subconsultas en el UNION
        $paramsU = array_merge($estados_circ, $estados_circ, $estados_circ);
        
        // CORRECCIÓN: Agregado el rol "Regional", "Zona" y "rol" en las subconsultas
        $stmtU = $pdo->prepare("
            SELECT id, TRIM(CONCAT(COALESCE(nombres,''),' ',COALESCE(ap_paterno,''),' ',COALESCE(ap_materno,''))) AS nombre, 'Regional' as rol 
            FROM coordinador_regional WHERE estado IN ($placeholders) AND estatus='activo'
            UNION ALL
            SELECT id, TRIM(CONCAT(COALESCE(nombres,''),' ',COALESCE(ap_paterno,''),' ',COALESCE(ap_materno,''))) AS nombre, 'Zona' as rol 
            FROM coordinador_zona WHERE estado IN ($placeholders) AND estatus='activo'
            UNION ALL
            SELECT id, nombre_completo AS nombre, rol 
            FROM usuarios WHERE estado IN ($placeholders) AND activo=1 
              AND rol IN ('regional','zona','coordinador','operador', 'coordinador_regional', 'coordinador_zona')
        ");
        $stmtU->execute($paramsU);
        $usuarios_base = $stmtU->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    // Silencioso
}

$primerEstado = $estados[0] ?? 'Sonora';

// ── Lista nominal por sección ─────────────────────────────────────────────────
$seccion_ln_map = [];
try {
    $allSecciones = [];
    foreach ($regiones_db as $est => $regiones) {
        foreach ($regiones as $r) {
            foreach ($r['zonas'] as $z) {
                foreach ($z['secciones'] as $s) {
                    $allSecciones[] = (string)$s;
                }
            }
        }
    }
    $allSecciones = array_unique($allSecciones);

    if (!empty($allSecciones) && !empty($estados_circ)) {
        $phS = implode(',', array_fill(0, count($allSecciones), '?'));
        $phE = implode(',', array_fill(0, count($estados_circ), '?'));
        $stLN = $pdo->prepare("SELECT nombre_seccion, COALESCE(lista_nominal, padron_electoral, 0) AS ln FROM secciones_electorales WHERE estado IN ($phE) AND nombre_seccion IN ($phS) AND activo = 1");
        $stLN->execute(array_merge($estados_circ, $allSecciones));
        foreach ($stLN->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $seccion_ln_map[(string)$row['nombre_seccion']] = (int)$row['ln'];
        }
    }
} catch (Exception $e) { /* silencioso */ }
$seccion_ln_map_json = json_encode($seccion_ln_map, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estructuras - Circunscripción</title>
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
    .page-header { padding:50px 0 30px 0; position:relative; z-index:2; display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:20px; }
    
    .ai-badge { color:var(--primary); font-size:13px; font-weight:800; letter-spacing:4px; margin-bottom:15px; display:inline-block; text-transform:uppercase; background:rgba(45,122,62,0.1); padding:5px 15px; border-radius:20px; }
    .main-title { font-size:54px; font-weight:900; line-height:1; margin-bottom:15px; color:var(--dark); }
    .description { color:#666; font-size:16px; line-height:1.6; max-width:600px; }
    
    .btn-primary-action { background:var(--primary); color:white; border:none; padding:12px 25px; border-radius:12px; font-weight:700; cursor:pointer; transition:all 0.3s; display:inline-flex; align-items:center; gap:10px; font-size:14px; box-shadow:0 4px 15px rgba(45,122,62,0.3); font-family:Arial,sans-serif; }
    .btn-primary-action:hover { background:var(--primary-dark); transform:translateY(-2px); }
    .btn-secondary-action { background:var(--white); color:var(--text-muted); border:1px solid var(--gray-200); padding:10px 18px; border-radius:10px; font-weight:600; cursor:pointer; transition:0.3s; display:inline-flex; align-items:center; gap:8px; font-size:13px; font-family:Arial,sans-serif; }
    .btn-secondary-action:hover { border-color:var(--dark); color:var(--dark); }
    .btn-sm { padding:6px 14px; font-size:12px; border-radius:8px; }
    .btn-danger-sm { background:#ffeaea; color:#d32f2f; border:1px solid #fecaca; padding:6px 12px; border-radius:8px; font-size:12px; font-weight:700; cursor:pointer; transition:0.2s; font-family:Arial,sans-serif; }
    .btn-danger-sm:hover { background:#fee2e2; }
    
    .circ-label { font-size:12px; font-weight:700; color:var(--text-muted); letter-spacing:1px; text-transform:uppercase; margin-bottom:8px; display:flex; align-items:center; gap:6px; }
    .circ-label i { color:var(--primary); }
    
    .tabs-bar { display:flex; gap:4px; flex-wrap:wrap; border-bottom:2px solid var(--gray-200); margin-bottom:30px; padding-bottom:0; }
    .tab-btn { padding:10px 20px; border:none; background:transparent; font-family:Arial,sans-serif; font-size:14px; font-weight:600; color:var(--text-muted); cursor:pointer; border-bottom:3px solid transparent; margin-bottom:-2px; transition:0.2s; border-radius:8px 8px 0 0; }
    .tab-btn:hover { color:var(--dark); background:var(--gray-100); }
    .tab-btn.active { color:var(--primary); border-bottom-color:var(--primary); background:var(--light); }
    
    .workspace { display:grid; grid-template-columns:280px 1fr 1fr; gap:0; border:1px solid var(--gray-200); border-radius:20px; overflow:hidden; box-shadow:var(--shadow-sm); min-height:600px; }
    .col-regiones, .col-zonas, .col-secciones { background:var(--white); display:flex; flex-direction:column; }
    .col-regiones { border-right:1px solid var(--gray-200); }
    .col-zonas { border-right:1px solid var(--gray-200); }
    
    .col-header { padding:18px 20px; border-bottom:1px solid var(--gray-200); display:flex; align-items:center; justify-content:space-between; background:var(--gray-100); }
    .col-header-title { font-size:14px; font-weight:800; color:var(--dark); display:flex; align-items:center; gap:8px; }
    
    .col-body, .list-scroll { flex:1; overflow-y:auto; padding:12px; }
    .col-body::-webkit-scrollbar, .list-scroll::-webkit-scrollbar { width:4px; }
    .col-body::-webkit-scrollbar-thumb, .list-scroll::-webkit-scrollbar-thumb { background:#ddd; border-radius:4px; }
    .list-scroll { padding: 0; }
    
    .region-card, .zona-card { background:var(--white); border:1px solid var(--gray-200); border-radius:14px; padding:16px 18px; margin-bottom:10px; cursor:pointer; transition:all 0.2s; position:relative; }
    .region-card:hover, .zona-card:hover { border-color:var(--primary); box-shadow:0 4px 14px rgba(45,122,62,0.1); }
    .region-card.active, .zona-card.active { border-color:var(--primary); background:rgba(45,122,62,0.04); box-shadow:0 4px 14px rgba(45,122,62,0.12); }
    
    .card-name { font-size:16px; font-weight:700; color:var(--dark); margin-bottom:6px; }
    .card-meta { display:flex; justify-content:space-between; align-items:center; font-size:13px; }
    .card-miembros { color:var(--text-muted); }
    .card-pct { color:var(--primary); font-weight:800; font-size:14px; }
    .card-bar-track { height:3px; background:var(--gray-200); border-radius:99px; margin-top:8px; overflow:hidden; }
    .card-bar-fill { height:100%; background:var(--primary); border-radius:99px; transition:width 0.4s; }
    .card-actions { position:absolute; top:10px; right:10px; display:flex; gap:4px; opacity:0; transition:0.2s; }
    .region-card:hover .card-actions, .zona-card:hover .card-actions { opacity:1; }
    .card-action-btn { background:var(--gray-100); border:1px solid var(--gray-200); border-radius:6px; padding:4px 8px; font-size:11px; cursor:pointer; color:var(--text-muted); transition:0.2s; font-family:Arial,sans-serif; }
    .card-action-btn:hover { background:var(--primary); color:white; border-color:var(--primary); }
    .card-action-btn.del:hover { background:#d32f2f; border-color:#d32f2f; color:white; }
    .card-secciones-count { font-size:11px; color:var(--text-muted); margin-top:4px; }
    .card-meta-bottom { margin-top:4px; font-size:11px; color:var(--text-muted); font-style:italic; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    
    .empty-col { text-align:center; padding:40px 20px; color:var(--text-muted); font-size:13px; }
    .empty-col i { display:block; font-size:28px; opacity:0.25; margin-bottom:10px; }
    
    .seccion-item { padding:10px 20px; font-size:13px; border-bottom:1px solid var(--gray-200); display:flex; align-items:center; justify-content:space-between; transition:background 0.15s; }
    .seccion-item:hover { background:var(--gray-100); }
    .seccion-num { font-weight:700; font-size:13px; }
    .seccion-assigned { color:var(--primary); font-size:11px; font-weight:700; display:flex; align-items:center; gap:4px; }
    
    .zona-info-box { margin:12px 16px; padding:14px 16px; background:rgba(45,122,62,0.05); border:1px solid rgba(45,122,62,0.2); border-radius:12px; font-size:13px; }
    .zona-info-box .zi-title { font-weight:800; font-size:14px; color:var(--dark); margin-bottom:4px; }
    .zona-info-box .zi-foco  { font-style:italic; color:var(--text-muted); margin-bottom:6px; font-size:12px; }
    .zona-info-box .zi-bullet { font-size:12px; color:var(--dark); margin-bottom:3px; display:flex; gap:6px; }
    .zona-info-box .zi-bullet strong { color:var(--primary); }
    
    .hint-text { text-align:center; padding:30px 16px; color:var(--text-muted); font-size:13px; }
    .hint-text i { display:block; font-size:24px; opacity:0.2; margin-bottom:8px; }
    
    .modal { display:flex; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(255,255,255,0.9); backdrop-filter:blur(5px); z-index:2000; align-items:center; justify-content:center; opacity:0; visibility:hidden; pointer-events:none; transition:opacity 0.3s ease, visibility 0.3s ease; }
    .modal.open { opacity:1; visibility:visible; pointer-events:all; }
    .modal-content { background:var(--white); border-radius:20px; border:1px solid var(--gray-200); box-shadow:0 25px 50px rgba(0,0,0,0.1); width:90%; max-width:560px; padding:40px; position:relative; transform:translateY(30px); opacity:0; transition:all 0.4s ease-out; max-height:90vh; overflow-y:auto; }
    .modal.open .modal-content { transform:translateY(0); opacity:1; }
    
    .close-modal { position:absolute; top:25px; right:25px; font-size:22px; cursor:pointer; color:#ccc; width:40px; height:40px; display:flex; align-items:center; justify-content:center; border-radius:50%; transition:all 0.3s cubic-bezier(0.175,0.885,0.32,1.275); }
    .close-modal:hover { color:var(--primary); background:#f0f7f0; transform:rotate(90deg) scale(1.1); }
    
    .form-group { display:flex; flex-direction:column; gap:8px; margin-bottom:18px; }
    .form-label { font-size:12px; font-weight:700; text-transform:uppercase; color:var(--text-muted); letter-spacing:0.5px; }
    .form-input-styled { width:100%; padding:12px 15px; border-radius:10px; border:2px solid var(--gray-200); background:var(--gray-100); font-size:14px; font-weight:600; color:var(--dark); transition:all 0.3s; outline:none; appearance:none; font-family:Arial,sans-serif; }
    .form-input-styled:focus { border-color:var(--primary); background:var(--white); }
    textarea.form-input-styled { resize:vertical; min-height:80px; font-weight:400; }
    
    .modal-footer { display:flex; justify-content:flex-end; gap:10px; margin-top:28px; padding-top:22px; border-top:1px solid var(--gray-200); }
    .btn-save { background:var(--primary); color:white; border:none; padding:12px 28px; border-radius:12px; font-weight:700; cursor:pointer; transition:all 0.3s; font-size:14px; box-shadow:0 4px 15px rgba(45,122,62,0.3); font-family:Arial,sans-serif; }
    .btn-save:hover { background:var(--primary-dark); }
    .btn-cancel { background:var(--white); color:var(--text-muted); border:1px solid var(--gray-200); padding:12px 22px; border-radius:12px; font-weight:600; cursor:pointer; transition:0.3s; font-size:13px; font-family:Arial,sans-serif; }
    .btn-cancel:hover { border-color:var(--dark); color:var(--dark); }
    
    .seccion-picker { display:flex; flex-wrap:wrap; gap:6px; max-height:180px; overflow-y:auto; padding:10px; background:var(--gray-100); border-radius:10px; border:2px solid var(--gray-200); }
    .seccion-tag { padding:4px 10px; border-radius:6px; border:1px solid var(--gray-200); font-size:12px; font-weight:600; cursor:pointer; background:var(--white); color:var(--text-muted); transition:0.15s; }
    .seccion-tag.selected { background:var(--primary); color:white; border-color:var(--primary); }
    .seccion-tag.ocupada { background:#f5f5f5; color:#bbb; border-color:#e0e0e0; cursor:not-allowed; text-decoration:line-through; opacity:0.65; }
    .seccion-tag.ocupada:hover { background:#f5f5f5; color:#bbb; border-color:#e0e0e0; }
    .seccion-tag.ocupada::after { content:' ✗'; font-size:9px; }
    .seccion-picker-search { width:100%; padding:8px 12px; border-radius:8px; border:1px solid var(--gray-200); background:var(--white); font-size:13px; outline:none; margin-bottom:8px; font-family:Arial,sans-serif; }
    
    .legend-box { font-size:12px; color:var(--primary); background:rgba(45,122,62,0.1); padding:10px 14px; border-radius:8px; margin-bottom:10px; font-weight:600; display:none; line-height:1.4; border:1px solid rgba(45,122,62,0.2); }
    
    .animate-up { animation:fadeInUp 0.6s ease forwards; opacity:0; }
    @keyframes fadeInUp { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }
    .animate-delay-1 { animation-delay:0.1s; }
    .animate-delay-2 { animation-delay:0.2s; }
    
    @media(max-width:1100px){ 
        .container{padding-left:20px;padding-right:20px;} 
        .workspace{grid-template-columns:1fr;} 
        .design-text{display:none;} 
        .col-regiones, .col-zonas {border-right:none;border-bottom:1px solid var(--gray-200);} 
    }
    </style>
</head>
<body>
<?php if (file_exists(__DIR__ . '/../../includes/components/sidebar_circunscripcion.php')) include __DIR__ . '/../../includes/components/sidebar_circunscripcion.php'; ?>

<div class="design-text">ESTRUCTURA TERRITORIAL</div>

<div class="container">
    <div class="page-header animate-up">
        <div>
            <div class="ai-badge"><i class="fas fa-map-marked-alt"></i> &nbsp; <?= htmlspecialchars($user_circ) ?></div>
            <h1 class="main-title">Seleccionador<br>de Estructuras.</h1>
            <p class="description">
                Estructura operativa del estado de <strong id="desc-estado"><?= htmlspecialchars($primerEstado) ?></strong>
                &mdash; <?= htmlspecialchars($user_circ) ?>: <?= implode(', ', $estados_circ_display) ?>.
            </p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <button class="btn-secondary-action" onclick="abrirModalRegion()">
                <i class="fas fa-plus"></i> Nueva Región
            </button>
        </div>
    </div>

    <div class="circ-label animate-up animate-delay-1">
        <i class="fas fa-globe-americas"></i>
        <?= htmlspecialchars($user_circ) ?> &mdash; <?= count($estados_circ_display) ?> estado<?= count($estados_circ_display)!==1?'s':'' ?>
    </div>

    <div class="tabs-bar animate-up animate-delay-1" id="tabs-bar">
        <?php foreach($estados as $i => $est): ?>
        <button class="tab-btn <?= $i===0?'active':'' ?>" onclick="seleccionarEstado('<?= htmlspecialchars($est, ENT_QUOTES) ?>', this)">
            <?= htmlspecialchars($est) ?>
        </button>
        <?php endforeach; ?>
    </div>

    <div class="workspace animate-up animate-delay-2">
        <div class="col-regiones">
            <div class="col-header">
                <div class="col-header-title"><i class="fas fa-map" style="color:var(--primary)"></i> Región</div>
                <button class="btn-primary-action btn-sm" onclick="abrirModalRegion()"><i class="fas fa-plus"></i> Nueva</button>
            </div>
            <div class="col-body" id="col-regiones-body">
                <div class="empty-col"><i class="fas fa-map-marked-alt"></i>No hay regiones.<br>Crea la primera.</div>
            </div>
        </div>
        <div class="col-zonas">
            <div class="col-header">
                <div class="col-header-title"><i class="fas fa-layer-group" style="color:var(--primary)"></i> Zonas <span id="col-zonas-subtitle" style="font-weight:400;color:var(--text-muted);font-size:12px;margin-left:4px;"></span></div>
                <button class="btn-primary-action btn-sm" id="btn-nueva-zona" onclick="abrirModalZona()" style="display:none;"><i class="fas fa-plus"></i> Nueva</button>
            </div>
            <div id="col-zonas-body" style="flex:1;display:flex;flex-direction:column;">
                <div class="hint-text"><i class="fas fa-arrow-left"></i>Selecciona una región.</div>
            </div>
        </div>
        <div class="col-secciones">
            <div class="col-header">
                <div class="col-header-title"><i class="fas fa-list-ol" style="color:var(--primary)"></i> Secciones <span id="col-sec-subtitle" style="font-weight:400;color:var(--text-muted);font-size:12px;margin-left:4px;"></span></div>
                <div style="display:flex;gap:6px;align-items:center;">
                    <button id="btn-ver-mapa-circ" onclick="verEnMapa()" title="Ver zona en el mapa" style="display:none;align-items:center;gap:6px;background:#1565c0;color:white;border:none;padding:6px 14px;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;font-family:Arial,sans-serif;">
                        <i class="fas fa-map-marked-alt"></i> Mapa
                    </button>
                    <button class="btn-primary-action btn-sm" id="btn-asignar-sec" onclick="abrirModalAsignarSecciones()" style="display:none;"><i class="fas fa-pen"></i> Editar</button>
                </div>
            </div>
            <div id="col-secciones-body" style="flex:1;display:flex;flex-direction:column;">
                <div class="hint-text"><i class="fas fa-arrow-left"></i>Selecciona una zona.</div>
            </div>
        </div>
    </div>
</div>

<div class="modal" id="modal-region">
    <div class="modal-content" style="max-width:600px;">
        <div class="close-modal" onclick="cerrarModal('modal-region')"><i class="fas fa-times"></i></div>
        <h2 id="modal-region-title" style="font-size:24px;font-weight:800;margin-bottom:5px;">Nueva Región</h2>
        <p style="font-size:14px;color:#999;margin-bottom:28px;">Agrega una región geográfica dentro del estado seleccionado.</p>
        
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div class="form-group">
                <label class="form-label">Estado *</label>
                <select id="r-estado" class="form-input-styled" onchange="cambiarEstadoModalRegion()">
                    <?php foreach($estados as $est): ?>
                    <option value="<?= htmlspecialchars($est, ENT_QUOTES) ?>"><?= htmlspecialchars($est) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Nombre de la Región *</label>
                <input type="text" id="r-nombre" class="form-input-styled" placeholder="Ej. Región Norte">
            </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div class="form-group">
                <label class="form-label">Meta de Miembros</label>
                <input type="number" id="r-meta" class="form-input-styled" placeholder="0" min="0">
            </div>
            <div class="form-group">
                <label class="form-label"><i class="fas fa-user-tie" style="color:var(--primary);margin-right:5px;"></i> Coordinador / Responsable</label>
                <select id="r-coordinador" class="form-input-styled">
                    <option value="">— Sin coordinador asignado —</option>
                </select>
                <div id="r-coordinador-loading" style="font-size:11px;color:var(--text-muted);margin-top:4px;display:none;"><i class="fas fa-spinner fa-spin"></i> Cargando usuarios...</div>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Descripción</label>
            <textarea id="r-desc" class="form-input-styled" placeholder="Descripción general de la región..." style="min-height: 60px;"></textarea>
        </div>
        <div class="form-group">
            <label class="form-label">Integradas por *</label>
            <div style="display:flex; gap:18px; margin-top:4px; flex-wrap:wrap;">
                <label style="display:flex; align-items:center; gap:6px; font-size:13px; cursor:pointer; font-weight:500;">
                    <input type="radio" name="r-tipo-integracion" value="municipios" checked onchange="cambiarTipoIntegracionRegion()"> Municipios
                </label>
                <label style="display:flex; align-items:center; gap:6px; font-size:13px; cursor:pointer; font-weight:500;">
                    <input type="radio" name="r-tipo-integracion" value="distrito_local" onchange="cambiarTipoIntegracionRegion()"> Distritos locales
                </label>
                <label style="display:flex; align-items:center; gap:6px; font-size:13px; cursor:pointer; font-weight:500;">
                    <input type="radio" name="r-tipo-integracion" value="distrito_federal" onchange="cambiarTipoIntegracionRegion()"> Distritos Federales
                </label>
            </div>
        </div>
        <div class="form-group">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <label class="form-label" id="r-muni-label">Asignados a la Región *</label>
                <button type="button" class="btn-secondary-action btn-sm" onclick="seleccionarTodosMunicipios()" style="padding:4px 8px; font-size:11px;">
                    <i class="fas fa-check-double"></i> Agregar Todos
                </button>
            </div>
            <input type="text" class="seccion-picker-search" id="r-muni-search" placeholder="Buscar (municipio / distrito local / distrito federal)..." oninput="filtrarMunicipiosPicker()">
            <div class="seccion-picker" id="muni-picker"></div>
            <div style="font-size:11px;color:var(--text-muted);margin-top:5px;" id="muni-counter">0 municipios seleccionados</div>
        </div>
        <div class="modal-footer">
            <button class="btn-cancel" onclick="cerrarModal('modal-region')">Cancelar</button>
            <button class="btn-save" onclick="guardarRegion()"><i class="fas fa-check"></i> Guardar</button>
        </div>
    </div>
</div>

<div class="modal" id="modal-zona">
    <div class="modal-content" style="max-width:620px;">
        <div class="close-modal" onclick="cerrarModal('modal-zona')"><i class="fas fa-times"></i></div>
        <h2 id="modal-zona-title" style="font-size:24px;font-weight:800;margin-bottom:5px;">Nueva Zona</h2>
        <p style="font-size:14px;color:#999;margin-bottom:28px;">Define una zona dentro de la región seleccionada.</p>
        
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div class="form-group">
                <label class="form-label">Nombre de la Zona *</label>
                <input type="text" id="z-nombre" class="form-input-styled" placeholder="Ej. Zona Norte">
            </div>
            <div class="form-group">
                <label class="form-label">Clave / Código</label>
                <input type="text" id="z-clave" class="form-input-styled" placeholder="Ej. ZN-01">
            </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div class="form-group">
                <label class="form-label">Meta de Miembros</label>
                <input type="number" id="z-meta" class="form-input-styled" placeholder="0" min="0">
            </div>
            <div class="form-group">
                <label class="form-label"><i class="fas fa-user-tie" style="color:var(--primary);margin-right:5px;"></i> Coordinador / Responsable</label>
                <select id="z-coordinador" class="form-input-styled">
                    <option value="">— Sin coordinador asignado —</option>
                </select>
                <div id="z-coordinador-loading" style="font-size:11px;color:var(--text-muted);margin-top:4px;display:none;"><i class="fas fa-spinner fa-spin"></i> Cargando usuarios...</div>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Foco / Descripción</label>
            <textarea id="z-foco" class="form-input-styled" placeholder="Foco: Vivienda de interés social. Población joven (25-40 años)..."></textarea>
        </div>
        <div class="form-group">
            <label class="form-label">Colonias Clave</label>
            <input type="text" id="z-colonias" class="form-input-styled" placeholder="Ej. Pitic, Jesús García, Centenario">
        </div>
        <div class="form-group">
            <label class="form-label">Secciones Electorales asignadas</label>
            <div id="z-muni-legend" class="legend-box"></div>
            <input type="text" class="seccion-picker-search" id="z-sec-search" placeholder="Buscar sección..." oninput="filtrarSeccionesPicker()">
            <div class="seccion-picker" id="seccion-picker"></div>
            <div style="font-size:11px;color:var(--text-muted);margin-top:5px;" id="sec-counter">0 secciones seleccionadas</div>
        </div>
        <div class="modal-footer">
            <button class="btn-cancel" onclick="cerrarModal('modal-zona')">Cancelar</button>
            <button class="btn-save" onclick="guardarZona()"><i class="fas fa-check"></i> Guardar</button>
        </div>
    </div>
</div>

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
            <button class="btn-save" onclick="guardarSeccionesEdit()"><i class="fas fa-check"></i> Guardar</button>
        </div>
    </div>
</div>

<script>
/* ══════════════════════════════════════════════
   DATOS DESDE PHP — filtrados por circunscripción
══════════════════════════════════════════════ */
const MUNICIPIOS_BASE          = <?= json_encode($municipios_por_estado, JSON_UNESCAPED_UNICODE) ?>;
const DISTRITOS_LOCALES_BASE   = <?= json_encode($distritos_locales_por_estado, JSON_UNESCAPED_UNICODE) ?>;
const DISTRITOS_FEDERALES_BASE = <?= json_encode($distritos_federales_por_estado, JSON_UNESCAPED_UNICODE) ?>;
const REGIONES_INIT     = <?= json_encode($regiones_db,             JSON_UNESCAPED_UNICODE) ?>;
const ESTADO_DISPLAY_TO_BD = <?= json_encode(
    array_combine(
        $estados_circ_display ?: $estados,
        $estados_circ         ?: $estados
    ),
    JSON_UNESCAPED_UNICODE
) ?>;
const USER_CIRC    = <?= json_encode($user_circ,     JSON_UNESCAPED_UNICODE) ?>;
const ESTADOS_CIRC = <?= json_encode($estados,       JSON_UNESCAPED_UNICODE) ?>;
const USUARIOS_BASE = <?= json_encode($usuarios_base, JSON_UNESCAPED_UNICODE) ?>;
const SECCION_LN_MAP = <?= $seccion_ln_map_json ?>;

function calcTotalLN(secs) {
    let t = 0;
    (secs || []).forEach(s => { t += SECCION_LN_MAP[String(s)] || 0; });
    return t;
}
function fmtLN(n) { return n > 0 ? n.toLocaleString('es-MX') : ''; }

let estadoActual      = <?= json_encode($primerEstado, JSON_UNESCAPED_UNICODE) ?>;
let estadoModalRegion = estadoActual;
let regionActiva = null, zonaActiva = null;
let editandoRegionId = null, editandoZonaId = null;
let seccionesSelPicker = new Set(), seccionesSelEdit = new Set(), municipiosSelPicker = new Set();
let tipoIntegracionRegion = 'municipios';

function getCatalogoIntegracion() {
    if (tipoIntegracionRegion === 'distrito_local')   return DISTRITOS_LOCALES_BASE;
    if (tipoIntegracionRegion === 'distrito_federal') return DISTRITOS_FEDERALES_BASE;
    return MUNICIPIOS_BASE;
}

function getEtiquetaBaseRegion(r) {
    if (r?.tipo_integracion === 'distrito_local')   return 'Distritos locales base';
    if (r?.tipo_integracion === 'distrito_federal') return 'Distritos Federales base';
    return 'Municipios base';
}

function getEtiquetaIntegracion() {
    if (tipoIntegracionRegion === 'distrito_local')   return { label: 'Distritos locales asignados a la Región *', unidad: 'distritos locales', prefijo: 'DL ' };
    if (tipoIntegracionRegion === 'distrito_federal') return { label: 'Distritos Federales asignados a la Región *', unidad: 'distritos federales', prefijo: 'DF ' };
    return { label: 'Municipios asignados a la Región *', unidad: 'municipios', prefijo: '' };
}
let db = {};

// Poblar db con datos PHP
Object.keys(ESTADO_DISPLAY_TO_BD).forEach(displayEst => {
    const bdEst = ESTADO_DISPLAY_TO_BD[displayEst] || displayEst;
    db[displayEst] = { regiones: REGIONES_INIT[bdEst] || REGIONES_INIT[displayEst] || [] };
});

Object.keys(REGIONES_INIT).forEach(est => {
    if (!db[est]) db[est] = { regiones: REGIONES_INIT[est] || [] };
});

/* ── HELPERS ── */
function getEstado() { 
    return db[estadoActual] || { regiones: [] }; 
}

function toNombreBD(displayName) {
    return ESTADO_DISPLAY_TO_BD[displayName] || displayName;
}

// Para Secciones (Se mantiene la exclusividad de secciones por zona)
function getSeccionesOcupadas(excluirId=null) {
    const o = new Set();
    const r = getEstado().regiones.find(r => r.id === regionActiva);
    if (!r) return o;
    for (const z of (r.zonas || [])) {
        if (z.id === excluirId) continue;
        for (const s of (z.secciones || [])) o.add(s);
    }
    return o;
}

/* ── AJAX SAVE ── */
async function saveToServer(accion, datos, overrideEstado=null) {
    const displayEstado = overrideEstado || estadoActual;
    const estadoBD      = toNombreBD(displayEstado);
    
    try {
        const resp = await fetch(window.location.pathname + '?ajax=1', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ accion, datos, estado: estadoBD })
        });
        const res = await resp.json();
        
        if (res.success) {
            if (res.regiones !== undefined) {
                db[displayEstado] = { regiones: res.regiones };
            }
            renderRegiones(); 
            renderZonas(); 
            renderSecciones();
            return true;
        } else { 
            alert('Error: ' + (res.error || 'Problema en el servidor')); 
            return false; 
        }
    } catch(e) { 
        alert('Error de conexión.'); 
        return false; 
    }
}

/* ── TABS ── */
function seleccionarEstado(est, btn) {
    estadoActual = est; 
    regionActiva = null; 
    zonaActiva = null;
    
    document.getElementById('desc-estado').textContent = est;
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    
    if (!db[est]) {
        const estBD = toNombreBD(est);
        fetch(window.location.pathname + '?ajax=1&estado=' + encodeURIComponent(estBD))
            .then(r => r.json())
            .then(d => { 
                db[est] = { regiones: d.regiones || [] }; 
                renderRegiones(); 
                renderZonas(); 
                renderSecciones(); 
            })
            .catch(() => { 
                db[est] = { regiones: [] }; 
                renderRegiones(); 
                renderZonas(); 
                renderSecciones(); 
            });
    } else { 
        renderRegiones(); 
        renderZonas(); 
        renderSecciones(); 
    }
}

/* ── RENDER REGIONES ── */
function renderRegiones() {
    const body = document.getElementById('col-regiones-body');
    const est  = getEstado();
    
    if (!est.regiones.length) { 
        body.innerHTML = `<div class="empty-col"><i class="fas fa-map-marked-alt"></i>No hay regiones.<br>Crea la primera.</div>`; 
        return; 
    }
    
    body.innerHTML = est.regiones.map(r => {
        const tot = (r.zonas || []).reduce((s, z) => s + (z.miembros || 0), 0);
        const pct = r.meta > 0 ? Math.min(100, Math.round(tot / r.meta * 100)) : 0;
        const nZ = (r.zonas || []).length;
        
        // CORRECCIÓN: Buscar el nombre del responsable priorizando el rol Regional
        const userResp = USUARIOS_BASE.find(u => u.id == r.coordinador_id && u.rol && u.rol.toLowerCase().includes('regional')) 
                      || USUARIOS_BASE.find(u => u.id == r.coordinador_id);
        const nombResp = userResp ? userResp.nombre : null;
        
        return `
            <div class="region-card ${regionActiva === r.id ? 'active' : ''}" onclick="selectRegion(${r.id})">
                <div class="card-actions">
                    <button class="card-action-btn" onclick="event.stopPropagation();editarRegion(${r.id})"><i class="fas fa-pen"></i></button>
                    <button class="card-action-btn del" onclick="event.stopPropagation();eliminarRegion(${r.id})"><i class="fas fa-trash"></i></button>
                </div>
                <div class="card-name">${r.nombre}</div>
                ${nombResp ? `<div style="font-size:12px; color:var(--text-muted); margin-bottom:8px;"><i class="fas fa-user" style="color:var(--primary);"></i> ${nombResp}</div>` : ''}
                <div class="card-meta">
                    <span class="card-miembros">${tot.toLocaleString('es-MX')} Miembros</span>
                    <span class="card-pct">${pct}%</span>
                </div>
                <div class="card-bar-track"><div class="card-bar-fill" style="width:${pct}%"></div></div>
                <div class="card-secciones-count">${nZ} zona${nZ !== 1 ? 's' : ''} · ${(r.municipios || []).length} ${getEtiquetaBaseRegion(r)}</div>
            </div>`;
    }).join('');
}

function selectRegion(id) { 
    regionActiva = id; 
    zonaActiva = null; 
    renderRegiones(); 
    renderZonas(); 
    renderSecciones(); 
}

/* ── RENDER ZONAS ── */
function renderZonas() {
    const body = document.getElementById('col-zonas-body');
    const btn = document.getElementById('btn-nueva-zona');
    const sub = document.getElementById('col-zonas-subtitle');
    
    if (!regionActiva) { 
        body.innerHTML = `<div class="hint-text"><i class="fas fa-arrow-left"></i>Selecciona una región.</div>`; 
        btn.style.display = 'none'; 
        sub.textContent = ''; 
        return; 
    }
    
    const r = getEstado().regiones.find(r => r.id === regionActiva); 
    if(!r) return;
    
    sub.textContent = r.nombre; 
    btn.style.display = 'flex';
    
    if (!r.zonas || !r.zonas.length) { 
        body.innerHTML = `<div class="hint-text"><i class="fas fa-layer-group"></i>No hay zonas en esta región.</div>`; 
        return; 
    }
    
    body.innerHTML = `<div class="col-body">` + r.zonas.map(z => {
        const pct = z.meta > 0 ? Math.min(100, Math.round((z.miembros || 0) / z.meta * 100)) : 0;
        const nS = (z.secciones || []).length;
        const ln = calcTotalLN(z.secciones || []);
        
        // CORRECCIÓN: Buscar el nombre del responsable priorizando el rol Zona
        const userResp = USUARIOS_BASE.find(u => u.id == z.coordinador_id && u.rol && u.rol.toLowerCase().includes('zona')) 
                      || USUARIOS_BASE.find(u => u.id == z.coordinador_id);
        const nombResp = userResp ? userResp.nombre : null;
        
        return `
            <div class="zona-card ${zonaActiva === z.id ? 'active' : ''}" onclick="selectZona(${z.id})">
                <div class="card-actions">
                    <button class="card-action-btn" onclick="event.stopPropagation();editarZona(${z.id})"><i class="fas fa-pen"></i></button>
                    <button class="card-action-btn del" onclick="event.stopPropagation();eliminarZona(${z.id})"><i class="fas fa-trash"></i></button>
                </div>
                <div class="card-name">${z.nombre}${z.clave ? ` <span style="font-size:11px;color:var(--text-muted);font-weight:400;">(${z.clave})</span>` : ''}</div>
                ${nombResp ? `<div style="font-size:12px; color:var(--text-muted); margin-bottom:8px;"><i class="fas fa-user" style="color:var(--primary);"></i> ${nombResp}</div>` : ''}
                <div class="card-meta">
                    <span class="card-miembros">${(z.miembros || 0).toLocaleString('es-MX')} Miembros</span>
                    <span class="card-pct">${pct}%</span>
                </div>
                <div class="card-bar-track"><div class="card-bar-fill" style="width:${pct}%"></div></div>
                <div class="card-secciones-count">${nS} sección${nS !== 1 ? 'es' : ''} · Meta: ${(z.meta || 0).toLocaleString('es-MX')}</div>
                ${ln > 0 ? `<div style="margin-top:6px;"><span style="font-size:11px;font-weight:700;color:#92400e;background:#fef3c7;border:1px solid #fde68a;border-radius:5px;padding:2px 8px;">${fmtLN(ln)} LN</span></div>` : ''}
                ${z.foco ? `<div class="card-meta-bottom">${z.foco}</div>` : ''}
            </div>`;
    }).join('') + `</div>`;
}

function selectZona(id) { 
    zonaActiva = id; 
    renderZonas(); 
    renderSecciones(); 
}

function renderSecciones() {
    const body = document.getElementById('col-secciones-body');
    const btn  = document.getElementById('btn-asignar-sec');
    const btnMapa = document.getElementById('btn-ver-mapa-circ');
    const sub  = document.getElementById('col-sec-subtitle');
    
    if (!zonaActiva) { 
        body.innerHTML = `<div class="hint-text"><i class="fas fa-arrow-left"></i>Selecciona una zona.</div>`; 
        btn.style.display = 'none';
        btnMapa.style.display = 'none';
        sub.textContent = ''; 
        return; 
    }
    
    const r = getEstado().regiones.find(r => r.id === regionActiva);
    const z = (r?.zonas || []).find(z => z.id === zonaActiva); 
    if(!z) return;
    
    sub.textContent = z.nombre; 
    btn.style.display = 'flex';
    btnMapa.style.display = 'flex';

    const totalLN = calcTotalLN(z.secciones || []);
    const info = `
        <div class="zona-info-box">
            <div class="zi-title">${z.nombre}${z.clave ? ' · ' + z.clave : ''}</div>
            ${z.foco ? `<div class="zi-foco">${z.foco}</div>` : ''}
            <div class="zi-bullet">
                <strong>Secciones (${(z.secciones || []).length}):</strong> 
                ${(z.secciones || []).length ? z.secciones.slice(0,8).join(', ') + (z.secciones.length > 8 ? '...' : '') : '—'}
            </div>
            ${totalLN > 0 ? `<div class="zi-bullet" style="margin-top:4px;"><span style="font-weight:800;color:#92400e;background:#fef3c7;border:1px solid #fde68a;border-radius:6px;padding:3px 10px;font-size:13px;">${fmtLN(totalLN)} de lista nominal</span></div>` : ''}
            ${z.colonias ? `<div class="zi-bullet"><strong>Colonias:</strong> ${z.colonias}</div>` : ''}
        </div>`;
        
    if (!z.secciones || !z.secciones.length) { 
        body.innerHTML = info + `
            <div class="hint-text">
                <i class="fas fa-list-ol"></i>Sin secciones asignadas.<br>
                <button class="btn-primary-action btn-sm" onclick="abrirModalAsignarSecciones()" style="margin-top:10px;">
                    <i class="fas fa-plus"></i> Asignar
                </button>
            </div>`; 
        return; 
    }
    
    const secsOrdenadas = [...z.secciones].sort((a,b) => parseInt(a)-parseInt(b));
    body.innerHTML = info + `<div class="list-scroll">` + secsOrdenadas.map(s => {
        const ln = SECCION_LN_MAP[String(s)] || 0;
        return `
        <div class="seccion-item">
            <span class="seccion-num">Sección ${s}</span>
            ${ln > 0 ? `<span style="font-size:11px;font-weight:700;color:#92400e;background:#fef3c7;border:1px solid #fde68a;border-radius:5px;padding:2px 8px;margin-left:auto;margin-right:10px;">Ln: ${ln.toLocaleString('es-MX')}</span>` : ''}
            <span class="seccion-assigned"><i class="fas fa-check-circle"></i> Asignada</span>
        </div>`;
    }).join('') + `</div>`;
}

/* ── MODALES ── */
function abrirModal(id) {
    const m = document.getElementById(id); 
    if(!m) return;
    m.classList.add('open');
}

function cerrarModal(id) {
    const m = document.getElementById(id); 
    if(!m) return;
    m.classList.remove('open');
}

document.querySelectorAll('.modal').forEach(el => el.addEventListener('click', e => {
    if(e.target === el) cerrarModal(el.id);
}));

/* ── CRUD REGIONES ── */
async function abrirModalRegion(id=null) {
    editandoRegionId = id;
    document.getElementById('modal-region-title').textContent = id ? 'Editar Región' : 'Nueva Región';
    ['r-nombre','r-desc','r-meta','r-muni-search'].forEach(f => document.getElementById(f).value = '');
    estadoModalRegion = estadoActual;
    document.getElementById('r-estado').value = estadoModalRegion;
    municipiosSelPicker = new Set();
    tipoIntegracionRegion = 'municipios';
    document.querySelectorAll('input[name="r-tipo-integracion"]').forEach(r => r.checked = (r.value === 'municipios'));
    
    // Resetear el select de coordinador ANTES de la carga async
    // para evitar que muestre el valor anterior mientras carga
    const selCoord = document.getElementById('r-coordinador');
    selCoord.innerHTML = '<option value="">— Cargando... —</option>';
    selCoord.disabled = true;
    
    if (id) {
        const r = getEstado().regiones.find(x => x.id === id);
        if (r) {
            document.getElementById('r-nombre').value = r.nombre || '';
            document.getElementById('r-desc').value   = r.desc || r.descripcion || '';
            document.getElementById('r-meta').value   = r.meta || '';
            tipoIntegracionRegion = r.tipo_integracion || 'municipios';
            document.querySelectorAll('input[name="r-tipo-integracion"]').forEach(rad => rad.checked = (rad.value === tipoIntegracionRegion));
            municipiosSelPicker = new Set(r.municipios || []);
            
            // Cargar usuarios y pre-seleccionar coordinador asignado
            const estBD = toNombreBD(estadoModalRegion);
            await cargarUsuariosEnSelect('r-coordinador','r-coordinador-loading', estBD, r.coordinador_id ?? null);
        }
    } else {
        // Nueva región: cargar usuarios del estado actual
        const estBD = toNombreBD(estadoModalRegion);
        await cargarUsuariosEnSelect('r-coordinador','r-coordinador-loading', estBD, null);
    }
    
    abrirModal('modal-region');
    try { renderMunicipiosPicker(); } catch(e) { console.warn(e); }
}

function cambiarEstadoModalRegion() { 
    estadoModalRegion = document.getElementById('r-estado').value; 
    municipiosSelPicker = new Set(); 
    renderMunicipiosPicker(); 
}

function cambiarTipoIntegracionRegion() {
    const sel = document.querySelector('input[name="r-tipo-integracion"]:checked');
    tipoIntegracionRegion = sel ? sel.value : 'municipios';
    municipiosSelPicker = new Set();
    document.getElementById('r-muni-search').value = '';
    renderMunicipiosPicker();
}

function seleccionarTodosMunicipios() {
    // Al quitar el "ocupados", solo agregamos todos sin excepción
    (getCatalogoIntegracion()[estadoModalRegion] || []).forEach(m => {
        municipiosSelPicker.add(m);
    });
    renderMunicipiosPicker();
}

function renderMunicipiosPicker() {
    const q = (document.getElementById('r-muni-search')?.value || '').toLowerCase();
    const { label, unidad, prefijo } = getEtiquetaIntegracion();
    const catalogo = getCatalogoIntegracion();
    const munis = (catalogo[estadoModalRegion] || []).filter(m => m.toLowerCase().includes(q));
    const picker = document.getElementById('muni-picker'); 
    
    const lbl = document.getElementById('r-muni-label');
    if (lbl) lbl.textContent = label;
    
    if(!picker) return;
    
    // Removido la verificación visual de municipios "ocupados"
    picker.innerHTML = munis.map(m => {
        const sel = municipiosSelPicker.has(m);
        return `<span class="seccion-tag ${sel ? 'selected' : ''}" onclick="toggleMunicipio('${m.replace(/'/g,"\\'")}')">${prefijo}${m}</span>`;
    }).join('');
    
    const c = document.getElementById('muni-counter'); 
    if(c) c.textContent = municipiosSelPicker.size + ' ' + unidad + ' seleccionados';
}

function filtrarMunicipiosPicker() { renderMunicipiosPicker(); }

function toggleMunicipio(m) { 
    municipiosSelPicker.has(m) ? municipiosSelPicker.delete(m) : municipiosSelPicker.add(m); 
    renderMunicipiosPicker(); 
}

async function guardarRegion() {
    const nombre = document.getElementById('r-nombre').value.trim();
    if (!nombre) { alert('El nombre es obligatorio.'); return; }
    
    const estadoDestino = document.getElementById('r-estado').value;
    const ok = await saveToServer('guardar_region', {
        id: editandoRegionId,
        nombre,
        desc: document.getElementById('r-desc').value.trim(),
        meta: parseInt(document.getElementById('r-meta').value) || 0,
        municipios: [...municipiosSelPicker].sort(),
        tipo_integracion: tipoIntegracionRegion,
        coordinador_id: getCoordinadorIdFromSelect('r-coordinador')
    }, estadoDestino);
    
    if (ok) {
        cerrarModal('modal-region');
        if (estadoDestino !== estadoActual) {
            document.querySelectorAll('.tab-btn').forEach(b => { 
                if(b.textContent.trim() === estadoDestino) seleccionarEstado(estadoDestino, b); 
            });
        }
    }
}

function editarRegion(id) { abrirModalRegion(id); }

async function eliminarRegion(id) {
    if (!confirm('¿Eliminar esta región y todas sus zonas?')) return;
    const ok = await saveToServer('eliminar_region', {id});
    if (ok && regionActiva === id) { 
        regionActiva = null; 
        zonaActiva = null; 
    }
}

/* ── CRUD ZONAS ── */
async function abrirModalZona(id=null) {
    editandoZonaId = id;
    document.getElementById('modal-zona-title').textContent = id ? 'Editar Zona' : 'Nueva Zona';
    ['z-nombre','z-clave','z-foco','z-colonias','z-meta','z-sec-search'].forEach(f => document.getElementById(f).value = '');
    seccionesSelPicker = new Set();
    
    const r = getEstado().regiones.find(r => r.id === regionActiva);
    const leg = document.getElementById('z-muni-legend');
    
    leg.innerHTML = r?.municipios?.length 
        ? `<i class="fas fa-filter"></i> <strong>${getEtiquetaBaseRegion(r)}:</strong> ${r.municipios.join(', ')}` 
        : `<i class="fas fa-exclamation-triangle"></i> Sin municipios asignados.`;
    leg.style.display = 'block';
    
    const estBD = toNombreBD(estadoActual);
    let coordActualId = null;
    
    if (id && r) { 
        const z = (r.zonas || []).find(z => z.id === id); 
        if(z) {
            document.getElementById('z-nombre').value = z.nombre || '';
            document.getElementById('z-clave').value = z.clave || '';
            document.getElementById('z-foco').value = z.foco || '';
            document.getElementById('z-colonias').value = z.colonias || '';
            document.getElementById('z-meta').value = z.meta || '';
            seccionesSelPicker = new Set(z.secciones || []);
            coordActualId = z.coordinador_id || null;
        } 
    }
    
    abrirModal('modal-zona');
    
    const secPicker = document.getElementById('seccion-picker');
    if (secPicker) secPicker.innerHTML = '<span style="font-size:12px;color:var(--text-muted);padding:4px;"><i class="fas fa-spinner fa-spin"></i> Cargando secciones...</span>';
    
    await Promise.all([
        cargarSeccionesDeRegion(r),
        cargarUsuariosEnSelect('z-coordinador','z-coordinador-loading', estBD, coordActualId)
    ]);
    
    renderSeccionesPicker('seccion-picker','z-sec-search',seccionesSelPicker);
}

// Secciones disponibles para la región activa
let seccionesDisponibles = [];

/* ── USUARIOS ── */
const ROLE_LABELS_COORD = {
    'coordinador_regional': 'Coord. Regional',
    'coordinador_zona':     'Coord. Zona',
    'regional':             'Regional',
    'zona':                 'Zona',
    'coordinador':          'Coordinador',
    'operador':             'Operador',
};

async function cargarUsuariosEnSelect(selectId, loadingId, estadoBD, valorActual = null) {
    const sel = document.getElementById(selectId);
    const loading = document.getElementById(loadingId);
    if (!sel) return;

    loading.style.display = 'block';
    sel.innerHTML = '<option value="">— Cargando... —</option>';
    sel.disabled = true;

    try {
        const resp = await fetch(window.location.pathname + '?ajax=1', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ accion: 'cargar_usuarios', estado: estadoBD, datos: {} })
        });
        const data = await resp.json();
        const usuarios = data.usuarios || [];

        sel.innerHTML = '<option value="">— Sin coordinador asignado —</option>';
        
        if (usuarios.length === 0) {
            sel.innerHTML += '<option value="" disabled>Sin usuarios disponibles</option>';
        } else {
            const grupos = {};
            usuarios.forEach(u => {
                const label = ROLE_LABELS_COORD[u.rol] || u.rol || 'Otro';
                if (!grupos[label]) grupos[label] = [];
                grupos[label].push(u);
            });
            
            Object.entries(grupos).forEach(([grupo, lista]) => {
                const og = document.createElement('optgroup');
                og.label = grupo;
                lista.forEach(u => {
                    const opt = document.createElement('option');
                    opt.value = u.id + '|' + u.rol;
                    opt.textContent = u.nombre + (u.email ? ' — ' + u.email : '');
                    if (valorActual && parseInt(u.id) === parseInt(valorActual)) opt.selected = true;
                    og.appendChild(opt);
                });
                sel.appendChild(og);
            });
        }
    } catch(e) {
        sel.innerHTML = '<option value="">— Error al cargar usuarios —</option>';
    } finally {
        loading.style.display = 'none';
        sel.disabled = false;
    }
}

function getCoordinadorIdFromSelect(selectId) {
    const sel = document.getElementById(selectId);
    if (!sel || !sel.value) return null;
    const parts = sel.value.split('|');
    return parts[0] ? parseInt(parts[0]) : null;
}

async function cargarSeccionesDeRegion(region) {
    if (!region || !region.municipios || region.municipios.length === 0) {
        seccionesDisponibles = [];
        return;
    }
    try {
        const resp = await fetch(window.location.pathname + '?ajax=1', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                accion: 'cargar_secciones',
                datos: { municipios: region.municipios, tipo_integracion: region.tipo_integracion || 'municipios' },
                estado: toNombreBD(estadoActual)
            })
        });
        const data = await resp.json();
        seccionesDisponibles = data.secciones || [];
    } catch(e) {
        seccionesDisponibles = [];
    }
}

function renderSeccionesPicker(pid, sid, sel) {
    const p = document.getElementById(pid); 
    if (!p) return;
    
    const q = (document.getElementById(sid)?.value || '').toLowerCase();
    const ocupadas = getSeccionesOcupadas(editandoZonaId);
    const filtradas = seccionesDisponibles.filter(s => s.toLowerCase().includes(q));

    if (filtradas.length === 0) {
        p.innerHTML = '<span style="font-size:12px;color:var(--text-muted);padding:4px;">' +
            (seccionesDisponibles.length === 0 ? 'Sin secciones disponibles.' : 'No hay coincidencias.') +
            '</span>';
    } else {
        p.innerHTML = filtradas.map(s => {
            const ocu = ocupadas.has(s) && !sel.has(s);
            const esSel = sel.has(s);
            return `<span class="seccion-tag ${esSel ? 'selected' : ocu ? 'ocupada' : ''}" ` +
                (ocu ? 'title="Ya asignada a otra zona"' : `onclick="toggleSeccion('${s}','${pid}','${sid}',seccionesSelPicker,'sec-counter')"`) +
                `>${s}</span>`;
        }).join('');
    }
    
    const cnt = document.getElementById('sec-counter');
    if (cnt) cnt.textContent = sel.size + ' secciones seleccionadas';
}

function filtrarSeccionesPicker() { renderSeccionesPicker('seccion-picker','z-sec-search',seccionesSelPicker); }

function toggleSeccion(sec,pid,sid,sel,cid) { 
    sel.has(sec) ? sel.delete(sec) : sel.add(sec); 
    renderSeccionesPicker(pid,sid,sel); 
}

async function guardarZona() {
    const nombre = document.getElementById('z-nombre').value.trim();
    if (!nombre){ alert('El nombre es obligatorio.'); return; } 
    if (!regionActiva){ alert('Selecciona una región.'); return; }
    
    const coordIdZona = getCoordinadorIdFromSelect('z-coordinador');
    const ok = await saveToServer('guardar_zona', {
        id: editandoZonaId,
        region_id: regionActiva,
        nombre,
        clave: document.getElementById('z-clave').value.trim(),
        foco: document.getElementById('z-foco').value.trim(),
        colonias: document.getElementById('z-colonias').value.trim(),
        meta: parseInt(document.getElementById('z-meta').value) || 0,
        secciones: [...seccionesSelPicker].sort(),
        coordinador_id: coordIdZona
    });
    
    if(ok) cerrarModal('modal-zona');
}

function editarZona(id){ abrirModalZona(id); }

async function eliminarZona(id) {
    if(!confirm('¿Eliminar esta zona?')) return;
    const ok = await saveToServer('eliminar_zona', {id});
    if(ok && zonaActiva === id) zonaActiva = null;
}

/* ── MODAL SECCIONES ── */
async function abrirModalAsignarSecciones() {
    const r = getEstado().regiones.find(r => r.id === regionActiva);
    const z = (r?.zonas || []).find(z => z.id === zonaActiva); 
    if(!z) return;
    
    document.getElementById('modal-sec-zona-name').textContent = z.nombre;
    document.getElementById('edit-sec-search').value = '';
    
    const leg = document.getElementById('edit-muni-legend');
    if (r?.municipios?.length) {
        leg.innerHTML = `<i class="fas fa-filter"></i> <strong>${getEtiquetaBaseRegion(r)}:</strong> ${r.municipios.join(', ')}`;
        leg.style.display = 'block';
    }
    
    seccionesSelEdit = new Set(z.secciones || []);
    abrirModal('modal-secciones');
    
    const ep = document.getElementById('edit-seccion-picker');
    if(ep) ep.innerHTML = '<span style="font-size:12px;color:var(--text-muted);padding:4px;"><i class="fas fa-spinner fa-spin"></i> Cargando secciones...</span>';
    
    await cargarSeccionesDeRegion(r);
    renderSeccionesEdit();
}

function renderSeccionesEdit() {
    const p = document.getElementById('edit-seccion-picker'); 
    if(!p) return;
    
    const q = (document.getElementById('edit-sec-search')?.value || '').toLowerCase();
    const ocupadas = getSeccionesOcupadas(zonaActiva);
    const filtradas = seccionesDisponibles.filter(s => s.toLowerCase().includes(q));
    
    if (filtradas.length === 0) {
        p.innerHTML = '<span style="font-size:12px;color:var(--text-muted);padding:4px;">' +
            (seccionesDisponibles.length === 0 ? 'Sin secciones disponibles.' : 'No hay coincidencias.') +
            '</span>';
    } else {
        p.innerHTML = filtradas.map(s => {
            const ocu = ocupadas.has(s) && !seccionesSelEdit.has(s);
            const esSel = seccionesSelEdit.has(s);
            return `<span class="seccion-tag ${esSel ? 'selected' : ocu ? 'ocupada' : ''}" ` +
                (ocu ? 'title="Ya asignada a otra zona"' : `onclick="toggleSeccionEdit('${s}')"`) +
                `>${s}</span>`;
        }).join('');
    }
    
    const cnt = document.getElementById('edit-sec-counter'); 
    if(cnt) cnt.textContent = seccionesSelEdit.size + ' secciones seleccionadas';
}

function filtrarSeccionesEdit() { renderSeccionesEdit(); }

function toggleSeccionEdit(sec) {
    seccionesSelEdit.has(sec) ? seccionesSelEdit.delete(sec) : seccionesSelEdit.add(sec);
    renderSeccionesEdit();
}

async function guardarSeccionesEdit() {
    const ok = await saveToServer('guardar_secciones', {
        zona_id: zonaActiva,
        secciones: [...seccionesSelEdit].sort()
    });
    if(ok) cerrarModal('modal-secciones');
}

/* ── VER EN MAPA (solo lectura) ── */
function verEnMapa() {
    if (!zonaActiva) return;
    const r = getEstado().regiones.find(r => r.id === regionActiva);
    const z = (r?.zonas || []).find(z => z.id === zonaActiva);
    const returnUrl = window.location.href.split('?')[0];
    const params = new URLSearchParams({
        zona_id:     zonaActiva || '',
        zona_nombre: z?.nombre || '',
        return_url:  returnUrl,
    });
    window.location.href = 'mapa_electoral.php?' + params.toString();
}

/* ── ARRANQUE ── */
document.addEventListener('DOMContentLoaded', () => { 
    renderRegiones(); 
    renderZonas(); 
    renderSecciones(); 
});
</script>
</body>
</html>