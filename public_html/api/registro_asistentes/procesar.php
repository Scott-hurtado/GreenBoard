<?php
// api/registro_asistentes/procesar.php
// Registro/pre-registro de asistentes (liga + QR) para Eventos (agenda_eventos)
// y Activaciones (activaciones). Reutiliza el mismo esquema de scope territorial
// por rol que ya usa api/activaciones/procesar.php.

if (file_exists(__DIR__ . '/../../includes/bootstrap.php')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
} else { session_start(); }

// ── Conexión PDO ──────────────────────────────────────────
try {
    $dbConfigPath = __DIR__ . '/../../includes/database.php';
    $dbConfig = file_exists($dbConfigPath) ? require $dbConfigPath : [
        'host'     => 'localhost',
        'database' => 'u881824383_pverde',
        'username' => 'u881824383_proyverde',
        'password' => 'PProyectoverde8#',
        'charset'  => 'utf8mb4',
        'options'  => [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
                       PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
    ];
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options'] ?? []);
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['ok'=>false,'msg'=>'DB error: '.$e->getMessage()]);
    exit();
}

// ── Crear tablas si no existen ────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS personas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombres VARCHAR(150) NOT NULL,
    apellidos VARCHAR(150) NOT NULL,
    lada VARCHAR(5) NOT NULL DEFAULT '+52',
    celular VARCHAR(20) NOT NULL UNIQUE COMMENT 'Llave única de persona a nivel app',
    fecha_alta TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS registro_qr (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo_origen ENUM('evento','activacion') NOT NULL,
    origen_id INT NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    link VARCHAR(500) NOT NULL,
    fecha_actividad DATE DEFAULT NULL,
    estado VARCHAR(100) DEFAULT NULL,
    municipio VARCHAR(150) DEFAULT NULL,
    region VARCHAR(120) DEFAULT NULL,
    zona VARCHAR(120) DEFAULT NULL,
    seccion_electoral VARCHAR(255) DEFAULT NULL,
    creado_por_id INT DEFAULT NULL,
    creado_por_rol VARCHAR(50) DEFAULT NULL,
    activo TINYINT(1) DEFAULT 1,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_origen (tipo_origen, origen_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS registro_asistentes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    registro_qr_id INT NOT NULL,
    persona_id INT NOT NULL,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_qr_persona (registro_qr_id, persona_id),
    INDEX idx_qr (registro_qr_id),
    INDEX idx_persona (persona_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

header('Content-Type: application/json; charset=utf-8');

// ── Helpers ────────────────────────────────────────────────
function slugificarRA(string $texto): string {
    $texto = trim($texto);
    $texto = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
    $texto = strtolower($texto);
    $texto = preg_replace('/[^a-z0-9]+/', '-', $texto);
    $texto = trim($texto, '-');
    return $texto !== '' ? $texto : 'registro';
}
function baseUrlActualRA(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_POST['action'] ?? $_GET['action'] ?? ($body['action'] ?? '');

// ════════════════════════════════════════════════════════════
// ACCIONES PÚBLICAS (sin sesión): las usa forms-registro.php
// ════════════════════════════════════════════════════════════
if ($action === 'obtener_publico') {
    $slug = trim($_GET['slug'] ?? '');
    $stmt = $pdo->prepare("SELECT id, titulo, tipo_origen, activo FROM registro_qr WHERE slug = ?");
    $stmt->execute([$slug]);
    $r = $stmt->fetch();
    if (!$r || !$r['activo']) { echo json_encode(['ok'=>false,'msg'=>'Registro no encontrado o inactivo']); exit(); }
    echo json_encode(['ok'=>true,'registro'=>$r]);
    exit();
}

if ($action === 'buscar_persona') {
    $celular = preg_replace('/\D+/', '', $body['celular'] ?? ($_GET['celular'] ?? ''));
    if (strlen($celular) < 10) { echo json_encode(['ok'=>false,'msg'=>'Celular inválido']); exit(); }
    $stmt = $pdo->prepare("SELECT id, nombres, apellidos, lada, celular FROM personas WHERE celular = ?");
    $stmt->execute([$celular]);
    $p = $stmt->fetch();
    echo json_encode(['ok'=>true, 'existe'=>(bool)$p, 'persona'=>$p ?: null]);
    exit();
}

if ($action === 'guardar_registro') {
    try {
        $slug      = trim($body['slug'] ?? '');
        $nombres   = trim($body['nombres'] ?? '');
        $apellidos = trim($body['apellidos'] ?? '');
        $lada      = in_array($body['lada'] ?? '', ['+52','+1']) ? $body['lada'] : '+52';
        $celular   = preg_replace('/\D+/', '', $body['celular'] ?? '');

        if ($nombres === '' || $apellidos === '') throw new Exception('Nombre(s) y apellido(s) son obligatorios.');
        if (strlen($celular) < 10) throw new Exception('Ingresa un número de celular válido (10 dígitos).');

        $stmtQ = $pdo->prepare("SELECT id, activo FROM registro_qr WHERE slug = ?");
        $stmtQ->execute([$slug]);
        $qr = $stmtQ->fetch();
        if (!$qr || !$qr['activo']) throw new Exception('Este registro ya no está disponible.');

        // El celular es la llave única de persona a nivel app: si ya existe,
        // reutilizamos su id (y actualizamos nombre/apellido si los editaron
        // en el paso de confirmación), en vez de crear un duplicado.
        $stmtP = $pdo->prepare("SELECT id FROM personas WHERE celular = ?");
        $stmtP->execute([$celular]);
        $personaId = $stmtP->fetchColumn();

        if ($personaId) {
            $pdo->prepare("UPDATE personas SET nombres=?, apellidos=?, lada=? WHERE id=?")
                ->execute([$nombres, $apellidos, $lada, $personaId]);
        } else {
            $pdo->prepare("INSERT INTO personas (nombres, apellidos, lada, celular) VALUES (?,?,?,?)")
                ->execute([$nombres, $apellidos, $lada, $celular]);
            $personaId = $pdo->lastInsertId();
        }

        // Asociar (o re-confirmar) la persona a este evento/activación.
        $pdo->prepare("INSERT INTO registro_asistentes (registro_qr_id, persona_id) VALUES (?,?)
                       ON DUPLICATE KEY UPDATE fecha_registro = fecha_registro")
            ->execute([$qr['id'], $personaId]);

        echo json_encode(['ok'=>true]);
    } catch (Exception $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit();
}

// ════════════════════════════════════════════════════════════
// A partir de aquí: acciones internas (requieren sesión con rol)
// ════════════════════════════════════════════════════════════
$rol     = strtolower($_SESSION['user_role'] ?? '');
$user_id = $_SESSION['user_id'] ?? null;

$permitidos = [
    'admin','administrador','superadmin','super admin',
    'circunscripcion','coordinadores_circunscripcion','coordinador_circunscripcion',
    'coordinador_estatal',
    'coordinador_regional','regional',
    'coordinador_zona',
];
if (!in_array($rol, $permitidos)) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'msg'=>'Sin permisos']);
    exit();
}

// ── FILTROS TERRITORIALES por rol (idéntico a api/activaciones/procesar.php) ──
$estado_coord       = null;
$estados_circ       = [];
$municipios_region  = [];
$secciones_region   = [];
$secciones_zona     = [];
$user_region_nombre = '';
$user_zona_nombre   = '';

if ($rol === 'coordinador_estatal') {
    $stmt = $pdo->prepare("SELECT estado FROM coordinador_estatal WHERE id=?");
    $stmt->execute([$user_id]);
    $estado_coord = $stmt->fetchColumn() ?: ($_SESSION['user_estado'] ?? '');
    if (!$estado_coord) { echo json_encode(['ok'=>false,'msg'=>'Coordinador estatal sin estado asignado']); exit(); }

} elseif (in_array($rol, ['circunscripcion','coordinadores_circunscripcion','coordinador_circunscripcion'])) {
    $user_circ = trim($_SESSION['user_circunscripcion'] ?? '');
    $CIRCS = [
        'circunscripcion 1' => ['Baja California','Baja California Sur','Chihuahua','Durango','Jalisco','Nayarit','Sinaloa','Sonora'],
        'circunscripcion 2' => ['Aguascalientes','Coahuila','Guanajuato','Nuevo Leon','San Luis Potosi','Tamaulipas','Zacatecas'],
        'circunscripcion 3' => ['Campeche','Chiapas','Oaxaca','Quintana Roo','Tabasco','Veracruz','Yucatan'],
        'circunscripcion 4' => ['Ciudad de Mexico','Guerrero','Hidalgo','Morelos','Puebla','Tlaxcala'],
        'circunscripcion 5' => ['Colima','Estado de Mexico','Michoacan','Queretaro'],
    ];
    // Normalizamos (sin acentos, minúsculas) antes de comparar: la sesión ha
    // guardado el nombre de la circunscripción con distinta ortografía según
    // el archivo/rol que la escribió ("Circunscripción 1" vs "Circunscripcion 1"),
    // y una comparación exacta como la de api/activaciones/procesar.php es
    // frágil ante esa variación.
    $normCirc = function (string $s): string {
        $s = mb_strtolower(trim($s), 'UTF-8');
        $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        return preg_replace('/\s+/', ' ', $s);
    };
    $user_circ_norm = $normCirc($user_circ);
    $estados_circ = [];
    foreach ($CIRCS as $key => $lista) {
        if ($normCirc($key) === $user_circ_norm) { $estados_circ = $lista; break; }
    }
    if (empty($estados_circ)) { echo json_encode(['ok'=>false,'msg'=>"Circunscripcion no configurada (sesion: '$user_circ')"]); exit(); }

} elseif (in_array($rol, ['coordinador_regional','regional'])) {
    $user_region = $_SESSION['user_region'] ?? '';
    $user_estado = $_SESSION['user_estado'] ?? '';
    if ($user_region) {
        $st = $pdo->prepare("SELECT nombre, municipios FROM regiones_estatales WHERE id = ? LIMIT 1");
        $st->execute([$user_region]);
        $regRow = $st->fetch();
        if ($regRow) {
            $user_region_nombre = $regRow['nombre'];
            $municipios_region  = json_decode($regRow['municipios'] ?? '[]', true) ?: [];
        }
        $stZ = $pdo->prepare("SELECT secciones FROM zonas_estatales WHERE estructura_id = ?");
        $stZ->execute([$user_region]);
        foreach ($stZ->fetchAll(PDO::FETCH_ASSOC) as $z) {
            $secs = json_decode($z['secciones'] ?? '[]', true) ?: [];
            foreach ($secs as $sec) $secciones_region[] = (string)$sec;
        }
        $secciones_region = array_unique($secciones_region);
    }
    if (empty($municipios_region)) { $estado_coord = $user_estado; }

} elseif ($rol === 'coordinador_zona') {
    $user_zona   = $_SESSION['user_zona_nombre'] ?? '';
    if ($user_zona) {
        $st = $pdo->prepare("SELECT nombre_seccion FROM secciones_electorales WHERE TRIM(zona_asignada)=TRIM(?) AND activo=1");
        $st->execute([$user_zona]);
        $secciones_zona = $st->fetchAll(PDO::FETCH_COLUMN);
    }
    if (empty($secciones_zona)) {
        $zona_id_s = (int)($_SESSION['user_zona_id'] ?? 0);
        if ($zona_id_s > 0) {
            $stZ = $pdo->prepare("SELECT secciones FROM zonas_estatales WHERE id = ? LIMIT 1");
            $stZ->execute([$zona_id_s]);
            $secJson = $stZ->fetchColumn();
            if ($secJson) $secciones_zona = json_decode($secJson, true) ?: [];
        }
        if (empty($secciones_zona)) {
            $stCZ = $pdo->prepare("SELECT z.secciones FROM coordinador_zona cz
                                    INNER JOIN zonas_estatales z ON cz.zona = z.id WHERE cz.id = ? LIMIT 1");
            $stCZ->execute([$user_id]);
            $secJson2 = $stCZ->fetchColumn();
            if ($secJson2) $secciones_zona = json_decode($secJson2, true) ?: [];
        }
    }
}

// Condición SQL + params para restringir registro_qr al alcance del rol.
// Devuelve [sqlCondicion, params] o [null, []] si el rol ve todo (admin).
function condicionScopeRA(string $rol, ?string $estado_coord, array $estados_circ,
                           array $municipios_region, array $secciones_region,
                           array $secciones_zona): array {
    if (in_array($rol, ['admin','administrador','superadmin','super admin'])) return [null, []];

    if ($rol === 'coordinador_estatal' && $estado_coord) return ['estado = ?', [$estado_coord]];

    if (in_array($rol, ['circunscripcion','coordinadores_circunscripcion','coordinador_circunscripcion'])) {
        $ph = implode(',', array_fill(0, count($estados_circ), '?'));
        return ["estado IN ($ph)", $estados_circ];
    }

    if (in_array($rol, ['coordinador_regional','regional'])) {
        $conds = []; $params = [];
        if (!empty($municipios_region)) {
            $ph = implode(',', array_fill(0, count($municipios_region), '?'));
            $conds[] = "municipio IN ($ph)"; $params = array_merge($params, $municipios_region);
        }
        if (!empty($secciones_region)) {
            $ph = implode(',', array_fill(0, count($secciones_region), '?'));
            $conds[] = "seccion_electoral IN ($ph)"; $params = array_merge($params, $secciones_region);
        }
        if ($conds) return ['(' . implode(' OR ', $conds) . ')', $params];
        if ($estado_coord) return ['estado = ?', [$estado_coord]];
        return ['1=0', []];
    }

    if ($rol === 'coordinador_zona') {
        if (empty($secciones_zona)) return ['1=0', []];
        $ph = implode(',', array_fill(0, count($secciones_zona), '?'));
        return ["seccion_electoral IN ($ph)", $secciones_zona];
    }

    return ['1=0', []];
}

[$scopeSql, $scopeParams] = condicionScopeRA($rol, $estado_coord, $estados_circ, $municipios_region, $secciones_region, $secciones_zona);

// ── activar: crea (o reactiva) el registro_qr para un evento/activación ──
// Evalúa si un evento/activación (ya cargado) cae dentro del alcance
// jerárquico del rol que hace la petición — misma regla que condicionScopeRA
// pero contra un registro puntual en vez de una condición SQL.
function dentroDeAlcanceRA(array $origen, string $rol, ?string $estado_coord, array $estados_circ,
                            array $municipios_region, array $secciones_region, array $secciones_zona): bool {
    if (in_array($rol, ['admin','administrador','superadmin','super admin'])) return true;
    if ($rol === 'coordinador_estatal') return $estado_coord !== null && $origen['estado'] === $estado_coord;
    if (in_array($rol, ['circunscripcion','coordinadores_circunscripcion','coordinador_circunscripcion']))
        return in_array($origen['estado'], $estados_circ);
    if (in_array($rol, ['coordinador_regional','regional'])) {
        if (!empty($municipios_region) && in_array($origen['municipio'], $municipios_region)) return true;
        if (!empty($secciones_region) && in_array((string)$origen['seccion'], $secciones_region)) return true;
        if (empty($municipios_region) && empty($secciones_region) && $estado_coord) return $origen['estado'] === $estado_coord;
        return false;
    }
    if ($rol === 'coordinador_zona') return in_array((string)$origen['seccion'], $secciones_zona);
    return false;
}

if ($action === 'activar') {
    $tipo     = $_POST['tipo_origen'] ?? '';
    $origenId = (int)($_POST['origen_id'] ?? 0);
    if (!in_array($tipo, ['evento','activacion']) || !$origenId) {
        echo json_encode(['ok'=>false,'msg'=>'Datos inválidos']); exit();
    }

    $tabla = $tipo === 'evento' ? 'agenda_eventos' : 'activaciones';
    $colSeccion = $tipo === 'evento' ? 'seccion' : 'seccion_electoral';
    $stmtO = $pdo->prepare("SELECT titulo, estado, municipio, region, zona, $colSeccion AS seccion,
                             " . ($tipo === 'evento' ? 'fecha' : 'fecha_actividad') . " AS fecha_act
                             FROM $tabla WHERE id = ?");
    $stmtO->execute([$origenId]);
    $origen = $stmtO->fetch();
    if (!$origen) { echo json_encode(['ok'=>false,'msg'=>ucfirst($tipo).' no encontrado']); exit(); }

    // Alcance jerárquico: solo puede activarse el registro de un evento/
    // activación dentro del territorio que le corresponde a este rol
    // (el mismo criterio con el que ya se filtra list/obtener_detalles).
    if (!dentroDeAlcanceRA($origen, $rol, $estado_coord, $estados_circ, $municipios_region, $secciones_region, $secciones_zona)) {
        echo json_encode(['ok'=>false,'msg'=>'No tienes permiso sobre este '.$tipo.' (fuera de tu alcance).']);
        exit();
    }

    // Ya existe uno para este origen: solo lo reactivamos si estaba apagado.
    $stmtEx = $pdo->prepare("SELECT id, slug, link, activo FROM registro_qr WHERE tipo_origen=? AND origen_id=?");
    $stmtEx->execute([$tipo, $origenId]);
    $existe = $stmtEx->fetch();
    if ($existe) {
        if (!$existe['activo']) $pdo->prepare("UPDATE registro_qr SET activo=1 WHERE id=?")->execute([$existe['id']]);
        echo json_encode(['ok'=>true, 'id'=>$existe['id'], 'slug'=>$existe['slug'], 'link'=>$existe['link']]);
        exit();
    }

    $slugBase = slugificarRA($origen['titulo']); $slug = $slugBase; $i = 2;
    $chk = $pdo->prepare("SELECT COUNT(*) FROM registro_qr WHERE slug = ?");
    while (true) {
        $chk->execute([$slug]);
        if ((int)$chk->fetchColumn() === 0) break;
        $slug = $slugBase . '-' . $i; $i++;
    }
    $link = baseUrlActualRA() . '/public/forms-registro.php?slug=' . $slug;

    $stmt = $pdo->prepare("INSERT INTO registro_qr
        (tipo_origen, origen_id, titulo, slug, link, fecha_actividad, estado, municipio, region, zona, seccion_electoral, creado_por_id, creado_por_rol)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([$tipo, $origenId, $origen['titulo'], $slug, $link, $origen['fecha_act'],
                     $origen['estado'], $origen['municipio'], $origen['region'], $origen['zona'], $origen['seccion'],
                     $user_id, $rol]);

    echo json_encode(['ok'=>true, 'id'=>$pdo->lastInsertId(), 'slug'=>$slug, 'link'=>$link]);
    exit();
}

if ($action === 'desactivar') {
    $id = (int)($_POST['id'] ?? 0);
    $tipo     = $_POST['tipo_origen'] ?? '';
    $origenId = (int)($_POST['origen_id'] ?? 0);

    // 1) Localizar el registro_qr (sin filtrar por alcance todavía) para
    //    distinguir "no existe" (no-op silencioso, ej. el modal desmarca el
    //    checkbox de un evento que nunca tuvo QR) de "existe pero está fuera
    //    de tu alcance jerárquico" (debe rechazarse explícitamente).
    if ($id) {
        $stmtF = $pdo->prepare("SELECT id FROM registro_qr WHERE id = ?");
        $stmtF->execute([$id]);
    } elseif (in_array($tipo, ['evento','activacion']) && $origenId) {
        $stmtF = $pdo->prepare("SELECT id FROM registro_qr WHERE tipo_origen=? AND origen_id=?");
        $stmtF->execute([$tipo, $origenId]);
    } else {
        echo json_encode(['ok'=>false,'msg'=>'Datos inválidos']); exit();
    }
    $found = $stmtF->fetch();
    if (!$found) { echo json_encode(['ok'=>true]); exit(); } // nada que desactivar

    // 2) Verificar alcance jerárquico (misma condición territorial que list/detalle).
    $where = $scopeSql ? " AND $scopeSql" : '';
    $stmtChk = $pdo->prepare("SELECT id FROM registro_qr WHERE id = ?" . $where);
    $stmtChk->execute(array_merge([$found['id']], $scopeParams));
    if (!$stmtChk->fetch()) {
        echo json_encode(['ok'=>false,'msg'=>'No tienes permiso para desactivar este registro (fuera de tu alcance).']);
        exit();
    }

    $pdo->prepare("UPDATE registro_qr SET activo=0 WHERE id=?")->execute([$found['id']]);
    echo json_encode(['ok'=>true]);
    exit();
}

// ── list: eventos/activaciones con registro habilitado, dentro del alcance ──
if ($action === 'list') {
    $conds = ["activo = 1"]; $params = [];
    if ($scopeSql) { $conds[] = $scopeSql; $params = array_merge($params, $scopeParams); }

    $tipo = $_GET['tipo'] ?? '';
    if (in_array($tipo, ['evento','activacion'])) { $conds[] = 'tipo_origen = ?'; $params[] = $tipo; }

    $nombre = trim($_GET['nombre'] ?? '');
    if ($nombre !== '') { $conds[] = 'titulo LIKE ?'; $params[] = '%'.$nombre.'%'; }

    $desde = $_GET['desde'] ?? ''; $hasta = $_GET['hasta'] ?? '';
    if ($desde !== '') { $conds[] = 'fecha_actividad >= ?'; $params[] = $desde; }
    if ($hasta !== '') { $conds[] = 'fecha_actividad <= ?'; $params[] = $hasta; }

    $estadoF = trim($_GET['estado'] ?? ''); if ($estadoF !== '') { $conds[] = 'estado LIKE ?'; $params[] = '%'.$estadoF.'%'; }
    $regionF = trim($_GET['region'] ?? ''); if ($regionF !== '') { $conds[] = 'region LIKE ?'; $params[] = '%'.$regionF.'%'; }
    $zonaF   = trim($_GET['zona']   ?? ''); if ($zonaF   !== '') { $conds[] = 'zona LIKE ?';   $params[] = '%'.$zonaF.'%'; }

    $sql = "SELECT rq.*, (SELECT COUNT(*) FROM registro_asistentes ra WHERE ra.registro_qr_id = rq.id) AS total_registrados
            FROM registro_qr rq WHERE " . implode(' AND ', $conds) . " ORDER BY rq.fecha_creacion DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode(['ok'=>true, 'items'=>$stmt->fetchAll()]);
    exit();
}

// ── obtener_detalles: registrados de un evento/activación ──
if ($action === 'obtener_detalles') {
    $id = (int)($_GET['id'] ?? 0);
    $where = $scopeSql ? " AND $scopeSql" : '';
    $stmtQ = $pdo->prepare("SELECT * FROM registro_qr WHERE id = ?" . $where);
    $stmtQ->execute(array_merge([$id], $scopeParams));
    $qr = $stmtQ->fetch();
    if (!$qr) { echo json_encode(['ok'=>false,'msg'=>'No encontrado o fuera de tu alcance']); exit(); }

    $stmtR = $pdo->prepare("SELECT p.id AS persona_id, p.nombres, p.apellidos, p.lada, p.celular, ra.fecha_registro
                             FROM registro_asistentes ra INNER JOIN personas p ON p.id = ra.persona_id
                             WHERE ra.registro_qr_id = ? ORDER BY ra.fecha_registro DESC");
    $stmtR->execute([$id]);
    echo json_encode(['ok'=>true, 'registro'=>$qr, 'registrados'=>$stmtR->fetchAll()]);
    exit();
}

// ── exportar: descarga Excel de los registrados de un evento/activación ──
if ($action === 'exportar') {
    $id = (int)($_GET['id'] ?? 0);
    $where = $scopeSql ? " AND $scopeSql" : '';
    $stmtQ = $pdo->prepare("SELECT * FROM registro_qr WHERE id = ?" . $where);
    $stmtQ->execute(array_merge([$id], $scopeParams));
    $qr = $stmtQ->fetch();
    if (!$qr) { die('No encontrado o fuera de tu alcance.'); }

    $stmtR = $pdo->prepare("SELECT p.nombres, p.apellidos, p.lada, p.celular, ra.fecha_registro
                             FROM registro_asistentes ra INNER JOIN personas p ON p.id = ra.persona_id
                             WHERE ra.registro_qr_id = ? ORDER BY ra.fecha_registro DESC");
    $stmtR->execute([$id]);
    $filas = $stmtR->fetchAll();

    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="registrados-' . slugificarRA($qr['titulo']) . '.xls"');
    echo "\xEF\xBB\xBF";
    echo '<table border="1"><tr>'
       . '<th>Nombre</th><th>Apellido</th><th>Celular</th><th>Evento/Activación</th>'
       . '<th>Fecha de registro</th><th>Zona</th><th>Región</th><th>Estado</th></tr>';
    foreach ($filas as $r) {
        echo '<tr>'
           . '<td>' . htmlspecialchars($r['nombres']) . '</td>'
           . '<td>' . htmlspecialchars($r['apellidos']) . '</td>'
           . '<td>' . htmlspecialchars($r['lada'].' '.$r['celular']) . '</td>'
           . '<td>' . htmlspecialchars($qr['titulo']) . '</td>'
           . '<td>' . htmlspecialchars(date('d/m/Y H:i', strtotime($r['fecha_registro']))) . '</td>'
           . '<td>' . htmlspecialchars($qr['zona'] ?? '') . '</td>'
           . '<td>' . htmlspecialchars($qr['region'] ?? '') . '</td>'
           . '<td>' . htmlspecialchars($qr['estado'] ?? '') . '</td>'
           . '</tr>';
    }
    echo '</table>';
    exit();
}

// ── list_personas: consulta general de personas dentro del alcance ──
if ($action === 'list_personas') {
    $conds = ["1=1"]; $params = [];
    if ($scopeSql) { $conds[] = $scopeSql; $params = array_merge($params, $scopeParams); }

    $nombre  = trim($_GET['nombre']  ?? '');
    $celular = trim($_GET['celular'] ?? '');
    $desde   = $_GET['desde'] ?? ''; $hasta = $_GET['hasta'] ?? '';
    $estadoF = trim($_GET['estado'] ?? ''); $regionF = trim($_GET['region'] ?? ''); $zonaF = trim($_GET['zona'] ?? '');
    if ($nombre  !== '') { $conds[] = "(p.nombres LIKE ? OR p.apellidos LIKE ?)"; $params[] = "%$nombre%"; $params[] = "%$nombre%"; }
    if ($celular !== '') { $conds[] = "p.celular LIKE ?"; $params[] = "%$celular%"; }
    if ($desde   !== '') { $conds[] = "ra.fecha_registro >= ?"; $params[] = $desde; }
    if ($hasta   !== '') { $conds[] = "ra.fecha_registro <= ?"; $params[] = $hasta; }
    if ($estadoF !== '') { $conds[] = "rq.estado LIKE ?"; $params[] = "%$estadoF%"; }
    if ($regionF !== '') { $conds[] = "rq.region LIKE ?"; $params[] = "%$regionF%"; }
    if ($zonaF   !== '') { $conds[] = "rq.zona LIKE ?"; $params[] = "%$zonaF%"; }

    $sql = "SELECT p.id, p.nombres, p.apellidos, p.lada, p.celular,
                   COUNT(DISTINCT ra.registro_qr_id) AS total_eventos, MAX(ra.fecha_registro) AS ultimo_registro
            FROM registro_asistentes ra
            INNER JOIN personas p ON p.id = ra.persona_id
            INNER JOIN registro_qr rq ON rq.id = ra.registro_qr_id
            WHERE " . implode(' AND ', $conds) . "
            GROUP BY p.id ORDER BY ultimo_registro DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode(['ok'=>true, 'items'=>$stmt->fetchAll()]);
    exit();
}

// ── historial_persona: eventos/activaciones de una persona, dentro del alcance ──
if ($action === 'historial_persona') {
    $personaId = (int)($_GET['persona_id'] ?? 0);
    $conds = ["ra.persona_id = ?"]; $params = [$personaId];
    if ($scopeSql) { $conds[] = $scopeSql; $params = array_merge($params, $scopeParams); }

    $sql = "SELECT rq.titulo, rq.tipo_origen, rq.fecha_actividad, rq.zona, rq.region, rq.estado, ra.fecha_registro
            FROM registro_asistentes ra INNER JOIN registro_qr rq ON rq.id = ra.registro_qr_id
            WHERE " . implode(' AND ', $conds) . " ORDER BY ra.fecha_registro DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode(['ok'=>true, 'items'=>$stmt->fetchAll()]);
    exit();
}

echo json_encode(['ok'=>false,'msg'=>'Acción no reconocida']);