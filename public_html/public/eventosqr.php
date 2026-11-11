<?php
/* ============================================================
   EVENTOS QR - Panel de administración
   Crea eventos, genera automáticamente su liga de invitación
   y su código QR. Los registros de cada evento se guardan en
   la tabla "formseventos".
   ============================================================ */

$bootstrapCandidatos = [
    __DIR__ . '/includes/bootstrap.php',
    __DIR__ . '/../includes/bootstrap.php',
    __DIR__ . '/../../includes/bootstrap.php',
];
$bootCargado = false;
foreach ($bootstrapCandidatos as $bp) {
    if (file_exists($bp)) { require_once $bp; $bootCargado = true; break; }
}
if (!$bootCargado) { session_start(); }

// Si tu bootstrap.php usa otro nombre de variable para la conexión PDO,
// ajusta esta línea (por ejemplo: $pdo = $conexion;)
if (!isset($pdo) || !($pdo instanceof PDO)) {
    die('No se encontró la conexión a la base de datos ($pdo). Revisa la ruta de includes/bootstrap.php dentro de eventosqr.php.');
}

$rol = $_SESSION['user_role'] ?? '';
$permitidos = ['admin','administrador','superadmin','super admin','circunscripcion'];

// ── ACCESO TEMPORALMENTE ABIERTO (sin login) ─────────────────────────────
// Cuando quieras volver a exigir sesión iniciada, descomenta este bloque:
// if (!in_array($rol, $permitidos)) {
//     header('Location: /public/index.php');
//     exit();
// }

$usuarioActual = $_SESSION['user_name'] ?? $_SESSION['user_email'] ?? ($rol ?: 'Sistema');

// ── Crear tablas si no existen ──────────────────────────────────────────────
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

// ── Helpers ──────────────────────────────────────────────────────────────────
function slugificar(string $texto): string {
    $texto = trim($texto);
    $texto = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
    $texto = strtolower($texto);
    $texto = preg_replace('/[^a-z0-9]+/', '-', $texto);
    $texto = trim($texto, '-');
    return $texto !== '' ? $texto : 'evento';
}

function baseUrlActual(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir    = rtrim(str_replace('\\', '/', dirname($_SERVER['PHP_SELF'])), '/');
    return $scheme . '://' . $host . $dir;
}

function qrUrl(string $data, int $size = 240): string {
    return 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size . '&data=' . urlencode($data);
}

// ── EXPORTAR REGISTROS A EXCEL (descarga directa, sin JSON) ──────────────────
if (isset($_GET['exportar'])) {
    $idExp = (int)$_GET['exportar'];

    $stmtE = $pdo->prepare("SELECT * FROM eventos_qr WHERE id = ?");
    $stmtE->execute([$idExp]);
    $eventoExp = $stmtE->fetch(PDO::FETCH_ASSOC);
    if (!$eventoExp) { die('Evento no encontrado.'); }

    $stmtR = $pdo->prepare("SELECT nombres, apellidos, lada, celular, fecha_registro FROM formseventos WHERE evento_id = ? ORDER BY fecha_registro DESC");
    $stmtR->execute([$idExp]);
    $registrosExp = $stmtR->fetchAll(PDO::FETCH_ASSOC);

    $nombreArchivo = 'registros-' . slugificar($eventoExp['titulo']) . '.xls';

    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo "\xEF\xBB\xBF"; // BOM para que Excel muestre bien acentos/ñ
    echo '<table border="1">';
    echo '<tr>'
       . '<th>Nombre(s)</th>'
       . '<th>Apellido(s)</th>'
       . '<th>Celular</th>'
       . '<th>Fecha de registro</th>'
       . '</tr>';

    foreach ($registrosExp as $r) {
        echo '<tr>'
           . '<td>' . htmlspecialchars($r['nombres']) . '</td>'
           . '<td>' . htmlspecialchars($r['apellidos']) . '</td>'
           . '<td>' . htmlspecialchars($r['lada'] . ' ' . $r['celular']) . '</td>'
           . '<td>' . htmlspecialchars(date('d/m/Y H:i', strtotime($r['fecha_registro']))) . '</td>'
           . '</tr>';
    }

    echo '</table>';
    exit;
}

// ── HANDLER AJAX (mismo archivo) ─────────────────────────────────────────────
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $accion = $body['accion'] ?? '';
    $datos  = $body['datos']  ?? [];

    try {
        switch ($accion) {

            case 'crear_evento':
                $titulo = trim($datos['titulo'] ?? '');
                if ($titulo === '') throw new Exception('El título del evento es obligatorio.');

                $slugBase = slugificar($titulo);
                $slug     = $slugBase;
                $i        = 2;
                $chk = $pdo->prepare("SELECT COUNT(*) FROM eventos_qr WHERE slug = ?");
                while (true) {
                    $chk->execute([$slug]);
                    if ((int)$chk->fetchColumn() === 0) break;
                    $slug = $slugBase . '-' . $i;
                    $i++;
                }

                $link = baseUrlActual() . '/forms-evento.php?slug=' . $slug;

                $stmt = $pdo->prepare("INSERT INTO eventos_qr (titulo, slug, link, creado_por) VALUES (?,?,?,?)");
                $stmt->execute([$titulo, $slug, $link, $usuarioActual]);

                echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
                break;

            case 'eliminar_evento':
                $id = (int)($datos['id'] ?? 0);
                if (!$id) throw new Exception('Evento inválido.');
                $pdo->prepare("DELETE FROM formseventos WHERE evento_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM eventos_qr WHERE id = ?")->execute([$id]);
                echo json_encode(['success' => true]);
                break;

            case 'obtener_detalles':
                $id = (int)($datos['id'] ?? 0);
                if (!$id) throw new Exception('Evento inválido.');

                $stmtE = $pdo->prepare("SELECT * FROM eventos_qr WHERE id = ?");
                $stmtE->execute([$id]);
                $evento = $stmtE->fetch(PDO::FETCH_ASSOC);
                if (!$evento) throw new Exception('Evento no encontrado.');

                $stmtR = $pdo->prepare("SELECT nombres, apellidos, lada, celular, fecha_registro FROM formseventos WHERE evento_id = ? ORDER BY fecha_registro DESC");
                $stmtR->execute([$id]);
                $registros = $stmtR->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['success' => true, 'evento' => $evento, 'registros' => $registros, 'total' => count($registros)]);
                break;

            default:
                echo json_encode(['success' => false, 'error' => 'Acción no reconocida']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ── Cargar eventos para el render inicial ────────────────────────────────────
$eventos = [];
try {
    $stmt = $pdo->query("
        SELECT e.*, (SELECT COUNT(*) FROM formseventos f WHERE f.evento_id = e.id) AS total_registros
        FROM eventos_qr e
        ORDER BY e.fecha_creacion DESC
    ");
    $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { /* silencioso */ }
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Eventos QR</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
    --primary:#2d7a3e; --primary-dark:#1f5a2d; --dark:#1a1a1a; --light:#f0f7f0;
    --white:#ffffff; --gray-100:#f8f9fa; --gray-200:#e9ecef; --text-muted:#6c757d;
    --shadow-sm:0 10px 30px rgba(0,0,0,0.05);
}
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'Arial',sans-serif; background:var(--light); color:var(--dark); overflow-x:hidden; }
.container { max-width:1200px; margin:0 auto; padding:50px 30px 60px; }
.page-header { display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:20px; margin-bottom:30px; }
.ai-badge { color:var(--primary); font-size:13px; font-weight:800; letter-spacing:4px; margin-bottom:15px; display:inline-block; text-transform:uppercase; background:rgba(45,122,62,0.1); padding:5px 15px; border-radius:20px; }
.main-title { font-size:44px; font-weight:900; line-height:1; margin-bottom:10px; color:var(--dark); }
.description { color:#666; font-size:15px; line-height:1.6; max-width:600px; }

.btn-primary-action { background:var(--primary); color:white; border:none; padding:12px 25px; border-radius:12px; font-weight:700; cursor:pointer; transition:all 0.3s; display:inline-flex; align-items:center; gap:10px; font-size:14px; box-shadow:0 4px 15px rgba(45,122,62,0.3); font-family:Arial,sans-serif; }
.btn-primary-action:hover { background:var(--primary-dark); transform:translateY(-2px); }
.btn-secondary-action { background:var(--white); color:var(--text-muted); border:1px solid var(--gray-200); padding:10px 18px; border-radius:10px; font-weight:600; cursor:pointer; transition:0.3s; display:inline-flex; align-items:center; gap:8px; font-size:13px; font-family:Arial,sans-serif; }
.btn-secondary-action:hover { border-color:var(--dark); color:var(--dark); }

.table-card { background:var(--white); border:1px solid var(--gray-200); border-radius:20px; box-shadow:var(--shadow-sm); overflow:hidden; }
table.eventos-table { width:100%; border-collapse:collapse; }
table.eventos-table thead th { text-align:left; font-size:12px; font-weight:800; text-transform:uppercase; letter-spacing:0.5px; color:var(--text-muted); background:var(--gray-100); padding:16px 20px; border-bottom:1px solid var(--gray-200); }
table.eventos-table tbody td { padding:16px 20px; border-bottom:1px solid var(--gray-200); font-size:14px; vertical-align:middle; }
table.eventos-table tbody tr:last-child td { border-bottom:none; }
table.eventos-table tbody tr:hover { background:var(--gray-100); }

.evento-titulo { font-weight:700; color:var(--dark); font-size:15px; }
.evento-fecha { font-size:12px; color:var(--text-muted); margin-top:2px; }
.evento-badge-count { display:inline-flex; align-items:center; gap:6px; font-size:12px; font-weight:700; color:var(--primary); background:rgba(45,122,62,0.1); padding:4px 10px; border-radius:20px; margin-top:6px; }

.qr-thumb { width:56px; height:56px; border-radius:10px; border:1px solid var(--gray-200); }

.link-box { display:flex; align-items:center; gap:8px; max-width:260px; }
.link-text { font-size:12px; color:var(--text-muted); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-family:monospace; background:var(--gray-100); padding:6px 10px; border-radius:8px; flex:1; }
.copy-btn { background:var(--gray-100); border:1px solid var(--gray-200); border-radius:8px; width:30px; height:30px; display:flex; align-items:center; justify-content:center; cursor:pointer; color:var(--text-muted); flex-shrink:0; transition:0.2s; }
.copy-btn:hover { background:var(--primary); color:white; border-color:var(--primary); }

.menu-wrap { position:relative; display:inline-block; }
.menu-dots { background:transparent; border:none; font-size:18px; color:var(--text-muted); cursor:pointer; width:34px; height:34px; border-radius:8px; }
.menu-dots:hover { background:var(--gray-100); color:var(--dark); }
.menu-dropdown { position:absolute; right:0; top:38px; background:var(--white); border:1px solid var(--gray-200); border-radius:12px; box-shadow:0 15px 35px rgba(0,0,0,0.12); min-width:180px; z-index:50; display:none; overflow:hidden; }
.menu-dropdown.open { display:block; }
.menu-item { padding:12px 16px; font-size:13px; font-weight:600; color:var(--dark); cursor:pointer; display:flex; align-items:center; gap:10px; transition:0.15s; }
.menu-item:hover { background:var(--gray-100); }
.menu-item.danger { color:#d32f2f; }
.menu-item.danger:hover { background:#fee2e2; }

.empty-state { text-align:center; padding:60px 20px; color:var(--text-muted); }
.empty-state i { font-size:36px; opacity:0.25; margin-bottom:12px; display:block; }

.modal { display:flex; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(255,255,255,0.9); backdrop-filter:blur(5px); z-index:2000; align-items:center; justify-content:center; opacity:0; visibility:hidden; pointer-events:none; transition:opacity 0.3s ease, visibility 0.3s ease; }
.modal.open { opacity:1; visibility:visible; pointer-events:all; }
.modal-content { background:var(--white); border-radius:20px; border:1px solid var(--gray-200); box-shadow:0 25px 50px rgba(0,0,0,0.1); width:92%; max-width:520px; padding:36px; position:relative; transform:translateY(30px); opacity:0; transition:all 0.4s ease-out; max-height:90vh; overflow-y:auto; }
.modal.open .modal-content { transform:translateY(0); opacity:1; }
.modal-content.wide { max-width:640px; }
.close-modal { position:absolute; top:22px; right:22px; font-size:20px; cursor:pointer; color:#ccc; width:38px; height:38px; display:flex; align-items:center; justify-content:center; border-radius:50%; transition:all 0.3s; }
.close-modal:hover { color:var(--primary); background:#f0f7f0; transform:rotate(90deg); }

.form-group { display:flex; flex-direction:column; gap:8px; margin-bottom:18px; }
.form-label { font-size:12px; font-weight:700; text-transform:uppercase; color:var(--text-muted); letter-spacing:0.5px; }
.form-input-styled { width:100%; padding:12px 15px; border-radius:10px; border:2px solid var(--gray-200); background:var(--gray-100); font-size:14px; font-weight:600; color:var(--dark); transition:all 0.3s; outline:none; font-family:Arial,sans-serif; }
.form-input-styled:focus { border-color:var(--primary); background:var(--white); }

.modal-footer { display:flex; justify-content:flex-end; gap:10px; margin-top:10px; padding-top:20px; border-top:1px solid var(--gray-200); }
.btn-save { background:var(--primary); color:white; border:none; padding:12px 28px; border-radius:12px; font-weight:700; cursor:pointer; font-size:14px; box-shadow:0 4px 15px rgba(45,122,62,0.3); font-family:Arial,sans-serif; }
.btn-save:hover { background:var(--primary-dark); }
.btn-cancel { background:var(--white); color:var(--text-muted); border:1px solid var(--gray-200); padding:12px 22px; border-radius:12px; font-weight:600; cursor:pointer; font-size:13px; font-family:Arial,sans-serif; }
.btn-cancel:hover { border-color:var(--dark); color:var(--dark); }

.detalle-header { text-align:center; margin-bottom:20px; }
.detalle-qr-big { width:200px; height:200px; border-radius:16px; border:1px solid var(--gray-200); margin:0 auto 14px; display:block; }
.detalle-titulo { font-size:20px; font-weight:800; color:var(--dark); }

.preview-form-box { background:var(--gray-100); border:1px dashed var(--gray-200); border-radius:14px; padding:18px; margin-bottom:20px; }
.preview-form-box .pf-label { font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:0.5px; color:var(--primary); margin-bottom:10px; }
.pf-field { background:var(--white); border:1px solid var(--gray-200); border-radius:8px; padding:10px 12px; font-size:13px; color:var(--text-muted); margin-bottom:8px; }
.pf-field:last-child { margin-bottom:0; }

.registros-title { font-size:12px; font-weight:800; text-transform:uppercase; color:var(--text-muted); letter-spacing:0.5px; margin-bottom:10px; display:flex; align-items:center; justify-content:space-between; }
.registros-list { max-height:220px; overflow-y:auto; border:1px solid var(--gray-200); border-radius:12px; }
.registro-row { padding:10px 14px; border-bottom:1px solid var(--gray-200); font-size:13px; display:flex; justify-content:space-between; gap:10px; }
.registro-row:last-child { border-bottom:none; }
.registro-nombre { font-weight:700; color:var(--dark); }
.registro-tel { color:var(--text-muted); font-size:12px; }

.toast { position:fixed; bottom:24px; right:24px; background:var(--dark); color:white; padding:12px 20px; border-radius:10px; font-size:13px; font-weight:600; z-index:3000; opacity:0; transform:translateY(10px); transition:all 0.3s; pointer-events:none; }
.toast.show { opacity:1; transform:translateY(0); }
</style>
</head>
<body>

<div class="container">
    <div class="page-header">
        <div>
            <div class="ai-badge"><i class="fas fa-qrcode"></i> &nbsp; Invitaciones</div>
            <h1 class="main-title">Eventos QR.</h1>
            <p class="description">Crea eventos, genera su liga de invitación y su código QR automáticamente.</p>
        </div>
        <button class="btn-primary-action" onclick="abrirModalEvento()"><i class="fas fa-plus"></i> Nuevo Evento</button>
    </div>

    <div class="table-card">
        <?php if (empty($eventos)): ?>
            <div class="empty-state">
                <i class="fas fa-calendar-xmark"></i>
                No hay eventos creados todavía.<br>Crea el primero con el botón "Nuevo Evento".
            </div>
        <?php else: ?>
        <table class="eventos-table">
            <thead>
                <tr>
                    <th>Evento</th>
                    <th>QR</th>
                    <th>Link de invitación</th>
                    <th style="width:50px;"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($eventos as $ev): ?>
                <tr>
                    <td>
                        <div class="evento-titulo"><?= htmlspecialchars($ev['titulo']) ?></div>
                        <div class="evento-fecha"><i class="far fa-clock"></i> <?= date('d/m/Y H:i', strtotime($ev['fecha_creacion'])) ?></div>
                        <div class="evento-badge-count"><i class="fas fa-user-check"></i> <?= (int)$ev['total_registros'] ?> registrados</div>
                    </td>
                    <td>
                        <img class="qr-thumb" src="<?= htmlspecialchars(qrUrl($ev['link'], 120)) ?>" alt="QR">
                    </td>
                    <td>
                        <div class="link-box">
                            <div class="link-text"><?= htmlspecialchars($ev['link']) ?></div>
                            <button class="copy-btn" title="Copiar link" onclick="copiarLink('<?= htmlspecialchars($ev['link'], ENT_QUOTES) ?>', this)"><i class="fas fa-copy"></i></button>
                        </div>
                    </td>
                    <td>
                        <div class="menu-wrap">
                            <button class="menu-dots" onclick="toggleMenu(event, <?= (int)$ev['id'] ?>)"><i class="fas fa-ellipsis-vertical"></i></button>
                            <div class="menu-dropdown" id="menu-<?= (int)$ev['id'] ?>">
                                <div class="menu-item" onclick="verDetalles(<?= (int)$ev['id'] ?>)"><i class="fas fa-circle-info"></i> Ver detalles</div>
                                <div class="menu-item" onclick="descargarQR('<?= htmlspecialchars($ev['link'], ENT_QUOTES) ?>', '<?= htmlspecialchars($ev['titulo'], ENT_QUOTES) ?>')"><i class="fas fa-download"></i> Descargar QR</div>
                                <a class="menu-item" href="?exportar=<?= (int)$ev['id'] ?>" style="text-decoration:none;"><i class="fas fa-file-excel"></i> Exportar a Excel</a>
                                <div class="menu-item" onclick="window.open('<?= htmlspecialchars($ev['link'], ENT_QUOTES) ?>','_blank')"><i class="fas fa-arrow-up-right-from-square"></i> Abrir invitación</div>
                                <div class="menu-item danger" onclick="eliminarEvento(<?= (int)$ev['id'] ?>)"><i class="fas fa-trash"></i> Eliminar evento</div>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: Nuevo Evento -->
<div class="modal" id="modal-evento">
    <div class="modal-content">
        <div class="close-modal" onclick="cerrarModal('modal-evento')"><i class="fas fa-times"></i></div>
        <h2 style="font-size:22px;font-weight:800;margin-bottom:5px;">Nuevo Evento</h2>
        <p style="font-size:14px;color:#999;margin-bottom:24px;">Por ahora solo se necesita el título. La liga y el QR se generan automáticamente.</p>
        <div class="form-group">
            <label class="form-label">Título del evento *</label>
            <input type="text" id="ev-titulo" class="form-input-styled" placeholder="Ej. Cena de Gala 2026">
        </div>
        <div class="modal-footer">
            <button class="btn-cancel" onclick="cerrarModal('modal-evento')">Cancelar</button>
            <button class="btn-save" onclick="crearEvento()"><i class="fas fa-check"></i> Crear evento</button>
        </div>
    </div>
</div>

<!-- Modal: Detalles -->
<div class="modal" id="modal-detalles">
    <div class="modal-content wide">
        <div class="close-modal" onclick="cerrarModal('modal-detalles')"><i class="fas fa-times"></i></div>
        <div class="detalle-header">
            <img class="detalle-qr-big" id="det-qr" src="" alt="QR">
            <div class="detalle-titulo" id="det-titulo"></div>
            <button class="btn-secondary-action" style="margin-top:14px;" id="det-download-btn" onclick="descargarQR(document.getElementById('det-link').textContent, document.getElementById('det-titulo').textContent)"><i class="fas fa-download"></i> Descargar QR</button>
        </div>
        <div class="link-box" style="max-width:100%;margin-bottom:20px;">
            <div class="link-text" id="det-link" style="white-space:normal;"></div>
            <button class="copy-btn" id="det-copy-btn" onclick="copiarLink(document.getElementById('det-link').textContent, this)"><i class="fas fa-copy"></i></button>
        </div>

        <div class="preview-form-box">
            <div class="pf-label"><i class="fas fa-eye"></i> Vista previa del formato de registro</div>
            <div class="pf-field"><i class="far fa-user"></i> &nbsp; Nombre(s)</div>
            <div class="pf-field"><i class="far fa-user"></i> &nbsp; Apellido(s)</div>
            <div class="pf-field"><i class="fas fa-mobile-screen-button"></i> &nbsp; Celular &nbsp; (+52 / +1)</div>
        </div>

        <div class="registros-title">
            <span>Registrados</span>
            <div style="display:flex;align-items:center;gap:10px;">
                <a id="det-export-link" href="#" class="btn-secondary-action" style="padding:6px 12px;font-size:11px;text-decoration:none;"><i class="fas fa-file-excel"></i> Exportar</a>
                <span id="det-total" style="color:var(--primary);"></span>
            </div>
        </div>
        <div class="registros-list" id="det-registros"></div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
let menuAbierto = null;

function abrirModal(id) { document.getElementById(id).classList.add('open'); }
function cerrarModal(id) { document.getElementById(id).classList.remove('open'); }

function abrirModalEvento() {
    document.getElementById('ev-titulo').value = '';
    abrirModal('modal-evento');
    setTimeout(() => document.getElementById('ev-titulo').focus(), 200);
}

function mostrarToast(msg) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 2200);
}

function copiarLink(link, btn) {
    navigator.clipboard.writeText(link).then(() => {
        const original = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i>';
        mostrarToast('Link copiado al portapapeles');
        setTimeout(() => btn.innerHTML = original, 1500);
    });
}

function toggleMenu(evt, id) {
    evt.stopPropagation();
    const el = document.getElementById('menu-' + id);
    if (menuAbierto && menuAbierto !== el) menuAbierto.classList.remove('open');
    el.classList.toggle('open');
    menuAbierto = el.classList.contains('open') ? el : null;
}
document.addEventListener('click', () => {
    if (menuAbierto) { menuAbierto.classList.remove('open'); menuAbierto = null; }
});

async function crearEvento() {
    const titulo = document.getElementById('ev-titulo').value.trim();
    if (!titulo) { alert('El título es obligatorio.'); return; }

    try {
        const resp = await fetch(window.location.pathname + '?ajax=1', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ accion: 'crear_evento', datos: { titulo } })
        });
        const res = await resp.json();
        if (res.success) {
            location.reload();
        } else {
            alert('Error: ' + (res.error || 'No se pudo crear el evento.'));
        }
    } catch (e) {
        alert('Error de conexión.');
    }
}

async function eliminarEvento(id) {
    if (!confirm('¿Eliminar este evento y todos sus registros? Esta acción no se puede deshacer.')) return;
    try {
        const resp = await fetch(window.location.pathname + '?ajax=1', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ accion: 'eliminar_evento', datos: { id } })
        });
        const res = await resp.json();
        if (res.success) { location.reload(); } else { alert('Error: ' + (res.error || 'No se pudo eliminar.')); }
    } catch (e) { alert('Error de conexión.'); }
}

async function verDetalles(id) {
    try {
        const resp = await fetch(window.location.pathname + '?ajax=1', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ accion: 'obtener_detalles', datos: { id } })
        });
        const res = await resp.json();
        if (!res.success) { alert('Error: ' + (res.error || 'No se pudo cargar el evento.')); return; }

        const ev = res.evento;
        document.getElementById('det-titulo').textContent = ev.titulo;
        document.getElementById('det-link').textContent = ev.link;
        document.getElementById('det-qr').src = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' + encodeURIComponent(ev.link);
        document.getElementById('det-total').textContent = res.total + ' persona' + (res.total === 1 ? '' : 's');
        document.getElementById('det-export-link').href = '?exportar=' + id;

        const cont = document.getElementById('det-registros');
        if (res.registros.length === 0) {
            cont.innerHTML = '<div style="padding:20px;text-align:center;color:var(--text-muted);font-size:13px;">Aún no hay registros.</div>';
        } else {
            cont.innerHTML = res.registros.map(r => `
                <div class="registro-row">
                    <span class="registro-nombre">${escHtml(r.nombres)} ${escHtml(r.apellidos)}</span>
                    <span class="registro-tel">${escHtml(r.lada)} ${escHtml(r.celular)}</span>
                </div>
            `).join('');
        }
        abrirModal('modal-detalles');
    } catch (e) {
        alert('Error de conexión.');
    }
}

async function descargarQR(link, titulo) {
    const qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=500x500&data=' + encodeURIComponent(link);
    const nombreArchivo = 'qr-' + (titulo || 'evento').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '') + '.png';

    try {
        const resp = await fetch(qrUrl);
        const blob = await resp.blob();
        const objectUrl = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = objectUrl;
        a.download = nombreArchivo;
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(objectUrl);
    } catch (e) {
        // Si el navegador bloquea el fetch cross-origin, abrimos la imagen para guardarla manualmente
        window.open(qrUrl, '_blank');
        mostrarToast('Clic derecho en la imagen y elige "Guardar imagen como..."');
    }
}

function escHtml(str) {
    const d = document.createElement('div');
    d.textContent = str ?? '';
    return d.innerHTML;
}
</script>
</body>
</html>