<?php
/* ════════════════════════════════════════════════════════════════
   global/secciones.php — Secciones Electorales Unificado
   Nivel 1 (Admin):      secciones_electorales — todo
   Nivel 2 (Circ):       JOIN secciones+mun — filtrado por estados circ
   Nivel 3 (Estatal):    secciones_electorales — filtrado por estado
   Nivel 4 (Regional):   secciones_electorales JOIN zona_secciones — por región
   Nivel 5 (Zona):       secciones_electorales WHERE zona_asignada
════════════════════════════════════════════════════════════════ */

// ── AJAX handlers inline (deben ir ANTES del resto del PHP) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ajax_action = $_POST['action'] ?? '';
    if (in_array($ajax_action, ['save_meta_contactos','save_contactos'])) {
        header('Content-Type: application/json');
        if (file_exists(__DIR__ . '/../../includes/bootstrap.php')) {
            require_once __DIR__ . '/../../includes/bootstrap.php';
        } else { session_start(); }
        $rol_ajax = strtolower($_SESSION['user_role'] ?? '');
        $ajax_ok = ['admin','administrador','superadmin','super admin',
                    'circunscripcion','coordinador_circunscripcion',
                    'coordinador_estatal','coordinador_regional','regional',
                    'coordinador_zona'];
        if (!in_array($rol_ajax, $ajax_ok)) {
            echo json_encode(['ok'=>false,'msg'=>'Sin permisos']); exit;
        }
        $id    = intval($_POST['id']    ?? 0);
        $valor = intval($_POST['value'] ?? 0);
        if (!$id) { echo json_encode(['ok'=>false,'msg'=>'ID inválido']); exit; }
        try {
            if (!isset($pdo)) {
                $dbConfigPath = __DIR__ . '/../../includes/database.php';
                $dbConfig = file_exists($dbConfigPath) ? require $dbConfigPath : [
                    'host'=>'localhost','database'=>'u881824383_pverde',
                    'username'=>'u881824383_proyverde','password'=>'PProyectoverde8#',
                    'charset'=>'utf8mb4',
                    'options'=>[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
                ];
                $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
                $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options'] ?? []);
            }
            $col = ($ajax_action === 'save_meta_contactos') ? 'meta_contactos' : 'contactos_conseguidos';
            // Buscar por id en secciones_electorales directamente
            $stUp = $pdo->prepare("UPDATE secciones_electorales SET $col=? WHERE id=?");
            $stUp->execute([$valor, $id]);
            echo json_encode(['ok'=>true,'updated'=>$stUp->rowCount()]);
        } catch (Exception $e) {
            echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
        }
        exit;
    }
}

// ── Seguridad ─────────────────────────────────────────────
if (file_exists(__DIR__ . '/../../includes/bootstrap.php')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
} else { session_start(); }

$rol_sesion  = strtolower($_SESSION['user_role'] ?? '');
$user_id     = $_SESSION['user_id']              ?? 0;
$user_circ   = $_SESSION['user_circunscripcion'] ?? '';
$user_estado = $_SESSION['user_estado']          ?? '';
$user_region = $_SESSION['user_region']          ?? '';
$user_zona   = $_SESSION['user_zona_nombre']     ?? '';

$jerarquia = [
    'admin'=>1,'administrador'=>1,'superadmin'=>1,'super admin'=>1,
    'coordinadores_circunscripcion'=>2,'coordinador_circunscripcion'=>2,'circunscripcion'=>2,
    'coordinador_estatal'=>3,
    'coordinador_regional'=>4,'regional'=>4,
    'coordinador_zona'=>5,
];
$mi_nivel = $jerarquia[$rol_sesion] ?? 6;

$permitidos = array_keys($jerarquia);
if (!in_array($rol_sesion, $permitidos)) { header('Location: /public/index.php'); exit(); }

// ── Circunscripciones ─────────────────────────────────────
$CIRCS = [
    'Circunscripción 1' => ['Baja California','Baja California Sur','Chihuahua','Durango','Jalisco','Nayarit','Sinaloa','Sonora'],
    'Circunscripción 2' => ['Aguascalientes','Coahuila','Guanajuato','Nuevo Leon','San Luis Potosi','Tamaulipas','Zacatecas'],
    'Circunscripción 3' => ['Campeche','Chiapas','Oaxaca','Quintana Roo','Tabasco','Veracruz','Yucatan'],
    'Circunscripción 4' => ['Ciudad de Mexico','Guerrero','Hidalgo','Morelos','Puebla','Tlaxcala'],
    'Circunscripción 5' => ['Colima','Estado de Mexico','Michoacan','Queretaro'],
];
$estados_circ = $CIRCS[$user_circ] ?? [];

// ── Conexión ──────────────────────────────────────────────
$secciones    = [];
$geo_js       = '{}';
$region_nombre= '—';
$zonas_region = [];
$error_db     = null;

try {
    if (!isset($pdo)) {
        $dbConfigPath = __DIR__ . '/../../includes/database.php';
        $dbConfig = file_exists($dbConfigPath) ? require $dbConfigPath : [
            'host'=>'localhost','database'=>'u881824383_pverde',
            'username'=>'u881824383_proyverde','password'=>'PProyectoverde8#',
            'charset'=>'utf8mb4',
            'options'=>[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
        ];
        $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
        $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options'] ?? []);
    }

    // ── SELECT común de columnas ──────────────────────────
    $SE_COLS = "
        se.id,
        se.nombre_seccion,
        se.estado,
        se.municipio,
        se.distrito_federal,
        se.distrito_local,
        COALESCE(se.lista_nominal,         0) AS lista_nominal,
        COALESCE(se.lista_hombres,         0) AS lista_hombres,
        COALESCE(se.lista_mujeres,         0) AS lista_mujeres,
        COALESCE(se.meta_contactos,        0) AS meta_contactos,
        COALESCE(se.contactos_conseguidos, 0) AS contactos_conseguidos,
        COALESCE(se.eventos_realizados,    0) AS eventos_realizados,
        COALESCE(se.metas_votos,           0) AS metas_votos,
        COALESCE(se.region_asignada,      '') AS region_asignada,
        COALESCE(se.zona_asignada,        '') AS zona_asignada,
        COALESCE(se.principales_necesidades,'') AS principales_necesidades,
        COALESCE(se.enfoque,              '') AS enfoque,
        COALESCE(se.perfil_socioeconomico,'') AS perfil_socioeconomico,
        COALESCE(se.activaciones_totales,  0) AS activaciones_totales,
        COALESCE(se.con_candidato,         0) AS con_candidato,
        COALESCE(se.sin_candidato,         0) AS sin_candidato,
        COALESCE(se.grupo_edad_18_24,      0) AS grupo_edad_18_24,
        COALESCE(se.grupo_edad_25_34,      0) AS grupo_edad_25_34,
        COALESCE(se.grupo_edad_35_44,      0) AS grupo_edad_35_49,
        COALESCE(se.grupo_edad_45_54,      0) AS grupo_edad_50_64,
        COALESCE(se.grupo_edad_55_mas,     0) AS grupo_edad_65_mas
    ";

    /* ── NIVEL 1: Admin — todo ── */
    if ($mi_nivel === 1) {
        $stmt = $pdo->query("SELECT $SE_COLS FROM secciones_electorales se WHERE se.activo=1 ORDER BY CAST(se.nombre_seccion AS UNSIGNED) ASC");
        $secciones = $stmt->fetchAll();

    /* ── NIVEL 2: Circunscripción — estados de la circ ── */
    } elseif ($mi_nivel === 2) {
        if (!empty($estados_circ)) {
            $ph = implode(',', array_fill(0, count($estados_circ), '?'));
            $stmt = $pdo->prepare("SELECT $SE_COLS FROM secciones_electorales se WHERE se.activo=1 AND se.estado IN ($ph) ORDER BY CAST(se.nombre_seccion AS UNSIGNED) ASC");
            $stmt->execute($estados_circ);
        } else {
            $stmt = $pdo->query("SELECT $SE_COLS FROM secciones_electorales se WHERE se.activo=1 ORDER BY CAST(se.nombre_seccion AS UNSIGNED) ASC");
        }
        $secciones = $stmt->fetchAll();

    /* ── NIVEL 3: Estatal — su estado ── */
    } elseif ($mi_nivel === 3) {
        // Obtener estado desde BD con fallback a sesión
        $stE = $pdo->prepare("SELECT estado FROM coordinador_estatal WHERE id=? LIMIT 1");
        $stE->execute([$user_id]);
        $estado_coord = $stE->fetchColumn() ?: $user_estado;

        if ($estado_coord) {
            $stmt = $pdo->prepare("SELECT $SE_COLS FROM secciones_electorales se WHERE se.activo=1 AND se.estado=? ORDER BY CAST(se.nombre_seccion AS UNSIGNED) ASC");
            $stmt->execute([$estado_coord]);
        } else {
            $stmt = $pdo->query("SELECT $SE_COLS FROM secciones_electorales se WHERE se.activo=0");
        }
        $secciones = $stmt->fetchAll();

    /* ── NIVEL 4: Regional — secciones de su región vía zona_secciones ── */
    } elseif ($mi_nivel === 4) {
        if ($user_region) {
            $st = $pdo->prepare("SELECT nombre FROM geo_regiones WHERE id=? LIMIT 1");
            $st->execute([$user_region]); $r = $st->fetch();
            if ($r) $region_nombre = ucfirst($r['nombre']);

            $st = $pdo->prepare("SELECT id, nombre FROM geo_zonas WHERE region_id=? ORDER BY nombre");
            $st->execute([$user_region]);
            $zonas_region = $st->fetchAll();
            $zona_ids = array_column($zonas_region, 'id');

            if ($zona_ids) {
                $in = implode(',', array_fill(0, count($zona_ids), '?'));
                $params = array_merge($zona_ids, [$user_estado]);
                $stmt = $pdo->prepare("
                    SELECT $SE_COLS, gz.nombre AS zona_nombre
                    FROM secciones_electorales se
                    INNER JOIN zona_secciones zs ON se.nombre_seccion=zs.seccion
                    INNER JOIN geo_zonas gz ON gz.id=zs.zona_id
                    WHERE zs.zona_id IN ($in) AND se.estado=? AND se.activo=1
                    ORDER BY gz.nombre ASC, CAST(se.nombre_seccion AS UNSIGNED) ASC
                ");
                $stmt->execute($params);
            } else {
                $stmt = $pdo->prepare("SELECT $SE_COLS FROM secciones_electorales se WHERE se.activo=1 AND se.estado=? ORDER BY CAST(se.nombre_seccion AS UNSIGNED) ASC");
                $stmt->execute([$user_estado]);
            }
        } else {
            $stmt = $pdo->prepare("SELECT $SE_COLS FROM secciones_electorales se WHERE se.activo=1 AND se.estado=? ORDER BY CAST(se.nombre_seccion AS UNSIGNED) ASC");
            $stmt->execute([$user_estado]);
        }
        $secciones = $stmt->fetchAll();

    /* ── NIVEL 5: Zona — secciones de su zona ── */
    } elseif ($mi_nivel === 5) {
        $stmt = $pdo->prepare("SELECT $SE_COLS FROM secciones_electorales se WHERE TRIM(se.zona_asignada)=TRIM(?) AND se.activo=1 ORDER BY CAST(se.nombre_seccion AS UNSIGNED) ASC");
        $stmt->execute([$user_zona]);
        $secciones = $stmt->fetchAll();
    }

    // ── GEO: estados y municipios para los filtros/modales ──
    // Solo necesarios para niveles 1-3 (donde el usuario puede filtrar por estado)
    if ($mi_nivel <= 3) {
        $geo_estados_scope = $mi_nivel <= 2 ? $estados_circ : [$estado_coord ?? $user_estado];
        $geoData = [];

        if (empty($geo_estados_scope)) {
            // Admin: obtener todos los estados disponibles
            $stG = $pdo->query("SELECT DISTINCT estado FROM secciones_electorales WHERE activo=1 ORDER BY estado ASC");
            $geo_estados_scope = $stG->fetchAll(PDO::FETCH_COLUMN);
        }

        $ph2 = implode(',', array_fill(0, count($geo_estados_scope), '?'));
        $stG2 = $pdo->prepare("SELECT DISTINCT estado, municipio FROM secciones_electorales WHERE activo=1 AND estado IN ($ph2) ORDER BY estado ASC, municipio ASC");
        $stG2->execute($geo_estados_scope);
        foreach ($stG2->fetchAll() as $row) {
            $est = $row['estado'];
            if (!isset($geoData[$est])) $geoData[$est] = ['label'=>$est,'municipios'=>[]];
            if ($row['municipio']) $geoData[$est]['municipios'][] = $row['municipio'];
        }
        $geo_js = json_encode(array_values($geoData), JSON_UNESCAPED_UNICODE);
    }

} catch (PDOException $e) {
    $error_db = $e->getMessage();
}

// ── Badge y descripción por nivel ─────────────────────────
$scope_labels = [
    1 => 'ADMINISTRADOR',
    2 => strtoupper($user_circ ?: 'CIRCUNSCRIPCIÓN'),
    3 => strtoupper($estado_coord ?? $user_estado ?: 'ESTATAL'),
    4 => 'REGIONAL — '.$region_nombre,
    5 => strtoupper($user_zona ?: 'ZONA'),
];
$badge_txt = $scope_labels[$mi_nivel] ?? 'MOVILIZACIÓN';
$scope_desc = match($mi_nivel) {
    1 => 'Todas las secciones electorales del país.',
    2 => 'Secciones de la '.htmlspecialchars($user_circ).'.',
    3 => 'Secciones del estado de '.htmlspecialchars($estado_coord ?? $user_estado).'.',
    4 => 'Secciones de la región '.htmlspecialchars($region_nombre).'.',
    5 => 'Secciones de la zona '.htmlspecialchars($user_zona).'.',
    default => ''
};
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secciones Electorales</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    :root { --primary:#2d7a3e; --primary-dark:#1f5a2d; --dark:#1a1a1a; --light:#f0f7f0; --white:#ffffff; --gray-100:#f8f9fa; --gray-200:#e9ecef; --text-muted:#6c757d; --shadow-sm:0 10px 30px rgba(0,0,0,0.05); }
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family:'Arial',sans-serif; background:var(--light); color:var(--dark); overflow-x:hidden; }
    .container { max-width:100%; margin:0; background:var(--white); min-height:100vh; padding-left:330px; padding-right:50px; padding-bottom:40px; transition:padding-left 0.3s ease; }
    .design-text { position:fixed; left:290px; top:50%; writing-mode:vertical-rl; font-size:13px; font-weight:500; color:var(--primary); opacity:0.3; letter-spacing:2px; z-index:0; pointer-events:none; }
    .page-header { padding:50px 0 30px 0; position:relative; z-index:2; display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:20px; }
    .ai-badge { color:var(--primary); font-size:13px; font-weight:800; letter-spacing:4px; margin-bottom:15px; display:inline-block; text-transform:uppercase; background:rgba(45,122,62,0.1); padding:5px 15px; border-radius:20px; }
    .main-title { font-size:54px; font-weight:900; line-height:1; margin-bottom:15px; color:var(--dark); }
    .description { color:#666; font-size:16px; line-height:1.6; max-width:600px; }
    .btn-secondary { background:var(--white); color:var(--primary); border:2px solid var(--primary); padding:10px 20px; border-radius:12px; font-weight:700; cursor:pointer; transition:all 0.3s; display:flex; align-items:center; gap:8px; font-size:14px; }
    .btn-secondary:hover { background:var(--light); }
    .stats-row { display:grid; grid-template-columns:repeat(3,1fr); gap:20px; margin-bottom:30px; }
    .stat-card { background:var(--white); border:1px solid var(--gray-200); border-radius:20px; padding:25px 30px; box-shadow:var(--shadow-sm); display:flex; align-items:center; gap:18px; }
    .stat-icon { width:52px; height:52px; border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:22px; flex-shrink:0; }
    .stat-icon.verde   { background:rgba(45,122,62,.1); color:var(--primary); }
    .stat-icon.amarillo{ background:#fffbeb; color:#d97706; }
    .stat-icon.azul    { background:#eff6ff; color:#2196f3; }
    .stat-label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:var(--text-muted); margin-bottom:5px; }
    .stat-val { font-size:30px; font-weight:900; color:var(--dark); line-height:1; }
    .filters-bar { background:var(--white); border:1px solid var(--gray-200); border-radius:16px; padding:20px; margin-bottom:30px; display:flex; gap:15px; align-items:flex-end; box-shadow:var(--shadow-sm); flex-wrap:wrap; }
    .filter-group { display:flex; flex-direction:column; gap:8px; flex:1; min-width:160px; }
    .form-label { font-size:12px; font-weight:700; text-transform:uppercase; color:var(--text-muted); letter-spacing:0.5px; }
    .form-input-styled { width:100%; padding:12px 15px; border-radius:10px; border:2px solid var(--gray-200); background:var(--gray-100); font-size:14px; font-weight:600; color:var(--dark); transition:all 0.3s; outline:none; appearance:none; font-family:Arial,sans-serif; cursor:pointer; }
    .form-input-styled:focus { border-color:var(--primary); background:var(--white); }
    textarea.form-input-styled { resize:vertical; min-height:80px; font-weight:400; cursor:auto; }
    .table-section { background:var(--white); border-radius:20px; padding:30px; border:1px solid var(--gray-200); box-shadow:var(--shadow-sm); }
    .section-header { margin-bottom:25px; display:flex; justify-content:space-between; align-items:center; }
    .section-title { font-size:20px; font-weight:800; color:var(--dark); }
    .modern-table { width:100%; border-collapse:separate; border-spacing:0; }
    .modern-table th { text-align:left; padding:15px 20px; color:var(--text-muted); font-size:12px; text-transform:uppercase; letter-spacing:1px; border-bottom:2px solid var(--gray-200); white-space:nowrap; }
    .modern-table td { padding:16px 20px; border-bottom:1px solid var(--gray-200); font-size:14px; color:var(--dark); font-weight:500; vertical-align:middle; transition:background 0.2s; }
    .modern-table tr:hover td { background:var(--gray-100); }
    .modern-table tr:last-child td { border-bottom:none; }
    .sortable-th { cursor:pointer; user-select:none; }
    .sortable-th:hover { background:var(--light); color:var(--primary); }
    .sort-arrows { display:inline-block; margin-left:5px; font-size:13px; color:var(--gray-200); transition:color 0.2s; }
    .sort-arrows.asc, .sort-arrows.desc { color:var(--primary); }
    .sort-active { color:var(--primary)!important; background:rgba(45,122,62,0.05); }
    .progress-wrap { min-width:100px; }
    .progress-bar-bg { height:8px; border-radius:4px; background:var(--gray-200); overflow:hidden; margin-top:4px; }
    .progress-bar-fill { height:100%; border-radius:4px; background:linear-gradient(90deg,var(--primary),#4caf50); transition:width 0.5s ease; }
    .progress-label { font-size:11px; color:var(--text-muted); font-weight:600; }
    .stat-cell { font-size:16px; font-weight:800; color:var(--primary); }
    .btn-detalle { background:rgba(45,122,62,0.1); color:var(--primary); border:none; padding:8px 18px; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; transition:all 0.2s; white-space:nowrap; }
    .btn-detalle:hover { background:var(--primary); color:white; }
    .empty-row td { text-align:center; padding:60px 20px; color:var(--text-muted); font-size:14px; }
    .pagination { display:flex; justify-content:center; align-items:center; gap:6px; margin-top:25px; flex-wrap:wrap; }
    .page-btn { background:var(--white); border:1px solid var(--gray-200); color:var(--dark); padding:8px 14px; border-radius:8px; font-size:14px; font-weight:600; cursor:pointer; transition:all 0.2s; }
    .page-btn:hover:not(.disabled) { background:var(--gray-100); border-color:var(--primary); color:var(--primary); }
    .page-btn.active { background:var(--primary); color:var(--white); border-color:var(--primary); }
    .page-btn.disabled { opacity:0.5; cursor:not-allowed; }
    .page-dots { color:var(--text-muted); font-weight:600; padding:0 5px; }
    .modal { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(255,255,255,0.9); backdrop-filter:blur(5px); z-index:2000; align-items:center; justify-content:center; opacity:0; transition:opacity 0.3s ease; }
    .modal.open { display:flex; opacity:1; }
    .modal-content { background:var(--white); border-radius:20px; border:1px solid var(--gray-200); box-shadow:0 25px 50px rgba(0,0,0,0.1); width:90%; max-width:760px; padding:40px; position:relative; transform:translateY(30px); opacity:0; transition:all 0.4s ease-out; max-height:90vh; overflow-y:auto; }
    .modal.open .modal-content { transform:translateY(0); opacity:1; }
    .modal-close { position:absolute; top:20px; right:20px; background:var(--gray-100); border:none; cursor:pointer; width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:16px; color:var(--text-muted); transition:all 0.2s; }
    .modal-close:hover { background:#ffeaea; color:#d32f2f; }
    .modal-title { font-size:26px; font-weight:900; margin-bottom:6px; color:var(--dark); }
    .modal-subtitle { font-size:13px; color:var(--text-muted); margin-bottom:30px; }
    .det-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:25px; }
    .det-item label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:var(--text-muted); display:block; margin-bottom:4px; }
    .det-item span { font-size:15px; font-weight:700; color:var(--dark); }
    .det-section-title { font-size:13px; font-weight:800; text-transform:uppercase; letter-spacing:1px; color:var(--primary); margin:20px 0 12px; padding-bottom:6px; border-bottom:2px solid var(--light); }
    .act-stats { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; }
    .act-stat-card { background:var(--gray-100); border-radius:12px; padding:16px; text-align:center; }
    .act-stat-num { font-size:28px; font-weight:900; color:var(--primary); }
    .act-stat-lbl { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:var(--text-muted); margin-top:2px; }
    .modal-progress-wrap { margin:10px 0; }
    .modal-progress-bg { height:12px; border-radius:6px; background:var(--gray-200); overflow:hidden; }
    .modal-progress-fill { height:100%; border-radius:6px; background:linear-gradient(90deg,var(--primary),#4caf50); transition:width 0.6s ease; }
    .modal-progress-label { display:flex; justify-content:space-between; font-size:12px; font-weight:700; color:var(--text-muted); margin-top:4px; }
    .field-editable { background:var(--gray-100); border-radius:10px; padding:14px 16px; font-size:14px; color:var(--dark); min-height:60px; line-height:1.5; position:relative; }
    .field-editable .edit-btn { position:absolute; top:8px; right:8px; background:var(--primary); color:white; border:none; border-radius:6px; padding:4px 10px; font-size:11px; font-weight:700; cursor:pointer; opacity:0; transition:opacity 0.2s; }
    .field-editable:hover .edit-btn { opacity:1; }
    .inline-textarea { width:100%; border:2px solid var(--primary); border-radius:8px; padding:10px 12px; font-size:14px; font-family:Arial,sans-serif; resize:vertical; min-height:80px; outline:none; background:white; }
    .save-inline-btn { margin-top:6px; background:var(--primary); color:white; border:none; border-radius:8px; padding:6px 16px; font-size:13px; font-weight:700; cursor:pointer; }
    .cancel-inline-btn { margin-top:6px; margin-left:6px; background:var(--gray-200); color:var(--dark); border:none; border-radius:8px; padding:6px 14px; font-size:13px; font-weight:700; cursor:pointer; }
    .edad-table { width:100%; border-collapse:separate; border-spacing:0; }
    .edad-table th { font-size:11px; text-transform:uppercase; letter-spacing:1px; color:var(--text-muted); padding:8px 12px; border-bottom:2px solid var(--gray-200); }
    .edad-table td { font-size:14px; font-weight:700; padding:10px 12px; border-bottom:1px solid var(--gray-200); }
    @media(max-width:1024px){ .container{padding-left:20px;padding-right:20px;} .stats-row{grid-template-columns:1fr;} .filters-bar{flex-direction:column;} .design-text{display:none;} .det-grid{grid-template-columns:1fr;} .act-stats{grid-template-columns:1fr 1fr;} }
    </style>
</head>
<body>
<?php
$path_sb = __DIR__ . '/../../includes/components/';
if      ($mi_nivel === 1) @include $path_sb . 'sidebar_admin.php';
elseif  ($mi_nivel === 2) @include $path_sb . 'sidebar_circunscripcion.php';
elseif  ($mi_nivel === 3) @include $path_sb . 'sidebar_ce.php';
elseif  ($mi_nivel === 4) @include $path_sb . 'sidebar_regional.php';
elseif  ($mi_nivel === 5) @include $path_sb . 'sidebar_cz.php';
?>
<div class="design-text">SECCIONES</div>

<div class="container" id="mainContainer">
    <div class="page-header">
        <div>
            <div class="ai-badge"><i class="fas fa-layer-group"></i> &nbsp;<?php echo htmlspecialchars($badge_txt); ?></div>
            <h1 class="main-title">Secciones.</h1>
            <p class="description"><?php echo htmlspecialchars($scope_desc); ?></p>
        </div>
    </div>

    <?php if ($error_db): ?>
    <div style="background:#f8d7da;color:#721c24;padding:15px;border-radius:10px;margin-bottom:20px;">
        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_db); ?>
    </div>
    <?php endif; ?>

    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon verde"><i class="fas fa-layer-group"></i></div>
            <div><div class="stat-label">Secciones Totales</div><div class="stat-val" id="stat-total">—</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon amarillo"><i class="fas fa-users"></i></div>
            <div><div class="stat-label">Contactos Conseguidos</div><div class="stat-val" id="stat-contactos">—</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon azul"><i class="fas fa-bolt"></i></div>
            <div><div class="stat-label">Activaciones Totales</div><div class="stat-val" id="stat-activaciones">—</div></div>
        </div>
    </div>

    <div class="filters-bar">
        <?php if ($mi_nivel <= 3): ?>
        <div class="filter-group">
            <label class="form-label">Estado</label>
            <select class="form-input-styled" id="filtroEstado" onchange="filtrarMunicipios()">
                <option value="">Todos los estados</option>
            </select>
        </div>
        <div class="filter-group">
            <label class="form-label">Municipio</label>
            <select class="form-input-styled" id="filtroMunicipio" onchange="aplicarFiltros()">
                <option value="">Todos los municipios</option>
            </select>
        </div>
        <?php elseif ($mi_nivel === 4): ?>
        <div class="filter-group">
            <label class="form-label">Zona</label>
            <select class="form-input-styled" id="filtroZona" onchange="aplicarFiltros()">
                <option value="">Todas las zonas</option>
                <?php foreach ($zonas_region as $z): ?>
                <option value="<?php echo htmlspecialchars($z['nombre']); ?>"><?php echo htmlspecialchars($z['nombre']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label class="form-label">Municipio</label>
            <select class="form-input-styled" id="filtroMunicipio" onchange="aplicarFiltros()">
                <option value="">Todos los municipios</option>
            </select>
        </div>
        <?php endif; ?>
        <div class="filter-group">
            <label class="form-label">Buscar</label>
            <input type="text" class="form-input-styled" id="filtroBuscar" placeholder="Número de sección…" oninput="aplicarFiltros()">
        </div>
        <button class="btn-secondary" onclick="limpiarFiltros()">
            <i class="fas fa-times"></i> Limpiar
        </button>
    </div>

    <div class="table-section">
        <div class="section-header">
            <div class="section-title">Tabla de Secciones</div>
            <div id="tableCount" style="font-size:13px;color:var(--text-muted);font-weight:600;"></div>
        </div>
        <div style="overflow-x:auto;">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th class="sortable-th" onclick="sortBy('nombre_seccion')">Sección <span class="sort-arrows" id="sort-nombre_seccion">↕</span></th>
                        <?php if ($mi_nivel <= 2): ?><th class="sortable-th" onclick="sortBy('estado')">Estado <span class="sort-arrows" id="sort-estado">↕</span></th><?php endif; ?>
                        <?php if ($mi_nivel <= 3): ?><th class="sortable-th" onclick="sortBy('municipio')">Municipio <span class="sort-arrows" id="sort-municipio">↕</span></th><?php endif; ?>
                        <?php if ($mi_nivel === 4): ?><th>Zona</th><?php endif; ?>
                        <th class="sortable-th" onclick="sortBy('lista_nominal')">Lista Nominal <span class="sort-arrows" id="sort-lista_nominal">↕</span></th>
                        <th class="sortable-th" onclick="sortBy('meta_contactos')">Meta <span class="sort-arrows" id="sort-meta_contactos">↕</span></th>
                        <th class="sortable-th" onclick="sortBy('contactos_conseguidos')">Contactos <span class="sort-arrows" id="sort-contactos_conseguidos">↕</span></th>
                        <th>Avance</th>
                        <th class="sortable-th" onclick="sortBy('eventos_realizados')">Eventos <span class="sort-arrows" id="sort-eventos_realizados">↕</span></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="tablaBody">
                    <tr class="empty-row"><td colspan="10"><i class="fas fa-spinner fa-spin"></i> Cargando…</td></tr>
                </tbody>
            </table>
        </div>
        <div class="pagination" id="pagination-container"></div>
    </div>
</div>

<!-- MODAL DETALLE -->
<div class="modal" id="modal-det">
    <div class="modal-content">
        <button class="modal-close" onclick="cerrarModal('modal-det')"><i class="fas fa-times"></i></button>
        <h2 class="modal-title" id="det-titulo">—</h2>
        <div class="modal-subtitle" id="det-ubicacion">—</div>

        <div class="det-grid">
            <div class="det-item"><label>Sección</label><span id="det-seccion">—</span></div>
            <div class="det-item"><label>Estado / Municipio</label><span id="det-municipio">—</span></div>
            <div class="det-item"><label>Distrito Federal</label><span id="det-df">—</span></div>
            <div class="det-item"><label>Distrito Local</label><span id="det-dl">—</span></div>
            <div class="det-item"><label>Región Asignada</label><span id="det-region">—</span></div>
            <div class="det-item"><label>Zona Asignada</label><span id="det-zona">—</span></div>
        </div>

        <div class="det-section-title"><i class="fas fa-users"></i> Lista Nominal</div>
        <div class="det-grid" style="grid-template-columns:1fr 1fr 1fr;margin-bottom:16px;">
            <div class="det-item"><label><i class="fas fa-users" style="color:#6b7280;margin-right:4px;"></i> Total</label><span id="det-padron" style="font-size:22px;font-weight:900;color:#374151;">—</span></div>
            <div class="det-item"><label><i class="fas fa-mars" style="color:#3b82f6;margin-right:4px;"></i> Hombres</label><span id="det-hombres" style="font-size:22px;font-weight:900;color:#3b82f6;">—</span></div>
            <div class="det-item"><label><i class="fas fa-venus" style="color:#ec4899;margin-right:4px;"></i> Mujeres</label><span id="det-mujeres" style="font-size:22px;font-weight:900;color:#ec4899;">—</span></div>
        </div>
        <div style="overflow-x:auto;">
            <table class="edad-table">
                <thead><tr><th>Total</th><th>18–24</th><th>25–34</th><th>35–49</th><th>50–64</th><th>65+</th></tr></thead>
                <tbody id="det-nominal-body"><tr><td colspan="6" style="text-align:center;color:var(--text-muted);">—</td></tr></tbody>
            </table>
        </div>

        <div class="det-section-title"><i class="fas fa-address-book"></i> Contactos y Avance</div>
        <div class="det-grid">
            <div class="det-item">
                <label>Meta de Contactos</label>
                <div style="display:flex;align-items:center;gap:8px;margin-top:4px;">
                    <span id="det-meta" style="font-size:20px;font-weight:900;color:var(--dark);">—</span>
                    <button id="btn-edit-meta" onclick="activarEdicionMeta()"
                        style="background:rgba(45,122,62,0.1);color:var(--primary);border:none;border-radius:6px;padding:4px 10px;font-size:11px;font-weight:700;cursor:pointer;">
                        <i class="fas fa-pencil-alt"></i> Editar
                    </button>
                </div>
                <div id="meta-edit-wrap" style="display:none;flex-direction:column;gap:6px;margin-top:8px;">
                    <div style="display:flex;align-items:center;gap:6px;">
                        <span style="font-size:11px;font-weight:700;color:var(--text-muted);white-space:nowrap;">% de lista:</span>
                        <input type="number" id="input-meta-pct" min="0" max="100" step="0.1" placeholder="12" oninput="recalcularMetaDesde('pct')"
                            style="width:70px;padding:6px 10px;border:2px solid #e2e8f0;border-radius:8px;font-size:14px;font-weight:700;outline:none;font-family:Arial,sans-serif;">
                        <span style="font-size:11px;color:var(--text-muted);margin:0 4px;">→</span>
                        <input type="number" id="input-meta" min="0" oninput="recalcularMetaDesde('num')"
                            style="width:100px;padding:6px 10px;border:2px solid var(--primary);border-radius:8px;font-size:14px;font-weight:700;outline:none;font-family:Arial,sans-serif;">
                    </div>
                    <div style="display:flex;gap:6px;">
                        <button onclick="guardarMeta()" style="background:var(--primary);color:white;border:none;border-radius:8px;padding:7px 14px;font-size:13px;font-weight:700;cursor:pointer;">
                            <i class="fas fa-check"></i> Guardar</button>
                        <button onclick="cancelarEdicionMeta()" style="background:#eee;color:#555;border:none;border-radius:8px;padding:7px 12px;font-size:13px;font-weight:700;cursor:pointer;">
                            <i class="fas fa-times"></i></button>
                    </div>
                </div>
            </div>
            <div class="det-item">
                <label>Contactos Conseguidos</label>
                <div style="display:flex;align-items:center;gap:8px;margin-top:4px;">
                    <span id="det-contactos" style="font-size:20px;font-weight:900;color:var(--primary);">—</span>
                    <button id="btn-edit-contactos" onclick="activarEdicionContactos()"
                        style="background:rgba(45,122,62,0.1);color:var(--primary);border:none;border-radius:6px;padding:4px 10px;font-size:11px;font-weight:700;cursor:pointer;">
                        <i class="fas fa-pencil-alt"></i> Editar
                    </button>
                </div>
                <div id="contactos-edit-wrap" style="display:none;margin-top:8px;">
                    <input type="number" id="input-contactos" min="0"
                        style="width:120px;padding:8px 12px;border:2px solid var(--primary);border-radius:8px;font-size:15px;font-weight:700;outline:none;font-family:Arial,sans-serif;">
                    <button onclick="guardarContactos()"
                        style="margin-left:6px;background:var(--primary);color:white;border:none;border-radius:8px;padding:8px 14px;font-size:13px;font-weight:700;cursor:pointer;">
                        <i class="fas fa-check"></i></button>
                    <button onclick="cancelarEdicionContactos()"
                        style="margin-left:4px;background:#eee;color:#555;border:none;border-radius:8px;padding:8px 12px;font-size:13px;font-weight:700;cursor:pointer;">
                        <i class="fas fa-times"></i></button>
                </div>
            </div>
        </div>
        <div class="modal-progress-wrap">
            <div class="modal-progress-bg"><div class="modal-progress-fill" id="det-progress-fill" style="width:0%"></div></div>
            <div class="modal-progress-label"><span id="det-progress-pct">0%</span><span id="det-progress-rem">0 restantes</span></div>
        </div>
        <div class="det-grid" style="margin-top:16px;">
            <div class="det-item"><label>Eventos Realizados</label><span id="det-eventos">—</span></div>
            <div class="det-item"><label>Metas de Votos</label><span id="det-votos">—</span></div>
        </div>

        <div class="det-section-title"><i class="fas fa-edit"></i> Campos Abiertos</div>
        <div style="margin-bottom:14px;">
            <label class="form-label">Principales Necesidades</label>
            <div class="field-editable" id="det-necesidades"><span class="content-text">—</span><button class="edit-btn" onclick="activarEdicion('necesidades','det-necesidades')">Editar</button></div>
        </div>
        <div style="margin-bottom:14px;">
            <label class="form-label">Enfoque</label>
            <div class="field-editable" id="det-enfoque"><span class="content-text">—</span><button class="edit-btn" onclick="activarEdicion('enfoque','det-enfoque')">Editar</button></div>
        </div>
        <div style="margin-bottom:14px;">
            <label class="form-label">Perfil Socioeconómico</label>
            <div class="field-editable" id="det-perfil"><span class="content-text">—</span><button class="edit-btn" onclick="activarEdicion('perfil_socioeconomico','det-perfil')">Editar</button></div>
        </div>

        <div class="det-section-title"><i class="fas fa-bolt"></i> Activaciones</div>
        <div class="act-stats">
            <div class="act-stat-card"><div class="act-stat-num" id="det-act-total">—</div><div class="act-stat-lbl">Total</div></div>
            <div class="act-stat-card"><div class="act-stat-num" id="det-act-con">—</div><div class="act-stat-lbl">Con Candidato</div></div>
            <div class="act-stat-card"><div class="act-stat-num" id="det-act-sin">—</div><div class="act-stat-lbl">Sin Candidato</div></div>
        </div>

        <div style="display:flex;gap:10px;margin-top:30px;flex-wrap:wrap;">
            <button class="btn-secondary" onclick="cerrarModal('modal-det')"><i class="fas fa-times"></i> Cerrar</button>
        </div>
    </div>
</div>

<script>
// ── Datos PHP → JS ────────────────────────────────────────
const MI_NIVEL = <?php echo $mi_nivel; ?>;
const API_SECCIONES = '/api/secciones/secciones_api.php';

let secciones = <?php echo json_encode(array_values($secciones), JSON_UNESCAPED_UNICODE); ?>;
const GEO_DATA = <?php echo ($mi_nivel <= 3) ? $geo_js : '[]'; ?>;

let seccionActualId = null;
let currentPage = 1;
const PER_PAGE = 50;

// ── Helpers ───────────────────────────────────────────────
function fmt(n){ return n!=null ? parseInt(n).toLocaleString('es-MX') : '—'; }
function pct(c,m){ if(!m||m==0) return 0; return Math.min(100,Math.round((c/m)*100)); }

// ── Filtros ───────────────────────────────────────────────
function aplicarFiltros(){ currentPage=1; renderTabla(); }

function filtrarMunicipios(){
    const est = document.getElementById('filtroEstado')?.value || '';
    const selM = document.getElementById('filtroMunicipio');
    if (!selM) return;
    selM.innerHTML = '<option value="">Todos los municipios</option>';
    if (est && GEO_DATA.length) {
        const entry = GEO_DATA.find(e => e.label === est);
        if (entry) entry.municipios.forEach(m => { selM.innerHTML += `<option value="${m}">${m}</option>`; });
    }
    aplicarFiltros();
}

function limpiarFiltros(){
    ['filtroEstado','filtroMunicipio','filtroZona','filtroBuscar'].forEach(id => {
        const el = document.getElementById(id); if(el) el.value='';
    });
    const selM = document.getElementById('filtroMunicipio');
    if (selM) selM.innerHTML = '<option value="">Todos los municipios</option>';
    aplicarFiltros();
}

function seccionesFiltradas(){
    const est  = document.getElementById('filtroEstado')?.value?.toLowerCase()    || '';
    const mun  = document.getElementById('filtroMunicipio')?.value?.toLowerCase() || '';
    const zona = document.getElementById('filtroZona')?.value?.toLowerCase()      || '';
    const q    = document.getElementById('filtroBuscar')?.value?.toLowerCase()    || '';
    return secciones.filter(s => {
        const matchE = !est  || (s.estado||'').toLowerCase()===est;
        const matchM = !mun  || (s.municipio||'').toLowerCase()===mun;
        const matchZ = !zona || (s.zona_nombre||s.zona_asignada||'').toLowerCase()===zona;
        const matchQ = !q    || (s.nombre_seccion||'').toString().toLowerCase().includes(q);
        return matchE && matchM && matchZ && matchQ;
    });
}

// ── Render tabla ──────────────────────────────────────────
let sortColumn='nombre_seccion', sortDir='asc';

function renderTabla(){
    const all = seccionesFiltradas();
    document.getElementById('tableCount').textContent = all.length+' sección'+(all.length!==1?'es':'');
    document.getElementById('stat-total').textContent       = all.length;
    document.getElementById('stat-contactos').textContent   = fmt(all.reduce((a,s)=>a+(parseInt(s.contactos_conseguidos)||0),0));
    document.getElementById('stat-activaciones').textContent= fmt(all.reduce((a,s)=>a+(parseInt(s.activaciones_totales)||0),0));

    const tbody = document.getElementById('tablaBody');
    if(!all.length){
        tbody.innerHTML=`<tr class="empty-row"><td colspan="10"><i class="fas fa-search"></i> Sin resultados</td></tr>`;
        renderPaginacion(0); return;
    }

    const start=(currentPage-1)*PER_PAGE;
    const page=all.slice(start,start+PER_PAGE);

    tbody.innerHTML = page.map(s => {
        const meta = (parseInt(s.meta_contactos)||0) || Math.round((parseInt(s.lista_nominal)||0)*0.12);
        const cons = parseInt(s.contactos_conseguidos)||0;
        const p    = pct(cons,meta);

        let row = `<tr>
            <td><strong>${s.nombre_seccion||'—'}</strong></td>`;
        if (MI_NIVEL<=2) row+=`<td style="font-size:13px;color:var(--text-muted);">${s.estado||'—'}</td>`;
        if (MI_NIVEL<=3) row+=`<td style="font-size:13px;color:var(--text-muted);">${s.municipio||'—'}</td>`;
        if (MI_NIVEL===4) row+=`<td style="font-size:13px;color:var(--text-muted);">${s.zona_nombre||s.zona_asignada||'—'}</td>`;
        row+=`
            <td>${fmt(s.lista_nominal)}</td>
            <td>${fmt(meta)}</td>
            <td class="stat-cell">${fmt(cons)}</td>
            <td>
                <div class="progress-wrap">
                    <div class="progress-label">${p}%</div>
                    <div class="progress-bar-bg"><div class="progress-bar-fill" style="width:${p}%"></div></div>
                </div>
            </td>
            <td>${fmt(s.eventos_realizados)}</td>
            <td><button class="btn-detalle" onclick="verDetalle(${s.id})"><i class="fas fa-eye"></i> Detalles</button></td>
        </tr>`;
        return row;
    }).join('');

    renderPaginacion(all.length);
}

function renderPaginacion(total){
    const cont = document.getElementById('pagination-container');
    if(total<=PER_PAGE){ cont.innerHTML=''; return; }
    const pages = Math.ceil(total/PER_PAGE);
    let h=`<button class="page-btn ${currentPage===1?'disabled':''}" onclick="${currentPage>1?`cambiarPagina(${currentPage-1})`:''}">&lsaquo;</button>`;
    let s=Math.max(1,currentPage-2), e=Math.min(pages,currentPage+2);
    if(s>1){h+=`<button class="page-btn" onclick="cambiarPagina(1)">1</button>`; if(s>2)h+=`<span class="page-dots">…</span>`;}
    for(let i=s;i<=e;i++) h+=`<button class="page-btn${i===currentPage?' active':''}" onclick="cambiarPagina(${i})">${i}</button>`;
    if(e<pages){if(e<pages-1)h+=`<span class="page-dots">…</span>`;h+=`<button class="page-btn" onclick="cambiarPagina(${pages})">${pages}</button>`;}
    h+=`<button class="page-btn ${currentPage===pages?'disabled':''}" onclick="${currentPage<pages?`cambiarPagina(${currentPage+1})`:''}">&rsaquo;</button>`;
    cont.innerHTML=h;
}

function cambiarPagina(p){
    const total=seccionesFiltradas().length;
    if(p<1||p>Math.ceil(total/PER_PAGE)) return;
    currentPage=p; renderTabla();
    document.querySelector('.table-section').scrollIntoView({behavior:'smooth',block:'start'});
}

// ── Ordenamiento ──────────────────────────────────────────
function sortBy(col){
    if(sortColumn===col) sortDir=sortDir==='asc'?'desc':'asc';
    else{sortColumn=col;sortDir='asc';}
    document.querySelectorAll('.sort-arrows').forEach(el=>{el.textContent='↕';el.className='sort-arrows';});
    document.querySelectorAll('.sortable-th').forEach(el=>el.classList.remove('sort-active'));
    const arrow=document.getElementById('sort-'+col);
    if(arrow){arrow.textContent=sortDir==='asc'?'↑':'↓';arrow.classList.add(sortDir);arrow.closest('th')?.classList.add('sort-active');}
    secciones.sort((a,b)=>{
        let va=a[col]??'', vb=b[col]??'';
        const na=parseFloat(va),nb=parseFloat(vb);
        if(!isNaN(na)&&!isNaN(nb)) return sortDir==='asc'?na-nb:nb-na;
        va=va.toString().toLowerCase();vb=vb.toString().toLowerCase();
        return sortDir==='asc'?va.localeCompare(vb,'es'):vb.localeCompare(va,'es');
    });
    currentPage=1; renderTabla();
}

// ── Inicializar filtros de estado ─────────────────────────
window.addEventListener('DOMContentLoaded', ()=>{
    if(MI_NIVEL<=3 && GEO_DATA.length){
        const selE=document.getElementById('filtroEstado');
        if(selE){
            GEO_DATA.forEach(e=>{selE.innerHTML+=`<option value="${e.label}">${e.label}</option>`;});
        }
    }
    renderTabla();
});

// ── DETALLE ───────────────────────────────────────────────
function verDetalle(id){
    id=parseInt(id);
    const s=secciones.find(x=>parseInt(x.id)===id); if(!s) return;
    seccionActualId=id;
    document.getElementById('det-titulo').textContent   = 'Sección '+s.nombre_seccion;
    document.getElementById('det-ubicacion').textContent= [s.estado,s.municipio].filter(Boolean).join(' › ')||'—';
    document.getElementById('det-seccion').textContent  = s.nombre_seccion||'—';
    document.getElementById('det-municipio').textContent= [s.estado,s.municipio].filter(Boolean).join(' / ')||'—';
    document.getElementById('det-df').textContent       = s.distrito_federal||'—';
    document.getElementById('det-dl').textContent       = s.distrito_local||'—';
    document.getElementById('det-region').textContent   = s.region_asignada||'—';
    document.getElementById('det-zona').textContent     = s.zona_asignada||s.zona_nombre||'—';
    document.getElementById('det-padron').textContent   = fmt(s.lista_nominal);
    document.getElementById('det-hombres').textContent  = fmt(s.lista_hombres);
    document.getElementById('det-mujeres').textContent  = fmt(s.lista_mujeres);
    document.getElementById('det-nominal-body').innerHTML=`<tr>
        <td><strong>${fmt(s.lista_nominal)}</strong></td>
        <td>${fmt(s.grupo_edad_18_24)}</td>
        <td>${fmt(s.grupo_edad_25_34)}</td>
        <td>${fmt(s.grupo_edad_35_49)}</td>
        <td>${fmt(s.grupo_edad_50_64)}</td>
        <td>${fmt(s.grupo_edad_65_mas)}</td>
    </tr>`;
    let meta=parseInt(s.meta_contactos)||0;
    const cons=parseInt(s.contactos_conseguidos)||0;
    if(meta===0&&parseInt(s.lista_nominal)>0) meta=Math.round(parseInt(s.lista_nominal)*0.12);
    const p=pct(cons,meta);
    document.getElementById('det-meta').textContent             = fmt(meta);
    document.getElementById('det-contactos').textContent        = fmt(cons);
    document.getElementById('det-progress-fill').style.width   = p+'%';
    document.getElementById('det-progress-pct').textContent    = p+'%';
    document.getElementById('det-progress-rem').textContent    = fmt(Math.max(0,meta-cons))+' restantes';
    document.getElementById('det-eventos').textContent         = fmt(s.eventos_realizados);
    document.getElementById('det-votos').textContent           = fmt(s.metas_votos);
    setFieldEditable('det-necesidades',s.principales_necesidades);
    setFieldEditable('det-enfoque',s.enfoque);
    setFieldEditable('det-perfil',s.perfil_socioeconomico);
    document.getElementById('det-act-total').textContent       = fmt(s.activaciones_totales);
    document.getElementById('det-act-con').textContent         = fmt(s.con_candidato);
    document.getElementById('det-act-sin').textContent         = fmt(s.sin_candidato);
    // Reset edición
    cancelarEdicionMeta(); cancelarEdicionContactos();
    document.getElementById('modal-det').classList.add('open');
}

// ── Edición inline Meta ───────────────────────────────────
function activarEdicionMeta(){
    const s=secciones.find(x=>parseInt(x.id)===parseInt(seccionActualId));
    const nom=parseInt(s?.lista_nominal)||0;
    let val=(parseInt(s?.meta_contactos)||0)||Math.round(nom*0.12);
    const pctVal=nom>0?parseFloat(((val/nom)*100).toFixed(1)):12;
    document.getElementById('input-meta').value=val;
    document.getElementById('input-meta-pct').value=pctVal;
    document.getElementById('meta-edit-wrap').style.display='flex';
    document.getElementById('btn-edit-meta').style.display='none';
    document.getElementById('input-meta-pct').focus();
}
function recalcularMetaDesde(origen){
    const s=secciones.find(x=>parseInt(x.id)===parseInt(seccionActualId));
    const nom=parseInt(s?.lista_nominal)||0;
    if(origen==='pct'){const p=parseFloat(document.getElementById('input-meta-pct').value)||0;document.getElementById('input-meta').value=Math.round(nom*p/100);}
    else{const n=parseInt(document.getElementById('input-meta').value)||0;document.getElementById('input-meta-pct').value=nom>0?parseFloat(((n/nom)*100).toFixed(1)):0;}
}
function cancelarEdicionMeta(){document.getElementById('meta-edit-wrap').style.display='none';document.getElementById('btn-edit-meta').style.display='inline-flex';}
async function guardarMeta(){
    const valor=parseInt(document.getElementById('input-meta').value)||0;
    const fd=new FormData();fd.append('action','save_meta_contactos');fd.append('id',seccionActualId);fd.append('value',valor);
    try{
        const res=await fetch(window.location.pathname,{method:'POST',body:fd});
        const data=await res.json();if(!data.ok) throw new Error(data.msg||'Error');
        const s=secciones.find(x=>parseInt(x.id)===parseInt(seccionActualId));
        if(s) s.meta_contactos=valor;
        document.getElementById('det-meta').textContent=fmt(valor);
        const cons=parseInt(s?.contactos_conseguidos)||0;
        const p=pct(cons,valor);
        document.getElementById('det-progress-fill').style.width=p+'%';
        document.getElementById('det-progress-pct').textContent=p+'%';
        document.getElementById('det-progress-rem').textContent=fmt(Math.max(0,valor-cons))+' restantes';
        cancelarEdicionMeta(); actualizarFilaTabla(seccionActualId);
    }catch(err){alert('Error al guardar: '+err.message);}
}

// ── Edición inline Contactos ──────────────────────────────
function activarEdicionContactos(){
    const s=secciones.find(x=>parseInt(x.id)===parseInt(seccionActualId));
    document.getElementById('input-contactos').value=parseInt(s?.contactos_conseguidos)||0;
    document.getElementById('contactos-edit-wrap').style.display='flex';
    document.getElementById('btn-edit-contactos').style.display='none';
    document.getElementById('input-contactos').focus();
}
function cancelarEdicionContactos(){document.getElementById('contactos-edit-wrap').style.display='none';document.getElementById('btn-edit-contactos').style.display='inline-flex';}
async function guardarContactos(){
    const valor=parseInt(document.getElementById('input-contactos').value)||0;
    const fd=new FormData();fd.append('action','save_contactos');fd.append('id',seccionActualId);fd.append('value',valor);
    try{
        const res=await fetch(window.location.pathname,{method:'POST',body:fd});
        const data=await res.json();if(!data.ok) throw new Error(data.msg||'Error');
        const s=secciones.find(x=>parseInt(x.id)===parseInt(seccionActualId));
        if(s) s.contactos_conseguidos=valor;
        document.getElementById('det-contactos').textContent=fmt(valor);
        const meta=parseInt(s?.meta_contactos)||0;
        const p=pct(valor,meta);
        document.getElementById('det-progress-fill').style.width=p+'%';
        document.getElementById('det-progress-pct').textContent=p+'%';
        document.getElementById('det-progress-rem').textContent=fmt(Math.max(0,meta-valor))+' restantes';
        cancelarEdicionContactos(); actualizarFilaTabla(seccionActualId);
    }catch(err){alert('Error al guardar: '+err.message);}
}

// ── Campos abiertos inline ────────────────────────────────
function setFieldEditable(divId,value){
    const div=document.getElementById(divId);
    div.innerHTML=`<span class="content-text">${value||'<em style="color:var(--text-muted)">Sin información</em>'}</span><button class="edit-btn" onclick="activarEdicion('${divId.replace('det-','')}','${divId}')">Editar</button>`;
}
const FIELD_MAP={'necesidades':'principales_necesidades','enfoque':'enfoque','perfil_socioeconomico':'perfil_socioeconomico'};
function activarEdicion(field,divId){
    const div=document.getElementById(divId);
    const s=secciones.find(x=>parseInt(x.id)===parseInt(seccionActualId));
    const db=FIELD_MAP[field]||field;
    const actual=s?(s[db]||''):'';
    div.innerHTML=`<textarea class="inline-textarea" id="inline-ta-${field}">${actual}</textarea><br>
        <button class="save-inline-btn" onclick="guardarInline('${field}','${divId}')"><i class="fas fa-check"></i> Guardar</button>
        <button class="cancel-inline-btn" onclick="setFieldEditable('${divId}',document.getElementById('inline-ta-${field}').defaultValue)">Cancelar</button>`;
}
async function guardarInline(field,divId){
    const ta=document.getElementById('inline-ta-'+field);
    const valor=ta.value;
    const db=FIELD_MAP[field]||field;
    const fd=new FormData();fd.append('action','update_field');fd.append('id',seccionActualId);fd.append('field',db);fd.append('value',valor);
    try{
        const res=await fetch(API_SECCIONES,{method:'POST',body:fd});
        const data=await res.json();if(!data.ok) throw new Error(data.msg||'Error');
        const s=secciones.find(x=>parseInt(x.id)===parseInt(seccionActualId));
        if(s) s[db]=valor;
        setFieldEditable(divId,valor);
    }catch(err){alert('Error al guardar: '+err.message);}
}

// ── Actualizar fila tabla sin re-render completo ──────────
function actualizarFilaTabla(id){
    const s=secciones.find(x=>parseInt(x.id)===parseInt(id)); if(!s) return;
    const meta=(parseInt(s.meta_contactos)||0)||Math.round((parseInt(s.lista_nominal)||0)*0.12);
    const cons=parseInt(s.contactos_conseguidos)||0;
    const p=pct(cons,meta);
    const btn=document.querySelector(`#tablaBody button[onclick="verDetalle(${id})"]`);
    if(!btn) return;
    const row=btn.closest('tr'); if(!row) return;
    const cells=row.cells;
    // La columna Contactos es la que está 2 antes del botón de avance
    // Se busca por clase stat-cell
    const tdCons=row.querySelector('.stat-cell');
    if(tdCons) tdCons.textContent=fmt(cons);
    const tdBar=row.querySelector('.progress-wrap');
    if(tdBar) tdBar.outerHTML=`<div class="progress-wrap"><div class="progress-label">${p}%</div><div class="progress-bar-bg"><div class="progress-bar-fill" style="width:${p}%"></div></div></div>`;
    document.getElementById('stat-contactos').textContent=fmt(seccionesFiltradas().reduce((a,s)=>a+(parseInt(s.contactos_conseguidos)||0),0));
}

// ── Modales ───────────────────────────────────────────────
function cerrarModal(id){document.getElementById(id).classList.remove('open');}
document.querySelectorAll('.modal').forEach(el=>el.addEventListener('click',e=>{if(e.target===el)cerrarModal(el.id);}));

// ── Sidebar padding sync ──────────────────────────────────
(function(){
    const container=document.getElementById('mainContainer');
    const sidebar=document.getElementById('sidebar');
    if(!sidebar||!container) return;
    const obs=new MutationObserver(()=>{container.style.paddingLeft=sidebar.classList.contains('collapsed')?'110px':'330px';});
    obs.observe(sidebar,{attributes:true,attributeFilter:['class']});
})();
</script>
</body>
</html>