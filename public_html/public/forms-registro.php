<?php
/* ============================================================
   FORMS-REGISTRO - Página pública de registro de asistentes
   Se abre desde la liga / QR generado al "Activar registro de
   asistentes" en un Evento (Agenda) o Activación real.
   No requiere sesión iniciada: cualquier invitado puede entrar.
   El celular es la llave única de la persona en toda la app.
   ============================================================ */
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Registro de asistencia</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
    --primary:#2d7a3e; --primary-dark:#1f5a2d; --dark:#1a1a1a; --light:#f0f7f0;
    --white:#ffffff; --gray-100:#f8f9fa; --gray-200:#e9ecef; --text-muted:#6c757d;
}
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'Arial',sans-serif; background:var(--light); color:var(--dark); min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px; }
.card { background:var(--white); border-radius:22px; box-shadow:0 20px 50px rgba(0,0,0,0.08); border:1px solid var(--gray-200); width:100%; max-width:420px; padding:36px 32px; text-align:center; }
.badge { color:var(--primary); font-size:12px; font-weight:800; letter-spacing:3px; text-transform:uppercase; background:rgba(45,122,62,0.1); padding:5px 14px; border-radius:20px; display:inline-block; margin-bottom:16px; }
h1 { font-size:24px; font-weight:900; margin-bottom:8px; }
.subtitle { color:var(--text-muted); font-size:14px; margin-bottom:26px; }

.form-group { display:flex; flex-direction:column; gap:6px; margin-bottom:16px; text-align:left; }
.form-label { font-size:12px; font-weight:700; text-transform:uppercase; color:var(--text-muted); letter-spacing:0.5px; }
.form-input-styled { width:100%; padding:13px 15px; border-radius:12px; border:2px solid var(--gray-200); background:var(--gray-100); font-size:15px; font-weight:600; color:var(--dark); outline:none; transition:0.3s; font-family:Arial,sans-serif; }
.form-input-styled:focus { border-color:var(--primary); background:var(--white); }
.form-input-styled:disabled { opacity:0.7; }

.tel-row { display:flex; gap:8px; }
.tel-row select { flex:0 0 90px; }
.tel-row input { flex:1; }

.btn-submit { width:100%; background:var(--primary); color:white; border:none; padding:15px; border-radius:12px; font-weight:800; font-size:15px; cursor:pointer; margin-top:8px; box-shadow:0 6px 18px rgba(45,122,62,0.3); transition:0.3s; font-family:Arial,sans-serif; }
.btn-submit:hover { background:var(--primary-dark); transform:translateY(-2px); }
.btn-submit:disabled { opacity:0.6; cursor:not-allowed; transform:none; }
.btn-link { background:none; border:none; color:var(--primary); font-weight:700; font-size:13px; cursor:pointer; margin-top:10px; text-decoration:underline; }

.error-box { background:#fee2e2; color:#d32f2f; border:1px solid #fecaca; padding:10px 14px; border-radius:10px; font-size:13px; font-weight:600; margin-bottom:16px; display:none; text-align:left; }
.info-box { background:rgba(45,122,62,0.08); color:var(--primary-dark); border:1px solid rgba(45,122,62,0.2); padding:10px 14px; border-radius:10px; font-size:13px; font-weight:600; margin-bottom:16px; display:none; text-align:left; }

.success-box { display:none; text-align:center; }
.success-box i { font-size:52px; color:var(--primary); margin-bottom:16px; display:block; }
.success-box h2 { font-size:22px; font-weight:900; margin-bottom:8px; }
.success-box p { color:var(--text-muted); font-size:14px; }

.not-found i { font-size:44px; color:var(--text-muted); opacity:0.4; margin-bottom:14px; display:block; }
.hidden { display:none !important; }
</style>
</head>
<body>

<div class="card">
    <div id="loading">Cargando…</div>

    <div id="not-found" class="not-found hidden">
        <i class="fas fa-calendar-xmark"></i>
        <h1>Registro no encontrado</h1>
        <p class="subtitle">La liga no es válida o el registro de asistentes ya no está disponible.</p>
    </div>

    <div id="form-wrap" class="hidden">
        <div class="badge"><i class="fas fa-star"></i> &nbsp; Invitación</div>
        <h1 id="titulo-evento"></h1>
        <p class="subtitle">Ingresa tu celular para registrar tu asistencia.</p>

        <div class="error-box" id="error-box"></div>
        <div class="info-box" id="info-box"></div>

        <!-- Paso 1: celular -->
        <div id="paso-celular">
            <div class="form-group">
                <label class="form-label">Celular *</label>
                <div class="tel-row">
                    <select id="f-lada" class="form-input-styled">
                        <option value="+52" selected>+52</option>
                        <option value="+1">+1</option>
                    </select>
                    <input type="tel" id="f-celular" class="form-input-styled" placeholder="10 dígitos" maxlength="10">
                </div>
            </div>
            <button class="btn-submit" id="btn-continuar" onclick="continuarConCelular()"><i class="fas fa-arrow-right"></i> Continuar</button>
        </div>

        <!-- Paso 2: nombre/apellido (autocompletado si ya existe) -->
        <div id="paso-datos" class="hidden">
            <div class="form-group">
                <label class="form-label">Nombre(s) *</label>
                <input type="text" id="f-nombres" class="form-input-styled" placeholder="Ej. Juan Carlos">
            </div>
            <div class="form-group">
                <label class="form-label">Apellido(s) *</label>
                <input type="text" id="f-apellidos" class="form-input-styled" placeholder="Ej. Pérez García">
            </div>
            <button class="btn-submit" id="btn-enviar" onclick="enviarRegistro()"><i class="fas fa-check"></i> Confirmar registro</button>
            <button class="btn-link" onclick="volverACelular()">Cambiar celular</button>
        </div>
    </div>

    <div class="success-box" id="success-box">
        <i class="fas fa-circle-check"></i>
        <h2>¡Registro exitoso!</h2>
        <p>Gracias por confirmar tu asistencia a<br><strong id="titulo-exito"></strong>.</p>
    </div>
</div>

<script>
const API = '../api/registro_asistentes/procesar.php';
const params = new URLSearchParams(window.location.search);
const SLUG = params.get('slug') || '';
let YA_EXISTIA = false;

document.getElementById('f-celular').addEventListener('input', function() {
    this.value = this.value.replace(/\D/g, '').slice(0, 10);
});

async function init() {
    if (!SLUG) { mostrarNoEncontrado(); return; }
    try {
        const resp = await fetch(`${API}?action=obtener_publico&slug=${encodeURIComponent(SLUG)}`);
        const res = await resp.json();
        if (!res.ok) { mostrarNoEncontrado(); return; }
        document.getElementById('titulo-evento').textContent = res.registro.titulo;
        document.getElementById('titulo-exito').textContent  = res.registro.titulo;
        document.getElementById('loading').classList.add('hidden');
        document.getElementById('form-wrap').classList.remove('hidden');
    } catch (e) { mostrarNoEncontrado(); }
}

function mostrarNoEncontrado() {
    document.getElementById('loading').classList.add('hidden');
    document.getElementById('not-found').classList.remove('hidden');
}

function mostrarError(msg) {
    const errBox = document.getElementById('error-box');
    errBox.textContent = msg;
    errBox.style.display = 'block';
}

async function continuarConCelular() {
    const celular = document.getElementById('f-celular').value.trim();
    document.getElementById('error-box').style.display = 'none';
    document.getElementById('info-box').style.display = 'none';
    if (celular.length < 10) { mostrarError('Ingresa un número de celular válido (10 dígitos).'); return; }

    const btn = document.getElementById('btn-continuar');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Buscando…';
    try {
        const resp = await fetch(`${API}?action=buscar_persona&celular=${celular}`);
        const res = await resp.json();
        YA_EXISTIA = !!res.existe;
        if (res.existe) {
            document.getElementById('f-nombres').value   = res.persona.nombres;
            document.getElementById('f-apellidos').value = res.persona.apellidos;
            document.getElementById('info-box').textContent = 'Encontramos tu registro. Confirma o edita tus datos.';
            document.getElementById('info-box').style.display = 'block';
        } else {
            document.getElementById('f-nombres').value = '';
            document.getElementById('f-apellidos').value = '';
        }
        document.getElementById('paso-celular').classList.add('hidden');
        document.getElementById('paso-datos').classList.remove('hidden');
    } catch (e) {
        mostrarError('Error de conexión. Intenta de nuevo.');
    } finally {
        btn.disabled = false; btn.innerHTML = '<i class="fas fa-arrow-right"></i> Continuar';
    }
}

function volverACelular() {
    document.getElementById('paso-datos').classList.add('hidden');
    document.getElementById('paso-celular').classList.remove('hidden');
    document.getElementById('info-box').style.display = 'none';
}

async function enviarRegistro() {
    const nombres   = document.getElementById('f-nombres').value.trim();
    const apellidos = document.getElementById('f-apellidos').value.trim();
    const lada      = document.getElementById('f-lada').value;
    const celular   = document.getElementById('f-celular').value.trim();
    const btn       = document.getElementById('btn-enviar');

    document.getElementById('error-box').style.display = 'none';
    if (!nombres || !apellidos) { mostrarError('Nombre(s) y apellido(s) son obligatorios.'); return; }

    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando…';
    try {
        const resp = await fetch(`${API}?action=guardar_registro`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ slug: SLUG, nombres, apellidos, lada, celular })
        });
        const res = await resp.json();
        if (res.ok) {
            document.getElementById('form-wrap').classList.add('hidden');
            document.getElementById('success-box').style.display = 'block';
        } else {
            mostrarError(res.msg || 'No se pudo completar el registro.');
        }
    } catch (e) {
        mostrarError('Error de conexión. Intenta de nuevo.');
    } finally {
        btn.disabled = false; btn.innerHTML = '<i class="fas fa-check"></i> Confirmar registro';
    }
}

init();
</script>
</body>
</html>