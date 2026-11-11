<?php
/**
 * Territorio Regional - Mapa Electoral
 * Igual arquitectura que mapa_electoral_circ.php, adaptado al rol regional
 */

// ── API: guardar secciones en sesión (evita URL 414 con listas grandes) ──
if (isset($_GET['api']) && $_GET['api'] === 'guardar_secs_session') {
    if (file_exists(__DIR__ . '/../../includes/bootstrap.php')) {
        require_once __DIR__ . '/../../includes/bootstrap.php';
    } else { session_start(); }
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $_SESSION['mapa_secciones_actuales'] = $body['secciones_actuales'] ?? '';
    $_SESSION['mapa_secciones_ocupadas'] = $body['secciones_ocupadas'] ?? '';
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

// ── Handler AJAX: detalle completo de sección ──
if (isset($_GET['action']) && $_GET['action'] === 'get_seccion_detalle') {
    header('Content-Type: application/json');
    if (file_exists(__DIR__ . '/../../includes/bootstrap.php')) {
        require_once __DIR__ . '/../../includes/bootstrap.php';
    } else { session_start(); }
    $rol       = $_SESSION['user_role'] ?? '';
    $permitidos = ['coordinador_regional', 'regional'];
    if (!in_array($rol, $permitidos)) { echo json_encode(['ok'=>false,'msg'=>'Sin permisos']); exit; }

    $numSeccion   = trim($_GET['seccion'] ?? '');
    $estadoNombre = trim($_GET['estado']  ?? '');
    if (!$numSeccion) { echo json_encode(['ok'=>false,'msg'=>'Sección requerida']); exit; }

    try {
        $dbConfigPath = __DIR__ . '/../../includes/database.php';
        $dbConfig = file_exists($dbConfigPath) ? require $dbConfigPath : [
            'host'=>'localhost','database'=>'u881824383_pverde',
            'username'=>'u881824383_proyverde','password'=>'PProyectoverde8#',
            'charset'=>'utf8mb4',
            'options'=>[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
        ];
        $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
        $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options'] ?? []);

        $whereEstado = $estadoNombre ? 'AND sec.estado_nombre = :estado' : '';
        $stmt = $pdo->prepare("
            SELECT
                sec.id,
                sec.seccion                          AS nombre_seccion,
                sec.estado_nombre                    AS estado,
                m.nombre                             AS municipio,
                sec.distrito_federal,
                sec.distrito_local,
                COALESCE(se.lista_nominal,         0) AS lista_nominal,
                COALESCE(se.padron_electoral,      0) AS padron_electoral,
                COALESCE(se.lista_hombres,         0) AS lista_hombres,
                COALESCE(se.lista_mujeres,         0) AS lista_mujeres,
                COALESCE(se.meta_contactos,        0) AS meta_contactos,
                COALESCE(se.contactos_conseguidos, 0) AS contactos_conseguidos,
                COALESCE(se.metas_votos,           0) AS metas_votos,
                COALESCE(se.region_asignada,      '') AS region_asignada,
                COALESCE(se.zona_asignada,        '') AS zona_asignada,
                COALESCE(se.principales_necesidades,'') AS principales_necesidades,
                COALESCE(se.enfoque,              '') AS enfoque,
                COALESCE(se.perfil_socioeconomico,'') AS perfil_socioeconomico,
                COALESCE(se.grupo_edad_18_24,      0) AS grupo_edad_18_24,
                COALESCE(se.grupo_edad_25_34,      0) AS grupo_edad_25_34,
                COALESCE(se.grupo_edad_35_44,      0) AS grupo_edad_35_49,
                COALESCE(se.grupo_edad_45_54,      0) AS grupo_edad_50_64,
                COALESCE(se.grupo_edad_55_mas,     0) AS grupo_edad_65_mas
            FROM secciones sec
            LEFT JOIN municipios m    ON m.id = sec.municipio_id
            LEFT JOIN secciones_electorales se
                   ON se.nombre_seccion = sec.seccion
                  AND se.estado         = sec.estado_nombre
            WHERE sec.seccion = :seccion $whereEstado
            LIMIT 1
        ");
        $params = [':seccion' => $numSeccion];
        if ($estadoNombre) $params[':estado'] = $estadoNombre;
        $stmt->execute($params);
        $row = $stmt->fetch();
        if (!$row) { echo json_encode(['ok'=>false,'msg'=>'No encontrada']); exit; }

        $row['eventos_realizados']   = 0;
        $row['activaciones_totales'] = 0;
        $row['con_candidato']        = 0;
        $row['sin_candidato']        = 0;
        $row['eventos_lista']        = [];
        $row['activaciones_lista']   = [];

        try {
            $s2 = $pdo->prepare("SELECT COUNT(*) FROM agenda_eventos WHERE seccion = :s AND activo = 1");
            $s2->execute([':s' => $numSeccion]);
            $row['eventos_realizados'] = (int)$s2->fetchColumn();
            $s3 = $pdo->prepare("SELECT id, titulo, eje, subtipo, fecha, inicio, fin, estado, municipio, responsable, notas FROM agenda_eventos WHERE seccion = :s AND activo = 1 ORDER BY fecha DESC LIMIT 20");
            $s3->execute([':s' => $numSeccion]);
            $row['eventos_lista'] = $s3->fetchAll();
        } catch (Exception $e2) {}

        try {
            $s4 = $pdo->prepare("SELECT COUNT(*) FROM activaciones WHERE seccion_electoral = :s AND activo = 1");
            $s4->execute([':s' => $numSeccion]);
            $row['activaciones_totales'] = (int)$s4->fetchColumn();
            $s5 = $pdo->prepare("SELECT COUNT(*) FROM activaciones WHERE seccion_electoral = :s AND activo = 1 AND candidato IS NOT NULL AND candidato <> ''");
            $s5->execute([':s' => $numSeccion]);
            $row['con_candidato'] = (int)$s5->fetchColumn();
            $row['sin_candidato'] = $row['activaciones_totales'] - $row['con_candidato'];
            $s6 = $pdo->prepare("SELECT id, titulo, tipo, estatus, fecha_actividad, coordinador, candidato, contactos_estimados, asistentes_estimados FROM activaciones WHERE seccion_electoral = :s AND activo = 1 ORDER BY fecha_actividad DESC LIMIT 20");
            $s6->execute([':s' => $numSeccion]);
            $row['activaciones_lista'] = $s6->fetchAll();
        } catch (Exception $e3) {}

        echo json_encode(['ok'=>true, 'seccion'=>$row]);
    } catch (Exception $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

/* ══════════════════════════════════════════════════════════════════
   MODO VISTA — parámetros GET enviados desde estructura_cr.php
══════════════════════════════════════════════════════════════════ */
$modoSeleccion   = isset($_GET['modo_seleccion']) && $_GET['modo_seleccion'] === '1';
$modoVista       = !$modoSeleccion && (
                    (isset($_GET['modo_vista']) && $_GET['modo_vista'] === '1')
                    || (isset($_GET['zona_id'])   && (int)$_GET['zona_id'] > 0)
                   );
$vistZonaId      = ($modoVista || $modoSeleccion) ? (int)($_GET['zona_id']     ?? 0) : 0;
$vistZonaNombre  = ($modoVista || $modoSeleccion) ? htmlspecialchars(trim($_GET['zona_nombre'] ?? '')) : '';
$vistReturnUrl   = ($modoVista || $modoSeleccion) ? htmlspecialchars(trim($_GET['return_url']  ?? '')) : '';
$seccionesZona   = [];  // secciones de la zona específica a visualizar
$zoomEntidad     = trim($_GET['zoom_entidad'] ?? '');
$zoomTipo        = trim($_GET['zoom_tipo'] ?? 'municipios');
if (!in_array($zoomTipo, ['municipios','distrito_local','distrito_federal'], true)) $zoomTipo = 'municipios';

// ── Bootstrap principal ──
if (file_exists(__DIR__ . '/../../includes/bootstrap.php')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
} else { session_start(); }

// Modo selección: secciones actuales/ocupadas (SESSION tiene precedencia — se guardó antes del redirect)
$selSecActualesStr = $modoSeleccion
    ? (trim($_SESSION['mapa_secciones_actuales'] ?? '') ?: trim($_GET['secciones_actuales'] ?? ''))
    : '';
$selSecOcupadasStr = $modoSeleccion
    ? (trim($_SESSION['mapa_secciones_ocupadas'] ?? '') ?: trim($_GET['secciones_ocupadas'] ?? ''))
    : '';
if ($modoSeleccion) {
    unset($_SESSION['mapa_secciones_actuales'], $_SESSION['mapa_secciones_ocupadas']);
}
$selSecActualesArr = ($modoSeleccion && $selSecActualesStr)
    ? array_filter(explode(',', $selSecActualesStr)) : [];
$selSecOcupadasArr = ($modoSeleccion && $selSecOcupadasStr)
    ? array_filter(explode(',', $selSecOcupadasStr)) : [];

$rol         = $_SESSION['user_role']   ?? '';
$user_estado = $_SESSION['user_estado'] ?? '';
$user_region = $_SESSION['user_region'] ?? '';

$permitidos = ['coordinador_regional', 'regional'];
if (!in_array($rol, $permitidos)) { header('Location: /public/index.php'); exit(); }

$region_nombre   = '—';
$secciones_region = [];
$municipios_region = [];

// Logo del partido para la hoja de impresión (misma ruta que usa el rol Zona)
$logoPartidoUrl = '../../storage/logo/PVEM.svg';

try {
    $dbCfg = __DIR__ . '/../../includes/database.php';
    $cfg = file_exists($dbCfg) ? require $dbCfg : [
        'host'=>'localhost','database'=>'u881824383_pverde',
        'username'=>'u881824383_proyverde','password'=>'PProyectoverde8#',
        'charset'=>'utf8mb4',
        'options'=>[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
    ];
    $dsn = "mysql:host={$cfg['host']};dbname={$cfg['database']};charset={$cfg['charset']}";
    $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], $cfg['options'] ?? [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);

    if ($user_region) {
        // Obtener nombre de la región (igual que estructura_cr.php)
        $st = $pdo->prepare("SELECT id, nombre, municipios FROM regiones_estatales WHERE id = ? LIMIT 1");
        $st->execute([$user_region]);
        $reg = $st->fetch();
        if ($reg) {
            $region_nombre = $reg['nombre'];
            $municipios_region = json_decode($reg['municipios'] ?? '[]', true) ?: [];
            $municipios_region = array_map(fn($m) => mb_strtoupper(trim($m), 'UTF-8'), $municipios_region);

            // Obtener zonas de la región
            $st = $pdo->prepare("SELECT id, secciones FROM zonas_estatales WHERE estructura_id = ?");
            $st->execute([$reg['id']]);
            $zonas_rows = $st->fetchAll(PDO::FETCH_ASSOC);

            // Extraer todas las secciones de todas las zonas
            foreach ($zonas_rows as $z) {
                $secs = json_decode($z['secciones'] ?? '[]', true) ?: [];
                foreach ($secs as $sec) {
                    $secciones_region[] = (string)$sec;
                }
            }
            $secciones_region = array_unique($secciones_region);
        }
    }
} catch (PDOException $e) { $error_db = $e->getMessage(); }

// ── Catálogo de estados con GeoJSON ──
$todos_estados = [
    'sonora'           => ['label'=>'Sonora',            'municipioFile'=>'municipio.geojson',                  'seccionFile'=>'seccion.geojson',                  'centroide'=>[29.5,  -110.5],  'zoom'=>7],
    'bc'               => ['label'=>'Baja California',   'municipioFile'=>'municipio-bc.geojson',               'seccionFile'=>'seccion-bc.geojson',               'centroide'=>[30.5,  -115.5],  'zoom'=>7],
    'bcs'              => ['label'=>'Baja California Sur','municipioFile'=>'municipio-bcs.geojson',              'seccionFile'=>'seccion-bcs.geojson',              'centroide'=>[24.5,  -111.5],  'zoom'=>7],
    'chihuahua'        => ['label'=>'Chihuahua',         'municipioFile'=>'municipio-chihuahua.geojson',        'seccionFile'=>'seccion-chihuahua.geojson',        'centroide'=>[28.6,  -106.1],  'zoom'=>7],
    'durango'          => ['label'=>'Durango',           'municipioFile'=>'municipio-durango.geojson',          'seccionFile'=>'seccion-durango.geojson',          'centroide'=>[24.5,  -104.7],  'zoom'=>7],
    'sinaloa'          => ['label'=>'Sinaloa',           'municipioFile'=>'municipio-sinaloa.geojson',          'seccionFile'=>'seccion-sinaloa.geojson',          'centroide'=>[24.8,  -107.4],  'zoom'=>7],
    'nayarit'          => ['label'=>'Nayarit',           'municipioFile'=>'municipio-nayarit.geojson',          'seccionFile'=>'seccion-nayarit.geojson',          'centroide'=>[21.75, -104.85], 'zoom'=>8],
    'jalisco'          => ['label'=>'Jalisco',           'municipioFile'=>'municipio-jalisco.geojson',          'seccionFile'=>'seccion-jalisco.geojson',          'centroide'=>[20.6,  -103.3],  'zoom'=>7],
    'aguascalientes'   => ['label'=>'Aguascalientes',    'municipioFile'=>'municipio-aguascalientes.geojson',   'seccionFile'=>'seccion-aguascalientes.geojson',   'centroide'=>[21.88, -102.29], 'zoom'=>9],
    'coahuila'         => ['label'=>'Coahuila',          'municipioFile'=>'municipio-coahuila.geojson',         'seccionFile'=>'seccion-coahuila.geojson',         'centroide'=>[27.3,  -102.0],  'zoom'=>7],
    'guanajuato'       => ['label'=>'Guanajuato',        'municipioFile'=>'municipio-guanajuato.geojson',       'seccionFile'=>'seccion-guanajuato.geojson',       'centroide'=>[21.0,  -101.25], 'zoom'=>8],
    'nuevo-leon'       => ['label'=>'Nuevo León',        'municipioFile'=>'municipio-nuevo-leon.geojson',       'seccionFile'=>'seccion-nuevo-leon.geojson',       'centroide'=>[25.5,  -99.9],   'zoom'=>7],
    'san-luis-potosi'  => ['label'=>'San Luis Potosí',   'municipioFile'=>'municipio-san-luis-potosi.geojson',  'seccionFile'=>'seccion-san-luis-potosi.geojson',  'centroide'=>[22.2,  -100.98], 'zoom'=>7],
    'tamaulipas'       => ['label'=>'Tamaulipas',        'municipioFile'=>'municipio-tamaulipas.geojson',       'seccionFile'=>'seccion-tamaulipas.geojson',       'centroide'=>[24.3,  -98.8],   'zoom'=>7],
    'zacatecas'        => ['label'=>'Zacatecas',         'municipioFile'=>'municipio-zacatecas.geojson',        'seccionFile'=>'seccion-zacatecas.geojson',        'centroide'=>[23.0,  -102.5],  'zoom'=>7],
    'campeche'         => ['label'=>'Campeche',          'municipioFile'=>'municipio-campeche.geojson',         'seccionFile'=>'seccion-campeche.geojson',         'centroide'=>[18.9,  -90.2],   'zoom'=>7],
    'chiapas'          => ['label'=>'Chiapas',           'municipioFile'=>'municipio-chiapas.geojson',          'seccionFile'=>'seccion-chiapas.geojson',          'centroide'=>[16.75, -92.6],   'zoom'=>7],
    'oaxaca'           => ['label'=>'Oaxaca',            'municipioFile'=>'municipio-oaxaca.geojson',           'seccionFile'=>'seccion-oaxaca.geojson',           'centroide'=>[17.0,  -96.7],   'zoom'=>7],
    'quintana-roo'     => ['label'=>'Quintana Roo',      'municipioFile'=>'municipio-quintana-roo.geojson',     'seccionFile'=>'seccion-quintana-roo.geojson',     'centroide'=>[19.5,  -88.0],   'zoom'=>7],
    'tabasco'          => ['label'=>'Tabasco',           'municipioFile'=>'municipio-tabasco.geojson',          'seccionFile'=>'seccion-tabasco.geojson',          'centroide'=>[18.0,  -92.8],   'zoom'=>8],
    'veracruz'         => ['label'=>'Veracruz',          'municipioFile'=>'municipio-veracruz.geojson',         'seccionFile'=>'seccion-veracruz.geojson',         'centroide'=>[19.5,  -96.9],   'zoom'=>7],
    'yucatan'          => ['label'=>'Yucatán',           'municipioFile'=>'municipio-yucatan.geojson',          'seccionFile'=>'seccion-yucatan.geojson',          'centroide'=>[20.7,  -89.1],   'zoom'=>8],
    'ciudad-de-mexico' => ['label'=>'Ciudad de México',  'municipioFile'=>'municipio-ciudad-de-mexico.geojson', 'seccionFile'=>'seccion-ciudad-de-mexico.geojson', 'centroide'=>[19.43, -99.13],  'zoom'=>10],
    'guerrero'         => ['label'=>'Guerrero',          'municipioFile'=>'municipio-guerrero.geojson',         'seccionFile'=>'seccion-guerrero.geojson',         'centroide'=>[17.55, -99.5],   'zoom'=>7],
    'hidalgo'          => ['label'=>'Hidalgo',           'municipioFile'=>'municipio-hidalgo.geojson',          'seccionFile'=>'seccion-hidalgo.geojson',          'centroide'=>[20.5,  -98.8],   'zoom'=>8],
    'morelos'          => ['label'=>'Morelos',           'municipioFile'=>'municipio-morelos.geojson',          'seccionFile'=>'seccion-morelos.geojson',          'centroide'=>[18.7,  -99.1],   'zoom'=>9],
    'puebla'           => ['label'=>'Puebla',            'municipioFile'=>'municipio-puebla.geojson',           'seccionFile'=>'seccion-puebla.geojson',           'centroide'=>[19.0,  -97.9],   'zoom'=>8],
    'tlaxcala'         => ['label'=>'Tlaxcala',          'municipioFile'=>'municipio-tlaxcala.geojson',         'seccionFile'=>'seccion-tlaxcala.geojson',         'centroide'=>[19.32, -98.23],  'zoom'=>9],
    'colima'           => ['label'=>'Colima',            'municipioFile'=>'municipio-colima.geojson',           'seccionFile'=>'seccion-colima.geojson',           'centroide'=>[19.12, -103.72], 'zoom'=>9],
    'estado-de-mexico' => ['label'=>'Estado de México',  'municipioFile'=>'municipio-estado-de-mexico.geojson', 'seccionFile'=>'seccion-estado-de-mexico.geojson', 'centroide'=>[19.35, -99.65],  'zoom'=>8],
    'michoacan'        => ['label'=>'Michoacán',         'municipioFile'=>'municipio-michoacan.geojson',        'seccionFile'=>'seccion-michoacan.geojson',        'centroide'=>[19.0,  -101.9],  'zoom'=>7],
    'queretaro'        => ['label'=>'Querétaro',         'municipioFile'=>'municipio-queretaro.geojson',        'seccionFile'=>'seccion-queretaro.geojson',        'centroide'=>[20.6,  -100.4],  'zoom'=>8],
];

// Determinar clave del estado del usuario
$estado_key_map = [
    'Sonora'=>'sonora','Chihuahua'=>'chihuahua','Sinaloa'=>'sinaloa','Jalisco'=>'jalisco',
    'Durango'=>'durango','Nayarit'=>'nayarit','Baja California'=>'bc','Baja California Sur'=>'bcs',
    'Aguascalientes'=>'aguascalientes','Coahuila'=>'coahuila','Guanajuato'=>'guanajuato',
    'Nuevo León'=>'nuevo-leon','San Luis Potosí'=>'san-luis-potosi','Tamaulipas'=>'tamaulipas',
    'Zacatecas'=>'zacatecas','Campeche'=>'campeche','Chiapas'=>'chiapas','Oaxaca'=>'oaxaca',
    'Quintana Roo'=>'quintana-roo','Tabasco'=>'tabasco','Veracruz'=>'veracruz','Yucatán'=>'yucatan',
    'Ciudad de México'=>'ciudad-de-mexico','Guerrero'=>'guerrero','Hidalgo'=>'hidalgo',
    'Morelos'=>'morelos','Puebla'=>'puebla','Tlaxcala'=>'tlaxcala','Colima'=>'colima',
    'Estado de México'=>'estado-de-mexico','Michoacán'=>'michoacan','Querétaro'=>'queretaro',
];
$estado_key_inicial = $estado_key_map[$user_estado] ?? '';

$geojsonPath = __DIR__ . '/../../geojson/';

// Solo cargar el estado del usuario
$estadosData = [];
if ($estado_key_inicial && isset($todos_estados[$estado_key_inicial])) {
    $eCfg    = $todos_estados[$estado_key_inicial];
    $munFile = $geojsonPath . $eCfg['municipioFile'];
    $munData = file_exists($munFile) ? json_decode(file_get_contents($munFile), true) : null;
    $key     = $estado_key_inicial;

    // Excepciones: en el servidor estos archivos de distritos se guardaron con el
    // nombre completo del estado en vez de la clave corta (a diferencia de
    // municipioFile/seccionFile, que sí usan la clave corta consistentemente).
    $distritoFileKeyMap = [
        'bc'  => 'baja-california',
        'bcs' => 'baja-california-sur',
    ];
    $distritoFileKey = $distritoFileKeyMap[$key] ?? $key;

    $estadosData[$key] = [
        'label'      => $eCfg['label'],
        'centroide'  => $eCfg['centroide'],
        'zoom'       => $eCfg['zoom'],
        'municipio'  => $munData,
        'seccionUrl' => '../../geojson/' . $eCfg['seccionFile'],
        'distFedUrl' => '../../geojson/distrito-federal-' . $distritoFileKey . '.geojson',
        'distLocUrl' => '../../geojson/distrito-local-'   . $distritoFileKey . '.geojson',
        'coloniaUrl' => (function($k) {
            $map = [
                'sonora'=>'colonias-sonora.geojson','bc'=>'colonias-baja-california.geojson',
                'bcs'=>'colonias-baja-california-sur.geojson','chihuahua'=>'colonias-chihuahua.geojson',
                'durango'=>'colonias-durango.geojson','sinaloa'=>'colonias-sinaloa.geosjon.geojson',
                'nayarit'=>'colonias-nayarit.geojson','jalisco'=>'colonias-jalisco.geosjon.geojson',
                'aguascalientes'=>'colonias-aguascalientes.geojson','coahuila'=>'colonias-coahuila.geojson',
                'guanajuato'=>'colonias-guanajuato.geojson','nuevo-leon'=>'colonias-nuevo-leon.geojson',
                'san-luis-potosi'=>'colonias-san-luis-potosi.geojson','tamaulipas'=>'colonias-tamaulipas.geojson',
                'zacatecas'=>'colonias-zacatecas.geojson','campeche'=>'colonias-campeche.geojson',
                'chiapas'=>'colonias-chiapas.geojson','oaxaca'=>'colonias-oaxaca.geosjon.geojson',
                'quintana-roo'=>null,'tabasco'=>'colonias-tabasco.geojson',
                'veracruz'=>'colonias-veracruz.geojson','yucatan'=>'colonias-yucatan.geojson',
                'ciudad-de-mexico'=>'colonias-ciudad-de-mexico.geojson','guerrero'=>'colonias-guerrero.geojson',
                'hidalgo'=>'colonias-hidalgo.geojson','morelos'=>'colonias-morelos.geojson',
                'puebla'=>'colonias-puebla.geojson','tlaxcala'=>'colonias-tlaxcala.geojson',
                'colima'=>'colonias-colima.geojson','estado-de-mexico'=>'colonias-estado-de-mexico.geojson',
                'michoacan'=>'colonias-michoacan.geojson','queretaro'=>'colonias-queretaro.geosjon.geojson',
            ];
            $f = $map[$k] ?? null;
            return $f ? '../../geojson/' . $f : null;
        })($key),
    ];
}

// ── Zonas de la región (con secciones para pintar en mapa sin fetch externo) ──
$zonas_region = [];
try {
    if (!empty($user_region)) {
        $stZ = $pdo->prepare("
            SELECT z.id, z.nombre, z.secciones
            FROM zonas_estatales z
            INNER JOIN regiones_estatales r ON z.estructura_id = r.id
            WHERE r.id = ?
            ORDER BY z.nombre
        ");
        $stZ->execute([$user_region]);
        $rows_zonas = $stZ->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows_zonas as $rz) {
            $zonas_region[] = [
                'id'       => (int)$rz['id'],
                'nombre'   => $rz['nombre'],
                'secciones'=> json_decode($rz['secciones'] ?? '[]', true) ?: [],
            ];
        }
    }
} catch (Exception $ez) { /* silencioso */ }

// ── Datos por sección para la hoja de impresión (L.N., distritos, municipio) ──
// Mismo origen de datos que la hoja de impresión del rol Zona (territorio.php de CZ).
$seccionesDatosImpresion = [];
try {
    $secsImpresion = [];
    foreach ($zonas_region as $zr) {
        foreach ($zr['secciones'] as $s) { $secsImpresion[trim((string)$s)] = true; }
    }
    $secsImpresion = array_map('strval', array_keys($secsImpresion));
    if (!empty($secsImpresion) && isset($pdo) && $user_estado) {
        $phI = implode(',', array_fill(0, count($secsImpresion), '?'));
        $stI = $pdo->prepare("
            SELECT sec.seccion, sec.distrito_local, sec.distrito_federal,
                   m.nombre AS municipio,
                   COALESCE(se.lista_nominal, 0) AS lista_nominal
            FROM secciones sec
            LEFT JOIN municipios m ON m.id = sec.municipio_id
            LEFT JOIN secciones_electorales se
                   ON se.nombre_seccion = sec.seccion AND se.estado = sec.estado_nombre
            WHERE sec.seccion IN ($phI) AND sec.estado_nombre = ?
        ");
        $stI->execute(array_merge($secsImpresion, [$user_estado]));
        foreach ($stI->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $seccionesDatosImpresion[(string)$r['seccion']] = [
                'ln'  => (int)$r['lista_nominal'],
                'dl'  => ($r['distrito_local']   !== null && $r['distrito_local']   !== '') ? (string)$r['distrito_local']   : '',
                'df'  => ($r['distrito_federal'] !== null && $r['distrito_federal'] !== '') ? (string)$r['distrito_federal'] : '',
                'mun' => (string)($r['municipio'] ?? ''),
            ];
        }
    }
} catch (Exception $eImp) { error_log('territorio regional impresion: ' . $eImp->getMessage()); }

// ── Modo Vista: cargar secciones de la zona específica ──
if ($modoVista && $vistZonaId) {
    try {
        $st = $pdo->prepare("SELECT secciones FROM zonas_estatales WHERE id = ? LIMIT 1");
        $st->execute([$vistZonaId]);
        $zr = $st->fetch();
        if ($zr) {
            $seccionesZona = json_decode($zr['secciones'] ?? '[]', true) ?: [];
        }
    } catch (Exception $e) {}
}

$estadosJson       = json_encode($estadosData);
$seccionesJson     = json_encode(array_values(array_map('strval', $secciones_region)));
$seccionesZonaJson = json_encode(array_values(array_map('strval', $seccionesZona)));
$zonasRegionJson   = json_encode($zonas_region);
$seccionesDatosImpJson = json_encode((object)$seccionesDatosImpresion, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cartografía Electoral - Región <?= htmlspecialchars($region_nombre) ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;600&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
  <style>
    :root {
      --verde:        #1a7a3c;
      --verde-hover:  #0f5228;
      --verde-acento: #22a050;
      --blanco:       #ffffff;
      --gris-panel:   #f5f6f8;
      --gris-borde:   #e2e4e8;
      --gris-medio:   #eaecf0;
      --texto:        #1a1e2a;
      --texto-dim:    #6b7280;
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'IBM Plex Sans', sans-serif; background: var(--blanco); color: var(--texto); height: 100vh; display: flex; flex-direction: column; overflow: hidden; padding-left: 280px; }

    /* ── Header ── */
    header { background: var(--blanco); border-bottom: 1px solid var(--gris-borde); padding: 0 24px; height: 52px; display: flex; align-items: center; justify-content: space-between; flex-shrink: 0; z-index: 1000; }
    .header-left { display: flex; align-items: center; gap: 12px; }
    .logo-dot { width: 10px; height: 10px; background: var(--verde-acento); border-radius: 50%; box-shadow: 0 0 8px rgba(34,160,80,0.35); }
    .search-box { display: flex; align-items: center; background: var(--gris-panel); border: 1px solid var(--gris-borde); border-radius: 6px; padding: 0 10px; height: 32px; }
    .search-box input { border: none; background: transparent; outline: none; font-size: 12px; width: 150px; font-family: 'IBM Plex Sans', sans-serif; }
    .search-box button { background: none; border: none; color: var(--texto-dim); cursor: pointer; padding: 5px; }
    .search-box button:hover { color: var(--verde); }
    header h1 { font-size: 13px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: var(--texto); }
    header span { font-size: 12px; color: var(--texto-dim); font-family: 'IBM Plex Mono', monospace; }
    .region-badge { background: rgba(26,122,60,0.1); color: var(--verde); border: 1px solid rgba(26,122,60,0.25); border-radius: 6px; padding: 4px 10px; font-size: 11px; font-weight: 700; font-family: 'IBM Plex Mono', monospace; letter-spacing: 0.05em; }

    /* ── Modal Evento ── */
    .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(255,255,255,0.9); backdrop-filter: blur(5px); z-index: 9000; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.3s ease; }
    .modal-overlay.open { display: flex; opacity: 1; }
    .modal { background: var(--blanco); border-radius: 20px; border: 1px solid var(--gris-borde); box-shadow: 0 25px 50px rgba(0,0,0,0.1); width: 90%; max-width: 680px; padding: 40px; position: relative; transform: translateY(30px); opacity: 0; transition: all 0.4s ease-out; max-height: 90vh; overflow-y: auto; }
    .modal-overlay.open .modal { transform: translateY(0); opacity: 1; }
    .modal-close { position: absolute; top: 25px; right: 25px; font-size: 22px; cursor: pointer; color: #ccc; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: 50%; border: none; background: transparent; transition: all 0.3s; }
    .modal-close:hover { color: var(--verde); background: #f0f7f0; transform: rotate(90deg); }
    .modal-title { font-size: 24px; font-weight: 800; margin-bottom: 5px; color: var(--texto); }
    .eje-btn-ag { padding:10px 8px; border-radius:10px; border:2px solid #e5e7eb; font-size:11px; font-weight:700; text-align:center; cursor:pointer; background:#f9fafb; color:#6b7280; transition:all 0.15s; font-family:inherit; }
    .eje-btn-ag.sel-voto        { background:rgba(26,122,60,.12);  border-color:var(--verde);  color:var(--verde); }
    .eje-btn-ag.sel-comunicacion{ background:rgba(33,150,243,.12); border-color:#2196f3; color:#2196f3; }
    .eje-btn-ag.sel-organizacion{ background:rgba(156,39,176,.12); border-color:#9c27b0; color:#9c27b0; }
    .modal-footer-ag { display:flex; justify-content:space-between; align-items:center; margin-top:30px; padding-top:25px; border-top:1px solid var(--gris-borde); }
    .btn-save-ag  { background:var(--verde); color:white; border:none; padding:12px 28px; border-radius:12px; font-weight:700; cursor:pointer; font-size:14px; font-family:inherit; transition:all 0.2s; }
    .btn-save-ag:hover { background:var(--verde-hover); }
    .btn-cancel-ag { background:var(--blanco); color:var(--texto-dim); border:1px solid var(--gris-borde); padding:12px 22px; border-radius:12px; font-weight:600; cursor:pointer; font-size:13px; font-family:inherit; }
    .btn-danger-ag { background:#ffeaea; color:#d32f2f; border:1px solid #fecaca; padding:12px 20px; border-radius:12px; font-weight:700; cursor:pointer; font-size:13px; font-family:inherit; }
    .seccion-badge { display:inline-flex; align-items:center; gap:8px; background:rgba(26,122,60,0.1); color:var(--verde); border:1px solid rgba(26,122,60,0.3); border-radius:8px; padding:8px 14px; font-size:13px; font-weight:700; font-family:'IBM Plex Mono',monospace; }
    .seccion-badge.empty { background:var(--gris-panel); color:var(--texto-dim); border-color:var(--gris-borde); font-family:'IBM Plex Sans',sans-serif; font-weight:500; }
    .eje-badge-det { display:inline-flex; align-items:center; gap:6px; padding:4px 14px; border-radius:20px; font-size:12px; font-weight:700; border:1px solid; margin-bottom:15px; }
    .detail-grid-ag { display:grid; grid-template-columns:repeat(3,1fr); gap:20px; margin-top:20px; padding-top:20px; border-top:1px solid var(--gris-borde); }
    .detail-item-ag strong { display:block; font-size:11px; color:var(--texto-dim); text-transform:uppercase; margin-bottom:5px; letter-spacing:0.5px; }
    .detail-item-ag span { font-size:15px; color:var(--texto); font-weight:600; }

    /* ── Evento Marker ── */
    .evento-marker { background:transparent !important; border:none !important; box-shadow:none !important; pointer-events:none; }
    .evento-pin { display:flex; flex-direction:column; align-items:center; }
    .evento-pin-icon { width:26px; height:26px; border-radius:50% 50% 50% 0; transform:rotate(-45deg); display:flex; align-items:center; justify-content:center; box-shadow:0 2px 8px rgba(0,0,0,0.25); border:2px solid white; }
    .evento-pin-icon i { transform:rotate(45deg); color:white; font-size:10px; }
    .evento-pin-label { margin-top:3px; font-family:'IBM Plex Mono',monospace; font-size:8px; font-weight:600; color:#0f5228; background:rgba(255,255,255,0.85); padding:1px 4px; border-radius:3px; white-space:nowrap; }

    /* ── Layout ── */
    .main { display:flex; flex:1; overflow:hidden; }

    /* ── Panel Lateral ── */
    .panel { width:260px; background:var(--gris-panel); border-right:1px solid var(--gris-borde); display:flex; flex-direction:column; flex-shrink:0; overflow-y:auto; }
    .panel-section { padding:16px; border-bottom:1px solid var(--gris-borde); }
    .panel-label { font-size:10px; font-weight:600; letter-spacing:0.12em; text-transform:uppercase; color:var(--texto-dim); margin-bottom:10px; }
    .stats-grid { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
    .stat-box { background:var(--blanco); border:1px solid var(--gris-borde); border-radius:6px; padding:10px; }
    .stat-num { font-family:'IBM Plex Mono',monospace; font-size:20px; font-weight:600; color:var(--verde); line-height:1; }
    .stat-label { font-size:10px; color:var(--texto-dim); margin-top:3px; }

    .layer-item { display:flex; align-items:center; justify-content:space-between; padding:8px 0; cursor:pointer; }
    .layer-left { display:flex; align-items:center; gap:8px; }
    .layer-color { width:12px; height:12px; border-radius:2px; flex-shrink:0; }
    .layer-name { font-size:12px; color:var(--texto); transition:color 0.15s; }
    .layer-item:hover .layer-name { color:var(--verde); }
    .toggle { width:28px; height:16px; background:#ccc; border-radius:8px; position:relative; transition:background 0.2s; flex-shrink:0; }
    .toggle.on { background:var(--verde); }
    .toggle::after { content:''; position:absolute; width:12px; height:12px; background:white; border-radius:50%; top:2px; left:2px; transition:left 0.2s; }
    .toggle.on::after { left:14px; }
    .ev-count-badge { font-family:'IBM Plex Mono',monospace; font-size:9px; font-weight:700; background:var(--verde); color:white; padding:1px 6px; border-radius:8px; margin-left:4px; }

    /* ── Selector de Zona ── */
    .zona-select { width:100%; height:30px; padding:0 10px; border:1px solid var(--gris-borde); border-radius:5px; background:var(--blanco); font-family:'IBM Plex Sans',sans-serif; font-size:11px; font-weight:600; color:var(--texto); cursor:pointer; outline:none; appearance:none; -webkit-appearance:none; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%236b7280'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 8px center; margin-bottom:8px; transition:border-color 0.15s; }
    .zona-select:focus { border-color:var(--verde); }
    .zona-btn-limpiar { width:100%; padding:6px; border:1px solid var(--gris-borde); border-radius:5px; background:var(--gris-panel); font-size:10px; font-weight:600; color:var(--texto-dim); cursor:pointer; font-family:'IBM Plex Sans',sans-serif; transition:all 0.15s; margin-top:2px; }
    .zona-btn-limpiar:hover { background:var(--gris-borde); color:var(--texto); }

    /* ── Filtros fecha ── */
    .filter-row { display:grid; grid-template-columns:1fr 1fr; gap:6px; margin-bottom:6px; }
    .filter-select { height:28px; padding:0 6px; border:1px solid var(--gris-borde); border-radius:5px; background:var(--blanco); font-family:'IBM Plex Sans',sans-serif; font-size:10px; font-weight:600; color:var(--texto); cursor:pointer; outline:none; appearance:none; -webkit-appearance:none; transition:border-color 0.15s; }
    .filter-select:focus { border-color:var(--verde); }
    .filter-label-row { display:flex; justify-content:space-between; align-items:center; margin-bottom:6px; }
    .filter-clear-btn { font-size:9px; color:var(--verde); cursor:pointer; background:none; border:none; font-family:'IBM Plex Sans',sans-serif; font-weight:600; padding:0; }
    .filter-clear-btn:hover { text-decoration:underline; }

    /* ── Panel Info Sección ── */
    #info-panel { padding:16px; display:none; overflow-y:auto; max-height:calc(100vh - 220px); }
    #info-panel.visible { display:block; }
    .info-title { font-family:'IBM Plex Mono',monospace; font-size:22px; font-weight:600; color:var(--verde); margin-bottom:12px; }
    .info-row { display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid var(--gris-borde); font-size:12px; }
    .info-row:last-child { border-bottom:none; }
    .info-key { color:var(--texto-dim); }
    .info-val { font-family:'IBM Plex Mono',monospace; color:var(--texto); }
    .info-val.verde { color:var(--verde); font-weight:700; }
    .db-sep { border:none; border-top:1px solid var(--gris-borde); margin:14px 0 10px; }
    .db-section-label { font-size:9px; font-weight:700; letter-spacing:0.12em; text-transform:uppercase; color:var(--verde); margin:14px 0 6px; display:flex; align-items:center; gap:5px; }
    .db-progress-bg { height:6px; border-radius:3px; background:var(--gris-medio); overflow:hidden; margin:5px 0 3px; }
    .db-progress-fill { height:100%; border-radius:3px; background:linear-gradient(90deg,#1a7a3c,#22a050); transition:width 0.5s ease; }
    .db-progress-meta { display:flex; justify-content:space-between; font-size:9px; color:var(--texto-dim); font-family:'IBM Plex Mono',monospace; }
    .act-mini-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:5px; margin:6px 0; }
    .act-mini-card { background:var(--blanco); border:1px solid var(--gris-borde); border-radius:5px; padding:7px 4px; text-align:center; }
    .act-mini-num { font-family:'IBM Plex Mono',monospace; font-size:16px; font-weight:600; color:var(--verde); line-height:1; }
    .act-mini-lbl { font-size:8px; color:var(--texto-dim); margin-top:2px; letter-spacing:0.04em; }
    .edad-chips { display:flex; flex-wrap:wrap; gap:4px; margin:5px 0 3px; }
    .edad-chip { font-size:9px; font-weight:700; padding:3px 6px; border-radius:4px; font-family:'IBM Plex Mono',monospace; border:1px solid transparent; }
    .db-open-field { margin-bottom:7px; }
    .db-open-label { font-size:9px; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; color:var(--texto-dim); margin-bottom:3px; }
    .db-open-text { font-size:11px; color:var(--texto); background:var(--gris-panel); border-radius:4px; padding:6px 8px; line-height:1.5; }
    .btn-ver-detalles { display:flex; align-items:center; justify-content:center; gap:6px; width:100%; margin-top:14px; padding:9px; background:rgba(26,122,60,0.1); color:var(--verde); border:1.5px solid rgba(26,122,60,0.3); border-radius:6px; font-size:11px; font-weight:700; cursor:pointer; font-family:inherit; transition:all 0.15s; }
    .btn-ver-detalles:hover { background:var(--verde); color:white; border-color:var(--verde); }
    .db-link-btn { display:flex; align-items:center; justify-content:center; gap:6px; width:100%; margin-top:12px; padding:8px; background:rgba(26,122,60,0.08); color:var(--verde); border:1.5px solid rgba(26,122,60,0.25); border-radius:6px; font-size:10px; font-weight:700; cursor:pointer; font-family:inherit; transition:all 0.15s; }
    .db-link-btn:hover { background:var(--verde); color:white; border-color:var(--verde); }

    /* ── Mapa ── */
    #map { flex:1; }
    .lbl-seccion { background:transparent !important; border:none !important; box-shadow:none !important; font-family:'IBM Plex Mono',monospace; font-size:9px; font-weight:600; color:#1a7a3c; text-shadow:0 0 3px rgba(255,255,255,1),0 0 6px rgba(255,255,255,0.8); white-space:nowrap; pointer-events:none; }

    body.escala-labels-2 .lbl-seccion { font-size: 18px; }
    body.escala-labels-3 .lbl-seccion { font-size: 27px; }

    .btn-escala { display:flex; gap:4px; }
    .btn-escala button { flex:1; font-family:'IBM Plex Mono',monospace; font-size:10px; font-weight:700; padding:5px 0; border-radius:5px; border:1px solid var(--gris-borde); background:var(--blanco); color:var(--texto-dim); cursor:pointer; }
    .btn-escala button.activo { background:var(--verde); color:#fff; border-color:var(--verde); }

    #btn-imprimir-mapa { width:100%; margin-top:8px; padding:8px 0; font-family:'IBM Plex Mono',monospace; font-size:11px; font-weight:700; border-radius:6px; border:1px solid var(--verde); background:var(--verde); color:#fff; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:6px; }
    #btn-imprimir-mapa:hover { filter:brightness(1.08); }

    select#select-capa-base { width:100%; font-size:11px; font-family:'IBM Plex Sans',sans-serif; padding:6px 8px; border-radius:5px; border:1px solid var(--gris-borde); background:var(--blanco); color:var(--texto); outline:none; }

    /* Hoja de impresión (mismo formato que el rol Zona: logo + tabla SEC/L.N. + zona + coordinador) */
    #hoja-impresion-pie { display:none; }
    #hoja-impresion-pie {
      grid-template-columns: 100px 1.4fr 1fr 1fr;
      font-family: Arial, sans-serif; color:#111;
    }
    #hoja-impresion-pie > div { border-right:1px solid #333; padding:6px 8px; }
    #hoja-impresion-pie > div:last-child { border-right:none; }
    .hip-logo { display:flex; align-items:center; justify-content:center; }
    .hip-logo img { max-width:88px; max-height:70px; object-fit:contain; }
    .hip-tabla table { width:100%; border-collapse:collapse; font-size:9px; }
    .hip-tabla th { text-align:left; font-size:8px; color:#555; padding:1px 4px; }
    .hip-tabla td { padding:1px 4px; }
    .hip-zona-label { font-size:9px; font-weight:700; }
    .hip-zona-code { font-size:16px; font-weight:800; }
    .hip-coord { font-size:9px; line-height:1; }
    .hip-coord-title { font-weight:700; margin-bottom:6px; }
    .hip-coord div:not(.hip-coord-title) { border-bottom:1px solid #ccc; padding:5px 0; }
    .hip-total { display:flex; flex-direction:column; justify-content:center; align-items:center; text-align:center; }

    @media print {
      /* Quita del flujo todo lo que no es el mapa ni la hoja (menú lateral de la
         app, botón hamburguesa, banners, toast, header y panel) */
      body > *:not(.main):not(#hoja-impresion-pie) { display:none !important; }
      header, .panel { display:none !important; }

      body * { visibility: hidden !important; }
      #map, #map *,
      #hoja-impresion-pie, #hoja-impresion-pie * { visibility: visible !important; }

      body { background:#fff !important; padding:0 !important; margin:0 !important; }
      .main { display:block !important; }

      #map {
        position: fixed !important; top:0; left:0;
        width:100vw !important; height:100vh !important;
      }
      /* Con zona seleccionada: mapa arriba + hoja de datos abajo (igual que rol Zona) */
      body.imprimir-con-pie #map {
        height:74vh !important;
        border:1.5px solid #333;
      }
      body.imprimir-con-pie #hoja-impresion-pie {
        display:grid !important;
        position: fixed !important; top:74vh; left:0;
        width:100vw !important;
        border:1.5px solid #333; border-top:none;
      }
      .leaflet-control-zoom, .leaflet-control-attribution, .leaflet-control-layers { display:none !important; }
    }

    .leaflet-container { background:#f4f6f4; }
    .leaflet-interactive:focus { outline:none !important; }
    .leaflet-control-attribution { display:none !important; }
    .leaflet-control-zoom { border:1px solid var(--gris-borde) !important; border-radius:6px !important; overflow:hidden; box-shadow:0 1px 4px rgba(0,0,0,0.08) !important; }
    .leaflet-control-zoom a { background:white !important; color:var(--texto) !important; border-bottom:1px solid var(--gris-borde) !important; width:28px !important; height:28px !important; line-height:28px !important; }
    .leaflet-control-zoom a:hover { background:var(--gris-medio) !important; color:var(--verde) !important; }

    /* ── Modal Detalle Sección ── */
    .sec-modal-overlay { display:none; position:fixed; inset:0; background:rgba(255,255,255,0.9); backdrop-filter:blur(5px); z-index:9500; align-items:center; justify-content:center; opacity:0; transition:opacity 0.3s ease; }
    .sec-modal-overlay.open { display:flex; opacity:1; }
    .sec-modal-box { background:#fff; border-radius:20px; border:1px solid #e9ecef; box-shadow:0 25px 50px rgba(0,0,0,0.12); width:92%; max-width:780px; padding:40px; position:relative; transform:translateY(30px); opacity:0; transition:all 0.4s ease-out; max-height:92vh; overflow-y:auto; font-family:'IBM Plex Sans',sans-serif; }
    .sec-modal-overlay.open .sec-modal-box { transform:translateY(0); opacity:1; }
    .sec-modal-close { position:absolute; top:20px; right:20px; font-size:20px; cursor:pointer; color:#ccc; width:36px; height:36px; display:flex; align-items:center; justify-content:center; border-radius:50%; border:none; background:transparent; transition:all 0.3s; }
    .sec-modal-close:hover { color:var(--verde); background:#f0f7f0; transform:rotate(90deg); }
    .sec-modal-title { font-size:36px; font-weight:900; color:#1a1e2a; line-height:1; margin-bottom:6px; }
    .sec-modal-subtitle { font-size:14px; color:#999; margin-bottom:22px; }
    .sec-det-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:12px; margin-bottom:20px; }
    .sec-det-item { display:flex; flex-direction:column; gap:4px; }
    .sec-det-item label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#6c757d; }
    .sec-det-item span { font-size:14px; color:#1a1e2a; font-weight:600; }
    .sec-section-title { font-size:13px; font-weight:800; text-transform:uppercase; letter-spacing:1px; color:#1a7a3c; margin:20px 0 12px; padding-bottom:8px; border-bottom:2px solid #e9ecef; display:flex; align-items:center; gap:8px; }
    .sec-nominal-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; margin-bottom:16px; }
    .sec-nominal-card { background:#f8f9fa; border-radius:12px; padding:14px; text-align:center; border:1px solid #e9ecef; }
    .sec-nominal-num { font-size:24px; font-weight:900; line-height:1; }
    .sec-nominal-lbl { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#6c757d; margin-top:3px; }
    .sec-modal-progress-bg { height:10px; border-radius:5px; background:#e9ecef; overflow:hidden; margin:8px 0 4px; }
    .sec-modal-progress-fill { height:100%; border-radius:5px; background:linear-gradient(90deg,#1a7a3c,#22a050); transition:width 0.6s ease; }
    .sec-modal-progress-lbl { display:flex; justify-content:space-between; font-size:12px; font-weight:700; color:#6c757d; }
    .sec-act-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; margin:10px 0; }
    .sec-act-card { background:#f8f9fa; border-radius:10px; padding:14px; text-align:center; }
    .sec-act-num { font-size:26px; font-weight:900; color:#1a7a3c; }
    .sec-act-lbl { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#6c757d; margin-top:2px; }
    .sec-open-field { margin-bottom:10px; }
    .sec-open-label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#6c757d; margin-bottom:4px; }
    .sec-open-text { font-size:13px; color:#1a1e2a; background:#f8f9fa; border-radius:8px; padding:10px 12px; line-height:1.5; }
    .sec-modal-footer { display:flex; justify-content:flex-end; margin-top:24px; padding-top:20px; border-top:1px solid #e9ecef; }
    .sec-modal-btn-close { background:#fff; color:#6c757d; border:1px solid #e9ecef; padding:11px 22px; border-radius:12px; font-weight:600; cursor:pointer; font-size:13px; font-family:inherit; transition:0.2s; }
    .sec-modal-btn-close:hover { border-color:#1a7a3c; color:#1a7a3c; }
      .lbl-distloc {
      background: rgba(255,255,255,0.92) !important;
      border: 1.5px solid #6a1b9a !important;
      box-shadow: 0 1px 4px rgba(0,0,0,0.15) !important;
      border-radius: 10px;
      font-family: 'IBM Plex Mono', monospace;
      font-size: 11px;
      font-weight: 800;
      color: #6a1b9a;
      padding: 2px 7px;
      white-space: nowrap;
      pointer-events: none;
    }
</style>
</head>
<body>

<?php if (file_exists(__DIR__ . '/../../includes/components/sidebar_regional.php'))
    include __DIR__ . '/../../includes/components/sidebar_regional.php'; ?>

<?php if ($modoSeleccion): ?>
<div id="sel-banner" style="position:fixed;top:0;left:280px;right:0;z-index:9999;background:#1a7a3c;color:white;padding:10px 20px;display:flex;align-items:center;justify-content:space-between;gap:12px;box-shadow:0 3px 14px rgba(0,0,0,0.25);">
  <div style="display:flex;align-items:center;gap:12px;">
    <i class="fas fa-vector-square" style="font-size:20px;opacity:0.9;"></i>
    <div>
      <div style="font-weight:800;font-size:13px;letter-spacing:0.02em;">
        MODO SELECCIÓN — <span style="font-weight:500;opacity:0.9;"><?= htmlspecialchars($vistZonaNombre) ?: 'Zona' ?></span>
      </div>
      <div id="sel-counter-txt" style="font-size:11px;opacity:0.85;margin-top:1px;">Haz clic en las secciones del mapa para seleccionarlas o deseleccionarlas</div>
    </div>
  </div>
  <div style="display:flex;gap:10px;align-items:center;flex-shrink:0;">
    <div style="background:rgba(255,255,255,0.18);padding:5px 14px;border-radius:20px;font-size:12px;font-weight:700;" id="sel-badge-cnt">0 seleccionadas</div>
    <div id="sel-badge-ln" style="display:none;background:#fef3c7;border:1px solid #fde68a;color:#92400e;padding:5px 14px;border-radius:20px;font-size:12px;font-weight:700;">
      <i class="fas fa-users" style="margin-right:5px;font-size:10px;"></i><span id="sel-ln-valor">0</span> LN
    </div>
    <button onclick="confirmarSeleccion()" style="background:white;color:#1a7a3c;border:none;padding:8px 20px;border-radius:8px;font-weight:800;font-size:13px;cursor:pointer;display:flex;align-items:center;gap:7px;" onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
      <i class="fas fa-check-circle"></i> Confirmar Selección
    </button>
    <button onclick="cancelarSeleccion()" style="background:rgba(255,255,255,0.12);color:white;border:1px solid rgba(255,255,255,0.35);padding:8px 14px;border-radius:8px;font-weight:600;font-size:12px;cursor:pointer;">
      <i class="fas fa-times"></i> Cancelar
    </button>
  </div>
</div>
<style>body { padding-top: 48px !important; }</style>
<?php elseif ($modoVista): ?>
<div id="vista-banner" style="position:fixed;top:0;left:280px;right:0;z-index:9999;background:#1565c0;color:white;padding:10px 20px;display:flex;align-items:center;justify-content:space-between;gap:12px;box-shadow:0 3px 14px rgba(0,0,0,0.25);">
  <div style="display:flex;align-items:center;gap:12px;">
    <i class="fas fa-map-marked-alt" style="font-size:20px;opacity:0.9;"></i>
    <div>
      <div style="font-weight:800;font-size:13px;letter-spacing:0.02em;">
        VISTA DE ZONA — <span style="font-weight:500;opacity:0.9;"><?= htmlspecialchars($vistZonaNombre) ?: 'Zona' ?></span>
      </div>
      <div style="font-size:11px;opacity:0.85;margin-top:1px;">Las secciones resaltadas en azul pertenecen a esta zona (solo lectura)</div>
    </div>
  </div>
  <button onclick="regresarEstructura()" style="background:white;color:#1565c0;border:none;padding:8px 20px;border-radius:8px;font-weight:800;font-size:13px;cursor:pointer;display:flex;align-items:center;gap:7px;" onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
    <i class="fas fa-arrow-left"></i> Regresar a Estructura
  </button>
</div>
<style>body { padding-top: 48px !important; }</style>
<?php endif; ?>

<header>
  <div class="header-left">
    <div class="logo-dot"></div>
    <h1>Cartografía Electoral</h1>
    <span><?= htmlspecialchars($user_estado) ?></span>
  </div>
  <div style="display:flex;align-items:center;gap:12px;">
    <div class="search-box">
      <input type="number" id="buscar-seccion-input" placeholder="Buscar sección..." onkeypress="if(event.key==='Enter') buscarSeccionMapa()">
      <button onclick="buscarSeccionMapa()"><i class="fas fa-search"></i></button>
    </div>
    <!-- badge y botón ocultos para el rol regional -->
    <span class="region-badge" style="display:none;">REGIÓN <?= htmlspecialchars(strtoupper($region_nombre)) ?> · <?= count($secciones_region) ?> SECCIONES</span>
    <button style="display:none;" onclick="abrirModal()">
      <i class="fa-solid fa-calendar-plus"></i> Agregar Evento
    </button>
  </div>
</header>

<div class="main">

  <!-- ── Panel lateral ── -->
  <aside class="panel">
    <div class="panel-section">
      <div class="panel-label">Estadísticas</div>
      <div class="stats-grid">
        <div class="stat-box">
          <div class="stat-num" id="stat-secciones"><?= count($secciones_region) ?></div>
          <div class="stat-label">Secciones</div>
        </div>
        <div class="stat-box">
          <div class="stat-num" id="stat-municipios">—</div>
          <div class="stat-label">Municipios</div>
        </div>
      </div>
    </div>

    <div class="panel-section">
      <div class="panel-label">Capas</div>

      <!-- 1. Municipio — encendida por defecto -->
      <div class="layer-item" id="row-municipio" onclick="toggleCapa('municipio')" style="display:none;">
        <div class="layer-left">
          <div class="layer-color" style="background:#e8f0eb;border:2px solid #4a7c5e;"></div>
          <span class="layer-name" id="label-municipio">Municipios</span>
        </div>
        <div class="toggle on" id="toggle-municipio"></div>
      </div>

      <!-- 2. Distritos Federales -->
      <div class="layer-item" id="row-distfed" onclick="toggleCapa('distFed')" style="display:none;">
        <div class="layer-left">
          <div class="layer-color" style="background:transparent;border:2px solid #1565c0;"></div>
          <span class="layer-name" id="label-distfed">Distritos Federales</span>
        </div>
        <div class="toggle" id="toggle-distFed"></div>
      </div>

      <!-- 3. Distritos Locales -->
      <div class="layer-item" id="row-distloc" onclick="toggleCapa('distLoc')" style="display:none;">
        <div class="layer-left">
          <div class="layer-color" style="background:transparent;border:2px solid #6a1b9a;"></div>
          <span class="layer-name" id="label-distloc">Distritos Locales</span>
        </div>
        <div class="toggle" id="toggle-distLoc"></div>
      </div>

      <!-- 4. Secciones -->
      <div class="layer-item" id="row-secciones" onclick="toggleCapa('secciones')" style="display:none;">
        <div class="layer-left">
          <div class="layer-color" style="background:transparent;border:2px solid #22a050;"></div>
          <span class="layer-name" id="label-secciones">Secciones</span>
        </div>
        <div class="toggle" id="toggle-secciones"></div>
      </div>

      <!-- 5. Secciones de la Región (exclusiva rol regional) -->
      <div class="layer-item" id="row-region" onclick="toggleCapa('region')" style="display:none;">
        <div class="layer-left">
          <div class="layer-color" style="background:rgba(26,122,60,0.35);border:2px solid #1a7a3c;"></div>
          <span class="layer-name">Secciones de la Región</span>
        </div>
        <div class="toggle" id="toggle-region"></div>
      </div>

      <!-- 6. Número de Sección -->
      <div class="layer-item" id="row-labels" onclick="toggleCapa('labels')" style="display:none;">
        <div class="layer-left">
          <div class="layer-color" style="background:#1a7a3c;border-radius:50%;"></div>
          <span class="layer-name">Número de Sección</span>
        </div>
        <div class="toggle" id="toggle-labels"></div>
      </div>

      <!-- 7. Colonias -->
      <div class="layer-item" id="row-colonias" onclick="toggleCapa('colonias')" style="display:none;">
        <div class="layer-left">
          <div class="layer-color" style="background:transparent;border:2px solid #00838f;border-radius:2px;"></div>
          <span class="layer-name" id="label-colonias">Colonias</span>
        </div>
        <div class="toggle" id="toggle-colonias"></div>
      </div>

      <!-- 8. Eventos -->
      <div class="layer-item" id="row-eventos" onclick="toggleCapaEventos()">
        <div class="layer-left">
          <div class="layer-color" style="background:#1a7a3c;border-radius:50%;"></div>
          <span class="layer-name">Eventos <span class="ev-count-badge" id="ev-count-badge">0</span></span>
          <span id="ev-sin-ubicacion" style="display:none;font-size:9px;color:var(--texto-dim);margin-left:6px;" title="Eventos de la región sin sección o coordenadas válidas para ubicarlos en el mapa"></span>
        </div>
        <div class="toggle" id="toggle-eventos"></div>
      </div>

      <!-- 9. Activaciones -->
      <div class="layer-item" id="row-activaciones" onclick="toggleCapaActivaciones()">
        <div class="layer-left">
          <div class="layer-color" style="background:#f59e0b;border-radius:2px;transform:rotate(45deg);width:10px;height:10px;margin:2px;"></div>
          <span class="layer-name">Activaciones <span class="ev-count-badge" id="act-count-badge" style="background:#f59e0b;">0</span></span>
        </div>
        <div class="toggle" id="toggle-activaciones"></div>
      </div>

      <div id="capas-placeholder" style="font-size:11px;color:var(--texto-dim);padding:6px 0;">Cargando estado…</div>
    </div>

    <!-- Selector de Zona (zonas de la región) -->
    <div class="panel-section" id="panel-zona-selector">
      <div class="panel-label">Pintar Zona</div>
      <select id="sel-zona" class="zona-select" onchange="onZonaChange()">
        <option value="">— Seleccionar zona —</option>
        <?php foreach ($zonas_region as $z): ?>
          <option value="<?= htmlspecialchars($z['id']) ?>" data-nombre="<?= htmlspecialchars($z['nombre']) ?>">
            <?= htmlspecialchars($z['nombre']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <button class="zona-btn-limpiar" onclick="limpiarZona()">
        <i class="fas fa-times-circle"></i> Limpiar selección
      </button>
    </div>

    <!-- Filtros fecha (solo eventos/activaciones de la región) -->
    <div class="panel-section" id="panel-filtros">
      <div class="filter-label-row">
        <span class="panel-label" style="margin-bottom:0;">Filtrar por fecha</span>
        <button class="filter-clear-btn" onclick="limpiarFiltros()"><i class="fas fa-undo"></i> Limpiar</button>
      </div>
      <div class="filter-row">
        <select id="filter-year" class="filter-select" onchange="aplicarFiltros()">
          <option value="">Año</option>
          <?php $cy=(int)date('Y'); for($y=$cy;$y>=$cy-4;$y--) echo "<option value=\"$y\">$y</option>"; ?>
        </select>
        <select id="filter-month" class="filter-select" onchange="aplicarFiltros()">
          <option value="">Mes</option>
          <?php
          $meses=['01'=>'Enero','02'=>'Febrero','03'=>'Marzo','04'=>'Abril','05'=>'Mayo','06'=>'Junio',
                  '07'=>'Julio','08'=>'Agosto','09'=>'Septiembre','10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre'];
          foreach($meses as $num=>$nombre) echo "<option value=\"$num\">$nombre</option>";
          ?>
        </select>
      </div>
      <div style="font-size:9px;color:var(--texto-dim);line-height:1.4;">Solo eventos y activaciones de la región.</div>
    </div>

    <!-- Panel info sección -->
    <div class="panel-section">
      <div class="panel-label">Impresión y Etiquetas</div>
      <div style="font-size:9px;color:var(--texto-dim);font-weight:600;letter-spacing:0.08em;text-transform:uppercase;margin-bottom:5px;">Tamaño de números de sección</div>
      <div class="btn-escala" id="grupo-escala-labels">
        <button type="button" class="activo" onclick="setEscalaLabels(1)" id="btn-escala-1">1x</button>
        <button type="button" onclick="setEscalaLabels(2)" id="btn-escala-2">2x</button>
        <button type="button" onclick="setEscalaLabels(3)" id="btn-escala-3">3x</button>
      </div>

      <div style="font-size:9px;color:var(--texto-dim);font-weight:600;letter-spacing:0.08em;text-transform:uppercase;margin:10px 0 5px;">Capa base</div>
      <select id="select-capa-base" onchange="cambiarCapaBase(this.value)">
        <option value="claro">Claro</option>
        <option value="osm">OpenStreetMap (calles)</option>
        <option value="satelite">Satélite (Esri)</option>
      </select>

      <button type="button" id="btn-imprimir-mapa" onclick="imprimirMapa()"><i class="fas fa-print"></i> Imprimir mapa</button>
      <div style="font-size:9px;color:var(--texto-dim);line-height:1.4;margin-top:6px;">Para imprimir la hoja de una zona, selecciónala primero en "Pintar Zona".</div>
    </div>

    <div id="info-panel">
      <div class="panel-label">Sección seleccionada</div>
      <div class="info-title" id="info-seccion">—</div>
      <div class="info-row"><span class="info-key">Dist. Federal</span><span class="info-val" id="info-df">—</span></div>
      <div class="info-row"><span class="info-key">Dist. Local</span><span class="info-val" id="info-dl">—</span></div>
      <div class="info-row"><span class="info-key">Municipio</span><span class="info-val" id="info-mun">—</span></div>
      <div class="info-row"><span class="info-key">Entidad</span><span class="info-val" id="info-ent">—</span></div>
      <div class="info-row"><span class="info-key">Tipo</span><span class="info-val" id="info-tipo">—</span></div>
      <button class="btn-ver-detalles" id="btn-ver-detalles" onclick="abrirModalDetalle()" style="display:none;">
        <i class="fas fa-table-list"></i> Ver Detalles de Sección
      </button>
      <div id="db-spinner" style="display:none;text-align:center;padding:14px 0;">
        <i class="fas fa-spinner fa-spin" style="color:var(--verde);font-size:15px;"></i>
        <div style="font-size:9px;color:var(--texto-dim);margin-top:4px;">Cargando datos…</div>
      </div>
      <div id="db-no-data" style="display:none;font-size:11px;color:var(--texto-dim);background:var(--gris-panel);border-radius:5px;padding:8px 10px;text-align:center;margin-top:10px;">
        <i class="fas fa-info-circle"></i> Sin datos registrados
      </div>
      <div id="db-panel" style="display:none;">
        <hr class="db-sep">
        <div class="db-section-label"><i class="fas fa-map-pin"></i> Zona</div>
        <div class="info-row"><span class="info-key">Región</span><span class="info-val" id="db-region">—</span></div>
        <div class="info-row"><span class="info-key">Zona</span><span class="info-val" id="db-zona">—</span></div>
        <div class="db-section-label"><i class="fas fa-users"></i> Lista Nominal</div>
        <div class="info-row"><span class="info-key">Total</span><span class="info-val verde" id="db-nominal">—</span></div>
        <div style="display:flex;gap:6px;margin:4px 0 6px;">
          <div style="flex:1;text-align:center;background:#eff6ff;border-radius:6px;padding:5px 4px;">
            <div style="font-size:9px;color:#3b82f6;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;"><i class="fas fa-mars"></i> Hombres</div>
            <div id="db-lista-hombres" style="font-size:14px;font-weight:800;color:#3b82f6;">—</div>
          </div>
          <div style="flex:1;text-align:center;background:#fdf2f8;border-radius:6px;padding:5px 4px;">
            <div style="font-size:9px;color:#ec4899;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;"><i class="fas fa-venus"></i> Mujeres</div>
            <div id="db-lista-mujeres" style="font-size:14px;font-weight:800;color:#ec4899;">—</div>
          </div>
        </div>
        <div class="edad-chips" id="db-edad-chips"></div>
        <div class="db-section-label"><i class="fas fa-address-book"></i> Contactos</div>
        <div class="info-row"><span class="info-key">Meta</span><span class="info-val" id="db-meta">—</span></div>
        <div class="info-row"><span class="info-key">Conseguidos</span><span class="info-val verde" id="db-contactos">—</span></div>
        <div class="db-progress-bg"><div class="db-progress-fill" id="db-progress-fill" style="width:0%"></div></div>
        <div class="db-progress-meta"><span id="db-pct">0%</span><span id="db-rem">0 restantes</span></div>
        <div class="db-section-label"><i class="fas fa-calendar-check"></i> Resultados</div>
        <div class="info-row"><span class="info-key">Eventos realizados</span><span class="info-val verde" id="db-eventos">—</span></div>
        <div class="info-row"><span class="info-key">Meta de votos</span><span class="info-val verde" id="db-votos">—</span></div>
        <div class="db-section-label"><i class="fas fa-bolt"></i> Activaciones</div>
        <div class="act-mini-grid">
          <div class="act-mini-card"><div class="act-mini-num" id="db-act-total">—</div><div class="act-mini-lbl">Total</div></div>
          <div class="act-mini-card"><div class="act-mini-num" id="db-act-con">—</div><div class="act-mini-lbl">Con Cand.</div></div>
          <div class="act-mini-card"><div class="act-mini-num" id="db-act-sin">—</div><div class="act-mini-lbl">Sin Cand.</div></div>
        </div>

        <div id="db-act-lista-wrap" style="display:none;margin-top:6px;">
          <div id="db-act-lista"></div>
        </div>

        <div id="db-eventos-wrap" style="display:none;margin-top:10px;">
          <div class="db-section-label"><i class="fas fa-calendar-check"></i> Eventos de Agenda</div>
          <div id="db-eventos-lista"></div>
        </div>

        <div class="db-section-label"><i class="fas fa-align-left"></i> Información</div>
        <div class="db-open-field" id="db-nec-wrap" style="display:none;"><div class="db-open-label">Principales Necesidades</div><div class="db-open-text" id="db-necesidades"></div></div>
        <div class="db-open-field" id="db-enf-wrap" style="display:none;"><div class="db-open-label">Enfoque</div><div class="db-open-text" id="db-enfoque"></div></div>
        <div class="db-open-field" id="db-per-wrap" style="display:none;"><div class="db-open-label">Perfil Socioeconómico</div><div class="db-open-text" id="db-perfil"></div></div>

        <button id="db-link" class="db-link-btn" type="button" style="display:none;">
          <i class="fas fa-external-link-alt"></i> Ver detalle completo
        </button>

      </div>
    </div>
  </aside>

  <div id="map"></div>
</div>

<!-- Hoja de impresión: solo visible al imprimir con una zona seleccionada (mismo formato que rol Zona) -->
<div id="hoja-impresion-pie">
  <div class="hip-logo">
    <img src="<?= htmlspecialchars($logoPartidoUrl) ?>" alt="Logo">
  </div>

  <div class="hip-tabla">
    <table>
      <tbody id="hip-tbody"></tbody>
    </table>
  </div>

  <div class="hip-zona">
    <div>
      <div class="hip-zona-label">ZONA :</div>
      <div class="hip-zona-code" id="hip-zona-nombre">—</div>
    </div>
    <div class="hip-zona-label" style="margin-top:6px;">MUNICIPIO:</div>
    <div class="hip-zona-code" style="font-size:12px;" id="hip-municipio">—</div>
    <div style="display:flex;gap:10px;margin-top:6px;">
      <div><span class="hip-zona-label">DISTRITO LOCAL:</span> <b id="hip-dl">—</b></div>
    </div>
    <div style="display:flex;gap:10px;">
      <div><span class="hip-zona-label">DISTRITO FEDERAL:</span> <b id="hip-df">—</b></div>
    </div>
  </div>

  <div class="hip-coord">
    <div class="hip-coord-title">COORDINADOR DE ZONA:</div>
    <div>TEL:</div>
    <div>CURP:</div>
    <div>CORREO:</div>
    <div>INE:</div>
    <div style="border-bottom:none;">OBSERVACIONES:</div>
  </div>

  <div class="hip-total">
    <div class="hip-zona-label">TOTAL L.N.:</div>
    <div class="hip-zona-code" id="hip-total-ln">0</div>
  </div>
</div>

<!-- ── Modal Agregar Evento ── -->
<div class="modal-overlay" id="modal-overlay" onclick="if(event.target===this)cerrarModal()">
  <div class="modal">
    <button class="modal-close" onclick="cerrarModal()"><i class="fas fa-times"></i></button>
    <h2 class="modal-title" id="m-modal-title">Nuevo Evento</h2>
    <p style="font-size:13px;color:#6b7280;margin-bottom:24px;">Registra un evento en la agenda. La sección se toma del mapa.</p>
    <div style="display:flex;flex-direction:column;gap:6px;margin-bottom:18px;">
      <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:#6b7280;">Sección Electoral</label>
      <div id="seccion-badge-wrap"><span class="seccion-badge empty" id="seccion-badge-text"><i class="fa-solid fa-map-marker-alt"></i> Ninguna sección seleccionada</span></div>
      <button type="button" onclick="activarSeleccionSeccion()" style="font-size:11px;color:var(--verde);background:none;border:none;cursor:pointer;padding:0;text-align:left;font-family:inherit;"><i class="fas fa-crosshairs"></i> Haz clic aquí, luego en el mapa para cambiar sección</button>
    </div>
    <div style="display:flex;flex-direction:column;gap:6px;margin-bottom:18px;">
      <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:#6b7280;">Eje *</label>
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;">
        <button class="eje-btn-ag" data-eje="voto"         onclick="seleccionarEjeM('voto')"         type="button">🗳️ Voto / Territorio</button>
        <button class="eje-btn-ag" data-eje="comunicacion" onclick="seleccionarEjeM('comunicacion')" type="button">📣 Comunicación</button>
        <button class="eje-btn-ag" data-eje="organizacion" onclick="seleccionarEjeM('organizacion')" type="button">⚙️ Organización</button>
      </div>
    </div>
    <div style="display:flex;flex-direction:column;gap:6px;margin-bottom:18px;">
      <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:#6b7280;">Subtipo</label>
      <select id="m-subtipo" style="width:100%;padding:11px 14px;border-radius:10px;border:1.5px solid #e5e7eb;background:#f9fafb;font-size:14px;font-weight:500;color:#111827;outline:none;font-family:inherit;appearance:none;"><option value="">— Selecciona un eje primero —</option></select>
    </div>
    <div style="display:flex;flex-direction:column;gap:6px;margin-bottom:18px;">
      <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:#6b7280;">Título *</label>
      <input type="text" id="m-titulo" style="width:100%;padding:11px 14px;border-radius:10px;border:1.5px solid #e5e7eb;background:#f9fafb;font-size:14px;font-weight:500;color:#111827;outline:none;font-family:inherit;" placeholder="Ej. Recorrido Colonia Pitic">
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-bottom:18px;">
      <div style="display:flex;flex-direction:column;gap:6px;"><label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:#6b7280;">Fecha *</label><input type="date" id="m-fecha" style="width:100%;padding:11px 14px;border-radius:10px;border:1.5px solid #e5e7eb;background:#f9fafb;font-size:14px;font-weight:500;color:#111827;outline:none;font-family:inherit;"></div>
      <div style="display:flex;flex-direction:column;gap:6px;"><label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:#6b7280;">Estado</label><input type="text" id="m-estado" readonly placeholder="Auto del mapa" style="width:100%;padding:11px 14px;border-radius:10px;border:1.5px solid #e5e7eb;background:#f3f4f6;font-size:14px;font-weight:500;color:#6b7280;outline:none;font-family:inherit;cursor:not-allowed;"></div>
      <div style="display:flex;flex-direction:column;gap:6px;"><label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:#6b7280;">Municipio</label><input type="text" id="m-municipio" readonly placeholder="Auto del mapa" style="width:100%;padding:11px 14px;border-radius:10px;border:1.5px solid #e5e7eb;background:#f3f4f6;font-size:14px;font-weight:500;color:#6b7280;outline:none;font-family:inherit;cursor:not-allowed;"></div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:18px;">
      <div style="display:flex;flex-direction:column;gap:6px;"><label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:#6b7280;">Inicio</label><select id="m-inicio" onchange="calcHorasM()" style="width:100%;padding:11px 14px;border-radius:10px;border:1.5px solid #e5e7eb;background:#f9fafb;font-size:14px;font-weight:500;color:#111827;outline:none;font-family:inherit;appearance:none;"><option value="">-- : --</option></select></div>
      <div style="display:flex;flex-direction:column;gap:6px;"><label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:#6b7280;">Fin</label><select id="m-fin" onchange="calcHorasM()" style="width:100%;padding:11px 14px;border-radius:10px;border:1.5px solid #e5e7eb;background:#f9fafb;font-size:14px;font-weight:500;color:#111827;outline:none;font-family:inherit;appearance:none;"><option value="">-- : --</option></select><span id="m-hora-error" style="display:none;font-size:11px;font-weight:700;color:#dc2626;margin-top:2px;"><i class="fas fa-exclamation-circle"></i> El fin debe ser posterior al inicio</span></div>
    </div>
    <div style="display:flex;flex-direction:column;gap:6px;margin-bottom:18px;"><label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:#6b7280;">Responsable</label><input type="text" id="m-responsable" style="width:100%;padding:11px 14px;border-radius:10px;border:1.5px solid #e5e7eb;background:#f9fafb;font-size:14px;font-weight:500;color:#111827;outline:none;font-family:inherit;" placeholder="Nombre del responsable"></div>
    <div style="display:flex;flex-direction:column;gap:6px;margin-bottom:24px;"><label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:#6b7280;">Notas</label><textarea id="m-notas" style="width:100%;padding:11px 14px;border-radius:10px;border:1.5px solid #e5e7eb;background:#f9fafb;font-size:14px;font-weight:400;color:#111827;outline:none;font-family:inherit;resize:vertical;min-height:80px;" placeholder="Observaciones…"></textarea></div>
    <div class="modal-footer-ag">
      <button id="m-del-btn" style="visibility:hidden;" class="btn-danger-ag" onclick="eliminarEventoMapa(editandoIdM)"><i class="fas fa-trash-alt"></i> Eliminar</button>
      <div style="display:flex;gap:10px;">
        <button onclick="cerrarModal()" class="btn-cancel-ag">Cancelar</button>
        <button id="m-save-btn" onclick="guardarEvento()" class="btn-save-ag"><i class="fas fa-check"></i> Guardar Evento</button>
      </div>
    </div>
  </div>
</div>

<!-- ── Modal Detalle Evento ── -->
<div class="modal-overlay" id="modal-det" onclick="if(event.target===this)cerrarModalDet()">
  <div class="modal" style="max-width:540px;">
    <button class="modal-close" onclick="cerrarModalDet()"><i class="fas fa-times"></i></button>
    <h2 id="det-titulo" class="modal-title">—</h2>
    <div id="det-eje-badge" class="eje-badge-det"></div>
    <div class="detail-grid-ag">
      <div class="detail-item-ag"><strong>Subtipo</strong><span id="det-subtipo">—</span></div>
      <div class="detail-item-ag"><strong>Fecha</strong><span id="det-fecha">—</span></div>
      <div class="detail-item-ag"><strong>Horario</strong><span id="det-horario">—</span></div>
      <div class="detail-item-ag"><strong>Estado</strong><span id="det-estado">—</span></div>
      <div class="detail-item-ag"><strong>Municipio</strong><span id="det-municipio">—</span></div>
      <div class="detail-item-ag"><strong>Sección</strong><span id="det-seccion">—</span></div>
      <div class="detail-item-ag"><strong>Responsable</strong><span id="det-responsable">—</span></div>
      <div class="detail-item-ag"><strong>Horas</strong><span id="det-horas">—</span></div>
    </div>
    <div style="margin-top:20px;">
      <strong style="font-size:11px;color:var(--texto-dim);text-transform:uppercase;letter-spacing:0.5px;">Notas</strong>
      <div id="det-notas" style="margin-top:8px;font-size:14px;color:#555;line-height:1.6;background:var(--gris-panel);padding:15px;border-radius:10px;border-left:4px solid var(--verde);">—</div>
    </div>
    <div class="modal-footer-ag">
      <button class="btn-cancel-ag" onclick="cerrarModalDet()">Cerrar</button>
    </div>
  </div>
</div>

<!-- ── Modal Detalle Activación ── -->
<div class="modal-overlay" id="modal-det-act" onclick="if(event.target===this)cerrarModalDetAct()">
  <div class="modal" style="max-width:540px;">
    <button class="modal-close" onclick="cerrarModalDetAct()"><i class="fas fa-times"></i></button>
    <h2 id="det-act-titulo" class="modal-title">—</h2>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:5px;">
      <span id="det-act-estatus-badge" class="eje-badge-det"></span>
      <span id="det-act-tipo-badge" class="eje-badge-det"></span>
    </div>
    <div class="detail-grid-ag">
      <div class="detail-item-ag"><strong>Fecha</strong><span id="det-act-fecha">—</span></div>
      <div class="detail-item-ag"><strong>Candidato</strong><span id="det-act-candidato">—</span></div>
      <div class="detail-item-ag"><strong>Coordinador</strong><span id="det-act-coordinador">—</span></div>
      <div class="detail-item-ag"><strong>Municipio</strong><span id="det-act-municipio">—</span></div>
      <div class="detail-item-ag"><strong>Sección</strong><span id="det-act-seccion">—</span></div>
      <div class="detail-item-ag"><strong>Estado</strong><span id="det-act-estado">—</span></div>
    </div>
    <div style="margin-top:20px;">
      <strong style="font-size:11px;color:var(--texto-dim);text-transform:uppercase;letter-spacing:0.5px;">Notas</strong>
      <div id="det-act-notas" style="margin-top:8px;font-size:14px;color:#555;line-height:1.6;background:var(--gris-panel);padding:15px;border-radius:10px;border-left:4px solid #f59e0b;">—</div>
    </div>
    <div class="modal-footer-ag">
      <button class="btn-cancel-ag" onclick="cerrarModalDetAct()">Cerrar</button>
    </div>
  </div>
</div>

<!-- ── Modal Detalle Sección ── -->
<div class="sec-modal-overlay" id="sec-modal-det" onclick="if(event.target===this)cerrarModalDetalle()">
  <div class="sec-modal-box">
    <button class="sec-modal-close" onclick="cerrarModalDetalle()"><i class="fas fa-times"></i></button>
    <div class="sec-modal-title" id="sdet-titulo">—</div>
    <div class="sec-modal-subtitle" id="sdet-ubicacion">—</div>
    <div id="sdet-spinner" style="text-align:center;padding:30px 0;display:none;"><i class="fas fa-spinner fa-spin" style="font-size:24px;color:#1a7a3c;"></i><div style="font-size:12px;color:#6b7280;margin-top:8px;">Cargando datos…</div></div>
    <div id="sdet-sin-datos" style="display:none;background:#f8f9fa;border-radius:8px;padding:14px;font-size:13px;color:#6b7280;margin-bottom:16px;"><i class="fas fa-info-circle"></i> Sin datos registrados para esta sección</div>
    <div id="sdet-contenido" style="display:none;">
      <div class="sec-section-title"><i class="fas fa-map-marker-alt"></i> Ubicación</div>
      <div class="sec-det-grid">
        <div class="sec-det-item"><label>Sección</label><span id="sdet-seccion">—</span></div>
        <div class="sec-det-item"><label>Municipio</label><span id="sdet-municipio">—</span></div>
        <div class="sec-det-item"><label>Distrito Federal</label><span id="sdet-df">—</span></div>
        <div class="sec-det-item"><label>Distrito Local</label><span id="sdet-dl">—</span></div>
        <div class="sec-det-item"><label>Región Asignada</label><span id="sdet-region">—</span></div>
        <div class="sec-det-item"><label>Zona Asignada</label><span id="sdet-zona">—</span></div>
      </div>
      <div class="sec-section-title"><i class="fas fa-users"></i> Lista Nominal</div>
      <div class="sec-nominal-grid">
        <div class="sec-nominal-card"><div class="sec-nominal-num" id="sdet-padron" style="color:#374151;">—</div><div class="sec-nominal-lbl">Padrón Electoral</div></div>
        <div class="sec-nominal-card"><div class="sec-nominal-num" id="sdet-hombres" style="color:#3b82f6;">—</div><div class="sec-nominal-lbl"><i class="fas fa-mars" style="color:#3b82f6;"></i> Hombres</div></div>
        <div class="sec-nominal-card"><div class="sec-nominal-num" id="sdet-mujeres" style="color:#ec4899;">—</div><div class="sec-nominal-lbl"><i class="fas fa-venus" style="color:#ec4899;"></i> Mujeres</div></div>
      </div>
      <div class="sec-section-title"><i class="fas fa-address-book"></i> Contactos y Avance</div>
      <div class="sec-det-grid" style="margin-bottom:10px;">
        <div class="sec-det-item"><label>Meta de Contactos</label><span id="sdet-meta" style="font-size:20px;font-weight:900;color:#1a1e2a;">—</span></div>
        <div class="sec-det-item"><label>Contactos Conseguidos</label><span id="sdet-contactos" style="font-size:20px;font-weight:900;color:#1a7a3c;">—</span></div>
      </div>
      <div class="sec-modal-progress-bg"><div class="sec-modal-progress-fill" id="sdet-prog-fill" style="width:0%"></div></div>
      <div class="sec-modal-progress-lbl"><span id="sdet-prog-pct">0%</span><span id="sdet-prog-rem">0 restantes</span></div>
      <div class="sec-section-title"><i class="fas fa-calendar-check"></i> Resultados</div>
      <div class="sec-det-grid"><div class="sec-det-item"><label>Eventos Realizados</label><span id="sdet-eventos">—</span></div><div class="sec-det-item"><label>Metas de Votos</label><span id="sdet-votos">—</span></div></div>
      <div class="sec-section-title"><i class="fas fa-bolt"></i> Activaciones Totales</div>
      <div class="sec-act-grid">
        <div class="sec-act-card"><div class="sec-act-num" id="sdet-act-total">—</div><div class="sec-act-lbl">Total</div></div>
        <div class="sec-act-card"><div class="sec-act-num" id="sdet-act-con">—</div><div class="sec-act-lbl">Con Candidato</div></div>
        <div class="sec-act-card"><div class="sec-act-num" id="sdet-act-sin">—</div><div class="sec-act-lbl">Sin Candidato</div></div>
      </div>
      <div id="sdet-act-lista-wrap" style="display:none;margin-top:6px;"><div id="sdet-act-lista"></div></div>
      <div id="sdet-eventos-wrap" style="display:none;margin-top:12px;">
        <div class="sec-section-title"><i class="fas fa-calendar-check"></i> Eventos de Agenda</div>
        <div id="sdet-eventos-lista"></div>
      </div>
      <div id="sdet-info-wrap" style="display:none;">
        <div class="sec-section-title"><i class="fas fa-align-left"></i> Información</div>
        <div class="sec-open-field" id="sdet-nec-wrap" style="display:none;"><div class="sec-open-label">Principales Necesidades</div><div class="sec-open-text" id="sdet-necesidades"></div></div>
        <div class="sec-open-field" id="sdet-enf-wrap" style="display:none;"><div class="sec-open-label">Enfoque</div><div class="sec-open-text" id="sdet-enfoque"></div></div>
        <div class="sec-open-field" id="sdet-per-wrap" style="display:none;"><div class="sec-open-label">Perfil Socioeconómico</div><div class="sec-open-text" id="sdet-perfil"></div></div>
      </div>
    </div>
    <div class="sec-modal-footer"><button class="sec-modal-btn-close" onclick="cerrarModalDetalle()"><i class="fas fa-times"></i> Cerrar</button></div>
  </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// ── Datos desde PHP ──
const ESTADOS_DATA      = <?= $estadosJson ?>;
const SECCIONES_REGION  = <?= $seccionesJson ?>;
const MUNICIPIOS_REGION = <?= json_encode(array_values($municipios_region)) ?>;
const ESTADO_INICIAL    = <?= json_encode($estado_key_inicial) ?>;
const ESTADO_LABEL      = <?= json_encode($user_estado) ?>;
const REGION_NOMBRE     = <?= json_encode($region_nombre) ?>;
const ZONAS_REGION      = <?= $zonasRegionJson ?>;
const SECCIONES_DATOS_IMP = <?= $seccionesDatosImpJson ?>;
const ACTIVACIONES_MAPA = [];

/* ── Modo Vista ── */
const MODO_VISTA        = <?= $modoVista ? 'true' : 'false' ?>;
const VIST_ZONA_ID      = <?= $vistZonaId ?>;
const VIST_ZONA_NOMBRE  = <?= json_encode($vistZonaNombre) ?>;
const VIST_RETURN_URL   = <?= json_encode($vistReturnUrl) ?>;
const SECCIONES_ZONA    = new Set(<?= $seccionesZonaJson ?>);

/* ── Modo Selección ── */
const MODO_SELECCION     = <?= $modoSeleccion ? 'true' : 'false' ?>;
const ZOOM_ENTIDAD       = <?= json_encode($zoomEntidad, JSON_UNESCAPED_UNICODE) ?>;
const ZOOM_TIPO          = <?= json_encode($zoomTipo, JSON_UNESCAPED_UNICODE) ?>;
const SEL_ZONA_ID        = <?= $vistZonaId ?>;
const SEL_RETURN_URL     = <?= json_encode($vistReturnUrl) ?>;
const SEL_SECS_INICIALES = new Set(<?= json_encode(array_values($selSecActualesArr)) ?>);
const SEL_SECS_OCUPADAS  = new Set(<?= json_encode(array_values($selSecOcupadasArr)) ?>);
let   seccionesZonaSet   = new Set(SEL_SECS_INICIALES);

// ── Mapa seccion → lista_nominal para el banner LN ──
const SEC_LN_MAP = {};

// ── Pre-cargar LN desde estructura_cr.php (api=ln_por_region) ──
if (MODO_SELECCION && SEL_RETURN_URL) {
  (async () => {
    try {
      const _base = new URL(SEL_RETURN_URL, window.location.origin).pathname;
      const res  = await fetch(`${_base}?api=ln_por_region`);
      const data = await res.json();
      if (data.ok && data.ln) {
        Object.assign(SEC_LN_MAP, data.ln);
        actualizarBannerSeleccion();
      }
    } catch(e) { console.warn('LN pre-fetch error:', e); }
  })();
}

// ── Fallback sessionStorage (si PHP no recibió secciones) ──
if (MODO_SELECCION && seccionesZonaSet.size === 0) {
  const _storedAct = sessionStorage.getItem('mapa_secciones_actuales') || '';
  const _storedOcu = sessionStorage.getItem('mapa_secciones_ocupadas') || '';
  if (_storedAct) _storedAct.split(',').filter(Boolean).forEach(s => seccionesZonaSet.add(s.trim()));
  if (_storedOcu) _storedOcu.split(',').filter(Boolean).forEach(s => SEL_SECS_OCUPADAS.add(s.trim()));
  sessionStorage.removeItem('mapa_secciones_actuales');
  sessionStorage.removeItem('mapa_secciones_ocupadas');
}

// ── Buscar sección en el mapa ──────────────────────────────────────────
function buscarSeccionMapa() {
    const input = document.getElementById('buscar-seccion-input').value.trim();
    if (!input) return;
    if (!layerSecciones) { mostrarToast('El mapa aún está cargando'); return; }
    let encontrada = false;
    layerSecciones.eachLayer(layer => {
        if (encontrada) return;
        try {
            if (String(layer.feature.properties.seccion) === input) {
                map.fitBounds(layer.getBounds(), { padding: [50, 50], maxZoom: 15 });
                layer.fire('click');
                encontrada = true;
            }
        } catch(_) {}
    });
    if (!encontrada) mostrarToast('Sección ' + input + ' no encontrada en esta región');
}

const MEXICO_BOUNDS = L.latLngBounds(L.latLng(14.5,-118.5), L.latLng(32.7,-86.7));
const map = L.map('map', {
  zoomControl: true, attributionControl: false,
  minZoom: 5, maxZoom: 19,
  maxBounds: MEXICO_BOUNDS, maxBoundsViscosity: 1.0
}).setView([23.6345,-102.5528], 5);

// Pane para la capa región: z-index por debajo del overlayPane (400) donde viven las secciones
map.createPane('regionPane');
map.getPane('regionPane').style.zIndex = 350;
map.getPane('regionPane').style.pointerEvents = 'none';

const capasBase = {
  claro:    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Canvas/World_Light_Gray_Base/MapServer/tile/{z}/{y}/{x}', { maxZoom: 19, maxNativeZoom: 16, attribution: 'Tiles &copy; Esri' }),
  osm:      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }),
  satelite: L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { maxZoom: 19, attribution: 'Esri' })
};
capasBase.claro.addTo(map);

let layerEstadoSilueta = null;
let layerMunicipio     = null;
let layerSecciones     = null;
let layerLabels        = null;
let layerRegion        = null;
let layerDistFed       = null;
let layerDistLoc       = null;
let layerDistLocLabels = null;   // números de distrito local
let layerColonias      = null;
let layerZonaResaltada = null;
let seccionActiva      = null;
let municipioActivo    = null;
let estadoActual       = null;
let seccionDbData      = null;
const capasVisibles = { municipio: true, secciones: MODO_SELECCION, labels: false, region: false, distFed: (ZOOM_ENTIDAD && ZOOM_TIPO === 'distrito_federal'), distLoc: (ZOOM_ENTIDAD && ZOOM_TIPO === 'distrito_local'), colonias: false };

function limpiarCapas() {
  [layerEstadoSilueta, layerMunicipio, layerSecciones, layerLabels, layerRegion,
   layerDistFed, layerDistLoc, layerDistLocLabels, layerColonias].forEach(l => { if(l) map.removeLayer(l); });
  layerEstadoSilueta = layerMunicipio = layerSecciones = layerLabels = layerRegion =
  layerDistFed = layerDistLoc = layerDistLocLabels = layerColonias = null;
  seccionActiva = municipioActivo = null;
  document.getElementById('info-panel').classList.remove('visible');
}

function cargarSilueta(munData) {
  layerEstadoSilueta = L.geoJSON(munData, { style: { color:'#1a7a3c', weight:2, fillColor:'#e8f5ec', fillOpacity:0.25 }, interactive: false }).addTo(map);
  map.fitBounds(layerEstadoSilueta.getBounds(), { padding:[24,24] });
}

function cargarMunicipios(munData) {
  municipioActivo = null;
  layerMunicipio = L.geoJSON(munData, {
    style: { color:'#4a7c5e', weight:1.2, fillColor:'#e8f0eb', fillOpacity:0.30, dashArray:'4,4' },
    onEachFeature: (feature, layer) => {
      const p = feature.properties;
      const nombre = p.nombre_mun||p.NOM_MUN||p.nom_mun||p.NOMBRE||p.nombre||p.municipio||'—';
      layer.on('mouseover', function() { if(municipioActivo!==this) this.setStyle({fillOpacity:0.55,color:'#1a7a3c',weight:2,dashArray:null}); this.bringToFront(); if(layerSecciones&&map.hasLayer(layerSecciones))layerSecciones.bringToFront(); });
      layer.on('mouseout', function() { if(municipioActivo!==this) layerMunicipio.resetStyle(this); });
      layer.on('click', function(e) { if(municipioActivo&&municipioActivo!==this) layerMunicipio.resetStyle(municipioActivo); this.setStyle({fillColor:'#1a7a3c',fillOpacity:0.45,color:'#0f5228',weight:2,dashArray:null}); this.bringToFront(); municipioActivo=this; if(layerSecciones&&map.hasLayer(layerSecciones))layerSecciones.bringToFront(); L.DomEvent.stopPropagation(e); });
      layer.bindTooltip(`<span style="font-family:IBM Plex Sans,sans-serif;font-size:11px;font-weight:600;color:#1a1e2a;">${nombre}</span>`, {sticky:true,direction:'top',offset:[0,-8],opacity:0.95});
    }
  }).addTo(map);
  if (!capasVisibles.municipio) map.removeLayer(layerMunicipio);
  map.fitBounds(layerMunicipio.getBounds(), { padding:[24,24] });
  document.getElementById('stat-municipios').textContent = munData.features?.length || '—';

  // Acercamiento a un municipio específico (viene del listado "Municipios de la región")
  if (ZOOM_ENTIDAD && ZOOM_TIPO === 'municipios') {
    let matched = null;
    layerMunicipio.eachLayer(l => {
      const p = l.feature.properties;
      const nombre = p.nombre_mun||p.NOM_MUN||p.nom_mun||p.NOMBRE||p.nombre||p.municipio||'';
      if (nombre && nombre.trim().toLowerCase() === ZOOM_ENTIDAD.trim().toLowerCase()) matched = l;
    });
    if (matched) {
      municipioActivo = matched;
      matched.setStyle({fillColor:'#1a7a3c',fillOpacity:0.45,color:'#0f5228',weight:2,dashArray:null});
      matched.bringToFront();
      map.fitBounds(matched.getBounds(), { padding:[40,40], maxZoom:14 });
    }
  }
}

function cargarSecciones(secData) {
  if (!secData || !secData.features || secData.features.length === 0) return;

  const secSetRegion = new Set(SECCIONES_REGION.map(String));

  layerSecciones = L.geoJSON(secData, {
    style: feature => {
      const sec = String(feature.properties.seccion ?? '');
      if (MODO_SELECCION) {
        if (seccionesZonaSet.has(sec))
          return { color:'#b71c1c', weight:2, fillColor:'#d32f2f', fillOpacity:0.55 };
        if (SEL_SECS_OCUPADAS.has(sec))
          return { color:'#b71c1c', weight:1, fillColor:'#c62828', fillOpacity:0.30 };
        return { color:'#22a050', weight:0.8, fillColor:'#ffffff', fillOpacity:0.01 };
      }
      if (MODO_VISTA && SECCIONES_ZONA.has(sec))
        return { color:'#1565c0', weight:2.5, fillColor:'#1565c0', fillOpacity:0.35 };
      return { color:'#22a050', weight:0.8, fillColor:'#ffffff', fillOpacity:0.01 };
    },
    onEachFeature: (feature, layer) => {
      const p = feature.properties;
      const sec = String(p.seccion ?? '');

      // ── MODO SELECCIÓN ──────────────────────────────────
      if (MODO_SELECCION) {
        const esOcupada = SEL_SECS_OCUPADAS.has(sec) && !seccionesZonaSet.has(sec);
        if (esOcupada) {
          layer.bindTooltip(
            `<span style="font-family:IBM Plex Sans,sans-serif;font-size:11px;color:#c62828;font-weight:700;">⛔ Sec. ${sec} — ya asignada</span>`,
            { sticky:true, direction:'top', offset:[0,-8], opacity:0.95 }
          );
          return; // no clickeable
        }
        layer.on('click', function(e) {
          L.DomEvent.stopPropagation(e);
          if (seccionesZonaSet.has(sec)) {
            seccionesZonaSet.delete(sec);
            this.setStyle({ fillColor:'#ffffff', fillOpacity:0.01, color:'#22a050', weight:0.8 });
          } else {
            seccionesZonaSet.add(sec);
            this.setStyle({ fillColor:'#d32f2f', fillOpacity:0.55, color:'#b71c1c', weight:2 });
            this.bringToFront();
          }
          actualizarBannerSeleccion();
        });
        layer.on('mouseover', function() {
          if (!seccionesZonaSet.has(sec))
            this.setStyle({ fillColor:'#1a7a3c', fillOpacity:0.20, weight:2 });
        });
        layer.on('mouseout', function() {
          if (!seccionesZonaSet.has(sec))
            this.setStyle({ fillColor:'#ffffff', fillOpacity:0.01, color:'#22a050', weight:0.8 });
        });
        layer.bindTooltip(
          `<span style="font-family:IBM Plex Sans,sans-serif;font-size:11px;font-weight:700;">Sec. ${sec}${seccionesZonaSet.has(sec)?' <span style="color:#d32f2f;">● Asignada</span>':''}</span>`,
          { sticky:true, direction:'top', offset:[0,-8], opacity:0.95 }
        );
        return;
      }

      // ── MODO NORMAL ──────────────────────────────────────
      layer.on('click', function(e) {
        if (seleccionandoSeccion) return;
        if (seccionActiva) layerSecciones.resetStyle(seccionActiva);
        this.setStyle({ fillColor:'#1a7a3c', fillOpacity:0.45, color:'#0f5228', weight:2 });
        this.bringToFront();
        seccionActiva = this;

        // ── Actualizar panel lateral ──
        document.getElementById('info-panel').classList.add('visible');
        document.getElementById('info-seccion').textContent = `SEC. ${p.seccion}`;
        document.getElementById('info-df').textContent   = `Distrito ${p.distrito_f ?? p.distrito_federal ?? '—'}`;
        document.getElementById('info-dl').textContent   = `Distrito ${p.distrito_l ?? p.distrito_local  ?? '—'}`;
        document.getElementById('info-mun').textContent  = p.nombre_mun || `Mun. ${p.municipio}`;
        document.getElementById('info-ent').textContent  = p.nombre_ent || `Ent. ${p.entidad}`;
        document.getElementById('info-tipo').textContent = p.tipo==1?'Urbana':p.tipo==2?'Rural':`Tipo ${p.tipo}`;

        seccionDbData = {
          seccion: p.seccion, nombre_seccion: p.seccion,
          estado: ESTADOS_DATA[estadoActual]?.label || ESTADO_LABEL,
          municipio: p.nombre_mun || `Mun. ${p.municipio}`,
          distrito_federal: `Distrito ${p.distrito_f ?? '—'}`,
          distrito_local:   `Distrito ${p.distrito_l ?? '—'}`,
        };

        // ── Mostrar botón y cargar datos (punto f — igual que rol estatal) ──
        document.getElementById('btn-ver-detalles').style.display = 'flex';
        cargarDatosSeccion(p.seccion);

        L.DomEvent.stopPropagation(e);
      });
    }
  }).addTo(map);
  if (!capasVisibles.secciones) map.removeLayer(layerSecciones);

  // ── Capa "Secciones de la Región" corregida ──
  // Filtra SOLO las secciones que pertenecen a esta región y las pinta en verde destacado
  const featuresRegion = secData.features.filter(f => secSetRegion.has(String(f.properties.seccion ?? '')));
  if (featuresRegion.length > 0) {
    layerRegion = L.geoJSON({ type:'FeatureCollection', features: featuresRegion }, {
      style: { color:'#1a7a3c', weight:2, fillColor:'#1a7a3c', fillOpacity:0.18 },
      pane: 'regionPane',
      interactive: false
    });
    if (capasVisibles.region) { layerRegion.addTo(map); layerSecciones.bringToFront(); }
  }

  // ── Labels numéricos ──
  layerLabels = L.layerGroup();
  secData.features.forEach(feature => {
    if (!feature.geometry) return;
    let latlng;
    try { latlng = L.geoJSON(feature).getBounds().getCenter(); } catch(e) { return; }
    layerLabels.addLayer(L.marker(latlng, {
      icon: L.divIcon({ className:'lbl-seccion', html: feature.properties.seccion, iconSize:null, iconAnchor:[10,7] }),
      interactive: false
    }));
  });
  if (capasVisibles.labels) layerLabels.addTo(map);

  document.getElementById('stat-secciones').textContent = SECCIONES_REGION.length || secData.features.length;

  // Asegurar que secciones quede sobre municipios para recibir clicks
  if (layerMunicipio && map.hasLayer(layerSecciones)) layerSecciones.bringToFront();

  // Modo Vista: auto-zoom a secciones de la zona
  if (MODO_VISTA && SECCIONES_ZONA.size > 0) {
    const bounds = [];
    layerSecciones.eachLayer(layer => {
      if (!layer.feature) return;
      const s = String(layer.feature.properties.seccion ?? '');
      if (SECCIONES_ZONA.has(s)) { try { bounds.push(layer.getBounds()); } catch(_) {} }
    });
    if (bounds.length > 0) {
      let b = bounds[0]; bounds.forEach(bb => { b = b.extend(bb); });
      map.fitBounds(b, { padding:[40,40], maxZoom:13 });
    }
  }
  // (auto-zoom modo selección se hace en el setTimeout de 700ms de cargarEstado)
}

function regresarEstructura() {
  if (VIST_RETURN_URL) window.location.href = VIST_RETURN_URL;
  else history.back();
}

// ══════════════════════════════════════════════════════════════
//  MODO SELECCIÓN DE SECCIONES (desde estructura_cr.php)
// ══════════════════════════════════════════════════════════════
function actualizarBannerSeleccion() {
  if (!MODO_SELECCION) return;
  const n     = seccionesZonaSet.size;
  const badge = document.getElementById('sel-badge-cnt');
  const txt   = document.getElementById('sel-counter-txt');
  if (badge) badge.textContent = n + ' seleccionada' + (n !== 1 ? 's' : '');
  if (txt)   txt.textContent   = n > 0
    ? `${n} sección${n!==1?'es':''} marcada${n!==1?'s':''} — clic de nuevo para quitar`
    : 'Haz clic en las secciones del mapa para seleccionarlas';

  let totalLN = 0;
  seccionesZonaSet.forEach(s => { totalLN += SEC_LN_MAP[String(s)] || 0; });
  const badgeLN = document.getElementById('sel-badge-ln');
  const valorLN = document.getElementById('sel-ln-valor');
  if (badgeLN && valorLN) {
    if (totalLN > 0) {
      valorLN.textContent = totalLN.toLocaleString('es-MX');
      badgeLN.style.display = 'flex';
      badgeLN.style.alignItems = 'center';
    } else {
      badgeLN.style.display = 'none';
    }
  }
}

function confirmarSeleccion() {
  if (!SEL_RETURN_URL) { alert('URL de retorno no configurada.'); return; }
  const secs = [...seccionesZonaSet].sort((a,b) => parseInt(a)-parseInt(b)).join(',');
  const url  = new URL(SEL_RETURN_URL, window.location.origin);
  url.searchParams.set('secciones_mapa', secs);
  if (SEL_ZONA_ID) url.searchParams.set('zona_id', SEL_ZONA_ID);
  window.location.href = url.toString();
}

function cancelarSeleccion() {
  if (SEL_RETURN_URL) window.location.href = SEL_RETURN_URL;
  else history.back();
}

// Escala de etiquetas de sección (para legibilidad al hacer zoom/imprimir)
let escalaLabelsActual = 1;
function setEscalaLabels(n) {
    escalaLabelsActual = n;
    document.body.classList.remove('escala-labels-2', 'escala-labels-3');
    if (n === 2) document.body.classList.add('escala-labels-2');
    if (n === 3) document.body.classList.add('escala-labels-3');
    [1, 2, 3].forEach(v => {
        const btn = document.getElementById(`btn-escala-${v}`);
        if (btn) btn.classList.toggle('activo', v === n);
    });
}

// Cambio de capa base (Claro / OpenStreetMap / Satélite Esri)
function cambiarCapaBase(nombre) {
    if (!capasBase[nombre]) return;
    Object.values(capasBase).forEach(capa => map.removeLayer(capa));
    capasBase[nombre].addTo(map);
}

// ══════════════════════════════════════════════════════════════
//  IMPRESIÓN — mismo resultado que el rol Zona:
//  mapa encuadrado en la zona + números de sección + hoja de datos
// ══════════════════════════════════════════════════════════════
let zonaImpresionActual   = null;   // zona que se está imprimiendo
let layerLabelsImpresion  = null;   // números solo de las secciones de la zona
let layerZonaImpresion    = null;   // resaltado temporal si la zona no está pintada
let labelsGlobalesOcultas = false;

// Zona a imprimir: la elegida en "Pintar Zona"; en modo vista, la zona que se está viendo
function zonaParaImpresion() {
    const sel = document.getElementById('sel-zona');
    let id = sel && sel.value ? sel.value : '';
    if (!id && MODO_VISTA && VIST_ZONA_ID) id = String(VIST_ZONA_ID);
    if (!id) return null;
    const zona = ZONAS_REGION.find(z => String(z.id) === String(id));
    if (zona) return zona;
    // Modo vista con zona que no viene en ZONAS_REGION: usar lo que mandó PHP
    if (MODO_VISTA && SECCIONES_ZONA.size > 0) {
        return { id: VIST_ZONA_ID, nombre: VIST_ZONA_NOMBRE || 'Zona', secciones: [...SECCIONES_ZONA] };
    }
    return null;
}

function seccionesSetDeZona(zona) {
    return new Set((zona?.secciones || []).map(s => String(s).trim()).filter(Boolean));
}

// Límites a encuadrar: la zona (si hay) → secciones de la región → secciones cargadas
function obtenerBoundsRegionalRA() {
    if (zonaImpresionActual && layerSecciones) {
        const secSet = seccionesSetDeZona(zonaImpresionActual);
        const b = L.latLngBounds();
        layerSecciones.eachLayer(layer => {
            if (!layer.feature) return;
            if (!secSet.has(String(layer.feature.properties.seccion ?? ''))) return;
            try { b.extend(layer.getBounds()); } catch (_) {}
        });
        if (b.isValid()) return b;
    }
    if (layerRegion) {
        try { const b = layerRegion.getBounds(); if (b.isValid()) return b; } catch (e) {}
    }
    if (layerSecciones) {
        try { const b = layerSecciones.getBounds(); if (b.isValid()) return b; } catch (e) {}
    }
    return null;
}

// Redimensiona y centra el mapa; se llama otra vez justo en "beforeprint"
// (cuando el navegador ya aplicó @media print) para que el encuadre sea
// siempre el mismo sin importar cómo esté panneado en pantalla.
function centrarYAjustarParaImpresionRA() {
    map.invalidateSize();
    const bounds = obtenerBoundsRegionalRA();
    if (bounds) map.fitBounds(bounds, { padding: [20, 20], animate: false });
}

// Llena la hoja de datos (tabla SEC/L.N., zona, municipio, distritos, total)
function construirHojaImpresion(zona) {
    const numSort = arr => [...arr].sort((a, b) => (parseInt(a, 10) || 0) - (parseInt(b, 10) || 0));
    const fmt = n => (Number(n) || 0).toLocaleString('en-US');
    const secs = numSort(seccionesSetDeZona(zona));

    const municipios = new Set(), dls = new Set(), dfs = new Set();
    let totalLN = 0;
    const entradas = secs.map(s => {
        const d = SECCIONES_DATOS_IMP[s] || {};
        const ln = Number(d.ln) || 0;
        totalLN += ln;
        if (d.mun) municipios.add(String(d.mun).trim().toUpperCase());
        if (d.dl)  dls.add(String(d.dl));
        if (d.df)  dfs.add(String(d.df));
        return [s, ln];
    });

    const tbody = document.getElementById('hip-tbody');
    tbody.innerHTML = '';
    const hdr = document.createElement('tr');
    hdr.innerHTML = '<th>SEC</th><th>L.N.</th><th>SEC</th><th>L.N.</th>';
    tbody.appendChild(hdr);
    const mitad = Math.ceil(entradas.length / 2);
    for (let i = 0; i < mitad; i++) {
        const izq = entradas[i], der = entradas[i + mitad];
        const tr = document.createElement('tr');
        [izq ? izq[0] : '', izq ? fmt(izq[1]) : '', der ? der[0] : '', der ? fmt(der[1]) : ''].forEach(v => {
            const td = document.createElement('td'); td.textContent = v; tr.appendChild(td);
        });
        tbody.appendChild(tr);
    }

    document.getElementById('hip-zona-nombre').textContent = zona.nombre || '—';
    document.getElementById('hip-municipio').textContent   = [...municipios].join(' / ') || '—';
    document.getElementById('hip-dl').textContent          = numSort(dls).join(', ') || '—';
    document.getElementById('hip-df').textContent          = numSort(dfs).join(', ') || '—';
    document.getElementById('hip-total-ln').textContent    = fmt(totalLN);
}

// Números de sección solo de la zona (como en el rol Zona) y resaltado si hace falta
function prepararCapasImpresion(zona) {
    const secSet = seccionesSetDeZona(zona);
    const feats = [];
    layerLabelsImpresion = L.layerGroup();
    layerSecciones.eachLayer(layer => {
        if (!layer.feature) return;
        const sec = String(layer.feature.properties.seccion ?? '');
        if (!secSet.has(sec)) return;
        feats.push(layer.feature);
        try {
            layerLabelsImpresion.addLayer(L.marker(layer.getBounds().getCenter(), {
                icon: L.divIcon({ className: 'lbl-seccion', html: sec, iconSize: null, iconAnchor: [10, 7] }),
                interactive: false
            }));
        } catch (_) {}
    });

    // Ocultar etiquetas de todo el estado para que no se amontonen a 3x
    if (layerLabels && map.hasLayer(layerLabels)) { map.removeLayer(layerLabels); labelsGlobalesOcultas = true; }

    // Si la zona no está pintada con "Pintar Zona" (p.ej. modo vista con capa apagada), resaltarla
    if (!layerZonaResaltada && feats.length) {
        layerZonaImpresion = L.geoJSON({ type: 'FeatureCollection', features: feats }, {
            style: { color: '#1a7a3c', weight: 1.5, fillColor: '#22a050', fillOpacity: 0.18 },
            interactive: false
        }).addTo(map);
    }
    layerLabelsImpresion.addTo(map);
}

function limpiarCapasImpresion() {
    if (layerLabelsImpresion) { map.removeLayer(layerLabelsImpresion); layerLabelsImpresion = null; }
    if (layerZonaImpresion)   { map.removeLayer(layerZonaImpresion);   layerZonaImpresion   = null; }
    if (labelsGlobalesOcultas && layerLabels && capasVisibles.labels) map.addLayer(layerLabels);
    labelsGlobalesOcultas = false;
}

// Imprimir mapa: agranda etiquetas, cambia a capa con detalle de calles,
// centra la zona seleccionada, arma la hoja de datos y abre el diálogo
function imprimirMapa() {
    zonaImpresionActual = zonaParaImpresion();
    if (zonaImpresionActual && !layerSecciones) {
        zonaImpresionActual = null;
        mostrarToast('El mapa aún está cargando las secciones, intenta en un momento');
        return;
    }

    const escalaPrevia = escalaLabelsActual;
    setEscalaLabels(3);
    const selectBase = document.getElementById('select-capa-base');
    const basePrevia = selectBase ? selectBase.value : 'claro';
    if (selectBase) selectBase.value = 'osm';
    cambiarCapaBase('osm');

    if (zonaImpresionActual) {
        construirHojaImpresion(zonaImpresionActual);
        prepararCapasImpresion(zonaImpresionActual);
        document.body.classList.add('imprimir-con-pie');
    } else {
        document.body.classList.remove('imprimir-con-pie');
        mostrarToast('Sin zona seleccionada: se imprime toda la región');
    }

    window.onbeforeprint = centrarYAjustarParaImpresionRA;
    window.onafterprint = () => {
        setEscalaLabels(escalaPrevia);
        limpiarCapasImpresion();
        document.body.classList.remove('imprimir-con-pie');
        zonaImpresionActual = null;
        if (selectBase) selectBase.value = basePrevia;
        cambiarCapaBase(basePrevia);
        window.onbeforeprint = null;
        window.onafterprint = null;
        setTimeout(() => map.invalidateSize(), 100);
    };

    // Dar tiempo a que carguen los tiles de OSM antes de abrir el diálogo
    setTimeout(() => {
        centrarYAjustarParaImpresionRA();
        window.print();
    }, 400);
}

function toggleCapa(nombre) {
  capasVisibles[nombre] = !capasVisibles[nombre];
  const tog = document.getElementById(`toggle-${nombre}`);
  if (tog) tog.classList.toggle('on', capasVisibles[nombre]);
  const capaMap = {
    municipio: layerMunicipio, secciones: layerSecciones, labels: layerLabels,
    region: layerRegion, distFed: layerDistFed, distLoc: layerDistLoc, colonias: layerColonias,
  };
  const capa = capaMap[nombre];
  if (!capa) return;
  if (capasVisibles[nombre]) {
    map.addLayer(capa);
    if (nombre === 'secciones') capa.bringToFront();
    if (nombre === 'region' && layerSecciones && map.hasLayer(layerSecciones)) layerSecciones.bringToFront();
  } else {
    map.removeLayer(capa);
  }

  // La silueta del estado se dibuja con el mismo GeoJSON de municipios:
  // apagar/encender junto con la capa municipio para que no queden polígonos visibles
  if (nombre === 'municipio' && layerEstadoSilueta) {
    if (capasVisibles[nombre]) {
      map.addLayer(layerEstadoSilueta);
      if (layerEstadoSilueta.bringToBack) layerEstadoSilueta.bringToBack();
    } else {
      map.removeLayer(layerEstadoSilueta);
    }
  }

  // Números de distrito local: misma capa que el perímetro
  if (nombre === 'distLoc' && layerDistLocLabels) {
    capasVisibles[nombre] ? map.addLayer(layerDistLocLabels) : map.removeLayer(layerDistLocLabels);
  }
}

// ── Cargar estado automáticamente al inicio ──
function cargarEstado(key) {
  limpiarCapas();
  limpiarZona();

  // Ocultar todas las filas de capa y resetear a solo municipio ON
  ['row-municipio','row-secciones','row-labels','row-region','row-distfed','row-distloc','row-colonias'].forEach(id => {
    const el = document.getElementById(id); if (el) el.style.display = 'none';
  });
  ['municipio','secciones','labels','region','distFed','distLoc','colonias'].forEach(n => {
    capasVisibles[n] = (n === 'municipio');
    const tog = document.getElementById(`toggle-${n}`);
    if (tog) tog.classList.toggle('on', capasVisibles[n]);
  });
  document.getElementById('capas-placeholder').style.display = 'block';

  if (!key || !ESTADOS_DATA[key]) return;
  estadoActual = key;
  const cfg = ESTADOS_DATA[key];

  document.getElementById('label-municipio').textContent = `Municipio — ${cfg.label}`;
  document.getElementById('label-secciones').textContent = `Secciones — ${cfg.label}`;
  if (document.getElementById('label-distfed'))
    document.getElementById('label-distfed').textContent  = `Distritos Federales — ${cfg.label}`;
  if (document.getElementById('label-distloc'))
    document.getElementById('label-distloc').textContent  = `Distritos Locales — ${cfg.label}`;
  if (document.getElementById('label-colonias'))
    document.getElementById('label-colonias').textContent = `Colonias — ${cfg.label}`;

  document.getElementById('capas-placeholder').style.display = 'none';
  document.getElementById('row-municipio').style.display = 'flex';

  const munData = cfg.municipio;
  if (!munData) { mostrarToast(`Sin datos geográficos para ${cfg.label}`); return; }

  if (layerEstadoSilueta) { map.removeLayer(layerEstadoSilueta); layerEstadoSilueta = null; }
  cargarSilueta(munData);
  setTimeout(() => {
    if (layerEstadoSilueta) { map.removeLayer(layerEstadoSilueta); layerEstadoSilueta = null; }
    cargarMunicipios(munData);
    // Si secciones ya cargó antes que municipios, restaurar orden
    if (layerSecciones && map.hasLayer(layerSecciones)) layerSecciones.bringToFront();
  }, 600);

  // ── Secciones + capa región corregida ──
  if (cfg.seccionUrl) {
    fetch(cfg.seccionUrl)
      .then(r => { if (!r.ok) throw new Error(`HTTP ${r.status}`); return r.json(); })
      .then(secData => {
        cargarSecciones(secData);
        // Garantizar orden sobre municipios + auto-zoom modo selección
        setTimeout(() => {
          if(layerSecciones&&map.hasLayer(layerSecciones))layerSecciones.bringToFront();
          // Auto-zoom modo selección: aquí layerSecciones ya está completamente renderizado
          if (MODO_SELECCION && seccionesZonaSet.size > 0) {
            const bounds = [];
            layerSecciones.eachLayer(layer => {
              if (!layer.feature) return;
              const s = String(layer.feature.properties.seccion ?? '');
              if (seccionesZonaSet.has(s)) { try { bounds.push(layer.getBounds()); } catch(_) {} }
            });
            if (bounds.length > 0) {
              let b = bounds[0]; bounds.forEach(bb => { b = b.extend(bb); });
              map.fitBounds(b, { padding:[40,40], maxZoom:13 });
            }
            actualizarBannerSeleccion();
          }
        }, 700);
        document.getElementById('row-secciones').style.display = 'flex';
        document.getElementById('row-labels').style.display    = 'flex';
        document.getElementById('row-region').style.display    = 'flex';
        setTimeout(() => {
          layerEventos.clearLayers();
          eventosData.forEach(ev => colocarMarkerEvento(ev));
          actualizarBadges();
          // FIX: mismo problema de orden — layerSecciones no existía cuando
          // corrió el cargarYPintarActivaciones() inicial en DOMContentLoaded,
          // así que las activaciones se posicionaban con el respaldo de
          // lat/lng en vez del polígono real de la sección. Se repinta aquí.
          repintarActivaciones();
          // El contador de la leyenda ("Activaciones N") se había quedado
          // con el valor de ANTES de este repintado (0 si esta era la
          // primera vez que se ubicaban bien) porque actualizarBadges()
          // solo se llamaba antes del repintado, no después.
          actualizarBadges();
        }, 400);
      })
      .catch(err => console.warn('GeoJSON secciones error:', err));
  }

  // ── Distritos Federales ──
  if (cfg.distFedUrl) {
    document.getElementById('row-distfed').style.display = 'flex';
    fetch(cfg.distFedUrl)
      .then(r => { if (!r.ok) throw new Error(`HTTP ${r.status}`); return r.json(); })
      .then(data => {
        layerDistFed = L.geoJSON(data, {
          style: { color:'#1565c0', weight:1.5, fillColor:'#e3f2fd', fillOpacity:0.0, dashArray:'6,3' },
          onEachFeature: (feature, layer) => {
            const p = feature.properties;
            const num = p.distrito_federal || p.distrito || p.DISTRITO || p.numero || p.id || '—';
            layer.bindTooltip(`<span style="font-family:IBM Plex Sans,sans-serif;font-size:11px;font-weight:700;color:#1565c0;">Dist. Federal ${num}</span>`,
              { sticky:true, direction:'top', offset:[0,-8], opacity:0.95 });
          }
        });
        if (capasVisibles.distFed) layerDistFed.addTo(map);

        // Acercamiento a un distrito federal específico
        if (ZOOM_ENTIDAD && ZOOM_TIPO === 'distrito_federal') {
          let matched = null;
          layerDistFed.eachLayer(l => {
            const p = l.feature.properties;
            const num = String(p.distrito_federal || p.distrito || p.DISTRITO || p.numero || p.id || '');
            if (num && num.trim() === ZOOM_ENTIDAD.trim()) matched = l;
          });
          if (matched) {
            const tog = document.getElementById('toggle-distFed');
            if (tog) tog.classList.add('on');
            matched.setStyle({ fillOpacity:0.30, weight:3 });
            matched.bringToFront();
            map.fitBounds(matched.getBounds(), { padding:[40,40], maxZoom:13 });
          }
        }
      })
      .catch(() => { document.getElementById('row-distfed').style.display = 'none'; });
  }

  // ── Distritos Locales ──
  if (cfg.distLocUrl) {
    document.getElementById('row-distloc').style.display = 'flex';
    fetch(cfg.distLocUrl)
      .then(r => { if (!r.ok) throw new Error(`HTTP ${r.status}`); return r.json(); })
      .then(data => {
        layerDistLoc = L.geoJSON(data, {
          style: { color:'#6a1b9a', weight:1.5, fillColor:'#f3e5f5', fillOpacity:0.0, dashArray:'4,4' },
          onEachFeature: (feature, layer) => {
            const p = feature.properties;
            const num = p.distrito_local || p.distrito || p.DISTRITO || p.numero || p.id || '—';
            layer.bindTooltip(`<span style="font-family:IBM Plex Sans,sans-serif;font-size:11px;font-weight:700;color:#6a1b9a;">Dist. Local ${num}</span>`,
              { sticky:true, direction:'top', offset:[0,-8], opacity:0.95 });
          }
        });

        // ── Números de distrito local siempre visibles (junto con el perímetro) ──
        layerDistLocLabels = L.layerGroup();
        data.features.forEach(f => {
          if (!f.geometry) return;
          const p = f.properties || {};
          const num = p.distrito_local || p.distrito || p.DISTRITO || p.numero || p.id || '—';
          let center;
          try { center = L.geoJSON(f).getBounds().getCenter(); } catch(e) { return; }
          layerDistLocLabels.addLayer(L.marker(center, {
            icon: L.divIcon({ className: 'lbl-distloc', html: 'DL ' + num, iconSize: null, iconAnchor: [16, 9] }),
            interactive: false
          }));
        });
        if (capasVisibles.distLoc) layerDistLocLabels.addTo(map);
        if (capasVisibles.distLoc) layerDistLoc.addTo(map);

        // Acercamiento a un distrito local específico
        if (ZOOM_ENTIDAD && ZOOM_TIPO === 'distrito_local') {
          let matched = null;
          layerDistLoc.eachLayer(l => {
            const p = l.feature.properties;
            const num = String(p.distrito_local || p.distrito || p.DISTRITO || p.numero || p.id || '');
            if (num && num.trim() === ZOOM_ENTIDAD.trim()) matched = l;
          });
          if (matched) {
            const tog = document.getElementById('toggle-distLoc');
            if (tog) tog.classList.add('on');
            matched.setStyle({ fillOpacity:0.30, weight:3 });
            matched.bringToFront();
            map.fitBounds(matched.getBounds(), { padding:[40,40], maxZoom:13 });
          }
        }
      })
      .catch(() => { document.getElementById('row-distloc').style.display = 'none'; });
  }

  // ── Colonias ──
  if (cfg.coloniaUrl) {
    document.getElementById('row-colonias').style.display = 'flex';
    fetch(cfg.coloniaUrl)
      .then(r => { if (!r.ok) throw new Error(`HTTP ${r.status}`); return r.json(); })
      .then(data => {
        layerColonias = L.geoJSON(data, {
          style: { color:'#00838f', weight:0.8, fillColor:'#e0f7fa', fillOpacity:0.0 },
          onEachFeature: (feature, layer) => {
            const p = feature.properties;
            const nombre = p.nombre || p.NOMBRE || p.colonia || p.nom_colonia || '—';
            layer.bindTooltip(`<span style="font-family:IBM Plex Sans,sans-serif;font-size:11px;font-weight:600;color:#00838f;">${nombre}</span>`,
              { sticky:true, direction:'top', offset:[0,-8], opacity:0.95 });
          }
        });
        if (capasVisibles.colonias) layerColonias.addTo(map);
      })
      .catch(() => {
        document.getElementById('row-colonias').style.display = 'none';
      });
  }
}

map.on('click', () => {
  if (seleccionandoSeccion) return;
  document.getElementById('btn-ver-detalles').style.display = 'none';
  seccionDbData = null;
});

// ── Eventos ──
const AGENDA_API = '/api/agenda/procesar.php';
let eventosData = [];
let capasVisiblesEventos = false;
const layerEventos = L.layerGroup();

// ── Jitter: separa pines que caen en el mismo punto ──────────────
let _eventoOffsets = {};

function jitteredLatLng(lat, lng, offsetMap) {
    const key = parseFloat(lat).toFixed(4) + ',' + parseFloat(lng).toFixed(4);
    const idx  = offsetMap[key] !== undefined ? offsetMap[key] : 0;
    offsetMap[key] = idx + 1;
    if (idx === 0) return [lat, lng];
    const radio  = 0.00028;
    const angulo = ((idx - 1) / 6) * 2 * Math.PI;
    return [parseFloat(lat) + radio * Math.sin(angulo),
            parseFloat(lng) + radio * Math.cos(angulo)];
}
let seleccionandoSeccion = false;
let seccionParaEvento    = null;

async function cargarYPintarEventos() {
  try {
    const res  = await fetch(AGENDA_API + '?action=list');
    const data = await res.json();
    if (!data.ok) return;
    // Solo eventos de este estado que pertenezcan a ESTA región:
    // 1) coincidencia EXACTA por nombre de región (evita cruces entre regiones que comparten
    //    municipio, p.ej. regiones especiales sin zonas como RE1 CM PVEM)
    // 2) sección dentro del catálogo de zonas de esta región (preciso)
    // 3) respaldo por municipio SOLO si el evento no trae región asignada (dato viejo/incompleto)
    eventosData = (data.eventos || []).filter(ev => {
      if (String(ev.estado ?? '').trim().toUpperCase() !== String(ESTADO_LABEL ?? '').trim().toUpperCase()) return false;
      const evRegion = String(ev.region ?? '').trim();
      if (evRegion !== '' && evRegion.toUpperCase() === String(REGION_NOMBRE ?? '').trim().toUpperCase()) return true;
      if (SECCIONES_REGION.includes(String(ev.seccion ?? ''))) return true;
      if (evRegion === '' && MUNICIPIOS_REGION.includes(String(ev.municipio ?? '').trim().toUpperCase())) return true;
      return false;
    });
    repintarEventos();
    actualizarBadges();
  } catch(e) { console.warn('cargarYPintarEventos error:', e); }
}

function repintarEventos() { _eventoOffsets = {}; layerEventos.clearLayers(); eventosData.forEach(ev => colocarMarkerEvento(ev)); }
function actualizarBadges() {
  const pintados = layerEventos.getLayers().length;
  document.getElementById('ev-count-badge').textContent  = pintados;
  document.getElementById('act-count-badge').textContent = layerActivaciones ? layerActivaciones.getLayers().length : 0;

  // Eventos que pertenecen a la región pero no se pudieron ubicar en el mapa
  // (sin sección reconocida en el catálogo geográfico ni lat/lng propias).
  const sinUbicar = eventosData.length - pintados;
  const elSinUbicacion = document.getElementById('ev-sin-ubicacion');
  if (elSinUbicacion) {
    if (sinUbicar > 0) {
      elSinUbicacion.textContent = `(+${sinUbicar} sin ubicación)`;
      elSinUbicacion.style.display = 'inline';
    } else {
      elSinUbicacion.style.display = 'none';
    }
  }
}
// alias para compatibilidad con llamadas anteriores
function actualizarBadge() { actualizarBadges(); }
function toggleCapaEventos() { capasVisiblesEventos = !capasVisiblesEventos; document.getElementById('toggle-eventos').classList.toggle('on', capasVisiblesEventos); capasVisiblesEventos ? map.addLayer(layerEventos) : map.removeLayer(layerEventos); }

// ── Activaciones ──
let _activacionOffsets  = {};
let capasVisiblesActivaciones = false;
const layerActivaciones = L.layerGroup();

async function cargarYPintarActivaciones() {
  try {
    const res  = await fetch('/api/activaciones/procesar.php?action=list');
    const data = await res.json();
    if (!data.ok) return;
    ACTIVACIONES_MAPA.length = 0;
    (data.activaciones || []).forEach(a => {
      // Solo activaciones de este estado que pertenezcan a ESTA región:
      // 1) coincidencia EXACTA por nombre de región (evita cruces entre regiones que comparten
      //    municipio, p.ej. regiones especiales sin zonas como RE1 CM PVEM)
      // 2) sección dentro del catálogo de zonas de esta región (preciso)
      // 3) respaldo por municipio SOLO si la activación no trae región asignada (dato viejo/incompleto)
      if (String(a.estado ?? '').trim().toUpperCase() !== String(ESTADO_LABEL ?? '').trim().toUpperCase()) return;
      const aRegion = String(a.region ?? '').trim();
      const _regionExacta = aRegion !== '' && aRegion.toUpperCase() === String(REGION_NOMBRE ?? '').trim().toUpperCase();
      const _enCatalogo = SECCIONES_REGION.includes(String(a.seccion_electoral ?? ''));
      const _sinRegionYenMunicipio = aRegion === '' && MUNICIPIOS_REGION.includes(String(a.municipio ?? '').trim().toUpperCase());
      if (!_regionExacta && !_enCatalogo && !_sinRegionYenMunicipio) return;
      ACTIVACIONES_MAPA.push(a);
    });
    repintarActivaciones();
    actualizarBadges();
  } catch(e) { console.warn('cargarYPintarActivaciones error:', e); }
}

function repintarActivaciones() {
  _activacionOffsets = {};
  layerActivaciones.clearLayers();
  ACTIVACIONES_MAPA.forEach(act => colocarMarkerActivacion(act));
}

function colocarMarkerActivacion(act) {
  let latlng = null;
  // 1. Prioridad: centroide del polígono real de la sección (igual que en
  // Estatal), para que el marcador no dependa de un clic impreciso en el
  // mapa del formulario.
  if (act.seccion_electoral && layerSecciones) {
    layerSecciones.eachLayer(layer => {
      if (latlng) return;
      try {
        const p = layer.feature?.properties;
        if (p && String(p.seccion) === String(act.seccion_electoral)) latlng = layer.getBounds().getCenter();
      } catch(_) {}
    });
  }
  // 2. Respaldo: coordenadas guardadas. Nota: action=list devuelve las
  // columnas tal cual (latitud/longitud), no lat/lng — se normalizan aquí.
  if (!latlng) {
    const _latRaw = act.lat ?? act.latitud;
    const _lngRaw = act.lng ?? act.longitud;
    if (_latRaw && _lngRaw && parseFloat(_latRaw) !== 0) {
      latlng = [parseFloat(_latRaw), parseFloat(_lngRaw)];
    }
  }
  if (!latlng) return;
  const _lat = latlng.lat !== undefined ? latlng.lat : latlng[0];
  const _lng = latlng.lng !== undefined ? latlng.lng : latlng[1];
  const jit  = jitteredLatLng(_lat, _lng, _activacionOffsets);
  const iconHtml = `<div style="display:flex;flex-direction:column;align-items:center;">
    <div style="width:22px;height:22px;background:#f59e0b;border-radius:3px;transform:rotate(45deg);border:2px solid white;box-shadow:0 2px 6px rgba(0,0,0,0.25);"></div>
    <span style="font-family:'IBM Plex Mono',monospace;font-size:8px;font-weight:600;color:#92400e;background:rgba(255,255,255,0.85);padding:1px 4px;border-radius:3px;margin-top:4px;white-space:nowrap;">SEC.${act.seccion_electoral||'?'}</span>
  </div>`;
  const marker = L.marker(jit, { icon: L.divIcon({ className:'evento-marker', html:iconHtml, iconSize:[40,44], iconAnchor:[11,38] }), interactive:true });
  const popupHtml = `<div style="font-family:IBM Plex Sans,sans-serif;min-width:160px;">
    <b style="font-size:13px;display:block;margin-bottom:4px;">${act.titulo||'—'}</b>
    <span style="font-family:IBM Plex Mono,monospace;font-size:11px;color:#d97706;">Sección ${act.seccion_electoral||'?'} · ${act.fecha_actividad||'—'}</span>
    <br><span style="font-size:11px;color:#666;">${act.tipo||''}</span>
    <br><button onclick="verDetalleActivacion(${JSON.stringify(act).replace(/"/g,'&quot;')})" style="margin-top:8px;padding:5px 10px;background:#d97706;color:white;border:none;border-radius:6px;font-size:11px;font-weight:700;cursor:pointer;font-family:IBM Plex Sans,sans-serif;">Ver detalle</button>
  </div>`;
  marker.bindPopup(popupHtml, { maxWidth:240 });
  layerActivaciones.addLayer(marker);
}

function toggleCapaActivaciones() {
  capasVisiblesActivaciones = !capasVisiblesActivaciones;
  document.getElementById('toggle-activaciones').classList.toggle('on', capasVisiblesActivaciones);
  capasVisiblesActivaciones ? map.addLayer(layerActivaciones) : map.removeLayer(layerActivaciones);
}

// ══════════════════════════════════════════════════════════════
//  SELECTOR DE ZONA (desde lista de zonas de la región)
// ══════════════════════════════════════════════════════════════
async function onZonaChange() {
  const sel    = document.getElementById('sel-zona');
  const zonaId = sel.value;
  limpiarZona();
  if (!zonaId) return;

  const zonaNombre = sel.options[sel.selectedIndex]?.dataset?.nombre
                  || sel.options[sel.selectedIndex]?.text || '';

  // Leer secciones directamente de ZONAS_REGION (sin fetch externo)
  const zonaData  = ZONAS_REGION.find(z => String(z.id) === String(zonaId));
  const secciones = zonaData?.secciones || [];

  if (secciones.length === 0) {
    mostrarToast('Sin secciones registradas para esta zona');
    return;
  }

  // Si layerSecciones aún no cargó, esperar hasta 8 s
  if (!layerSecciones) {
    mostrarToast('Cargando secciones, espera un momento…');
    let intentos = 0;
    await new Promise(resolve => {
      const t = setInterval(() => {
        intentos++;
        if (layerSecciones || intentos > 16) { clearInterval(t); resolve(); }
      }, 500);
    });
    if (!layerSecciones) { mostrarToast('Las secciones aún no están disponibles'); return; }
  }

  const secSet = new Set(secciones.map(String));
  const bounds = [];
  layerZonaResaltada = L.layerGroup();

  // Auto-activar capa Secciones si está apagada
  if (layerSecciones && !capasVisibles.secciones) {
    capasVisibles.secciones = true;
    map.addLayer(layerSecciones);
    const tog = document.getElementById('toggle-secciones');
    if (tog) tog.classList.add('on');
  }

  if (layerSecciones) {
    layerSecciones.eachLayer(layer => {
      if (!layer.feature) return;
      const sec = String(layer.feature.properties.seccion ?? '');
      if (!secSet.has(sec)) return;
      try {
        bounds.push(layer.getBounds());
        const hl = L.geoJSON(layer.feature, {
          style: { color:'#d32f2f', weight:2, fillColor:'#ef9a9a', fillOpacity:0.55 }
        });
        layerZonaResaltada.addLayer(hl);
      } catch(_) {}
    });
  }

  layerZonaResaltada.addTo(map);
  if (bounds.length > 0) {
    const combined = bounds.reduce((a, b) => a.extend(b), bounds[0]);
    map.fitBounds(combined, { padding:[40,40], maxZoom:13 });
    mostrarToast(`Zona "${zonaNombre}": ${secSet.size} secciones`);
  } else {
    mostrarToast('Activa la capa Secciones para ver el resaltado');
  }
}

function limpiarZona() {
  if (layerZonaResaltada) { map.removeLayer(layerZonaResaltada); layerZonaResaltada = null; }
}

// ══════════════════════════════════════════════════════════════
//  FILTROS DE FECHA (eventos y activaciones de la región)
// ══════════════════════════════════════════════════════════════
function aplicarFiltros() {
  const year  = document.getElementById('filter-year').value;
  const month = document.getElementById('filter-month').value;

  // Filtrar eventos
  _eventoOffsets = {};
  layerEventos.clearLayers();
  eventosData.forEach(ev => {
    if (ev.fecha) {
      const [y, m] = ev.fecha.split('-');
      if (year  && y !== year)  return;
      if (month && m !== month) return;
    }
    colocarMarkerEvento(ev);
  });

  // Filtrar activaciones
  _activacionOffsets = {};
  layerActivaciones.clearLayers();
  ACTIVACIONES_MAPA.forEach(act => {
    if (act.fecha_actividad) {
      const [y, m] = act.fecha_actividad.split('-');
      if (year  && y !== year)  return;
      if (month && m !== month) return;
    }
    colocarMarkerActivacion(act);
  });

  actualizarBadges();

  if (year || month) {
    const mLabel = month ? document.getElementById('filter-month').options[document.getElementById('filter-month').selectedIndex].text : '';
    mostrarToast(`Filtro: ${[year, mLabel].filter(Boolean).join(' / ')}`);
  }
}

function limpiarFiltros() {
  document.getElementById('filter-year').value  = '';
  document.getElementById('filter-month').value = '';
  aplicarFiltros();
}

const SUBTIPOS_M = { voto:['Casa por casa','Evento de campaña','Mitin','Brigada vecinal','Recorrido territorial','Firma de compromisos','Contacto directo'], comunicacion:['Entrevista de radio','Entrevista de TV','Rueda de prensa','Publicación redes sociales','Spot / grabación','Vocería','Evento mediático'], organizacion:['Reunión de estructura','Capacitación','Coordinación interna','Planeación estratégica','Logística','Revisión de presupuesto','Reunión de equipo'] };
const EJE_LABELS_M = { voto:'🗳️ Voto / Territorio', comunicacion:'📣 Comunicación', organizacion:'⚙️ Organización' };
const EJE_COLORS_M = { voto:'#1a7a3c', comunicacion:'#2196f3', organizacion:'#9c27b0' };
let ejeSeleccionadoM = null;
let editandoIdM      = null;

function seleccionarEjeM(eje) {
  ejeSeleccionadoM = eje;
  document.querySelectorAll('.eje-btn-ag').forEach(b => b.className = b.dataset.eje===eje ? `eje-btn-ag sel-${eje}` : 'eje-btn-ag');
  const sel = document.getElementById('m-subtipo');
  sel.innerHTML = '<option value="">Selecciona subtipo…</option>';
  (SUBTIPOS_M[eje]||[]).forEach(s => { const o=document.createElement('option'); o.value=s; o.textContent=s; sel.appendChild(o); });
}

function calcHorasM() {
  const ini=document.getElementById('m-inicio').value, fin=document.getElementById('m-fin').value;
  const errEl=document.getElementById('m-hora-error');
  if(ini&&fin){const[hi,mi]=ini.split(':').map(Number),[hf,mf]=fin.split(':').map(Number); if((hf*60+mf-hi*60-mi)<=0){if(errEl)errEl.style.display='block'; setTimeout(()=>{if(errEl)errEl.style.display='none';},3000);}else{if(errEl)errEl.style.display='none';}}
}

function poblarSelectHorasM(selId, valorActual='') {
  const sel=document.getElementById(selId); if(!sel) return;
  const cur=valorActual||sel.value; sel.innerHTML='<option value="">-- : --</option>';
  for(let h=0;h<24;h++) for(let m of[0,30]){const hh=String(h).padStart(2,'0'),mm=String(m).padStart(2,'0'),val=`${hh}:${mm}`,h12=h%12||12,ampm=h<12?'am':'pm',o=document.createElement('option');o.value=val;o.textContent=`${h12}:${mm} ${ampm}`;if(val===cur)o.selected=true;sel.appendChild(o);}
}

function abrirModal() {
  ejeSeleccionadoM=null;
  document.querySelectorAll('.eje-btn-ag').forEach(b=>b.className='eje-btn-ag');
  document.getElementById('m-subtipo').innerHTML='<option value="">— Selecciona un eje primero —</option>';
  poblarSelectHorasM('m-inicio'); poblarSelectHorasM('m-fin');
  ['m-titulo','m-responsable','m-notas'].forEach(id=>document.getElementById(id).value='');
  const hoy=new Date(); document.getElementById('m-fecha').value=`${hoy.getFullYear()}-${String(hoy.getMonth()+1).padStart(2,'0')}-${String(hoy.getDate()).padStart(2,'0')}`;
  if(seccionActiva){const p=seccionActiva.feature.properties; const latlng=L.geoJSON(seccionActiva.feature).getBounds().getCenter(); seccionParaEvento={num:p.seccion,latlng,estado:ESTADOS_DATA[estadoActual]?.label||ESTADO_LABEL,municipio:p.nombre_mun||p.NOM_MUN||''};}
  actualizarSelectorSeccion();
  document.getElementById('m-del-btn').style.visibility='hidden'; editandoIdM=null;
  document.getElementById('modal-overlay').classList.add('open');
}
function cerrarModal() { editandoIdM=null; document.getElementById('m-del-btn').style.visibility='hidden'; document.getElementById('modal-overlay').classList.remove('open'); seleccionandoSeccion=false; map.getContainer().style.cursor=''; }
function cerrarModalDet() { document.getElementById('modal-det').classList.remove('open'); }
function cerrarModalDetAct() { document.getElementById('modal-det-act').classList.remove('open'); }

const ESTATUS_STYLES_ACT_M = {
  programada:  'background:#fff7ed;color:#d97706;border:1px solid #fde68a;',
  'en-curso':  'background:#eff6ff;color:#1e88e5;border:1px solid #bfdbfe;',
  completada:  'background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0;',
  cancelada:   'background:#fff5f5;color:#e53935;border:1px solid #fecaca;',
};

function verDetalleActivacion(act) {
  document.getElementById('det-act-titulo').textContent = act.titulo || '—';
  const estKey = (act.estatus || 'programada').toLowerCase();
  const estBadge = document.getElementById('det-act-estatus-badge');
  estBadge.textContent = (act.estatus || 'Programada').toString().replace('-', ' ').replace(/\b\w/g, c => c.toUpperCase());
  estBadge.style.cssText = ESTATUS_STYLES_ACT_M[estKey] || ESTATUS_STYLES_ACT_M['programada'];
  const tipoBadge = document.getElementById('det-act-tipo-badge');
  tipoBadge.textContent = act.tipo || '—';
  tipoBadge.style.cssText = 'background:#f59e0b20;color:#d97706;border:1px solid #f59e0b50;';
  document.getElementById('det-act-fecha').textContent = act.fecha_actividad || act.fecha || '—';
  document.getElementById('det-act-candidato').textContent = act.candidato ? ('🙋 ' + act.candidato) : 'Sin candidato';
  document.getElementById('det-act-coordinador').textContent = act.coordinador || act.responsable || '—';
  document.getElementById('det-act-municipio').textContent = act.municipio || '—';
  document.getElementById('det-act-seccion').textContent = (act.seccion_electoral || act.seccion) ? `Sección ${act.seccion_electoral || act.seccion}` : '—';
  document.getElementById('det-act-estado').textContent = act.estado || '—';
  document.getElementById('det-act-notas').textContent = act.notas || 'Sin notas.';
  document.getElementById('modal-det-act').classList.add('open');
}
function activarSeleccionSeccion() { cerrarModal(); seleccionandoSeccion=true; map.getContainer().style.cursor='crosshair'; mostrarToast('Haz clic en una sección del mapa'); }
function actualizarSelectorSeccion() {
  const badge=document.getElementById('seccion-badge-text');
  if(seccionParaEvento&&seccionParaEvento.num){badge.className='seccion-badge';badge.innerHTML=`<i class="fas fa-map-marker-alt"></i> Sección ${seccionParaEvento.num}`;document.getElementById('m-estado').value=seccionParaEvento.estado||'';document.getElementById('m-municipio').value=seccionParaEvento.municipio||'';}
  else{badge.className='seccion-badge empty';badge.innerHTML=`<i class="fa-solid fa-map-marker-alt"></i> Ninguna sección seleccionada`;document.getElementById('m-estado').value='';document.getElementById('m-municipio').value='';}
}

async function guardarEvento() {
  const titulo=document.getElementById('m-titulo').value.trim(), fecha=document.getElementById('m-fecha').value, btn=document.getElementById('m-save-btn');
  if(!titulo){document.getElementById('m-titulo').focus();mostrarToast('El título es obligatorio');return;}
  if(!fecha){mostrarToast('La fecha es obligatoria');return;}
  if(!ejeSeleccionadoM){mostrarToast('Selecciona un eje');return;}
  if(!editandoIdM&&!seccionParaEvento){mostrarToast('Selecciona una sección en el mapa');return;}
  const ini=document.getElementById('m-inicio').value, fin=document.getElementById('m-fin').value;
  let horas=0;
  if(ini&&fin){const[hi,mi]=ini.split(':').map(Number),[hf,mf]=fin.split(':').map(Number);horas=Math.max(0,(hf*60+mf-hi*60-mi)/60);}
  if(!horas)horas=1;
  btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Guardando…'; btn.disabled=true;
  const secNum=seccionParaEvento?seccionParaEvento.num:'';
  const estado=document.getElementById('m-estado').value||(seccionParaEvento?.estado||ESTADO_LABEL);
  const municipio=document.getElementById('m-municipio').value||(seccionParaEvento?.municipio||'');
  const fd=new FormData();
  fd.append('action',editandoIdM?'update':'create'); if(editandoIdM)fd.append('id',editandoIdM);
  fd.append('titulo',titulo);fd.append('fecha',fecha);fd.append('eje',ejeSeleccionadoM);fd.append('subtipo',document.getElementById('m-subtipo').value);
  fd.append('seccion',secNum);fd.append('estado',estado);fd.append('municipio',municipio);fd.append('inicio',ini);fd.append('fin',fin);fd.append('horas',horas);
  fd.append('responsable',document.getElementById('m-responsable').value.trim());
  fd.append('notas',document.getElementById('m-notas').value.trim()||(seccionParaEvento?`Evento registrado desde el mapa. Región ${REGION_NOMBRE}. Sección ${secNum}.`:''));
  if(seccionParaEvento?.latlng){fd.append('lat',seccionParaEvento.latlng.lat??seccionParaEvento.latlng[0]??'');fd.append('lng',seccionParaEvento.latlng.lng??seccionParaEvento.latlng[1]??'');}
  try{const res=await fetch(AGENDA_API,{method:'POST',body:fd});const data=await res.json();if(!data.ok)throw new Error(data.msg||'Error'); await cargarYPintarEventos();cerrarModal();mostrarToast(editandoIdM?'Evento actualizado':`Evento guardado — Sec. ${secNum}`);}
  catch(err){mostrarToast('Error: '+err.message);}
  finally{btn.innerHTML='<i class="fas fa-check"></i> Guardar Evento';btn.disabled=false;}
}

async function eliminarEventoMapa(id) {
  if(!id||!confirm('¿Eliminar este evento?'))return;
  const fd=new FormData(); fd.append('action','delete'); fd.append('id',id);
  try{const res=await fetch(AGENDA_API,{method:'POST',body:fd});const data=await res.json();if(!data.ok)throw new Error(data.msg);editandoIdM=null;await cargarYPintarEventos();cerrarModal();mostrarToast('Evento eliminado');}
  catch(err){mostrarToast('Error: '+err.message);}
}

function verDetalleEvento(ev) {
  document.getElementById('det-titulo').textContent=ev.titulo||'—';
  const b=document.getElementById('det-eje-badge'),ej=ev.eje||'voto';
  b.textContent=EJE_LABELS_M[ej]||ej; b.style.cssText=`background:${EJE_COLORS_M[ej]}20;color:${EJE_COLORS_M[ej]};border-color:${EJE_COLORS_M[ej]}50;`;
  document.getElementById('det-subtipo').textContent=ev.subtipo||'—';document.getElementById('det-fecha').textContent=ev.fecha||'—';
  document.getElementById('det-estado').textContent=ev.estado||'—';document.getElementById('det-municipio').textContent=ev.municipio||'—';
  document.getElementById('det-seccion').textContent=ev.seccion?`Sección ${ev.seccion}`:'—';document.getElementById('det-responsable').textContent=ev.responsable||'—';
  document.getElementById('det-horas').textContent=ev.horas?ev.horas+'h':'—';
  const fmtH=t=>{if(!t)return'—';const[h,m]=t.split(':');const hh=parseInt(h);return`${hh%12||12}:${m} ${hh>=12?'p.m.':'a.m.'}`};
  document.getElementById('det-horario').textContent=(ev.inicio||ev.fin)?`${fmtH(ev.inicio)} – ${fmtH(ev.fin)}`:'—';
  document.getElementById('det-notas').textContent=ev.notas||'Sin notas.';
  document.getElementById('modal-det').classList.add('open');
}

function colocarMarkerEvento(ev) {
  let latlng=null;
  if(ev.lat&&ev.lng&&parseFloat(ev.lat)!==0)latlng=[parseFloat(ev.lat),parseFloat(ev.lng)];
  else if(layerSecciones){layerSecciones.eachLayer(layer=>{if(latlng)return;try{const p=layer.feature?.properties;if(p&&String(p.seccion)===String(ev.seccion))latlng=layer.getBounds().getCenter();}catch(_){}});}
  if(!latlng)return;
  // Normalizar: getCenter() devuelve L.LatLng (.lat/.lng), no un array
  const _lat = latlng.lat !== undefined ? latlng.lat : latlng[0];
  const _lng = latlng.lng !== undefined ? latlng.lng : latlng[1];
  latlng = jitteredLatLng(_lat, _lng, _eventoOffsets);
  const ejeColors={voto:'#1a7a3c',comunicacion:'#2196f3',organizacion:'#9c27b0'};
  const color=ejeColors[ev.eje]||'#1a7a3c';
  const iconHtml=`<div class="evento-pin"><div class="evento-pin-icon" style="background:${color};"><i class="fa-solid fa-location-dot"></i></div><div class="evento-pin-label">SEC.${ev.seccion||'?'}</div></div>`;
  const marker=L.marker(latlng,{icon:L.divIcon({className:'evento-marker',html:iconHtml,iconSize:[40,44],iconAnchor:[13,38]}),interactive:true});
  const fechaStr=ev.fecha?` — ${ev.fecha}`:'',ejeStr=ev.eje?` · ${ev.eje.charAt(0).toUpperCase()+ev.eje.slice(1)}`:'';
  const popupHtml=`<div style="font-family:IBM Plex Sans,sans-serif;min-width:160px;"><b style="font-size:13px;display:block;margin-bottom:4px;">${ev.titulo||'—'}</b><span style="font-family:IBM Plex Mono,monospace;font-size:11px;color:${color};">Sección ${ev.seccion||'?'}${fechaStr}</span><br><span style="font-size:11px;color:#666;">${ejeStr.replace(' · ','')}</span><br><button onclick="verDetalleEvento(${JSON.stringify(ev).replace(/"/g,'&quot;')})" style="margin-top:8px;padding:5px 10px;background:${color};color:white;border:none;border-radius:6px;font-size:11px;font-weight:700;cursor:pointer;font-family:IBM Plex Sans,sans-serif;">Ver detalle</button></div>`;
  marker.bindPopup(popupHtml,{maxWidth:260});
  layerEventos.addLayer(marker);
}

// ── Datos de sección desde BD ──
async function cargarDatosSeccion(numSeccion) {
  document.getElementById('db-panel').style.display='none';document.getElementById('db-no-data').style.display='none';document.getElementById('db-spinner').style.display='block';
  const fmt=n=>(n!=null&&n!==''&&!isNaN(n))?parseInt(n).toLocaleString('es-MX'):'—';
  const pct=(c,m)=>(!m||m==0)?0:Math.min(100,Math.round(c/m*100));
  try{
    const res=await fetch(`?action=get_seccion_detalle&seccion=${encodeURIComponent(numSeccion)}&estado=${encodeURIComponent(ESTADOS_DATA[estadoActual]?.label||ESTADO_LABEL)}`);
    const data=await res.json();
    document.getElementById('db-spinner').style.display='none';
    if(!data.ok||!data.seccion){document.getElementById('db-no-data').style.display='block';return;}
    const s=data.seccion;
    document.getElementById('db-region').textContent=s.region_asignada||'—';document.getElementById('db-zona').textContent=s.zona_asignada||'—';
    document.getElementById('db-nominal').textContent=fmt(s.lista_nominal);
    const edadRows=[['18-24',s.grupo_edad_18_24],['25-34',s.grupo_edad_25_34],['35-44',s.grupo_edad_35_44],['45-54',s.grupo_edad_45_54],['55+',s.grupo_edad_55_mas]].filter(([,v])=>v>0);
    document.getElementById('db-lista-hombres').textContent=fmt(s.lista_hombres);
    document.getElementById('db-lista-mujeres').textContent=fmt(s.lista_mujeres);
    const coloresEdad = {'18-24':'#3498db','25-34':'#2ecc71','35-44':'#f1c40f','45-54':'#e67e22','55+':'#9b59b6'};
    document.getElementById('db-edad-chips').innerHTML=edadRows.map(([l,v])=>{const c=coloresEdad[l]||'#6b7280';return `<span class="edad-chip" style="background:${c}20;color:${c};border:1px solid ${c}40;">${l}: ${fmt(v)}</span>`;}).join('');
    const meta=parseInt(s.meta_contactos)||0,cons=parseInt(s.contactos_conseguidos)||0,p=pct(cons,meta);
    document.getElementById('db-meta').textContent=fmt(meta);document.getElementById('db-contactos').textContent=fmt(cons);
    document.getElementById('db-progress-fill').style.width=p+'%';document.getElementById('db-pct').textContent=p+'%';document.getElementById('db-rem').textContent=fmt(Math.max(0,meta-cons))+' rest.';
    document.getElementById('db-eventos').textContent=fmt(s.eventos_realizados);document.getElementById('db-votos').textContent=fmt(s.metas_votos);
    document.getElementById('db-act-total').textContent=fmt(s.activaciones_totales);document.getElementById('db-act-con').textContent=fmt(s.con_candidato);document.getElementById('db-act-sin').textContent=fmt(s.sin_candidato);
    // Lista de activaciones
    const actListWrap=document.getElementById('db-act-lista-wrap'),actListEl=document.getElementById('db-act-lista');
    if(s.activaciones_lista&&s.activaciones_lista.length>0){
      actListEl.innerHTML=s.activaciones_lista.map(a=>`<div class="db-open-text" style="margin-bottom:4px;font-size:10px;"><b>${a.titulo||'—'}</b> <span style="color:var(--texto-dim);">${a.tipo||''} · ${a.fecha_actividad||''}</span></div>`).join('');
      actListWrap.style.display='block';
    }else{actListWrap.style.display='none';}
    // Lista de eventos de agenda
    const evWrap=document.getElementById('db-eventos-wrap'),evLista=document.getElementById('db-eventos-lista');
    if(s.eventos_lista&&s.eventos_lista.length>0){
      evLista.innerHTML=s.eventos_lista.map(ev=>`<div class="db-open-text" style="margin-bottom:4px;font-size:10px;"><b>${ev.titulo||'—'}</b> <span style="color:var(--texto-dim);">${ev.eje||''} · ${ev.fecha||''}</span></div>`).join('');
      evWrap.style.display='block';
    }else{evWrap.style.display='none';}
    const setOpen=(wrapId,textId,val)=>{const ok=val&&val.trim();document.getElementById(wrapId).style.display=ok?'block':'none';if(ok)document.getElementById(textId).textContent=val;};
    setOpen('db-nec-wrap','db-necesidades',s.principales_necesidades);setOpen('db-enf-wrap','db-enfoque',s.enfoque);setOpen('db-per-wrap','db-perfil',s.perfil_socioeconomico);
    // Botón "Ver detalle completo" → modal completo si existe
    const dbLink=document.getElementById('db-link');
    if(dbLink){dbLink.style.display='block';dbLink.onclick=()=>{seccionDbData=Object.assign(seccionDbData||{},s);abrirModalDetalle();};}
    document.getElementById('db-panel').style.display='block';
    seccionDbData=Object.assign(seccionDbData||{},s);
  }catch(err){document.getElementById('db-spinner').style.display='none';document.getElementById('db-no-data').style.display='block';}
}

// ── Modal Detalle Sección ──
async function abrirModalDetalle() {
  if(!seccionDbData)return;
  const numSeccion=seccionDbData.seccion||seccionDbData.nombre_seccion;
  const estadoLabel=ESTADOS_DATA[estadoActual]?.label||ESTADO_LABEL;
  const overlay=document.getElementById('sec-modal-det');
  overlay.classList.add('open');
  document.getElementById('sdet-titulo').textContent=`SEC. ${numSeccion}`;document.getElementById('sdet-ubicacion').textContent='Cargando datos…';
  document.getElementById('sdet-spinner').style.display='block';document.getElementById('sdet-contenido').style.display='none';
  const fmt=n=>(n!=null&&n!==''&&!isNaN(n))?parseInt(n).toLocaleString('es-MX'):'—';
  const pct=(c,m)=>(!m||m==0)?0:Math.min(100,Math.round(c/m*100));
  try{
    const res=await fetch(`?action=get_seccion_detalle&seccion=${encodeURIComponent(numSeccion)}&estado=${encodeURIComponent(estadoLabel)}`);
    const data=await res.json();
    document.getElementById('sdet-spinner').style.display='none';document.getElementById('sdet-contenido').style.display='block';
    if(!data.ok||!data.seccion){document.getElementById('sdet-ubicacion').textContent=estadoLabel;document.getElementById('sdet-sin-datos').style.display='block';return;}
    const s=data.seccion;
    document.getElementById('sdet-titulo').textContent=`SEC. ${s.nombre_seccion}`;document.getElementById('sdet-ubicacion').textContent=[s.estado,s.municipio].filter(Boolean).join(' › ');
    document.getElementById('sdet-sin-datos').style.display='none';
    document.getElementById('sdet-seccion').textContent=s.nombre_seccion||'—';document.getElementById('sdet-municipio').textContent=s.municipio||'—';
    document.getElementById('sdet-df').textContent=s.distrito_federal||'—';document.getElementById('sdet-dl').textContent=s.distrito_local||'—';
    document.getElementById('sdet-region').textContent=s.region_asignada||'—';document.getElementById('sdet-zona').textContent=s.zona_asignada||'—';
    document.getElementById('sdet-padron').textContent=fmt(s.padron_electoral);document.getElementById('sdet-hombres').textContent=fmt(s.lista_hombres);document.getElementById('sdet-mujeres').textContent=fmt(s.lista_mujeres);
    const meta=parseInt(s.meta_contactos)||0,cons=parseInt(s.contactos_conseguidos)||0,p=pct(cons,meta);
    document.getElementById('sdet-meta').textContent=fmt(meta);document.getElementById('sdet-contactos').textContent=fmt(cons);
    document.getElementById('sdet-prog-fill').style.width=p+'%';document.getElementById('sdet-prog-pct').textContent=p+'%';document.getElementById('sdet-prog-rem').textContent=fmt(Math.max(0,meta-cons))+' restantes';
    document.getElementById('sdet-eventos').textContent=fmt(s.eventos_realizados);document.getElementById('sdet-votos').textContent=fmt(s.metas_votos);
    document.getElementById('sdet-act-total').textContent=fmt(s.activaciones_totales);document.getElementById('sdet-act-con').textContent=fmt(s.con_candidato);document.getElementById('sdet-act-sin').textContent=fmt(s.sin_candidato);
    const hayCampos=s.principales_necesidades||s.enfoque||s.perfil_socioeconomico;
    document.getElementById('sdet-info-wrap').style.display=hayCampos?'block':'none';
    const setOpen=(wrapId,textId,val)=>{const ok=val&&val.trim();document.getElementById(wrapId).style.display=ok?'block':'none';if(ok)document.getElementById(textId).textContent=val;};
    setOpen('sdet-nec-wrap','sdet-necesidades',s.principales_necesidades);setOpen('sdet-enf-wrap','sdet-enfoque',s.enfoque);setOpen('sdet-per-wrap','sdet-perfil',s.perfil_socioeconomico);
    // Eventos agenda
    const ejeColor={voto:'#2d7a3e',comunicacion:'#1a73e8',organizacion:'#8b00d4'},ejeLabel={voto:'🗳️ Voto',comunicacion:'📣 Comunicación',organizacion:'⚙️ Organización'};
    const listEv=document.getElementById('sdet-eventos-lista'),wrapEv=document.getElementById('sdet-eventos-wrap'),evs=s.eventos_lista||[];
    wrapEv.style.display='block';
    listEv.innerHTML=evs.length?evs.map(ev=>{const c=ejeColor[ev.eje]||'#666',hora=ev.inicio?` · ${ev.inicio.substring(0,5)}`:'';return`<div style="display:flex;gap:10px;align-items:flex-start;padding:8px 0;border-bottom:1px solid #f0f0f0;"><span style="width:8px;height:8px;border-radius:50%;background:${c};flex-shrink:0;margin-top:5px;"></span><div><div style="font-size:13px;font-weight:700;color:#1a1e2a;">${ev.titulo||'—'}</div><div style="font-size:11px;color:#6b7280;">${ejeLabel[ev.eje]||ev.eje||''} · ${ev.fecha||''}${hora}</div>${ev.responsable?`<div style="font-size:11px;color:#6b7280;">👤 ${ev.responsable}</div>`:''}</div></div>`;}).join(''):'<div style="font-size:12px;color:#9ca3af;padding:8px 0;">Sin eventos registrados para esta sección.</div>';
    // Activaciones
    const estatusBadge={programada:'background:#fff7ed;color:#d97706;border:1px solid #fde68a;','en-curso':'background:#eff6ff;color:#1e88e5;border:1px solid #bfdbfe;',completada:'background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0;',cancelada:'background:#fff5f5;color:#e53935;border:1px solid #fecaca;'};
    const listAct=document.getElementById('sdet-act-lista'),wrapAct=document.getElementById('sdet-act-lista-wrap'),acts=s.activaciones_lista||[];
    wrapAct.style.display='block';
    listAct.innerHTML=acts.length?acts.map(a=>{const badge=estatusBadge[a.estatus]||'',cand=a.candidato?`🙋 ${a.candidato}`:'🚫 Sin candidato';return`<div style="padding:8px 0;border-bottom:1px solid #f0f0f0;"><div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;"><span style="font-size:13px;font-weight:700;color:#1a1e2a;">${a.titulo||'—'}</span><span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:12px;${badge}">${(a.estatus||'').replace('-',' ')}</span></div><div style="font-size:11px;color:#6b7280;margin-top:3px;">${a.fecha_actividad||''} · ${cand}</div>${a.coordinador?`<div style="font-size:11px;color:#6b7280;">👤 ${a.coordinador}</div>`:''}</div>`;}).join(''):'<div style="font-size:12px;color:#9ca3af;padding:8px 0;">Sin activaciones registradas para esta sección.</div>';
  }catch(err){document.getElementById('sdet-spinner').style.display='none';document.getElementById('sdet-contenido').style.display='block';document.getElementById('sdet-sin-datos').style.display='block';document.getElementById('sdet-ubicacion').textContent=estadoLabel;}
}

function cerrarModalDetalle() { document.getElementById('sec-modal-det').classList.remove('open'); }

// ── Interceptar clic en mapa para seleccionar sección ──
if (layerSecciones) {
  layerSecciones.on('click', function(e) {
    if (!seleccionandoSeccion) return;
    const p = e.layer.feature?.properties;
    if (!p) return;
    const latlng = e.layer.getBounds().getCenter();
    seccionParaEvento = { num: p.seccion, latlng, estado: ESTADOS_DATA[estadoActual]?.label||ESTADO_LABEL, municipio: p.nombre_mun||p.NOM_MUN||'' };
    seleccionandoSeccion = false;
    map.getContainer().style.cursor = '';
    actualizarSelectorSeccion();
    document.getElementById('modal-overlay').classList.add('open');
  });
}

function mostrarToast(msg) {
  let t=document.getElementById('toast-ev');
  if(!t){t=document.createElement('div');t.id='toast-ev';t.style.cssText='position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:#1a1e2a;color:#fff;font-family:IBM Plex Sans,sans-serif;font-size:12px;padding:8px 16px;border-radius:6px;z-index:9999;pointer-events:none;opacity:0;transition:opacity 0.2s;white-space:nowrap;';document.body.appendChild(t);}
  t.textContent=msg;t.style.opacity='1';clearTimeout(t._timer);t._timer=setTimeout(()=>{t.style.opacity='0';},2500);
}

// ── Inicializar ──
window.addEventListener('DOMContentLoaded', () => {
  cargarEstado(ESTADO_INICIAL);
  cargarYPintarEventos();
  cargarYPintarActivaciones();
  map.on('layeradd', () => {
    if (layerSecciones && !layerSecciones._selListenerAdded) {
      layerSecciones._selListenerAdded = true;
      layerSecciones.on('click', function(e) {
        if (!seleccionandoSeccion) return;
        const p = e.layer.feature?.properties; if (!p) return;
        const latlng = e.layer.getBounds().getCenter();
        seccionParaEvento = { num: p.seccion, latlng, estado: ESTADOS_DATA[estadoActual]?.label||ESTADO_LABEL, municipio: p.nombre_mun||p.NOM_MUN||'' };
        seleccionandoSeccion = false; map.getContainer().style.cursor = '';
        actualizarSelectorSeccion();
        document.getElementById('modal-overlay').classList.add('open');
      });
    }
  });
});
</script>
</body>
</html>