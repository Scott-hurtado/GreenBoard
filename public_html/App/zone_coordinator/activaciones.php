<?php
// ── Seguridad ──────────────────────────────────────────────
if (file_exists(__DIR__ . '/../../includes/bootstrap.php')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
} else { session_start(); }

$rol = $_SESSION['user_role'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;
$user_zona = $_SESSION['user_zona_nombre'] ?? '';
$user_estado = $_SESSION['user_estado'] ?? '';

// Solo permitimos roles operativos a nivel zona y superiores
$permitidos = ['coordinador_zona', 'coordinador_regional', 'coordinador_estatal', 'admin', 'superadmin'];
if (!in_array($rol, $permitidos)) { header('Location: /public/index.php'); exit(); }

// ── Conexión y carga de datos ──────────────────────────────
$activaciones = [];
$estados_mx_db = []; // Para el mapa (centrado en los municipios de su zona)
$seccionesZona = [];
$municipiosZona = [];
$seccionMunicipioMap = [];
$error_db = null;

try {
    $dbConfigPath = __DIR__ . '/../../includes/database.php';
    $dbConfig = file_exists($dbConfigPath) ? require $dbConfigPath : [
        'host'=>'localhost','database'=>'u881824383_pverde',
        'username'=>'u881824383_proyverde','password'=>'PProyectoverde8#',
        'charset'=>'utf8mb4',
        'options'=>[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
    ];
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options'] ?? []);

    // 1. CORRECCIÓN: Obtener SECCIONES desde zonas_estatales por coordinador_id
    $zonaEstructura = null;
    if ($user_id > 0) {
        $stmtZ = $pdo->prepare("
            SELECT z.*
            FROM coordinador_zona cz
            INNER JOIN zonas_estatales z ON cz.zona = z.id
            WHERE cz.id = ? LIMIT 1
        ");
        $stmtZ->execute([$user_id]);
        $zonaEstructura = $stmtZ->fetch();
    }

    if ($zonaEstructura && !empty($zonaEstructura['secciones'])) {
        $arr = json_decode($zonaEstructura['secciones'], true);
        if (is_array($arr)) {
            usort($arr, fn($a, $b) => (int)$a - (int)$b);
            $seccionesZona = $arr;
        }
    }

    // Municipios: extraer de secciones usando las secciones de la zona
    if (!empty($seccionesZona)) {
        $placeholdersSec = implode(',', array_fill(0, count($seccionesZona), '?'));
        // Intentar primero con tabla secciones (más completa)
        try {
            $stmtMun = $pdo->prepare("
                SELECT DISTINCT m.nombre AS municipio
                FROM secciones s
                LEFT JOIN municipios m ON m.id = s.municipio_id
                WHERE s.seccion IN ($placeholdersSec) AND s.estado_nombre = ?
                AND m.nombre IS NOT NULL AND m.nombre != ''
            ");
            $stmtMun->execute(array_merge($seccionesZona, [$user_estado]));
            $municipiosZona = array_unique(array_filter(array_column($stmtMun->fetchAll(), 'municipio')));
        } catch (Exception $e3) { /* fallback */ }

        // Fallback con tabla secciones_electorales si la anterior falla o da vacío
        if (empty($municipiosZona)) {
            try {
                $stmtMun2 = $pdo->prepare("
                    SELECT DISTINCT municipio FROM secciones_electorales
                    WHERE nombre_seccion IN ($placeholdersSec) AND activo = 1
                ");
                $stmtMun2->execute($seccionesZona);
                $municipiosZona = array_unique(array_filter(array_column($stmtMun2->fetchAll(), 'municipio')));
            } catch (Exception $e4) { /* silencioso */ }
        }

        // Mapeo sección -> municipio, para poder guardar el municipio correcto
        // al crear/editar una activación (antes se mandaba vacío y por eso
        // Regional no veía las activaciones registradas por Zona).
        try {
            $stmtSecMun = $pdo->prepare("
                SELECT s.seccion, m.nombre AS municipio
                FROM secciones s
                LEFT JOIN municipios m ON m.id = s.municipio_id
                WHERE s.seccion IN ($placeholdersSec) AND s.estado_nombre = ?
                AND m.nombre IS NOT NULL AND m.nombre != ''
            ");
            $stmtSecMun->execute(array_merge($seccionesZona, [$user_estado]));
            foreach ($stmtSecMun->fetchAll() as $r) {
                $seccionMunicipioMap[strval($r['seccion'])] = $r['municipio'];
            }
        } catch (Exception $e5) { /* fallback abajo */ }

        if (empty($seccionMunicipioMap)) {
            try {
                $stmtSecMun2 = $pdo->prepare("
                    SELECT nombre_seccion AS seccion, municipio
                    FROM secciones_electorales
                    WHERE nombre_seccion IN ($placeholdersSec) AND activo = 1
                ");
                $stmtSecMun2->execute($seccionesZona);
                foreach ($stmtSecMun2->fetchAll() as $r) {
                    $seccionMunicipioMap[strval($r['seccion'])] = $r['municipio'];
                }
            } catch (Exception $e6) { /* silencioso */ }
        }
    }

    // 2. Cargar SOLO las activaciones del estado + secciones de esta zona
    if (!empty($seccionesZona) && !empty($user_estado)) {
        $placeholders = implode(',', array_fill(0, count($seccionesZona), '?'));
        
        $sqlActs = "
            SELECT id, codigo_referencia, titulo, tipo, estatus, estado, municipio,
                   DATE_FORMAT(fecha_actividad,'%Y-%m-%d') AS fecha_actividad,
                   coordinador, seccion_electoral, contactos_estimados,
                   asistentes_estimados, costo, notas,
                   latitud, longitud, fotos, enlaces, creado_por_rol
            FROM activaciones
            WHERE activo = 1
              AND estado = ?
              AND seccion_electoral IN ($placeholders)
            ORDER BY fecha_creacion DESC
        ";
        
        // Primero el estado, luego las secciones
        $paramsActs = array_merge([$user_estado], $seccionesZona);
        $stmt = $pdo->prepare($sqlActs);
        $stmt->execute($paramsActs);
        $activaciones = $stmt->fetchAll();
    }

    // 3. Cargar coordenadas solo de los municipios de su zona para el centrado del mapa
    if (!empty($municipiosZona) && !empty($user_estado)) {
        $placeholdersMun = implode(',', array_fill(0, count($municipiosZona), '?'));
        // Pasamos primero el estado, y luego los municipios
        $paramsGeo = array_merge([$user_estado], $municipiosZona);
        
        $stmtGeo = $pdo->prepare("
            SELECT 
                e.nombre as estado,
                m.nombre as municipio,
                AVG(c.latitud) as mun_lat,
                AVG(c.longitud) as mun_lng
            FROM estados e
            INNER JOIN municipios m ON e.clave = m.estado_id
            INNER JOIN colonias c ON m.id = c.municipio
            WHERE e.nombre = ? AND m.nombre IN ($placeholdersMun)
            GROUP BY e.clave, m.id
            ORDER BY m.nombre ASC
        ");
        $stmtGeo->execute($paramsGeo);
        
        foreach ($stmtGeo->fetchAll() as $row) {
            $est = $row['estado'];
            if (!isset($estados_mx_db[$est])) {
                $estados_mx_db[$est] = [
                    'lat' => (float)($row['mun_lat'] ?? 23.6345),
                    'lng' => (float)($row['mun_lng'] ?? -102.5528),
                    'municipios' => []
                ];
            }
            if ($row['municipio']) {
                $estados_mx_db[$est]['municipios'][$row['municipio']] = [
                    'lat' => (float)$row['mun_lat'],
                    'lng' => (float)$row['mun_lng']
                ];
            }
        }
    }
} catch (PDOException $e) {
    error_log("Error DB: " . $e->getMessage());
    $error_db = "Error al cargar la base de datos.";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activaciones - Zona <?= htmlspecialchars($user_zona) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
    :root { --primary:#2d7a3e; --primary-dark:#1f5a2d; --dark:#1a1a1a; --light:#f0f7f0; --white:#ffffff; --gray-100:#f8f9fa; --gray-200:#e9ecef; --text-muted:#6c757d; --shadow-sm:0 10px 30px rgba(0,0,0,0.05); }
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family:'Arial',sans-serif; background:var(--light); color:var(--dark); overflow-x:hidden; }
    .container { max-width:100%; margin:0; background:var(--white); min-height:100vh; position:relative; padding-left:330px; padding-right:50px; padding-bottom:40px; transition:padding-left 0.3s ease; }
    .design-text { position:fixed; left:290px; top:50%; writing-mode:vertical-rl; font-size:13px; font-weight:500; color:var(--primary); opacity:0.3; letter-spacing:2px; z-index:0; pointer-events:none; }
    .page-header { padding:50px 0 30px 0; position:relative; z-index:2; display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:20px; }
    .ai-badge { color:var(--primary); font-size:13px; font-weight:800; letter-spacing:4px; margin-bottom:15px; display:inline-block; text-transform:uppercase; background:rgba(45,122,62,0.1); padding:5px 15px; border-radius:20px; }
    .main-title { font-size:54px; font-weight:900; line-height:1; margin-bottom:15px; color:var(--dark); }
    .description { color:#666; font-size:16px; line-height:1.6; max-width:600px; }
    .header-actions { display:flex; gap:12px; align-items:center; flex-wrap:wrap; }
    .btn-primary-action { background:var(--primary); color:white; border:none; padding:12px 25px; border-radius:12px; font-weight:700; cursor:pointer; transition:all 0.3s; display:flex; align-items:center; gap:10px; font-size:14px; box-shadow:0 4px 15px rgba(45,122,62,0.3); }
    .btn-primary-action:hover { background:var(--primary-dark); transform:translateY(-2px); }
    .stats-row { display:grid; grid-template-columns:repeat(3,1fr); gap:20px; margin-bottom:30px; }
    .stat-card { background:var(--white); border:1px solid var(--gray-200); border-radius:20px; padding:25px 30px; box-shadow:var(--shadow-sm); display:flex; align-items:center; gap:18px; }
    .stat-icon { width:52px; height:52px; border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:22px; flex-shrink:0; }
    .stat-icon.verde { background:rgba(45,122,62,.1); color:var(--primary); }
    .stat-icon.amarillo { background:#fffbeb; color:#d97706; }
    .stat-icon.azul { background:#eff6ff; color:#2196f3; }
    .stat-label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:var(--text-muted); margin-bottom:5px; }
    .stat-val { font-size:30px; font-weight:900; color:var(--dark); line-height:1; }
    .filters-bar { background:var(--white); border:1px solid var(--gray-200); border-radius:16px; padding:20px; margin-bottom:30px; display:flex; gap:15px; align-items:flex-end; box-shadow:var(--shadow-sm); flex-wrap:wrap; }
    .filter-group { display:flex; flex-direction:column; gap:8px; flex:1; min-width:160px; }
    .form-label { font-size:12px; font-weight:700; text-transform:uppercase; color:var(--text-muted); letter-spacing:0.5px; }
    .form-input-styled { width:100%; padding:12px 15px; border-radius:10px; border:2px solid var(--gray-200); background:var(--gray-100); font-size:14px; font-weight:600; color:var(--dark); transition:all 0.3s; outline:none; appearance:none; font-family:Arial,sans-serif; }
    .form-input-styled:focus { border-color:var(--primary); background:var(--white); }
    textarea.form-input-styled { resize:vertical; min-height:90px; font-weight:400; }
    .table-section { background:var(--white); border-radius:20px; padding:30px; border:1px solid var(--gray-200); box-shadow:var(--shadow-sm); }
    .section-header { margin-bottom:25px; display:flex; justify-content:space-between; align-items:center; }
    .section-title { font-size:20px; font-weight:800; color:var(--dark); }
        .modern-table { width:100%; border-collapse:separate; border-spacing:0; table-layout:fixed; }
    .modern-table th { text-align:left; padding:12px 12px; color:var(--text-muted); font-size:11px; text-transform:uppercase; letter-spacing:0.5px; border-bottom:2px solid var(--gray-200); }
    .modern-table td { padding:14px 12px; border-bottom:1px solid var(--gray-200); font-size:13px; color:var(--dark); font-weight:500; vertical-align:middle; transition:background 0.2s; }
    .modern-table tr:hover td { background:var(--gray-100); }
    .modern-table tr:last-child td { border-bottom:none; }
    .badge { padding:5px 12px; border-radius:20px; font-size:11px; font-weight:700; text-transform:uppercase; display:inline-block; }
    .badge-programada { background:#fff7ed; color:#d97706; border:1px solid #fde68a; }
    .badge-en-curso { background:#eff6ff; color:#1e88e5; border:1px solid #bfdbfe; }
    .badge-completada { background:#f0fdf4; color:#16a34a; border:1px solid #bbf7d0; }
    .badge-cancelada { background:#fff5f5; color:#e53935; border:1px solid #fecaca; }
    .tipo-chip { display:inline-block; padding:3px 10px; border-radius:8px; font-size:11px; font-weight:600; background:var(--gray-200); color:var(--text-muted); }
    .stat-cell { font-size:16px; font-weight:800; color:var(--primary); }
    .stat-cell.neutral { color:var(--dark); }
    .stat-cell-label { font-size:10px; font-weight:700; text-transform:uppercase; color:var(--text-muted); letter-spacing:0.5px; }
    .action-cell { text-align:right; position:relative; }
    .action-btn { background:none; border:none; cursor:pointer; color:var(--text-muted); font-size:16px; padding:8px 12px; border-radius:50%; transition:all 0.2s; }
    .action-btn:hover, .action-btn.active { background:var(--gray-200); color:var(--primary); }
    .dropdown-menu { position:absolute; right:20px; top:50px; width:160px; background:var(--white); border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,0.15); border:1px solid var(--gray-200); z-index:100; padding:8px 0; opacity:0; visibility:hidden; transform:translateY(10px); transition:all 0.25s cubic-bezier(0.175,0.885,0.32,1.275); }
    .dropdown-menu.show { opacity:1; visibility:visible; transform:translateY(0); }
    .dropdown-item { display:flex; align-items:center; gap:10px; padding:10px 20px; width:100%; border:none; background:none; text-align:left; cursor:pointer; font-size:13px; font-weight:600; color:var(--dark); transition:background 0.2s; font-family:Arial,sans-serif; }
    .dropdown-item:hover { background:var(--gray-100); color:var(--primary); }
    .dropdown-item.delete-item:hover { background:#ffeaea; color:#d32f2f; }
    .empty-row td { text-align:center; padding:60px 20px; color:var(--text-muted); font-size:14px; }
    .modal { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(255,255,255,0.9); backdrop-filter:blur(5px); z-index:2000; align-items:center; justify-content:center; opacity:0; transition:opacity 0.3s ease; }
    .modal.open { display:flex; opacity:1; }
    .modal-content { background:var(--white); border-radius:20px; border:1px solid var(--gray-200); box-shadow:0 25px 50px rgba(0,0,0,0.1); width:90%; max-width:720px; padding:40px; position:relative; transform:translateY(30px); opacity:0; transition:all 0.4s ease-out; max-height:90vh; overflow-y:auto; }
    .modal.open .modal-content { transform:translateY(0); opacity:1; }
    .close-modal { position:absolute; top:25px; right:25px; font-size:22px; cursor:pointer; color:#ccc; width:40px; height:40px; display:flex; align-items:center; justify-content:center; border-radius:50%; transition:all 0.3s cubic-bezier(0.175,0.885,0.32,1.275); }
    .close-modal:hover { color:var(--primary); background:#f0f7f0; transform:rotate(90deg) scale(1.1); }
    .form-group { display:flex; flex-direction:column; gap:8px; }
    .estatus-selector { display:grid; grid-template-columns:repeat(4,1fr); gap:8px; }
    .est-btn { padding:10px 6px; border-radius:10px; border:2px solid var(--gray-200); font-size:11px; font-weight:700; cursor:pointer; text-align:center; background:var(--gray-100); color:var(--text-muted); transition:0.2s; font-family:Arial,sans-serif; }
    .est-btn.sel-programada { background:#fff7ed; border-color:#f59e0b; color:#d97706; }
    .est-btn.sel-en-curso { background:#eff6ff; border-color:#2196f3; color:#1e88e5; }
    .est-btn.sel-completada { background:#f0fdf4; border-color:#2d7a3e; color:#16a34a; }
    .est-btn.sel-cancelada { background:#fff5f5; border-color:#e53935; color:#e53935; }
    #mapa { height:220px; border-radius:12px; border:2px solid var(--gray-200); }
    #det-mapa { height:200px; border-radius:12px; border:2px solid var(--gray-200); margin-top:10px; }
    .map-coords { display:flex; gap:10px; margin-top:8px; }
    .map-note { font-size:12px; color:var(--text-muted); margin-bottom:6px; }
    .modal-footer { display:flex; justify-content:space-between; align-items:center; margin-top:30px; padding-top:25px; border-top:1px solid var(--gray-200); }
    .btn-save { background:var(--primary); color:white; border:none; padding:12px 28px; border-radius:12px; font-weight:700; cursor:pointer; transition:all 0.3s; font-size:14px; box-shadow:0 4px 15px rgba(45,122,62,0.3); font-family:Arial,sans-serif; }
    .btn-save:hover { background:var(--primary-dark); transform:translateY(-1px); }
    .btn-cancel { background:var(--white); color:var(--text-muted); border:1px solid var(--gray-200); padding:12px 22px; border-radius:12px; font-weight:600; cursor:pointer; transition:0.3s; font-size:13px; font-family:Arial,sans-serif; }
    .btn-cancel:hover { border-color:var(--dark); color:var(--dark); }
    .btn-danger { background:#ffeaea; color:#d32f2f; border:1px solid #fecaca; padding:12px 20px; border-radius:12px; font-weight:700; cursor:pointer; transition:0.3s; font-size:13px; font-family:Arial,sans-serif; }
    .btn-danger:hover { background:#fee2e2; }
    .detail-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:20px; margin-top:20px; padding-top:20px; border-top:1px solid var(--gray-200); }
    .detail-item strong { display:block; font-size:11px; color:var(--text-muted); text-transform:uppercase; margin-bottom:5px; letter-spacing:0.5px; }
    .detail-item span { font-size:15px; color:var(--dark); font-weight:600; }
    .detail-item span.big { font-size:24px; font-weight:900; color:var(--primary); }
    .animate-up { animation:fadeInUp 0.6s ease forwards; opacity:0; }
    @keyframes fadeInUp { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }
    .animate-delay-1 { animation-delay:0.1s; }
    .animate-delay-2 { animation-delay:0.2s; }
    .btn-save.loading { opacity:0.7; pointer-events:none; }
    .fotos-upload-area { border:2px dashed var(--gray-200); border-radius:14px; padding:28px 20px; text-align:center; cursor:pointer; transition:all 0.3s; background:var(--gray-100); position:relative; }
    .fotos-upload-area:hover, .fotos-upload-area.drag-over { border-color:var(--primary); background:rgba(45,122,62,0.04); }
    .fotos-upload-area .upload-icon { font-size:28px; color:var(--primary); opacity:0.6; margin-bottom:8px; }
    .fotos-upload-area p { font-size:13px; color:var(--text-muted); font-weight:600; margin:0; }
    .fotos-upload-area p span { color:var(--primary); text-decoration:underline; }
    #foto-input { display:none; }
    .fotos-preview-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(90px,1fr)); gap:10px; margin-top:12px; }
    .foto-thumb { position:relative; border-radius:10px; overflow:hidden; aspect-ratio:1; background:var(--gray-200); box-shadow:0 2px 8px rgba(0,0,0,0.08); }
    .foto-thumb img { width:100%; height:100%; object-fit:cover; display:block; transition:transform 0.2s; }
    .foto-thumb:hover img { transform:scale(1.04); }
    .foto-thumb .remove-foto { position:absolute; top:4px; right:4px; background:rgba(0,0,0,0.6); color:#fff; border:none; border-radius:50%; width:22px; height:22px; cursor:pointer; font-size:11px; display:flex; align-items:center; justify-content:center; transition:background 0.2s; opacity:0; }
    .foto-thumb:hover .remove-foto { opacity:1; }
    .foto-thumb .remove-foto:hover { background:rgba(211,47,47,0.9); }
    .foto-counter { font-size:11px; font-weight:700; color:var(--text-muted); margin-top:8px; text-align:right; letter-spacing:0.5px; }
    .det-galeria { margin-top:20px; }
    .det-galeria-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(80px,1fr)); gap:8px; margin-top:10px; }
    .det-galeria-grid img { width:100%; aspect-ratio:1; object-fit:cover; border-radius:8px; cursor:pointer; transition:transform 0.2s,box-shadow 0.2s; }
    .det-galeria-grid img:hover { transform:scale(1.05); box-shadow:0 4px 12px rgba(0,0,0,0.15); }
    #lightbox { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.88); z-index:9999; align-items:center; justify-content:center; flex-direction:column; }
    #lightbox.open { display:flex; }
    #lightbox img { max-width:90vw; max-height:80vh; border-radius:12px; box-shadow:0 20px 60px rgba(0,0,0,0.5); object-fit:contain; }
    #lightbox .lb-close { position:absolute; top:20px; right:25px; color:#fff; font-size:28px; cursor:pointer; opacity:0.8; transition:opacity 0.2s; background:none; border:none; }
    #lightbox .lb-close:hover { opacity:1; }
    #lightbox .lb-nav { display:flex; gap:20px; margin-top:16px; }
    #lightbox .lb-btn { background:rgba(255,255,255,0.15); color:#fff; border:none; border-radius:8px; padding:8px 20px; cursor:pointer; font-size:14px; font-weight:700; transition:background 0.2s; }
    #lightbox .lb-btn:hover { background:rgba(255,255,255,0.3); }
    #lightbox .lb-counter { color:rgba(255,255,255,0.6); font-size:13px; margin-top:8px; }

    #modal-nav { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.55); backdrop-filter:blur(4px); z-index:3000; align-items:center; justify-content:center; }
    #modal-nav.open { display:flex; }
    .nav-box { background:#fff; border-radius:20px; box-shadow:0 30px 60px rgba(0,0,0,0.18); padding:36px 40px; width:90%; max-width:480px; position:relative; animation:fadeInUp 0.35s ease; }
    .nav-box h3 { font-size:20px; font-weight:900; margin-bottom:4px; color:var(--dark); }
    .nav-box p.nav-sub { font-size:13px; color:var(--text-muted); margin-bottom:24px; }
    .nav-step { display:flex; flex-direction:column; gap:8px; margin-bottom:18px; }
    .nav-step label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--text-muted); }
    .nav-step select { width:100%; padding:13px 16px; border-radius:11px; border:2px solid var(--gray-200); background:var(--gray-100); font-size:14px; font-weight:600; color:var(--dark); outline:none; appearance:none; font-family:Arial,sans-serif; transition:border-color .2s; cursor:pointer; }
    .nav-step select:focus { border-color:var(--primary); background:#fff; }
    .nav-step select:disabled { opacity:.45; cursor:default; }
    .btn-nav-ir { width:100%; padding:13px; background:var(--primary); color:#fff; border:none; border-radius:12px; font-size:15px; font-weight:800; cursor:pointer; transition:background .2s,transform .15s; box-shadow:0 4px 15px rgba(45,122,62,.3); font-family:Arial,sans-serif; margin-top:4px; }
    .btn-nav-ir:hover { background:var(--primary-dark); transform:translateY(-2px); }
    .btn-nav-ir:disabled { opacity:.4; pointer-events:none; }
    .close-nav { position:absolute; top:18px; right:20px; background:none; border:none; font-size:20px; cursor:pointer; color:#ccc; transition:color .2s; }
    .close-nav:hover { color:var(--primary); }
    .btn-nav-quick { display:inline-flex; align-items:center; gap:6px; padding:8px 14px; background:rgba(45,122,62,.1); color:var(--primary); border:none; border-radius:9px; font-size:12px; font-weight:700; cursor:pointer; font-family:Arial,sans-serif; margin-bottom:10px; transition:background .2s; }
    .btn-nav-quick:hover { background:rgba(45,122,62,.18); }

    @media(max-width:1024px){ .container{padding-left:20px;padding-right:20px;} .stats-row{grid-template-columns:1fr;} .filters-bar{flex-direction:column;} .design-text{display:none;} }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/components/sidebar_cz.php'; ?>
    <div class="design-text">ACTIVACIONES - ZONA</div>

    <div class="container">

        <div class="page-header animate-up">
            <div>
                <div class="ai-badge">ZONA: <?= strtoupper($user_zona ?: 'SIN ASIGNAR') ?></div>
                <h1 class="main-title">Activaciones Locales.</h1>
                <p class="description">Registra y monitorea las activaciones, caravanas y brigadas realizadas exclusivamente en tus secciones asignadas.</p>
            </div>
            <div class="header-actions">
                <button class="btn-primary-action" onclick="abrirModal()">
                    <i class="fas fa-plus-circle"></i> Nueva Activación
                </button>
            </div>
        </div>

        <?php if(isset($error_db)): ?>
        <div style="background:#f8d7da;color:#721c24;padding:15px;border-radius:10px;margin-bottom:20px;">
            <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error_db) ?>
        </div>
        <?php endif; ?>

        <div class="stats-row animate-up animate-delay-1">
            <div class="stat-card">
                <div class="stat-icon verde"><i class="fas fa-users"></i></div>
                <div><div class="stat-label">Contactos Totales</div><div class="stat-val" id="res-contactos">0</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon amarillo"><i class="fas fa-bolt"></i></div>
                <div><div class="stat-label">Total de Activaciones</div><div class="stat-val" id="res-inversion">0</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon azul"><i class="fas fa-sitemap"></i></div> 
                <div>
                    <div class="stat-label">Secciones en Estructura</div> 
                    <div class="stat-val" id="res-secciones"><?= count($seccionesZona) ?></div>
                </div>
            </div>
        </div>

        <!-- ── FILTRO MES / SEMANA (igual que dashboard) ── -->
        <div style="display:flex;align-items:center;gap:16px;margin-bottom:16px;flex-wrap:wrap;">
            <div style="display:flex;align-items:center;gap:8px;">
                <span style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted);">Mes:</span>
                <select id="filtro-mes" class="form-input-styled" style="min-width:130px;font-size:13px;padding:8px 12px;" onchange="mesChange()">
                    <option value="0">Todos los meses</option>
                    <option value="1">Enero</option><option value="2">Febrero</option>
                    <option value="3">Marzo</option><option value="4">Abril</option>
                    <option value="5">Mayo</option><option value="6">Junio</option>
                    <option value="7">Julio</option><option value="8">Agosto</option>
                    <option value="9">Septiembre</option><option value="10">Octubre</option>
                    <option value="11">Noviembre</option><option value="12">Diciembre</option>
                </select>
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
                <span style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted);">Semana:</span>
                <select id="filtro-semana" class="form-input-styled" style="min-width:210px;font-size:13px;padding:8px 12px;" onchange="renderTabla()" disabled>
                    <option value="">Todas las semanas</option>
                </select>
            </div>
            <button onclick="limpiarMesSemana()" id="btn-limpiar-fecha"
                style="display:none;height:36px;padding:0 14px;border-radius:8px;border:1.5px solid var(--gray-200);background:var(--white);font-size:12px;font-weight:600;color:var(--text-muted);cursor:pointer;transition:all 0.15s;"
                onmouseover="this.style.borderColor='var(--primary)';this.style.color='var(--primary)'"
                onmouseout="this.style.borderColor='var(--gray-200)';this.style.color='var(--text-muted)'">
                × Limpiar fecha
            </button>
        </div>
        <div class="filters-bar animate-up animate-delay-1">
            <div class="filter-group">
                <label class="form-label">Estatus</label>
                <select id="filtro-estatus" class="form-input-styled" onchange="renderTabla()">
                    <option value="">Todos</option>
                    <option value="programada">Programada</option>
                    <option value="en-curso">En curso</option>
                    <option value="completada">Completada</option>
                    <option value="cancelada">Cancelada</option>
                </select>
            </div>
            <div class="filter-group">
                <label class="form-label">Tipo</label>
                <select id="filtro-tipo" class="form-input-styled" onchange="renderTabla()">
                    <option value="">Todos los tipos</option>
                    <option value="territorial">Territorial</option>
                    <option value="digital">Digital</option>
                    <option value="simbolica">Simbólica / Impacto</option>
                    <option value="estructura">Estructura</option>
                </select>
            </div>
            <div class="filter-group">
                <label class="form-label">Sección</label>
                <select id="filtro-seccion" class="form-input-styled" onchange="renderTabla()">
                    <option value="">Todas las secciones</option>
                    <?php foreach($seccionesZona as $sec): ?>
                        <option value="<?= htmlspecialchars($sec) ?>"><?= htmlspecialchars($sec) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="table-section animate-up animate-delay-2">
            <div class="section-header">
                <div class="section-title">Registro de Activaciones de tu Zona</div>
                <div id="count-label" style="font-size:13px;color:var(--text-muted);font-weight:600;"></div>
            </div>
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>Activación</th><th>Tipo</th><th>Fecha</th><th>Estatus</th><th>Registró</th>
                        <th style="text-align:right;">Contactos</th>
                        <th style="text-align:right;">Asistentes</th>
                        <th style="text-align:right;">Costo</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="tabla-body"></tbody>
            </table>
        </div>
    </div>

    <div class="modal" id="modal-form">
        <div class="modal-content">
            <div class="close-modal" onclick="cerrarModal('modal-form')"><i class="fas fa-times"></i></div>
            <h2 id="form-title" style="font-size:24px;font-weight:800;margin-bottom:5px;">Nueva Activación</h2>
            <p style="font-size:14px;color:#999;margin-bottom:30px;">Registra o edita una activación ocurrida en tus secciones.</p>

            <div class="form-group" style="margin-bottom:20px;">
                <label class="form-label">Título *</label>
                <input type="text" id="f-titulo" class="form-input-styled" placeholder="Ej. Caravana Verde Col. Pitic">
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
                <div class="form-group">
                    <label class="form-label">Tipo *</label>
                    <select id="f-tipo" class="form-input-styled">
                        <option value="">Selecciona...</option>
                        <option value="territorial">Activación Territorial (presencial)</option>
                        <option value="digital">Activación Digital</option>
                        <option value="simbolica">Activación Simbólica / Impacto</option>
                        <option value="estructura">Activación de Estructura</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Fecha *</label>
                    <input type="date" id="f-fecha" class="form-input-styled">
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
                <div class="form-group">
                    <label class="form-label">Coordinador Encargado</label>
                    <input type="text" id="f-coordinador" class="form-input-styled" placeholder="Tu nombre">
                </div>
                <div class="form-group">
                    <label class="form-label">Sección Electoral (Solo de tu zona) *</label>
                    <select id="f-seccion" class="form-input-styled">
                        <option value="">Selecciona una sección</option>
                        <?php foreach($seccionesZona as $sec): ?>
                            <option value="<?= htmlspecialchars($sec) ?>"><?= htmlspecialchars($sec) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group" style="margin-bottom:20px;">
                <label class="form-label">Estatus *</label>
                <div class="estatus-selector" style="margin-top:5px;">
                    <button class="est-btn" data-est="programada" onclick="seleccionarEstatus('programada')" type="button">📅 Programada</button>
                    <button class="est-btn" data-est="en-curso"   onclick="seleccionarEstatus('en-curso')"   type="button">▶️ En curso</button>
                    <button class="est-btn" data-est="completada" onclick="seleccionarEstatus('completada')" type="button">✅ Completada</button>
                    <button class="est-btn" data-est="cancelada"  onclick="seleccionarEstatus('cancelada')"  type="button">❌ Cancelada</button>
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;margin-bottom:20px;">
                <div class="form-group"><label class="form-label">Contactos Nuevos Est.</label><input type="number" id="f-contactos" class="form-input-styled" min="0" placeholder="0"></div>
                <div class="form-group"><label class="form-label">Asistentes Est.</label><input type="number" id="f-asistentes" class="form-input-styled" min="0" placeholder="0"></div>
                <div class="form-group"><label class="form-label">Costo Estimado ($)</label><input type="number" id="f-costo" class="form-input-styled" min="0" placeholder="0"></div>
            </div>
            <div class="form-group" style="margin-bottom:20px;">
                <label class="form-label">Notas Adicionales</label>
                <textarea id="f-notas" class="form-input-styled" placeholder="Materiales, logística, objetivos específicos..."></textarea>
            </div>
            <div class="form-group" style="margin-bottom:5px;">
                <label class="form-label">Ubicación / Georeferencia</label>
                <div style="display:flex;gap:8px;margin-bottom:8px;">
                    <div style="position:relative;flex:1;">
                        <i class="fas fa-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#aaa;font-size:13px;pointer-events:none;"></i>
                        <input type="text" id="map-search-input" placeholder="Buscar dirección o lugar..."
                            style="width:100%;padding:10px 12px 10px 34px;border:2px solid #eee;border-radius:10px;font-size:13px;outline:none;transition:border 0.2s;"
                            onfocus="this.style.borderColor='var(--primary)'" onblur="this.style.borderColor='#eee'"
                            onkeydown="if(event.key==='Enter'){event.preventDefault();buscarEnMapa();}">
                    </div>
                    <button type="button" onclick="buscarEnMapa()"
                        style="padding:10px 16px;background:var(--primary);color:white;border:none;border-radius:10px;font-size:13px;font-weight:700;cursor:pointer;white-space:nowrap;transition:background 0.2s;"
                        onmouseover="this.style.background='var(--primary-dark)'" onmouseout="this.style.background='var(--primary)'">
                        <i class="fas fa-map-marker-alt"></i> Buscar
                    </button>
                </div>
                <div id="map-search-results" style="display:none;border:1px solid #eee;border-radius:10px;background:white;max-height:180px;overflow-y:auto;margin-bottom:8px;box-shadow:0 4px 12px rgba(0,0,0,0.08);"></div>
                <button class="btn-nav-quick" onclick="abrirNavMapa()" type="button"><i class="fas fa-map-marker-alt"></i> Centrar en Municipio</button>
                <p class="map-note"><i class="fas fa-hand-pointer"></i> Haz clic en el mapa para colocar el marcador</p>
                <div id="mapa"></div>
                <div class="map-coords">
                    <input type="number" id="f-lat" class="form-input-styled" placeholder="Latitud" step="0.000001" readonly style="background:#f0f0f0;">
                    <input type="number" id="f-lng" class="form-input-styled" placeholder="Longitud" step="0.000001" readonly style="background:#f0f0f0;">
                </div>
            </div>
            <div class="form-group" style="margin-bottom:20px;margin-top:20px;">
                <label class="form-label">📷 Fotos de la Activación</label>
                <div class="fotos-upload-area" id="drop-zone" onclick="document.getElementById('foto-input').click()" ondragover="dragOver(event)" ondragleave="dragLeave(event)" ondrop="dropFotos(event)">
                    <div class="upload-icon"><i class="fas fa-images"></i></div>
                    <p>Arrastra imágenes aquí o <span>haz clic para seleccionar</span></p>
                    <p style="font-size:11px;margin-top:4px;opacity:0.7;">JPG, PNG, WEBP — máx. 5 MB por foto</p>
                </div>
                <input type="file" id="foto-input" accept="image/*" multiple onchange="procesarFotos(this.files)">
                <div class="fotos-preview-grid" id="fotos-preview"></div>
                <div class="foto-counter" id="foto-counter"></div>
            </div>
            <div class="form-group" style="margin-bottom:20px;">
                <label class="form-label">🔗 Enlaces (repositorios / redes sociales)</label>
                <div style="display:flex;gap:8px;margin-bottom:8px;">
                    <input type="url" id="enlace-input" class="form-input-styled" placeholder="https://... (Drive, Fotos, Facebook, Instagram, X, etc.)" style="flex:1;"
                           onkeydown="if(event.key==='Enter'){event.preventDefault();agregarEnlace();}">
                    <button type="button" onclick="agregarEnlace()" style="background:var(--primary);color:#fff;border:none;border-radius:10px;padding:0 16px;font-weight:700;font-size:13px;cursor:pointer;white-space:nowrap;"><i class="fas fa-plus"></i> Agregar</button>
                </div>
                <div id="enlaces-preview"></div>
            </div>
            <div class="form-group" style="background:#f0f7f0;border:1px solid #d5e8d5;border-radius:10px;padding:14px;margin-bottom:20px;">
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-weight:700;font-size:13px;color:#1f5a2d;">
                    <input type="checkbox" id="f-registro-qr" style="width:18px;height:18px;">
                    <i class="fas fa-qrcode"></i> Activar registro de asistentes (liga + QR)
                </label>
                <div style="font-size:11px;color:#4a7a4a;margin-top:6px;">La liga y el QR quedan disponibles en "Database QR" al guardar.</div>
            </div>
            <div class="modal-footer">
                <button class="btn-danger" id="btn-eliminar" style="visibility:hidden"><i class="fas fa-trash-alt"></i> Eliminar</button>
                <div style="display:flex;gap:10px;">
                    <button class="btn-cancel" onclick="cerrarModal('modal-form')">Cancelar</button>
                    <button class="btn-save" id="btn-guardar" onclick="guardar()"><i class="fas fa-check"></i> Guardar</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal" id="modal-det">
        <div class="modal-content" style="max-width:580px;">
            <div class="close-modal" onclick="cerrarModal('modal-det')"><i class="fas fa-times"></i></div>
            <h2 id="det-titulo" style="font-size:24px;font-weight:800;margin-bottom:10px;">—</h2>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:5px;">
                <span id="det-estatus-badge" class="badge"></span>
                <span id="det-tipo-chip" class="tipo-chip"></span>
            </div>
            <div class="detail-grid">
                <div class="detail-item"><strong>Fecha</strong><span id="det-fecha">—</span></div>
                <div class="detail-item"><strong>Coordinador</strong><span id="det-coordinador">—</span></div>
                <div class="detail-item"><strong>Sección Asignada</strong><span id="det-seccion">—</span></div>
                <div class="detail-item"><strong>Contactos Est.</strong><span id="det-contactos" class="big">—</span></div>
                <div class="detail-item"><strong>Asistentes Est.</strong><span id="det-asistentes" class="big">—</span></div>
                <div class="detail-item"><strong>Costo Estimado</strong><span id="det-costo">—</span></div>
            </div>
            <div style="margin-top:20px;">
                <strong style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;">Notas</strong>
                <div id="det-notas" style="margin-top:8px;font-size:14px;color:#555;line-height:1.6;background:var(--gray-100);padding:15px;border-radius:10px;border-left:4px solid var(--primary);">—</div>
            </div>
            <div id="det-mapa-wrap" style="display:none;margin-top:20px;">
                <strong style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;">Ubicación</strong>
                <div id="det-mapa"></div>
            </div>
            <div class="det-galeria" id="det-galeria-wrap" style="display:none;">
                <strong style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;">📷 Fotos</strong>
                <div class="det-galeria-grid" id="det-galeria-grid"></div>
            </div>
            <div class="modal-footer">
                <button class="btn-danger" id="det-btn-del"><i class="fas fa-trash-alt"></i> Eliminar</button>
                <div style="display:flex;gap:10px;">
                    <button class="btn-cancel" onclick="cerrarModal('modal-det')">Cerrar</button>
                    <button class="btn-save" id="det-btn-edit"><i class="fas fa-pen"></i> Editar</button>
                </div>
            </div>
        </div>
    </div>

    <div id="modal-nav">
        <div class="nav-box">
            <button class="close-nav" onclick="cerrarNavMapa()"><i class="fas fa-times"></i></button>
            <h3><i class="fas fa-map-marked-alt" style="color:var(--primary);margin-right:8px;"></i>Navegar al mapa</h3>
            <p class="nav-sub">Selecciona un municipio de tu zona para centrar el mapa rápidamente.</p>
            <div class="nav-step">
                <label>Estado</label>
                <select id="nav-estado" disabled>
                    <option value="<?= htmlspecialchars($user_estado) ?>"><?= htmlspecialchars($user_estado) ?></option>
                </select>
            </div>
            <div class="nav-step">
                <label>Municipio de tu Zona</label>
                <select id="nav-municipio" onchange="document.getElementById('btn-nav-ir').disabled = !this.value">
                    <option value="">— Selecciona municipio —</option>
                    <?php foreach($municipiosZona as $mun): ?>
                        <option value="<?= htmlspecialchars($mun) ?>"><?= htmlspecialchars($mun) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn-nav-ir" id="btn-nav-ir" onclick="irAlMapa()" disabled>
                <i class="fas fa-crosshairs"></i> Centrar mapa aquí
            </button>
        </div>
    </div>

    <div id="lightbox">
        <button class="lb-close" onclick="cerrarLightbox()"><i class="fas fa-times"></i></button>
        <img id="lb-img" src="" alt="">
        <div class="lb-nav">
            <button class="lb-btn" onclick="lbPrev()"><i class="fas fa-chevron-left"></i> Ant.</button>
            <button class="lb-btn" onclick="lbNext()">Sig. <i class="fas fa-chevron-right"></i></button>
        </div>
        <div class="lb-counter" id="lb-counter"></div>
    </div>

<script>
const ESTADO_COORDINADOR = '<?= htmlspecialchars($user_estado) ?>';
const ESTADOS_MX = <?= json_encode($estados_mx_db, JSON_UNESCAPED_UNICODE) ?: '{}' ?>;
// Mapeo sección -> municipio, para completar el campo municipio al guardar
// (evita que las activaciones de Zona queden sin municipio y no aparezcan en Regional)
const SECCION_MUNICIPIO = <?= json_encode($seccionMunicipioMap, JSON_UNESCAPED_UNICODE) ?: '{}' ?>;

const activacionesDB = <?php
    $out = [];
    foreach ($activaciones as $row) {
        $out[] = [
            'id'          => (int)$row['id'],
            'titulo'      => $row['titulo'],
            'tipo'        => $row['tipo'],
            'fecha'       => $row['fecha_actividad'] ?? '',
            'estatus'     => $row['estatus'],
            'coordinador' => $row['coordinador'] ?? '',
            'seccion'     => $row['seccion_electoral'] ?? '',
            'contactos'   => (int)($row['contactos_estimados'] ?? 0),
            'asistentes'  => (int)($row['asistentes_estimados'] ?? 0),
            'costo'       => (float)($row['costo'] ?? 0),
            'notas'       => $row['notas'] ?? '',
            'lat'         => $row['latitud']  ? (float)$row['latitud']  : null,
            'lng'         => $row['longitud'] ? (float)$row['longitud'] : null,
            'estado'      => $row['estado'] ?? '',
            'municipio'   => $row['municipio'] ?? '',
            'fotos'       => json_decode($row['fotos'] ?? '[]', true) ?: [],
            'enlaces'     => json_decode($row['enlaces'] ?? '[]', true) ?: [],
            'creado_por_rol' => $row['creado_por_rol'] ?? '',
        ];
    }
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
?>;

let activaciones = activacionesDB;
let editandoId = null, estatusSeleccionado = null;
let mapaInst = null, marcador = null, detMapaInst = null;
let fotosActuales = [];
let enlacesActuales = [];
let lbFotos = [], lbIdx = 0;

const API = '../../api/activaciones/procesar.php';
const ROL_ACTUAL = '<?= addslashes($rol) ?>';
const TIPO_LABELS = {territorial:'Territorial',digital:'Digital',simbolica:'Simbólica / Impacto',estructura:'Estructura'};

function actualizarResumen(){
    const activas = activaciones.filter(a => a.estatus !== 'cancelada');
    const c   = activas.reduce((s,a) => s + (parseInt(a.contactos)||0), 0);
    const total = activaciones.length;
    document.getElementById('res-contactos').textContent = c.toLocaleString('es-MX');
    document.getElementById('res-inversion').textContent = total.toLocaleString('es-MX'); 
}


// ── Filtro Mes / Semana ─────────────────────────────────────────────
function isoWeek(dateStr) {
    if (!dateStr) return null;
    const d = new Date(dateStr + 'T12:00:00');
    const day = d.getDay() || 7;
    d.setDate(d.getDate() + 4 - day);
    const yearStart = new Date(d.getFullYear(), 0, 1);
    return { week: Math.ceil(((d - yearStart) / 86400000 + 1) / 7), year: d.getFullYear() };
}
function weekLabel(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr + 'T12:00:00');
    const day = d.getDay() || 7;
    const lun = new Date(d); lun.setDate(d.getDate() - (day - 1));
    const dom = new Date(lun); dom.setDate(lun.getDate() + 6);
    const fmtD = dd => String(dd.getDate()).padStart(2,'0');
    const MESES_CORTO = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
    return `${fmtD(lun)} ${MESES_CORTO[lun.getMonth()]} - ${fmtD(dom)} ${MESES_CORTO[dom.getMonth()]}`;
}
function mesChange() {
    const mes = parseInt(document.getElementById('filtro-mes').value);
    const selSem = document.getElementById('filtro-semana');
    selSem.innerHTML = '<option value="">Todas las semanas</option>';
    selSem.disabled = (mes === 0);
    document.getElementById('btn-limpiar-fecha').style.display = mes !== 0 ? 'inline-block' : 'none';
    if (mes === 0) { selSem.value = ''; renderTabla(); return; }

    // Generar TODAS las semanas del mes (no solo las que tienen activaciones)
    const year = new Date().getFullYear();
    const semanasMap = new Map();

    // Iterar cada día del mes y colectar semanas únicas
    const diasEnMes = new Date(year, mes, 0).getDate();
    for (let dia = 1; dia <= diasEnMes; dia++) {
        const dateStr = `${year}-${String(mes).padStart(2,'0')}-${String(dia).padStart(2,'0')}`;
        const w = isoWeek(dateStr);
        if (!w) continue;
        const key = `${w.year}-W${String(w.week).padStart(2,'0')}`;
        if (!semanasMap.has(key)) semanasMap.set(key, weekLabel(dateStr));
    }

    [...semanasMap.entries()]
        .sort(([a],[b]) => a.localeCompare(b))
        .forEach(([key, label]) => {
            const o = document.createElement('option');
            o.value = key; o.textContent = `Semana ${key.split('W')[1]} (${label})`;
            selSem.appendChild(o);
        });
    renderTabla();
}
function limpiarMesSemana() {
    document.getElementById('filtro-mes').value = '0';
    document.getElementById('filtro-semana').innerHTML = '<option value="">Todas las semanas</option>';
    document.getElementById('filtro-semana').disabled = true;
    document.getElementById('btn-limpiar-fecha').style.display = 'none';
    renderTabla();
}


// ── BADGE ROL REGISTRADOR ─────────────────────────────────
const ROL_LABELS = {
    'coordinador_estatal':    {label:'Estatal',    color:'#1e88e5', bg:'#eff6ff'},
    'coordinador_regional':   {label:'Regional',   color:'#7c3aed', bg:'#f5f3ff'},
    'regional':               {label:'Regional',   color:'#7c3aed', bg:'#f5f3ff'},
    'coordinador_zona':       {label:'Zona',       color:'#d97706', bg:'#fffbeb'},
    'coordinador_circunscripcion': {label:'Circunscripción', color:'#0891b2', bg:'#ecfeff'},
    'circunscripcion':        {label:'Circunscripción', color:'#0891b2', bg:'#ecfeff'},
    'admin':                  {label:'Admin',      color:'#dc2626', bg:'#fef2f2'},
    'administrador':          {label:'Admin',      color:'#dc2626', bg:'#fef2f2'},
};
function rolBadge(rol) {
    if (!rol) return '';
    const r = ROL_LABELS[rol] || {label: rol, color:'#6c757d', bg:'#f8f9fa'};
    return `<span style="display:inline-block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;padding:2px 7px;border-radius:5px;margin-left:6px;color:${r.color};background:${r.bg};border:1px solid ${r.color}22;vertical-align:middle;">Registro ${r.label}</span>`;
}

function renderTabla(){
    // 1. Lectura segura de filtros (Evita errores si el input no existe en este archivo)
    const inputBuscar = document.getElementById('buscador');
    const q = inputBuscar ? inputBuscar.value.toLowerCase() : '';

    const filtroEst   = document.getElementById('filtro-estatus');
    const est         = filtroEst ? filtroEst.value : '';

    const filtroTipo  = document.getElementById('filtro-tipo');
    const tipo        = filtroTipo ? filtroTipo.value : '';

    const filtroEdo   = document.getElementById('filtro-estado');
    const estFiltro   = filtroEdo ? filtroEdo.value : '';

    const filtroMun   = document.getElementById('filtro-municipio');
    const munFiltro   = filtroMun ? filtroMun.value : '';

    const filtroSeccion = document.getElementById('filtro-seccion') ? document.getElementById('filtro-seccion').value : '';

    // Filtro Mes / Semana
    const _mes  = parseInt(document.getElementById('filtro-mes')?.value || '0');
    const _sem  = document.getElementById('filtro-semana')?.value || '';

    // 2. Filtrado de la lista
    let lista = [...activaciones].sort((a,b) => (b.fecha||'').localeCompare(a.fecha||''));
    if(q)         lista = lista.filter(a => (a.titulo||'').toLowerCase().includes(q) || (a.seccion||'').includes(q));
    if (_mes > 0) lista = lista.filter(a => a.fecha && (new Date(a.fecha+'T12:00:00').getMonth()+1) === _mes);
    if (_sem)     lista = lista.filter(a => {
        const w = isoWeek(a.fecha);
        return w && `${w.year}-W${String(w.week).padStart(2,'0')}` === _sem;
    });
    if(est)       lista = lista.filter(a => a.estatus === est);
    if(tipo)      lista = lista.filter(a => a.tipo === tipo);
    if(estFiltro) lista = lista.filter(a => a.estado === estFiltro);
    if(munFiltro) lista = lista.filter(a => a.municipio === munFiltro);
    if(filtroSeccion) lista = lista.filter(a => String(a.seccion) === String(filtroSeccion));

    // Actualizar contador
    const countLabel = document.getElementById('count-label');
    if(countLabel) countLabel.textContent = lista.length + ' activación' + (lista.length !== 1 ? 'es' : '');

    // 3. Leer las columnas del HTML para adaptar el renderizado
    const thead = document.querySelector('.modern-table thead tr');
    const numColumnas = thead ? thead.children.length : 8; // Para el colspan
    
    // Verificar qué columnas existen en este nivel (Regional usa Municipio, Admin usa Estado)
    const headersText = thead ? Array.from(thead.children).map(th => th.textContent.trim().toLowerCase()) : [];
    const tieneEstado = headersText.includes('estado');
    const tieneMunicipio = headersText.includes('municipio');

    const tbody = document.getElementById('tabla-body');
    if(!lista.length){
        tbody.innerHTML = `<tr class="empty-row"><td colspan="${numColumnas}"><i class="fas fa-calendar-xmark" style="font-size:30px;opacity:0.2;display:block;margin-bottom:10px;"></i>No hay activaciones registradas.</td></tr>`;
        actualizarResumen(); return;
    }

    tbody.innerHTML = lista.map(a => {
        const bc   = `badge-${a.estatus||'programada'}`;
        const bt   = (a.estatus||'programada').replace('-',' ').replace(/\b\w/g,c=>c.toUpperCase());
        const fecha = a.fecha ? fmtFecha(a.fecha) : '—';
        
        // Construir la fila dinámica
        let rowHTML = `<tr>
            <td>
                <span style="font-weight:700;font-size:14px;cursor:pointer;color:var(--dark);" onclick="verDetalle(${a.id})">${a.titulo||'Sin título'}</span>
                ${a.fotos && a.fotos.length ? `<span style="font-size:11px;font-weight:700;color:var(--primary);background:rgba(45,122,62,0.1);padding:2px 7px;border-radius:6px;margin-left:6px;"><i class="fas fa-camera"></i> ${a.fotos.length}</span>` : ''}
                ${(a.colonia || a.seccion) ? `<div style="font-size:12px;color:var(--text-muted);margin-top:3px;"><i class="fas fa-location-dot"></i> ${[a.colonia, a.seccion ? 'Secc. ' + a.seccion : ''].filter(Boolean).join(' · ')}</div>` : ''}
                ${a.coordinador ? `<div style="font-size:12px;color:var(--text-muted);"><i class="fas fa-user"></i> ${a.coordinador}</div>` : ''}
            </td>
            <td><span class="tipo-chip">${TIPO_LABELS[a.tipo]||a.tipo||'—'}</span></td>
            <td style="font-size:13px;">${fecha}</td>
            <td><span class="badge ${bc}">${bt}</span></td>`;
            
        // Agregar columnas dinámicas si existen en el THEAD
        if(tieneEstado)   rowHTML += `<td><span style="font-size:13px; font-weight:600; color:var(--text-muted);">${a.estado || '—'}</span></td>`;
        if(tieneMunicipio) rowHTML += `<td><span style="font-size:13px; font-weight:600; color:var(--text-muted);">${a.municipio || '—'}</span></td>`;
        rowHTML += `<td>${rolBadge(a.creado_por_rol)}</td>`;

        rowHTML += `
            <td style="text-align:right;"><div class="stat-cell-label">Contactos</div><div class="stat-cell">${(a.contactos||0).toLocaleString('es-MX')}</div></td>
            <td style="text-align:right;"><div class="stat-cell-label">Asistentes</div><div class="stat-cell">${(a.asistentes||0).toLocaleString('es-MX')}</div></td>
            <td style="text-align:right;"><div class="stat-cell-label">Costo</div><div class="stat-cell neutral">$${parseFloat(a.costo||0).toLocaleString('es-MX')}</div></td>
            <td class="action-cell">
                <button class="action-btn" onclick="toggleDropdown(this)"><i class="fas fa-ellipsis-v"></i></button>
                <div class="dropdown-menu">
                    <button class="dropdown-item" onclick="verDetalle(${a.id})"><i class="fas fa-eye"></i> Ver detalle</button>
                    ${a.creado_por_rol === ROL_ACTUAL ? `
                    <button class="dropdown-item" onclick="abrirModal(${a.id})"><i class="fas fa-pen"></i> Editar</button>
                    ${(a.estatus === 'en-curso' || a.estatus === 'completada')
                        ? '<button class="dropdown-item delete-item" disabled title="No se puede eliminar una activación en curso o completada" style="opacity:0.4;cursor:not-allowed;"><i class=\"fas fa-trash-alt\"></i> Eliminar</button>'
                        : `<button class="dropdown-item delete-item" onclick="eliminar(${a.id})"><i class="fas fa-trash-alt"></i> Eliminar</button>`}
                    ` : ''}
                </div>
            </td>
        </tr>`;
        return rowHTML;
    }).join('');
    
    actualizarResumen();
}

function fmtFecha(f){ const m=['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic']; const[y,mo,d]=f.split('-'); return `${parseInt(d)} ${m[parseInt(mo)-1]} ${y}`; }

function toggleDropdown(btn){
    document.querySelectorAll('.dropdown-menu.show').forEach(d=>{if(d!==btn.nextElementSibling)d.classList.remove('show');});
    btn.classList.toggle('active'); btn.nextElementSibling.classList.toggle('show');
}
document.addEventListener('click',e=>{
    if(!e.target.closest('.action-cell')){
        document.querySelectorAll('.dropdown-menu.show').forEach(d=>d.classList.remove('show'));
        document.querySelectorAll('.action-btn.active').forEach(b=>b.classList.remove('active'));
    }
});

function abrirModal(id=null){
    // Verificar que solo el rol que registró puede editar
    if (id) {
        const _a = activaciones.find(x => parseInt(x.id) === parseInt(id));
        if (_a && _a.creado_por_rol && _a.creado_por_rol !== ROL_ACTUAL) {
            alert('Solo el rol que registró esta activación puede editarla.');
            return;
        }
    }
    editandoId = id ? parseInt(id) : null; id = editandoId; estatusSeleccionado = null;
    document.getElementById('form-title').textContent = id ? 'Editar Activación' : 'Nueva Activación';
    ['f-titulo','f-tipo','f-fecha','f-coordinador','f-seccion','f-contactos','f-asistentes','f-costo','f-notas','f-lat','f-lng']
        .forEach(f => document.getElementById(f).value = '');
    fotosActuales = []; renderFotosPreview();
    enlacesActuales = []; renderEnlacesPreview();
    document.querySelectorAll('.est-btn').forEach(b => b.className = 'est-btn');
    document.getElementById('f-registro-qr').checked = false;
    if (id) {
        fetch(`../../api/registro_asistentes/procesar.php?action=list&tipo=activacion`)
            .then(r=>r.json())
            .then(d=>{
                if (d.ok && d.items.some(it => it.origen_id === id)) {
                    document.getElementById('f-registro-qr').checked = true;
                }
            }).catch(()=>{});
    }
    const btnEl = document.getElementById('btn-eliminar');

    if(!id){
        document.getElementById('f-fecha').value = new Date().toISOString().split('T')[0];
        btnEl.style.visibility = 'hidden';
    } else {
        const a = activaciones.find(x => parseInt(x.id) === parseInt(id));
        if(a){
            document.getElementById('f-titulo').value     = a.titulo||'';
            document.getElementById('f-tipo').value       = a.tipo||'';
            document.getElementById('f-fecha').value      = a.fecha||'';
            document.getElementById('f-coordinador').value= a.coordinador||'';
            document.getElementById('f-seccion').value    = a.seccion||'';
            document.getElementById('f-contactos').value  = a.contactos||'';
            document.getElementById('f-asistentes').value = a.asistentes||'';
            document.getElementById('f-costo').value      = a.costo||'';
            document.getElementById('f-notas').value      = a.notas||'';
            if(a.lat) document.getElementById('f-lat').value = a.lat;
            if(a.lng) document.getElementById('f-lng').value = a.lng;
            if(a.estatus) seleccionarEstatus(a.estatus);
            fotosActuales = (a.fotos||[]).map(d => ({dataUrl:d, name:'foto'}));
            renderFotosPreview();
            enlacesActuales = (a.enlaces||[]);
            renderEnlacesPreview();
        }
        btnEl.style.visibility = 'visible';
        btnEl.onclick = () => eliminar(id);
    }
    document.getElementById('modal-form').classList.add('open');
    setTimeout(() => iniciarMapa(), 120);
}
function cerrarModal(id){
    document.getElementById(id).classList.remove('open');
    if(id==='modal-form' && mapaInst){ mapaInst.remove(); mapaInst=null; marcador=null; }
    if(id==='modal-det'  && detMapaInst){ detMapaInst.remove(); detMapaInst=null; }
}
function seleccionarEstatus(est){
    estatusSeleccionado = est;
    document.querySelectorAll('.est-btn').forEach(b => b.className = b.dataset.est===est ? `est-btn sel-${est}` : 'est-btn');
}

function iniciarMapa(){
    const el = document.getElementById('mapa'); if(!el||mapaInst) return;
    
    let lat0 = 23.6345; let lng0 = -102.5528;
    if(ESTADO_COORDINADOR && ESTADOS_MX[ESTADO_COORDINADOR]){
        lat0 = ESTADOS_MX[ESTADO_COORDINADOR].lat;
        lng0 = ESTADOS_MX[ESTADO_COORDINADOR].lng;
    }

    lat0 = parseFloat(document.getElementById('f-lat').value) || lat0;
    lng0 = parseFloat(document.getElementById('f-lng').value) || lng0;

    mapaInst = L.map('mapa').setView([lat0,lng0], (document.getElementById('f-lat').value ? 14 : 7));
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'© OpenStreetMap'}).addTo(mapaInst);
    
    if(document.getElementById('f-lat').value) marcador = L.marker([lat0,lng0]).addTo(mapaInst);
    
    mapaInst.on('click',function(e){
        const{lat,lng}=e.latlng;
        document.getElementById('f-lat').value = lat.toFixed(6);
        document.getElementById('f-lng').value = lng.toFixed(6);
        if(marcador) mapaInst.removeLayer(marcador);
        marcador = L.marker([lat,lng]).addTo(mapaInst);
    });
}

async function guardar(){
    const titulo = document.getElementById('f-titulo').value.trim();
    const tipo   = document.getElementById('f-tipo').value;
    const fecha  = document.getElementById('f-fecha').value;
    const seccion = document.getElementById('f-seccion').value;
    
    if(!titulo){ alert('El título es obligatorio.'); return; }
    if(!tipo)  { alert('Selecciona un tipo.'); return; }
    if(!fecha) { alert('La fecha es obligatoria.'); return; }
    if(!seccion) { alert('Debes seleccionar una sección.'); return; }
    if(!estatusSeleccionado){ alert('Selecciona un estatus.'); return; }

    const btn = document.getElementById('btn-guardar');
    btn.classList.add('loading');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';

    const fd = new FormData();
    fd.append('action',              editandoId ? 'update' : 'create');
    if(editandoId) fd.append('id',   editandoId);
    fd.append('titulo',              titulo);
    fd.append('tipo',                tipo);
    fd.append('fecha_actividad',     fecha);
    fd.append('estatus',             estatusSeleccionado);
    fd.append('estado',              ESTADO_COORDINADOR);
    fd.append('municipio',           SECCION_MUNICIPIO[seccion] || '');
    fd.append('zona_id',             '<?= intval($zonaEstructura["id"] ?? 0) ?>');
    fd.append('coordinador',         document.getElementById('f-coordinador').value.trim());
    fd.append('seccion_electoral',   seccion);
    fd.append('contactos_estimados', document.getElementById('f-contactos').value || 0);
    fd.append('asistentes_estimados',document.getElementById('f-asistentes').value || 0);
    fd.append('costo',               document.getElementById('f-costo').value || 0);
    fd.append('notas',               document.getElementById('f-notas').value.trim());
    fd.append('latitud',             document.getElementById('f-lat').value || '');
    fd.append('longitud',            document.getElementById('f-lng').value || '');
    fd.append('fotos',               JSON.stringify(fotosActuales.map(f => f.dataUrl)));
    fd.append('enlaces',             JSON.stringify(enlacesActuales));

    try {
        const res  = await fetch(API, {method:'POST', body:fd});
        const data = await res.json();
        if(!data.ok) throw new Error(data.msg || 'Error desconocido');

        const obj = {
            id:          editandoId ? parseInt(editandoId) : parseInt(data.id),
            titulo, tipo, fecha, estatus: estatusSeleccionado,
            coordinador: document.getElementById('f-coordinador').value.trim(),
            seccion:     seccion,
            contactos:   parseInt(document.getElementById('f-contactos').value)||0,
            asistentes:  parseInt(document.getElementById('f-asistentes').value)||0,
            costo:       parseFloat(document.getElementById('f-costo').value)||0,
            notas:       document.getElementById('f-notas').value.trim(),
            lat:         document.getElementById('f-lat').value || null,
            lng:         document.getElementById('f-lng').value || null,
            estado:      ESTADO_COORDINADOR,
            municipio:   '',
            fotos:       fotosActuales.map(f => f.dataUrl),
            enlaces:     enlacesActuales.slice(),
            creado_por_rol: editandoId
                ? (activaciones.find(x => parseInt(x.id)===parseInt(editandoId))?.creado_por_rol || '')
                : '<?= addslashes($rol) ?>',
        };
        if(editandoId){
            const i = activaciones.findIndex(x => parseInt(x.id) === parseInt(editandoId));
            if(i !== -1) activaciones[i] = obj;
        } else {
            activaciones.push(obj);
        }
        const idFinalRA = editandoId ? parseInt(editandoId) : parseInt(data.id);
        if (idFinalRA) {
            if (document.getElementById('f-registro-qr').checked) {
                await activarRegistroQR('activacion', idFinalRA);
            } else if (editandoId) {
                await desactivarRegistroQR('activacion', idFinalRA);
            }
        }
        cerrarModal('modal-form');
        renderTabla();
    } catch(err){
        alert('Error al guardar: ' + err.message);
    } finally {
        btn.classList.remove('loading');
        btn.innerHTML = '<i class="fas fa-check"></i> Guardar';
    }
}

async function activarRegistroQR(tipoOrigen, origenId) {
    try {
        const fdQR = new FormData();
        fdQR.append('action', 'activar');
        fdQR.append('tipo_origen', tipoOrigen);
        fdQR.append('origen_id', origenId);
        await fetch('../../api/registro_asistentes/procesar.php', { method:'POST', body:fdQR });
    } catch (e) { /* no bloquea el guardado de la activación si falla el QR */ }
}

async function desactivarRegistroQR(tipoOrigen, origenId) {
    try {
        const fdQR = new FormData();
        fdQR.append('action', 'desactivar');
        fdQR.append('tipo_origen', tipoOrigen);
        fdQR.append('origen_id', origenId);
        await fetch('../../api/registro_asistentes/procesar.php', { method:'POST', body:fdQR });
    } catch (e) { /* no bloquea el guardado de la activación */ }
}

async function eliminar(id){
    if(!confirm('¿Eliminar esta activación?')) return;
    const fd = new FormData();
    fd.append('action','delete');
    fd.append('id', id);
    try {
        const res  = await fetch(API, {method:'POST', body:fd});
        const data = await res.json();
        if(!data.ok) throw new Error(data.msg);
        activaciones = activaciones.filter(x => parseInt(x.id) !== parseInt(id));
        renderTabla();
    } catch(err){
        alert('Error al eliminar: ' + err.message);
    }
}

function verDetalle(id){
    id = parseInt(id);
    const a = activaciones.find(x => parseInt(x.id) === id); if(!a) return;
    document.getElementById('det-titulo').textContent = a.titulo||'—';
    const bc = `badge-${a.estatus||'programada'}`;
    const bt = (a.estatus||'programada').replace('-',' ').replace(/\b\w/g,c=>c.toUpperCase());
    document.getElementById('det-estatus-badge').className = `badge ${bc}`;
    document.getElementById('det-estatus-badge').textContent = bt;
    document.getElementById('det-tipo-chip').textContent = TIPO_LABELS[a.tipo]||a.tipo||'—';
    document.getElementById('det-fecha').textContent       = a.fecha ? fmtFecha(a.fecha) : '—';
    document.getElementById('det-coordinador').textContent = a.coordinador||'—';
    document.getElementById('det-seccion').textContent     = a.seccion||'—';
    document.getElementById('det-contactos').textContent   = (a.contactos||0).toLocaleString('es-MX');
    document.getElementById('det-asistentes').textContent  = (a.asistentes||0).toLocaleString('es-MX');
    document.getElementById('det-costo').textContent       = a.costo ? '$'+parseFloat(a.costo).toLocaleString('es-MX') : '—';
    document.getElementById('det-notas').textContent       = a.notas||'Sin notas.';
    // Mostrar/ocultar botones según quién registró
    const puedeEditar = (a.creado_por_rol === ROL_ACTUAL);
    document.getElementById('det-btn-del').style.display  = puedeEditar ? '' : 'none';
    document.getElementById('det-btn-edit').style.display = puedeEditar ? '' : 'none';
    document.getElementById('det-btn-del').onclick  = () => { cerrarModal('modal-det'); eliminar(id); };
    document.getElementById('det-btn-edit').onclick = () => { cerrarModal('modal-det'); abrirModal(id); };
    document.getElementById('det-mapa-wrap').style.display = (a.lat&&a.lng) ? 'block' : 'none';
    const galeriaWrap = document.getElementById('det-galeria-wrap');
    const galeriaGrid = document.getElementById('det-galeria-grid');
    if(a.fotos && a.fotos.length){
        galeriaWrap.style.display = 'block';
        galeriaGrid.innerHTML = a.fotos.map((src,i) =>
            `<img src="${src}" alt="foto ${i+1}" onclick="abrirLightbox(${JSON.stringify(a.fotos).replace(/"/g, '&quot;')},${i})">`
        ).join('');
    } else { galeriaWrap.style.display='none'; galeriaGrid.innerHTML=''; }
    document.getElementById('modal-det').classList.add('open');
    if(a.lat && a.lng){
        setTimeout(()=>{
            if(detMapaInst){ detMapaInst.remove(); detMapaInst=null; }
            detMapaInst = L.map('det-mapa').setView([parseFloat(a.lat),parseFloat(a.lng)],14);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'© OpenStreetMap'}).addTo(detMapaInst);
            L.marker([parseFloat(a.lat),parseFloat(a.lng)]).bindPopup(a.titulo).addTo(detMapaInst).openPopup();
        },150);
    }
}

// ── FOTOS ──────────────────────────────────────────────────
function procesarFotos(files){
    Array.from(files).forEach(file=>{
        if(!file.type.startsWith('image/')) return;
        if(file.size > 5*1024*1024){ alert('La foto "'+file.name+'" supera 5 MB.'); return; }
        const reader = new FileReader();
        reader.onload = e => { fotosActuales.push({dataUrl:e.target.result,name:file.name}); renderFotosPreview(); };
        reader.readAsDataURL(file);
    });
    document.getElementById('foto-input').value = '';
}
function renderFotosPreview(){
    const grid    = document.getElementById('fotos-preview');
    const counter = document.getElementById('foto-counter');
    grid.innerHTML = fotosActuales.map((f,i) => `
        <div class="foto-thumb">
            <img src="${f.dataUrl}" alt="${f.name}" onclick="abrirLightbox(fotosActuales.map(x=>x.dataUrl),${i})">
            <button class="remove-foto" onclick="quitarFoto(${i})" type="button"><i class="fas fa-times"></i></button>
        </div>`).join('');
    counter.textContent = fotosActuales.length ? fotosActuales.length+' foto'+(fotosActuales.length!==1?'s':'') : '';
}
function quitarFoto(i){ fotosActuales.splice(i,1); renderFotosPreview(); }

function agregarEnlace(){
    const input = document.getElementById('enlace-input');
    const url = (input.value||'').trim();
    if(!url) return;
    try{ new URL(url); }catch(_e){ alert('Ingresa una URL válida (debe iniciar con http:// o https://).'); return; }
    enlacesActuales.push(url);
    input.value='';
    renderEnlacesPreview();
}
function renderEnlacesPreview(){
    const list = document.getElementById('enlaces-preview');
    if(!list) return;
    list.innerHTML = enlacesActuales.map((url,i)=>`
        <div style="display:flex;align-items:center;gap:8px;padding:8px 10px;border:1px solid #eee;border-radius:8px;margin-bottom:6px;background:#fafafa;">
            <i class="fas fa-link" style="color:var(--primary);flex-shrink:0;"></i>
            <a href="${url}" target="_blank" rel="noopener" style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px;color:#333;">${url}</a>
            <button type="button" onclick="quitarEnlace(${i})" style="background:none;border:none;cursor:pointer;color:#999;"><i class="fas fa-times"></i></button>
        </div>`).join('');
}
function quitarEnlace(i){ enlacesActuales.splice(i,1); renderEnlacesPreview(); }
function dragOver(e){ e.preventDefault(); document.getElementById('drop-zone').classList.add('drag-over'); }
function dragLeave(e){ document.getElementById('drop-zone').classList.remove('drag-over'); }
function dropFotos(e){ e.preventDefault(); document.getElementById('drop-zone').classList.remove('drag-over'); procesarFotos(e.dataTransfer.files); }

// ── LIGHTBOX ───────────────────────────────────────────────
function abrirLightbox(urls,idx){ lbFotos=urls; lbIdx=idx; document.getElementById('lb-img').src=lbFotos[lbIdx]; document.getElementById('lb-counter').textContent=(lbIdx+1)+' / '+lbFotos.length; document.getElementById('lightbox').classList.add('open'); }
function cerrarLightbox(){ document.getElementById('lightbox').classList.remove('open'); }
function lbPrev(){ lbIdx=(lbIdx-1+lbFotos.length)%lbFotos.length; document.getElementById('lb-img').src=lbFotos[lbIdx]; document.getElementById('lb-counter').textContent=(lbIdx+1)+' / '+lbFotos.length; }
function lbNext(){ lbIdx=(lbIdx+1)%lbFotos.length; document.getElementById('lb-img').src=lbFotos[lbIdx]; document.getElementById('lb-counter').textContent=(lbIdx+1)+' / '+lbFotos.length; }
document.addEventListener('keydown',e=>{ if(!document.getElementById('lightbox').classList.contains('open'))return; if(e.key==='ArrowLeft')lbPrev(); if(e.key==='ArrowRight')lbNext(); if(e.key==='Escape')cerrarLightbox(); });
document.getElementById('lightbox').addEventListener('click',e=>{ if(e.target===document.getElementById('lightbox'))cerrarLightbox(); });

renderTabla();

// ── BÚSQUEDA DE DIRECCIÓN ──────────────────────────────────
async function buscarEnMapa() {
    const q = document.getElementById('map-search-input').value.trim();
    if (!q) return;
    const resultsEl = document.getElementById('map-search-results');
    resultsEl.style.display = 'block';
    resultsEl.innerHTML = '<div style="padding:10px 14px;font-size:13px;color:#999;"><i class="fas fa-spinner fa-spin"></i> Buscando...</div>';
    try {
        const resp = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(q)}&limit=6&accept-language=es`, {headers:{'Accept-Language':'es'}});
        const data = await resp.json();
        if (!data.length) { resultsEl.innerHTML = '<div style="padding:10px 14px;font-size:13px;color:#999;">Sin resultados.</div>'; return; }
        resultsEl.innerHTML = data.map(r => `
            <div onclick="seleccionarLugar(${r.lat},${r.lon},'${r.display_name.replace(/'/g, "\\'")}')"
                style="padding:10px 14px;font-size:13px;cursor:pointer;border-bottom:1px solid #f5f5f5;display:flex;align-items:flex-start;gap:10px;transition:background 0.15s;"
                onmouseover="this.style.background='#f4fcf6'" onmouseout="this.style.background='white'">
                <i class="fas fa-map-marker-alt" style="color:var(--primary);margin-top:2px;flex-shrink:0;"></i>
                <span>${r.display_name}</span>
            </div>`).join('');
    } catch(e) { resultsEl.innerHTML = '<div style="padding:10px 14px;font-size:13px;color:#e53e3e;">Error al buscar.</div>'; }
}

function seleccionarLugar(lat, lng, nombre) {
    lat = parseFloat(lat); lng = parseFloat(lng);
    document.getElementById('f-lat').value = lat.toFixed(6);
    document.getElementById('f-lng').value = lng.toFixed(6);
    document.getElementById('map-search-input').value = nombre;
    document.getElementById('map-search-results').style.display = 'none';
    if (!mapaInst) { iniciarMapa(); }
    setTimeout(() => {
        if (marcador) mapaInst.removeLayer(marcador);
        marcador = L.marker([lat, lng]).addTo(mapaInst);
        mapaInst.invalidateSize();
        mapaInst.setView([lat, lng], 15);
    }, mapaInst ? 0 : 150);
}

document.addEventListener('click', function(e) {
    const inp = document.getElementById('map-search-input');
    const res = document.getElementById('map-search-results');
    if (inp && res && !inp.contains(e.target) && !res.contains(e.target)) res.style.display = 'none';
});

// ── NAVEGADOR MAPA ─────────────────────────────────────────
function abrirNavMapa(){
    document.getElementById('modal-nav').classList.add('open');
}

function cerrarNavMapa(){
    document.getElementById('modal-nav').classList.remove('open');
}

function irAlMapa(){
    const munVal = document.getElementById('nav-municipio').value;
    if (!munVal) return;

    // Intentar con coords locales primero
    const estado = ESTADOS_MX[ESTADO_COORDINADOR];
    if (estado && estado.municipios && estado.municipios[munVal]
        && estado.municipios[munVal].lat && estado.municipios[munVal].lng) {
        const coords = estado.municipios[munVal];
        cerrarNavMapa();
        _centrarMapaEnCoords(coords.lat, coords.lng);
        return;
    }

    // Fallback: buscar con Nominatim
    const btnIr = document.getElementById('btn-nav-ir');
    const textoOriginal = btnIr.innerHTML;
    btnIr.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Buscando...';
    btnIr.disabled = true;

    fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(munVal + ', ' + ESTADO_COORDINADOR + ', México')}&limit=1&accept-language=es`)
        .then(r => r.json())
        .then(data => {
            if (data && data.length > 0) {
                const lat = parseFloat(data[0].lat);
                const lng = parseFloat(data[0].lon);
                cerrarNavMapa();
                _centrarMapaEnCoords(lat, lng);
            } else {
                alert('No se encontraron coordenadas para ' + munVal);
            }
        })
        .catch(() => alert('Error al buscar coordenadas. Intenta con el buscador de dirección.'))
        .finally(() => {
            btnIr.innerHTML = textoOriginal;
            btnIr.disabled = false;
        });
}

function _centrarMapaEnCoords(lat, lng) {
    if (!mapaInst) return;
    mapaInst.invalidateSize();
    mapaInst.setView([lat, lng], 13);
    if (marcador) mapaInst.removeLayer(marcador);
    marcador = L.marker([lat, lng]).addTo(mapaInst);
    document.getElementById('f-lat').value = lat.toFixed(6);
    document.getElementById('f-lng').value = lng.toFixed(6);
}
</script>
</body>
</html>