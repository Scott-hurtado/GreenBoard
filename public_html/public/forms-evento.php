<?php
/* ============================================================
   FORMS-EVENTO - Página pública de registro de invitados
   Se abre desde el link / QR generado en eventosqr.php
   No requiere sesión iniciada: cualquier invitado puede entrar.
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

if (!isset($pdo) || !($pdo instanceof PDO)) {
    die('No se encontró la conexión a la base de datos ($pdo). Revisa la ruta de includes/bootstrap.php dentro de forms-evento.php.');
}

// Aseguramos que las tablas existan (por si este archivo se abre antes que eventosqr.php)
$pdo->exec("CREATE TABLE IF NOT EXISTS eventos_qr (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    link VARCHAR(500) NOT NULL,
    creado_por VARCHAR(150) DEFAULT NULL,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS formseventos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evento_id INT NOT NULL,
    nombres VARCHAR(150) NOT NULL,
    apellidos VARCHAR(150) NOT NULL,
    lada VARCHAR(5) NOT NULL DEFAULT '+52',
    celular VARCHAR(20) NOT NULL,
    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_evento (evento_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── HANDLER AJAX: guardar registro ───────────────────────────────────────────
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    $body  = json_decode(file_get_contents('php://input'), true) ?? [];
    $slug  = trim($body['slug'] ?? '');

    try {
        $stmtE = $pdo->prepare("SELECT id FROM eventos_qr WHERE slug = ?");
        $stmtE->execute([$slug]);
        $evento = $stmtE->fetch(PDO::FETCH_ASSOC);
        if (!$evento) throw new Exception('Evento no encontrado.');

        $nombres   = trim($body['nombres']   ?? '');
        $apellidos = trim($body['apellidos'] ?? '');
        $lada      = trim($body['lada']      ?? '+52');
        $celular   = preg_replace('/\D+/', '', $body['celular'] ?? '');

        if ($nombres === '' || $apellidos === '') throw new Exception('Nombre(s) y apellido(s) son obligatorios.');
        if (!in_array($lada, ['+52', '+1'])) $lada = '+52';
        if (strlen($celular) < 10) throw new Exception('Ingresa un número de celular válido (10 dígitos).');

        $stmt = $pdo->prepare("INSERT INTO formseventos (evento_id, nombres, apellidos, lada, celular) VALUES (?,?,?,?,?)");
        $stmt->execute([$evento['id'], $nombres, $apellidos, $lada, $celular]);

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ── Cargar evento por slug ────────────────────────────────────────────────
$slug   = trim($_GET['slug'] ?? '');
$evento = null;
if ($slug !== '') {
    $stmt = $pdo->prepare("SELECT * FROM eventos_qr WHERE slug = ?");
    $stmt->execute([$slug]);
    $evento = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $evento ? htmlspecialchars($evento['titulo']) : 'Registro de invitado' ?></title>
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
h1 { font-size:26px; font-weight:900; margin-bottom:8px; }
.subtitle { color:var(--text-muted); font-size:14px; margin-bottom:26px; }

.form-group { display:flex; flex-direction:column; gap:6px; margin-bottom:16px; text-align:left; }
.form-label { font-size:12px; font-weight:700; text-transform:uppercase; color:var(--text-muted); letter-spacing:0.5px; }
.form-input-styled { width:100%; padding:13px 15px; border-radius:12px; border:2px solid var(--gray-200); background:var(--gray-100); font-size:15px; font-weight:600; color:var(--dark); outline:none; transition:0.3s; font-family:Arial,sans-serif; }
.form-input-styled:focus { border-color:var(--primary); background:var(--white); }

.tel-row { display:flex; gap:8px; }
.tel-row select { flex:0 0 90px; }
.tel-row input { flex:1; }

.btn-submit { width:100%; background:var(--primary); color:white; border:none; padding:15px; border-radius:12px; font-weight:800; font-size:15px; cursor:pointer; margin-top:8px; box-shadow:0 6px 18px rgba(45,122,62,0.3); transition:0.3s; font-family:Arial,sans-serif; }
.btn-submit:hover { background:var(--primary-dark); transform:translateY(-2px); }
.btn-submit:disabled { opacity:0.6; cursor:not-allowed; transform:none; }

.error-box { background:#fee2e2; color:#d32f2f; border:1px solid #fecaca; padding:10px 14px; border-radius:10px; font-size:13px; font-weight:600; margin-bottom:16px; display:none; text-align:left; }

.success-box { display:none; text-align:center; }
.success-box i { font-size:52px; color:var(--primary); margin-bottom:16px; display:block; }
.success-box h2 { font-size:22px; font-weight:900; margin-bottom:8px; }
.success-box p { color:var(--text-muted); font-size:14px; }

.not-found i { font-size:44px; color:var(--text-muted); opacity:0.4; margin-bottom:14px; display:block; }
</style>
</head>
<body>

<div class="card">
    <?php if (!$evento): ?>
        <div class="not-found">
            <i class="fas fa-calendar-xmark"></i>
            <h1>Evento no encontrado</h1>
            <p class="subtitle">La liga de invitación no es válida o el evento ya no está disponible.</p>
        </div>
    <?php else: ?>
        <div id="form-wrap">
            <div class="badge"><i class="fas fa-star"></i> &nbsp; Invitación</div>
            <h1><?= htmlspecialchars($evento['titulo']) ?></h1>
            <p class="subtitle">Completa tus datos para registrar tu asistencia.</p>

            <div class="error-box" id="error-box"></div>

            <div class="form-group">
                <label class="form-label">Nombre(s) *</label>
                <input type="text" id="f-nombres" class="form-input-styled" placeholder="Ej. Juan Carlos">
            </div>
            <div class="form-group">
                <label class="form-label">Apellido(s) *</label>
                <input type="text" id="f-apellidos" class="form-input-styled" placeholder="Ej. Pérez García">
            </div>
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

            <button class="btn-submit" id="btn-enviar" onclick="enviarRegistro()"><i class="fas fa-check"></i> Confirmar registro</button>
        </div>

        <div class="success-box" id="success-box">
            <i class="fas fa-circle-check"></i>
            <h2>¡Registro exitoso!</h2>
            <p>Gracias por confirmar tu asistencia a<br><strong><?= htmlspecialchars($evento['titulo']) ?></strong>.</p>
        </div>
    <?php endif; ?>
</div>

<?php if ($evento): ?>
<script>
const SLUG = <?= json_encode($evento['slug']) ?>;

document.getElementById('f-celular').addEventListener('input', function() {
    this.value = this.value.replace(/\D/g, '').slice(0, 10);
});

async function enviarRegistro() {
    const nombres   = document.getElementById('f-nombres').value.trim();
    const apellidos = document.getElementById('f-apellidos').value.trim();
    const lada      = document.getElementById('f-lada').value;
    const celular   = document.getElementById('f-celular').value.trim();
    const errBox    = document.getElementById('error-box');
    const btn       = document.getElementById('btn-enviar');

    errBox.style.display = 'none';

    if (!nombres || !apellidos) { mostrarError('Nombre(s) y apellido(s) son obligatorios.'); return; }
    if (celular.length < 10) { mostrarError('Ingresa un número de celular válido (10 dígitos).'); return; }

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';

    try {
        const resp = await fetch(window.location.pathname + '?ajax=1', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ slug: SLUG, nombres, apellidos, lada, celular })
        });
        const res = await resp.json();

        if (res.success) {
            document.getElementById('form-wrap').style.display = 'none';
            document.getElementById('success-box').style.display = 'block';
        } else {
            mostrarError(res.error || 'No se pudo completar el registro.');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check"></i> Confirmar registro';
        }
    } catch (e) {
        mostrarError('Error de conexión. Intenta de nuevo.');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check"></i> Confirmar registro';
    }
}

function mostrarError(msg) {
    const errBox = document.getElementById('error-box');
    errBox.textContent = msg;
    errBox.style.display = 'block';
}
</script>
<?php endif; ?>
</body>
</html>