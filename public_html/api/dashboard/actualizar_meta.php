<?php
// actualizar_meta.php
if (file_exists(__DIR__ . '/../../includes/bootstrap.php')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
} else { session_start(); }

header('Content-Type: application/json');

$rol = $_SESSION['user_role'] ?? '';

// 1. SOLUCIÓN: Se agregó 'coordinador_estatal' a los roles permitidos
$permitidos = ['admin', 'administrador', 'superadmin', 'super admin', 'circunscripcion', 'coordinador_estatal'];

if (!in_array($rol, $permitidos)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'No autorizado para cambiar metas']);
    exit();
}

// ── Conexión PDO (por si no viene en el bootstrap) ──
try {
    global $pdo;
    if (!isset($pdo)) {
        $dbCfg = __DIR__ . '/../../includes/database.php';
        $cfg = file_exists($dbCfg) ? require $dbCfg : ['host'=>'localhost','database'=>'u881824383_pverde','username'=>'u881824383_proyverde','password'=>'PProyectoverde8#','charset'=>'utf8mb4'];
        $pdo = new PDO("mysql:host={$cfg['host']};dbname={$cfg['database']};charset={$cfg['charset']}", $cfg['username'], $cfg['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    }
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'msg' => 'Error de conexión: ' . $e->getMessage()]);
    exit();
}

// Leer los datos JSON que envía el Dashboard mediante fetch()
$data = json_decode(file_get_contents('php://input'), true);

// Si no viene por JSON, intentamos por POST tradicional
if (!$data) {
    $data = $_POST;
}

// 2. Extraemos el estado y la meta TOTAL que ahora envía el frontend
$estado = trim($data['estado'] ?? '');
$meta   = (int)($data['meta'] ?? 0);

// 3. Hacemos la distribución matemática en el backend para conservar la estructura de tu BD
$meta_voto = (int)round($meta * 0.45);
$meta_com  = (int)round($meta * 0.35);
$meta_org  = (int)round($meta * 0.20);

// Validaciones
if (empty($estado)) {
    echo json_encode(['ok' => false, 'msg' => 'Falta especificar el estado a modificar.']);
    exit();
}

if ($meta <= 0) {
    echo json_encode(['ok' => false, 'msg' => 'La meta total debe ser mayor a 0.']);
    exit();
}

try {
    $stCheck = $pdo->prepare("SELECT id FROM metas_estados WHERE estado = ? LIMIT 1");
    $stCheck->execute([$estado]);
    $existe = $stCheck->fetchColumn();

    if ($existe) {
        $stUpd = $pdo->prepare("UPDATE metas_estados SET meta=?, meta_voto=?, meta_com=?, meta_org=?, updated_at=CURRENT_TIMESTAMP WHERE estado=?");
        $stUpd->execute([$meta, $meta_voto, $meta_com, $meta_org, $estado]);
    } else {
        $stIns = $pdo->prepare("INSERT INTO metas_estados (estado, meta, meta_voto, meta_com, meta_org) VALUES (?,?,?,?,?)");
        $stIns->execute([$estado, $meta, $meta_voto, $meta_com, $meta_org]);
    }

    echo json_encode(['ok' => true, 'msg' => 'Meta actualizada correctamente.']);

} catch (Exception $e) {
    echo json_encode(['ok' => false, 'msg' => 'Error en la base de datos: ' . $e->getMessage()]);
}