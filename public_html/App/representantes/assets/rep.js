/* =====================================================================
   Módulo «Representantes» — GreenBoard
   Vistas: tablero, casillas, padron, registrar, expedientes, registro,
           metas, ruta, configuracion, bitacora
   Todo el cálculo y las validaciones viven en el servidor
   (/api/representantes/api.php); aquí solo se pinta y se repiten las
   validaciones de formato para avisar de inmediato.
   ===================================================================== */
(function () {
  'use strict';

  const ROOT = document.getElementById('rep');
  const VIEW = document.getElementById('view');
  const LAYER = document.getElementById('rep-layer');
  const TOP = document.getElementById('rep-topbar');
  const PAGE = ROOT.dataset.page;
  const API = '/api/representantes/api.php';
  const QS = new URLSearchParams(location.search);
  let CTX = null;

  /* ------------------------------------------------------------------
     Utilidades
     ------------------------------------------------------------------ */
  const MES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
  const NF = new Intl.NumberFormat('es-MX');
  const N = x => NF.format(Math.round(+x || 0));
  const P = (x, d = 1) => (Number.isFinite(+x) ? +x : 0).toFixed(d) + '%';
  const clamp = (x, a, b) => Math.max(a, Math.min(b, x));
  const DAY = 864e5;
  const dn = s => Math.round(Date.parse(String(s).slice(0, 10) + 'T00:00:00Z') / DAY);
  const isoOf = n => new Date(n * DAY).toISOString().slice(0, 10);
  const fd = s => { if (!s) return ''; const i = String(s).slice(0, 10); return `${+i.slice(8)} ${MES[+i.slice(5, 7) - 1]} ${i.slice(0, 4)}`; };
  const fdc = s => { if (!s) return ''; const i = String(s).slice(0, 10); return `${+i.slice(8)} ${MES[+i.slice(5, 7) - 1]}`; };
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const can = p => !!CTX && CTX.perms.includes(p);
  const $ = (sel, el = document) => el.querySelector(sel);
  const $$ = (sel, el = document) => Array.from(el.querySelectorAll(sel));
  const RX_CURP = /^[A-Z][AEIOUX][A-Z]{2}\d{2}(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])[HM][A-Z]{2}[B-DF-HJ-NP-TV-Z]{3}[A-Z0-9]\d$/;
  const RX_NOM = /^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ' .-]{2,60}$/;
  const debounce = (fn, ms = 300) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };

  function estadoSel() { return QS.get('estado') || sessionStorage.getItem('rep_estado') || ''; }

  async function api(action, params = {}, body = null) {
    const u = new URLSearchParams(Object.assign({ action }, params));
    const e = estadoSel();
    if (e && !u.has('estado')) u.set('estado', e);
    const opt = { credentials: 'same-origin' };
    if (body instanceof FormData) { opt.method = 'POST'; opt.body = body; }
    else if (body) { opt.method = 'POST'; opt.headers = { 'Content-Type': 'application/json' }; opt.body = JSON.stringify(body); }
    let r, d;
    try { r = await fetch(API + '?' + u.toString(), opt); } catch (_) { throw new Error('Error de conexión. Revisa tu internet e intenta de nuevo.'); }
    try { d = await r.json(); } catch (_) { throw new Error('Respuesta inválida del servidor (' + r.status + ').'); }
    if (!d.ok) { const err = new Error(d.msg || 'Error'); err.data = d; throw err; }
    return d;
  }

  /* ------------------------------------------------------------------
     Íconos y piezas visuales
     ------------------------------------------------------------------ */
  const ICONS = {
    search: 'M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16zM21 21l-4.35-4.35',
    download: 'M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3',
    upload: 'M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12',
    check: 'M20 6L9 17l-5-5',
    alert: 'M12 9v4M12 17h.01M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z',
    info: 'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20zM12 16v-4M12 8h.01',
    x: 'M18 6L6 18M6 6l12 12',
    eye: 'M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8zM12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z',
    userplus: 'M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M8.5 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M20 8v6M23 11h-6',
    stamp: 'M5 22h14M5 18h14v-3a2 2 0 0 0-2-2h-3V9a3 3 0 1 0-4 0v4H7a2 2 0 0 0-2 2z',
    plus: 'M12 5v14M5 12h14',
    lock: 'M5 11h14v10H5zM8 11V7a4 4 0 0 1 8 0v4',
  };
  const Ic = n => `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="${ICONS[n]}"/></svg>`;
  const chip = (k, t) => `<span class="chip ${esc(k || '')}">${esc(t || (CTX && CTX.sem[k]) || k)}</span>`;
  const estChip = e => { const x = CTX.estatus[e] || [e, '']; return `<span class="chip ${x[1]}">${esc(x[0])}</span>`; };
  const bar = (v, mark, c, thin) => `<div class="bar${thin ? ' thin' : ''}" style="--c:var(--c-${c || 'rec'})"><i style="width:${clamp(+v || 0, 0, 100)}%"></i>${mark != null ? `<b style="left:${clamp(+mark, 0, 100)}%"></b>` : ''}</div>`;
  const banner = (k, html) => `<div class="banner ${k}">${Ic(k === 'ok' ? 'check' : k === 'info' ? 'info' : 'alert')}<div>${html}</div></div>`;
  const tipoL = t => t === 'CONSEJERO' ? 'Consejero' : t;
  const loading = (t = 'Cargando…') => `<div class="empty"><span class="spin"></span> ${esc(t)}</div>`;

  function toast(msg, kind) {
    const el = document.createElement('div');
    el.className = 'toast ' + (kind || '');
    el.setAttribute('role', 'status');
    el.innerHTML = Ic(kind === 'bad' ? 'alert' : 'check') + `<div>${esc(msg)}</div>`;
    $$('.toast', LAYER).forEach(x => x.remove());
    LAYER.appendChild(el);
    setTimeout(() => el.remove(), kind === 'bad' ? 6500 : 4200);
  }
  const fallo = e => toast(e.message || String(e), 'bad');

  function modal(title, html, wide) {
    const wrap = document.createElement('div');
    wrap.innerHTML = `<div class="scrim"></div><div class="modal${wide ? ' wide' : ''}" role="dialog" aria-label="${esc(title)}">
      <div class="md-h"><h3>${esc(title)}</h3><button class="x" aria-label="Cerrar">${Ic('x')}</button></div><div class="md-b">${html}</div></div>`;
    LAYER.appendChild(wrap);
    const close = () => wrap.remove();
    $('.scrim', wrap).onclick = close;
    $('.x', wrap).onclick = close;
    return { el: $('.md-b', wrap), close, wrap };
  }

  function csvDescargar(nombre, head, rows) {
    const q = v => { const s = String(v ?? ''); return /[",\n;]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s; };
    const txt = '﻿' + [head, ...rows].map(r => r.map(q).join(',')).join('\r\n');
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([txt], { type: 'text/csv;charset=utf-8' }));
    a.download = nombre.replace(/[^\w\-áéíóúñÁÉÍÓÚÑ ]+/g, '_') + '.csv';
    document.body.appendChild(a);
    a.click();
    setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 500);
  }

  function leerArchivoTexto(input, cb) {
    const f = input.files[0];
    if (!f) return;
    const r = new FileReader();
    r.onload = () => cb(String(r.result).replace(/^﻿/, ''));
    r.readAsText(f, 'UTF-8');
  }

  function validarArchivo(f) {
    if (f.size > 10 * 1024 * 1024) return 'El archivo supera 10 MB.';
    if (!/\.(pdf|jpe?g|png)$/i.test(f.name)) return 'Formato no permitido. Usa PDF, JPG o PNG.';
    return '';
  }

  function zonasCtx() { return CTX.regiones.flatMap(r => r.zonas.map(z => Object.assign({ region: r.nombre, regionId: r.id }, z))); }
  function zonaSelect(id, val, extra = '') {
    return `<select id="${id}" ${extra}>${CTX.regiones.map(r => r.zonas.length ? `<optgroup label="${esc(r.nombre)}">${r.zonas.map(z => `<option value="${z.id}" ${+val === z.id ? 'selected' : ''}>${esc(r.nombre)} · ${esc(z.nombre)}</option>`).join('')}</optgroup>` : '').join('')}</select>`;
  }

  /* ------------------------------------------------------------------
     Barra superior y arranque
     ------------------------------------------------------------------ */
  function pintarTopbar() {
    const per = CTX.periodo;
    let h = '';
    if (CTX.estados.length > 1) {
      h += `<label for="estSel">Estado <select id="estSel">${CTX.estados.map(e => `<option ${e === CTX.estado ? 'selected' : ''}>${esc(e)}</option>`).join('')}</select></label>`;
    }
    h += `<span class="pchip ${per.k === 'abierto' ? '' : per.k === 'sust' ? 'warn' : 'bad'}" title="${esc(per.msg)}">${per.k === 'abierto' ? 'Registro abierto' : per.k === 'sust' ? 'Solo sustituciones' : 'Periodo cerrado'}</span>`;
    h += `<span class="muted">${esc(CTX.rolLabel)} · hoy ${fd(CTX.hoy)}</span>`;
    TOP.innerHTML = h;
    const s = $('#estSel', TOP);
    if (s) s.onchange = () => {
      sessionStorage.setItem('rep_estado', s.value);
      const q = new URLSearchParams(location.search);
      q.set('estado', s.value);
      q.delete('u'); q.delete('zona'); q.delete('sec');
      location.search = q.toString();
    };
  }

  async function iniciar() {
    try {
      CTX = await api('contexto');
      if (CTX.estados.length > 1) sessionStorage.setItem('rep_estado', CTX.estado);
      pintarTopbar();
      const V = VISTAS[PAGE];
      if (!V) { VIEW.innerHTML = `<div class="page">${banner('bad', 'Pantalla no encontrada.')}</div>`; return; }
      await V();
    } catch (e) {
      VIEW.innerHTML = `<div class="page">${banner('bad', esc(e.message))}</div>`;
    }
  }
  document.addEventListener('keydown', e => { if (e.key === 'Escape') { const w = LAYER.lastElementChild; if (w && !w.classList.contains('toast')) w.remove(); } });

  /* ==================================================================
     FICHA DE PERSONA (cajón lateral)
     ================================================================== */
  async function abrirFicha(id, alCambiar) {
    const wrap = document.createElement('div');
    wrap.innerHTML = `<div class="scrim"></div><aside class="drawer" role="dialog" aria-label="Ficha">${loading()}</aside>`;
    LAYER.appendChild(wrap);
    const close = () => { wrap.remove(); };
    $('.scrim', wrap).onclick = close;
    const dr = $('.drawer', wrap);
    let tab = 'datos', curpVisible = null, askBaja = false;
    let d;
    const recargar = async (cambio) => {
      try { d = await api('persona', { id }); } catch (e) { dr.innerHTML = `<div class="dr-b">${banner('bad', esc(e.message))}</div>`; return; }
      if (cambio && alCambiar) alCambiar();
      pintar();
    };
    const accion = async (fn) => { try { const r = await fn(); if (r && r.msg) toast(r.msg); await recargar(true); } catch (e) { fallo(e); } };
    function pintar() {
      const p = d.p, pu = d.puede, per = pu.periodo;
      const cap = pu.capturar && p.vigente;
      dr.innerHTML = `
      <div class="dr-h"><div style="display:flex;justify-content:space-between;gap:10px"><div><div class="eyebrow">${p.tipo === 'CONSEJERO' ? 'Consejero' : p.tipo === 'RG' ? 'Representante general' : 'Representante de casilla'}</div><h2>${esc(p.nombre)}</h2></div><button class="x" aria-label="Cerrar">${Ic('x')}</button></div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">${estChip(p.estatus)}<span class="tag">${esc(p.pos || 'Sin posición asignada')}</span></div>
        <div class="tabs">${[['datos', 'Datos'], ['asig', 'Asignación'], ['exp', 'Expediente'], ['hist', 'Historial']].map(([k, l]) => `<button data-tab="${k}" class="${tab === k ? 'on' : ''}">${l}</button>`).join('')}</div></div>
      <div class="dr-b">
        ${tab === 'datos' ? `<dl class="dl">
          <dt>Identificador</dt><dd class="mono">${esc(curpVisible || p.curp)} ${!curpVisible && pu.verid ? `<button class="btn link sm" data-act="vercurp">${Ic('eye')}Ver completo</button>` : ''}</dd>
          <dt>Teléfono</dt><dd>${esc(String(p.tel).replace(/(\d{3})(\d{3})(\d{4})/, '$1 $2 $3'))}</dd>
          <dt>Correo</dt><dd>${esc(p.email || 'No capturado')}</dd>
          <dt>Sección de domicilio</dt><dd>${esc(p.secDom)}</dd>
          <dt>Región</dt><dd>${esc(p.region)}</dd>
          <dt>Zona responsable</dt><dd>${p.zona ? esc(p.zona + (p.zonaCoord ? ' · ' + p.zonaCoord : '')) : 'Coordinación regional'}</dd>
          <dt>Folio de acuse</dt><dd>${esc(p.folio || 'Sin registro')}</dd>
          <dt>Alta</dt><dd>${fd(p.alta)}</dd>
          ${p.baja ? `<dt>Baja</dt><dd>${fd(p.baja)} · ${esc(p.motivoBaja || '')}</dd>` : ''}</dl>` : ''}
        ${tab === 'asig' ? `<dl class="dl"><dt>Tipo</dt><dd>${tipoL(p.tipo)}</dd><dt>Posición</dt><dd>${esc(p.pos || 'Sin posición asignada')}</dd>
          <dt>Cuenta en</dt><dd>${p.zona ? esc(p.zona + ', región ' + p.region) : esc('Región ' + p.region)} (RN-10)</dd></dl>
          ${!p.pos && p.vigente ? banner('warn', 'La persona cuenta en reclutamiento pero no cubre ninguna posición. Asígnala desde <a href="casillas.php">Secciones y casillas</a>.') : ''}
          ${p.pos && cap && per.k !== 'cerrado' ? `<div><button class="btn" data-act="liberar">Liberar posición</button></div>` : ''}
          ${banner('info', 'Compatibilidad: una persona ocupa a lo más una posición y no puede ser RG y RC a la vez (RN-12).')}` : ''}
        ${tab === 'exp' ? `<div>${d.docs.map((x, i) => `<div class="docrow"><div class="dn"><b>${x.id ? `<a class="doclink" target="_blank" rel="noopener" href="${API}?action=doc_ver&id=${x.id}">${esc(x.n)}</a>` : esc(x.n)}</b><small>${x.m ? 'Motivo: ' + esc(x.m) : esc(x.file || 'Sin cargar')}</small></div>
            <div style="display:flex;gap:8px;align-items:center">${docChip(x.e)}
            ${cap && ['pendiente', 'observado'].includes(x.e) || (cap && x.e === 'cargado' && ['RECLUTADO', 'OBSERVADO'].includes(p.estatus)) ? `<label class="btn sm file">${Ic('upload')}${x.e === 'pendiente' ? 'Cargar' : 'Reemplazar'}<input type="file" accept=".pdf,.jpg,.jpeg,.png" data-doc="${i}"></label>` : ''}</div></div>`).join('')}</div>
          ${pu.revisar && p.estatus === 'EN_REVISION' ? `<a class="btn" href="expedientes.php?id=${p.id}">Abrir en revisión de expedientes</a>` : ''}` : ''}
        ${tab === 'hist' ? `<div class="hist">${d.hist.map(h => `<div><b>${esc((CTX.estatus[h.a] || [h.a])[0])}</b>${h.o ? ' · ' + esc(h.o) : ''}<small>${fd(h.f)} ${esc(String(h.f).slice(11, 16))} · ${esc(h.u || '')}</small></div>`).join('')}</div>` : ''}
        ${askBaja ? `<div class="card" style="box-shadow:none"><div class="field"><label for="mb">Motivo de la baja</label><select id="mb"><option value="">Selecciona un motivo</option><option>Renuncia voluntaria</option><option>Cambio de domicilio</option><option>Incompatibilidad de funciones</option><option>No localizable</option><option>Fallecimiento</option></select></div>
          <p class="muted">${p.pos ? 'La posición queda vacante y se reporta como faltante hoy. ' : ''}El historial se conserva.</p><div style="display:flex;gap:8px;justify-content:flex-end"><button class="btn" data-act="nobaja">Cancelar</button><button class="btn danger" data-act="baja">Confirmar baja</button></div></div>` : ''}
      </div>
      <div class="dr-f">
        ${cap && ['RECLUTADO', 'OBSERVADO'].includes(p.estatus) ? `<button class="btn primary" data-act="enviar">Enviar a revisión</button>` : ''}
        ${pu.registro && p.vigente && p.estatus === 'REGISTRADO' ? `<button class="btn primary" data-act="acreditar">Confirmar acreditación</button>` : ''}
        ${pu.registro && p.vigente && p.estatus === 'COMPLETO' ? `<a class="btn" href="registro.php">Ir a registro con acuse</a>` : ''}
        ${cap && per.k !== 'cerrado' && !askBaja ? `<button class="btn danger" data-act="pedirbaja">Dar de baja</button>` : ''}
        <button class="btn" data-act="cerrar">Cerrar</button>
      </div>`;
      $('.x', dr).onclick = close;
      $$('[data-tab]', dr).forEach(b => b.onclick = () => { tab = b.dataset.tab; pintar(); });
      $$('[data-act]', dr).forEach(b => b.onclick = () => {
        const a = b.dataset.act;
        if (a === 'cerrar') return close();
        if (a === 'vercurp') return api('ver_curp', {}, { id }).then(r => { curpVisible = r.curp; pintar(); }).catch(fallo);
        if (a === 'enviar') return accion(() => api('enviar_revision', {}, { id }));
        if (a === 'acreditar') return accion(() => api('acreditar', {}, { ids: [id] }));
        if (a === 'liberar') return accion(() => api('liberar', {}, { id }));
        if (a === 'pedirbaja') { askBaja = true; return pintar(); }
        if (a === 'nobaja') { askBaja = false; return pintar(); }
        if (a === 'baja') { const m = $('#mb', dr).value; if (!m) return toast('Indica el motivo de la baja.', 'bad'); askBaja = false; return accion(() => api('baja', {}, { id, motivo: m })); }
      });
      $$('input[data-doc]', dr).forEach(inp => inp.onchange = () => {
        const f = inp.files[0];
        if (!f) return;
        const err = validarArchivo(f);
        if (err) return toast(err, 'bad');
        const fdta = new FormData();
        fdta.append('id', id);
        fdta.append('tipo_documento', d.docs[+inp.dataset.doc].n);
        fdta.append('archivo', f);
        accion(() => api('doc_subir', {}, fdta));
      });
    }
    await recargar(false);
    return close;
  }
  const DOC_E = { pendiente: ['Sin cargar', ''], cargado: ['Cargado', 'info'], revision: ['En revisión', 'warn'], aprobado: ['Aprobado', 'ok'], observado: ['Observado', 'bad'] };
  const docChip = e => `<span class="chip ${DOC_E[e][1]}">${DOC_E[e][0]}</span>`;

  /* ==================================================================
     P-01..P-04  TABLERO
     ================================================================== */
  const TB = { u: QS.get('u') || '', corte: '' };
  async function vTablero() {
    VIEW.innerHTML = `<div class="page">${loading()}</div>`;
    let d;
    try { d = await api('tablero', { u: TB.u, corte: TB.corte }); } catch (e) { VIEW.innerHTML = `<div class="page">${banner('bad', esc(e.message))}</div>`; return; }
    TB.u = d.u;
    const pr = CTX.proceso, t = d.t, u = d.u;
    const fechas = [['Hoy', CTX.hoy, 'Fecha del sistema'], ['Cierre de registro', pr.cierreRegistro], ['Cierre de sustituciones', pr.cierreSust], ['Jornada electoral', pr.jornada]];
    const cerrados = d.cortes.filter(c => c.cerrado);
    const nivelHijo = u === 'circ' ? 'estado' : u === 'estado' ? 'región' : 'zona';
    const m = d.meta, r = d.real;

    // Avance frente a la ruta
    const avance = `<section class="card">
      <div class="card-h"><h3>Avance ${u === 'estado' ? 'estatal' : u === 'circ' ? 'de la circunscripción' : u.startsWith('r:') ? 'de la región' : 'de la zona'} frente a la ruta</h3><div class="acts"><span class="muted">Corte al ${fd(t)}</span>${can('export') ? `<button class="btn sm" data-x="avance">${Ic('download')}Exportar</button>` : ''}</div></div>
      ${d.etapas.map(s => `<div class="stage">
        <div class="stage-h"><div><b>${esc(s.l)}</b><small>${esc(s.sub)}</small></div><div class="num"><b>${N(s.real)}</b> de ${N(s.meta)}</div></div>
        ${bar(s.meta ? s.real / s.meta * 100 : 0, s.e, s.k)}
        <div class="stage-f"><span>Esperado a la fecha: ${P(s.e)} · ${N(s.ex)} personas</span>${s.ex > s.real ? `<span class="tag">Faltan ${N(s.ex - s.real)}</span>` : ''}${chip(s.sem)}</div></div>`).join('')}
      <div class="legend"><span><span class="mk"></span>Avance esperado a la fecha</span>
        ${d.hijos.length ? chip('ok', d.semCount.ok + ' En ruta') + chip('warn', d.semCount.warn + ' Atención') + chip('bad', d.semCount.bad + ' Rezago') + chip('idle', d.semCount.idle + ' Arranque') : ''}</div></section>`;

    // Tarjeta de metas
    let mt = 'Metas', note = '', rows = [];
    const reg = CTX.slots.filter(s => s[0] === 'P').length, sup = CTX.slots.length - reg;
    if (u === 'estado' || u === 'circ') {
      mt = u === 'estado' ? 'Metas estatales' : 'Metas de la circunscripción';
      note = u === 'estado' ? d.metaFuente : d.hijos.length + ' estados';
      rows = [['Representantes en casilla (RG + RC)', N(m.p)], [`RC (${reg + sup} por casilla)`, N(m.rc)], ['RG requeridos', N(m.rg)], ['Casillas en catálogo', N(m.cas)], ['Posiciones en consejos', N(m.con)],
        u === 'estado' ? ['Regiones · municipios', `${d.regiones} · ${d.municipios}`] : ['Estados', d.hijos.length]];
    } else if (u.startsWith('r:')) {
      mt = 'Metas de la región'; note = m.publicada ? 'Publicada' : 'Calculada de posiciones';
      rows = [['Meta RG + RC', N(m.p)], [`RC (${reg + sup} por casilla)`, N(m.rc)], ['RG requeridos', N(m.rg)], ['Consejeros', N(m.con)], ['Zonas', d.hijos.length], ['Meta distribuida a zonas', N(d.hijos.reduce((a, k) => a + k.meta.p, 0))]];
    } else {
      mt = 'Metas de la zona'; note = m.publicada ? 'Asignada por la región' : 'Calculada de posiciones';
      rows = [['Meta RG + RC', N(m.p)], [`RC (${reg + sup} por casilla)`, N(m.rc)], ['RG requeridos', N(m.rg)], ['Casillas', N(m.cas)], ['RC asignados a casilla', N(d.ficha ? d.ficha.covRC : 0)]];
    }
    const metasCard = `<section class="card"><div class="card-h"><h3>${mt}</h3><span class="muted">${esc(note)}</span></div><div class="kv">${rows.map(([a, b]) => `<div><span>${esc(a)}</span><b>${esc(b)}</b></div>`).join('')}</div></section>`;
    const alertas = `<section class="card"><div class="card-h"><h3>Alertas</h3><span class="muted">${d.alertas.length} activas</span></div>
      ${d.alertas.length ? `<div class="alerts">${d.alertas.slice(0, 7).map(([k, a, b]) => `<div class="alert"><span class="dot ${k}"></span><div>${esc(a)}<small>${esc(b)}</small></div></div>`).join('')}</div>` : '<p class="muted">Sin alertas para este corte.</p>'}</section>`;

    // Ficha
    let ficha = '';
    if (d.ficha) {
      const f = d.ficha, isR = f.tipo === 'region';
      ficha = `<div class="card"><div class="ficha"><div>
        <div class="eyebrow">${isR ? 'Región' : 'Región ' + esc(f.region)}</div><h2>${esc(f.nombre)}</h2>
        <p><b>${isR ? 'Coordinador regional' : 'Coordinador de zona'}:</b> ${esc(f.coord)}</p>
        <p>${isR ? `${f.municipios.length} ${f.integracion === 'municipios' ? (f.municipios.length === 1 ? 'municipio' : 'municipios') : 'unidades (' + esc(f.integracion.replace('_', ' ')) + ')'} · ${f.zonas} zonas · ${N(f.secciones)} secciones` : `${N(f.secciones)} secciones · ${N(f.ln)} de lista nominal`}</p>
        ${f.distritos.length ? `<div class="chips">${f.distritos.map(x => `<span class="tag">${esc(x)}</span>`).join('')}</div>` : ''}
      </div><div>${chip(d.sem)}</div></div>
      <div class="kpis" style="margin-top:14px">
        <div class="kpi"><small>Casillas</small><b>${N(m.cas)}</b><span>${P(f.casPctPadre)} ${isR ? 'del estado' : 'de la región'}</span></div>
        <div class="kpi"><small>RC · RG</small><b>${N(m.rc)} · ${N(m.rg)}</b><span>${N(m.p)} meta total</span></div>
        ${isR ? `<div class="kpi"><small>Consejeros</small><b>${N(m.con)}</b><span>representaciones en consejos</span></div>` : `<div class="kpi"><small>Secciones</small><b>${N(f.secciones)}</b><span>${f.secciones ? (m.cas / f.secciones).toFixed(1) : 0} casillas en promedio</span></div>`}
        <div class="kpi"><small>Cobertura RC</small><b>${P(m.rc ? f.covRC / m.rc * 100 : 0)}</b><span>${N(f.covRC)} de ${N(m.rc)} posiciones</span></div>
      </div></div>`;
    }

    // Tabla de hijos o de secciones
    let tabla = '';
    if (d.secciones) {
      tabla = `<section class="card"><div class="card-h"><h3>Secciones y cobertura de casillas</h3><div class="acts"><span class="muted">Posiciones de RC por casilla: ${CTX.slots.length}</span>${can('export') ? `<button class="btn sm" data-x="secciones">${Ic('download')}Exportar</button>` : ''}</div></div>
        ${d.secciones.length ? `<div class="tw"><table class="tbl"><thead><tr><th>Sección</th><th class="n">Lista nominal</th><th class="n">Casillas</th><th class="n">Posiciones</th><th class="n">Cubiertas</th><th class="n">Faltantes</th><th>Cobertura</th><th></th></tr></thead><tbody>
        ${d.secciones.map(s => `<tr class="click" data-sec="${esc(s.seccion)}"><td><b>Sección ${esc(s.seccion)}</b><span class="sub">${s.casillas.length ? esc(s.casillas.join(' · ')) : 'Sin casillas en catálogo'}</span></td><td class="n">${N(s.ln)}</td><td class="n">${s.casillas.length}</td><td class="n">${s.req}</td><td class="n">${s.cub}</td>
          <td class="n">${s.req - s.cub > 0 ? `<b style="color:var(--bad)">${s.req - s.cub}</b>` : '0'}</td><td style="min-width:120px">${bar(s.req ? s.cub / s.req * 100 : 0, null, 'rec', true)}</td><td><span class="btn link sm">Ver casillas</span></td></tr>`).join('')}</tbody></table></div>` : '<div class="empty">La zona no tiene secciones asignadas en Estructura.</div>'}</section>`;
    } else if (d.hijos.length) {
      const colsK = d.etapas.filter(s => !(u.startsWith('r:') && s.k === 'con'));
      const maxMeta = Math.max(1, ...d.hijos.map(k => k.meta.p));
      tabla = `<section class="card"><div class="card-h"><h3>Semáforo por ${nivelHijo}</h3><div class="acts"><span class="muted">Selecciona una fila para ver su detalle</span>${can('export') ? `<button class="btn sm" data-x="hijos">${Ic('download')}Exportar</button>` : ''}</div></div>
        <div class="tw"><table class="tbl"><thead><tr><th>${nivelHijo[0].toUpperCase() + nivelHijo.slice(1)} y coordinador</th><th>Meta RG + RC</th>${colsK.map(s => `<th>${esc(s.l.split(' ')[0])}</th>`).join('')}<th>Estado</th></tr></thead><tbody>
        ${d.hijos.map((k, i) => `<tr class="click" data-kid="${i}"><td><b>${esc(k.nombre)}</b><span class="sub">${esc(k.coord)}</span>${k.real.rec === 0 && k.real.con === 0 ? '<span class="warnt">Sin captura</span>' : ''}</td>
          <td><div class="mini"><span><b>${N(k.meta.p)}</b></span><span class="sub">${N(k.meta.cas)} casillas</span>${bar(k.meta.p / maxMeta * 100, null, 'exp', true)}</div></td>
          ${colsK.map(s => { const v = k.pct[s.k]; return `<td><div class="mini"><span>${v == null ? '—' : P(v)}</span>${bar(v || 0, s.e, s.k, true)}</div></td>`; }).join('')}
          <td>${chip(k.sem)}</td></tr>`).join('')}</tbody>
        <tfoot><tr><td>${u === 'estado' ? 'Total ' + esc(CTX.estado) : 'Total ' + esc(d.nombre)}</td><td>${N(m.p)}<span class="sub">${N(m.cas)} casillas</span></td>${colsK.map(s => `<td>${s.meta ? P(s.real / s.meta * 100) : '—'}</td>`).join('')}<td>${u === 'circ' ? '' : chip(d.sem)}</td></tr></tfoot></table></div></section>`;
    }

    const rutaU = `<section class="card"><div class="card-h"><h3>Ruta de metas ${u === 'estado' ? 'estatal' : u.startsWith('r:') ? 'de la región' : u.startsWith('z:') ? 'de la zona' : ''}</h3><span class="muted">Acumulado esperado en personas y porcentaje</span></div>
      <div class="tw"><table class="tbl"><thead><tr><th>Corte</th>${d.etapas.map(s => `<th class="n">${esc(s.l.split(' ')[0])}</th>`).join('')}</tr></thead><tbody>
      ${(() => { const nxt = d.cortes.find(c => c.fecha > t); return d.cortes.map(c => `<tr class="${c === nxt ? 'hl' : ''}"><td style="white-space:nowrap">${fd(c.fecha)}<span class="sub">${c === nxt ? 'Próximo corte' : c.cerrado ? 'Cerrado' : ''}</span></td>
        ${d.etapas.map(s => `<td class="n">${N(Math.round(s.meta * c[s.k] / 100))}<span class="sub">${+c[s.k]}%</span></td>`).join('')}</tr>`).join(''); })()}</tbody></table></div></section>`;

    VIEW.innerHTML = `
      <header class="band"><div class="band-in">
        <div class="eyebrow">Secretaría Electoral · ${u === 'circ' ? 'Circunscripción' : 'Equipo ' + esc(CTX.estado)}</div>
        <div class="band-top"><div><h1>Tablero de seguimiento de RG y RC</h1><p>Ruta de cumplimiento del ${fd(pr.inicio)} al cierre de registro del ${fd(pr.cierreRegistro)}.</p></div>
          ${u !== 'circ' ? `<label class="corte" for="corteSel">Corte al<select id="corteSel"><option value="">Hoy · ${fd(CTX.hoy)}</option>${cerrados.map(c => `<option value="${c.fecha}" ${TB.corte === c.fecha ? 'selected' : ''}>${fd(c.fecha)} · corte cerrado</option>`).join('')}</select></label>` : ''}</div>
        <div class="dates">${fechas.map(([l, f, s]) => { const x = dn(f) - dn(CTX.hoy); return `<div class="date"><small>${l}</small><b>${fd(f)}</b><span>${s || (x > 0 ? `Faltan ${x} días` : x === 0 ? 'Hoy' : 'Concluido')}</span></div>`; }).join('')}</div>
        <nav class="crumbs" aria-label="Nivel territorial">${d.crumbs.map((c, i) => `${i ? '<span class="sep">›</span>' : ''}<button data-u="${esc(c.u)}" class="${i === d.crumbs.length - 1 ? 'on' : ''}">${esc(c.nombre)}</button>`).join('')}</nav>
      </div></header>
      <div class="page">
        ${d.corte ? banner('info', `Estás viendo el <b>corte cerrado del ${fd(t)}</b>. Metas y avances salen de la foto guardada al cerrar el corte y no cambian con capturas posteriores (RN-08).`) : ''}
        ${d.tabs ? `<div class="pills">${d.tabs.map(x => `<button class="pill${x.u === u ? ' on' : ''}" data-u="${x.u}"><span class="dot ${x.sem}"></span>${esc(x.nombre)}</button>`).join('')}</div>` : ''}
        ${ficha}
        <div class="g2">${avance}<div class="col">${metasCard}${alertas}</div></div>
        ${tabla}
        <div class="g2e">${curva(d, { m })}${rutaU}</div>
      </div>`;

    const sel = $('#corteSel');
    if (sel) sel.onchange = () => { TB.corte = sel.value; vTablero(); };
    $$('[data-u]', VIEW).forEach(b => b.onclick = () => { TB.u = b.dataset.u; vTablero(); window.scrollTo(0, 0); });
    $$('[data-kid]', VIEW).forEach(tr => tr.onclick = () => {
      const k = d.hijos[+tr.dataset.kid];
      if (u === 'circ') { sessionStorage.setItem('rep_estado', k.estado); location.href = 'tablero.php?estado=' + encodeURIComponent(k.estado) + '&u=estado'; return; }
      TB.u = k.u; vTablero(); window.scrollTo(0, 0);
    });
    $$('[data-sec]', VIEW).forEach(tr => tr.onclick = () => { location.href = `casillas.php?zona=${u.slice(2)}&sec=${encodeURIComponent(tr.dataset.sec)}`; });
    $$('[data-x]', VIEW).forEach(b => b.onclick = () => {
      const x = b.dataset.x;
      if (x === 'avance') csvDescargar('Avance ' + d.nombre, ['Etapa', 'Real', 'Meta', 'Esperado %', 'Exigible', 'Faltante a la fecha', 'Semáforo'], d.etapas.map(s => [s.l, s.real, s.meta, s.e, s.ex, Math.max(0, s.ex - s.real), CTX.sem[s.sem]]));
      if (x === 'hijos') csvDescargar('Semáforo ' + d.nombre, [nivelHijo, 'Coordinador', 'Meta RG+RC', 'Casillas', 'Reclutamiento %', 'Expedientes %', 'Registro %', 'Consejeros %', 'Estado'], d.hijos.map(k => [k.nombre, k.coord, k.meta.p, k.meta.cas, k.pct.rec, k.pct.exp, k.pct.reg, k.pct.con ?? '', CTX.sem[k.sem]]));
      if (x === 'secciones') csvDescargar('Secciones ' + d.nombre, ['Sección', 'Lista nominal', 'Casillas', 'Posiciones RC', 'Cubiertas', 'Faltantes', 'Cobertura %'], d.secciones.map(s => [s.seccion, s.ln, s.casillas.length, s.req, s.cub, s.req - s.cub, s.req ? (s.cub / s.req * 100).toFixed(1) : 0]));
    });
  }

  /** Curva planeada (cortes) y avance real (puntos) */
  function curva(d, o = {}) {
    const cs = o.cortes || d.cortes, pr = CTX.proceso;
    const W = 720, Hh = 280, L = 42, Rr = 18, T = 18, B = 34;
    const x0 = dn(pr.inicio), x1 = Math.max(dn(pr.cierreRegistro) + 7, ...cs.map(c => dn(c.fecha) + 7));
    const X = v => L + (v - x0) / (x1 - x0) * (W - L - Rr), Y = v => T + (1 - clamp(v, 0, 100) / 100) * (Hh - T - B);
    const stg = CTX.etapas.filter(s => s.k !== 'con' || (o.m ? o.m.con > 0 : true));
    const path = pts => 'M' + pts.map(p => X(p[0]).toFixed(1) + ' ' + Y(p[1]).toFixed(1)).join('L');
    const last = cs[cs.length - 1] || { rec: 0, exp: 0, reg: 0, con: 0 };
    const planned = s => path([[x0, 0], ...cs.filter(c => c.fecha).map(c => [dn(c.fecha), +c[s.k]]), [x1, +last[s.k]]]);
    const months = [];
    const d0 = new Date(x0 * DAY);
    for (let y = d0.getUTCFullYear(), mo = d0.getUTCMonth(); ;) { const v = dn(`${y}-${String(mo + 1).padStart(2, '0')}-01`); if (v > x1) break; if (v >= x0) months.push([v, MES[mo]]); mo++; if (mo === 12) { mo = 0; y++; } }
    const IR = dn(pr.inicioRegistro), CR = dn(pr.cierreRegistro), hoy = d && d.t ? dn(d.t) : dn(CTX.hoy);
    const real = o.cortes ? null : stg.map(s => [s.k, (d.curva || []).filter(p => p[s.k] != null).map(p => [dn(p.f), p[s.k]])]);
    return `<section class="card"><div class="card-h"><h3>${esc(o.title || 'Curva de ruta planeada')}</h3><span class="muted">${esc(o.sub || 'Porcentaje acumulado de la meta')}</span></div>
      <div class="tw"><svg class="chart" viewBox="0 0 ${W} ${Hh}" role="img" aria-label="Curva planeada contra avance real por etapa">
      <g class="grid">${[0, 25, 50, 75, 100].map(v => `<line x1="${L}" x2="${W - Rr}" y1="${Y(v)}" y2="${Y(v)}"/>`).join('')}</g>
      <g class="ax">${[0, 25, 50, 75, 100].map(v => `<text x="${L - 8}" y="${Y(v) + 4}" text-anchor="end">${v}%</text>`).join('')}${months.map(([v, l]) => `<text x="${X(v)}" y="${Hh - 12}" text-anchor="middle">${l}</text>`).join('')}</g>
      <line class="mkl" x1="${X(IR)}" x2="${X(IR)}" y1="${T}" y2="${Hh - B}"/><text class="mkt" x="${X(IR) - 5}" y="${T + 10}" text-anchor="end">Inicio de registro</text>
      <line class="mkl" x1="${X(CR)}" x2="${X(CR)}" y1="${T}" y2="${Hh - B}"/><text class="mkt" x="${X(CR) - 5}" y="${T + 24}" text-anchor="end">Cierre</text>
      ${stg.map(s => `<path class="pl s-${s.k}" d="${planned(s)}"/>`).join('')}
      ${real ? real.filter(([, pts]) => pts.length).map(([k, pts]) => `<g><path class="rl s-${k}" d="${path(pts)}"/>${pts.map((p, i) => `<circle class="f-${k}" cx="${X(p[0])}" cy="${Y(p[1])}" r="${i === pts.length - 1 ? 4.5 : 2.6}"><title>${fd(isoOf(p[0]))}: ${P(p[1])}</title></circle>`).join('')}</g>`).join('') : ''}
      ${!o.cortes ? `<line class="hoy" x1="${X(hoy)}" x2="${X(hoy)}" y1="${T}" y2="${Hh - B}"/><text class="hoyt" x="${X(hoy) + 5}" y="${Hh - B - 6}">${d.corte ? 'Corte' : 'Hoy'}</text>` : ''}
      </svg></div>
      <div class="lg">${stg.map(s => `<span><i style="background:var(--c-${s.k})"></i>${esc(s.l)}</span>`).join('')}<span class="muted">${o.cortes ? 'Vista previa de la ruta en edición.' : 'Las líneas son la ruta planeada; los puntos, el avance real capturado.'}</span></div></section>`;
  }

  /* ==================================================================
     P-05  SECCIONES Y CASILLAS
     ================================================================== */
  const CS = { zona: +QS.get('zona') || 0, sec: QS.get('sec') || '', q: '', rgAll: false };
  async function vCasillas() {
    if (!CTX.regiones.some(r => r.zonas.length)) { VIEW.innerHTML = `<div class="page">${banner('warn', 'No hay zonas en tu ámbito. Crea zonas y asígnales secciones en Estructura / Zonas.')}</div>`; return; }
    if (!zonasCtx().some(z => z.id === CS.zona)) CS.zona = zonasCtx()[0].id;
    VIEW.innerHTML = `<div class="page">${loading()}</div>`;
    let d;
    try { d = await api('casillas_zona', { zona: CS.zona }); } catch (e) { VIEW.innerHTML = `<div class="page">${banner('bad', esc(e.message))}</div>`; return; }
    const z = d.zona, nS = d.slots.length;
    if (!d.secciones.find(s => s.seccion === CS.sec)) CS.sec = d.secciones[0] ? d.secciones[0].seccion : '';
    const occ = d.ocupadas;
    const covSec = s => s.casillas.reduce((a, c) => a + d.slots.filter(sl => occ['C' + c.id + ':' + sl.k]).length, 0);
    const totCas = d.secciones.reduce((a, s) => a + s.casillas.length, 0);
    const rcOcc = Object.keys(occ).filter(k => k[0] === 'C').length, rgOcc = Object.keys(occ).filter(k => k[0] === 'Z').length;
    const s = d.secciones.find(x => x.seccion === CS.sec);
    const slot = (k, label) => {
      const o = occ[k];
      if (o) return `<div class="slot occ" role="button" tabindex="0" data-p="${o.id}"><div><small>${esc(label)}</small><div class="nm">${esc(o.nombre)}</div></div>${estChip(o.estatus)}</div>`;
      return `<div class="slot"><div><small>${esc(label)}</small><div class="vac">Vacante</div></div>${d.editable ? `<button class="btn sm" data-asig="${k}">Asignar</button>` : ''}</div>`;
    };
    const rgKeys = Array.from({ length: z.rg }, (_, i) => `Z${z.id}:RG${i + 1}`);
    const secList = d.secciones.filter(x => !CS.q || x.seccion.includes(CS.q.trim()));
    VIEW.innerHTML = `<div class="page">
      <div class="ph"><div><div class="eyebrow">Nivel operativo</div><h1>Secciones y casillas</h1><p>Cada casilla requiere ${nS} posiciones de RC (${d.slots.map(x => x.l.toLowerCase()).join(', ')}). Asigna personas del padrón o registra a alguien nuevo en una posición vacante.</p></div>
        <div class="field" style="min-width:260px"><label for="zsel">Zona</label>${zonaSelect('zsel', z.id)}</div></div>
      ${d.periodo.k !== 'abierto' ? banner(d.periodo.k === 'cerrado' ? 'bad' : 'warn', esc(d.periodo.msg)) : ''}
      <div class="kpis">
        <div class="kpi"><small>Secciones</small><b>${d.secciones.length}</b><span>${esc(z.nombre)} · ${esc(z.region)}</span></div>
        <div class="kpi"><small>Casillas</small><b>${N(totCas)}</b><span>${N(totCas * nS)} posiciones de RC</span></div>
        <div class="kpi"><small>RC asignados</small><b>${N(rcOcc)}</b><span>${P(totCas ? rcOcc / (totCas * nS) * 100 : 0)} de cobertura</span></div>
        <div class="kpi"><small>Faltantes</small><b>${N(totCas * nS - rcOcc)}</b><span>posiciones de RC vacantes</span></div>
      </div>
      ${!totCas ? banner('warn', 'Esta zona no tiene casillas en el catálogo. ' + (d.catalogo ? 'Agrégalas por sección aquí o impórtalas en <a href="configuracion.php">Configuración</a>.' : 'La coordinación estatal debe cargar el catálogo.')) : ''}
      ${!d.secciones.length ? banner('warn', 'La zona no tiene secciones asignadas.') : `
      <div class="split">
        <div class="card" style="padding:14px">
          <div class="field search" style="margin-bottom:10px">${Ic('search')}<input id="secq" placeholder="Buscar sección" aria-label="Buscar sección" value="${esc(CS.q)}"></div>
          <div class="seclist">${secList.map(x => { const r2 = x.casillas.length * nS, c2 = covSec(x); return `<button class="secitem${x.seccion === CS.sec ? ' on' : ''}" data-s="${esc(x.seccion)}">
            <div class="r1"><b>Sección ${esc(x.seccion)}</b><span class="ln">LN ${N(x.ln)}</span></div>
            <div class="r1"><span class="muted">${x.casillas.length} ${x.casillas.length === 1 ? 'casilla' : 'casillas'}</span><span class="muted">${c2} de ${r2}</span></div>${bar(r2 ? c2 / r2 * 100 : 0, null, 'rec', true)}</button>`; }).join('')}
            ${!secList.length ? '<p class="muted">No hay secciones con ese número en esta zona.</p>' : ''}</div>
        </div>
        <div class="col">
          ${s ? (() => { const req = s.casillas.length * nS, c = covSec(s); return `<div class="card"><div class="card-h"><h3>Sección ${esc(s.seccion)}</h3><div class="acts"><span class="ln">LN ${N(s.ln)}</span><span class="muted">${c} de ${req} posiciones cubiertas</span>${req ? chip(c === req ? 'ok' : c >= req * 0.5 ? 'warn' : 'bad', c === req ? 'Completa' : `Faltan ${req - c}`) : ''}${d.catalogo ? `<button class="btn sm" data-addcas="1">${Ic('plus')}Casilla</button>` : ''}</div></div>
            ${s.casillas.length ? `<div class="cas-grid">${s.casillas.map(ca => { const cc = d.slots.filter(sl => occ['C' + ca.id + ':' + sl.k]).length; return `<div class="cas">
              <div class="cas-h"><b>Casilla ${esc(ca.tipo)}</b><span class="muted">${ca.tipo === 'B' ? 'Básica' : ca.tipo[0] === 'E' ? 'Especial' : ca.tipo[0] === 'X' ? 'Extraordinaria' : 'Contigua'} · ${cc}/${nS}${d.catalogo ? ` <button class="casx" title="Dar de baja la casilla" data-bajacas="${ca.id}">✕</button>` : ''}</span></div>
              ${ca.ubicacion ? `<small class="muted">${esc(ca.ubicacion)}</small>` : ''}
              ${d.slots.map(sl => slot('C' + ca.id + ':' + sl.k, sl.l)).join('')}</div>`; }).join('')}</div>` : '<div class="empty">Sin casillas en el catálogo para esta sección.</div>'}
            ${!d.editable ? `<p class="muted" style="margin-top:12px">${can('capturar') ? esc(d.periodo.msg) : 'Solo la coordinación de zona asigna posiciones. Tu rol puede consultar la cobertura.'}</p>` : ''}</div>`; })() : ''}
          <div class="card"><div class="card-h"><h3>Representantes generales de la zona</h3><span class="muted">${N(rgOcc)} de ${z.rg} asignados · ${z.rg ? 'cada RG atiende un grupo de casillas' : 'se calculan con las casillas del catálogo'}</span></div>
            ${z.rg ? `<div class="cas-grid">${rgKeys.slice(0, CS.rgAll ? 9999 : 8).map((k, i) => slot(k, 'RG ' + (i + 1))).join('')}</div>${rgKeys.length > 8 ? `<button class="btn link" style="margin-top:8px" data-rgall="1">${CS.rgAll ? 'Ver menos' : `Ver los ${rgKeys.length} RG`}</button>` : ''}` : '<div class="empty">Sin posiciones de RG todavía.</div>'}
          </div>
        </div>
      </div>`}</div>`;

    $('#zsel').onchange = e => { CS.zona = +e.target.value; CS.sec = ''; vCasillas(); };
    const sq = $('#secq');
    if (sq) sq.oninput = debounce(() => { CS.q = sq.value; vCasillas().then(() => { const x = $('#secq'); if (x) { x.focus(); x.setSelectionRange(x.value.length, x.value.length); } }); }, 250);
    $$('[data-s]', VIEW).forEach(b => b.onclick = () => { CS.sec = b.dataset.s; vCasillas(); });
    $$('[data-p]', VIEW).forEach(b => b.onclick = () => abrirFicha(+b.dataset.p, vCasillas));
    $$('[data-asig]', VIEW).forEach(b => b.onclick = () => modalAsignar(b.dataset.asig, z.id));
    const rb = $('[data-rgall]', VIEW);
    if (rb) rb.onclick = () => { CS.rgAll = !CS.rgAll; vCasillas(); };
    const ac = $('[data-addcas]', VIEW);
    if (ac) ac.onclick = () => {
      const sug = (() => { const ts = s.casillas.map(c => c.tipo); if (!ts.includes('B')) return 'B'; let i = 1; while (ts.includes('C' + i)) i++; return 'C' + i; })();
      const M = modal('Agregar casilla · Sección ' + s.seccion, `<div class="fgrid"><div class="field"><label for="ct">Tipo</label><input id="ct" value="${sug}" maxlength="3"><span class="hint">B básica · C1, C2… contiguas · E1 especial · X1 extraordinaria</span></div>
        <div class="field"><label for="cu">Ubicación (opcional)</label><input id="cu" maxlength="200"></div></div><div class="md-f" style="padding:0;border:0"><button class="btn" data-c>Cancelar</button><button class="btn primary" data-ok>Agregar</button></div>`);
      $('[data-c]', M.el).onclick = M.close;
      $('[data-ok]', M.el).onclick = () => api('casilla_agregar', {}, { seccion: s.seccion, tipo: $('#ct', M.el).value, ubicacion: $('#cu', M.el).value }).then(r => { toast(r.msg); M.close(); vCasillas(); }).catch(fallo);
    };
    $$('[data-bajacas]', VIEW).forEach(b => b.onclick = () => {
      if (!confirm('¿Dar de baja esta casilla del catálogo? Solo procede si no tiene posiciones ocupadas.')) return;
      api('casilla_baja', {}, { id: +b.dataset.bajacas }).then(r => { toast(r.msg); vCasillas(); }).catch(fallo);
    });
  }

  function modalAsignar(key, zonaId) {
    const M = modal('Asignar posición', loading(), false);
    let q = '';
    const cargar = async () => {
      let d;
      try { d = await api('candidatos', { pos: key, q }); } catch (e) { M.el.innerHTML = banner('bad', esc(e.message)); return; }
      const per = CTX.periodo;
      M.el.innerHTML = `<div class="kv"><div><span>Posición</span><b style="font-size:15px">${esc(d.pos.label)}</b></div></div>
        ${per.k === 'sust' ? banner('warn', esc(per.msg) + ' Esta asignación se registra como sustitución.') : ''}
        <div class="field search">${Ic('search')}<input id="asq" placeholder="Buscar en el padrón" aria-label="Buscar persona" value="${esc(q)}"></div>
        <p class="muted">Personas ${tipoL(d.pos.tipo)} sin posición asignada: ${d.candidatos.length}${d.candidatos.length === 40 ? ' o más' : ''}.</p>
        ${d.candidatos.length ? `<div class="tw"><table class="tbl"><tbody>${d.candidatos.map(p => `<tr><td><b>${esc(p.nombre)}</b><span class="sub mono">${esc(p.curp)}</span></td><td>${estChip(p.estatus)}</td><td class="n"><button class="btn sm primary" data-id="${p.id}">Asignar</button></td></tr>`).join('')}</tbody></table></div>` : '<div class="empty">No hay personas disponibles con ese criterio.</div>'}
        <div class="md-f" style="padding:0;border:0"><button class="btn" data-c>Cancelar</button>${can('capturar') || can('capturar_con') ? `<a class="btn primary" href="registrar.php?pos=${encodeURIComponent(key)}&zona=${zonaId || ''}">${Ic('userplus')}Registrar persona nueva aquí</a>` : ''}</div>`;
      $('[data-c]', M.el).onclick = M.close;
      const inp = $('#asq', M.el);
      inp.oninput = debounce(() => { q = inp.value; cargar().then(() => { const x = $('#asq', M.el); if (x) { x.focus(); x.setSelectionRange(x.value.length, x.value.length); } }); }, 300);
      $$('[data-id]', M.el).forEach(b => b.onclick = () => api('asignar', {}, { id: +b.dataset.id, pos: key }).then(r => { toast(r.msg); M.close(); vCasillas(); }).catch(fallo));
    };
    cargar();
  }

  /* ==================================================================
     P-06  PADRÓN
     ================================================================== */
  const PF = { q: QS.get('q') || '', region: '', zona: '', tipo: '', estatus: '', sinpos: '', page: 0 };
  async function vPadron(soloTabla) {
    if (!soloTabla) {
      const regs = CTX.regiones;
      VIEW.innerHTML = `<div class="page">
        <div class="ph"><div><div class="eyebrow">Registro maestro</div><h1>Padrón de representantes</h1><p>Una persona, un registro. El identificador oficial se muestra enmascarado; consultarlo completo queda en bitácora.</p></div>
          <div style="display:flex;gap:8px;flex-wrap:wrap">
            ${can('capturar') ? `<button class="btn" data-act="carga">${Ic('upload')}Carga masiva</button>` : ''}
            ${can('export') ? `<button class="btn" data-act="exp">${Ic('download')}Exportar</button>` : ''}
            ${can('capturar') || can('capturar_con') ? `<a class="btn primary" href="registrar.php">${Ic('userplus')}Registrar representante</a>` : ''}
          </div></div>
        <div class="card"><div class="filters">
          <div class="field" style="flex:2 1 240px"><label for="pq">Buscar</label><div class="search">${Ic('search')}<input id="pq" placeholder="Nombre, CURP completa, teléfono o folio" value="${esc(PF.q)}"></div></div>
          ${regs.length > 1 ? `<div class="field"><label for="pr">Región</label><select id="pr"><option value="">Todas</option>${regs.map(r => `<option value="${r.id}">${esc(r.nombre)}</option>`).join('')}</select></div>` : ''}
          ${CTX.nivel < 5 ? `<div class="field"><label for="pz">Zona</label><select id="pz"></select></div>` : ''}
          <div class="field"><label for="pt">Tipo</label><select id="pt"><option value="">Todos</option><option value="RC">RC</option><option value="RG">RG</option>${CTX.nivel < 5 ? '<option value="CONSEJERO">Consejero</option>' : ''}</select></div>
          <div class="field"><label for="ps">Posición</label><select id="ps"><option value="">Todas</option><option value="1">Sin posición</option></select></div>
        </div><div class="pills" id="ppills" style="margin-top:14px"></div></div>
        <div class="card" id="ptabla">${loading()}</div></div>`;
      const pz = $('#pz'), pr = $('#pr');
      const llenarZonas = () => { if (!pz) return; const zs = zonasCtx().filter(z => !PF.region || z.regionId === +PF.region); pz.innerHTML = `<option value="">Todas</option>` + zs.map(z => `<option value="${z.id}">${PF.region ? '' : esc(z.region) + ' · '}${esc(z.nombre)}</option>`).join(''); pz.value = PF.zona; };
      llenarZonas();
      if (pr) pr.onchange = () => { PF.region = pr.value; PF.zona = ''; PF.page = 0; llenarZonas(); vPadron(true); };
      if (pz) pz.onchange = () => { PF.zona = pz.value; PF.page = 0; vPadron(true); };
      $('#pt').onchange = e => { PF.tipo = e.target.value; PF.page = 0; vPadron(true); };
      $('#ps').onchange = e => { PF.sinpos = e.target.value; PF.page = 0; vPadron(true); };
      $('#pq').oninput = debounce(e => { PF.q = e.target.value; PF.page = 0; vPadron(true); }, 350);
      const bc = $('[data-act=carga]', VIEW); if (bc) bc.onclick = modalCargaMasiva;
      const be = $('[data-act=exp]', VIEW); if (be) be.onclick = async () => {
        try {
          const d = await api('padron', Object.assign({}, PF, { export: 1 }));
          csvDescargar('Padrón ' + CTX.estado, ['Nombre', 'Identificador', 'Tipo', 'Posición', 'Región', 'Zona', 'Estatus', 'Folio', 'Última actualización'], d.rows.map(p => [p.nombre, p.curp, tipoL(p.tipo), p.pos || 'Sin posición', p.region, p.zona || '', CTX.estatus[p.estatus][0], p.folio || '', p.act]));
        } catch (e) { fallo(e); }
      };
    }
    const box = $('#ptabla');
    let d;
    try { d = await api('padron', PF); } catch (e) { box.innerHTML = banner('bad', esc(e.message)); return; }
    const tot = Object.values(d.counts).reduce((a, b) => a + b, 0);
    $('#ppills').innerHTML = `<button class="pill${!PF.estatus ? ' on' : ''}" data-e="">Todos <b>${N(tot)}</b></button>` + Object.keys(CTX.estatus).filter(e => d.counts[e]).map(e => `<button class="pill${PF.estatus === e ? ' on' : ''}" data-e="${e}">${esc(CTX.estatus[e][0])} <b>${N(d.counts[e])}</b></button>`).join('');
    $$('#ppills [data-e]').forEach(b => b.onclick = () => { PF.estatus = b.dataset.e; PF.page = 0; vPadron(true); });
    const pages = Math.max(1, Math.ceil(d.total / d.per));
    box.innerHTML = `<div class="tw"><table class="tbl"><thead><tr><th>Nombre</th><th>Identificador</th><th>Tipo</th><th>Posición</th><th>Zona</th><th>Estatus</th><th>Actualizado</th></tr></thead><tbody>
      ${d.rows.map(p => `<tr class="click" data-id="${p.id}"><td><b>${esc(p.nombre)}</b></td><td class="mono">${esc(p.curp)}</td><td><span class="tag">${tipoL(p.tipo)}</span></td>
        <td>${p.pos ? esc(p.pos) : p.estatus === 'BAJA' ? '—' : '<span class="warnt">Sin posición</span>'}</td><td>${p.zona ? esc(p.region + ' · ' + p.zona) : esc('Región ' + p.region)}</td><td>${estChip(p.estatus)}</td><td class="num">${fdc(p.act)}</td></tr>`).join('')}</tbody></table></div>
      ${!d.rows.length ? '<div class="empty">No hay registros con estos filtros.</div>' : ''}
      <div class="pager"><span>${N(d.total)} registros · página ${d.page + 1} de ${pages}</span><div style="display:flex;gap:6px"><button class="btn sm" data-pg="-1" ${d.page === 0 ? 'disabled' : ''}>Anterior</button><button class="btn sm" data-pg="1" ${d.page >= pages - 1 ? 'disabled' : ''}>Siguiente</button></div></div>`;
    $$('[data-id]', box).forEach(tr => tr.onclick = () => abrirFicha(+tr.dataset.id, () => vPadron(true)));
    $$('[data-pg]', box).forEach(b => b.onclick = () => { PF.page += +b.dataset.pg; vPadron(true); });
  }

  const PLANTILLA = 'curp,nombre,apellido_paterno,apellido_materno,telefono,seccion,tipo';
  function modalCargaMasiva() {
    const zs = zonasCtx();
    const M = modal('Carga masiva de representantes', `
      <p class="muted">Usa la plantilla oficial (CSV). Cada fila se valida igual que la captura individual; solo se confirman las filas válidas. Máximo 5,000 filas por archivo.</p>
      <div class="fgrid"><div class="field"><label for="cmz">Zona donde se dan de alta</label>${zonaSelect('cmz', zs[0] && zs[0].id)}</div>
        <div class="field"><label>Archivo CSV</label><div style="display:flex;gap:8px;flex-wrap:wrap"><label class="btn sm file">${Ic('upload')}Elegir archivo<input type="file" id="cmf" accept=".csv,.txt"></label><button class="btn sm" data-pl>${Ic('download')}Descargar plantilla</button></div></div></div>
      <div class="field"><label for="cmtxt">Contenido de la plantilla</label><textarea id="cmtxt" rows="8">${PLANTILLA}\n</textarea><span class="hint">Columnas: ${PLANTILLA}. Tipo: RC o RG.</span></div>
      <div id="cmres"></div>
      <div class="md-f" style="padding:0;border:0"><button class="btn" data-c>Cancelar</button><button class="btn" data-v>Validar filas</button><button class="btn primary" data-ok disabled>Confirmar filas válidas</button></div>`, true);
    const txt = $('#cmtxt', M.el), ok = $('[data-ok]', M.el), res = $('#cmres', M.el);
    $('[data-c]', M.el).onclick = M.close;
    $('[data-pl]', M.el).onclick = () => csvDescargar('plantilla_representantes', PLANTILLA.split(','), []);
    $('#cmf', M.el).onchange = e => leerArchivoTexto(e.target, t => { txt.value = t; ok.disabled = true; res.innerHTML = ''; });
    txt.oninput = () => { ok.disabled = true; };
    $('[data-v]', M.el).onclick = async () => {
      res.innerHTML = loading('Validando…');
      try {
        const d = await api('carga_validar', {}, { zona_id: +$('#cmz', M.el).value, csv: txt.value });
        res.innerHTML = `<div class="tw" style="max-height:300px"><table class="tbl"><thead><tr><th>Fila</th><th>Nombre</th><th>Tipo</th><th>Sección</th><th>Resultado</th></tr></thead><tbody>
          ${d.filas.map(r => `<tr><td class="num">${r.n}</td><td>${esc(r.nombre + ' ' + r.ap)}</td><td>${esc(r.tipo)}</td><td>${esc(r.sec)}</td><td>${r.err.length ? `<span style="color:var(--bad);font-weight:600">${esc(r.err.join(' · '))}</span>` : chip('ok', 'Válida')}</td></tr>`).join('')}</tbody></table></div>
          ${banner(d.validas ? 'info' : 'warn', `${d.validas} filas válidas y ${d.errores} con error. Solo se confirman las válidas. ${d.errores ? '<button class="btn link sm" data-errcsv>Descargar errores</button>' : ''}`)}`;
        ok.disabled = !d.validas;
        ok.textContent = `Confirmar ${d.validas} filas válidas`;
        const be = $('[data-errcsv]', res);
        if (be) be.onclick = () => csvDescargar('errores_carga', ['Fila', 'Nombre', 'Apellido', 'Tipo', 'Sección', 'Motivo'], d.filas.filter(r => r.err.length).map(r => [r.n, r.nombre, r.ap, r.tipo, r.sec, r.err.join(' · ')]));
      } catch (e) { res.innerHTML = banner('bad', esc(e.message)); }
    };
    ok.onclick = async () => {
      ok.disabled = true;
      try { const r = await api('carga_confirmar', {}, { zona_id: +$('#cmz', M.el).value, csv: txt.value }); toast(r.msg); M.close(); if (PAGE === 'padron') vPadron(true); }
      catch (e) { fallo(e); ok.disabled = false; }
    };
  }

  /* ==================================================================
     P-07  REGISTRAR REPRESENTANTE
     ================================================================== */
  async function vRegistrar() {
    const esCon = !can('capturar') && can('capturar_con');
    if (!can('capturar') && !can('capturar_con')) { VIEW.innerHTML = `<div class="page">${banner('warn', 'Tu rol no registra representantes. La captura la hace la coordinación de zona (RG y RC) y la regional (consejeros).')}</div>`; return; }
    const zs = zonasCtx();
    const per = CTX.periodo;
    const pre = QS.get('pos') || '';
    const vacio = () => ({ curp: '', nombre: '', ap: '', am: '', tel: '', email: '', secDom: '', tipo: esCon ? 'CONSEJERO' : 'RC', zona_id: zs[0] ? zs[0].id : 0, region_id: CTX.regiones[0] ? CTX.regiones[0].id : 0, pos: '', files: {} });
    const fm = vacio();
    if (pre) {
      fm.pos = pre;
      if (/^Z\d+:RG/.test(pre)) fm.tipo = 'RG';
      if (/^R\d+:CON/.test(pre)) fm.tipo = 'CONSEJERO';
      if (+QS.get('zona')) fm.zona_id = +QS.get('zona');
    }
    let st = 0, touched = {}, srvErr = {}, dup = null, vac = null, enviando = false;
    const docs = () => fm.tipo === 'CONSEJERO' ? CTX.docsCon : CTX.docsRep;
    const errores = () => {
      const e1 = {}, e2 = {};
      if (!RX_CURP.test(fm.curp)) e1.curp = 'El identificador no tiene un formato de CURP válido (18 caracteres).';
      else if (dup && dup.vigente) e1.curp = 'Ya existe una persona con este identificador.';
      if (!RX_NOM.test(fm.nombre.trim())) e1.nombre = 'Captura el nombre (2 a 60 letras, sin números).';
      if (!RX_NOM.test(fm.ap.trim())) e1.ap = 'Captura el apellido paterno.';
      if (fm.am && !RX_NOM.test(fm.am.trim())) e1.am = 'El apellido materno solo admite letras.';
      if (!/^\d{10}$/.test(fm.tel)) e1.tel = 'El teléfono debe tener 10 dígitos.';
      if (fm.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(fm.email)) e1.email = 'Revisa el formato del correo.';
      if (!/^\d{1,5}$/.test(fm.secDom)) e1.secDom = 'Captura la sección de domicilio.';
      if (per.k === 'sust' && !fm.pos) e2.pos = 'Después del cierre de registro solo se dan altas para cubrir una posición vacante.';
      Object.assign(e1, Object.fromEntries(Object.entries(srvErr).filter(([k]) => ['curp', 'nombre', 'ap', 'am', 'tel', 'email', 'secDom'].includes(k))));
      Object.assign(e2, Object.fromEntries(Object.entries(srvErr).filter(([k]) => ['zona', 'pos'].includes(k))));
      return [e1, e2, {}, {}];
    };
    const cargarVacantes = async () => {
      vac = null;
      try { vac = fm.tipo === 'CONSEJERO' ? await api('vacantes', { tipo: 'CONSEJERO', region: fm.region_id }) : await api('vacantes', { tipo: fm.tipo, zona: fm.zona_id }); }
      catch (e) { vac = { total: 0, vacantes: [] }; }
      if (fm.pos && !vac.vacantes.some(v => v.k === fm.pos)) fm.pos = '';
      if (st === 1) pintar();
    };
    const buscarDup = debounce(async () => {
      if (!RX_CURP.test(fm.curp)) { dup = null; return pintar(); }
      try { const r = await api('buscar_curp', {}, { curp: fm.curp }); dup = r.existe ? r : null; } catch (_) { dup = null; }
      pintar();
    }, 350);
    // Actualiza solo el aviso de un campo (repintar todo se comería el clic en «Continuar»)
    const marcar = k => {
      const inpEl = $('#f_' + k, VIEW); if (!inpEl) return;
      const box = inpEl.closest('.field'), e = errores()[st][k];
      let sp = $('.err, .hint', box);
      box.classList.toggle('bad', !!e);
      if (e) { if (!sp || !sp.classList.contains('err')) { if (sp) sp.remove(); sp = document.createElement('span'); sp.className = 'err'; box.appendChild(sp); } sp.textContent = e; }
      else if (sp && sp.classList.contains('err')) sp.remove();
    };
    const F = (k, l, hint, child) => { const errs = errores()[st]; const show = (touched[k] || touched._all) && errs[k]; return `<div class="field${show ? ' bad' : ''}"><label for="f_${k}">${l}</label>${child}${show ? `<span class="err">${esc(errs[k])}</span>` : hint ? `<span class="hint">${hint}</span>` : ''}</div>`; };
    const inp = (k, o = {}) => `<input id="f_${k}" data-k="${k}" value="${esc(fm[k])}" maxlength="${o.max || 60}" inputmode="${o.dig ? 'numeric' : 'text'}" autocomplete="off" ${o.up ? 'data-up="1"' : ''} ${o.dig ? 'data-dig="1"' : ''}>`;
    function pintar() {
      const [e1, e2] = errores();
      const zSel = zs.find(z => z.id === fm.zona_id);
      const stepsL = ['Identificación', 'Representación', 'Documentos', 'Confirmación'];
      const foco = document.activeElement && document.activeElement.id;
      VIEW.innerHTML = `<div class="page">
        <div class="ph"><div><div class="eyebrow">${esCon ? 'Captura regional' : 'Captura de la zona'}</div><h1>Registrar representante</h1><p>El sistema busca duplicados por identificador antes de crear el registro. La persona suma en reclutamiento al guardar.</p></div></div>
        ${per.k !== 'abierto' ? banner(per.k === 'cerrado' ? 'bad' : 'warn', esc(per.msg)) : ''}
        <div class="steps">${stepsL.map((l, i) => `<span class="step${i === st ? ' on' : i < st ? ' done' : ''}"><i>${i < st ? '✓' : i + 1}</i>${l}</span>`).join('')}</div>
        <div class="card">
        ${st === 0 ? `<div class="fgrid">
            ${F('curp', 'CURP', '18 caracteres. Se guarda cifrada y se muestra enmascarada.', inp('curp', { up: 1, max: 18 }))}
            ${F('nombre', 'Nombre(s)', '', inp('nombre'))}
            ${F('ap', 'Apellido paterno', '', inp('ap'))}
            ${F('am', 'Apellido materno', 'Opcional', inp('am'))}
            ${F('tel', 'Teléfono', '10 dígitos', inp('tel', { dig: 1, max: 10 }))}
            ${F('email', 'Correo electrónico', 'Opcional', inp('email', { max: 120 }))}
            ${F('secDom', 'Sección de domicilio', zSel ? 'Sección electoral de su credencial' : '', inp('secDom', { dig: 1, max: 5 }))}
          </div>
          ${dup ? `<div style="margin-top:14px">${banner(dup.vigente ? 'bad' : 'info', dup.vigente ? `<b>Posible duplicado bloqueado.</b> ${esc(dup.nombre)} ya está en el padrón como ${esc(tipoL(dup.tipo))} en ${esc(dup.donde)} (${esc(dup.estatus)}). No se crea otro registro.` : `${esc(dup.nombre)} existe en el registro maestro sin asignación vigente. Al guardar se le agrega una nueva asignación con los datos que captures.`)}</div>` : ''}` : ''}
        ${st === 1 ? `<div class="fgrid">
            <div class="field"><label for="f_tipo">Tipo de representación</label><select id="f_tipo">${esCon ? '<option value="CONSEJERO">Consejero</option>' : `<option value="RC" ${fm.tipo === 'RC' ? 'selected' : ''}>Representante de casilla (RC)</option><option value="RG" ${fm.tipo === 'RG' ? 'selected' : ''}>Representante general (RG)</option><option disabled>Consejero (lo registra la coordinación regional)</option>`}</select></div>
            ${esCon ? F('zona', 'Región', '', `<select id="f_region">${CTX.regiones.map(r => `<option value="${r.id}" ${r.id === fm.region_id ? 'selected' : ''}>${esc(r.nombre)}</option>`).join('')}</select>`)
                    : F('zona', 'Zona responsable', '', zonaSelect('f_zona', fm.zona_id))}
            ${F('pos', 'Posición', vac ? `${N(vac.total)} posiciones vacantes. Puedes asignarla después.` : 'Cargando vacantes…', `<select id="f_pos"><option value="">Sin asignar por ahora</option>${(vac ? vac.vacantes : []).map(v => `<option value="${esc(v.k)}" ${v.k === fm.pos ? 'selected' : ''}>${esc(v.l)}</option>`).join('')}</select>`)}
          </div><div style="margin-top:14px">${banner('info', 'Antes de tener posición, la persona cuenta en la zona responsable. Con posición, cuenta en la zona de la casilla (RN-10). Una persona no puede ocupar dos posiciones ni ser RG y RC a la vez (RN-12).')}</div>` : ''}
        ${st === 2 ? `<div>${docs().map(n => `<div class="docrow"><div class="dn"><b>${esc(n)}</b><small>${fm.files[n] ? esc(fm.files[n].name) : 'PDF, JPG o PNG · máximo 10 MB'}</small></div>
            <div style="display:flex;gap:8px;align-items:center">${fm.files[n] ? `${chip('info', 'Listo para subir')}<button class="btn sm" data-quitar="${esc(n)}">Quitar</button>` : `<label class="btn sm file">${Ic('upload')}Elegir archivo<input type="file" accept=".pdf,.jpg,.jpeg,.png" capture="environment" data-doc="${esc(n)}"></label>`}</div></div>`).join('')}
            <p class="muted" style="margin-top:10px">Los documentos pueden cargarse después desde la ficha. El expediente solo se envía a revisión con todos los obligatorios.</p></div>` : ''}
        ${st === 3 ? `<dl class="dl">
            <dt>Nombre</dt><dd>${esc(fm.nombre + ' ' + fm.ap + ' ' + fm.am)}</dd><dt>CURP</dt><dd class="mono">${esc(fm.curp.slice(0, 4) + '••••••••••' + fm.curp.slice(-4))}</dd><dt>Teléfono</dt><dd>${esc(fm.tel)}</dd><dt>Sección de domicilio</dt><dd>${esc(fm.secDom)}</dd>
            <dt>Tipo</dt><dd>${tipoL(fm.tipo)}</dd><dt>${esCon ? 'Región' : 'Zona'}</dt><dd>${esCon ? esc((CTX.regiones.find(r => r.id === fm.region_id) || {}).nombre) : esc(zSel ? zSel.region + ' · ' + zSel.nombre : '')}</dd>
            <dt>Posición</dt><dd>${esc(fm.pos ? ((vac && vac.vacantes.find(v => v.k === fm.pos)) || { l: fm.pos }).l : 'Sin posición asignada')}</dd>
            <dt>Documentos</dt><dd>${Object.keys(fm.files).length} de ${docs().length} listos</dd><dt>Estatus inicial</dt><dd>Reclutado</dd></dl>` : ''}
        <div style="display:flex;justify-content:space-between;gap:8px;margin-top:18px;flex-wrap:wrap">
          <button class="btn" data-nav="-1" ${st === 0 ? 'disabled' : ''}>Anterior</button>
          ${st < 3 ? `<button class="btn primary" data-nav="1" ${per.k === 'cerrado' ? 'disabled' : ''}>Continuar</button>` : `<button class="btn primary" data-save ${per.k === 'cerrado' || enviando ? 'disabled' : ''}>${enviando ? '<span class="spin"></span> Guardando…' : Ic('check') + 'Registrar representante'}</button>`}
        </div></div></div>`;
      $$('input[data-k]', VIEW).forEach(i => {
        i.oninput = () => {
          let v = i.value;
          if (i.dataset.up) v = v.toUpperCase().replace(/\s/g, '');
          if (i.dataset.dig) v = v.replace(/\D/g, '');
          if (v !== i.value) i.value = v;
          fm[i.dataset.k] = v;
          delete srvErr[i.dataset.k];
          if (i.dataset.k === 'curp') { dup = null; buscarDup(); }
        };
        i.onblur = () => { touched[i.dataset.k] = true; marcar(i.dataset.k); };
      });
      if (foco && $('#' + foco, VIEW)) { const el = $('#' + foco, VIEW); el.focus(); if (el.setSelectionRange && el.type !== 'email') try { el.setSelectionRange(el.value.length, el.value.length); } catch (_) { } }
      const ft = $('#f_tipo'); if (ft) ft.onchange = () => { fm.tipo = ft.value; fm.pos = ''; delete srvErr.pos; cargarVacantes(); pintar(); };
      const fz = $('#f_zona'); if (fz) fz.onchange = () => { fm.zona_id = +fz.value; fm.pos = ''; delete srvErr.zona; cargarVacantes(); pintar(); };
      const fr = $('#f_region'); if (fr) fr.onchange = () => { fm.region_id = +fr.value; fm.pos = ''; cargarVacantes(); pintar(); };
      const fp = $('#f_pos'); if (fp) fp.onchange = () => { fm.pos = fp.value; delete srvErr.pos; };
      $$('input[data-doc]', VIEW).forEach(i => i.onchange = () => { const f = i.files[0]; if (!f) return; const e = validarArchivo(f); if (e) return toast(e, 'bad'); fm.files[i.dataset.doc] = f; pintar(); });
      $$('[data-quitar]', VIEW).forEach(b => b.onclick = () => { delete fm.files[b.dataset.quitar]; pintar(); });
      $$('[data-nav]', VIEW).forEach(b => b.onclick = () => {
        const dir = +b.dataset.nav;
        if (dir > 0) {
          const e = errores()[st];
          if (Object.keys(e).length) { touched._all = true; toast('Revisa los campos marcados antes de continuar.', 'bad'); return pintar(); }
          touched = {};
          if (st === 0) cargarVacantes();
        }
        st += dir; pintar(); window.scrollTo(0, 0);
      });
      const sv = $('[data-save]', VIEW); if (sv) sv.onclick = guardar;
    }
    async function guardar() {
      const [e1, e2] = errores();
      if (Object.keys(e1).length || Object.keys(e2).length) { st = Object.keys(e1).length ? 0 : 1; touched._all = true; toast('Hay datos con errores. Regresa a los pasos anteriores.', 'bad'); return pintar(); }
      enviando = true; pintar();
      let r;
      try {
        r = await api('alta', {}, { curp: fm.curp, nombre: fm.nombre.trim(), ap: fm.ap.trim(), am: fm.am.trim(), tel: fm.tel, email: fm.email.trim(), secDom: fm.secDom, tipo: fm.tipo, zona_id: fm.zona_id, region_id: fm.region_id, pos: fm.pos });
      } catch (e) {
        enviando = false;
        if (e.data && e.data.errores) { srvErr = e.data.errores; st = Object.keys(srvErr).some(k => ['zona', 'pos'].includes(k)) && !Object.keys(srvErr).some(k => !['zona', 'pos'].includes(k)) ? 1 : 0; touched._all = true; }
        fallo(e); return pintar();
      }
      const fallidos = [];
      for (const [n, f] of Object.entries(fm.files)) {
        const fd2 = new FormData(); fd2.append('id', r.id); fd2.append('tipo_documento', n); fd2.append('archivo', f);
        try { await api('doc_subir', {}, fd2); } catch (e) { fallidos.push(n + ': ' + e.message); }
      }
      enviando = false;
      toast(r.msg + (fallidos.length ? ' No se subieron: ' + fallidos.join('; ') : ''), fallidos.length ? 'bad' : '');
      Object.assign(fm, vacio()); st = 0; touched = {}; srvErr = {}; dup = null;
      if (pre) history.replaceState(null, '', 'registrar.php');
      pintar();
      abrirFicha(r.id);
    }
    pintar();
  }

  /* ==================================================================
     P-08  REVISIÓN DE EXPEDIENTES
     ================================================================== */
  const EX = { sel: +QS.get('id') || 0 };
  async function vExpedientes() {
    if (!can('revisar')) { VIEW.innerHTML = `<div class="page">${banner('warn', 'Tu rol no revisa expedientes. Lo hacen la coordinación estatal y la regional.')}</div>`; return; }
    let d, p = null;
    try { d = await api('expedientes'); } catch (e) { VIEW.innerHTML = `<div class="page">${banner('bad', esc(e.message))}</div>`; return; }
    if (!d.cola.find(x => x.id === EX.sel)) EX.sel = d.cola[0] ? d.cola[0].id : 0;
    if (EX.sel) { try { p = await api('persona', { id: EX.sel }); } catch (_) { p = null; } }
    const sel = d.cola.find(x => x.id === EX.sel);
    VIEW.innerHTML = `<div class="page">
      <div class="ph"><div><div class="eyebrow">Validación</div><h1>Revisión de expedientes</h1><p>Aprueba cada documento u obsérvalo con un motivo. Con todos aprobados, la persona pasa a Expediente completo y suma en la meta de expedientes.</p></div></div>
      <div class="kpis"><div class="kpi"><small>En cola</small><b>${N(d.total)}</b><span>expedientes por revisar</span></div><div class="kpi"><small>Tiempo promedio</small><b>${d.promedio} días</b><span>en revisión</span></div><div class="kpi"><small>Observados</small><b>${N(d.observados)}</b><span>esperando corrección de la zona</span></div></div>
      <div class="split">
        <div class="card" style="padding:14px"><div class="queue">${d.cola.map(x => `<button class="qitem${x.id === EX.sel ? ' on' : ''}" data-q="${x.id}"><div><b>${esc(x.nombre)}</b><small>${tipoL(x.tipo)} · ${esc(x.donde)}</small></div><span class="tag">${x.dias} d</span></button>`).join('')}
          ${!d.cola.length ? '<div class="empty">No hay expedientes en revisión.</div>' : ''}</div></div>
        ${p && sel ? `<div class="card"><div class="card-h"><div><h3>${esc(p.p.nombre)}</h3><span class="muted">${tipoL(p.p.tipo)} · ${esc(p.p.pos || 'Sin posición')} · enviado el ${fd(sel.enviado)}</span></div><button class="btn sm" data-ficha>Ver ficha</button></div>
          <div class="cas-grid">${p.docs.map(x => `<div class="cas"><div class="cas-h"><b style="font-size:15px">${esc(x.n)}</b>${docChip(x.e)}</div>
            ${x.id ? `<a class="btn sm" target="_blank" rel="noopener" href="${API}?action=doc_ver&id=${x.id}">${Ic('eye')}Abrir documento</a><div data-prev="${x.id}" data-file="${esc(x.file || '')}"></div>` : '<div class="preview">Sin documento</div>'}
            ${x.e === 'revision' ? `<div class="field"><label for="mo${x.id}">Motivo si se observa</label><select id="mo${x.id}"><option value="">Selecciona un motivo</option>${d.motivos.map(m => `<option>${esc(m)}</option>`).join('')}</select></div>
              <div style="display:flex;gap:8px"><button class="btn primary sm" data-ok="${x.id}">${Ic('check')}Aprobar</button><button class="btn danger sm" data-obs="${x.id}">Observar</button></div>`
            : `<p class="muted">${x.e === 'aprobado' ? 'Documento aprobado.' : esc(x.m || '')}</p>`}</div>`).join('')}</div></div>`
        : `<div class="card empty">Selecciona un expediente de la cola.</div>`}
      </div></div>`;
    $$('[data-q]', VIEW).forEach(b => b.onclick = () => { EX.sel = +b.dataset.q; vExpedientes(); });
    const bf = $('[data-ficha]', VIEW); if (bf) bf.onclick = () => abrirFicha(EX.sel, vExpedientes);
    $$('[data-prev]', VIEW).forEach(el => {
      const f = el.dataset.file.toLowerCase(), src = `${API}?action=doc_ver&id=${el.dataset.prev}`;
      el.innerHTML = /\.(jpe?g|png)$/.test(f) ? `<img class="docimg" alt="Vista previa" src="${src}">` : `<iframe class="docframe" title="Vista previa" src="${src}"></iframe>`;
    });
    const rev = (id, ok) => {
      const motivo = ok ? '' : ($('#mo' + id, VIEW) || {}).value;
      if (!ok && !motivo) return toast('Selecciona el motivo de la observación.', 'bad');
      api('doc_revisar', {}, { doc_id: id, ok, motivo }).then(r => { toast(r.msg); vExpedientes(); }).catch(fallo);
    };
    $$('[data-ok]', VIEW).forEach(b => b.onclick = () => rev(+b.dataset.ok, true));
    $$('[data-obs]', VIEW).forEach(b => b.onclick = () => rev(+b.dataset.obs, false));
  }

  /* ==================================================================
     P-09  REGISTRO Y ACREDITACIÓN
     ================================================================== */
  const RG = { tab: 'por', sel: new Set() };
  async function vRegistro() {
    if (!can('registro')) { VIEW.innerHTML = `<div class="page">${banner('warn', 'Tu rol no registra acuses. Lo hacen la coordinación estatal y la regional.')}</div>`; return; }
    let d;
    try { d = await api('registro_lista', { tab: RG.tab }); } catch (e) { VIEW.innerHTML = `<div class="page">${banner('bad', esc(e.message))}</div>`; return; }
    const pr = CTX.proceso, ids = new Set(d.rows.map(r => r.id));
    RG.sel.forEach(id => { if (!ids.has(id)) RG.sel.delete(id); });
    const selR = d.rows.filter(r => RG.sel.has(r.id));
    VIEW.innerHTML = `<div class="page">
      <div class="ph"><div><div class="eyebrow">Autoridad electoral</div><h1>Registro y acreditación</h1><p>Registra lotes de personas con expediente completo. Pasar a «Registrado con acuse» exige folio, fecha y archivo del acuse (RN-14).</p></div></div>
      ${!d.abierto ? banner('warn', CTX.hoy < pr.inicioRegistro ? `El registro ante la autoridad abre el ${fd(pr.inicioRegistro)}. Mientras tanto puedes consultar quiénes ya tienen expediente completo.` : `El registro cerró el ${fd(pr.cierreRegistro)}.`) : ''}
      <div class="kpis"><div class="kpi"><small>Por registrar</small><b>${N(d.kpi.por)}</b><span>con expediente completo</span></div><div class="kpi"><small>Registrados con acuse</small><b>${N(d.kpi.reg)}</b><span>esperando acreditación</span></div><div class="kpi"><small>Acreditados</small><b>${N(d.kpi.acr)}</b><span>confirmados por la autoridad</span></div></div>
      <div class="card">
        <div class="card-h"><div class="tabs">${[['por', 'Por registrar'], ['reg', 'Registrados']].map(([k, l]) => `<button data-tab="${k}" class="${RG.tab === k ? 'on' : ''}">${l}</button>`).join('')}</div>
          <div class="acts">${RG.tab === 'por' ? `<button class="btn" data-imp ${d.abierto ? '' : 'disabled'}>${Ic('upload')}Importar acuses</button><button class="btn primary" data-lote ${selR.length && d.abierto ? '' : 'disabled'}>${Ic('stamp')}Registrar lote (${selR.length})</button>`
            : `<button class="btn primary" data-acr ${selR.some(r => r.estatus === 'REGISTRADO') ? '' : 'disabled'}>${Ic('check')}Confirmar acreditación (${selR.filter(r => r.estatus === 'REGISTRADO').length})</button>`}</div></div>
        <div class="tw"><table class="tbl"><thead><tr><th><input type="checkbox" aria-label="Seleccionar todos" data-all></th><th>Nombre</th><th>Tipo</th><th>Posición</th><th>Zona</th><th>${RG.tab === 'por' ? 'Expediente completo' : 'Folio'}</th><th>Estatus</th></tr></thead>
          <tbody>${d.rows.map(p => `<tr><td><input type="checkbox" aria-label="Seleccionar ${esc(p.nombre)}" data-s="${p.id}" ${RG.sel.has(p.id) ? 'checked' : ''} ${RG.tab === 'reg' && p.estatus === 'ACREDITADO' ? 'disabled' : ''}></td>
            <td><b class="click" style="cursor:pointer" data-f="${p.id}">${esc(p.nombre)}</b></td><td><span class="tag">${tipoL(p.tipo)}</span></td><td>${esc(p.pos)}</td><td>${esc(p.donde)}</td>
            <td class="mono">${RG.tab === 'por' ? fd(p.completo) : esc(p.folio || '')}</td><td>${estChip(p.estatus)}</td></tr>`).join('')}</tbody></table></div>
        ${!d.rows.length ? '<div class="empty">No hay personas en esta lista.</div>' : ''}
        ${d.rows.length >= 500 ? '<p class="muted">Se muestran las primeras 500.</p>' : ''}
      </div></div>`;
    $$('[data-tab]', VIEW).forEach(b => b.onclick = () => { RG.tab = b.dataset.tab; RG.sel.clear(); vRegistro(); });
    $$('[data-s]', VIEW).forEach(c => c.onchange = () => { c.checked ? RG.sel.add(+c.dataset.s) : RG.sel.delete(+c.dataset.s); vRegistro(); });
    $('[data-all]', VIEW).onchange = e => { d.rows.filter(r => RG.tab === 'por' || r.estatus === 'REGISTRADO').forEach(r => e.target.checked ? RG.sel.add(r.id) : RG.sel.delete(r.id)); vRegistro(); };
    $$('[data-f]', VIEW).forEach(b => b.onclick = () => abrirFicha(+b.dataset.f, vRegistro));
    const bl = $('[data-lote]', VIEW); if (bl) bl.onclick = () => modalLote(selR);
    const ba = $('[data-acr]', VIEW); if (ba) ba.onclick = () => api('acreditar', {}, { ids: selR.filter(r => r.estatus === 'REGISTRADO').map(r => r.id) }).then(r => { toast(r.msg); RG.sel.clear(); vRegistro(); }).catch(fallo);
    const bi = $('[data-imp]', VIEW); if (bi) bi.onclick = modalImportarAcuses;
  }

  function modalLote(ps) {
    const M = modal('Registrar lote con acuse', `
      <p class="muted">${ps.length} personas con expediente completo. Cada una recibe el folio del lote con su consecutivo (folio-001, folio-002…).</p>
      <div class="fgrid"><div class="field"><label for="lf">Folio del acuse</label><input id="lf" maxlength="50" placeholder="Ej. SON-2027-L001"></div>
        <div class="field"><label for="lfe">Fecha de presentación</label><input id="lfe" type="date" value="${CTX.hoy}" max="${CTX.hoy}" min="${CTX.proceso.inicioRegistro}"></div></div>
      <div class="docrow"><div class="dn"><b>Archivo del acuse</b><small id="lan">PDF, JPG o PNG · máximo 10 MB</small></div><label class="btn sm file">${Ic('upload')}Elegir archivo<input type="file" id="laf" accept=".pdf,.jpg,.jpeg,.png"></label></div>
      <div id="lerr"></div>
      <div class="md-f" style="padding:0;border:0"><button class="btn" data-c>Cancelar</button><button class="btn primary" data-ok>Registrar ${ps.length} personas</button></div>`);
    $('[data-c]', M.el).onclick = M.close;
    const laf = $('#laf', M.el);
    laf.onchange = () => { const f = laf.files[0]; if (!f) return; const e = validarArchivo(f); if (e) { laf.value = ''; return toast(e, 'bad'); } $('#lan', M.el).textContent = f.name; };
    $('[data-ok]', M.el).onclick = async (ev) => {
      const fdta = new FormData();
      fdta.append('ids', JSON.stringify(ps.map(p => p.id)));
      fdta.append('folio', $('#lf', M.el).value.trim());
      fdta.append('fecha', $('#lfe', M.el).value);
      if (laf.files[0]) fdta.append('archivo', laf.files[0]);
      ev.target.disabled = true;
      try { const r = await api('lote', {}, fdta); toast(r.msg); M.close(); RG.sel.clear(); vRegistro(); }
      catch (e) { ev.target.disabled = false; $('#lerr', M.el).innerHTML = banner('bad', (e.data && e.data.errores ? e.data.errores : [e.message]).map(x => `<div>${esc(x)}</div>`).join('')); }
    };
  }

  function modalImportarAcuses() {
    const M = modal('Importar acuses', `
      ${banner('info', 'Importa el archivo que entrega la autoridad con <b>curp, folio, fecha</b> (AAAA-MM-DD). Cada CURP debe existir en el padrón con expediente completo y cada folio debe ser único. La integración automática queda para una fase posterior.')}
      <div style="display:flex;gap:8px"><label class="btn sm file">${Ic('upload')}Elegir archivo<input type="file" id="iaf" accept=".csv,.txt"></label></div>
      <div class="field"><label for="iat">Contenido</label><textarea id="iat" rows="7">curp,folio,fecha\n</textarea></div><div id="iar"></div>
      <div class="md-f" style="padding:0;border:0"><button class="btn" data-c>Cancelar</button><button class="btn" data-v>Validar</button><button class="btn primary" data-ok disabled>Importar</button></div>`, true);
    const t = $('#iat', M.el), ok = $('[data-ok]', M.el), res = $('#iar', M.el);
    $('[data-c]', M.el).onclick = M.close;
    $('#iaf', M.el).onchange = e => leerArchivoTexto(e.target, x => { t.value = x; ok.disabled = true; });
    $('[data-v]', M.el).onclick = async () => {
      try {
        const d = await api('importar_acuses', {}, { csv: t.value });
        res.innerHTML = `<div class="tw" style="max-height:260px"><table class="tbl"><thead><tr><th>Fila</th><th>Persona</th><th>Folio</th><th>Fecha</th><th>Resultado</th></tr></thead><tbody>${d.filas.map(r => `<tr><td>${r.n}</td><td>${esc(r.nombre)}</td><td class="mono">${esc(r.folio)}</td><td>${esc(r.fecha)}</td><td>${r.err ? `<span style="color:var(--bad);font-weight:600">${esc(r.err)}</span>` : chip('ok', 'Válida')}</td></tr>`).join('')}</tbody></table></div>`;
        ok.disabled = !d.validas; ok.textContent = `Importar ${d.validas} acuses`;
      } catch (e) { res.innerHTML = banner('bad', esc(e.message)); }
    };
    ok.onclick = () => api('importar_acuses', {}, { csv: t.value, confirmar: 1 }).then(r => { toast(r.msg); M.close(); vRegistro(); }).catch(fallo);
  }

  /* ==================================================================
     P-10  METAS
     ================================================================== */
  const MT = { padre: '', draft: null, aut: false, mot: '' };
  function distribuir(total, pesos) {
    const s = pesos.reduce((a, b) => a + b, 0) || pesos.length || 1;
    const w = pesos.reduce((a, b) => a + b, 0) ? pesos : pesos.map(() => 1);
    const raw = w.map(x => total * x / s), base = raw.map(Math.floor);
    const rest = total - base.reduce((a, b) => a + b, 0);
    const ord = raw.map((r, i) => [r - Math.floor(r), i]).sort((a, b) => b[0] - a[0]);
    for (let k = 0; k < rest; k++) base[ord[k % ord.length][1]]++;
    return base;
  }
  async function vMetas() {
    let d;
    try { d = await api('metas', { padre: MT.padre }); } catch (e) { VIEW.innerHTML = `<div class="page">${banner('bad', esc(e.message))}</div>`; return; }
    if (MT.padre !== d.padre) { MT.padre = d.padre; MT.draft = null; }
    if (!MT.draft) MT.draft = Object.fromEntries(d.hijos.map(k => [k.id, { meta: k.meta, con: k.con }]));
    const D = MT.draft, est = d.padre === 'estado';
    const pintar = () => {
      const sum = d.hijos.reduce((a, k) => a + (+D[k.id].meta || 0), 0), sumCon = d.hijos.reduce((a, k) => a + (+D[k.id].con || 0), 0);
      const diff = sum - d.superior, diffCon = est ? sumCon - d.superiorCon : 0;
      const dirty = d.hijos.some(k => +D[k.id].meta !== k.meta || (est && +D[k.id].con !== k.con));
      VIEW.innerHTML = `<div class="page">
        <div class="ph"><div><div class="eyebrow">Distribución territorial</div><h1>Metas</h1><p>La coordinación estatal distribuye entre regiones y cada región entre sus zonas. La suma debe cuadrar con la meta superior salvo autorización expresa (RN-02).</p></div>
          ${d.vistas.length > 1 ? `<div class="field" style="min-width:260px"><label for="mv">Distribución</label><select id="mv">${d.vistas.map(v => `<option value="${esc(v.u)}" ${v.u === d.padre ? 'selected' : ''}>${esc(v.l)}</option>`).join('')}</select></div>` : ''}</div>
        ${!d.editable ? banner('info', can('metas') ? 'Esta distribución la publica otro nivel. Aquí solo la consultas.' : 'Tu rol consulta las metas; no las modifica.') : ''}
        ${est && d.editaEstado ? `<div class="card"><div class="card-h"><h3>Meta del estado</h3><span class="muted">${d.superiorPublicada ? 'Publicada' : 'Aún no publicada: se usa la suma de posiciones requeridas (' + N(d.superiorReq) + ')'}</span></div>
          <div class="fgrid"><div class="field"><label for="me">Representantes en casilla (RG + RC)</label><input id="me" type="number" min="0" step="1" value="${d.superior}"></div>
            <div class="field"><label for="mc">Consejeros</label><input id="mc" type="number" min="0" step="1" value="${d.superiorCon}"></div>
            <div class="field"><label for="mf">Fuente de la meta</label><input id="mf" maxlength="100" value="${esc(d.fuente)}"></div></div>
          <div style="display:flex;justify-content:flex-end;margin-top:12px"><button class="btn primary" data-mest>Publicar meta estatal</button></div></div>` : ''}
        <div class="card">
          <div class="card-h"><h3>${esc(d.nombre)} → ${est ? 'regiones' : 'zonas'}</h3>
            <div class="acts">${d.editable ? `<button class="btn" data-prop>Proponer distribución</button><button class="btn" data-desc ${dirty ? '' : 'disabled'}>Descartar</button><button class="btn primary" data-pub ${dirty || !d.hijos.some(k => k.publicada) ? '' : 'disabled'}>Publicar versión ${d.siguiente}</button>` : ''}</div></div>
          ${d.hijos.length ? `<div class="tw"><table class="tbl"><thead><tr><th>${est ? 'Región' : 'Zona'}</th><th class="n">Posiciones requeridas</th><th class="n">Meta vigente</th><th class="n">${d.editable ? 'Meta en edición' : 'Meta'}</th>${est ? '<th class="n">Consejeros</th>' : ''}<th>Observación</th></tr></thead>
          <tbody>${d.hijos.map(k => { const v = +D[k.id].meta; return `<tr><td><b>${esc(k.nombre)}</b><span class="sub">${esc(k.coord)}${k.publicada ? '' : ' · sin publicar'}</span></td><td class="n">${N(k.req)}</td><td class="n">${N(k.meta)}</td>
            <td class="n">${d.editable ? `<input class="inp num" style="width:110px;text-align:right" type="number" min="0" step="1" aria-label="Meta ${esc(k.nombre)}" data-m="${k.id}" value="${esc(D[k.id].meta)}">` : N(v)}</td>
            ${est ? `<td class="n">${d.editable ? `<input class="inp num" style="width:80px;text-align:right" type="number" min="0" step="1" aria-label="Consejeros ${esc(k.nombre)}" data-c="${k.id}" value="${esc(D[k.id].con)}">` : N(D[k.id].con)}</td>` : ''}
            <td>${v < k.req ? `<span class="warnt">La meta no cubre todas las posiciones (−${N(k.req - v)})</span>` : v !== k.meta ? `<span class="tag">Cambia ${v > k.meta ? '+' : ''}${N(v - k.meta)}</span>` : ''}</td></tr>`; }).join('')}</tbody>
          <tfoot><tr><td>Suma</td><td class="n">${N(d.hijos.reduce((a, k) => a + k.req, 0))}</td><td class="n">${N(d.hijos.reduce((a, k) => a + k.meta, 0))}</td><td class="n">${N(sum)}</td>${est ? `<td class="n">${N(sumCon)}</td>` : ''}<td></td></tr></tfoot></table></div>
          <div style="margin-top:14px">${diff === 0 && diffCon === 0 ? banner('ok', `Cuadra: asignado ${N(sum)} de ${N(d.superior)}${est ? ` y ${N(sumCon)} de ${N(d.superiorCon)} consejeros` : ''}.`)
            : banner('bad', `No cuadra: asignado ${N(sum)} de ${N(d.superior)} (${diff > 0 ? 'sobran' : 'faltan'} ${N(Math.abs(diff))})${diffCon ? ` · consejeros ${N(sumCon)} de ${N(d.superiorCon)}` : ''}. No se publica sin autorización (RN-02).`)
              + (d.editable && d.autorizar ? `<div class="fgrid" style="margin-top:10px"><label class="check"><input type="checkbox" id="aut" ${MT.aut ? 'checked' : ''}>Autorizo publicar con descuadre</label><div class="field"><label for="mm">Motivo de la autorización</label><input id="mm" value="${esc(MT.mot)}" placeholder="Ej. reserva para sustituciones"></div></div>` : '')}</div>`
          : '<div class="empty">No hay unidades hijas para distribuir.</div>'}
        </div>
        <div class="card"><div class="card-h"><h3>Historial de versiones</h3><span class="muted">Una meta publicada solo cambia con una nueva versión (RN-13)</span></div>
          <div class="tw"><table class="tbl"><thead><tr><th>Versión</th><th>Fecha</th><th>Ámbito</th><th>Publicó</th><th>Motivo</th><th></th></tr></thead>
          <tbody>${d.versiones.map(v => `<tr><td><b>v${v.version}</b></td><td>${fd(v.creado_en)}</td><td>${esc(v.ambito)}</td><td>${esc(v.usuario_nombre)}</td><td>${esc(v.motivo || '')}</td><td>${+v.autorizada ? chip('warn', 'Con autorización') : chip('ok', 'Cuadrada')}</td></tr>`).join('')}</tbody></table></div>
          ${!d.versiones.length ? '<div class="empty">Aún no hay metas publicadas. Mientras tanto, cada unidad usa sus posiciones requeridas como meta.</div>' : ''}</div>
      </div>`;
      const mv = $('#mv'); if (mv) mv.onchange = () => { MT.padre = mv.value; MT.draft = null; vMetas(); };
      $$('[data-m]', VIEW).forEach(i => i.onchange = () => { D[i.dataset.m].meta = i.value === '' ? '' : +i.value; pintar(); });
      $$('[data-c]', VIEW).forEach(i => i.onchange = () => { D[i.dataset.c].con = i.value === '' ? '' : +i.value; pintar(); });
      const au = $('#aut'); if (au) au.onchange = () => { MT.aut = au.checked; };
      const mm = $('#mm'); if (mm) mm.oninput = () => { MT.mot = mm.value; };
      const bp = $('[data-prop]', VIEW); if (bp) bp.onclick = () => {
        const v = distribuir(d.superior, d.hijos.map(k => k.req));
        d.hijos.forEach((k, i) => D[k.id].meta = v[i]);
        if (est) { const c = distribuir(d.superiorCon, d.hijos.map(k => k.con || k.req)); d.hijos.forEach((k, i) => D[k.id].con = c[i]); }
        pintar();
      };
      const bd = $('[data-desc]', VIEW); if (bd) bd.onclick = () => { MT.draft = null; vMetas(); };
      const bpub = $('[data-pub]', VIEW); if (bpub) bpub.onclick = () => {
        if (d.hijos.some(k => !Number.isInteger(+D[k.id].meta) || +D[k.id].meta < 0 || D[k.id].meta === '')) return toast('Las metas deben ser números enteros mayores o iguales a cero.', 'bad');
        api('metas_publicar', {}, { padre: d.padre, valores: D, autorizo: MT.aut ? 1 : 0, motivo: MT.mot }).then(r => { toast(r.msg); MT.draft = null; MT.aut = false; MT.mot = ''; vMetas(); }).catch(fallo);
      };
      const be = $('[data-mest]', VIEW); if (be) be.onclick = () => api('meta_estatal', {}, { meta: $('#me').value, con: $('#mc').value, fuente: $('#mf').value }).then(r => { toast(r.msg); vMetas(); }).catch(fallo);
    };
    pintar();
  }

  /* ==================================================================
     P-11  RUTA Y CORTES
     ================================================================== */
  const RT = { draft: null };
  async function vRuta() {
    let d;
    try { d = await api('ruta'); } catch (e) { VIEW.innerHTML = `<div class="page">${banner('bad', esc(e.message))}</div>`; return; }
    const ks = ['rec', 'exp', 'reg', 'con'];
    RT.draft = d.cortes.map(c => Object.assign({}, c));
    const pintar = () => {
      const D = RT.draft, errs = {}, edit = d.editable;
      D.forEach((c, i) => {
        if (!c.fecha) errs[i + 'f'] = 'Fecha requerida';
        else if (i > 0 && D[i - 1].fecha && c.fecha <= D[i - 1].fecha) errs[i + 'f'] = 'Debe ser posterior al corte anterior';
        else if (c.fecha < d.inicio || c.fecha > d.cierre) errs[i + 'f'] = 'Fuera del periodo de la ruta';
        ks.forEach(k => { const v = +c[k]; if (c[k] === '' || !Number.isFinite(v) || v < 0 || v > 100) errs[i + k] = '0 a 100'; else if (i > 0 && v < +D[i - 1][k]) errs[i + k] = 'No puede bajar'; });
      });
      if (D.length) ks.forEach(k => { if (+D[D.length - 1][k] !== 100) errs[(D.length - 1) + k] = 'El último corte debe ser 100%'; });
      const nErr = Object.keys(errs).length;
      const sig = c => [c.fecha, ...ks.map(k => +c[k])].join('|');
      const dirty = D.length !== d.cortes.length || D.some((c, i) => !d.cortes[i] || sig(c) !== sig(d.cortes[i]));
      const cell = (c, i, k) => { const lock = !edit || c.cerrado, e = errs[i + k]; return `<td class="n">${lock ? +c[k] + '%' : `<input class="inp num" style="width:70px;text-align:right${e ? ';border-color:var(--bad)' : ''}" type="number" min="0" max="100" step="0.5" aria-label="${k} ${c.fecha}" data-i="${i}" data-k="${k}" value="${esc(c[k])}">`}${e ? `<span class="sub" style="color:var(--bad)">${esc(e)}</span>` : ''}</td>`; };
      VIEW.innerHTML = `<div class="page">
        <div class="ph"><div><div class="eyebrow">Programación</div><h1>Ruta y cortes</h1><p>Fechas de corte y porcentaje acumulado esperado por etapa. Se configuran sin tocar código; al cerrar un corte vencido se guarda una foto que no cambia (RN-08).</p></div>
          ${edit ? `<div style="display:flex;gap:8px;flex-wrap:wrap"><button class="btn" data-add>${Ic('plus')}Agregar corte</button><button class="btn" data-desc ${dirty ? '' : 'disabled'}>Descartar</button><button class="btn primary" data-save ${dirty ? '' : 'disabled'}>Guardar versión ${d.version + 1}</button></div>` : ''}</div>
        ${!edit ? banner('info', 'Solo la coordinación estatal modifica la ruta. Tu rol la consulta.') : ''}
        ${nErr && dirty ? banner('bad', `${nErr} ${nErr === 1 ? 'celda con error' : 'celdas con error'}. La ruta no se guarda hasta corregirlas.`) : ''}
        ${dirty && edit ? banner('warn', 'Tienes cambios sin guardar. Guarda la ruta antes de cerrar un corte.') : ''}
        <div class="g2e">
          <div class="card"><div class="card-h"><h3>Cortes · versión ${d.version}</h3><span class="muted">% acumulado de la meta</span></div>
            <div class="tw"><table class="tbl"><thead><tr><th>Corte</th><th class="n">Reclut.</th><th class="n">Exped.</th><th class="n">Registro</th><th class="n">Consej.</th><th></th></tr></thead>
            <tbody>${D.map((c, i) => `<tr><td>${!edit || c.cerrado ? fd(c.fecha) : `<input class="inp" type="date" aria-label="Fecha de corte" data-i="${i}" data-k="fecha" value="${esc(c.fecha)}" style="${errs[i + 'f'] ? 'border-color:var(--bad)' : ''}">`}${errs[i + 'f'] ? `<span class="sub" style="color:var(--bad)">${esc(errs[i + 'f'])}</span>` : ''}</td>
              ${ks.map(k => cell(c, i, k)).join('')}
              <td style="white-space:nowrap">${c.cerrado ? `<button class="btn sm" data-foto="${c.id}">Ver foto</button>` : c.id && c.fecha <= d.hoy ? (edit && !dirty ? `<button class="btn sm primary" data-cerrar="${c.id}">Cerrar corte</button>` : chip('warn', 'Por cerrar')) : `${edit && !c.cerrado && D.length > 1 ? `<button class="casx" title="Quitar corte" data-del="${i}">✕</button>` : '<span class="muted">Abierto</span>'}`}</td></tr>`).join('')}</tbody></table></div></div>
          ${curva({ t: d.hoy, curva: [] }, { cortes: nErr ? d.cortes : D, title: 'Vista previa de la ruta', sub: nErr ? 'Mostrando la versión vigente' : 'Con los cambios en edición' })}
        </div></div>`;
      $$('input[data-i]', VIEW).forEach(inp => inp.onchange = () => { RT.draft[+inp.dataset.i][inp.dataset.k] = inp.value; pintar(); });
      const ba = $('[data-add]', VIEW); if (ba) ba.onclick = () => {
        const last = D[D.length - 1], prev = D[D.length - 2] || { fecha: d.inicio, rec: 0, exp: 0, reg: 0, con: 0 };
        const mid = isoOf(Math.round((dn(prev.fecha) + dn(last.fecha)) / 2));
        D.splice(D.length - 1, 0, { id: 0, fecha: mid, rec: prev.rec, exp: prev.exp, reg: prev.reg, con: prev.con, cerrado: false });
        pintar();
      };
      $$('[data-del]', VIEW).forEach(b => b.onclick = () => { D.splice(+b.dataset.del, 1); pintar(); });
      const bd = $('[data-desc]', VIEW); if (bd) bd.onclick = () => vRuta();
      const bs = $('[data-save]', VIEW); if (bs) bs.onclick = () => {
        if (nErr) return toast('Corrige las celdas marcadas antes de guardar.', 'bad');
        api('ruta_guardar', {}, { cortes: D.map(c => ({ id: c.cerrado ? c.id : 0, fecha: c.fecha, rec: +c.rec, exp: +c.exp, reg: +c.reg, con: +c.con })) }).then(r => { toast(r.msg); vRuta(); }).catch(fallo);
      };
      $$('[data-cerrar]', VIEW).forEach(b => b.onclick = () => { if (confirm('Al cerrar el corte se guarda una foto inmutable de metas y avances. ¿Continuar?')) api('corte_cerrar', {}, { id: +b.dataset.cerrar }).then(r => { toast(r.msg); vRuta(); }).catch(fallo); });
      $$('[data-foto]', VIEW).forEach(b => b.onclick = async () => {
        try {
          const f = await api('corte_foto', { id: b.dataset.foto });
          modal('Foto del corte · ' + fd(f.fecha), `${banner('info', `Foto inmutable guardada el ${fd(f.cerrado_en)} por ${esc(f.cerrado_por || '')} (RN-08). Si después cambian metas o registros, estas cifras se conservan.`)}
            <div class="tw"><table class="tbl"><thead><tr><th>Región</th><th class="n">Meta</th><th class="n">Reclut. real / exigible</th><th class="n">Exped. real / exigible</th><th class="n">Registro real / exigible</th><th>Semáforo reclut.</th></tr></thead>
            <tbody>${f.rows.map(r => `<tr><td><b>${esc(r.nombre)}</b></td><td class="n">${N(r.meta)}</td><td class="n">${N(r.rec.real)} / ${N(r.rec.ex)}</td><td class="n">${N(r.exp.real)} / ${N(r.exp.ex)}</td><td class="n">${N(r.reg.real)} / ${N(r.reg.ex)}</td><td>${chip(r.rec.sem)}</td></tr>`).join('')}</tbody></table></div>`, true);
        } catch (e) { fallo(e); }
      });
    };
    pintar();
  }

  /* ==================================================================
     P-12 / P-14  CONFIGURACIÓN
     ================================================================== */
  async function vConfiguracion() {
    let d;
    try { d = await api('config'); } catch (e) { VIEW.innerHTML = `<div class="page">${banner('bad', esc(e.message))}</div>`; return; }
    const p = d.proceso, ed = d.editable, dis = ed ? '' : 'disabled';
    const tot = d.regiones.reduce((a, r) => ({ cas: a.cas + r.cas, rc: a.rc + r.rc, rg: a.rg + r.rg }), { cas: 0, rc: 0, rg: 0 });
    const fechaF = (k, l) => `<div class="field"><label for="c_${k}">${l}</label><input id="c_${k}" type="date" value="${esc(p[k])}" ${dis}></div>`;
    VIEW.innerHTML = `<div class="page">
      <div class="ph"><div><div class="eyebrow">Parámetros del proceso · ${esc(CTX.estado)}</div><h1>Configuración</h1><p>Todo lo que cambia entre procesos vive en configuración: fechas, reglas de posiciones, umbrales del semáforo, documentos obligatorios y catálogo de casillas.</p></div>
        ${ed ? `<button class="btn primary" data-save>${Ic('check')}Guardar configuración</button>` : ''}</div>
      ${!ed ? banner('info', 'Solo la coordinación estatal y la administración modifican estos parámetros. Tu rol los consulta.') : ''}
      <div class="card"><div class="card-h"><h3>Fechas clave</h3><span class="muted">${esc(p.nombre)}</span></div>
        <div class="fgrid"><div class="field"><label for="c_nombre">Nombre del proceso</label><input id="c_nombre" maxlength="150" value="${esc(p.nombre)}" ${dis}></div>
          ${fechaF('inicio', 'Inicio de la ruta')}${fechaF('inicioRegistro', 'Inicio del registro con acuse')}${fechaF('cierreRegistro', 'Cierre de registro')}${fechaF('cierreSust', 'Cierre de sustituciones')}${fechaF('jornada', 'Jornada electoral')}</div></div>
      <div class="g2e">
        <div class="card"><div class="card-h"><h3>Umbrales del semáforo</h3><span class="muted">Contra la meta exigible a la fecha</span></div>
          <div class="kv">
            <div><span>${chip('ok')} cumplimiento mayor o igual a</span><span class="cfgval"><input class="inp num" id="c_umbralRuta" type="number" min="1" max="150" step="0.5" value="${p.umbralRuta}" ${dis}>%</span></div>
            <div><span>${chip('warn')} cumplimiento mayor o igual a</span><span class="cfgval"><input class="inp num" id="c_umbralAtn" type="number" min="1" max="149" step="0.5" value="${p.umbralAtn}" ${dis}>%</span></div>
            <div><span>${chip('bad')} por debajo del umbral de atención</span><span></span></div>
            <div><span>${chip('idle')} sin registros ni metas exigibles</span><span></span></div></div>
          <p class="muted">La región o zona toma el estado de su etapa más atrasada.</p></div>
        <div class="card"><div class="card-h"><h3>Reglas de posiciones</h3><span class="muted">Validar contra la norma aplicable</span></div>
          <div class="fgrid">
            <div class="field"><label for="c_prop">RC propietarios por casilla</label><input id="c_prop" type="number" min="1" max="4" value="${p.prop}" ${dis}></div>
            <div class="field"><label for="c_sup">RC suplentes por casilla</label><input id="c_sup" type="number" min="0" max="4" value="${p.sup}" ${dis}></div>
            <div class="field"><label for="c_rgCada">RG: un grupo por cada N casillas</label><input id="c_rgCada" type="number" min="1" max="50" value="${p.rgCada}" ${dis}></div>
            <div class="field"><label for="c_rgMult">RG por grupo</label><input id="c_rgMult" type="number" min="1" max="5" value="${p.rgMult}" ${dis}></div>
            <div class="field"><label for="c_ambito">Ámbito de redondeo del RG</label><select id="c_ambito" ${dis}>${[['municipio', 'Por municipio'], ['zona', 'Por zona'], ['region', 'Por región']].map(([v, l]) => `<option value="${v}" ${p.ambito === v ? 'selected' : ''}>${l}</option>`).join('')}</select><span class="hint">Las posiciones de RG se crean por zona; el ámbito afecta la meta requerida de cada región.</span></div>
          </div></div>
      </div>
      <div class="card"><div class="card-h"><h3>Posiciones requeridas con la regla vigente</h3><span class="muted">Se recalcula al guardar</span></div>
        ${banner(tot.cas ? 'info' : 'warn', tot.cas ? `Con la regla vigente ${esc(CTX.estado)} requiere <b>${N(tot.rc)} RC</b> y <b>${N(tot.rg)} RG</b> en ${N(tot.cas)} casillas.` : 'No hay casillas en el catálogo: las posiciones requeridas salen de ahí. Cárgalas abajo.')}
        <div class="tw" style="margin-top:12px"><table class="tbl"><thead><tr><th>Región</th><th class="n">Zonas</th><th class="n">Secciones</th><th class="n">Casillas</th><th class="n">RC</th><th class="n">RG</th><th class="n">Meta vigente</th></tr></thead>
          <tbody>${d.regiones.map(r => `<tr><td><b>${esc(r.nombre)}</b></td><td class="n">${r.zonas}</td><td class="n">${N(r.secciones)}</td><td class="n">${N(r.cas)}</td><td class="n">${N(r.rc)}</td><td class="n">${N(r.rg)}</td><td class="n">${N(r.meta)}${r.publicada ? '' : '<span class="sub">sin publicar</span>'}</td></tr>`).join('')}</tbody>
          <tfoot><tr><td>${esc(CTX.estado)}</td><td></td><td></td><td class="n">${N(tot.cas)}</td><td class="n">${N(tot.rc)}</td><td class="n">${N(tot.rg)}</td><td></td></tr></tfoot></table></div></div>
      <div class="g2e lista-edit">
        <div class="card"><div class="card-h"><h3>Documentos obligatorios</h3><span class="muted">Uno por línea</span></div>
          <div class="field"><label for="c_docsRep">RG y RC</label><textarea id="c_docsRep" rows="5" ${dis}>${esc(p.docsRep.join('\n'))}</textarea></div>
          <div class="field" style="margin-top:10px"><label for="c_docsCon">Consejeros</label><textarea id="c_docsCon" rows="4" ${dis}>${esc(p.docsCon.join('\n'))}</textarea></div>
          <p class="muted">Cambiar el nombre de un documento no migra los archivos ya cargados con el nombre anterior.</p></div>
        <div class="card"><div class="card-h"><h3>Motivos de observación</h3><span class="muted">Catálogo para la revisión</span></div>
          <div class="field"><label for="c_motivos">Motivos</label><textarea id="c_motivos" rows="8" ${dis}>${esc(p.motivos.join('\n'))}</textarea></div>
          <div class="tw" style="margin-top:12px"><table class="tbl"><thead><tr><th>Compatibilidad</th><th>RC</th><th>RG</th><th>Consejero</th></tr></thead><tbody>
            <tr><td><b>RC</b></td><td>${chip('bad', 'Una casilla')}</td><td>${chip('bad', 'No')}</td><td>${chip('bad', 'No')}</td></tr>
            <tr><td><b>RG</b></td><td>${chip('bad', 'No')}</td><td>${chip('bad', 'Un ámbito')}</td><td>${chip('bad', 'No')}</td></tr></tbody></table></div>
          <p class="muted">Por defecto una persona tiene una sola asignación vigente por proceso (RN-12).</p></div>
      </div>
      <div class="card"><div class="card-h"><h3>Catálogo de casillas</h3><span class="muted">${N(d.catalogo.total)} casillas activas · ${N(d.catalogo.secciones)} secciones en zonas · ${N(d.catalogo.sinCasillas)} sin casillas</span></div>
        ${d.catalogo.fuentes.length ? `<div class="chips" style="margin:0 0 12px">${d.catalogo.fuentes.map(f => `<span class="tag">${esc(f.fuente)}: ${N(f.act)} activas de ${N(f.n)}</span>`).join('')}</div>` : ''}
        ${d.casillas ? `<div class="g2e">
          <div><h4 style="margin:0 0 6px">Importar encarte / listado oficial</h4><p class="muted" style="margin:0 0 8px">CSV con columnas <b>seccion,tipo,ubicacion</b>. Tipo: B, C1, C2…, E1 (especial), X1 (extraordinaria).</p>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px"><label class="btn sm file">${Ic('upload')}Elegir archivo<input type="file" id="cif" accept=".csv,.txt"></label><button class="btn sm" data-plc>${Ic('download')}Plantilla</button></div>
            <div class="field"><textarea id="cit" rows="6">seccion,tipo,ubicacion\n</textarea></div><div id="cir"></div>
            <div style="display:flex;gap:8px;margin-top:8px"><button class="btn" data-civ>Validar</button><button class="btn primary" data-cio disabled>Importar</button></div></div>
          <div><h4 style="margin:0 0 6px">Estimar desde lista nominal</h4><p class="muted" style="margin:0 0 8px">Mientras llega el encarte oficial: crea una casilla básica y las contiguas necesarias en cada sección <b>sin casillas</b>, a razón de una por cada N electores. Quedan marcadas como «estimacion».</p>
            <div class="inline"><div class="field" style="max-width:160px"><label for="cpe">Electores por casilla</label><input id="cpe" type="number" min="100" max="1500" value="750"></div><button class="btn" data-est>Estimar casillas</button></div></div>
        </div>` : '<p class="muted">Solo la coordinación estatal y la administración administran el catálogo.</p>'}
      </div></div>`;
    const bs = $('[data-save]', VIEW); if (bs) bs.onclick = () => {
      const g = k => $('#c_' + k).value;
      const lines = k => g(k).split('\n').map(x => x.trim()).filter(Boolean);
      api('config_guardar', {}, { proceso: { nombre: g('nombre'), inicio: g('inicio'), inicioRegistro: g('inicioRegistro'), cierreRegistro: g('cierreRegistro'), cierreSust: g('cierreSust'), jornada: g('jornada'),
        umbralRuta: +g('umbralRuta'), umbralAtn: +g('umbralAtn'), prop: +g('prop'), sup: +g('sup'), rgCada: +g('rgCada'), rgMult: +g('rgMult'), ambito: g('ambito'), docsRep: lines('docsRep'), docsCon: lines('docsCon'), motivos: lines('motivos') } })
        .then(r => { toast(r.msg); setTimeout(() => location.reload(), 900); }).catch(fallo);
    };
    if (!d.casillas) return;
    const cit = $('#cit'), cio = $('[data-cio]'), cir = $('#cir');
    $('#cif').onchange = e => leerArchivoTexto(e.target, t => { cit.value = t; cio.disabled = true; cir.innerHTML = ''; });
    cit.oninput = () => { cio.disabled = true; };
    $('[data-plc]').onclick = () => csvDescargar('plantilla_casillas', ['seccion', 'tipo', 'ubicacion'], [['1234', 'B', 'Escuela primaria …'], ['1234', 'C1', '']]);
    $('[data-civ]').onclick = async () => {
      cir.innerHTML = loading('Validando…');
      try {
        const r = await api('casillas_importar', {}, { csv: cit.value });
        const errs = r.filas.filter(f => f.err);
        cir.innerHTML = banner(r.validas ? 'info' : 'warn', `${N(r.validas)} filas válidas y ${N(r.errores)} con error.`) + (errs.length ? `<div class="tw" style="max-height:200px"><table class="tbl"><tbody>${errs.slice(0, 100).map(f => `<tr><td>Fila ${f.n}</td><td>${esc(f.seccion)} · ${esc(f.tipo)}</td><td style="color:var(--bad);font-weight:600">${esc(f.err)}</td></tr>`).join('')}</tbody></table></div>` : '');
        cio.disabled = !r.validas;
      } catch (e) { cir.innerHTML = banner('bad', esc(e.message)); }
    };
    cio.onclick = () => api('casillas_importar', {}, { csv: cit.value, confirmar: 1 }).then(r => { toast(r.msg); vConfiguracion(); }).catch(fallo);
    $('[data-est]').onclick = () => {
      if (!confirm('Se crearán casillas estimadas solo en las secciones que no tienen ninguna. ¿Continuar?')) return;
      api('casillas_estimar', {}, { por_cada: +$('#cpe').value }).then(r => { toast(r.msg); vConfiguracion(); }).catch(fallo);
    };
  }

  /* ==================================================================
     P-15  BITÁCORA
     ================================================================== */
  const BT = { q: '', page: 0 };
  async function vBitacora(soloTabla) {
    if (!can('bitacora')) { VIEW.innerHTML = `<div class="page">${banner('warn', 'Tu rol no consulta la bitácora.')}</div>`; return; }
    if (!soloTabla) {
      VIEW.innerHTML = `<div class="page">
        <div class="ph"><div><div class="eyebrow">Trazabilidad</div><h1>Bitácora</h1><p>Cada cambio de metas, estatus y asignaciones, y cada consulta de datos personales, queda registrado con usuario y fecha (RN-06). No se puede editar ni borrar.</p></div>${can('export') ? `<button class="btn" data-exp>${Ic('download')}Exportar</button>` : ''}</div>
        <div class="card"><div class="field search" style="max-width:420px;margin-bottom:12px"><div class="search">${Ic('search')}<input id="bq" aria-label="Buscar en bitácora" placeholder="Buscar por usuario, acción o persona" value="${esc(BT.q)}"></div></div><div id="btab">${loading()}</div></div></div>`;
      $('#bq').oninput = debounce(e => { BT.q = e.target.value; BT.page = 0; vBitacora(true); }, 350);
      const be = $('[data-exp]', VIEW); if (be) be.onclick = async () => { try { const d = await api('bitacora', { q: BT.q, export: 1 }); csvDescargar('Bitácora ' + CTX.estado, ['Fecha', 'Usuario', 'Rol', 'Acción', 'Registro', 'Detalle'], d.rows.map(l => [l.fecha, l.u, l.rol, l.a, l.e, l.d])); } catch (e) { fallo(e); } };
    }
    const box = $('#btab');
    let d;
    try { d = await api('bitacora', BT); } catch (e) { box.innerHTML = banner('bad', esc(e.message)); return; }
    const pages = Math.max(1, Math.ceil(d.total / d.per));
    box.innerHTML = `<div class="tw"><table class="tbl"><thead><tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Registro</th><th>Detalle</th></tr></thead>
      <tbody>${d.rows.map(l => `<tr><td class="num" style="white-space:nowrap">${fd(l.fecha)} ${esc(String(l.fecha).slice(11, 16))}</td><td>${esc(l.u)}</td><td><span class="tag">${esc(l.a)}</span></td><td><b>${esc(l.e)}</b></td><td>${esc(l.d)}</td></tr>`).join('')}</tbody></table></div>
      ${!d.rows.length ? '<div class="empty">Sin movimientos con ese criterio.</div>' : ''}
      <div class="pager"><span>${N(d.total)} movimientos · página ${d.page + 1} de ${pages}</span><div style="display:flex;gap:6px"><button class="btn sm" data-pg="-1" ${d.page === 0 ? 'disabled' : ''}>Anterior</button><button class="btn sm" data-pg="1" ${d.page >= pages - 1 ? 'disabled' : ''}>Siguiente</button></div></div>`;
    $$('[data-pg]', box).forEach(b => b.onclick = () => { BT.page += +b.dataset.pg; vBitacora(true); });
  }

  const VISTAS = { tablero: vTablero, casillas: vCasillas, padron: vPadron, registrar: vRegistrar, expedientes: vExpedientes, registro: vRegistro, metas: vMetas, ruta: vRuta, configuracion: vConfiguracion, bitacora: vBitacora };
  iniciar();
})();
