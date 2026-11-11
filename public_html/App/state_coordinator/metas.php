<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

$rol = $_SESSION['user_role'] ?? '';
$permitidos = ['admin', 'administrador', 'superadmin', 'super admin', 'coordinador_estatal'];
if (!in_array($rol, $permitidos)) {
    header('Location: /public/index.php');
    exit();
}
$estado_actual = $_SESSION['user_estado'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Metas · GreenBoard</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
  :root { --primary: #2d7a3e; --primary-dark: #1f5a2d; --accent: #e8a020; --dark: #1a1a1a; --light: #f0f7f0; --white: #ffffff;
          --success: #22c55e; --warning: #f59e0b; --danger: #ef4444; --info: #3b82f6; --text-muted: #888; --border: #f0f0f0;
          --shadow-sm: 0 10px 30px rgba(0,0,0,0.05); --radius: 20px; }
  * { box-sizing: border-box; margin:0; padding:0; }
  body { font-family: -apple-system, 'Segoe UI', Arial, sans-serif; background:#fafafa; color: var(--dark); }
  .container { margin-left: 260px; padding: 28px 36px 60px; }
  @media (max-width: 900px){ .container{ margin-left:0; } }

  .page-header { display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:16px; margin-bottom:22px; }
  .ai-badge { color: var(--primary); font-size: 12px; font-weight: 800; letter-spacing: 3px; margin-bottom: 10px; display: inline-block; text-transform: uppercase; background: rgba(45,122,62,0.1); padding: 5px 15px; border-radius: 20px; }
  .main-title { font-size: 28px; font-weight: 800; }
  .description { color: var(--text-muted); font-size: 14px; margin-top:4px; }

  .context-bar { display:flex; flex-wrap:wrap; gap:20px; align-items:flex-end; background:white; border:1px solid var(--border); border-radius:16px; padding:18px 20px; margin-bottom:22px; box-shadow: var(--shadow-sm); }
  .filter-group { display:flex; flex-direction:column; gap:6px; }
  .filter-label { font-size: 11px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
  .ui-select { background: var(--white); border: 1.5px solid var(--border); padding: 9px 36px 9px 14px; border-radius: 10px; font-size: 13px; font-weight: 700; color: var(--dark); cursor: pointer; outline: none; appearance:none;
    background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%232d7a3e' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
    background-repeat:no-repeat; background-position: right 10px center; background-size:14px; min-width:150px; }

  .weeks-row { display:flex; gap:8px; flex-wrap:wrap; }
  .week-chip { border:1.5px solid var(--border); background:white; border-radius:10px; padding:8px 14px; font-size:12px; font-weight:700; color:var(--dark); cursor:pointer; white-space:nowrap; }
  .week-chip.active { background: var(--primary); border-color: var(--primary); color:white; }

  .card { background:white; border:1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow-sm); overflow:hidden; }
  .card-header { display:flex; justify-content:space-between; align-items:center; padding:20px 24px; border-bottom:1px solid var(--border); flex-wrap:wrap; gap:10px; }
  .card-title { font-size:16px; font-weight:800; }
  .card-sub { font-size:12px; color:var(--text-muted); margin-top:2px; }

  table.metas-table { width:100%; border-collapse:collapse; }
  table.metas-table th { text-align:left; font-size:11px; text-transform:uppercase; letter-spacing:0.5px; color:var(--text-muted); font-weight:800; padding:12px 20px; border-bottom:1px solid var(--border); background:#fafafa; }
  table.metas-table td { padding:10px 20px; border-bottom:1px solid var(--border); font-size:13px; }
  table.metas-table tr:last-child td { border-bottom:none; }
  .entidad-name { font-weight:700; }
  input.meta-input { width:90px; padding:8px 10px; border:1.5px solid var(--border); border-radius:8px; font-size:13px; font-weight:700; text-align:center; }
  input.meta-input:focus { outline:none; border-color: var(--primary); }
  .col-voto { color:var(--info); }
  .col-com  { color:#8b5cf6; }
  .col-org  { color:var(--accent); }

  tr.total-row td { background:#f6faf6; border-bottom:2px solid var(--primary); font-weight:800; }
  tr.total-row .entidad-name { color:var(--primary-dark); }
  .total-value { display:inline-flex; align-items:center; justify-content:center; width:90px; padding:8px 10px; border:1.5px solid var(--border); border-radius:8px; font-size:13px; font-weight:800; background:white; }

  .btn-action { display:inline-flex; align-items:center; gap:8px; background: var(--primary); color:white; border:none; padding:11px 22px; border-radius:12px; font-size:13px; font-weight:700; cursor:pointer; transition:.2s; }
  .btn-action:hover { background: var(--primary-dark); }
  .btn-outline { background:white; color:var(--dark); border:1.5px solid var(--border); }
  .btn-outline:hover { border-color: var(--primary); color: var(--primary); }

  .empty-state { padding:50px 20px; text-align:center; color:var(--text-muted); }
  .empty-state i { font-size:30px; margin-bottom:10px; display:block; }
  .toast { position:fixed; bottom:24px; right:24px; background: var(--dark); color:white; padding:14px 22px; border-radius:12px; font-size:13px; font-weight:700; box-shadow:0 10px 30px rgba(0,0,0,.2); opacity:0; transform:translateY(10px); transition:.25s; pointer-events:none; z-index:999; }
  .toast.show { opacity:1; transform:translateY(0); }
  .toast.error { background: var(--danger); }
</style>
</head>
<body>
<?php include __DIR__ . '/../../includes/components/sidebar_ce.php'; ?>

<div class="container">
  <div class="page-header">
    <div>
      <span class="ai-badge">METAS</span>
      <h1 class="main-title">Metas de Agenda por Región</h1>
      <p class="description">Define cuántos eventos de cada eje debe realizar cada región, por semana.</p>
    </div>
    <a href="dashboard.php" class="btn-action btn-outline"><i class="fas fa-arrow-left"></i> Volver al Dashboard</a>
  </div>

  <div class="context-bar">
    <div class="filter-group">
      <span class="filter-label">Año</span>
      <select id="year-select" class="ui-select" onchange="onMesAnioChange()">
        <?php $anioActual = (int)date('Y'); for ($y = 2026; $y <= max(2026, $anioActual); $y++): ?>
          <option value="<?= $y ?>" <?= $y === $anioActual ? 'selected' : '' ?>><?= $y ?></option>
        <?php endfor; ?>
      </select>
    </div>
    <div class="filter-group">
      <span class="filter-label">Mes</span>
      <select id="month-select" class="ui-select" onchange="onMesAnioChange()"></select>
    </div>
    <div class="filter-group" style="flex:1;">
      <span class="filter-label">Semana</span>
      <div class="weeks-row" id="weeks-row"></div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <div>
        <div class="card-title">Metas por región — <span id="periodo-label"></span></div>
        <div class="card-sub">Regla sugerida 45% Voto / 35% Comunicación / 20% Organización</div>
      </div>
      <button class="btn-action" onclick="guardarMetas()"><i class="fas fa-save"></i> Guardar cambios</button>
    </div>
    <div id="tabla-wrap">
      <div class="empty-state"><i class="fas fa-spinner fa-spin"></i>Cargando…</div>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
const API = '../../api/metas/procesar.php';
const mesesArr = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
const hoy = new Date();

let entidades = [];      // [{id, nombre}]
let metasDelMes = [];    // filas crudas desde 'listar': {entidad_id, semana, eje, meta}
let semanasDelMes = [];  // [{numero, rango}]
let semanaActiva = null;

function getWeekNumber(d) {
  d = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()));
  d.setUTCDate(d.getUTCDate() + 4 - (d.getUTCDay()||7));
  const yearStart = new Date(Date.UTC(d.getUTCFullYear(),0,1));
  return Math.ceil((((d - yearStart) / 86400000) + 1)/7);
}
function getDateRangeOfWeek(weekNo, year) {
  let d = new Date(year, 0, 1);
  let dayNum = d.getDay() || 7;
  d.setDate(d.getDate() + (4 - dayNum) + (weekNo - 1) * 7);
  let start = new Date(d); start.setDate(d.getDate() - 3);
  let end = new Date(d); end.setDate(d.getDate() + 3);
  const mesesAbrev = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
  return `${start.getDate()} ${mesesAbrev[start.getMonth()]} - ${end.getDate()} ${mesesAbrev[end.getMonth()]}`;
}

function showToast(msg, isError) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.className = 'toast show' + (isError ? ' error' : '');
  setTimeout(() => t.className = 'toast', 2200);
}

async function cargarEntidades() {
  const res = await fetch(`${API}?action=entidades`);
  const data = await res.json();
  if (data.ok) entidades = data.entidades || [];
}

function construirSemanas() {
  const year  = parseInt(document.getElementById('year-select').value);
  const month = parseInt(document.getElementById('month-select').value) - 1;
  semanasDelMes = [];
  const firstDay = new Date(year, month, 1);
  const w1 = getWeekNumber(firstDay);
  for (let i = 0; i < 5; i++) {
    const numero = w1 + i;
    // Si la semana ya cae completamente en el mes siguiente, no la mostramos
    const jueves = new Date(year, 0, 1);
    let dayNum = jueves.getDay() || 7;
    jueves.setDate(jueves.getDate() + (4 - dayNum) + (numero - 1) * 7);
    if (jueves.getMonth() !== month && i > 0) break;
    semanasDelMes.push({ numero, rango: getDateRangeOfWeek(numero, year) });
  }
  const row = document.getElementById('weeks-row');
  row.innerHTML = semanasDelMes.map(s =>
    `<div class="week-chip" data-sem="${s.numero}" onclick="seleccionarSemana(${s.numero})">Sem ${s.numero} (${s.rango})</div>`
  ).join('');

  const hoyEnMes = (year === hoy.getFullYear() && month === hoy.getMonth());
  const semHoy = hoyEnMes ? getWeekNumber(hoy) : null;
  const existe = semanasDelMes.find(s => s.numero === semHoy);
  semanaActiva = existe ? semHoy : (semanasDelMes[0] ? semanasDelMes[0].numero : null);
  marcarSemanaActiva();
}

function marcarSemanaActiva() {
  document.querySelectorAll('.week-chip').forEach(el => {
    el.classList.toggle('active', parseInt(el.dataset.sem) === semanaActiva);
  });
  const mSel = document.getElementById('month-select');
  const mName = mSel.options[mSel.selectedIndex]?.text || '';
  const ySel = document.getElementById('year-select').value;
  document.getElementById('periodo-label').textContent = `Semana ${semanaActiva} · ${mName} ${ySel}`;
}

function seleccionarSemana(numero) {
  semanaActiva = numero;
  marcarSemanaActiva();
  renderTabla();
}

async function cargarMetasDelMes() {
  const anio = document.getElementById('year-select').value;
  const mes  = document.getElementById('month-select').value;
  document.getElementById('tabla-wrap').innerHTML = '<div class="empty-state"><i class="fas fa-spinner fa-spin"></i>Cargando…</div>';
  const res = await fetch(`${API}?action=listar&anio=${anio}&mes=${mes}`);
  const data = await res.json();
  metasDelMes = data.ok ? (data.metas || []) : [];
  renderTabla();
}

function valorGuardado(entidadId, semana, eje) {
  const row = metasDelMes.find(m => parseInt(m.entidad_id) === entidadId && parseInt(m.semana) === semana && m.eje === eje);
  return row ? parseInt(row.meta) : 0;
}

function renderTabla() {
  const wrap = document.getElementById('tabla-wrap');
  if (!entidades.length) {
    wrap.innerHTML = '<div class="empty-state"><i class="fas fa-map"></i>No hay regiones configuradas para tu estado.</div>';
    return;
  }
  if (!semanaActiva) {
    wrap.innerHTML = '<div class="empty-state"><i class="fas fa-calendar-times"></i>Selecciona una semana.</div>';
    return;
  }
  let rows = entidades.map(e => `
    <tr data-entidad-id="${e.id}" data-entidad-nombre="${e.nombre.replace(/"/g,'&quot;')}">
      <td class="entidad-name">${e.nombre}${e.clave ? ` <span style="color:var(--text-muted);font-weight:400;">(${e.clave})</span>` : ''}</td>
      <td><input type="number" min="0" class="meta-input col-voto" data-eje="voto" value="${valorGuardado(e.id, semanaActiva, 'voto')}"></td>
      <td><input type="number" min="0" class="meta-input col-com"  data-eje="comunicacion" value="${valorGuardado(e.id, semanaActiva, 'comunicacion')}"></td>
      <td><input type="number" min="0" class="meta-input col-org"  data-eje="organizacion" value="${valorGuardado(e.id, semanaActiva, 'organizacion')}"></td>
    </tr>`).join('');

  const filaTotal = `
    <tr class="total-row">
      <td class="entidad-name">Total</td>
      <td><span class="total-value col-voto" id="total-voto">0</span></td>
      <td><span class="total-value col-com" id="total-com">0</span></td>
      <td><span class="total-value col-org" id="total-org">0</span></td>
    </tr>`;

  wrap.innerHTML = `
    <table class="metas-table">
      <thead><tr>
        <th>Región</th>
        <th class="col-voto">Voto / Territorio</th>
        <th class="col-com">Comunicación</th>
        <th class="col-org">Organización</th>
      </tr></thead>
      <tbody>${filaTotal}${rows}</tbody>
    </table>`;
  calcularTotales();
}

function calcularTotales() {
  const filas = document.querySelectorAll('#tabla-wrap tbody tr:not(.total-row)');
  let totVoto = 0, totCom = 0, totOrg = 0;
  filas.forEach(tr => {
    totVoto += parseInt(tr.querySelector('.col-voto')?.value || 0);
    totCom  += parseInt(tr.querySelector('.col-com')?.value || 0);
    totOrg  += parseInt(tr.querySelector('.col-org')?.value || 0);
  });
  const elVoto = document.getElementById('total-voto');
  const elCom  = document.getElementById('total-com');
  const elOrg  = document.getElementById('total-org');
  if (elVoto) elVoto.textContent = totVoto;
  if (elCom)  elCom.textContent  = totCom;
  if (elOrg)  elOrg.textContent  = totOrg;
}

async function guardarMetas() {
  const anio = document.getElementById('year-select').value;
  const mes  = document.getElementById('month-select').value;
  const filas = Array.from(document.querySelectorAll('#tabla-wrap tbody tr:not(.total-row)')).map(tr => {
    const inputs = tr.querySelectorAll('.meta-input');
    const fila = {
      entidad_id: parseInt(tr.dataset.entidadId),
      entidad_nombre: tr.dataset.entidadNombre,
      semana: semanaActiva,
    };
    inputs.forEach(inp => fila[inp.dataset.eje] = parseInt(inp.value || 0));
    return fila;
  });

  try {
    const res = await fetch(`${API}?action=guardar`, {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ anio, mes, filas })
    });
    const data = await res.json();
    if (data.ok) {
      showToast('Metas guardadas correctamente');
      cargarMetasDelMes();
    } else {
      showToast(data.msg || 'Error al guardar', true);
    }
  } catch (e) {
    showToast('Error de conexión', true);
  }
}

function onMesAnioChange() {
  construirSemanas();
  cargarMetasDelMes();
}

window.addEventListener('DOMContentLoaded', async () => {
  const ms = document.getElementById('month-select');
  ms.innerHTML = mesesArr.map((m,i) => `<option value="${i+1}" ${i===hoy.getMonth()?'selected':''}>${m}</option>`).join('');
  document.getElementById('tabla-wrap').addEventListener('input', (e) => {
    if (e.target.classList.contains('meta-input')) calcularTotales();
  });
  await cargarEntidades();
  construirSemanas();
  await cargarMetasDelMes();
});
</script>
</body>
</html>