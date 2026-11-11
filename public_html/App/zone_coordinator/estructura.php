<?php
// ── Seguridad e Inicio de Sesión ──────────────────────────────────
if (file_exists(__DIR__ . '/../../includes/bootstrap.php')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
} else { 
    session_start(); 
}

$rol = $_SESSION['user_role'] ?? '';
// Permitimos acceso a los perfiles operativos
$permitidos = ['coordinador_zona', 'coordinador_regional', 'coordinador_estatal', 'admin', 'superadmin'];
if (!in_array($rol, $permitidos)) { header('Location: /public/index.php'); exit(); }

// Variables de sesión operativas
$user_id     = $_SESSION['user_id']         ?? 0;
$user_zona   = $_SESSION['user_zona_nombre'] ?? 'Zona Asignada'; 
$user_estado = $_SESSION['user_estado']      ?? '';

// Conexión a BD
try {
    $dbConfigPath = __DIR__ . '/../../includes/database.php';
    $dbConfig = file_exists($dbConfigPath) ? require $dbConfigPath : [
        'host'    => 'localhost',
        'database'=> 'u881824383_pverde',
        'username'=> 'u881824383_proyverde',
        'password'=> 'PProyectoverde8#',
        'charset' => 'utf8mb4',
        'options' => [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC],
    ];
    if (!isset($pdo)) {
        $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
        $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options'] ?? []);
    }
} catch (Exception $e) {
    error_log("Error de conexión: " . $e->getMessage());
}

/* ══════════════════════════════════════════════════════════════════
   API JSON INTERNA (Para carga dinámica sin recargar la página)
══════════════════════════════════════════════════════════════════ */
if (isset($_GET['api'])) {
    header('Content-Type: application/json; charset=utf-8');
    $accion = $_GET['api'] ?? '';

    try {
        if ($accion === 'cargar') {
            // 1. Obtener info de la zona por coordinador_id (no por nombre)
            $zonaInfo = null;
            $seccionesIds = [];
            if ($user_id > 0) {
                $stmtZ = $pdo->prepare("
                    SELECT z.*
                    FROM coordinador_zona cz
                    INNER JOIN zonas_estatales z ON cz.zona = z.id
                    WHERE cz.id = ? LIMIT 1
                ");
                $stmtZ->execute([$user_id]);
                $zonaInfo = $stmtZ->fetch(PDO::FETCH_ASSOC);
            }

            // 2. Extraer secciones del JSON de zonas_estatales
            if ($zonaInfo && !empty($zonaInfo['secciones'])) {
                $arr = json_decode($zonaInfo['secciones'], true);
                if (is_array($arr)) {
                    usort($arr, fn($a, $b) => (int)$a - (int)$b);
                    $seccionesIds = $arr;
                }
            }

            // 3. Obtener datos de secciones_electorales filtrando por estado + número de sección
            $secciones = [];
            if (!empty($seccionesIds) && !empty($user_estado)) {
                $placeholders = implode(',', array_fill(0, count($seccionesIds), '?'));
                $stmtS = $pdo->prepare("
                    SELECT municipio, nombre_seccion, meta_contactos, contactos_conseguidos,
                           COALESCE(lista_nominal, padron_electoral, 0) AS lista_nominal,
                           padron_electoral
                    FROM secciones_electorales
                    WHERE estado = ?
                      AND nombre_seccion IN ($placeholders)
                      AND activo = 1
                    ORDER BY municipio ASC, CAST(nombre_seccion AS UNSIGNED) ASC
                ");
                $stmtS->execute(array_merge([$user_estado], $seccionesIds));
                $secciones = $stmtS->fetchAll(PDO::FETCH_ASSOC);
            }

            // 4. Agrupar la información por municipio para la interfaz
            // Para secciones sin municipio en secciones_electorales, incluirlas igual
            $seccionesConMunicipio = array_column($secciones, null, 'nombre_seccion');
            $municipiosMap = [];
            $totalSecciones = 0;
            $metaZona = (int)($zonaInfo['meta'] ?? 0);
            $contactosZona = 0;

            // Iterar todas las secciones de la zona
            foreach ($seccionesIds as $numSec) {
                $sec = $seccionesConMunicipio[(string)$numSec] ?? [
                    'nombre_seccion'       => (string)$numSec,
                    'municipio'            => 'Sin Municipio',
                    'meta_contactos'       => 0,
                    'contactos_conseguidos'=> 0,
                    'lista_nominal'        => 0,
                    'padron_electoral'     => 0,
                ];
                $mun = $sec['municipio'] ?: 'Sin Municipio';
                if (!isset($municipiosMap[$mun])) {
                    $municipiosMap[$mun] = ['nombre'=>$mun, 'secciones'=>[], 'meta'=>0, 'contactos'=>0, 'padron'=>0, 'ln'=>0];
                }
                $municipiosMap[$mun]['secciones'][] = $sec;
                $municipiosMap[$mun]['meta']      += (int)$sec['meta_contactos'];
                $municipiosMap[$mun]['contactos'] += (int)$sec['contactos_conseguidos'];
                $municipiosMap[$mun]['padron']    += (int)$sec['padron_electoral'];
                $municipiosMap[$mun]['ln']        += (int)$sec['lista_nominal'];
                $contactosZona += (int)$sec['contactos_conseguidos'];
                $totalSecciones++;
            }

            ksort($municipiosMap);

            $zonaData = [
                'nombre'            => $zonaInfo['nombre'] ?? $user_zona,
                'info'              => $zonaInfo,
                'total_secciones'   => $totalSecciones,
                'meta_total'        => $metaZona,
                'contactos_totales' => $contactosZona,
                'municipios'        => array_values($municipiosMap)
            ];

            echo json_encode(['ok' => true, 'zona' => $zonaData]);
            exit();
        }

        echo json_encode(['ok' => false, 'msg' => 'Acción desconocida']);

    } catch (Exception $e) {
        error_log('estructura API error: ' . $e->getMessage());
        echo json_encode(['ok' => false, 'msg' => 'Error del servidor: ' . $e->getMessage()]);
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Estructura - <?= htmlspecialchars($user_zona) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    /* ── VARIABLES & BASE ── */
    :root {
        --primary:#2d7a3e; --primary-dark:#1f5a2d; --dark:#1a1a1a; --light:#f0f7f0;
        --white:#ffffff; --gray-100:#f8f9fa; --gray-200:#e9ecef; --text-muted:#6c757d;
        --shadow-sm:0 10px 30px rgba(0,0,0,0.05);
    }
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family:'Arial',sans-serif; background:var(--light); color:var(--dark); overflow-x:hidden; }

    /* ── LAYOUT ── */
    .container { max-width:100%; margin:0; background:var(--white); min-height:100vh; position:relative; padding-left:330px; padding-right:50px; padding-bottom:60px; transition:padding-left 0.3s ease; }
    .design-text { position:fixed; left:290px; top:50%; writing-mode:vertical-rl; font-size:13px; font-weight:500; color:var(--primary); opacity:0.3; letter-spacing:2px; z-index:0; pointer-events:none; }

    /* ── HEADER ── */
    .page-header { padding:50px 0 30px 0; position:relative; z-index:2; display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:20px; border-bottom:2px solid var(--gray-200); margin-bottom:30px; }
    .ai-badge { color:var(--primary); font-size:13px; font-weight:800; letter-spacing:4px; margin-bottom:15px; display:inline-block; text-transform:uppercase; background:rgba(45,122,62,0.1); padding:5px 15px; border-radius:20px; }
    .main-title { font-size:54px; font-weight:900; line-height:1; margin-bottom:15px; color:var(--dark); }
    .description { color:#666; font-size:16px; line-height:1.6; max-width:600px; }

    /* ── PANEL PRINCIPAL: 3 COLUMNAS INTERACTIVAS ── */
    .workspace { 
        display:grid; 
        grid-template-columns: 280px 320px 1fr; 
        gap:0; 
        border:1px solid var(--gray-200); 
        border-radius:20px; 
        overflow:hidden; 
        box-shadow:var(--shadow-sm); 
        min-height:600px; 
    }

    .col-panel { background:var(--white); border-right:1px solid var(--gray-200); display:flex; flex-direction:column; }
    .col-panel:last-child { border-right:none; }
    
    .col-header { padding:18px 20px; border-bottom:1px solid var(--gray-200); display:flex; align-items:center; justify-content:space-between; background:var(--gray-100); }
    .col-header-title { font-size:14px; font-weight:800; color:var(--dark); display:flex; align-items:center; gap:8px; }
    .col-body { flex:1; overflow-y:auto; padding:12px; }
    .col-body::-webkit-scrollbar { width:4px; }
    .col-body::-webkit-scrollbar-thumb { background:#ddd; border-radius:4px; }

    /* ── TARJETAS (CARDS) ── */
    .zona-card, .muni-card { 
        background:var(--white); border:1px solid var(--gray-200); border-radius:14px; 
        padding:16px 18px; margin-bottom:10px; cursor:pointer; transition:all 0.2s; position:relative; 
    }
    .muni-card:hover { border-color:var(--primary); box-shadow:0 4px 14px rgba(45,122,62,0.1); }
    .muni-card.active { border-color:var(--primary); background:rgba(45,122,62,0.04); box-shadow:0 4px 14px rgba(45,122,62,0.12); }
    
    /* Resaltar la tarjeta principal de la zona */
    .zona-card { border-color:var(--primary); background:rgba(45,122,62,0.02); cursor:default; }

    .card-name { font-size:16px; font-weight:700; color:var(--dark); margin-bottom:6px; }
    .zona-card .card-name { font-size: 20px; font-weight: 900; }
    
    .card-meta { display:flex; justify-content:space-between; align-items:center; font-size:13px; }
    .card-datos { color:var(--text-muted); }
    .card-pct { color:var(--primary); font-weight:800; font-size:14px; }
    .card-bar-track { height:4px; background:var(--gray-200); border-radius:99px; margin-top:8px; overflow:hidden; }
    .card-bar-fill { height:100%; background:var(--primary); border-radius:99px; transition:width 0.4s; }
    .card-secciones-count { font-size:11px; color:var(--text-muted); margin-top:6px; }
    
    .zi-bullet { font-size:12px; color:var(--dark); margin-top:8px; display:flex; gap:6px; align-items:flex-start; }
    .zi-bullet i { color:var(--primary); margin-top:2px; }

    /* ── SECCIONES ── */
    .col-search { padding:12px 20px; border-bottom:1px solid var(--gray-200); background: var(--white); }
    .col-search input { width:100%; padding:10px 15px; border-radius:10px; border:1px solid var(--gray-200); background:var(--gray-100); font-size:13px; outline:none; font-family:Arial,sans-serif; transition: 0.2s;}
    .col-search input:focus { border-color:var(--primary); background:var(--white); }

    .list-scroll { flex:1; overflow-y:auto; }
    .list-scroll::-webkit-scrollbar { width:6px; }
    .list-scroll::-webkit-scrollbar-thumb { background:#ccc; border-radius:6px; }

    .seccion-item { padding:10px 20px; font-size:13px; border-bottom:1px solid var(--gray-200); display:flex; align-items:center; justify-content:space-between; transition:background 0.15s; }
    .seccion-item:hover { background:var(--gray-100); }
    .seccion-num { font-weight:700; font-size:13px; color:var(--dark); }
    .seccion-ln { font-size:11px; font-weight:700; color:#92400e; background:#fef3c7; border:1px solid #fde68a; border-radius:5px; padding:2px 8px; margin-left:auto; margin-right:10px; }
    .seccion-assigned { color:var(--primary); font-size:11px; font-weight:700; display:flex; align-items:center; gap:4px; }

    /* Zona info box */
    .zona-info-box { margin:12px 16px; padding:14px 16px; background:rgba(45,122,62,0.05); border:1px solid rgba(45,122,62,0.2); border-radius:12px; font-size:13px; }
    .zona-info-box .zi-title { font-weight:800; font-size:14px; color:var(--dark); margin-bottom:4px; }
    .zona-info-box .zi-foco  { font-style:italic; color:var(--text-muted); margin-bottom:6px; font-size:12px; }
    .zona-info-box .zi-bullet { font-size:12px; color:var(--dark); margin-bottom:3px; display:flex; gap:6px; }
    .zona-info-box .zi-bullet strong { color:var(--primary); }

    .hint-text { text-align:center; padding:60px 20px; color:var(--text-muted); font-size:14px; }
    .hint-text i { display:block; font-size:32px; opacity:0.2; margin-bottom:12px; }

    .empty-col { text-align:center; padding:40px 20px; color:var(--text-muted); font-size:13px; }
    .empty-col i { display:block; font-size:28px; opacity:0.25; margin-bottom:10px; }

    /* Animaciones */
    .animate-up { animation:fadeInUp 0.6s ease forwards; opacity:0; }
    @keyframes fadeInUp { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }
    .animate-delay-1 { animation-delay:0.1s; }

    @media(max-width:1100px){ 
        .container{padding-left:20px;padding-right:20px;} 
        .workspace{grid-template-columns:1fr;} 
        .design-text{display:none;} 
        .col-panel{border-right:none; border-bottom:1px solid var(--gray-200);} 
    }
    </style>
</head>
<body>
<?php include __DIR__ . '/../../includes/components/sidebar_cz.php'; ?>

<div class="design-text">MAPA ESTRUCTURAL</div>

<div class="container">
    <div class="page-header animate-up">
        <div>
            <div class="ai-badge">VISTA OPERATIVA</div>
            <h1 class="main-title">Estructura<br>Asignada.</h1>
            <p class="description">Consulta la distribución geográfica oficial (Secciones y Municipios) que conforman tu zona de responsabilidad territorial en <strong><?= htmlspecialchars($user_estado) ?></strong>.</p>
        </div>
    </div>

    <div class="workspace animate-up animate-delay-1">

        <div class="col-panel">
            <div class="col-header">
                <div class="col-header-title"><i class="fas fa-map-marker-alt" style="color:var(--primary)"></i> Resumen de Zona</div>
                <button id="btn-ver-mapa-cz" onclick="verEnMapa()" title="Ver zona en el mapa" style="display:none;align-items:center;gap:6px;background:#1565c0;color:white;border:none;padding:6px 14px;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;font-family:Arial,sans-serif;">
                    <i class="fas fa-map-marked-alt"></i> Mapa
                </button>
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
            <div id="col-munis-body" style="flex:1;display:flex;flex-direction:column; overflow-y:auto; padding: 12px;">
                <div class="hint-text"><i class="fas fa-circle-notch fa-spin"></i>Cargando...</div>
            </div>
        </div>

        <div class="col-panel" style="flex: 1;">
            <div class="col-header">
                <div class="col-header-title">
                    <i class="fas fa-list-ol" style="color:var(--primary)"></i> Secciones
                    <span id="col-sec-subtitle" style="font-weight:400;color:var(--text-muted);font-size:12px;margin-left:4px;"></span>
                </div>
            </div>
            <div class="col-search">
                <input type="text" id="searchInput" placeholder="Filtrar sección por número..." onkeyup="filtrarSecciones()">
            </div>
            <div id="col-secciones-body" style="flex:1;display:flex;flex-direction:column;">
                <div class="hint-text"><i class="fas fa-arrow-left"></i>Selecciona un municipio para ver sus secciones.</div>
            </div>
        </div>

    </div>
</div>

<script>
/* ══════════════════════════════════════════════
   ESTADO EN MEMORIA
══════════════════════════════════════════════ */
let appData         = { zona: null };
let municipioActivo = null;

/* ══════════════════════════════════════════════
   CARGA DESDE BD (API Interna)
══════════════════════════════════════════════ */
async function cargarDatos() {
    try {
        const res = await fetch(`${window.location.pathname}?api=cargar`);
        const data = await res.json();
        if (!data.ok) throw new Error(data.msg || 'Error en la respuesta');
        
        appData.zona = data.zona;
        renderZona();
        renderMunicipios();

        // Mostrar botón Mapa una vez cargada la zona
        const btnMapa = document.getElementById('btn-ver-mapa-cz');
        if (btnMapa) btnMapa.style.display = 'flex';
        
        // Si solo hay un municipio, lo seleccionamos automáticamente
        if (appData.zona.municipios.length === 1) {
            selectMunicipio(appData.zona.municipios[0].nombre);
        }
    } catch(e) {
        document.getElementById('col-zona-body').innerHTML =
            `<div class="empty-col"><i class="fas fa-exclamation-triangle" style="color:#d32f2f"></i>Error al cargar datos.<br><small>${e.message}</small></div>`;
    }
}

/* ══════════════════════════════════════════════
   RENDER: COLUMNA 1 (Mi Zona)
══════════════════════════════════════════════ */
function renderZona() {
    const body = document.getElementById('col-zona-body');
    if (!appData.zona) return;

    const z = appData.zona;
    const info = z.info || {};
    const nMunis = z.municipios.length;
    
    // Cálculo de progreso general
    const pct = z.meta_total > 0 ? Math.min(100, Math.round((z.contactos_totales / z.meta_total) * 100)) : 0;

    body.innerHTML = `
        <div class="zona-card">
            <div class="card-name">${z.nombre} ${info.clave ? `<span style="font-size:12px; color:#888;">(${info.clave})</span>` : ''}</div>
            
            <div class="card-meta">
                <span class="card-datos">Avance de Contactos</span>
                <span class="card-pct">${pct}%</span>
            </div>
            <div class="card-bar-track"><div class="card-bar-fill" style="width:${pct}%"></div></div>
            <div class="card-secciones-count" style="margin-bottom: 15px;">
                ${z.contactos_totales.toLocaleString('es-MX')} de ${(z.meta_total).toLocaleString('es-MX')} meta total
            </div>

            <div style="border-top: 1px solid rgba(45,122,62,0.2); padding-top: 12px;">
                <div class="zi-bullet">
                    <i class="fas fa-layer-group"></i> 
                    <div><strong>${z.total_secciones}</strong> Secciones electorales</div>
                </div>
                <div class="zi-bullet">
                    <i class="fas fa-city"></i> 
                    <div><strong>${nMunis}</strong> Municipio${nMunis !== 1 ? 's' : ''}</div>
                </div>
                ${(() => {
                    const totalLN = z.municipios.reduce((s,m) => s + (m.ln||0), 0);
                    return totalLN > 0
                        ? `<div class="zi-bullet" style="margin-top:10px;"><i class="fas fa-users"></i><span style="font-weight:800;color:#92400e;background:#fef3c7;border:1px solid #fde68a;border-radius:6px;padding:3px 10px;font-size:13px;">${totalLN.toLocaleString('es-MX')} de lista nominal</span></div>`
                        : '';
                })()}
                ${info.foco ? `<div class="zi-bullet"><i class="fas fa-bullseye"></i> <div><strong>Foco:</strong> ${info.foco}</div></div>` : ''}
            </div>
        </div>
        <div style="text-align: center; margin-top: 20px; font-size: 11px; color: var(--text-muted);">
            Información gestionada por la coordinación estatal.
        </div>
    `;
}

/* ══════════════════════════════════════════════
   RENDER: COLUMNA 2 (Municipios)
══════════════════════════════════════════════ */
function renderMunicipios() {
    const body = document.getElementById('col-munis-body');
    const subtitle = document.getElementById('col-munis-subtitle');
    
    if (!appData.zona || appData.zona.municipios.length === 0) {
        body.innerHTML = '<div class="hint-text"><i class="fas fa-city"></i>No hay municipios registrados para esta zona.</div>';
        return;
    }

    subtitle.textContent = `(${appData.zona.municipios.length})`;

    body.innerHTML = appData.zona.municipios.map(m => {
        const pct = m.meta > 0 ? Math.min(100, Math.round((m.contactos / m.meta) * 100)) : 0;
        const nSec = m.secciones.length;
        const ln   = m.ln || 0;
        
        return `
        <div class="muni-card ${municipioActivo === m.nombre ? 'active' : ''}" onclick="selectMunicipio('${m.nombre.replace(/'/g, "\\'")}')">
            <div class="card-name">${m.nombre}</div>
            <div class="card-meta">
                <span class="card-datos">${nSec} Sección${nSec !== 1 ? 'es' : ''}</span>
                <span class="card-pct">${pct}%</span>
            </div>
            <div class="card-bar-track"><div class="card-bar-fill" style="width:${pct}%"></div></div>
            <div class="card-secciones-count">${m.contactos.toLocaleString('es-MX')} / ${m.meta.toLocaleString('es-MX')} meta</div>
            ${ln > 0 ? `<div style="margin-top:6px;"><span style="font-size:11px;font-weight:700;color:#92400e;background:#fef3c7;border:1px solid #fde68a;border-radius:5px;padding:2px 8px;">${ln.toLocaleString('es-MX')} LN</span></div>` : ''}
        </div>`;
    }).join('');
}

function selectMunicipio(nombre) {
    municipioActivo = nombre;
    renderMunicipios(); 
    renderSecciones();
    
    // Limpiar buscador al cambiar de municipio
    const searchInput = document.getElementById('searchInput');
    if(searchInput) {
        searchInput.value = '';
        filtrarSecciones();
    }
}

/* ══════════════════════════════════════════════
   RENDER: COLUMNA 3 (Secciones)
══════════════════════════════════════════════ */
function renderSecciones() {
    const body = document.getElementById('col-secciones-body');
    const subtitle = document.getElementById('col-sec-subtitle');

    if (!municipioActivo) {
        body.innerHTML = '<div class="hint-text"><i class="fas fa-arrow-left"></i>Selecciona un municipio para ver sus secciones.</div>';
        subtitle.textContent = '';
        return;
    }

    const municipio = appData.zona.municipios.find(m => m.nombre === municipioActivo);
    if (!municipio) return;

    subtitle.textContent = municipio.nombre;

    if (municipio.secciones.length === 0) {
        body.innerHTML = '<div class="hint-text"><i class="fas fa-list-ol"></i>Sin secciones asignadas.</div>';
        return;
    }

    const totalLN = municipio.ln || 0;
    const infoBox = `<div class="zona-info-box">
        <div class="zi-title">${municipio.nombre}</div>
        <div class="zi-bullet"><strong>Secciones (${municipio.secciones.length}):</strong>
            ${municipio.secciones.slice(0,8).map(s=>s.nombre_seccion).join(', ')}${municipio.secciones.length>8?'...':''}
        </div>
        ${totalLN > 0 ? `<div class="zi-bullet" style="margin-top:4px;"><span style="font-weight:800;color:#92400e;background:#fef3c7;border:1px solid #fde68a;border-radius:6px;padding:3px 10px;font-size:13px;">${totalLN.toLocaleString('es-MX')} de lista nominal</span></div>` : ''}
    </div>`;

    body.innerHTML = infoBox + '<div class="list-scroll">' + municipio.secciones.map(s => {
        const meta   = parseInt(s.meta_contactos) || 0;
        const cont   = parseInt(s.contactos_conseguidos) || 0;
        const ln     = parseInt(s.lista_nominal) || 0;
        const pct    = meta > 0 ? Math.min(100, Math.round((cont / meta) * 100)) : 0;
        
        return `
        <div class="seccion-item">
            <span class="seccion-num">Sección ${s.nombre_seccion}</span>
            ${ln > 0 ? `<span class="seccion-ln">Ln: ${ln.toLocaleString('es-MX')}</span>` : ''}
            <span class="seccion-assigned"><i class="fas fa-check-circle"></i> Asignada</span>
        </div>`;
    }).join('') + '</div>';
}

/* ══════════════════════════════════════════════
   FILTRO DE SECCIONES (Frontend)
══════════════════════════════════════════════ */
function filtrarSecciones() {
    const input = document.getElementById('searchInput');
    const filter = input.value.toLowerCase();
    const nodes = document.querySelectorAll('.seccion-item');

    nodes.forEach(node => {
        // Buscamos dentro del título de la sección
        const numSeccion = node.querySelector('.seccion-num').textContent.toLowerCase();
        if (numSeccion.includes(filter)) {
            node.style.display = "flex";
        } else {
            node.style.display = "none";
        }
    });
}

/* ══════════════════════════════════════════════
   VER EN MAPA (solo lectura)
══════════════════════════════════════════════ */
function verEnMapa() {
    if (!appData.zona) return;
    const zonaId     = appData.zona.info?.id || '';
    const zonaNombre = appData.zona.nombre    || '';
    const returnUrl  = window.location.href.split('?')[0];
    const params = new URLSearchParams({
        zona_id:     zonaId,
        zona_nombre: zonaNombre,
        return_url:  returnUrl,
    });
    window.location.href = 'territorio.php?' + params.toString();
}

// Inicializar la carga al terminar de cargar el DOM
document.addEventListener('DOMContentLoaded', () => { 
    cargarDatos(); 
});
</script>
</body>
</html>