<?php
/* ============================================================
   DATABASE QR - Circunscripción
   1) Listado de Eventos/Activaciones con registro de asistentes
      habilitado (filtrado por el alcance territorial del rol).
   2) Consulta general de personas registradas dentro del alcance.
   ============================================================ */

$bootstrapCandidatos = [
    __DIR__ . '/includes/bootstrap.php',
    __DIR__ . '/../includes/bootstrap.php',
    __DIR__ . '/../../includes/bootstrap.php',
];
foreach ($bootstrapCandidatos as $bp) {
    if (file_exists($bp)) { require_once $bp; break; }
}
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$rol  = $_SESSION['user_role'] ?? '';
$name = $_SESSION['user_name'] ?? '';

// Estados de la circunscripción propia (para el filtro de Estado en el navegador)
$CIRCS = [
    'Circunscripcion 1' => ['Baja California','Baja California Sur','Chihuahua','Durango','Jalisco','Nayarit','Sinaloa','Sonora'],
    'Circunscripcion 2' => ['Aguascalientes','Coahuila','Guanajuato','Nuevo Leon','San Luis Potosi','Tamaulipas','Zacatecas'],
    'Circunscripcion 3' => ['Campeche','Chiapas','Oaxaca','Quintana Roo','Tabasco','Veracruz','Yucatan'],
    'Circunscripcion 4' => ['Ciudad de Mexico','Guerrero','Hidalgo','Morelos','Puebla','Tlaxcala'],
    'Circunscripcion 5' => ['Colima','Estado de Mexico','Michoacan','Queretaro'],
];
$estadosPropios = $CIRCS[$_SESSION['user_circunscripcion'] ?? ''] ?? [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Database QR</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
    --primary:#2d7a3e; --primary-dark:#1f5a2d; --dark:#1a1a1a; --light:#f0f7f0;
    --white:#ffffff; --gray-100:#f8f9fa; --gray-200:#e9ecef; --text-muted:#6c757d;
    --shadow-sm:0 10px 30px rgba(0,0,0,0.05);
}
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'Arial',sans-serif; background:var(--light); color:var(--dark); padding-left:280px; }
.container { max-width:1200px; margin:0 auto; padding:40px 30px 60px; }
.page-header { display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:16px; margin-bottom:24px; }
.ai-badge { color:var(--primary); font-size:12px; font-weight:800; letter-spacing:3px; margin-bottom:12px; display:inline-block; text-transform:uppercase; background:rgba(45,122,62,0.1); padding:5px 14px; border-radius:20px; }
.main-title { font-size:36px; font-weight:900; margin-bottom:6px; }
.description { color:#666; font-size:14px; }

.tabs { display:flex; gap:8px; margin-bottom:20px; }
.tab-btn { background:var(--white); border:1px solid var(--gray-200); padding:10px 20px; border-radius:12px; font-weight:700; font-size:13px; cursor:pointer; color:var(--text-muted); font-family:Arial,sans-serif; }
.tab-btn.active { background:var(--primary); color:white; border-color:var(--primary); }

.filters-card { background:var(--white); border:1px solid var(--gray-200); border-radius:16px; padding:18px 20px; margin-bottom:20px; display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; }
.f-group { display:flex; flex-direction:column; gap:4px; }
.f-group label { font-size:10px; font-weight:800; text-transform:uppercase; color:var(--text-muted); letter-spacing:0.5px; }
.f-group input, .f-group select { padding:9px 12px; border-radius:8px; border:1px solid var(--gray-200); font-size:13px; font-family:Arial,sans-serif; min-width:130px; }
.btn-filter { background:var(--primary); color:white; border:none; padding:10px 18px; border-radius:8px; font-weight:700; font-size:13px; cursor:pointer; }
.btn-clear { background:var(--white); color:var(--text-muted); border:1px solid var(--gray-200); padding:10px 14px; border-radius:8px; font-weight:600; font-size:12px; cursor:pointer; }

.table-card { background:var(--white); border:1px solid var(--gray-200); border-radius:20px; box-shadow:var(--shadow-sm); overflow:hidden; }
table.dqr-table { width:100%; border-collapse:collapse; }
table.dqr-table thead th { text-align:left; font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:0.5px; color:var(--text-muted); background:var(--gray-100); padding:14px 18px; border-bottom:1px solid var(--gray-200); }
table.dqr-table tbody td { padding:14px 18px; border-bottom:1px solid var(--gray-200); font-size:13px; vertical-align:middle; }
table.dqr-table tbody tr:last-child td { border-bottom:none; }
table.dqr-table tbody tr:hover { background:var(--gray-100); }

.badge-tipo { font-size:10px; font-weight:800; padding:3px 9px; border-radius:20px; text-transform:uppercase; }
.badge-tipo.evento { background:rgba(45,122,62,0.1); color:var(--primary); }
.badge-tipo.activacion { background:rgba(245,158,11,0.12); color:#b45309; }
.count-badge { display:inline-flex; align-items:center; gap:6px; font-size:12px; font-weight:700; color:var(--primary); }

.menu-wrap { position:relative; display:inline-block; }
.menu-dots { background:transparent; border:none; font-size:18px; color:var(--text-muted); cursor:pointer; width:34px; height:34px; border-radius:8px; }
.menu-dots:hover { background:var(--gray-100); }
.menu-dropdown { position:fixed; background:var(--white); border:1px solid var(--gray-200); border-radius:12px; box-shadow:0 15px 35px rgba(0,0,0,0.12); min-width:190px; z-index:9999; display:none; overflow:hidden; }
.menu-dropdown.open { display:block; }
.menu-item { padding:11px 16px; font-size:13px; font-weight:600; color:var(--dark); cursor:pointer; display:flex; align-items:center; gap:9px; text-decoration:none; }
.menu-item:hover { background:var(--gray-100); }

.empty-state { text-align:center; padding:60px 20px; color:var(--text-muted); }
.empty-state i { font-size:36px; opacity:0.25; margin-bottom:12px; display:block; }

.modal { display:flex; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(255,255,255,0.9); backdrop-filter:blur(5px); z-index:2000; align-items:center; justify-content:center; opacity:0; visibility:hidden; pointer-events:none; transition:0.3s; }
.modal.open { opacity:1; visibility:visible; pointer-events:all; }
.modal-content { background:var(--white); border-radius:20px; border:1px solid var(--gray-200); width:92%; max-width:600px; padding:32px; position:relative; max-height:88vh; overflow-y:auto; }
.close-modal { position:absolute; top:20px; right:20px; font-size:18px; cursor:pointer; color:#ccc; }
.detalle-header { text-align:center; margin-bottom:18px; }
.detalle-qr-big { width:180px; height:180px; border-radius:14px; border:1px solid var(--gray-200); margin:0 auto 12px; display:block; }
.link-box { display:flex; align-items:center; gap:8px; max-width:100%; margin-bottom:16px; }
.link-text { font-size:12px; color:var(--text-muted); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-family:monospace; background:var(--gray-100); padding:6px 10px; border-radius:8px; flex:1; }
.copy-btn { background:var(--gray-100); border:1px solid var(--gray-200); border-radius:8px; width:32px; height:32px; cursor:pointer; }
.registros-title { font-size:12px; font-weight:800; text-transform:uppercase; color:var(--text-muted); margin-bottom:8px; display:flex; justify-content:space-between; }
.registros-list { max-height:220px; overflow-y:auto; border:1px solid var(--gray-200); border-radius:12px; }
.det-tabs { display:flex; gap:6px; margin:18px 0 12px; border-bottom:1px solid var(--gray-200); }
.det-tab-btn { background:none; border:none; padding:9px 4px; margin-right:14px; font-size:12px; font-weight:700; color:var(--text-muted); cursor:pointer; border-bottom:2px solid transparent; font-family:Arial,sans-serif; }
.det-tab-btn.active { color:var(--primary); border-bottom-color:var(--primary); }
.det-preview-frame { border:1px solid var(--gray-200); border-radius:16px; overflow:hidden; background:var(--gray-100); }
.det-preview-frame iframe { width:100%; height:420px; border:none; display:block; }
.registro-row { padding:10px 14px; border-bottom:1px solid var(--gray-200); font-size:13px; display:flex; justify-content:space-between; gap:10px; }
.registro-row:last-child { border-bottom:none; }
.toast { position:fixed; bottom:24px; right:24px; background:var(--dark); color:white; padding:12px 20px; border-radius:10px; font-size:13px; font-weight:600; z-index:3000; opacity:0; transform:translateY(10px); transition:0.3s; pointer-events:none; }
.toast.show { opacity:1; transform:translateY(0); }
</style>
</head>
<body>
<?php if (file_exists(__DIR__ . '/../../includes/components/sidebar_circunscripcion.php'))
    include __DIR__ . '/../../includes/components/sidebar_circunscripcion.php'; ?>

<div class="container">
    <div class="page-header">
        <div>
            <div class="ai-badge"><i class="fas fa-database"></i> &nbsp; Database QR</div>
            <h1 class="main-title">Registro de Asistentes</h1>
            <p class="description">Eventos y Activaciones con registro habilitado, y consulta general de personas.</p>
        </div>
    </div>

    <div class="tabs">
        <button class="tab-btn active" id="tab-eventos" onclick="cambiarTab('eventos')"><i class="fas fa-calendar-check"></i> Por Evento/Activación</button>
        <button class="tab-btn" id="tab-personas" onclick="cambiarTab('personas')"><i class="fas fa-users"></i> Por Personas</button>
    </div>

    <!-- FILTROS -->
    <div class="filters-card" id="filtros-eventos">
        <div class="f-group">
            <label>Tipo</label>
            <select id="fl-tipo"><option value="">Todos</option><option value="evento">Evento</option><option value="activacion">Activación</option></select>
        </div>
        <div class="f-group">
            <label>Estado</label>
            <select id="fl-estado">
                <option value="">Todos</option>
                <?php foreach ($estadosPropios as $e): ?><option value="<?= htmlspecialchars($e) ?>"><?= htmlspecialchars($e) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="f-group"><label>Región</label><input type="text" id="fl-region" placeholder="Región…"></div>
        <div class="f-group"><label>Zona</label><input type="text" id="fl-zona" placeholder="Zona…"></div>
        <div class="f-group"><label>Nombre</label><input type="text" id="fl-nombre" placeholder="Título…"></div>
        <div class="f-group"><label>Desde</label><input type="date" id="fl-desde"></div>
        <div class="f-group"><label>Hasta</label><input type="date" id="fl-hasta"></div>
        <button class="btn-filter" onclick="cargarEventosQR()"><i class="fas fa-filter"></i> Filtrar</button>
        <button class="btn-clear" onclick="limpiarFiltrosEventos()">Limpiar</button>
    </div>

    <div class="filters-card hidden" id="filtros-personas" style="display:none;">
        <div class="f-group"><label>Nombre</label><input type="text" id="fp-nombre" placeholder="Nombre o apellido…"></div>
        <div class="f-group"><label>Celular</label><input type="text" id="fp-celular" placeholder="Celular…"></div>
        <div class="f-group">
            <label>Estado</label>
            <select id="fp-estado">
                <option value="">Todos</option>
                <?php foreach ($estadosPropios as $e): ?><option value="<?= htmlspecialchars($e) ?>"><?= htmlspecialchars($e) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="f-group"><label>Región</label><input type="text" id="fp-region" placeholder="Región…"></div>
        <div class="f-group"><label>Zona</label><input type="text" id="fp-zona" placeholder="Zona…"></div>
        <div class="f-group"><label>Desde</label><input type="date" id="fp-desde"></div>
        <div class="f-group"><label>Hasta</label><input type="date" id="fp-hasta"></div>
        <button class="btn-filter" onclick="cargarPersonas()"><i class="fas fa-filter"></i> Filtrar</button>
        <button class="btn-clear" onclick="limpiarFiltrosPersonas()">Limpiar</button>
    </div>

    <!-- TABLA EVENTOS/ACTIVACIONES -->
    <div class="table-card" id="vista-eventos">
        <div id="eventos-cont"><div class="empty-state"><i class="fas fa-spinner fa-spin"></i> Cargando…</div></div>
    </div>

    <!-- TABLA PERSONAS -->
    <div class="table-card" id="vista-personas" style="display:none;">
        <div id="personas-cont"><div class="empty-state"><i class="fas fa-spinner fa-spin"></i> Cargando…</div></div>
    </div>
</div>

<!-- Modal: Detalles de un Evento/Activación -->
<div class="modal" id="modal-detalles">
    <div class="modal-content">
        <div class="close-modal" onclick="cerrarModal('modal-detalles')"><i class="fas fa-times"></i></div>
        <div class="detalle-header">
            <img class="detalle-qr-big" id="det-qr" src="" alt="QR">
            <div style="font-size:19px;font-weight:800;" id="det-titulo"></div>
        </div>
        <div class="link-box">
            <div class="link-text" id="det-link"></div>
            <button class="copy-btn" onclick="copiarLink(document.getElementById('det-link').textContent, this)"><i class="fas fa-copy"></i></button>
        </div>

        <div class="det-tabs">
            <button class="det-tab-btn active" id="det-tab-registrados" onclick="detCambiarTab('registrados')"><i class="fas fa-list"></i> Registrados</button>
            <button class="det-tab-btn" id="det-tab-preview" onclick="detCambiarTab('preview')"><i class="fas fa-eye"></i> Vista previa del formulario</button>
        </div>

        <div id="det-vista-registrados">
            <div class="registros-title">
                <span>Registrados</span>
                <div style="display:flex;align-items:center;gap:10px;">
                    <a id="det-export-link" href="#" class="btn-clear" style="text-decoration:none;"><i class="fas fa-file-excel"></i> Exportar</a>
                    <span id="det-total" style="color:var(--primary);font-weight:700;"></span>
                </div>
            </div>
            <div class="registros-list" id="det-registros"></div>
        </div>

        <div id="det-vista-preview" style="display:none;">
            <div style="font-size:11px;color:var(--text-muted);margin-bottom:8px;">Así lo ve la persona que abre la liga o escanea el QR:</div>
            <div class="det-preview-frame">
                <iframe id="det-preview-iframe" src="about:blank" title="Vista previa del formulario"></iframe>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Historial de una persona -->
<div class="modal" id="modal-historial">
    <div class="modal-content">
        <div class="close-modal" onclick="cerrarModal('modal-historial')"><i class="fas fa-times"></i></div>
        <div style="font-size:19px;font-weight:800;margin-bottom:16px;" id="hist-nombre"></div>
        <div class="registros-list" id="hist-lista"></div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
const API = '../../api/registro_asistentes/procesar.php';

function cambiarTab(tab) {
    document.getElementById('tab-eventos').classList.toggle('active', tab==='eventos');
    document.getElementById('tab-personas').classList.toggle('active', tab==='personas');
    document.getElementById('vista-eventos').style.display = tab==='eventos' ? '' : 'none';
    document.getElementById('vista-personas').style.display = tab==='personas' ? '' : 'none';
    document.getElementById('filtros-eventos').style.display = tab==='eventos' ? '' : 'none';
    document.getElementById('filtros-personas').style.display = tab==='personas' ? '' : 'none';
    if (tab==='personas') cargarPersonas();
}

function mostrarToast(msg) {
    const t = document.getElementById('toast');
    t.textContent = msg; t.classList.add('show');
    setTimeout(()=>t.classList.remove('show'), 2200);
}
function copiarLink(link, btn) {
    navigator.clipboard.writeText(link).then(()=>mostrarToast('Link copiado al portapapeles'));
}
function abrirModal(id){ document.getElementById(id).classList.add('open'); }
function cerrarModal(id){ document.getElementById(id).classList.remove('open'); }

let menuAbierto = null;
function toggleMenu(evt, id) {
    evt.stopPropagation();
    const el = document.getElementById('menu-'+id);
    if (menuAbierto && menuAbierto !== el) menuAbierto.classList.remove('open');
    const yaAbierto = el.classList.contains('open');
    el.classList.toggle('open');
    if (!yaAbierto) {
        // position:fixed calculado en JS: escapa del overflow:hidden de .table-card
        const rect = evt.currentTarget.getBoundingClientRect();
        const minWidth = 190;
        let left = rect.right - minWidth;
        if (left < 8) left = 8;
        let top = rect.bottom + 4;
        el.style.left = left + 'px';
        el.style.top = top + 'px';
        // Si se saldría por abajo de la ventana, la abrimos hacia arriba del botón
        requestAnimationFrame(() => {
            const menuRect = el.getBoundingClientRect();
            if (menuRect.bottom > window.innerHeight) {
                el.style.top = (rect.top - menuRect.height - 4) + 'px';
            }
        });
    }
    menuAbierto = el.classList.contains('open') ? el : null;
}
document.addEventListener('click', ()=>{ if (menuAbierto){ menuAbierto.classList.remove('open'); menuAbierto=null; } });

function qsFiltrosEventos() {
    const p = new URLSearchParams();
    ['tipo','estado','region','zona','nombre','desde','hasta'].forEach(k=>{
        const v = document.getElementById('fl-'+k).value.trim();
        if (v) p.set(k, v);
    });
    return p.toString();
}
function limpiarFiltrosEventos() {
    ['tipo','estado','region','zona','nombre','desde','hasta'].forEach(k=>document.getElementById('fl-'+k).value='');
    cargarEventosQR();
}

async function cargarEventosQR() {
    const cont = document.getElementById('eventos-cont');
    cont.innerHTML = '<div class="empty-state"><i class="fas fa-spinner fa-spin"></i> Cargando…</div>';
    try {
        const resp = await fetch(`${API}?action=list&${qsFiltrosEventos()}`);
        const res = await resp.json();
        if (!res.ok) { cont.innerHTML = `<div class="empty-state">${res.msg||'Error al cargar.'}</div>`; return; }
        if (res.items.length === 0) { cont.innerHTML = '<div class="empty-state"><i class="fas fa-inbox"></i> No hay eventos/activaciones con registro habilitado en tu alcance.</div>'; return; }
        cont.innerHTML = `<table class="dqr-table"><thead><tr>
            <th>Evento/Activación</th><th>Tipo</th><th>Fecha</th><th>Ubicación</th><th>Registrados</th><th style="width:44px;"></th>
        </tr></thead><tbody>` + res.items.map(it => `
            <tr>
                <td><strong>${escHtml(it.titulo)}</strong></td>
                <td><span class="badge-tipo ${it.tipo_origen}">${it.tipo_origen}</span></td>
                <td>${it.fecha_actividad ? formatearFecha(it.fecha_actividad) : '—'}</td>
                <td>${escHtml([it.zona, it.region, it.estado].filter(Boolean).join(' · ') || '—')}</td>
                <td><span class="count-badge"><i class="fas fa-user-check"></i> ${it.total_registrados}</span></td>
                <td>
                    <div class="menu-wrap">
                        <button class="menu-dots" onclick="toggleMenu(event, ${it.id})"><i class="fas fa-ellipsis-vertical"></i></button>
                        <div class="menu-dropdown" id="menu-${it.id}">
                            <div class="menu-item" onclick="verDetalles(${it.id})"><i class="fas fa-circle-info"></i> Ver detalles</div>
                            <div class="menu-item" onclick="descargarQR('${escAttr(it.link)}', '${escAttr(it.titulo)}')"><i class="fas fa-download"></i> Descargar QR</div>
                            <a class="menu-item" href="${API}?action=exportar&id=${it.id}" style="text-decoration:none;"><i class="fas fa-file-excel"></i> Exportar a Excel</a>
                            <div class="menu-item" onclick="window.open('${escAttr(it.link)}','_blank')"><i class="fas fa-arrow-up-right-from-square"></i> Abrir invitación</div>
                            <div class="menu-item" style="color:#d32f2f;" onclick="desactivarDesdeDB(${it.id})"><i class="fas fa-ban"></i> Desactivar registro</div>
                        </div>
                    </div>
                </td>
            </tr>`).join('') + '</tbody></table>';
    } catch (e) { cont.innerHTML = '<div class="empty-state">Error de conexión.</div>'; }
}

async function verDetalles(id) {
    try {
        const resp = await fetch(`${API}?action=obtener_detalles&id=${id}`);
        const res = await resp.json();
        if (!res.ok) { mostrarToast(res.msg || 'No se pudo cargar.'); return; }
        document.getElementById('det-titulo').textContent = res.registro.titulo;
        document.getElementById('det-link').textContent = res.registro.link;
        document.getElementById('det-qr').src = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' + encodeURIComponent(res.registro.link);
        document.getElementById('det-total').textContent = res.registrados.length + ' persona' + (res.registrados.length===1?'':'s');
        document.getElementById('det-export-link').href = `${API}?action=exportar&id=${id}`;
        const cont = document.getElementById('det-registros');
        cont.innerHTML = res.registrados.length === 0
            ? '<div style="padding:18px;text-align:center;color:var(--text-muted);font-size:13px;">Aún no hay registros.</div>'
            : res.registrados.map(r => `<div class="registro-row"><span><strong>${escHtml(r.nombres)} ${escHtml(r.apellidos)}</strong></span><span style="color:var(--text-muted);">${escHtml(r.lada)} ${escHtml(r.celular)}</span></div>`).join('');
        // La vista previa carga el propio formulario público en un iframe; se
        // asigna el src solo al abrir la pestaña (detCambiarTab) para no
        // gastar una carga de la liga si el usuario nunca la revisa.
        const iframePrev = document.getElementById('det-preview-iframe');
        iframePrev.src = 'about:blank';
        iframePrev.dataset.src = res.registro.link;
        detCambiarTab('registrados');
        abrirModal('modal-detalles');
    } catch (e) { mostrarToast('Error de conexión.'); }
}

function detCambiarTab(tab) {
    document.getElementById('det-tab-registrados').classList.toggle('active', tab==='registrados');
    document.getElementById('det-tab-preview').classList.toggle('active', tab==='preview');
    document.getElementById('det-vista-registrados').style.display = tab==='registrados' ? '' : 'none';
    document.getElementById('det-vista-preview').style.display = tab==='preview' ? '' : 'none';
    if (tab === 'preview') {
        const iframe = document.getElementById('det-preview-iframe');
        if (iframe.src === 'about:blank' && iframe.dataset.src) iframe.src = iframe.dataset.src;
    }
}

async function desactivarDesdeDB(id) {
    if (!confirm('¿Desactivar el registro de asistentes? La liga dejará de aceptar nuevos registros.')) return;
    const fd = new FormData(); fd.append('action','desactivar'); fd.append('id', id);
    try {
        const resp = await fetch(API, { method:'POST', body:fd });
        const res = await resp.json();
        if (res.ok) { mostrarToast('Registro desactivado'); cargarEventosQR(); }
        else mostrarToast(res.msg || 'No se pudo desactivar.');
    } catch(e) { mostrarToast('Error de conexión.'); }
}

async function descargarQR(link, titulo) {
    const qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=500x500&data=' + encodeURIComponent(link);
    const nombreArchivo = 'qr-' + (titulo||'registro').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'') + '.png';
    try {
        const resp = await fetch(qrUrl); const blob = await resp.blob();
        const objectUrl = URL.createObjectURL(blob);
        const a = document.createElement('a'); a.href = objectUrl; a.download = nombreArchivo;
        document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(objectUrl);
    } catch (e) { window.open(qrUrl, '_blank'); mostrarToast('Clic derecho y "Guardar imagen como..."'); }
}

// ── PERSONAS ──────────────────────────────────────────────
function qsFiltrosPersonas() {
    const p = new URLSearchParams();
    ['nombre','celular','estado','region','zona','desde','hasta'].forEach(k=>{
        const v = document.getElementById('fp-'+k).value.trim();
        if (v) p.set(k, v);
    });
    return p.toString();
}
function limpiarFiltrosPersonas() {
    ['nombre','celular','estado','region','zona','desde','hasta'].forEach(k=>document.getElementById('fp-'+k).value='');
    cargarPersonas();
}

async function cargarPersonas() {
    const cont = document.getElementById('personas-cont');
    cont.innerHTML = '<div class="empty-state"><i class="fas fa-spinner fa-spin"></i> Cargando…</div>';
    try {
        const resp = await fetch(`${API}?action=list_personas&${qsFiltrosPersonas()}`);
        const res = await resp.json();
        if (!res.ok) { cont.innerHTML = `<div class="empty-state">${res.msg||'Error al cargar.'}</div>`; return; }
        if (res.items.length === 0) { cont.innerHTML = '<div class="empty-state"><i class="fas fa-user-slash"></i> No hay personas registradas en tu alcance.</div>'; return; }
        cont.innerHTML = `<table class="dqr-table"><thead><tr>
            <th>Nombre</th><th>Celular</th><th>Eventos/Activaciones</th><th>Último registro</th><th style="width:60px;"></th>
        </tr></thead><tbody>` + res.items.map(p => `
            <tr>
                <td><strong>${escHtml(p.nombres)} ${escHtml(p.apellidos)}</strong></td>
                <td>${escHtml(p.lada)} ${escHtml(p.celular)}</td>
                <td>${p.total_eventos}</td>
                <td>${formatearFechaHora(p.ultimo_registro)}</td>
                <td><button class="btn-clear" onclick="verHistorial(${p.id}, '${escAttr(p.nombres+' '+p.apellidos)}')"><i class="fas fa-clock-rotate-left"></i></button></td>
            </tr>`).join('') + '</tbody></table>';
    } catch (e) { cont.innerHTML = '<div class="empty-state">Error de conexión.</div>'; }
}

async function verHistorial(personaId, nombreCompleto) {
    try {
        const resp = await fetch(`${API}?action=historial_persona&persona_id=${personaId}`);
        const res = await resp.json();
        document.getElementById('hist-nombre').textContent = nombreCompleto;
        const lista = document.getElementById('hist-lista');
        lista.innerHTML = (!res.ok || res.items.length === 0)
            ? '<div style="padding:18px;text-align:center;color:var(--text-muted);font-size:13px;">Sin historial dentro de tu alcance.</div>'
            : res.items.map(h => `<div class="registro-row"><span><strong>${escHtml(h.titulo)}</strong> <span class="badge-tipo ${h.tipo_origen}">${h.tipo_origen}</span></span><span style="color:var(--text-muted);">${formatearFechaHora(h.fecha_registro)}</span></div>`).join('');
        abrirModal('modal-historial');
    } catch (e) { mostrarToast('Error de conexión.'); }
}

function formatearFecha(f) { const d = new Date(f+'T00:00:00'); return d.toLocaleDateString('es-MX'); }
function formatearFechaHora(f) { const d = new Date(f.replace(' ','T')); return d.toLocaleString('es-MX'); }
function escHtml(str) { const d = document.createElement('div'); d.textContent = str ?? ''; return d.innerHTML; }
function escAttr(str) { return (str ?? '').replace(/'/g, "\\'"); }

cargarEventosQR();
</script>
</body>
</html>