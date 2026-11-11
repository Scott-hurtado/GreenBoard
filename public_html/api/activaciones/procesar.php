<?php
// api/activaciones/procesar.php — Unificado todos los niveles

if (file_exists(__DIR__ . '/../../includes/bootstrap.php')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
} else { session_start(); }

$rol     = strtolower($_SESSION['user_role'] ?? '');
$user_id = $_SESSION['user_id'] ?? null;

// ── Permisos — todos los roles operativos ─────────────────
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
    echo json_encode(['ok'=>false,'msg'=>'DB error: '.$e->getMessage()]);
    exit();
}

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ── FILTROS TERRITORIALES por rol ─────────────────────────
$estado_coord      = null;   // estatal: su estado
$estados_circ      = [];     // circ: sus estados
$municipios_region = [];     // regional: sus municipios
$secciones_region  = [];     // regional: catálogo de secciones de sus zonas (respaldo)
$secciones_zona    = [];     // zona: sus secciones
$user_region_nombre = '';    // regional/zona: nombre real de su propia región
$user_zona_nombre    = '';    // zona: nombre real de su propia zona

if ($rol === 'coordinador_estatal') {
    $stmt = $pdo->prepare("SELECT estado FROM coordinador_estatal WHERE id=?");
    $stmt->execute([$user_id]);
    $estado_coord = $stmt->fetchColumn() ?: ($_SESSION['user_estado'] ?? '');
    if (!$estado_coord) {
        echo json_encode(['ok'=>false,'msg'=>'Coordinador estatal sin estado asignado']);
        exit();
    }

} elseif (in_array($rol, ['circunscripcion','coordinadores_circunscripcion','coordinador_circunscripcion'])) {
    $user_circ = trim($_SESSION['user_circunscripcion'] ?? '');
    $CIRCS = [
        'circunscripcion 1' => ['Baja California','Baja California Sur','Chihuahua','Durango','Jalisco','Nayarit','Sinaloa','Sonora'],
        'circunscripcion 2' => ['Aguascalientes','Coahuila','Guanajuato','Nuevo Leon','San Luis Potosi','Tamaulipas','Zacatecas'],
        'circunscripcion 3' => ['Campeche','Chiapas','Oaxaca','Quintana Roo','Tabasco','Veracruz','Yucatan'],
        'circunscripcion 4' => ['Ciudad de Mexico','Guerrero','Hidalgo','Morelos','Puebla','Tlaxcala'],
        'circunscripcion 5' => ['Colima','Estado de Mexico','Michoacan','Queretaro'],
    ];
    // Normalizamos (sin acentos, minúsculas, sin espacios extra) antes de
    // comparar: el nombre guardado en sesión puede traer acentos/mayúsculas
    // distintos a como está escrito aquí ("Circunscripción 1" vs
    // "Circunscripcion 1"), y una comparación exacta de array-key rompía el
    // guardado de Activaciones para esta cuenta.
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
    if (empty($estados_circ)) {
        echo json_encode(['ok'=>false,'msg'=>"Circunscripcion no configurada (sesion: '$user_circ')"]);
        exit();
    }

} elseif (in_array($rol, ['coordinador_regional','regional'])) {
    $user_region = $_SESSION['user_region'] ?? '';
    $user_estado = $_SESSION['user_estado'] ?? '';
    // Nombre y municipios de la región propia del usuario: misma fuente que
    // usa territorio_php_cr.php (regiones_estatales.id = user_region), en
    // vez de la tabla region_municipios / coordinador_id, que puede no estar
    // poblada para todas las regiones y dejaba $municipios_region y
    // $user_region_nombre vacíos silenciosamente.
    if ($user_region) {
        $st = $pdo->prepare("SELECT nombre, municipios FROM regiones_estatales WHERE id = ? LIMIT 1");
        $st->execute([$user_region]);
        $regRow = $st->fetch();
        if ($regRow) {
            $user_region_nombre = $regRow['nombre'];
            $municipios_region  = json_decode($regRow['municipios'] ?? '[]', true) ?: [];
        }
        // Catálogo de secciones de TODAS las zonas de la región (mismo cálculo
        // que territorio_php_cr.php): respaldo robusto para no depender solo
        // de que `municipio` calce exactamente contra $municipios_region
        // (mayúsculas/acentos, o zonas nuevas cuyo municipio aún no figure
        // en regiones_estatales.municipios).
        $stZ = $pdo->prepare("SELECT secciones FROM zonas_estatales WHERE estructura_id = ?");
        $stZ->execute([$user_region]);
        foreach ($stZ->fetchAll(PDO::FETCH_ASSOC) as $z) {
            $secs = json_decode($z['secciones'] ?? '[]', true) ?: [];
            foreach ($secs as $sec) $secciones_region[] = (string)$sec;
        }
        $secciones_region = array_unique($secciones_region);
    }
    // Sin municipios de region -> restringir al menos por estado
    if (empty($municipios_region)) {
        $estado_coord = $user_estado;
    }
} elseif ($rol === 'coordinador_zona') {
    $user_zona   = $_SESSION['user_zona_nombre'] ?? '';
    $user_estado = $_SESSION['user_estado'] ?? '';
    // Intentar primero desde secciones_electorales (tabla legacy)
    if ($user_zona) {
        $st = $pdo->prepare("SELECT nombre_seccion FROM secciones_electorales WHERE TRIM(zona_asignada)=TRIM(?) AND activo=1");
        $st->execute([$user_zona]);
        $secciones_zona = $st->fetchAll(PDO::FETCH_COLUMN);
    }
    // Fallback: cargar desde zonas_estatales (tabla principal)
    if (empty($secciones_zona)) {
        $zona_id_s = (int)($_SESSION['user_zona_id'] ?? 0);
        // Intentar por ID de sesión
        if ($zona_id_s > 0) {
            $stZ = $pdo->prepare("SELECT secciones FROM zonas_estatales WHERE id = ? LIMIT 1");
            $stZ->execute([$zona_id_s]);
            $secJson = $stZ->fetchColumn();
            if ($secJson) $secciones_zona = json_decode($secJson, true) ?: [];
        }
        // Si no, buscar por coordinador_zona.zona → zonas_estatales
        if (empty($secciones_zona)) {
            $stCZ = $pdo->prepare("
                SELECT z.secciones FROM coordinador_zona cz
                INNER JOIN zonas_estatales z ON cz.zona = z.id
                WHERE cz.id = ? LIMIT 1
            ");
            $stCZ->execute([$user_id]);
            $secJson2 = $stCZ->fetchColumn();
            if ($secJson2) $secciones_zona = json_decode($secJson2, true) ?: [];
        }
    }
    // Nombre real de la zona propia y de su región padre (mismo join que usa
    // territorio.php): no se puede inferir por municipio ni por sección,
    // porque ambos pueden repetirse entre zonas/regiones distintas.
    $zona_id_res = (int)($_SESSION['user_zona_id'] ?? 0);
    if ($zona_id_res <= 0) {
        $stZid = $pdo->prepare("SELECT zona FROM coordinador_zona WHERE id = ? LIMIT 1");
        $stZid->execute([$user_id]);
        $zona_id_res = (int)($stZid->fetchColumn() ?: 0);
    }
    if ($zona_id_res > 0) {
        $stZR = $pdo->prepare("
            SELECT z.nombre AS zona_nombre, r.nombre AS region_nombre
            FROM zonas_estatales z
            INNER JOIN regiones_estatales r ON z.estructura_id = r.id
            WHERE z.id = ? LIMIT 1
        ");
        $stZR->execute([$zona_id_res]);
        $rowZR = $stZR->fetch();
        if ($rowZR) {
            $user_zona_nombre   = $rowZR['zona_nombre'];
            $user_region_nombre = $rowZR['region_nombre'];
        }
    }
}

// ── Verificar scope territorial de una activacion ─────────
function verificarScope(PDO $pdo, int $id, string $rol, ?string $estado_coord,
                        array $estados_circ, array $municipios_region, array $secciones_zona,
                        array $secciones_region = []): bool {
    $check = $pdo->prepare("SELECT estado, municipio, seccion_electoral FROM activaciones WHERE id=?");
    $check->execute([$id]);
    $row = $check->fetch();
    if (!$row) return false;

    if (in_array($rol, ['admin','administrador','superadmin','super admin'])) return true;
    if ($rol === 'coordinador_estatal' && $estado_coord)
        return $row['estado'] === $estado_coord;
    if (in_array($rol, ['circunscripcion','coordinadores_circunscripcion','coordinador_circunscripcion']))
        return in_array($row['estado'], $estados_circ);
    if (in_array($rol, ['coordinador_regional','regional'])) {
        if (!empty($municipios_region) && in_array($row['municipio'], $municipios_region)) return true;
        if (!empty($secciones_region) && in_array($row['seccion_electoral'], $secciones_region)) return true;
        if (empty($municipios_region) && empty($secciones_region) && $estado_coord) return $row['estado'] === $estado_coord;
        return false;
    }
    if ($rol === 'coordinador_zona')
        return in_array($row['seccion_electoral'], $secciones_zona);
    return false;
}

// ── Generar codigo de referencia ──────────────────────────
function generarCodigo(PDO $pdo): string {
    $prefix = 'ACT-'.date('ymd').'-';
    $stmt = $pdo->prepare("SELECT codigo_referencia FROM activaciones WHERE codigo_referencia LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix.'%']);
    $ultimo = $stmt->fetchColumn();
    $num = $ultimo ? (intval(substr($ultimo, -3))+1) : 1;
    return $prefix.str_pad($num, 3, '0', STR_PAD_LEFT);
}

// Resuelve el nombre de la región a partir del municipio, usando el JSON
// `municipios` de regiones_estatales. Es el mismo respaldo que usa agenda,
// para que activaciones quede consistente con los eventos.
function resolverRegionPorMunicipio(PDO $pdo, string $estado, string $municipio): string {
    static $cache = [];
    $estado = trim($estado);
    $municipio = trim($municipio);
    if ($estado === '' || $municipio === '') return '';
    if (!isset($cache[$estado])) {
        $st = $pdo->prepare("SELECT nombre, municipios FROM regiones_estatales WHERE estado = ?");
        $st->execute([$estado]);
        $cache[$estado] = $st->fetchAll(PDO::FETCH_ASSOC);
    }
    $muniNorm = mb_strtoupper($municipio, 'UTF-8');
    foreach ($cache[$estado] as $row) {
        $lista = json_decode($row['municipios'] ?? '[]', true) ?: [];
        foreach ($lista as $m) {
            if (mb_strtoupper(trim($m), 'UTF-8') === $muniNorm) {
                return $row['nombre'];
            }
        }
    }
    return '';
}

// Resuelve el nombre de la zona a partir de la sección electoral, dentro de
// una región ya resuelta, usando el JSON `secciones` de zonas_estatales.
function resolverZonaPorSeccion(PDO $pdo, string $regionNombre, string $seccion): string {
    static $cache = [];
    $regionNombre = trim($regionNombre);
    $seccion = trim($seccion);
    if ($regionNombre === '' || $seccion === '') return '';
    if (!isset($cache[$regionNombre])) {
        $st = $pdo->prepare("
            SELECT z.nombre, z.secciones
            FROM zonas_estatales z
            INNER JOIN regiones_estatales r ON z.estructura_id = r.id
            WHERE r.nombre = ?
        ");
        $st->execute([$regionNombre]);
        $cache[$regionNombre] = $st->fetchAll(PDO::FETCH_ASSOC);
    }
    foreach ($cache[$regionNombre] as $row) {
        $lista = json_decode($row['secciones'] ?? '[]', true) ?: [];
        foreach ($lista as $s) {
            if ((string)$s === $seccion) return $row['nombre'];
        }
    }
    return '';
}

// Resuelve el municipio a partir de la sección electoral y el estado,
// usando la misma fuente (tabla `secciones`, con fallback a
// `secciones_electorales`) que ya usa activaciones_php_cz.php. Sirve como
// respaldo del lado servidor para el rol de zona: aunque el formulario no
// mande el municipio (o lo mande vacío/incorrecto), aquí se completa, para
// que la activación sea visible también para el rol Regional (que filtra
// por municipio).
function resolverMunicipioPorSeccion(PDO $pdo, string $estado, string $seccion): string {
    $estado = trim($estado);
    $seccion = trim($seccion);
    if ($estado === '' || $seccion === '') return '';
    try {
        $st = $pdo->prepare("
            SELECT m.nombre
            FROM secciones s
            LEFT JOIN municipios m ON m.id = s.municipio_id
            WHERE s.seccion = ? AND s.estado_nombre = ?
            AND m.nombre IS NOT NULL AND m.nombre != ''
            LIMIT 1
        ");
        $st->execute([$seccion, $estado]);
        $m = $st->fetchColumn();
        if ($m) return $m;
    } catch (Exception $e) { /* fallback abajo */ }
    try {
        $st2 = $pdo->prepare("
            SELECT municipio FROM secciones_electorales
            WHERE nombre_seccion = ? AND activo = 1 LIMIT 1
        ");
        $st2->execute([$seccion]);
        $m2 = $st2->fetchColumn();
        if ($m2) return $m2;
    } catch (Exception $e2) { /* silencioso */ }
    return '';
}

// Helper: normaliza y valida un JSON de enlaces (solo URLs http/https)
function limpiarEnlaces($raw): string {
    if (!is_string($raw)) $raw = json_encode($raw);
    $arr = json_decode($raw, true);
    if (!is_array($arr)) return '[]';
    $limpio = array_values(array_filter(array_map('trim', $arr), function($u) {
        return is_string($u) && $u !== '' && filter_var($u, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $u);
    }));
    return json_encode($limpio, JSON_UNESCAPED_UNICODE);
}

// ── OBTENER FOTOS (bajo demanda, para no cargar imágenes en el listado) ──
if ($action === 'obtener_fotos') {
    $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['ok'=>false,'msg'=>'id requerido']); exit(); }

    if (!verificarScope($pdo, $id, $rol, $estado_coord, $estados_circ, $municipios_region, $secciones_zona, $secciones_region)) {
        echo json_encode(['ok'=>false,'msg'=>'Sin permiso sobre este registro']); exit();
    }

    $st = $pdo->prepare("SELECT fotos FROM activaciones WHERE id = ? AND activo = 1");
    $st->execute([$id]);
    $fotosJson = $st->fetchColumn();
    $fotos = $fotosJson ? (json_decode($fotosJson, true) ?: []) : [];

    echo json_encode(['ok'=>true, 'fotos'=>$fotos], JSON_UNESCAPED_UNICODE);
    exit();
}

// ── CREATE ────────────────────────────────────────────────
if ($action === 'create') {
    $titulo  = trim($_POST['titulo']         ?? '');
    $tipo    = $_POST['tipo']                ?? 'territorial';
    $fecha   = $_POST['fecha_actividad']     ?? null;
    $estatus = $_POST['estatus']             ?? 'programada';

    if (!$titulo) { echo json_encode(['ok'=>false,'msg'=>'Titulo requerido']); exit(); }

    // Estado: roles con scope fijo usan estado_coord; admin/circ toman del POST
    if ($estado_coord) {
        $estado_post = $estado_coord;
    } else {
        $estado_post = trim($_POST['estado'] ?? '');
        if (!empty($estados_circ) && $estado_post && !in_array($estado_post, $estados_circ)) {
            echo json_encode(['ok'=>false,'msg'=>'Estado fuera de tu circunscripcion']); exit();
        }
    }

    $seccion_post = trim($_POST['seccion_electoral'] ?? '');
    if (!empty($secciones_zona) && $seccion_post && !in_array($seccion_post, $secciones_zona)) {
        echo json_encode(['ok'=>false,'msg'=>'Seccion fuera de tu zona']); exit();
    }
    if ($seccion_post === '') {
        echo json_encode(['ok'=>false,'msg'=>'Debes indicar la sección electoral de la activación']); exit();
    }

    $municipio_post = trim($_POST['municipio'] ?? '');
    // Respaldo: si es zona y no llegó municipio (o el front no lo resolvió),
    // se deriva aquí a partir de la sección para que la activación no quede
    // sin municipio y sea visible también para el rol Regional.
    if ($rol === 'coordinador_zona' && $municipio_post === '') {
        $municipio_post = resolverMunicipioPorSeccion($pdo, $estado_post, $seccion_post);
    }
    if (!empty($municipios_region) && $municipio_post && !in_array($municipio_post, $municipios_region)) {
        echo json_encode(['ok'=>false,'msg'=>'Municipio fuera de tu region']); exit();
    }
    if ($rol !== 'coordinador_zona' && $municipio_post === '') {
        echo json_encode(['ok'=>false,'msg'=>'Debes indicar el municipio de la activación']); exit();
    }

    $codigo = generarCodigo($pdo);
    $enlaces_post = limpiarEnlaces($_POST['enlaces'] ?? '[]');

    // Región/zona: activaciones nunca las guardaba — se calculan aquí.
    // Para un usuario regional o de zona usamos SIEMPRE su propia región/zona
    // (un mismo municipio o sección puede repartirse entre varias
    // regiones/zonas, así que resolverlas por municipio/sección es
    // ambiguo). Para el resto de roles se mantiene el cálculo por
    // municipio/sección, igual que en agenda.
    $region_post = !empty($user_region_nombre)
        ? $user_region_nombre
        : resolverRegionPorMunicipio($pdo, $estado_post, $municipio_post);
    $zona_post   = !empty($user_zona_nombre)
        ? $user_zona_nombre
        : ($region_post !== '' ? resolverZonaPorSeccion($pdo, $region_post, $seccion_post) : '');

    try {
        $stmt = $pdo->prepare("
            INSERT INTO activaciones
                (codigo_referencia, titulo, tipo, estatus, estado, municipio, region, zona, fecha_actividad,
                 coordinador, seccion_electoral, contactos_estimados, asistentes_estimados,
                 costo, notas, latitud, longitud, fotos, enlaces, candidato, colonia,
                 creado_por_id, creado_por_rol, activo)
            VALUES
                (:codigo, :titulo, :tipo, :estatus, :estado, :municipio, :region, :zona, :fecha,
                 :coordinador, :seccion, :contactos, :asistentes,
                 :costo, :notas, :lat, :lng, :fotos, :enlaces, :candidato, :colonia,
                 :creado_por_id, :creado_por_rol, 1)
        ");
        $stmt->execute([
            ':codigo'        => $codigo,
            ':titulo'        => $titulo,
            ':tipo'          => $tipo,
            ':estatus'       => $estatus,
            ':estado'        => $estado_post,
            ':municipio'     => $municipio_post,
            ':region'        => $region_post,
            ':zona'          => $zona_post,
            ':fecha'         => $fecha ?: null,
            ':coordinador'   => trim($_POST['coordinador']          ?? ''),
            ':seccion'       => $seccion_post,
            ':contactos'     => intval($_POST['contactos_estimados']  ?? 0),
            ':asistentes'    => intval($_POST['asistentes_estimados'] ?? 0),
            ':costo'         => floatval($_POST['costo']              ?? 0),
            ':notas'         => trim($_POST['notas']                  ?? ''),
            ':lat'           => $_POST['latitud']  ? floatval($_POST['latitud'])  : null,
            ':lng'           => $_POST['longitud'] ? floatval($_POST['longitud']) : null,
            ':fotos'         => $_POST['fotos']    ?? '[]',
            ':enlaces'       => $enlaces_post,
            ':candidato'     => trim($_POST['candidato']              ?? ''),
            ':colonia'       => trim($_POST['colonia']                ?? ''),
            ':creado_por_id' => $user_id,
            ':creado_por_rol'=> $rol,
        ]);
        echo json_encode(['ok'=>true, 'id'=>$pdo->lastInsertId(), 'codigo'=>$codigo]);
    } catch (PDOException $e) {
        echo json_encode(['ok'=>false,'msg'=>'Error de BD al crear: '.$e->getMessage()]);
    }
    exit();
}

// ── UPDATE ────────────────────────────────────────────────
if ($action === 'update') {
    $id = intval($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['ok'=>false,'msg'=>'ID requerido']); exit(); }

    if (!verificarScope($pdo, $id, $rol, $estado_coord, $estados_circ, $municipios_region, $secciones_zona, $secciones_region)) {
        echo json_encode(['ok'=>false,'msg'=>'No tienes permiso para editar esta activacion']); exit();
    }

    $estado_post    = $estado_coord ?: trim($_POST['estado']           ?? '');
    $municipio_post = trim($_POST['municipio']         ?? '');
    $seccion_post   = trim($_POST['seccion_electoral'] ?? '');

    // Respaldo: igual que en create, si es zona y no llegó municipio, se
    // deriva a partir de la sección.
    if ($rol === 'coordinador_zona' && $municipio_post === '') {
        $municipio_post = resolverMunicipioPorSeccion($pdo, $estado_post, $seccion_post);
    }

    // Re-validar territorialmente en update
    if (!empty($municipios_region) && $municipio_post && !in_array($municipio_post, $municipios_region)) {
        echo json_encode(['ok'=>false,'msg'=>'Municipio fuera de tu region']); exit();
    }
    if ($rol !== 'coordinador_zona' && $municipio_post === '') {
        echo json_encode(['ok'=>false,'msg'=>'Debes indicar el municipio de la activación']); exit();
    }
    if (!empty($secciones_zona) && $seccion_post && !in_array($seccion_post, $secciones_zona)) {
        echo json_encode(['ok'=>false,'msg'=>'Seccion fuera de tu zona']); exit();
    }
    if ($seccion_post === '') {
        echo json_encode(['ok'=>false,'msg'=>'Debes indicar la sección electoral de la activación']); exit();
    }

    // Región/zona: mismo cálculo automático que en create.
    $region_post = !empty($user_region_nombre)
        ? $user_region_nombre
        : resolverRegionPorMunicipio($pdo, $estado_post, $municipio_post);
    $zona_post   = !empty($user_zona_nombre)
        ? $user_zona_nombre
        : ($region_post !== '' ? resolverZonaPorSeccion($pdo, $region_post, $seccion_post) : '');

    $enlaces_post = limpiarEnlaces($_POST['enlaces'] ?? '[]');

    try {
        $stmt = $pdo->prepare("
            UPDATE activaciones SET
                titulo               = :titulo,
                tipo                 = :tipo,
                estatus              = :estatus,
                estado               = :estado,
                municipio            = :municipio,
                region               = :region,
                zona                 = :zona,
                fecha_actividad      = :fecha,
                coordinador          = :coordinador,
                seccion_electoral    = :seccion,
                contactos_estimados  = :contactos,
                asistentes_estimados = :asistentes,
                costo                = :costo,
                notas                = :notas,
                latitud              = :lat,
                longitud             = :lng,
                fotos                = :fotos,
                enlaces              = :enlaces,
                candidato            = :candidato,
                colonia              = :colonia
            WHERE id=:id AND activo=1
        ");
        $stmt->execute([
            ':titulo'     => trim($_POST['titulo']            ?? ''),
            ':tipo'       => $_POST['tipo']                   ?? 'territorial',
            ':estatus'    => $_POST['estatus']                ?? 'programada',
            ':estado'     => $estado_post,
            ':municipio'  => $municipio_post,
            ':region'     => $region_post,
            ':zona'       => $zona_post,
            ':fecha'      => $_POST['fecha_actividad']        ?: null,
            ':coordinador'=> trim($_POST['coordinador']       ?? ''),
            ':seccion'    => $seccion_post,
            ':contactos'  => intval($_POST['contactos_estimados']  ?? 0),
            ':asistentes' => intval($_POST['asistentes_estimados'] ?? 0),
            ':costo'      => floatval($_POST['costo']              ?? 0),
            ':notas'      => trim($_POST['notas']                  ?? ''),
            ':lat'        => $_POST['latitud']  ? floatval($_POST['latitud'])  : null,
            ':lng'        => $_POST['longitud'] ? floatval($_POST['longitud']) : null,
            ':fotos'      => $_POST['fotos']    ?? '[]',
            ':enlaces'    => $enlaces_post,
            ':candidato'  => trim($_POST['candidato']              ?? ''),
            ':colonia'    => trim($_POST['colonia']                ?? ''),
            ':id'         => $id,
        ]);
        echo json_encode(['ok'=>true]);
    } catch (PDOException $e) {
        echo json_encode(['ok'=>false,'msg'=>'Error de BD al actualizar: '.$e->getMessage()]);
    }
    exit();
}

// ── DELETE (soft) ─────────────────────────────────────────
if ($action === 'delete') {
    $id = intval($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['ok'=>false,'msg'=>'ID requerido']); exit(); }

    if (!verificarScope($pdo, $id, $rol, $estado_coord, $estados_circ, $municipios_region, $secciones_zona, $secciones_region)) {
        echo json_encode(['ok'=>false,'msg'=>'No tienes permiso para eliminar esta activacion']); exit();
    }

    try {
        $pdo->prepare("UPDATE activaciones SET activo=0 WHERE id=:id")->execute([':id'=>$id]);
        echo json_encode(['ok'=>true]);
    } catch (PDOException $e) {
        echo json_encode(['ok'=>false,'msg'=>'Error de BD al eliminar: '.$e->getMessage()]);
    }
    exit();
}

// ── LIST (uso futuro / debug) ─────────────────────────────
if ($action === 'list') {
    try {
        if ($estado_coord) {
            $stmt = $pdo->prepare("SELECT * FROM activaciones WHERE activo=1 AND estado=? ORDER BY fecha_creacion DESC");
            $stmt->execute([$estado_coord]);
        } elseif (!empty($estados_circ)) {
            $ph = implode(',', array_fill(0, count($estados_circ), '?'));
            $stmt = $pdo->prepare("SELECT * FROM activaciones WHERE activo=1 AND estado IN ($ph) ORDER BY fecha_creacion DESC");
            $stmt->execute($estados_circ);
        } elseif (!empty($municipios_region) || !empty($secciones_region)) {
            // Regional: región exacta OR municipio en catálogo OR sección de
            // alguna de sus zonas — igual de permisivo que el filtro que ya
            // usa territorio_php_cr.php del lado del navegador para eventos,
            // para no perder activaciones cuyo `municipio` no calce exacto.
            $conds  = ['region = ?'];
            $params = [$user_region_nombre];
            if (!empty($municipios_region)) {
                $ph = implode(',', array_fill(0, count($municipios_region), '?'));
                $conds[] = "municipio IN ($ph)";
                $params  = array_merge($params, $municipios_region);
            }
            if (!empty($secciones_region)) {
                $ph2 = implode(',', array_fill(0, count($secciones_region), '?'));
                $conds[] = "seccion_electoral IN ($ph2)";
                $params  = array_merge($params, $secciones_region);
            }
            $sql  = "SELECT * FROM activaciones WHERE activo=1 AND (" . implode(' OR ', $conds) . ") ORDER BY fecha_creacion DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        } elseif (!empty($secciones_zona)) {
            $ph = implode(',', array_fill(0, count($secciones_zona), '?'));
            $stmt = $pdo->prepare("SELECT * FROM activaciones WHERE activo=1 AND seccion_electoral IN ($ph) ORDER BY fecha_creacion DESC");
            $stmt->execute($secciones_zona);
        } else {
            $stmt = $pdo->query("SELECT * FROM activaciones WHERE activo=1 ORDER BY fecha_creacion DESC");
        }
        echo json_encode(['ok'=>true,'activaciones'=>$stmt->fetchAll()]);
    } catch (PDOException $e) {
        echo json_encode(['ok'=>false,'msg'=>'Error de BD al listar: '.$e->getMessage()]);
    }
    exit();
}

echo json_encode(['ok'=>false,'msg'=>'Accion no reconocida']);