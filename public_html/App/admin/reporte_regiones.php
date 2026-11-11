<?php
// ── Seguridad ──────────────────────────────────────────────
if (file_exists(__DIR__ . '/../../includes/bootstrap.php')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
} else { session_start(); }

$rol = $_SESSION['user_role'] ?? '';
$permitidos = ['admin','administrador','superadmin','super admin', 'coordinador_estatal'];
if (!in_array($rol, $permitidos)) { header('Location: /public/index.php'); exit(); }

// ── Conexión y carga de datos ──────────────────────────────
$eventos      = [];
$activaciones = [];
$error_db     = null;

try {
    $dbConfigPath = __DIR__ . '/../../includes/database.php';
    $dbConfig = file_exists($dbConfigPath) ? require $dbConfigPath : [
        'host'=>'localhost','database'=>'u881824383_pverde',
        'username'=>'u881824383_proyverde','password'=>'PProyectoverde8#',
        'charset'=>'utf8mb4',
        'options'=>[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
    ];
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options'] ?? []);

    $stmt = $pdo->query("
        SELECT id, codigo_referencia, titulo, estado, municipio, seccion, region, zona,
               creado_por_id, creado_por_rol,
               DATE_FORMAT(fecha_creacion,'%Y-%m-%d') AS fecha_creacion,
               DATE_FORMAT(fecha,'%Y-%m-%d') AS fecha
        FROM agenda_eventos
        WHERE activo = 1
        ORDER BY fecha_creacion DESC
    ");
    $eventos = $stmt->fetchAll();

    $stmt = $pdo->query("
        SELECT id, codigo_referencia, titulo, estado, municipio, seccion_electoral, region, zona,
               creado_por_id, creado_por_rol,
               DATE_FORMAT(fecha_creacion,'%Y-%m-%d') AS fecha_creacion,
               DATE_FORMAT(fecha_actividad,'%Y-%m-%d') AS fecha_actividad
        FROM activaciones
        WHERE activo = 1
        ORDER BY fecha_creacion DESC
    ");
    $activaciones = $stmt->fetchAll();

} catch (PDOException $e) {
    $error_db = $e->getMessage();
}

// ── Marcar sin_region y armar resumen por rol ───────────────
function marcarYResumir(array $rows): array {
    $resumen = []; // rol => ['total'=>n,'sin_region'=>n]
    foreach ($rows as &$r) {
        if (!array_key_exists('seccion', $r) && array_key_exists('seccion_electoral', $r)) {
            $r['seccion'] = $r['seccion_electoral'];
        }
        $r['sin_region'] = (trim((string)($r['region'] ?? '')) === '') ? 1 : 0;
        $rolFila = $r['creado_por_rol'] ?: '(sin rol)';
        if (!isset($resumen[$rolFila])) $resumen[$rolFila] = ['total'=>0,'sin_region'=>0];
        $resumen[$rolFila]['total']++;
        if ($r['sin_region']) $resumen[$rolFila]['sin_region']++;
    }
    unset($r);
    arsort($resumen);
    return [$rows, $resumen];
}

[$eventos, $resumenEventos]           = marcarYResumir($eventos);
[$activaciones, $resumenActivaciones] = marcarYResumir($activaciones);

$totalEventos      = count($eventos);
$sinRegionEventos  = array_sum(array_column($eventos, 'sin_region'));
$totalAct          = count($activaciones);
$sinRegionAct      = array_sum(array_column($activaciones, 'sin_region'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reporte de Región/Zona - Admin</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root { --primary:#2d7a3e; --primary-dark:#1f5a2d; --dark:#1a1a1a; --light:#f0f7f0; --white:#ffffff; --gray-100:#f8f9fa; --gray-200:#e9ecef; --text-muted:#6c757d; --shadow-sm:0 10px 30px rgba(0,0,0,0.05); }
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'Arial',sans-serif; background:var(--light); color:var(--dark); overflow-x:hidden; }
.container { max-width:100%; margin:0; background:var(--white); min-height:100vh; position:relative; padding-left:330px; padding-right:50px; padding-bottom:60px; transition:padding-left 0.3s ease; overflow-x:hidden; }
.page-header { padding:50px 0 30px 0; }
.ai-badge { color:var(--primary); font-size:13px; font-weight:800; letter-spacing:4px; margin-bottom:15px; display:inline-block; text-transform:uppercase; background:rgba(45,122,62,0.1); padding:5px 15px; border-radius:20px; }
.main-title { font-size:44px; font-weight:900; line-height:1; margin-bottom:12px; color:var(--dark); }
.description { color:#666; font-size:15px; line-height:1.6; max-width:700px; }
.stats-row { display:grid; grid-template-columns:repeat(4,1fr); gap:20px; margin-bottom:30px; }
.stat-card { background:var(--white); border:1px solid var(--gray-200); border-radius:20px; padding:25px 30px; box-shadow:var(--shadow-sm); display:flex; align-items:center; gap:18px; }
.stat-icon { width:52px; height:52px; border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:22px; flex-shrink:0; }
.stat-icon.verde { background:rgba(45,122,62,.1); color:var(--primary); }
.stat-icon.rojo { background:#fff5f5; color:#e53935; }
.stat-label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:var(--text-muted); margin-bottom:5px; }
.stat-val { font-size:28px; font-weight:900; color:var(--dark); line-height:1; }
.table-section { background:var(--white); border-radius:20px; padding:30px; border:1px solid var(--gray-200); box-shadow:var(--shadow-sm); margin-bottom:30px; }
.section-header { margin-bottom:20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; }
.section-title { font-size:18px; font-weight:800; color:var(--dark); }
.modern-table { width:100%; border-collapse:separate; border-spacing:0; }
.modern-table th { text-align:left; padding:12px 16px; color:var(--text-muted); font-size:11px; text-transform:uppercase; letter-spacing:1px; border-bottom:2px solid var(--gray-200); white-space:nowrap; }
.modern-table td { padding:14px 16px; border-bottom:1px solid var(--gray-200); font-size:13px; color:var(--dark); font-weight:500; vertical-align:middle; }
.modern-table tr:hover td { background:var(--gray-100); }
.modern-table tr:last-child td { border-bottom:none; }
.badge { padding:4px 10px; border-radius:20px; font-size:10px; font-weight:700; text-transform:uppercase; display:inline-block; white-space:nowrap; }
.badge-ok { background:#f0fdf4; color:#16a34a; border:1px solid #bbf7d0; }
.badge-sin { background:#fff5f5; color:#e53935; border:1px solid #fecaca; }
.tabs { display:flex; gap:8px; margin-bottom:20px; }
.tab-btn { padding:10px 22px; border-radius:12px; border:2px solid var(--gray-200); background:var(--gray-100); color:var(--text-muted); font-weight:700; font-size:13px; cursor:pointer; transition:.2s; font-family:Arial,sans-serif; }
.tab-btn.active { background:var(--primary); border-color:var(--primary); color:#fff; }
.filters-bar { background:var(--white); border:1px solid var(--gray-200); border-radius:16px; padding:18px; margin-bottom:20px; display:flex; gap:15px; align-items:flex-end; box-shadow:var(--shadow-sm); flex-wrap:wrap; }
.filter-group { display:flex; flex-direction:column; gap:6px; flex:1; min-width:180px; }
.form-label { font-size:11px; font-weight:700; text-transform:uppercase; color:var(--text-muted); letter-spacing:0.5px; }
.form-input-styled { width:100%; padding:11px 14px; border-radius:10px; border:2px solid var(--gray-200); background:var(--gray-100); font-size:13px; font-weight:600; color:var(--dark); outline:none; appearance:none; font-family:Arial,sans-serif; }
.form-input-styled:focus { border-color:var(--primary); background:var(--white); }
.chk-group { display:flex; align-items:center; gap:8px; padding-bottom:11px; }
.chk-group input { width:18px; height:18px; accent-color:var(--primary); cursor:pointer; }
.chk-group label { font-size:13px; font-weight:600; color:var(--dark); cursor:pointer; }
.empty-row td { text-align:center; padding:50px 20px; color:var(--text-muted); font-size:13px; }
.resumen-rol td.num { text-align:right; font-weight:800; }
.mini-col{ border:1px solid var(--gray-200); border-radius:12px; overflow:hidden; display:flex; flex-direction:column; height:360px; }
.mini-col-header{ display:flex; justify-content:space-between; align-items:center; padding:10px 12px; background:var(--gray-100); border-bottom:1px solid var(--gray-200); font-size:12px; font-weight:800; text-transform:uppercase; letter-spacing:.5px; color:var(--dark); }
.mini-col-header i{ color:var(--primary); margin-right:4px; }
.mini-btn-nueva{ font-size:10px; font-weight:700; padding:4px 9px; border-radius:8px; border:none; background:var(--primary); color:#fff; cursor:pointer; font-family:Arial,sans-serif; }
.mini-col-body{ flex:1; overflow-y:auto; padding:10px; display:flex; flex-direction:column; gap:8px; }
.mini-hint{ margin:auto; text-align:center; font-size:12px; color:var(--text-muted); padding:20px; }
.mini-hint i{ display:block; margin-bottom:6px; font-size:16px; }
.mini-card{ border:2px solid var(--gray-200); border-radius:10px; padding:10px 12px; cursor:pointer; transition:.15s; position:relative; }
.mini-card:hover{ border-color:#c9e6cf; }
.mini-card.active{ border-color:var(--primary); background:#f0fdf4; }
.mini-card .mc-name{ font-size:13px; font-weight:800; color:var(--dark); margin-bottom:3px; padding-right:20px; }
.mini-card .mc-sub{ font-size:11px; color:var(--text-muted); }
.mini-card .mc-edit{ position:absolute; top:8px; right:8px; font-size:11px; color:var(--text-muted); background:none; border:none; cursor:pointer; padding:3px; }
.mini-card .mc-edit:hover{ color:var(--primary); }
.mini-sec-row{ display:flex; justify-content:space-between; align-items:center; border:2px solid var(--gray-200); border-radius:8px; padding:7px 12px; cursor:pointer; font-size:12px; transition:.15s; }
.mini-sec-row:hover{ border-color:#c9e6cf; }
.mini-sec-row.active{ border-color:var(--primary); background:#f0fdf4; font-weight:700; }
.mini-sec-row .msr-check{ color:var(--primary); display:none; }
.mini-sec-row.active .msr-check{ display:inline; }
@media(max-width:1024px){ .container{padding-left:20px;padding-right:20px;} .stats-row{grid-template-columns:repeat(2,1fr);} }
</style>
</head>
<body>
<?php if (file_exists(__DIR__ . '/../../includes/components/sidebar_admin.php')) include __DIR__ . '/../../includes/components/sidebar_admin.php'; ?>

<div class="container">

    <div class="page-header">
        <div class="ai-badge">DIAGNÓSTICO</div>
        <h1 class="main-title">Reporte de Región / Zona.</h1>
        <p class="description">Eventos y activaciones activos, quién los creó y cuáles se quedaron sin región asignada (por municipios que aún no están dados de alta en Estructura).</p>
    </div>

    <?php if ($error_db): ?>
    <div style="background:#f8d7da;color:#721c24;padding:15px;border-radius:10px;margin-bottom:20px;">
        <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error_db) ?>
    </div>
    <?php endif; ?>

    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon verde"><i class="fas fa-calendar"></i></div>
            <div><div class="stat-label">Eventos activos</div><div class="stat-val"><?= $totalEventos ?></div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon rojo"><i class="fas fa-triangle-exclamation"></i></div>
            <div><div class="stat-label">Eventos sin región</div><div class="stat-val"><?= $sinRegionEventos ?></div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon verde"><i class="fas fa-bolt"></i></div>
            <div><div class="stat-label">Activaciones activas</div><div class="stat-val"><?= $totalAct ?></div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon rojo"><i class="fas fa-triangle-exclamation"></i></div>
            <div><div class="stat-label">Activaciones sin región</div><div class="stat-val"><?= $sinRegionAct ?></div></div>
        </div>
    </div>

    <div class="table-section">
        <div class="section-header"><div class="section-title">Resumen por rol que creó el registro</div></div>
        <div style="overflow-x:auto;">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>Rol</th>
                    <th style="text-align:right;">Eventos</th>
                    <th style="text-align:right;">Eventos sin región</th>
                    <th style="text-align:right;">Activaciones</th>
                    <th style="text-align:right;">Activaciones sin región</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $todosRoles = array_unique(array_merge(array_keys($resumenEventos), array_keys($resumenActivaciones)));
                sort($todosRoles);
                foreach ($todosRoles as $rolNombre):
                    $re = $resumenEventos[$rolNombre] ?? ['total'=>0,'sin_region'=>0];
                    $ra = $resumenActivaciones[$rolNombre] ?? ['total'=>0,'sin_region'=>0];
                ?>
                <tr class="resumen-rol">
                    <td><?= htmlspecialchars($rolNombre) ?></td>
                    <td class="num"><?= $re['total'] ?></td>
                    <td class="num" style="color:<?= $re['sin_region']>0 ? '#e53935':'inherit' ?>"><?= $re['sin_region'] ?></td>
                    <td class="num"><?= $ra['total'] ?></td>
                    <td class="num" style="color:<?= $ra['sin_region']>0 ? '#e53935':'inherit' ?>"><?= $ra['sin_region'] ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($todosRoles)): ?>
                <tr class="empty-row"><td colspan="5">Sin datos.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>

    <div class="table-section">
        <div class="tabs">
            <button class="tab-btn active" id="tab-eventos" onclick="cambiarTab('eventos')">Eventos (<?= $totalEventos ?>)</button>
            <button class="tab-btn" id="tab-activaciones" onclick="cambiarTab('activaciones')">Activaciones (<?= $totalAct ?>)</button>
        </div>

        <div class="filters-bar">
            <div class="filter-group">
                <label class="form-label">Buscar</label>
                <input type="text" id="f-buscar" class="form-input-styled" placeholder="Título, código, municipio..." oninput="renderTabla()">
            </div>
            <div class="filter-group">
                <label class="form-label">Rol que creó</label>
                <select id="f-rol" class="form-input-styled" onchange="renderTabla()">
                    <option value="">Todos</option>
                </select>
            </div>
            <div class="filter-group">
                <label class="form-label">Estado</label>
                <select id="f-estado" class="form-input-styled" onchange="renderTabla()">
                    <option value="">Todos</option>
                </select>
            </div>
            <div class="chk-group">
                <input type="checkbox" id="f-sin-region" onchange="renderTabla()">
                <label for="f-sin-region">Solo sin región</label>
            </div>
            <div class="chk-group">
                <input type="checkbox" id="f-sin-seccion" onchange="renderTabla()">
                <label for="f-sin-seccion">Solo sin sección</label>
            </div>
        </div>

        <div class="section-header">
            <div class="section-title" id="tabla-titulo">Eventos</div>
            <div id="count-label" style="font-size:13px;color:var(--text-muted);font-weight:600;"></div>
        </div>
        <div style="overflow-x:auto;">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>Código</th><th>Título</th><th>Estado</th><th>Municipio</th><th>Sección</th>
                    <th>Región</th><th>Zona</th><th>Creado por</th><th>Fecha</th><th>Región OK</th><th>Acción</th>
                </tr>
            </thead>
            <tbody id="tabla-body"></tbody>
        </table>
        </div>
    </div>

</div>

<!-- Modal: asignar región/zona/sección (browser 3 columnas, estilo Estructura) -->
<div id="modal-asignar" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9500;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;padding:24px;width:1040px;max-width:96vw;box-shadow:0 20px 60px rgba(0,0,0,.25);">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:4px;">
            <div style="font-size:17px;font-weight:800;">Asignar región / zona / sección</div>
            <button onclick="cerrarAsignar()" style="border:none;background:none;font-size:18px;cursor:pointer;color:var(--text-muted);">&times;</button>
        </div>
        <div id="asignar-subtitulo" style="font-size:12px;color:var(--text-muted);margin-bottom:14px;"></div>

        <div class="filter-group" style="margin-bottom:14px;max-width:320px;">
            <label class="form-label">Municipio</label>
            <input type="text" id="asig-municipio" class="form-input-styled">
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;">
            <div class="mini-col">
                <div class="mini-col-header">
                    <span><i class="fas fa-map"></i> Regiones</span>
                    <button class="mini-btn-nueva" onclick="abrirNuevaRegion()"><i class="fas fa-plus"></i> Nueva</button>
                </div>
                <div class="mini-col-body" id="mini-regiones-body"></div>
            </div>
            <div class="mini-col">
                <div class="mini-col-header">
                    <span><i class="fas fa-layer-group"></i> Zonas</span>
                    <button class="mini-btn-nueva" id="mini-btn-nueva-zona" onclick="abrirNuevaZona()" style="display:none;"><i class="fas fa-plus"></i> Nueva</button>
                </div>
                <div class="mini-col-body" id="mini-zonas-body"><div class="mini-hint"><i class="fas fa-arrow-left"></i> Selecciona una región.</div></div>
            </div>
            <div class="mini-col">
                <div class="mini-col-header">
                    <span><i class="fas fa-list-ol"></i> Secciones</span>
                </div>
                <div class="mini-col-body" id="mini-secciones-body"><div class="mini-hint"><i class="fas fa-arrow-left"></i> Selecciona una zona.</div></div>
            </div>
        </div>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:18px;padding-top:14px;border-top:1px solid var(--gray-200);">
            <div id="asignar-resumen" style="font-size:13px;color:var(--text-muted);">Sin sección elegida todavía.</div>
            <div style="display:flex;gap:10px;">
                <button onclick="cerrarAsignar()" style="padding:10px 18px;border-radius:10px;border:2px solid var(--gray-200);background:var(--gray-100);font-weight:700;font-size:13px;cursor:pointer;font-family:Arial,sans-serif;">Cancelar</button>
                <button onclick="guardarAsignar()" id="btn-guardar-asignar" style="padding:10px 18px;border-radius:10px;border:none;background:var(--primary);color:#fff;font-weight:700;font-size:13px;cursor:pointer;font-family:Arial,sans-serif;">Guardar</button>
            </div>
        </div>
    </div>
</div>

<!-- Sub-modal: nueva región (mismos campos que Estructura) -->
<div id="modal-nueva-region" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9600;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;padding:26px;width:460px;max-width:92vw;max-height:85vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.3);">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px;">
            <div id="modal-nueva-region-titulo" style="font-size:17px;font-weight:800;">Nueva región</div>
            <button onclick="cerrarNuevaRegion()" style="border:none;background:none;font-size:18px;cursor:pointer;color:var(--text-muted);">&times;</button>
        </div>
        <div style="display:flex;gap:10px;margin-bottom:12px;">
            <div class="filter-group">
                <label class="form-label">Nombre de la región *</label>
                <input type="text" id="nr-nombre" class="form-input-styled">
            </div>
            <div class="filter-group" style="max-width:110px;">
                <label class="form-label">Meta</label>
                <input type="number" id="nr-meta" class="form-input-styled" value="0">
            </div>
        </div>
        <div class="filter-group" style="margin-bottom:14px;">
            <label class="form-label">Descripción</label>
            <input type="text" id="nr-desc" class="form-input-styled">
        </div>
        <div class="filter-group" style="margin-bottom:8px;">
            <label class="form-label">Municipios asignados *</label>
            <input type="text" id="nr-muni-buscar" class="form-input-styled" placeholder="Buscar municipio..." oninput="renderMuniPicker()">
        </div>
        <div id="nr-muni-picker" style="display:flex;flex-wrap:wrap;gap:6px;max-height:160px;overflow-y:auto;border:1px solid var(--gray-200);border-radius:10px;padding:10px;margin-bottom:8px;"></div>
        <div id="nr-muni-contador" style="font-size:11px;color:var(--text-muted);margin-bottom:18px;">0 municipios seleccionados</div>
        <div style="display:flex;justify-content:flex-end;gap:10px;">
            <button onclick="cerrarNuevaRegion()" style="padding:10px 18px;border-radius:10px;border:2px solid var(--gray-200);background:var(--gray-100);font-weight:700;font-size:13px;cursor:pointer;font-family:Arial,sans-serif;">Cancelar</button>
            <button onclick="guardarNuevaRegion()" id="btn-guardar-nr" style="padding:10px 18px;border-radius:10px;border:none;background:var(--primary);color:#fff;font-weight:700;font-size:13px;cursor:pointer;font-family:Arial,sans-serif;">Crear región</button>
        </div>
    </div>
</div>

<!-- Sub-modal: nueva zona -->
<div id="modal-nueva-zona" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9600;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;padding:26px;width:460px;max-width:92vw;max-height:85vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.3);">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px;">
            <div id="modal-nueva-zona-titulo" style="font-size:17px;font-weight:800;">Nueva zona</div>
            <button onclick="cerrarNuevaZona()" style="border:none;background:none;font-size:18px;cursor:pointer;color:var(--text-muted);">&times;</button>
        </div>
        <div id="nz-region-label" style="font-size:12px;color:var(--text-muted);margin-bottom:14px;"></div>
        <div style="display:flex;gap:10px;margin-bottom:12px;">
            <div class="filter-group">
                <label class="form-label">Nombre de la zona *</label>
                <input type="text" id="nz-nombre" class="form-input-styled">
            </div>
            <div class="filter-group" style="max-width:110px;">
                <label class="form-label">Clave</label>
                <input type="text" id="nz-clave" class="form-input-styled">
            </div>
        </div>
        <div style="display:flex;gap:10px;margin-bottom:12px;">
            <div class="filter-group">
                <label class="form-label">Foco</label>
                <input type="text" id="nz-foco" class="form-input-styled">
            </div>
            <div class="filter-group" style="max-width:110px;">
                <label class="form-label">Meta</label>
                <input type="number" id="nz-meta" class="form-input-styled" value="0">
            </div>
        </div>
        <div class="filter-group" style="margin-bottom:14px;">
            <label class="form-label">Colonias</label>
            <input type="text" id="nz-colonias" class="form-input-styled">
        </div>
        <div class="filter-group" style="margin-bottom:6px;">
            <label class="form-label">Secciones *</label>
            <div style="display:flex;gap:8px;">
                <input type="text" id="nz-sec-add" class="form-input-styled" placeholder="Ej. 101, 102, 103" onkeydown="if(event.key==='Enter'){event.preventDefault();agregarSeccionInput();}">
                <button type="button" onclick="agregarSeccionInput()" style="padding:0 16px;border-radius:10px;border:none;background:var(--primary);color:#fff;font-weight:700;font-size:13px;cursor:pointer;font-family:Arial,sans-serif;white-space:nowrap;">Agregar</button>
            </div>
        </div>
        <div id="nz-sec-picker" style="min-height:44px;max-height:140px;overflow-y:auto;border:1px solid var(--gray-200);border-radius:10px;padding:8px;margin-bottom:6px;"></div>
        <div id="nz-sec-contador" style="font-size:11px;color:var(--text-muted);margin-bottom:18px;">0 sección(es)</div>
        <div style="display:flex;justify-content:flex-end;gap:10px;">
            <button onclick="cerrarNuevaZona()" style="padding:10px 18px;border-radius:10px;border:2px solid var(--gray-200);background:var(--gray-100);font-weight:700;font-size:13px;cursor:pointer;font-family:Arial,sans-serif;">Cancelar</button>
            <button onclick="guardarNuevaZona()" id="btn-guardar-nz" style="padding:10px 18px;border-radius:10px;border:none;background:var(--primary);color:#fff;font-weight:700;font-size:13px;cursor:pointer;font-family:Arial,sans-serif;">Crear zona</button>
        </div>
    </div>
</div>

<script>
const EVENTOS      = <?= json_encode($eventos, JSON_UNESCAPED_UNICODE) ?: '[]' ?>;
const ACTIVACIONES = <?= json_encode($activaciones, JSON_UNESCAPED_UNICODE) ?: '[]' ?>;

let tabActual = 'eventos';

function datosActuales() {
    return tabActual === 'eventos' ? EVENTOS : ACTIVACIONES;
}

function poblarFiltros() {
    const datos = datosActuales();
    const roles  = [...new Set(datos.map(d => d.creado_por_rol || '(sin rol)'))].sort();
    const estados = [...new Set(datos.map(d => d.estado || '(sin estado)'))].sort();

    const selRol = document.getElementById('f-rol');
    const rolVal = selRol.value;
    selRol.innerHTML = '<option value="">Todos</option>' + roles.map(r => `<option value="${r}">${r}</option>`).join('');
    selRol.value = roles.includes(rolVal) ? rolVal : '';

    const selEst = document.getElementById('f-estado');
    const estVal = selEst.value;
    selEst.innerHTML = '<option value="">Todos</option>' + estados.map(e => `<option value="${e}">${e}</option>`).join('');
    selEst.value = estados.includes(estVal) ? estVal : '';
}

function cambiarTab(tab) {
    tabActual = tab;
    document.getElementById('tab-eventos').classList.toggle('active', tab === 'eventos');
    document.getElementById('tab-activaciones').classList.toggle('active', tab === 'activaciones');
    document.getElementById('tabla-titulo').textContent = tab === 'eventos' ? 'Eventos' : 'Activaciones';
    poblarFiltros();
    renderTabla();
}

function renderTabla() {
    const q       = (document.getElementById('f-buscar').value || '').toLowerCase().trim();
    const rolF    = document.getElementById('f-rol').value;
    const estF    = document.getElementById('f-estado').value;
    const soloSin = document.getElementById('f-sin-region').checked;
    const soloSinSeccion = document.getElementById('f-sin-seccion').checked;

    let lista = datosActuales().filter(d => {
        if (rolF && (d.creado_por_rol || '(sin rol)') !== rolF) return false;
        if (estF && (d.estado || '(sin estado)') !== estF) return false;
        if (soloSin && !d.sin_region) return false;
        if (soloSinSeccion && d.seccion) return false;
        if (q) {
            const hay = `${d.titulo||''} ${d.codigo_referencia||''} ${d.municipio||''}`.toLowerCase();
            if (!hay.includes(q)) return false;
        }
        return true;
    });

    const tbody = document.getElementById('tabla-body');
    document.getElementById('count-label').textContent = `${lista.length} registro(s)`;

    if (!lista.length) {
        tbody.innerHTML = '<tr class="empty-row"><td colspan="11">Sin registros con estos filtros.</td></tr>';
        return;
    }

    tbody.innerHTML = lista.map(d => {
        const fecha = tabActual === 'eventos' ? (d.fecha || d.fecha_creacion || '-') : (d.fecha_actividad || d.fecha_creacion || '-');
        const badge = d.sin_region
            ? '<span class="badge badge-sin">Sin región</span>'
            : '<span class="badge badge-ok">OK</span>';
        const accion = d.sin_region
            ? `<button class="tab-btn" style="padding:6px 14px;font-size:11px;" onclick="abrirAsignar(${d.id})">Asignar</button>`
            : '-';
        return `<tr>
            <td>${d.codigo_referencia || '-'}</td>
            <td>${d.titulo || '-'}</td>
            <td>${d.estado || '-'}</td>
            <td>${d.municipio || '-'}</td>
            <td>${d.seccion || '-'}</td>
            <td>${d.region || '-'}</td>
            <td>${d.zona || '-'}</td>
            <td>${d.creado_por_rol || '(sin rol)'}</td>
            <td>${fecha}</td>
            <td>${badge}</td>
            <td>${accion}</td>
        </tr>`;
    }).join('');
}

poblarFiltros();
renderTabla();

// ── Modal asignar región/zona/sección (browser 3 columnas) ──────────
const ACTUALIZAR_URL = 'reporte_regiones_actualizar.php';
let asignarActual   = null;  // { id, tabla, estado }
let estructuraCache = {};    // estado => { regiones:[{id,nombre,meta,municipios,zonas:[{id,nombre,clave,foco,colonias,meta,secciones}]}], municipios:[...] }
let miniRegionId       = null;
let miniZonaId          = null;
let miniSeccionElegida  = '';
let editandoRegionId    = null;
let editandoZonaId      = null;

async function cargarEstructura(estado, forzar = false) {
    if (forzar) delete estructuraCache[estado];
    if (estructuraCache[estado]) return estructuraCache[estado];
    const res  = await fetch(`${ACTUALIZAR_URL}?action=estructura&estado=${encodeURIComponent(estado)}`);
    const data = await res.json();
    if (!data.ok) throw new Error(data.msg || 'No se pudo cargar la estructura');
    estructuraCache[estado] = data;
    return data;
}

function regionSeleccionada() {
    const est = estructuraCache[asignarActual?.estado];
    return est?.regiones.find(r => r.id === miniRegionId) || null;
}
function zonaSeleccionada() {
    const r = regionSeleccionada();
    return r?.zonas.find(z => z.id === miniZonaId) || null;
}

async function abrirAsignar(id) {
    const tabla = tabActual === 'eventos' ? 'eventos' : 'activaciones';
    const d = datosActuales().find(x => x.id === id);
    if (!d) return;

    asignarActual = { id, tabla, estado: d.estado || '' };
    miniRegionId = null; miniZonaId = null; miniSeccionElegida = '';

    document.getElementById('asignar-subtitulo').textContent = `${d.titulo || '(sin título)'} · ${d.estado || '-'}`;
    document.getElementById('asig-municipio').value = d.municipio || '';
    document.getElementById('mini-regiones-body').innerHTML = '<div class="mini-hint"><i class="fas fa-circle-notch fa-spin"></i>Cargando...</div>';
    document.getElementById('mini-zonas-body').innerHTML     = '<div class="mini-hint"><i class="fas fa-arrow-left"></i> Selecciona una región.</div>';
    document.getElementById('mini-secciones-body').innerHTML = '<div class="mini-hint"><i class="fas fa-arrow-left"></i> Selecciona una zona.</div>';
    document.getElementById('mini-btn-nueva-zona').style.display = 'none';
    actualizarResumen();

    document.getElementById('modal-asignar').style.display = 'flex';
    if (!d.estado) { renderMiniRegiones(); return; }

    try {
        await cargarEstructura(d.estado);
        renderMiniRegiones();
        // Preseleccionar si el evento ya traía región/zona/sección
        if (d.region) {
            const r = estructuraCache[d.estado].regiones.find(x => x.nombre === d.region);
            if (r) {
                seleccionarRegion(r.id, false);
                if (d.zona) {
                    const z = r.zonas.find(x => x.nombre === d.zona);
                    if (z) {
                        seleccionarZona(z.id, false);
                        if (d.seccion && z.secciones.includes(String(d.seccion))) {
                            miniSeccionElegida = String(d.seccion);
                        }
                    }
                }
            }
        }
        renderMiniZonas(); renderMiniSecciones(); actualizarResumen();
    } catch (e) {
        document.getElementById('mini-regiones-body').innerHTML = `<div class="mini-hint">Error: ${e.message}</div>`;
    }
}

function cerrarAsignar() {
    document.getElementById('modal-asignar').style.display = 'none';
    asignarActual = null;
}

function renderMiniRegiones() {
    const est = estructuraCache[asignarActual?.estado];
    const body = document.getElementById('mini-regiones-body');
    const regiones = est?.regiones || [];
    if (!regiones.length) {
        body.innerHTML = '<div class="mini-hint"><i class="fas fa-map-marked-alt"></i>No hay regiones.<br>Crea la primera.</div>';
        return;
    }
    body.innerHTML = regiones.map(r => `
        <div class="mini-card ${miniRegionId === r.id ? 'active' : ''}" onclick="seleccionarRegion(${r.id})">
            <button class="mc-edit" onclick="event.stopPropagation();abrirEditarRegion(${r.id})" title="Editar"><i class="fas fa-pen"></i></button>
            <div class="mc-name">${r.nombre}</div>
            <div class="mc-sub">${(r.zonas||[]).length} zona(s) · ${(r.municipios||[]).length} municipio(s)</div>
        </div>`).join('');
}

function seleccionarRegion(id, resetHijos = true) {
    miniRegionId = id;
    if (resetHijos) { miniZonaId = null; miniSeccionElegida = ''; }
    renderMiniRegiones();
    renderMiniZonas();
    renderMiniSecciones();
    actualizarResumen();
}

function renderMiniZonas() {
    const region = regionSeleccionada();
    const body = document.getElementById('mini-zonas-body');
    const btnNueva = document.getElementById('mini-btn-nueva-zona');
    if (!region) {
        body.innerHTML = '<div class="mini-hint"><i class="fas fa-arrow-left"></i> Selecciona una región.</div>';
        btnNueva.style.display = 'none';
        return;
    }
    btnNueva.style.display = 'inline-flex';
    const zonas = region.zonas || [];
    if (!zonas.length) {
        body.innerHTML = '<div class="mini-hint"><i class="fas fa-layer-group"></i>Sin zonas en esta región.</div>';
        return;
    }
    body.innerHTML = zonas.map(z => `
        <div class="mini-card ${miniZonaId === z.id ? 'active' : ''}" onclick="seleccionarZona(${z.id})">
            <button class="mc-edit" onclick="event.stopPropagation();abrirEditarZona(${z.id})" title="Editar"><i class="fas fa-pen"></i></button>
            <div class="mc-name">${z.nombre}${z.clave ? ' <span style="font-weight:400;color:var(--text-muted);">(' + z.clave + ')</span>' : ''}</div>
            <div class="mc-sub">${(z.secciones||[]).length} sección(es)</div>
        </div>`).join('');
}

function seleccionarZona(id, resetHijo = true) {
    miniZonaId = id;
    if (resetHijo) miniSeccionElegida = '';
    renderMiniZonas();
    renderMiniSecciones();
    actualizarResumen();
}

function renderMiniSecciones() {
    const zona = zonaSeleccionada();
    const body = document.getElementById('mini-secciones-body');
    if (!zona) {
        body.innerHTML = '<div class="mini-hint"><i class="fas fa-arrow-left"></i> Selecciona una zona.</div>';
        return;
    }
    const secs = zona.secciones || [];
    if (!secs.length) {
        body.innerHTML = `<div class="mini-hint"><i class="fas fa-list-ol"></i>Esta zona no tiene secciones todavía.<br>
            <button class="mini-btn-nueva" style="margin-top:8px;" onclick="abrirEditarZona(${zona.id})"><i class="fas fa-plus"></i> Agregar secciones</button></div>`;
        return;
    }
    body.innerHTML = secs.map(s => `
        <div class="mini-sec-row ${miniSeccionElegida === String(s) ? 'active' : ''}" onclick="elegirSeccion('${s}')">
            <span>Sección ${s}</span><span class="msr-check"><i class="fas fa-check-circle"></i></span>
        </div>`).join('');
}

function elegirSeccion(s) {
    miniSeccionElegida = String(s);
    renderMiniSecciones();
    actualizarResumen();
}

function actualizarResumen() {
    const region = regionSeleccionada();
    const zona   = zonaSeleccionada();
    const el = document.getElementById('asignar-resumen');
    if (!region) { el.textContent = 'Sin región elegida todavía.'; return; }
    if (!zona)   { el.innerHTML = `Región: <strong>${region.nombre}</strong> · sin zona elegida.`; return; }
    if (!miniSeccionElegida) { el.innerHTML = `Región: <strong>${region.nombre}</strong> · Zona: <strong>${zona.nombre}</strong> · sin sección elegida.`; return; }
    el.innerHTML = `Región: <strong>${region.nombre}</strong> · Zona: <strong>${zona.nombre}</strong> · Sección: <strong>${miniSeccionElegida}</strong>`;
}

async function guardarAsignar() {
    if (!asignarActual) return;
    const municipio = document.getElementById('asig-municipio').value.trim();
    const region = regionSeleccionada();
    const zona   = zonaSeleccionada();

    if (!region) { alert('Selecciona una región.'); return; }
    if (!miniSeccionElegida) { alert('Elige la sección de este evento/activación en la columna de Secciones.'); return; }

    const btn = document.getElementById('btn-guardar-asignar');
    btn.disabled = true; btn.textContent = 'Guardando...';

    try {
        const fd = new FormData();
        fd.append('action', 'guardar');
        fd.append('tabla', asignarActual.tabla);
        fd.append('id', asignarActual.id);
        fd.append('seccion', miniSeccionElegida);
        fd.append('municipio', municipio);
        fd.append('region', region.nombre);
        fd.append('zona', zona ? zona.nombre : '');

        const res  = await fetch(ACTUALIZAR_URL, { method: 'POST', body: fd });
        const data = await res.json();
        if (!data.ok) throw new Error(data.msg || 'Error al guardar');

        const arr = asignarActual.tabla === 'eventos' ? EVENTOS : ACTIVACIONES;
        const row = arr.find(x => x.id === asignarActual.id);
        if (row) {
            row.seccion = miniSeccionElegida; row.municipio = municipio;
            row.region = region.nombre; row.zona = zona ? zona.nombre : '';
            row.sin_region = 0;
        }
        cerrarAsignar();
        poblarFiltros();
        renderTabla();
    } catch (e) {
        alert('Error: ' + e.message);
    } finally {
        btn.disabled = false; btn.textContent = 'Guardar';
    }
}

// ── Sub-modal: Nueva / Editar región ──────────────────────────────────
let muniSelPicker = new Set();

function abrirNuevaRegion() {
    if (!asignarActual) return;
    editandoRegionId = null;
    document.getElementById('modal-nueva-region-titulo').textContent = 'Nueva región';
    document.getElementById('nr-nombre').value = '';
    document.getElementById('nr-meta').value   = 0;
    document.getElementById('nr-desc').value   = '';
    document.getElementById('nr-muni-buscar').value = '';
    muniSelPicker = new Set();
    if (document.getElementById('asig-municipio').value.trim()) {
        muniSelPicker.add(document.getElementById('asig-municipio').value.trim());
    }
    renderMuniPicker();
    document.getElementById('modal-nueva-region').style.display = 'flex';
}

function abrirEditarRegion(id) {
    const est = estructuraCache[asignarActual.estado];
    const r = est.regiones.find(x => x.id === id);
    if (!r) return;
    editandoRegionId = id;
    document.getElementById('modal-nueva-region-titulo').textContent = 'Editar región';
    document.getElementById('nr-nombre').value = r.nombre || '';
    document.getElementById('nr-meta').value   = r.meta || 0;
    document.getElementById('nr-desc').value   = r.desc || '';
    document.getElementById('nr-muni-buscar').value = '';
    muniSelPicker = new Set(r.municipios || []);
    renderMuniPicker();
    document.getElementById('modal-nueva-region').style.display = 'flex';
}

function cerrarNuevaRegion() {
    document.getElementById('modal-nueva-region').style.display = 'none';
}

function renderMuniPicker() {
    if (!asignarActual) return;
    const estructura = estructuraCache[asignarActual.estado];
    const q = document.getElementById('nr-muni-buscar').value.toLowerCase();
    const munis = (estructura?.municipios || []).filter(m => m.toLowerCase().includes(q));
    const picker = document.getElementById('nr-muni-picker');
    picker.innerHTML = munis.map(m => {
        const sel = muniSelPicker.has(m);
        return `<span onclick="toggleMuni('${m.replace(/'/g,"\\'")}')"
                       style="cursor:pointer;padding:5px 12px;border-radius:20px;font-size:12px;font-weight:600;
                              border:1px solid ${sel ? 'var(--primary)' : 'var(--gray-200)'};
                              background:${sel ? 'var(--primary)' : 'var(--gray-100)'};
                              color:${sel ? '#fff' : 'var(--dark)'};">${m}</span>`;
    }).join('') || '<span style="font-size:12px;color:var(--text-muted);">Sin municipios para ese estado en el catálogo.</span>';
    document.getElementById('nr-muni-contador').textContent = `${muniSelPicker.size} municipio(s) seleccionado(s)`;
}

function toggleMuni(m) {
    muniSelPicker.has(m) ? muniSelPicker.delete(m) : muniSelPicker.add(m);
    renderMuniPicker();
}

async function guardarNuevaRegion() {
    const nombre = document.getElementById('nr-nombre').value.trim();
    if (!nombre) { alert('El nombre de la región es obligatorio.'); return; }
    if (!muniSelPicker.size) { alert('Selecciona al menos un municipio.'); return; }

    const btn = document.getElementById('btn-guardar-nr');
    btn.disabled = true; btn.textContent = 'Guardando...';
    try {
        const fd = new FormData();
        fd.append('action', editandoRegionId ? 'editar_region' : 'crear_region');
        if (editandoRegionId) fd.append('id', editandoRegionId);
        fd.append('estado', asignarActual.estado);
        fd.append('nombre', nombre);
        fd.append('desc', document.getElementById('nr-desc').value.trim());
        fd.append('meta', document.getElementById('nr-meta').value || 0);
        fd.append('municipios', JSON.stringify([...muniSelPicker]));

        const res  = await fetch(ACTUALIZAR_URL, { method: 'POST', body: fd });
        const data = await res.json();
        if (!data.ok) throw new Error(data.msg || 'Error al guardar región');

        await cargarEstructura(asignarActual.estado, true);
        const nuevaId = editandoRegionId || data.id;
        renderMiniRegiones();
        seleccionarRegion(nuevaId, !editandoRegionId);
        document.getElementById('modal-nueva-region').style.display = 'none';
    } catch (e) {
        alert('Error: ' + e.message);
    } finally {
        btn.disabled = false; btn.textContent = editandoRegionId ? 'Guardar cambios' : 'Crear región';
    }
}

// ── Sub-modal: Nueva / Editar zona ────────────────────────────────────
let secSelPicker = new Set();

function abrirNuevaZona() {
    if (!miniRegionId) { alert('Primero selecciona (o crea) la región a la que pertenece esta zona.'); return; }
    editandoZonaId = null;
    document.getElementById('modal-nueva-zona-titulo').textContent = 'Nueva zona';
    document.getElementById('nz-region-label').textContent = `Región: ${regionSeleccionada()?.nombre || ''}`;
    document.getElementById('nz-nombre').value    = '';
    document.getElementById('nz-clave').value     = '';
    document.getElementById('nz-foco').value      = '';
    document.getElementById('nz-meta').value      = 0;
    document.getElementById('nz-colonias').value  = '';
    secSelPicker = new Set();
    renderSecPicker();
    document.getElementById('modal-nueva-zona').style.display = 'flex';
}

function abrirEditarZona(id) {
    const region = regionSeleccionada() || estructuraCache[asignarActual.estado].regiones.find(r => r.zonas.some(z => z.id === id));
    const z = region?.zonas.find(x => x.id === id);
    if (!z || !region) return;
    if (miniRegionId !== region.id) seleccionarRegion(region.id, true);
    editandoZonaId = id;
    document.getElementById('modal-nueva-zona-titulo').textContent = 'Editar zona';
    document.getElementById('nz-region-label').textContent = `Región: ${region.nombre}`;
    document.getElementById('nz-nombre').value    = z.nombre || '';
    document.getElementById('nz-clave').value     = z.clave || '';
    document.getElementById('nz-foco').value      = z.foco || '';
    document.getElementById('nz-meta').value      = z.meta || 0;
    document.getElementById('nz-colonias').value  = z.colonias || '';
    secSelPicker = new Set(z.secciones || []);
    renderSecPicker();
    document.getElementById('modal-nueva-zona').style.display = 'flex';
}

function cerrarNuevaZona() {
    document.getElementById('modal-nueva-zona').style.display = 'none';
}

function renderSecPicker() {
    const picker = document.getElementById('nz-sec-picker');
    if (!picker) return;
    picker.innerHTML = [...secSelPicker].sort((a,b)=>parseInt(a)-parseInt(b)).map(s => `
        <span style="display:inline-flex;align-items:center;gap:6px;padding:5px 10px;border-radius:20px;font-size:12px;font-weight:600;background:var(--primary);color:#fff;margin:3px;">
            ${s} <i class="fas fa-times" style="cursor:pointer;" onclick="quitarSeccion('${s}')"></i>
        </span>`).join('') || '<span style="font-size:12px;color:var(--text-muted);">Sin secciones agregadas.</span>';
    document.getElementById('nz-sec-contador').textContent = `${secSelPicker.size} sección(es)`;
}

function agregarSeccionInput() {
    const input = document.getElementById('nz-sec-add');
    const val = input.value.trim();
    if (!val) return;
    val.split(',').map(s => s.trim()).filter(Boolean).forEach(s => secSelPicker.add(s));
    input.value = '';
    renderSecPicker();
}

function quitarSeccion(s) {
    secSelPicker.delete(s);
    renderSecPicker();
}

async function guardarNuevaZona() {
    const nombre = document.getElementById('nz-nombre').value.trim();
    if (!nombre) { alert('El nombre de la zona es obligatorio.'); return; }
    if (!secSelPicker.size) { alert('Agrega al menos una sección.'); return; }

    const btn = document.getElementById('btn-guardar-nz');
    btn.disabled = true; btn.textContent = 'Guardando...';
    try {
        const fd = new FormData();
        fd.append('action', editandoZonaId ? 'editar_zona' : 'crear_zona');
        if (editandoZonaId) fd.append('id', editandoZonaId);
        fd.append('estado', asignarActual.estado);
        fd.append('region_id', miniRegionId);
        fd.append('nombre', nombre);
        fd.append('clave', document.getElementById('nz-clave').value.trim());
        fd.append('foco', document.getElementById('nz-foco').value.trim());
        fd.append('colonias', document.getElementById('nz-colonias').value.trim());
        fd.append('meta', document.getElementById('nz-meta').value || 0);
        fd.append('secciones', JSON.stringify([...secSelPicker]));

        const res  = await fetch(ACTUALIZAR_URL, { method: 'POST', body: fd });
        const data = await res.json();
        if (!data.ok) throw new Error(data.msg || 'Error al guardar zona');

        await cargarEstructura(asignarActual.estado, true);
        renderMiniRegiones();
        seleccionarZona(editandoZonaId || data.id, false);
        renderMiniZonas();
        document.getElementById('modal-nueva-zona').style.display = 'none';
    } catch (e) {
        alert('Error: ' + e.message);
    } finally {
        btn.disabled = false; btn.textContent = editandoZonaId ? 'Guardar cambios' : 'Crear zona';
    }
}
</script>
</body>
</html>