<?php
// api/agenda/procesar.php

if (file_exists(__DIR__ . '/../../includes/bootstrap.php')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
} else { session_start(); }

$rol      = $_SESSION['user_role'] ?? '';
$user_id  = $_SESSION['user_id']   ?? null;

// 1. Agregamos 'coordinador_estatal' a los permitidos
$permitidos = ['admin','administrador','superadmin','super admin', 'coordinador_estatal'];

if (!in_array($rol, $permitidos)) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'msg'=>'Sin permisos']);
    exit();
}

// ── Conexión PDO ───────────────────────────────────────────
try {
    $dbConfigPath = __DIR__ . '/../../includes/database.php';
    $dbConfig = file_exists($dbConfigPath) ? require $dbConfigPath : [
        'host'      => 'localhost',
        'database' => 'u881824383_pverde',
        'username' => 'u881824383_proyverde',
        'password' => 'PProyectoverde8#',
        'charset'   => 'utf8mb4',
        'options'   => [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    ];
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options'] ?? []);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'msg'=>'DB error: '.$e->getMessage()]);
    exit();
}

header('Content-Type: application/json');
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// 2. Si es coordinador, obtenemos su estado asignado
$estado_coordinador = null;
if ($rol === 'coordinador_estatal') {
    $stmt = $pdo->prepare("SELECT estado FROM coordinador_estatal WHERE id = ?");
    $stmt->execute([$user_id]);
    $estado_coordinador = $stmt->fetchColumn();
    
    if (!$estado_coordinador) {
        echo json_encode(['ok'=>false,'msg'=>'Coordinador sin estado asignado']);
        exit();
    }
}

// ── Funciones Auxiliares ────────────────────────────────────
function generarCodigo(PDO $pdo): string {
    $prefix = 'AGE-' . date('ymd') . '-';
    $stmt = $pdo->prepare("SELECT codigo_referencia FROM agenda_eventos WHERE codigo_referencia LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $ultimo = $stmt->fetchColumn();
    $num = $ultimo ? (intval(substr($ultimo, -3)) + 1) : 1;
    return $prefix . str_pad($num, 3, '0', STR_PAD_LEFT);
}

function calcularHoras(?string $ini, ?string $fin, $horasPost): float {
    $h = floatval($horasPost);
    if (!$h && $ini && $fin) {
        [$hi, $mi] = array_map('intval', explode(':', $ini));
        [$hf, $mf] = array_map('intval', explode(':', $fin));
        $diff = ($hf * 60 + $mf) - ($hi * 60 + $mi);
        if ($diff > 0) $h = round($diff / 60, 1);
    }
    return $h ?: 1.0;
}

// ── CREATE ─────────────────────────────────────────────────
if ($action === 'create') {
    $titulo = trim($_POST['titulo'] ?? '');
    $fecha  = $_POST['fecha'] ?? '';
    $eje    = $_POST['eje']   ?? '';
    $estado_post = trim($_POST['estado'] ?? '');

    // Seguridad: Si es coordinador, forzamos que el estado sea el suyo
    if ($estado_coordinador && $estado_post !== $estado_coordinador) {
        $estado_post = $estado_coordinador; 
    }

    if (!$titulo || !$fecha || !$eje) { echo json_encode(['ok'=>false,'msg'=>'Faltan campos obligatorios']); exit(); }

    $ini   = $_POST['inicio'] ?: null;
    $fin   = $_POST['fin']    ?: null;
    $horas = calcularHoras($ini, $fin, $_POST['horas'] ?? 0);
    $codigo = generarCodigo($pdo);

    $lat = isset($_POST['lat']) && $_POST['lat'] !== '' ? floatval($_POST['lat']) : null;
    $lng = isset($_POST['lng']) && $_POST['lng'] !== '' ? floatval($_POST['lng']) : null;

    $stmt = $pdo->prepare("
        INSERT INTO agenda_eventos 
        (codigo_referencia, eje, subtipo, titulo, fecha, estado, municipio, seccion, inicio, fin, horas, responsable, notas, lat, lng, creado_por_id, creado_por_rol, activo)
        VALUES 
        (:codigo, :eje, :subtipo, :titulo, :fecha, :estado, :municipio, :seccion, :inicio, :fin, :horas, :responsable, :notas, :lat, :lng, :creado_por_id, :creado_por_rol, 1)
    ");
    $stmt->execute([
        ':codigo' => $codigo, ':eje' => $eje, ':subtipo' => trim($_POST['subtipo'] ?? ''),
        ':titulo' => $titulo, ':fecha' => $fecha, ':estado' => $estado_post,
        ':municipio' => trim($_POST['municipio'] ?? ''), ':seccion' => trim($_POST['seccion'] ?? ''),
        ':inicio' => $ini, ':fin' => $fin, ':horas' => $horas,
        ':responsable' => trim($_POST['responsable'] ?? ''), ':notas' => trim($_POST['notas'] ?? ''),
        ':lat' => $lat, ':lng' => $lng,
        ':creado_por_id' => $user_id, ':creado_por_rol' => $rol
    ]);
    echo json_encode(['ok'=>true, 'id'=>(int)$pdo->lastInsertId(), 'codigo'=>$codigo]);
    exit();
}

// ── UPDATE ─────────────────────────────────────────────────
if ($action === 'update') {
    $id = intval($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['ok'=>false,'msg'=>'ID requerido']); exit(); }

    // Seguridad: Verificar que el evento pertenece al estado del coordinador
    if ($estado_coordinador) {
        $check = $pdo->prepare("SELECT estado FROM agenda_eventos WHERE id = ? AND activo = 1");
        $check->execute([$id]);
        if ($check->fetchColumn() !== $estado_coordinador) {
            echo json_encode(['ok'=>false,'msg'=>'No puedes editar eventos de otros estados']);
            exit();
        }
    }

    $ini   = $_POST['inicio'] ?: null;
    $fin   = $_POST['fin']    ?: null;
    $horas = calcularHoras($ini, $fin, $_POST['horas'] ?? 0);

    $stmt = $pdo->prepare("
        UPDATE agenda_eventos SET
            eje = :eje, subtipo = :subtipo, titulo = :titulo, fecha = :fecha,
            estado = :estado, municipio = :municipio, seccion = :seccion,
            inicio = :inicio, fin = :fin, horas = :horas, responsable = :responsable, notas = :notas
        WHERE id = :id AND activo = 1
    ");
    $stmt->execute([
        ':eje' => $_POST['eje'] ?? 'voto', ':subtipo' => trim($_POST['subtipo'] ?? ''),
        ':titulo' => trim($_POST['titulo'] ?? ''), ':fecha' => $_POST['fecha'] ?? '',
        ':estado' => $estado_coordinador ?: trim($_POST['estado'] ?? ''),
        ':municipio' => trim($_POST['municipio'] ?? ''), ':seccion' => trim($_POST['seccion'] ?? ''),
        ':inicio' => $ini, ':fin' => $fin, ':horas' => $horas,
        ':responsable' => trim($_POST['responsable'] ?? ''), ':notas' => trim($_POST['notas'] ?? ''),
        ':id' => $id
    ]);
    echo json_encode(['ok'=>true]);
    exit();
}

// ── DELETE ─────────────────────────────────────────────────
if ($action === 'delete') {
    $id = intval($_POST['id'] ?? 0);
    
    // Seguridad: Verificar estado antes de borrar
    if ($estado_coordinador) {
        $check = $pdo->prepare("SELECT estado FROM agenda_eventos WHERE id = ?");
        $check->execute([$id]);
        if ($check->fetchColumn() !== $estado_coordinador) {
            echo json_encode(['ok'=>false,'msg'=>'No tienes permiso para eliminar este evento']);
            exit();
        }
    }

    $pdo->prepare("UPDATE agenda_eventos SET activo=0 WHERE id=:id")->execute([':id'=>$id]);
    echo json_encode(['ok'=>true]);
    exit();
}

// ── LIST ───────────────────────────────────────────────────
if ($action === 'list') {
    // 3. Filtrado dinámico: Si es coordinador, solo ve su estado. Si es admin, ve todo.
    if ($estado_coordinador) {
        $stmt = $pdo->prepare("SELECT * FROM agenda_eventos WHERE activo=1 AND estado = ? ORDER BY fecha, inicio");
        $stmt->execute([$estado_coordinador]);
    } else {
        $stmt = $pdo->query("SELECT * FROM agenda_eventos WHERE activo=1 ORDER BY fecha, inicio");
    }
    
    echo json_encode(['ok'=>true,'eventos'=>$stmt->fetchAll()]);
    exit();
}

echo json_encode(['ok'=>false,'msg'=>'Acción no reconocida']);