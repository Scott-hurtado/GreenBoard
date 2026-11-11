<?php
// api/alertas/procesar.php
// Detecta regiones/zonas sin actividad (evento de Agenda o Activación)
// registrada en las últimas N horas (por defecto 72h). Alcance según rol
// de sesión: circunscripción (estados > regiones > zonas), estatal
// (regiones > zonas de su estado), regional (zonas de su región).

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    if (file_exists(__DIR__ . '/../../includes/bootstrap.php')) {
        require_once __DIR__ . '/../../includes/bootstrap.php';
    } else {
        if (session_status() === PHP_SESSION_NONE) session_start();
    }

    $rol     = $_SESSION['user_role'] ?? '';
    $user_id = $_SESSION['user_id']   ?? null;

    $permitidos = ['admin','administrador','superadmin','super admin','circunscripcion','coordinador_estatal','coordinador_regional','regional'];
    if (!in_array($rol, $permitidos)) {
        http_response_code(403);
        echo json_encode(['ok'=>false,'msg'=>'Sin permisos']);
        exit();
    }

    if (!isset($pdo)) {
        $dbConfigPath = __DIR__ . '/../../includes/database.php';
        $dbConfig = file_exists($dbConfigPath) ? require $dbConfigPath : [
            'host'=>'localhost','database'=>'u881824383_pverde',
            'username'=>'u881824383_proyverde','password'=>'PProyectoverde8#',
            'charset'=>'utf8mb4',
            'options'=>[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
        ];
        $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
        $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options'] ?? []);
    }

    $action = $_GET['action'] ?? '';
    if ($action !== 'inactividad') {
        echo json_encode(['ok'=>false,'msg'=>'Acción no reconocida']);
        exit();
    }

    $horasLimite = max(1, intval($_GET['horas'] ?? 72));

    // ── Resolver alcance territorial según el rol ───────────────────
    $estadosScope = [];   // estados a considerar (circ: varios; estatal: uno)
    $regionIdPropia = 0;  // regional: solo su propia región

    if ($rol === 'circunscripcion') {
        $user_circ = $_SESSION['user_circunscripcion'] ?? '';
        $CIRCS = [
            'Circunscripción 1' => ['Baja California','Baja California Sur','Chihuahua','Durango','Jalisco','Nayarit','Sinaloa','Sonora'],
            'Circunscripción 2' => ['Aguascalientes','Coahuila','Guanajuato','Nuevo León','San Luis Potosí','Tamaulipas','Zacatecas'],
            'Circunscripción 3' => ['Campeche','Chiapas','Oaxaca','Quintana Roo','Tabasco','Veracruz','Yucatán'],
            'Circunscripción 4' => ['Ciudad de México','Guerrero','Hidalgo','Morelos','Puebla','Tlaxcala'],
            'Circunscripción 5' => ['Colima','Estado de México','Michoacán','Querétaro'],
        ];
        $estadosScope = $CIRCS[$user_circ] ?? [];
    } elseif ($rol === 'coordinador_estatal') {
        $estado_actual = $_SESSION['user_estado'] ?? '';
        if ($estado_actual) $estadosScope = [$estado_actual];
    } elseif (in_array($rol, ['coordinador_regional','regional'])) {
        $user_region = $_SESSION['user_region'] ?? '';
        if (!empty($user_region)) {
            $st = $pdo->prepare("SELECT id FROM regiones_estatales WHERE id = ? LIMIT 1");
            $st->execute([$user_region]);
            $regionIdPropia = (int)($st->fetchColumn() ?: 0);
        }
        if (!$regionIdPropia) {
            $st = $pdo->prepare("SELECT id FROM regiones_estatales WHERE coordinador_id = ? LIMIT 1");
            $st->execute([$user_id]);
            $regionIdPropia = (int)($st->fetchColumn() ?: 0);
        }
    }
    // admin/superadmin sin estado/región propios: ven todo (sin filtro territorial)

    $regiones = [];
    $zonas = [];

    // ── REGIONES (circunscripción y estatal) ────────────────────────
    if ($rol === 'circunscripcion' || $rol === 'coordinador_estatal' || in_array($rol, ['admin','administrador','superadmin','super admin'])) {
        $sql = "
            SELECT reg.id, reg.nombre, reg.estado, MAX(act.fecha_creacion) AS ultima
            FROM regiones_estatales reg
            LEFT JOIN (
                SELECT estado, region, fecha_creacion FROM agenda_eventos WHERE activo = 1
                UNION ALL
                SELECT estado, region, fecha_creacion FROM activaciones WHERE activo = 1
            ) act ON act.estado = reg.estado AND act.region = reg.nombre
        ";
        $params = [];
        if (!empty($estadosScope)) {
            $in = implode(',', array_fill(0, count($estadosScope), '?'));
            $sql .= " WHERE reg.estado IN ($in)";
            $params = $estadosScope;
        }
        $sql .= " GROUP BY reg.id, reg.nombre, reg.estado ORDER BY reg.estado, reg.nombre";
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $regiones = $st->fetchAll();
    }

    // ── ZONAS (todos los roles, acotadas a su alcance) ───────────────
    $sqlZ = "
        SELECT z.id, z.nombre, z.clave, r.nombre AS region_nombre, r.estado, MAX(act.fecha_creacion) AS ultima
        FROM zonas_estatales z
        INNER JOIN regiones_estatales r ON z.estructura_id = r.id
        LEFT JOIN (
            SELECT estado, zona, fecha_creacion FROM agenda_eventos WHERE activo = 1
            UNION ALL
            SELECT estado, zona, fecha_creacion FROM activaciones WHERE activo = 1
        ) act ON act.estado = r.estado AND act.zona = z.nombre
    ";
    $paramsZ = [];
    if ($regionIdPropia) {
        $sqlZ .= " WHERE r.id = ?";
        $paramsZ = [$regionIdPropia];
    } elseif (!empty($estadosScope)) {
        $in = implode(',', array_fill(0, count($estadosScope), '?'));
        $sqlZ .= " WHERE r.estado IN ($in)";
        $paramsZ = $estadosScope;
    }
    $sqlZ .= " GROUP BY z.id, z.nombre, z.clave, r.nombre, r.estado ORDER BY r.estado, r.nombre, z.nombre";
    $stZ = $pdo->prepare($sqlZ);
    $stZ->execute($paramsZ);
    $zonas = $stZ->fetchAll();

    // ── Calcular horas de inactividad y filtrar solo las que exceden el límite ──
    $ahora = new DateTime();
    $procesar = function(array $rows) use ($ahora, $horasLimite) {
        $out = [];
        foreach ($rows as $r) {
            if (empty($r['ultima'])) {
                $horas = null; // nunca ha registrado nada
            } else {
                $ultima = new DateTime($r['ultima']);
                $horas = ($ahora->getTimestamp() - $ultima->getTimestamp()) / 3600;
            }
            if ($horas === null || $horas >= $horasLimite) {
                $r['horas_inactividad'] = $horas === null ? null : round($horas, 1);
                $r['ultima_actividad']  = $r['ultima'];
                unset($r['ultima']);
                $out[] = $r;
            }
        }
        // Más urgente primero: nunca (null) al inicio, luego más horas de inactividad
        usort($out, function($a, $b) {
            if ($a['horas_inactividad'] === null && $b['horas_inactividad'] === null) return 0;
            if ($a['horas_inactividad'] === null) return -1;
            if ($b['horas_inactividad'] === null) return 1;
            return $b['horas_inactividad'] <=> $a['horas_inactividad'];
        });
        return $out;
    };

    $regionesAlerta = $procesar($regiones);
    $zonasAlerta    = $procesar($zonas);

    echo json_encode([
        'ok' => true,
        'horas_limite' => $horasLimite,
        'regiones' => $regionesAlerta,
        'zonas'    => $zonasAlerta,
    ]);
    exit();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'msg'=>'Error en el servidor: '.$e->getMessage()]);
    exit();
}