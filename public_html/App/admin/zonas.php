<?php
if (file_exists(__DIR__ . '/../../includes/bootstrap.php')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
} else { 
    session_start(); 
}

$rol = $_SESSION['user_role'] ?? '';
$permitidos = ['admin','administrador','superadmin','super admin'];
if (!in_array($rol, $permitidos)) { 
    header('Location: /public/index.php'); 
    exit(); 
}

$db = Database::getInstance()->getConnection();

/* ══════════════════════════════════════════════════════════════════
   API JSON INCRUSTADA
══════════════════════════════════════════════════════════════════ */
if (isset($_GET['api'])) {
    header('Content-Type: application/json; charset=utf-8');
    $accion = $_GET['api'] ?? '';
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    
    // El estado llega por GET o POST dinámicamente
    $estado_req = $_GET['estado'] ?? $body['estado'] ?? 'Sonora';

    try {
        /* ── OBTENER MUNICIPIOS Y USUARIOS ── */
        if ($accion === 'get_catalogos') {
            $municipios_base = [];
            $usuarios_base = [];

            $stmt = $db->prepare("SELECT id FROM estados WHERE nombre = ? LIMIT 1");
            $stmt->execute([$estado_req]);
            $estado_row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($estado_row) {
                $estado_id = $estado_row['id'];
                $stmt = $db->prepare("SELECT id, nombre FROM municipios WHERE estado_id = ? ORDER BY nombre");
                $stmt->execute([$estado_id]);
                $municipios = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($municipios as $mun) {
                    $stmt2 = $db->prepare("SELECT seccion FROM secciones WHERE estado_id = ? AND municipio_id = ? ORDER BY CAST(seccion AS UNSIGNED)");
                    $stmt2->execute([$estado_id, $mun['id']]);
                    $secciones = array_column($stmt2->fetchAll(PDO::FETCH_ASSOC), 'seccion');
                    if (!empty($secciones)) {
                        $municipios_base[$mun['nombre']] = $secciones;
                    }
                }
            }

            $stmtU = $db->prepare("
                SELECT id, TRIM(CONCAT(COALESCE(nombres,''),' ',COALESCE(ap_paterno,''),' ',COALESCE(ap_materno,''))) AS nombre, 'Regional' as rol 
                FROM coordinador_regional WHERE estado = ? AND estatus='activo'
                UNION ALL
                SELECT id, TRIM(CONCAT(COALESCE(nombres,''),' ',COALESCE(ap_paterno,''),' ',COALESCE(ap_materno,''))) AS nombre, 'Zona' as rol 
                FROM coordinador_zona WHERE estado = ? AND estatus='activo'
                UNION ALL
                SELECT id, nombre_completo AS nombre, rol 
                FROM usuarios WHERE estado = ? AND activo = 1 AND rol IN ('regional', 'zona', 'coordinador', 'operador', 'coordinador_regional', 'coordinador_zona')
                ORDER BY nombre ASC
            ");
            $stmtU->execute([$estado_req, $estado_req, $estado_req]);
            $usuarios_base = $stmtU->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['ok' => true, 'municipios' => $municipios_base, 'usuarios' => $usuarios_base]);
            exit();
        }

        /* ── CARGAR ESTRUCTURA ── */
        if ($accion === 'cargar') {
            $stmt = $db->prepare("SELECT * FROM regiones_estatales WHERE estado = ? ORDER BY id ASC");
            $stmt->execute([$estado_req]);
            $estructuras = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($estructuras as &$est) {
                $est['id']          = (int)$est['id'];
                $est['meta']        = (int)$est['meta'];
                $est['municipios']  = json_decode($est['municipios'] ?? '[]', true) ?: [];
                $est['responsable'] = $est['coordinador_id'] ?? null;

                $stmt2 = $db->prepare("SELECT * FROM zonas_estatales WHERE estructura_id = ? ORDER BY id ASC");
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

            echo json_encode(['ok' => true, 'regiones' => $estructuras]);
            exit();
        }

        /* ── GUARDAR REGIÓN ── */
        if ($accion === 'guardar_region') {
            $id          = isset($body['id']) ? (int)$body['id'] : 0;
            $nombre      = trim($body['nombre'] ?? '');
            $desc        = trim($body['desc'] ?? '');
            $meta        = (int)($body['meta'] ?? 0);
            $coord_id    = isset($body['responsable']) && $body['responsable'] !== '' ? (int)$body['responsable'] : null;
            $municipios  = json_encode($body['municipios'] ?? [], JSON_UNESCAPED_UNICODE);

            if (!$nombre) { echo json_encode(['ok'=>false,'msg'=>'Nombre requerido']); exit(); }

            if ($id) {
                $stmt = $db->prepare("UPDATE regiones_estatales SET nombre=?, descripcion=?, meta=?, municipios=?, coordinador_id=? WHERE id=? AND estado=?");
                $stmt->execute([$nombre, $desc, $meta, $municipios, $coord_id, $id, $estado_req]);
            } else {
                $stmt = $db->prepare("INSERT INTO regiones_estatales (estado, nombre, descripcion, meta, municipios, coordinador_id) VALUES (?,?,?,?,?,?)");
                $stmt->execute([$estado_req, $nombre, $desc, $meta, $municipios, $coord_id]);
                $id = (int)$db->lastInsertId();
            }
            echo json_encode(['ok' => true, 'id' => $id]);
            exit();
        }

        /* ── ELIMINAR REGIÓN ── */
        if ($accion === 'eliminar_region') {
            $id = (int)($body['id'] ?? 0);
            $stmt = $db->prepare("DELETE FROM regiones_estatales WHERE id = ? AND estado = ?");
            $stmt->execute([$id, $estado_req]);
            echo json_encode(['ok' => true]);
            exit();
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

            $chk = $db->prepare("SELECT id FROM regiones_estatales WHERE id = ? AND estado = ?");
            $chk->execute([$estructura_id, $estado_req]);
            if (!$chk->fetch()) { echo json_encode(['ok'=>false,'msg'=>'Región no válida']); exit(); }

            if ($id) {
                $stmt = $db->prepare("UPDATE zonas_estatales SET nombre=?, clave=?, foco=?, colonias=?, meta=?, secciones=?, coordinador_id=? WHERE id=? AND estructura_id=?");
                $stmt->execute([$nombre, $clave, $foco, $colonias, $meta, $secciones, $coord_id, $id, $estructura_id]);
            } else {
                $stmt = $db->prepare("INSERT INTO zonas_estatales (estructura_id, nombre, clave, foco, colonias, meta, secciones, coordinador_id) VALUES (?,?,?,?,?,?,?,?)");
                $stmt->execute([$estructura_id, $nombre, $clave, $foco, $colonias, $meta, $secciones, $coord_id]);
                $id = (int)$db->lastInsertId();
            }
            echo json_encode(['ok' => true, 'id' => $id]);
            exit();
        }

        /* ── ELIMINAR ZONA ── */
        if ($accion === 'eliminar_zona') {
            $id = (int)($body['id'] ?? 0);
            $stmt = $db->prepare("DELETE z FROM zonas_estatales z INNER JOIN regiones_estatales r ON r.id = z.estructura_id WHERE z.id = ? AND r.estado = ?");
            $stmt->execute([$id, $estado_req]);
            echo json_encode(['ok' => true]);
            exit();
        }

        /* ── GUARDAR SECCIONES ── */
        if ($accion === 'guardar_secciones') {
            $zona_id   = (int)($body['zona_id'] ?? 0);
            $secciones = json_encode($body['secciones'] ?? [], JSON_UNESCAPED_UNICODE);

            $chk = $db->prepare("SELECT z.id FROM zonas_estatales z INNER JOIN regiones_estatales r ON r.id = z.estructura_id WHERE z.id = ? AND r.estado = ?");
            $chk->execute([$zona_id, $estado_req]);
            if (!$chk->fetch()) { echo json_encode(['ok'=>false,'msg'=>'Zona no válida']); exit(); }

            $stmt = $db->prepare("UPDATE zonas_estatales SET secciones=? WHERE id=?");
            $stmt->execute([$secciones, $zona_id]);
            echo json_encode(['ok' => true]);
            exit();
        }

        echo json_encode(['ok' => false, 'msg' => 'Acción desconocida']);
    } catch (Exception $e) {
        error_log('Admin zonas API error: ' . $e->getMessage());
        echo json_encode(['ok' => false, 'msg' => 'Error del servidor: ' . $e->getMessage()]);
    }
    exit();
}

// ── DICCIONARIO DE CIRCUNSCRIPCIONES Y ESTADOS ──────────────────────────────
$circunscripciones = [
    'Circunscripción 1' => ['Baja California', 'Baja California Sur', 'Chihuahua', 'Durango', 'Jalisco', 'Nayarit', 'Sinaloa', 'Sonora'],
    'Circunscripción 2' => ['Aguascalientes', 'Coahuila', 'Guanajuato', 'Nuevo León', 'San Luis Potosí', 'Tamaulipas', 'Zacatecas'],
    'Circunscripción 3' => ['Campeche', 'Chiapas', 'Oaxaca', 'Quintana Roo', 'Tabasco', 'Veracruz', 'Yucatán'],
    'Circunscripción 4' => ['Ciudad de México', 'Guerrero', 'Hidalgo', 'Morelos', 'Puebla', 'Tlaxcala'],
    'Circunscripción 5' => ['Colima', 'Estado de México', 'Michoacán', 'Querétaro']
];

$circunscripciones_json = json_encode($circunscripciones, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estructuras - Admin</title>
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

    /* Pestañas Circunscripción */
    .tabs-bar { display:flex; gap:4px; flex-wrap:wrap; border-bottom:2px solid var(--gray-200); margin-bottom:15px; padding-bottom:0; }
    .tab-btn { padding:10px 20px; border:none; background:transparent; font-family:Arial,sans-serif; font-size:14px; font-weight:600; color:var(--text-muted); cursor:pointer; border-bottom:3px solid transparent; margin-bottom:-2px; transition:0.2s; border-radius:8px 8px 0 0; }
    .tab-btn:hover { color:var(--dark); background:var(--gray-100); }
    .tab-btn.active { color:var(--primary); border-bottom-color:var(--primary); background:var(--light); }

    /* Píldoras Estados */
    .sub-tabs-bar { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:25px; padding: 5px 0; }
    .sub-tab-btn { background:var(--white); border:1px solid var(--gray-200); padding:8px 16px; border-radius:20px; font-size:13px; font-weight:600; color:var(--text-muted); cursor:pointer; transition:all 0.2s; font-family:Arial,sans-serif; }
    .sub-tab-btn:hover { border-color:var(--primary); color:var(--primary); }
    .sub-tab-btn.active { background:var(--primary); color:white; border-color:var(--primary); box-shadow:0 4px 10px rgba(45,122,62,0.2); }

    .workspace { display:grid; grid-template-columns:280px 1fr 1fr; gap:0; border:1px solid var(--gray-200); border-radius:20px; overflow:hidden; box-shadow:var(--shadow-sm); min-height:600px; }

    .col-regiones { background:var(--white); border-right:1px solid var(--gray-200); display:flex; flex-direction:column; }
    .col-header { padding:18px 20px; border-bottom:1px solid var(--gray-200); display:flex; align-items:center; justify-content:space-between; background:var(--gray-100); }
    .col-header-title { font-size:14px; font-weight:800; color:var(--dark); display:flex; align-items:center; gap:8px; }
    .col-body { flex:1; overflow-y:auto; padding:12px; }
    .col-body::-webkit-scrollbar { width:4px; }
    .col-body::-webkit-scrollbar-thumb { background:#ddd; border-radius:4px; }

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

    .col-zonas { border-right:1px solid var(--gray-200); display:flex; flex-direction:column; background:var(--white); }
    .col-secciones { display:flex; flex-direction:column; background:var(--white); }

    .list-scroll { flex:1; overflow-y:auto; }
    .list-scroll::-webkit-scrollbar { width:4px; }
    .list-scroll::-webkit-scrollbar-thumb { background:#ddd; border-radius:4px; }
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

    /* Toast & Modals */
    .toast { position:fixed; bottom:30px; right:30px; background:var(--dark); color:white; padding:12px 20px; border-radius:12px; font-size:13px; font-weight:600; z-index:9999; transform:translateY(80px); opacity:0; transition:all 0.35s cubic-bezier(0.175,0.885,0.32,1.275); pointer-events:none; display:flex; align-items:center; gap:8px; }
    .toast.show { transform:translateY(0); opacity:1; }
    .toast.ok  { background:#2d7a3e; }
    .toast.err { background:#d32f2f; }

    .modal { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(255,255,255,0.9); backdrop-filter:blur(5px); z-index:2000; align-items:center; justify-content:center; opacity:0; transition:opacity 0.3s ease; }
    .modal.open { display:flex; opacity:1; }
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
    .seccion-tag.ocupada::after { content:' ✗'; font-size:9px; }
    .seccion-picker-search { width:100%; padding:8px 12px; border-radius:8px; border:1px solid var(--gray-200); background:var(--white); font-size:13px; outline:none; margin-bottom:8px; font-family:Arial,sans-serif; }
    .legend-box { font-size:12px; color:var(--primary); background:rgba(45,122,62,0.1); padding:10px 14px; border-radius:8px; margin-bottom:10px; font-weight:600; display:none; line-height:1.4; border:1px solid rgba(45,122,62,0.2); }

    .animate-up { animation:fadeInUp 0.6s ease forwards; opacity:0; }
    @keyframes fadeInUp { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }
    .animate-delay-1 { animation-delay:0.1s; }
    .animate-delay-2 { animation-delay:0.2s; }

    @media(max-width:1100px){ .container{padding-left:20px;padding-right:20px;} .workspace{grid-template-columns:1fr;} .design-text{display:none;} .col-regiones,.col-zonas{border-right:none;border-bottom:1px solid var(--gray-200);} }
    </style>
</head>
<body>
<?php if (file_exists(__DIR__ . '/../../includes/components/sidebar_admin.php')) include __DIR__ . '/../../includes/components/sidebar_admin.php'; ?>

<div class="design-text">ESTRUCTURA TERRITORIAL</div>

<div class="container">

    <div class="page-header animate-up">
        <div>
            <div class="ai-badge">VISTA ADMINISTRADOR</div>
            <h1 class="main-title">Configuración<br>de Estructuras.</h1>
            <p class="description">Estructura operativa para: <strong id="desc-estado">Cargando...</strong>.</p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <button class="btn-secondary-action" onclick="abrirModalRegion()">
                <i class="fas fa-plus"></i> Nueva Región
            </button>
        </div>
    </div>

    <div class="tabs-bar animate-up animate-delay-1" id="tabs-circ">
        </div>

    <div class="sub-tabs-bar animate-up animate-delay-1" id="tabs-estados">
        </div>

    <div class="workspace animate-up animate-delay-2">

        <div class="col-regiones">
            <div class="col-header">
                <div class="col-header-title"><i class="fas fa-map" style="color:var(--primary)"></i> Región</div>
                <button class="btn-primary-action btn-sm" onclick="abrirModalRegion()">
                    <i class="fas fa-plus"></i> Nueva
                </button>
            </div>
            <div class="col-body" id="col-regiones-body">
                <div class="empty-col"><i class="fas fa-circle-notch fa-spin"></i> Cargando datos...</div>
            </div>
        </div>

        <div class="col-zonas">
            <div class="col-header">
                <div class="col-header-title"><i class="fas fa-layer-group" style="color:var(--primary)"></i> Zonas <span id="col-zonas-subtitle" style="font-weight:400;color:var(--text-muted);font-size:12px;margin-left:4px;"></span></div>
                <button class="btn-primary-action btn-sm" id="btn-nueva-zona" onclick="abrirModalZona()" style="display:none;">
                    <i class="fas fa-plus"></i> Nueva
                </button>
            </div>
            <div id="col-zonas-body" style="flex:1;display:flex;flex-direction:column;">
                <div class="hint-text"><i class="fas fa-arrow-left"></i>Selecciona una región.</div>
            </div>
        </div>

        <div class="col-secciones">
            <div class="col-header">
                <div class="col-header-title"><i class="fas fa-list-ol" style="color:var(--primary)"></i> Secciones <span id="col-sec-subtitle" style="font-weight:400;color:var(--text-muted);font-size:12px;margin-left:4px;"></span></div>
                <button class="btn-primary-action btn-sm" id="btn-asignar-sec" onclick="abrirModalAsignarSecciones()" style="display:none;">
                    <i class="fas fa-pen"></i> Editar
                </button>
            </div>
            <div id="col-secciones-body" style="flex:1;display:flex;flex-direction:column;">
                <div class="hint-text"><i class="fas fa-arrow-left"></i>Selecciona una zona.</div>
            </div>
        </div>

    </div>
</div>

<div class="toast" id="toast"></div>

<div class="modal" id="modal-region">
    <div class="modal-content" style="max-width:600px;">
        <div class="close-modal" onclick="cerrarModal('modal-region')"><i class="fas fa-times"></i></div>
        <h2 id="modal-region-title" style="font-size:24px;font-weight:800;margin-bottom:5px;">Nueva Región</h2>
        <p style="font-size:14px;color:#999;margin-bottom:28px;">Agrega una región geográfica dentro del estado seleccionado.</p>
        
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
            <select id="r-responsable" class="form-input-styled">
                <option value="">Cargando usuarios...</option>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">Descripción</label>
            <textarea id="r-desc" class="form-input-styled" placeholder="Descripción general de la región..." style="min-height: 60px;"></textarea>
        </div>

        <div class="form-group">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <label class="form-label">Municipios asignados a la Región *</label>
                <button type="button" class="btn-secondary-action btn-sm" onclick="seleccionarTodosMunicipios()" style="padding:4px 8px; font-size:11px;">
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
                <select id="z-responsable" class="form-input-styled">
                    <option value="">Cargando usuarios...</option>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Foco / Descripción</label>
            <textarea id="z-foco" class="form-input-styled" placeholder="Foco: Vivienda de interés social..."></textarea>
        </div>
        <div class="form-group">
            <label class="form-label">Colonias Clave</label>
            <input type="text" id="z-colonias" class="form-input-styled" placeholder="Ej. Pitic, Jesús García">
        </div>
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

<script>
/* ══════════════════════════════════════════════
   DATOS BASE (MAPEO DE CIRCUNSCRIPCIONES)
══════════════════════════════════════════════ */
const MAPA_CIRCUNSCRIPCIONES = <?php echo $circunscripciones_json; ?>;
const LISTA_CIRC = Object.keys(MAPA_CIRCUNSCRIPCIONES);

let circActual = LISTA_CIRC[0]; // Ej: Circunscripción 1
let estadoActual = MAPA_CIRCUNSCRIPCIONES[circActual][0]; // Ej: Baja California

let MUNICIPIOS_BASE = {};
let USUARIOS_BASE = [];

/* ══════════════════════════════════════════════
   API HELPER
══════════════════════════════════════════════ */
const API_BASE = window.location.pathname;

async function apiGet(accion) {
    const res = await fetch(`${API_BASE}?api=${accion}&estado=${encodeURIComponent(estadoActual)}`);
    return res.json();
}
async function apiPost(accion, datos) {
    datos.estado = estadoActual;
    const res = await fetch(`${API_BASE}?api=${accion}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(datos)
    });
    return res.json();
}

/* ══════════════════════════════════════════════
   ESTADO EN MEMORIA
══════════════════════════════════════════════ */
let appData             = { regiones: [] };
let regionActiva        = null;   
let zonaActiva          = null;   
let editandoRegionId    = null;
let editandoZonaId      = null;
let seccionesSelPicker  = new Set();
let seccionesSelEdit    = new Set();
let municipiosSelPicker = new Set();

/* ══════════════════════════════════════════════
   TOAST
══════════════════════════════════════════════ */
function toast(msg, tipo='ok') {
    const el = document.getElementById('toast');
    el.className = 'toast ' + tipo;
    el.innerHTML = `<i class="fas fa-${tipo==='ok'?'check-circle':'exclamation-circle'}"></i> ${msg}`;
    el.classList.add('show');
    clearTimeout(window._toastTimer);
    window._toastTimer = setTimeout(() => el.classList.remove('show'), 3000);
}

/* ══════════════════════════════════════════════
   NAVEGACIÓN DE TABS (CIRCUNSCRIPCIONES Y ESTADOS)
══════════════════════════════════════════════ */
function initTabs() {
    renderCircTabs();
    renderEstadosTabs();
}

function renderCircTabs() {
    const container = document.getElementById('tabs-circ');
    container.innerHTML = LISTA_CIRC.map(circ => {
        const isActive = (circ === circActual) ? 'active' : '';
        return `<button class="tab-btn ${isActive}" onclick="seleccionarCirc('${circ}')">${circ}</button>`;
    }).join('');
}

function renderEstadosTabs() {
    const container = document.getElementById('tabs-estados');
    const estados = MAPA_CIRCUNSCRIPCIONES[circActual];
    
    container.innerHTML = estados.map(est => {
        const isActive = (est === estadoActual) ? 'active' : '';
        return `<button class="sub-tab-btn ${isActive}" onclick="seleccionarEstado('${est}')">${est}</button>`;
    }).join('');
}

async function seleccionarCirc(circ) {
    if (circ === circActual) return;
    circActual = circ;
    estadoActual = MAPA_CIRCUNSCRIPCIONES[circActual][0]; // Seleccionar primer estado por defecto
    renderCircTabs();
    renderEstadosTabs();
    await resetAndLoadData();
}

async function seleccionarEstado(est) {
    if (est === estadoActual) return;
    estadoActual = est;
    renderEstadosTabs();
    await resetAndLoadData();
}

async function resetAndLoadData() {
    regionActiva = null; 
    zonaActiva = null;
    document.getElementById('desc-estado').textContent = estadoActual;
    
    document.getElementById('col-regiones-body').innerHTML = '<div class="empty-col"><i class="fas fa-circle-notch fa-spin"></i> Cargando datos...</div>';
    document.getElementById('col-zonas-body').innerHTML = '<div class="hint-text"><i class="fas fa-arrow-left"></i>Selecciona una región.</div>';
    document.getElementById('col-secciones-body').innerHTML = '<div class="hint-text"><i class="fas fa-arrow-left"></i>Selecciona una zona.</div>';
    document.getElementById('col-zonas-subtitle').textContent = '';
    document.getElementById('col-sec-subtitle').textContent = '';
    document.getElementById('btn-nueva-zona').style.display = 'none';
    document.getElementById('btn-asignar-sec').style.display = 'none';
    
    await cargarCatalogos();
    await cargarDatos();
}


/* ══════════════════════════════════════════════
   CARGA DE CATÁLOGOS Y DATOS
══════════════════════════════════════════════ */
async function cargarCatalogos() {
    try {
        const data = await apiGet('get_catalogos');
        if (data.ok) {
            MUNICIPIOS_BASE = data.municipios || {};
            USUARIOS_BASE = data.usuarios || [];
        }
    } catch(e) {
        console.error('Error al cargar catálogos', e);
    }
}

async function cargarDatos() {
    try {
        const data = await apiGet('cargar');
        if (!data.ok) throw new Error(data.msg || 'Error');
        appData = { regiones: data.regiones || [] };
        renderRegiones();
        renderZonas();
        renderSecciones();
    } catch(e) {
        document.getElementById('col-regiones-body').innerHTML =
            `<div class="empty-col"><i class="fas fa-exclamation-triangle" style="color:#d32f2f"></i>Error al cargar datos.<br><small>${e.message}</small></div>`;
    }
}

function getSeccionesOcupadas(excluirZonaId = null) {
    const ocupadas = new Set();
    const region = appData.regiones.find(r => r.id === regionActiva);
    if (!region) return ocupadas;
    for (const z of (region.zonas||[])) {
        if (z.id === excluirZonaId) continue;
        for (const s of (z.secciones||[])) ocupadas.add(s);
    }
    return ocupadas;
}

function renderSelectUsuarios(selectId, seleccionado = null) {
    const select = document.getElementById(selectId);
    let html = '<option value="">Sin responsable asignado</option>';
    
    USUARIOS_BASE.forEach(u => {
        const isSelected = (u.id == seleccionado) ? 'selected' : '';
        const rolLabel = u.rol ? ` - ${u.rol.replace('coordinador_', '').toUpperCase()}` : '';
        html += `<option value="${u.id}" ${isSelected}>${u.nombre}${rolLabel}</option>`;
    });
    
    select.innerHTML = html;
}

/* ══════════════════════════════════════════════
   RENDER REGIONES
══════════════════════════════════════════════ */
function renderRegiones() {
    const body = document.getElementById('col-regiones-body');
    if (!appData.regiones.length) {
        body.innerHTML = '<div class="empty-col"><i class="fas fa-map-marked-alt"></i>No hay regiones.<br>Crea la primera.</div>';
        return;
    }
    body.innerHTML = appData.regiones.map(r => {
        const totalMiembros = (r.zonas||[]).reduce((s,z) => s+(z.miembros||0), 0);
        const pct    = r.meta > 0 ? Math.min(100, Math.round(totalMiembros/r.meta*100)) : 0;
        const nZonas = (r.zonas||[]).length;
        const nMunis = (r.municipios||[]).length;
        
        const userResp = USUARIOS_BASE.find(u => u.id == r.responsable);
        const nombResp = userResp ? userResp.nombre : null;

        return `<div class="region-card ${regionActiva===r.id?'active':''}" onclick="selectRegion(${r.id})">
            <div class="card-actions">
                <button class="card-action-btn" onclick="event.stopPropagation();editarRegion(${r.id})"><i class="fas fa-pen"></i></button>
                <button class="card-action-btn del" onclick="event.stopPropagation();eliminarRegion(${r.id})"><i class="fas fa-trash"></i></button>
            </div>
            <div class="card-name">${r.nombre}</div>
            ${nombResp ? `<div style="font-size:12px; color:var(--text-muted); margin-bottom:8px;"><i class="fas fa-user" style="color:var(--primary);"></i> ${nombResp}</div>` : ''}
            <div class="card-meta">
                <span class="card-miembros">${totalMiembros.toLocaleString('es-MX')} Miembros</span>
                <span class="card-pct">${pct}%</span>
            </div>
            <div class="card-bar-track"><div class="card-bar-fill" style="width:${pct}%"></div></div>
            <div class="card-secciones-count">${nZonas} zona${nZonas!==1?'s':''} · ${nMunis} municipio${nMunis!==1?'s':''}</div>
        </div>`;
    }).join('');
}

function selectRegion(id) {
    regionActiva = id; zonaActiva = null;
    renderRegiones(); renderZonas(); renderSecciones();
}

/* ══════════════════════════════════════════════
   RENDER ZONAS
══════════════════════════════════════════════ */
function renderZonas() {
    const body     = document.getElementById('col-zonas-body');
    const btnNueva = document.getElementById('btn-nueva-zona');
    const subtitle = document.getElementById('col-zonas-subtitle');

    if (!regionActiva) {
        body.innerHTML = '<div class="hint-text"><i class="fas fa-arrow-left"></i>Selecciona una región.</div>';
        btnNueva.style.display = 'none'; subtitle.textContent = '';
        return;
    }
    const region = appData.regiones.find(r => r.id === regionActiva);
    if (!region) return;
    subtitle.textContent = region.nombre;
    btnNueva.style.display = 'flex';

    if (!region.zonas || !region.zonas.length) {
        body.innerHTML = '<div class="hint-text"><i class="fas fa-layer-group"></i>No hay zonas en esta región.</div>';
        return;
    }
    body.innerHTML = '<div class="col-body">' + region.zonas.map(z => {
        const pct  = z.meta > 0 ? Math.min(100, Math.round((z.miembros||0)/z.meta*100)) : 0;
        const nSec = (z.secciones||[]).length;
        
        const userResp = USUARIOS_BASE.find(u => u.id == z.responsable);
        const nombResp = userResp ? userResp.nombre : null;

        return `<div class="zona-card ${zonaActiva===z.id?'active':''}" onclick="selectZona(${z.id})">
            <div class="card-actions">
                <button class="card-action-btn" onclick="event.stopPropagation();editarZona(${z.id})"><i class="fas fa-pen"></i></button>
                <button class="card-action-btn del" onclick="event.stopPropagation();eliminarZona(${z.id})"><i class="fas fa-trash"></i></button>
            </div>
            <div class="card-name">${z.nombre}${z.clave?' <span style="font-size:11px;color:var(--text-muted);font-weight:400;">('+z.clave+')</span>':''}</div>
            ${nombResp ? `<div style="font-size:12px; color:var(--text-muted); margin-bottom:8px;"><i class="fas fa-user" style="color:var(--primary);"></i> ${nombResp}</div>` : ''}
            <div class="card-meta">
                <span class="card-miembros">${(z.miembros||0).toLocaleString('es-MX')} Miembros</span>
                <span class="card-pct">${pct}%</span>
            </div>
            <div class="card-bar-track"><div class="card-bar-fill" style="width:${pct}%"></div></div>
            <div class="card-secciones-count">${nSec} sección${nSec!==1?'es':''} asignada${nSec!==1?'s':''} · Meta: ${(z.meta||0).toLocaleString('es-MX')}</div>
            ${z.foco?'<div class="card-meta-bottom">'+z.foco+'</div>':''}
        </div>`;
    }).join('') + '</div>';
}

function selectZona(id) {
    zonaActiva = id;
    renderZonas(); renderSecciones();
}

/* ══════════════════════════════════════════════
   RENDER SECCIONES
══════════════════════════════════════════════ */
function renderSecciones() {
    const body     = document.getElementById('col-secciones-body');
    const btnAsig  = document.getElementById('btn-asignar-sec');
    const subtitle = document.getElementById('col-sec-subtitle');

    if (!zonaActiva) {
        body.innerHTML = '<div class="hint-text"><i class="fas fa-arrow-left"></i>Selecciona una zona.</div>';
        btnAsig.style.display = 'none'; subtitle.textContent = '';
        return;
    }
    const region = appData.regiones.find(r => r.id === regionActiva);
    const zona   = (region?.zonas||[]).find(z => z.id === zonaActiva);
    if (!zona) return;

    subtitle.textContent = zona.nombre;
    btnAsig.style.display = 'flex';

    const secs = zona.secciones || [];
    const infoBox = `<div class="zona-info-box">
        <div class="zi-title">${zona.nombre}${zona.clave?' · '+zona.clave:''}</div>
        ${zona.foco?'<div class="zi-foco">'+zona.foco+'</div>':''}
        <div class="zi-bullet"><strong>Secciones (${secs.length}):</strong> ${secs.length ? secs.slice(0,8).join(', ')+(secs.length>8?'...':'') : '—'}</div>
        ${zona.colonias?'<div class="zi-bullet"><strong>Colonias:</strong> '+zona.colonias+'</div>':''}
    </div>`;

    if (!secs.length) {
        body.innerHTML = infoBox + `<div class="hint-text"><i class="fas fa-list-ol"></i>Sin secciones asignadas.<br>
            <button class="btn-primary-action btn-sm" onclick="abrirModalAsignarSecciones()" style="margin-top:10px;">
                <i class="fas fa-plus"></i> Asignar
            </button></div>`;
        return;
    }
    body.innerHTML = infoBox + '<div class="list-scroll">' +
        secs.map(s => `<div class="seccion-item">
            <span class="seccion-num">Sección ${s}</span>
            <span class="seccion-assigned"><i class="fas fa-check-circle"></i> Asignada</span>
        </div>`).join('') + '</div>';
}

/* ══════════════════════════════════════════════
   CRUD REGIONES
══════════════════════════════════════════════ */
function abrirModalRegion(id=null) {
    editandoRegionId = id;
    document.getElementById('modal-region-title').textContent = id ? 'Editar Región' : 'Nueva Región';
    ['r-nombre','r-desc','r-meta','r-responsable','r-muni-search'].forEach(f => document.getElementById(f).value = '');
    municipiosSelPicker = new Set();

    let idResponsableActual = null;

    if (id) {
        const r = appData.regiones.find(x => x.id === id);
        if (r) {
            document.getElementById('r-nombre').value      = r.nombre      || '';
            document.getElementById('r-desc').value        = r.descripcion || '';
            document.getElementById('r-meta').value        = r.meta        || '';
            idResponsableActual                            = r.responsable;
            municipiosSelPicker = new Set(r.municipios || []);
        }
    }
    
    renderSelectUsuarios('r-responsable', idResponsableActual);
    renderMunicipiosPicker();
    document.getElementById('modal-region').classList.add('open');
}

function seleccionarTodosMunicipios() {
    const allMunis = Object.keys(MUNICIPIOS_BASE || {});
    allMunis.forEach(m => municipiosSelPicker.add(m));
    renderMunicipiosPicker();
}

function renderMunicipiosPicker() {
    const q        = document.getElementById('r-muni-search').value.toLowerCase();
    const allMunis = Object.keys(MUNICIPIOS_BASE || {});
    const munis    = allMunis.filter(m => m.toLowerCase().includes(q));
    const picker   = document.getElementById('muni-picker');

     picker.innerHTML = munis.map(m => {
        const esSel = municipiosSelPicker.has(m);
        const cls   = esSel ? 'selected' : '';
        
        return `<span class="seccion-tag ${cls}" data-mun="${m.replace(/"/g,'&quot;')}" onclick="toggleMunicipio(this)">${m}</span>`;
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
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Guardando...';

    try {
        const res = await apiPost('guardar_region', {
            id:          editandoRegionId,
            nombre,
            desc:        document.getElementById('r-desc').value.trim(),
            meta:        parseInt(document.getElementById('r-meta').value) || 0,
            responsable: document.getElementById('r-responsable').value || null,
            municipios:  [...municipiosSelPicker].sort()
        });
        if (!res.ok) throw new Error(res.msg || 'Error');
        if (!editandoRegionId) regionActiva = res.id;
        await cargarDatos();
        cerrarModal('modal-region');
        toast(editandoRegionId ? 'Región actualizada.' : 'Región creada.');
    } catch(e) {
        toast('Error: ' + e.message, 'err');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check"></i> Guardar';
    }
}

function editarRegion(id) { abrirModalRegion(id); }

async function eliminarRegion(id) {
    if (!confirm('¿Eliminar esta región y todas sus zonas?')) return;
    try {
        const res = await apiPost('eliminar_region', { id });
        if (!res.ok) throw new Error(res.msg || 'Error');
        if (regionActiva === id) { regionActiva = null; zonaActiva = null; }
        await cargarDatos();
        toast('Región eliminada.');
    } catch(e) {
        toast('Error: ' + e.message, 'err');
    }
}

/* ══════════════════════════════════════════════
   CRUD ZONAS
══════════════════════════════════════════════ */
function abrirModalZona(id=null) {
    editandoZonaId = id;
    document.getElementById('modal-zona-title').textContent = id ? 'Editar Zona' : 'Nueva Zona';
    ['z-nombre','z-clave','z-foco','z-colonias','z-meta','z-responsable','z-sec-search'].forEach(f => document.getElementById(f).value = '');
    seccionesSelPicker = new Set();
    let idResponsableActual = null;

    const region = appData.regiones.find(r => r.id === regionActiva);
    const legend = document.getElementById('z-muni-legend');
    if (region?.municipios?.length) {
        legend.innerHTML = '<i class="fas fa-filter"></i> <strong>Municipios de la región:</strong> ' + region.municipios.join(', ');
        legend.style.display = 'block';
    } else {
        legend.innerHTML = '<i class="fas fa-exclamation-triangle"></i> La región no tiene municipios asignados.';
        legend.style.display = 'block';
    }

    if (id && region) {
        const z = (region.zonas||[]).find(z => z.id === id);
        if (z) {
            document.getElementById('z-nombre').value      = z.nombre      || '';
            document.getElementById('z-clave').value       = z.clave       || '';
            document.getElementById('z-foco').value        = z.foco        || '';
            document.getElementById('z-colonias').value    = z.colonias    || '';
            document.getElementById('z-meta').value        = z.meta        || '';
            idResponsableActual                            = z.responsable;
            seccionesSelPicker = new Set(z.secciones||[]);
        }
    }
    
    renderSelectUsuarios('z-responsable', idResponsableActual);
    renderSeccionesPicker('seccion-picker', 'z-sec-search', seccionesSelPicker);
    document.getElementById('modal-zona').classList.add('open');
}

function getSeccionesPermitidas() {
    const region = appData.regiones.find(r => r.id === regionActiva);
    const munis  = region?.municipios || [];
    let permitidas = [];
    munis.forEach(m => { if (MUNICIPIOS_BASE[m]) permitidas = permitidas.concat(MUNICIPIOS_BASE[m]); });
    return permitidas.sort((a,b) => parseInt(a)-parseInt(b));
}

function renderSeccionesPicker(pickerId, searchId, selSet) {
    const q        = document.getElementById(searchId).value.toLowerCase();
    const secs     = getSeccionesPermitidas().filter(s => s.includes(q));
    const picker   = document.getElementById(pickerId);
    const ocupadas = getSeccionesOcupadas(editandoZonaId);

    picker.innerHTML = secs.map(s => {
        const esOcupada = ocupadas.has(s) && !selSet.has(s);
        const esSel     = selSet.has(s);
        const cls       = esSel ? 'selected' : esOcupada ? 'ocupada' : '';
        const click     = esOcupada ? '' : `onclick="toggleSeccion(this)"`;
        const title     = esOcupada ? 'title="Ya asignada a otra zona"' : '';
        return `<span class="seccion-tag ${cls}" data-sec="${s}" ${click} ${title}>${s}</span>`;
    }).join('');
    document.getElementById('sec-counter').textContent = selSet.size + ' secciones seleccionadas';
}

function filtrarSeccionesPicker() { renderSeccionesPicker('seccion-picker','z-sec-search',seccionesSelPicker); }

function toggleSeccion(el) {
    const s = el.dataset.sec;
    if (seccionesSelPicker.has(s)) seccionesSelPicker.delete(s);
    else {
        if (getSeccionesOcupadas(editandoZonaId).has(s)) return;
        seccionesSelPicker.add(s);
    }
    renderSeccionesPicker('seccion-picker','z-sec-search',seccionesSelPicker);
}

async function guardarZona() {
    const nombre = document.getElementById('z-nombre').value.trim();
    if (!nombre)        { toast('El nombre es obligatorio.','err'); return; }
    if (!regionActiva)  { toast('Selecciona una región primero.','err'); return; }

    const btn = document.getElementById('btn-save-zona');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Guardando...';

    try {
        const res = await apiPost('guardar_zona', {
            id:          editandoZonaId,
            region_id:   regionActiva,
            nombre,
            clave:       document.getElementById('z-clave').value.trim(),
            foco:        document.getElementById('z-foco').value.trim(),
            colonias:    document.getElementById('z-colonias').value.trim(),
            meta:        parseInt(document.getElementById('z-meta').value) || 0,
            responsable: document.getElementById('z-responsable').value || null,
            secciones:   [...seccionesSelPicker].sort((a,b)=>parseInt(a)-parseInt(b))
        });
        if (!res.ok) throw new Error(res.msg || 'Error');
        if (!editandoZonaId) zonaActiva = res.id;
        await cargarDatos();
        cerrarModal('modal-zona');
        toast(editandoZonaId ? 'Zona actualizada.' : 'Zona creada.');
    } catch(e) {
        toast('Error: ' + e.message, 'err');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check"></i> Guardar';
    }
}

function editarZona(id) { abrirModalZona(id); }

async function eliminarZona(id) {
    if (!confirm('¿Eliminar esta zona permanentemente?')) return;
    try {
        const res = await apiPost('eliminar_zona', { id });
        if (!res.ok) throw new Error(res.msg || 'Error');
        if (zonaActiva === id) zonaActiva = null;
        await cargarDatos();
        toast('Zona eliminada.');
    } catch(e) {
        toast('Error: ' + e.message, 'err');
    }
}

/* ══════════════════════════════════════════════
   MODAL EDITAR SECCIONES
══════════════════════════════════════════════ */
function abrirModalAsignarSecciones() {
    const region = appData.regiones.find(r => r.id === regionActiva);
    const zona   = (region?.zonas||[]).find(z => z.id === zonaActiva);
    if (!zona) return;

    document.getElementById('modal-sec-zona-name').textContent = zona.nombre;
    document.getElementById('edit-sec-search').value = '';

    const legend = document.getElementById('edit-muni-legend');
    if (region.municipios?.length) {
        legend.innerHTML = '<i class="fas fa-filter"></i> <strong>Municipios de la región:</strong> ' + region.municipios.join(', ');
        legend.style.display = 'block';
    }

    seccionesSelEdit = new Set(zona.secciones||[]);
    renderSeccionesEdit();
    document.getElementById('modal-secciones').classList.add('open');
}

function renderSeccionesEdit() {
    const q        = document.getElementById('edit-sec-search').value.toLowerCase();
    const secs     = getSeccionesPermitidas().filter(s => s.includes(q));
    const ocupadas = getSeccionesOcupadas(zonaActiva);

    document.getElementById('edit-seccion-picker').innerHTML = secs.map(s => {
        const esOcupada = ocupadas.has(s) && !seccionesSelEdit.has(s);
        const esSel     = seccionesSelEdit.has(s);
        const cls       = esSel ? 'selected' : esOcupada ? 'ocupada' : '';
        const click     = esOcupada ? '' : `onclick="toggleSeccionEdit(this)"`;
        const title     = esOcupada ? 'title="Ya asignada a otra zona"' : '';
        return `<span class="seccion-tag ${cls}" data-sec="${s}" ${click} ${title}>${s}</span>`;
    }).join('');
    document.getElementById('edit-sec-counter').textContent = seccionesSelEdit.size + ' secciones seleccionadas';
}

function filtrarSeccionesEdit() { renderSeccionesEdit(); }

function toggleSeccionEdit(el) {
    const s = el.dataset.sec;
    if (seccionesSelEdit.has(s)) seccionesSelEdit.delete(s);
    else {
        if (getSeccionesOcupadas(zonaActiva).has(s)) return;
        seccionesSelEdit.add(s);
    }
    renderSeccionesEdit();
}

async function guardarSeccionesEdit() {
    const btn = document.getElementById('btn-save-secciones');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Guardando...';
    try {
        const res = await apiPost('guardar_secciones', {
            zona_id:   zonaActiva,
            secciones: [...seccionesSelEdit].sort((a,b)=>parseInt(a)-parseInt(b))
        });
        if (!res.ok) throw new Error(res.msg || 'Error');
        await cargarDatos();
        cerrarModal('modal-secciones');
        toast('Secciones guardadas.');
    } catch(e) {
        toast('Error: ' + e.message, 'err');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check"></i> Guardar';
    }
}

/* ══════════════════════════════════════════════
   INICIALIZACIÓN
══════════════════════════════════════════════ */
function cerrarModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal').forEach(el =>
    el.addEventListener('click', e => { if (e.target === el) el.classList.remove('open'); })
);

document.addEventListener('DOMContentLoaded', async () => { 
    initTabs();
    document.getElementById('desc-estado').textContent = estadoActual;
    await cargarCatalogos();
    await cargarDatos(); 
});
</script>
</body>
</html>