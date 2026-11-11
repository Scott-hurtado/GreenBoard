<?php
/**
 * Mapa Electoral + Territorio - Coordinador de Zona (VERSIÓN MEJORADA)
 */

// Habilitar visualización de errores para debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

if (file_exists(__DIR__ . '/../../includes/bootstrap.php')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
} else { session_start(); }

$rol = $_SESSION['user_role'] ?? '';
$permitidos = ['coordinador_zona', 'coordinador_regional', 'coordinador_estatal', 'admin', 'superadmin'];
if (!in_array($rol, $permitidos)) { header('Location: /public/index.php'); exit(); }

// ══════════════════════════════════════════════════════════════════════
// HANDLER AJAX: detalle de seccion (llamado desde cargarDatosSeccion)
// ══════════════════════════════════════════════════════════════════════
if (isset($_GET['action'])) {
$_accion = $_GET['action'];

// Helper: obtener secciones de la zona del coordinador actual
function getTerritorioCZSecciones(PDO $pdo, int $userId, string $zona): array {
    $secs = [];
    if ($userId > 0) {
        $s = $pdo->prepare("SELECT z.secciones FROM coordinador_zona cz INNER JOIN zonas_estatales z ON cz.zona=z.id WHERE cz.id=? LIMIT 1");
        $s->execute([$userId]); $r=$s->fetch();
        if ($r) $secs = json_decode($r['secciones']??'[]',true)?:[];
    }
    if (empty($secs) && $zona) {
        $s = $pdo->prepare("SELECT secciones FROM zonas_estatales WHERE TRIM(nombre)=TRIM(?) LIMIT 1");
        $s->execute([$zona]); $r=$s->fetch();
        if ($r) $secs = json_decode($r['secciones']??'[]',true)?:[];
    }
    return $secs;
}
$_uid   = (int)($_SESSION['user_id']         ?? 0);
$_zona  = $_SESSION['user_zona_nombre']       ?? '';
$_estado = $_SESSION['user_estado']            ?? '';

if ($_accion === 'get_seccion_detalle') {
    header('Content-Type: application/json; charset=utf-8');
    $numSeccion  = trim($_GET['seccion'] ?? '');
    $estadoParam = trim($_GET['estado']  ?? '');
    if (!$numSeccion) { echo json_encode(['ok'=>false,'msg'=>'Seccion requerida']); exit; }
    try {
        $whereEstado = $estadoParam ? 'AND sec.estado_nombre = :estado' : '';
        $stmtD = $pdo->prepare("
            SELECT sec.id,
                sec.seccion                           AS nombre_seccion,
                sec.estado_nombre                     AS estado,
                m.nombre                              AS municipio,
                sec.distrito_federal, sec.distrito_local,
                COALESCE(se.lista_nominal,          0) AS lista_nominal,
                COALESCE(se.padron_electoral,       0) AS padron_electoral,
                COALESCE(se.lista_hombres,          0) AS lista_hombres,
                COALESCE(se.lista_mujeres,          0) AS lista_mujeres,
                COALESCE(se.meta_contactos,         0) AS meta_contactos,
                COALESCE(se.contactos_conseguidos,  0) AS contactos_conseguidos,
                COALESCE(se.metas_votos,            0) AS metas_votos,
                COALESCE(se.region_asignada,       '') AS region_asignada,
                COALESCE(se.zona_asignada,         '') AS zona_asignada,
                COALESCE(se.principales_necesidades,'') AS principales_necesidades,
                COALESCE(se.enfoque,               '') AS enfoque,
                COALESCE(se.perfil_socioeconomico, '') AS perfil_socioeconomico,
                COALESCE(se.grupo_edad_18_24,       0) AS grupo_edad_18_24,
                COALESCE(se.grupo_edad_25_34,       0) AS grupo_edad_25_34,
                COALESCE(se.grupo_edad_35_44,       0) AS grupo_edad_35_44,
                COALESCE(se.grupo_edad_45_54,       0) AS grupo_edad_45_54,
                COALESCE(se.grupo_edad_55_mas,      0) AS grupo_edad_55_mas
            FROM secciones sec
            LEFT JOIN municipios m ON m.id = sec.municipio_id
            LEFT JOIN secciones_electorales se
                   ON se.nombre_seccion = sec.seccion AND se.estado = sec.estado_nombre
            WHERE sec.seccion = :seccion $whereEstado
            LIMIT 1
        ");
        $params = [':seccion' => $numSeccion];
        if ($estadoParam) $params[':estado'] = $estadoParam;
        $stmtD->execute($params);
        $row = $stmtD->fetch();
        if (!$row) { echo json_encode(['ok'=>false,'msg'=>'No encontrada']); exit; }
        $row['eventos_realizados'] = 0; $row['activaciones_totales'] = 0;
        $row['con_candidato'] = 0; $row['sin_candidato'] = 0;
        $row['eventos_lista'] = []; $row['activaciones_lista'] = [];
        try {
            $s2 = $pdo->prepare("SELECT COUNT(*) FROM agenda_eventos WHERE seccion = :s AND activo = 1");
            $s2->execute([':s'=>$numSeccion]); $row['eventos_realizados'] = (int)$s2->fetchColumn();
            $s3 = $pdo->prepare("SELECT id,titulo,eje,subtipo,fecha,inicio,fin,estado,municipio,responsable,notas FROM agenda_eventos WHERE seccion=:s AND activo=1 ORDER BY fecha DESC LIMIT 20");
            $s3->execute([':s'=>$numSeccion]); $row['eventos_lista'] = $s3->fetchAll();
        } catch (Exception $e2) {}
        try {
            $s4 = $pdo->prepare("SELECT COUNT(*) FROM activaciones WHERE seccion_electoral=:s AND activo=1");
            $s4->execute([':s'=>$numSeccion]); $row['activaciones_totales'] = (int)$s4->fetchColumn();
            $s5 = $pdo->prepare("SELECT COUNT(*) FROM activaciones WHERE seccion_electoral=:s AND activo=1 AND candidato IS NOT NULL AND candidato<>''");
            $s5->execute([':s'=>$numSeccion]); $row['con_candidato'] = (int)$s5->fetchColumn();
            $row['sin_candidato'] = $row['activaciones_totales'] - $row['con_candidato'];
        } catch (Exception $e3) {}
        echo json_encode(['ok'=>true,'seccion'=>$row]);
    } catch (Exception $e) { echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    exit;
} // fin get_seccion_detalle

// ── Handler: list_eventos ─────────────────────────────────────────
if ($_accion === 'list_eventos') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $secs = getTerritorioCZSecciones($pdo, $_uid, $_zona);
        $eventos = [];
        if (!empty($secs)) {
            $ph = implode(',', array_fill(0, count($secs), '?'));
            $params = $secs;
            // Filtrar por estado si lo tenemos
            $whereEst = $_estado ? 'AND estado = ?' : '';
            if ($_estado) $params[] = $_estado;
            $s = $pdo->prepare("
                SELECT id, titulo, eje, subtipo, fecha, inicio, fin, horas,
                       estado, municipio, seccion, responsable, notas
                FROM agenda_eventos
                WHERE activo = 1
                  AND seccion IN ($ph)
                  $whereEst
                ORDER BY fecha DESC, inicio DESC
            ");
            $s->execute($params);
            $eventos = $s->fetchAll();
        }
        echo json_encode(['ok'=>true,'eventos'=>$eventos]);
    } catch (Exception $e) { echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    exit;
}

// ── Handler: list_activaciones ────────────────────────────────────
if ($_accion === 'list_activaciones') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $secs = getTerritorioCZSecciones($pdo, $_uid, $_zona);
        $acts = [];
        if (!empty($secs) && $_estado) {
            $ph = implode(',', array_fill(0, count($secs), '?'));
            $params = array_merge([$_estado], $secs);
            $s = $pdo->prepare("
                SELECT id, titulo, tipo, estatus, estado, municipio,
                       seccion_electoral AS seccion,
                       DATE_FORMAT(fecha_actividad,'%Y-%m-%d') AS fecha,
                       coordinador, contactos_estimados, asistentes_estimados,
                       latitud, longitud, notas
                FROM activaciones
                WHERE activo = 1
                  AND estado = ?
                  AND seccion_electoral IN ($ph)
                ORDER BY fecha_actividad DESC
            ");
            $s->execute($params);
            $acts = $s->fetchAll();
        }
        echo json_encode(['ok'=>true,'activaciones'=>$acts]);
    } catch (Exception $e) { echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    exit;
}

} // fin if isset($_GET['action'])

// ── Datos Operativos del Coordinador ──────────────────────────────────
$user_id           = (int)($_SESSION['user_id']         ?? 0);
$estadoCoordinador = $_SESSION['user_estado']            ?? 'Sonora';
$zonaCoordinador   = $_SESSION['user_zona_nombre']       ?? 'Zona Asignada';

// Logo del partido (misma ruta relativa que usa sidebar_cz.php)
$logoPartidoUrl = '../../storage/logo/PVEM.svg';

// ── Catalogo de estados: nombre en BD → GeoJSON key + centroide ───────
$todos_estados = [
    'sonora'           => ['label'=>'Sonora',            'municipioFile'=>'municipio.geojson',                  'seccionFile'=>'seccion.geojson',                  'coloniaFile'=>'colonias-sonora.geojson',            'centroide'=>[29.5,  -110.5],  'zoom'=>7],
    'bc'               => ['label'=>'Baja California',   'municipioFile'=>'municipio-bc.geojson',               'seccionFile'=>'seccion-bc.geojson',               'coloniaFile'=>'colonias-baja-california.geojson',   'centroide'=>[30.5,  -115.5],  'zoom'=>7],
    'bcs'              => ['label'=>'Baja California Sur','municipioFile'=>'municipio-bcs.geojson',              'seccionFile'=>'seccion-bcs.geojson',              'coloniaFile'=>'colonias-baja-california-sur.geojson','centroide'=>[24.5,  -111.5],  'zoom'=>7],
    'chihuahua'        => ['label'=>'Chihuahua',         'municipioFile'=>'municipio-chihuahua.geojson',        'seccionFile'=>'seccion-chihuahua.geojson',        'coloniaFile'=>'colonias-chihuahua.geojson',         'centroide'=>[28.6,  -106.1],  'zoom'=>7],
    'durango'          => ['label'=>'Durango',           'municipioFile'=>'municipio-durango.geojson',          'seccionFile'=>'seccion-durango.geojson',          'coloniaFile'=>'colonias-durango.geojson',           'centroide'=>[24.5,  -104.7],  'zoom'=>7],
    'sinaloa'          => ['label'=>'Sinaloa',           'municipioFile'=>'municipio-sinaloa.geojson',          'seccionFile'=>'seccion-sinaloa.geojson',          'coloniaFile'=>'colonias-sinaloa.geosjon.geojson',   'centroide'=>[24.8,  -107.4],  'zoom'=>7],
    'nayarit'          => ['label'=>'Nayarit',           'municipioFile'=>'municipio-nayarit.geojson',          'seccionFile'=>'seccion-nayarit.geojson',          'coloniaFile'=>'colonias-nayarit.geojson',           'centroide'=>[21.75,-104.85],  'zoom'=>8],
    'jalisco'          => ['label'=>'Jalisco',           'municipioFile'=>'municipio-jalisco.geojson',          'seccionFile'=>'seccion-jalisco.geojson',          'coloniaFile'=>'colonias-jalisco.geosjon.geojson',   'centroide'=>[20.6,  -103.3],  'zoom'=>7],
    'aguascalientes'   => ['label'=>'Aguascalientes',    'municipioFile'=>'municipio-aguascalientes.geojson',   'seccionFile'=>'seccion-aguascalientes.geojson',   'coloniaFile'=>'colonias-aguascalientes.geojson',    'centroide'=>[21.88,-102.29],  'zoom'=>9],
    'coahuila'         => ['label'=>'Coahuila',          'municipioFile'=>'municipio-coahuila.geojson',         'seccionFile'=>'seccion-coahuila.geojson',         'coloniaFile'=>'colonias-coahuila.geojson',          'centroide'=>[27.3,  -102.0],  'zoom'=>7],
    'guanajuato'       => ['label'=>'Guanajuato',        'municipioFile'=>'municipio-guanajuato.geojson',       'seccionFile'=>'seccion-guanajuato.geojson',       'coloniaFile'=>'colonias-guanajuato.geojson',        'centroide'=>[21.0,  -101.25], 'zoom'=>8],
    'nuevo-leon'       => ['label'=>'Nuevo Leon',        'municipioFile'=>'municipio-nuevo-leon.geojson',       'seccionFile'=>'seccion-nuevo-leon.geojson',       'coloniaFile'=>'colonias-nuevo-leon.geojson',        'centroide'=>[25.5,   -99.9],  'zoom'=>7],
    'san-luis-potosi'  => ['label'=>'San Luis Potosi',   'municipioFile'=>'municipio-san-luis-potosi.geojson',  'seccionFile'=>'seccion-san-luis-potosi.geojson',  'coloniaFile'=>'colonias-san-luis-potosi.geojson',   'centroide'=>[22.2,  -100.98], 'zoom'=>7],
    'tamaulipas'       => ['label'=>'Tamaulipas',        'municipioFile'=>'municipio-tamaulipas.geojson',       'seccionFile'=>'seccion-tamaulipas.geojson',       'coloniaFile'=>'colonias-tamaulipas.geojson',        'centroide'=>[24.3,   -98.8],  'zoom'=>7],
    'zacatecas'        => ['label'=>'Zacatecas',         'municipioFile'=>'municipio-zacatecas.geojson',        'seccionFile'=>'seccion-zacatecas.geojson',        'coloniaFile'=>'colonias-zacatecas.geojson',         'centroide'=>[23.0,  -102.5],  'zoom'=>7],
    'campeche'         => ['label'=>'Campeche',          'municipioFile'=>'municipio-campeche.geojson',         'seccionFile'=>'seccion-campeche.geojson',         'coloniaFile'=>'colonias-campeche.geojson',          'centroide'=>[18.9,   -90.2],  'zoom'=>7],
    'chiapas'          => ['label'=>'Chiapas',           'municipioFile'=>'municipio-chiapas.geojson',          'seccionFile'=>'seccion-chiapas.geojson',          'coloniaFile'=>'colonias-chiapas.geojson',           'centroide'=>[16.75,  -92.6],  'zoom'=>7],
    'oaxaca'           => ['label'=>'Oaxaca',            'municipioFile'=>'municipio-oaxaca.geojson',           'seccionFile'=>'seccion-oaxaca.geojson',           'coloniaFile'=>'colonias-oaxaca.geosjon.geojson',    'centroide'=>[17.0,   -96.7],  'zoom'=>7],
    'quintana-roo'     => ['label'=>'Quintana Roo',      'municipioFile'=>'municipio-quintana-roo.geojson',     'seccionFile'=>'seccion-quintana-roo.geojson',     'coloniaFile'=>null,                                 'centroide'=>[19.5,   -88.0],  'zoom'=>7],
    'tabasco'          => ['label'=>'Tabasco',           'municipioFile'=>'municipio-tabasco.geojson',          'seccionFile'=>'seccion-tabasco.geojson',          'coloniaFile'=>'colonias-tabasco.geojson',           'centroide'=>[18.0,   -92.8],  'zoom'=>8],
    'veracruz'         => ['label'=>'Veracruz',          'municipioFile'=>'municipio-veracruz.geojson',         'seccionFile'=>'seccion-veracruz.geojson',         'coloniaFile'=>'colonias-veracruz.geojson',          'centroide'=>[19.5,   -96.9],  'zoom'=>7],
    'yucatan'          => ['label'=>'Yucatan',           'municipioFile'=>'municipio-yucatan.geojson',          'seccionFile'=>'seccion-yucatan.geojson',          'coloniaFile'=>'colonias-yucatan.geojson',           'centroide'=>[20.7,   -89.1],  'zoom'=>8],
    'ciudad-de-mexico' => ['label'=>'Ciudad de Mexico',  'municipioFile'=>'municipio-ciudad-de-mexico.geojson', 'seccionFile'=>'seccion-ciudad-de-mexico.geojson', 'coloniaFile'=>'colonias-ciudad-de-mexico.geojson',  'centroide'=>[19.43,  -99.13], 'zoom'=>10],
    'guerrero'         => ['label'=>'Guerrero',          'municipioFile'=>'municipio-guerrero.geojson',         'seccionFile'=>'seccion-guerrero.geojson',         'coloniaFile'=>'colonias-guerrero.geojson',          'centroide'=>[17.55,  -99.5],  'zoom'=>7],
    'hidalgo'          => ['label'=>'Hidalgo',           'municipioFile'=>'municipio-hidalgo.geojson',          'seccionFile'=>'seccion-hidalgo.geojson',          'coloniaFile'=>'colonias-hidalgo.geojson',           'centroide'=>[20.5,   -98.8],  'zoom'=>8],
    'morelos'          => ['label'=>'Morelos',           'municipioFile'=>'municipio-morelos.geojson',          'seccionFile'=>'seccion-morelos.geojson',          'coloniaFile'=>'colonias-morelos.geojson',           'centroide'=>[18.7,   -99.1],  'zoom'=>9],
    'puebla'           => ['label'=>'Puebla',            'municipioFile'=>'municipio-puebla.geojson',           'seccionFile'=>'seccion-puebla.geojson',           'coloniaFile'=>'colonias-puebla.geojson',            'centroide'=>[19.0,   -97.9],  'zoom'=>8],
    'tlaxcala'         => ['label'=>'Tlaxcala',          'municipioFile'=>'municipio-tlaxcala.geojson',         'seccionFile'=>'seccion-tlaxcala.geojson',         'coloniaFile'=>'colonias-tlaxcala.geojson',          'centroide'=>[19.32,  -98.23], 'zoom'=>9],
    'colima'           => ['label'=>'Colima',            'municipioFile'=>'municipio-colima.geojson',           'seccionFile'=>'seccion-colima.geojson',           'coloniaFile'=>'colonias-colima.geojson',            'centroide'=>[19.12, -103.72], 'zoom'=>9],
    'estado-de-mexico' => ['label'=>'Estado de Mexico',  'municipioFile'=>'municipio-estado-de-mexico.geojson', 'seccionFile'=>'seccion-estado-de-mexico.geojson', 'coloniaFile'=>'colonias-estado-de-mexico.geojson',  'centroide'=>[19.35,  -99.65], 'zoom'=>8],
    'michoacan'        => ['label'=>'Michoacan',         'municipioFile'=>'municipio-michoacan.geojson',        'seccionFile'=>'seccion-michoacan.geojson',        'coloniaFile'=>'colonias-michoacan.geojson',         'centroide'=>[19.0,  -101.9],  'zoom'=>7],
    'queretaro'        => ['label'=>'Queretaro',         'municipioFile'=>'municipio-queretaro.geojson',        'seccionFile'=>'seccion-queretaro.geojson',        'coloniaFile'=>'colonias-queretaro.geosjon.geojson', 'centroide'=>[20.6,  -100.4],  'zoom'=>8],
];

$NOMBRE_A_CLAVE = [
    'Sonora'=>'sonora','Baja California'=>'bc','Baja California Sur'=>'bcs',
    'Chihuahua'=>'chihuahua','Durango'=>'durango','Sinaloa'=>'sinaloa',
    'Nayarit'=>'nayarit','Jalisco'=>'jalisco','Aguascalientes'=>'aguascalientes',
    'Coahuila'=>'coahuila','Guanajuato'=>'guanajuato',
    'Nuevo Leon'=>'nuevo-leon','Nuevo León'=>'nuevo-leon',
    'San Luis Potosi'=>'san-luis-potosi','San Luis Potosí'=>'san-luis-potosi',
    'Tamaulipas'=>'tamaulipas','Zacatecas'=>'zacatecas','Campeche'=>'campeche',
    'Chiapas'=>'chiapas','Oaxaca'=>'oaxaca','Quintana Roo'=>'quintana-roo',
    'Tabasco'=>'tabasco','Veracruz'=>'veracruz',
    'Yucatan'=>'yucatan','Yucatán'=>'yucatan',
    'Ciudad de Mexico'=>'ciudad-de-mexico','Ciudad de México'=>'ciudad-de-mexico',
    'Guerrero'=>'guerrero','Hidalgo'=>'hidalgo','Morelos'=>'morelos',
    'Puebla'=>'puebla','Tlaxcala'=>'tlaxcala','Colima'=>'colima',
    'Estado de Mexico'=>'estado-de-mexico','Estado de México'=>'estado-de-mexico',
    'Michoacan'=>'michoacan','Michoacán'=>'michoacan',
    'Queretaro'=>'queretaro','Querétaro'=>'queretaro',
];
$estadoClave  = $NOMBRE_A_CLAVE[$estadoCoordinador] ?? strtolower(str_replace(' ','-',$estadoCoordinador));
$estadoCfgMap = $todos_estados[$estadoClave] ?? null;
$geojsonPath  = __DIR__ . '/../../geojson/';

// ── Conexion a BD ──────────────────────────────────────────────────────
$seccionesZona = [];
$municipiosZona = [];
$metaZona = 1;
$seccionesDatosZona = [];
$totalLNZona = 0;
$distritosLocalesZona = [];
$distritosFederalesZona = [];
try {
    $dbConfigPath = __DIR__ . '/../../includes/database.php';
    $dbConfig = file_exists($dbConfigPath) ? require $dbConfigPath : [
        'host'    => 'localhost','database'=>'u881824383_pverde',
        'username'=>'u881824383_proyverde','password'=>'PProyectoverde8#','charset'=>'utf8mb4',
        'options' =>[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
    ];
    if (!isset($pdo)) {
        $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
        $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options'] ?? []);
    }

    // Obtener secciones via JOIN: coordinador_zona → zonas_estatales
    // (mismo patron que estructura_cz.php — unico metodo correcto)
    if ($user_id > 0) {
        $stmtZ = $pdo->prepare("
            SELECT z.secciones, z.meta
            FROM coordinador_zona cz
            INNER JOIN zonas_estatales z ON cz.zona = z.id
            WHERE cz.id = ? LIMIT 1
        ");
        $stmtZ->execute([$user_id]);
        $zonaRow = $stmtZ->fetch();
        if ($zonaRow) {
            $seccionesZona = json_decode($zonaRow['secciones'] ?? '[]', true) ?: [];
            $metaZona = max(1, (int)($zonaRow['meta'] ?? 1));
        }
    }
    // Fallback por nombre de zona
    if (empty($seccionesZona) && !empty($zonaCoordinador)) {
        $stmtZb = $pdo->prepare("SELECT secciones, meta FROM zonas_estatales WHERE TRIM(nombre)=TRIM(?) LIMIT 1");
        $stmtZb->execute([$zonaCoordinador]);
        $zb = $stmtZb->fetch();
        if ($zb) { $seccionesZona = json_decode($zb['secciones'] ?? '[]', true) ?: []; $metaZona = max(1,(int)($zb['meta']??1)); }
    }

    // Municipios de las secciones (filtrado por estado para evitar duplicados)
    if (!empty($seccionesZona)) {
        $phM = implode(',', array_fill(0, count($seccionesZona), '?'));
        $stmtM = $pdo->prepare("
            SELECT DISTINCT m.nombre AS municipio
            FROM secciones sec LEFT JOIN municipios m ON m.id=sec.municipio_id
            WHERE sec.seccion IN ($phM) AND sec.estado_nombre = ?
        ");
        $stmtM->execute(array_merge($seccionesZona, [$estadoCoordinador]));
        $municipiosZona = array_filter(array_column($stmtM->fetchAll(), 'municipio'));
    }

    // Datos por sección para la hoja de impresión: L.N., distrito local/federal
    if (!empty($seccionesZona)) {
        $phS = implode(',', array_fill(0, count($seccionesZona), '?'));
        $stmtS = $pdo->prepare("
            SELECT sec.seccion, sec.distrito_local, sec.distrito_federal,
                   COALESCE(se.lista_nominal, 0) AS lista_nominal
            FROM secciones sec
            LEFT JOIN secciones_electorales se
                   ON se.nombre_seccion = sec.seccion AND se.estado = sec.estado_nombre
            WHERE sec.seccion IN ($phS) AND sec.estado_nombre = ?
        ");
        $stmtS->execute(array_merge($seccionesZona, [$estadoCoordinador]));
        foreach ($stmtS->fetchAll() as $row) {
            $sNum = $row['seccion'];
            $ln = (int)$row['lista_nominal'];
            $seccionesDatosZona[$sNum] = $ln;
            $totalLNZona += $ln;
            if ($row['distrito_local']   !== null && $row['distrito_local']   !== '') $distritosLocalesZona[(string)$row['distrito_local']]   = true;
            if ($row['distrito_federal'] !== null && $row['distrito_federal'] !== '') $distritosFederalesZona[(string)$row['distrito_federal']] = true;
        }
    }
} catch (PDOException $e) { error_log("Error DB territorio_cz: " . $e->getMessage()); }
$seccionesDatosZona     = $seccionesDatosZona     ?? [];
$totalLNZona            = $totalLNZona            ?? 0;
$distritosLocalesZona   = array_keys($distritosLocalesZona   ?? []); sort($distritosLocalesZona,   SORT_NUMERIC);
$distritosFederalesZona = array_keys($distritosFederalesZona ?? []); sort($distritosFederalesZona, SORT_NUMERIC);

// ── Google Sheets ──────────────────────────────────────────────────────
define('GOOGLE_SHEET_ID',   '1PB10SQiyOBoRoD7lIoA4dhSC9ITLvixMP5VrOoIRQ-E');
define('GOOGLE_SHEET_NAME', 'DB');
function getGoogleSheetData() {
    $url=$url='https://docs.google.com/spreadsheets/d/'.GOOGLE_SHEET_ID.'/gviz/tq?tqx=out:csv&sheet='.GOOGLE_SHEET_NAME;
    $users=[];
    try {
        $ctx=stream_context_create(['http'=>['timeout'=>30,'user_agent'=>'Mozilla/5.0']]);
        $csv=@file_get_contents($url,false,$ctx);
        if(!$csv) throw new Exception('No se pudo conectar');
        foreach(explode("
",$csv) as $i=>$line){
            if($i===0||empty(trim($line))) continue;
            $row=array_pad(str_getcsv($line),14,'');
            $users[]=['fecha'=>trim($row[0]),'celular'=>trim($row[1]),'tiene_codigo'=>trim($row[2]),
                'codigo'=>trim($row[3]),'nombre'=>trim($row[4]).' '.trim($row[5]),'municipio'=>trim($row[6]),
                'estado'=>trim($row[7]),'colonia'=>trim($row[8]),'seccion'=>trim($row[10]),
                'rol'=>trim($row[11]),'temas_interes'=>trim($row[12]),'propuesta'=>trim($row[13])];
        }
    } catch(Exception $e){$users=[];}
    return $users;
}
$allUsers=getGoogleSheetData();
$usersFiltrados=array_values(array_filter($allUsers,function($u) use ($seccionesZona){
    return in_array(trim($u['seccion']),array_map('strval',$seccionesZona));
}));
$total=count($usersFiltrados);
$pct=min(100,$metaZona>0?round(($total/$metaZona)*100,2):0);
$rolesCount=['simpatizante'=>0,'militante'=>0,'voluntario'=>0,'evento'=>0,'indeciso'=>0];
foreach($usersFiltrados as $u){
    $r=strtolower($u['rol']);
    if(stripos($r,'simpatizante')!==false)$rolesCount['simpatizante']++;
    elseif(stripos($r,'militante')!==false)$rolesCount['militante']++;
    elseif(stripos($r,'voluntario')!==false||stripos($r,'activista')!==false)$rolesCount['voluntario']++;
    elseif(stripos($r,'evento')!==false||stripos($r,'vine')!==false)$rolesCount['evento']++;
    else $rolesCount['indeciso']++;
}
$statsData=['total'=>$total,'meta'=>$metaZona,'pct'=>$pct,'roles'=>$rolesCount];

// ── Configuracion GeoJSON del estado del coordinador ──────────────────
$munFile    = $estadoCfgMap ? $geojsonPath.$estadoCfgMap['municipioFile'] : null;
$munData    = ($munFile && file_exists($munFile)) ? json_decode(file_get_contents($munFile),true) : null;
$secUrl     = $estadoCfgMap ? '../../geojson/'.$estadoCfgMap['seccionFile'] : null;
$distFedUrl = $estadoCfgMap ? '../../geojson/distrito-federal-'.$estadoClave.'.geojson' : null;
$distLocUrl = $estadoCfgMap ? '../../geojson/distrito-local-'.$estadoClave.'.geojson'   : null;
$coloniaUrl = (!empty($estadoCfgMap['coloniaFile'])) ? '../../geojson/'.$estadoCfgMap['coloniaFile'] : null;
$centroide  = $estadoCfgMap['centroide'] ?? [23.6345,-102.5528];
$zoom       = $estadoCfgMap['zoom']      ?? 7;
$estadoActivo = [
    'label'      => $estadoCoordinador,
    'centroide'  => $centroide,
    'zoom'       => $zoom,
    'seccionUrl' => $secUrl,
    'distFedUrl' => $distFedUrl,
    'distLocUrl' => $distLocUrl,
    'coloniaUrl' => $coloniaUrl,
    'municipio'  => $munData,
];
$vistReturnUrl = htmlspecialchars(trim($_GET['return_url'] ?? ''));

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cartografía Electoral - Zona</title>

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

    body {
      font-family: 'IBM Plex Sans', sans-serif;
      background: var(--blanco);
      color: var(--texto);
      height: 100vh;
      display: flex;
      flex-direction: column;
      overflow: hidden;
      padding-left: 280px; /* sidebar width */
    }

    /* Layout */
    .main { display: flex; flex: 1; overflow: hidden; }

    /* Header & Buscador */
    header {
      background: var(--blanco);
      border-bottom: 1px solid var(--gris-borde);
      padding: 0 24px;
      height: 52px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-shrink: 0;
      z-index: 1000;
    }

    .header-left {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .logo-dot {
      width: 10px; height: 10px;
      background: var(--verde-acento);
      border-radius: 50%;
      box-shadow: 0 0 8px rgba(34,160,80,0.35);
    }

    header h1 {
      font-size: 13px;
      font-weight: 600;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: var(--texto);
    }

    header span {
      font-size: 12px;
      color: var(--texto-dim);
      font-family: 'IBM Plex Mono', monospace;
    }

    .search-box {
      display: flex;
      align-items: center;
      background: var(--gris-panel);
      border: 1px solid var(--gris-borde);
      border-radius: 6px;
      padding: 0 10px;
      height: 32px;
    }
    .search-box input {
      border: none;
      background: transparent;
      outline: none;
      font-size: 12px;
      width: 140px;
      font-family: 'IBM Plex Sans', sans-serif;
    }
    .search-box button {
      background: none;
      border: none;
      color: var(--texto-dim);
      cursor: pointer;
      padding: 5px;
    }
    .search-box button:hover { color: var(--verde); }

    /* .btn-evento removido en rol Zona */

    /* Modal estilo Agenda */
    .modal-overlay {
      display: none; position: fixed; inset: 0;
      background: rgba(255,255,255,0.9); backdrop-filter: blur(5px);
      z-index: 9000; align-items: center; justify-content: center;
      opacity: 0; transition: opacity 0.3s ease;
    }
    .modal-overlay.open { display: flex; opacity: 1; }
    .modal {
      background: var(--blanco); border-radius: 20px;
      border: 1px solid var(--gris-borde);
      box-shadow: 0 25px 50px rgba(0,0,0,0.1);
      width: 90%; max-width: 680px; padding: 40px;
      position: relative; transform: translateY(30px); opacity: 0;
      transition: all 0.4s ease-out; max-height: 90vh; overflow-y: auto;
    }
    .modal-overlay.open .modal { transform: translateY(0); opacity: 1; }
    .modal-close {
      position: absolute; top: 25px; right: 25px;
      font-size: 22px; cursor: pointer; color: #ccc;
      width: 40px; height: 40px; display: flex; align-items: center;
      justify-content: center; border-radius: 50%; border: none;
      background: transparent;
      transition: all 0.3s cubic-bezier(0.175,0.885,0.32,1.275);
    }
    .modal-close:hover { color: var(--verde); background: #f0f7f0; transform: rotate(90deg) scale(1.1); }
    .modal-title { font-size: 24px; font-weight: 800; margin-bottom: 5px; color: var(--texto); font-family: 'IBM Plex Sans', sans-serif; }
    .modal-subtitle { font-size: 14px; color: #999; margin-bottom: 25px; }

    .form-group { display: flex; flex-direction: column; gap: 8px; }
    .form-label { font-size: 12px; font-weight: 700; text-transform: uppercase; color: var(--texto-dim); letter-spacing: 0.5px; }
    .form-input-ag {
      width: 100%; padding: 12px 15px; border-radius: 10px;
      border: 2px solid var(--gris-borde); background: var(--gris-panel);
      font-size: 14px; font-weight: 600; color: var(--texto);
      transition: all 0.3s; outline: none; appearance: none; font-family: 'IBM Plex Sans', sans-serif;
    }
    .form-input-ag:focus { border-color: var(--verde); background: var(--blanco); }
    textarea.form-input-ag { resize: vertical; min-height: 90px; font-weight: 400; }

    .eje-selector-ag { display: grid; grid-template-columns: repeat(3,1fr); gap: 8px; }
    .eje-btn-ag {
      padding: 10px 6px; border-radius: 10px; border: 2px solid var(--gris-borde);
      font-size: 11px; font-weight: 700; cursor: pointer; text-align: center;
      background: var(--gris-panel); color: var(--texto-dim); transition: 0.2s; font-family: 'IBM Plex Sans', sans-serif;
    }
    .eje-btn-ag.sel-voto         { background: rgba(26,122,60,.12);  border-color: var(--verde);   color: var(--verde); }
    .eje-btn-ag.sel-comunicacion { background: rgba(33,150,243,.12); border-color: #2196f3; color: #2196f3; }
    .eje-btn-ag.sel-organizacion { background: rgba(156,39,176,.12); border-color: #9c27b0; color: #9c27b0; }

    .modal-footer-ag { display: flex; justify-content: space-between; align-items: center; margin-top: 30px; padding-top: 25px; border-top: 1px solid var(--gris-borde); }
    .btn-save-ag { background: var(--verde); color: white; border: none; padding: 12px 28px; border-radius: 12px; font-weight: 700; cursor: pointer; transition: all 0.3s; font-size: 14px; }
    .btn-save-ag:hover { background: var(--verde-hover); transform: translateY(-1px); }
    .btn-cancel-ag { background: var(--blanco); color: var(--texto-dim); border: 1px solid var(--gris-borde); padding: 12px 22px; border-radius: 12px; font-weight: 600; cursor: pointer; transition: 0.3s; font-size: 13px; }
    .btn-cancel-ag:hover { border-color: var(--texto); color: var(--texto); }
    .btn-danger-ag { background: #ffeaea; color: #d32f2f; border: 1px solid #fecaca; padding: 12px 20px; border-radius: 12px; font-weight: 700; cursor: pointer; font-size: 13px; }
    .btn-danger-ag:hover { background: #fee2e2; }

    .seccion-badge { display: inline-flex; align-items: center; gap: 8px; background: rgba(26,122,60,0.1); color: var(--verde); border: 1px solid rgba(26,122,60,0.3); border-radius: 8px; padding: 8px 14px; font-size: 13px; font-weight: 700; font-family: 'IBM Plex Mono', monospace; }
    .seccion-badge.empty { background: var(--gris-panel); color: var(--texto-dim); border-color: var(--gris-borde); font-family: 'IBM Plex Sans', sans-serif; font-weight: 500; }

    .detail-grid-ag { display: grid; grid-template-columns: repeat(3,1fr); gap: 20px; margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--gris-borde); }
    .detail-item-ag strong { display: block; font-size: 11px; color: var(--texto-dim); text-transform: uppercase; margin-bottom: 5px; letter-spacing: 0.5px; }
    .detail-item-ag span { font-size: 15px; color: var(--texto); font-weight: 600; }
    .eje-badge-det { display: inline-flex; align-items: center; gap: 6px; padding: 4px 14px; border-radius: 20px; font-size: 12px; font-weight: 700; border: 1px solid; margin-bottom: 15px; }

    /* Icono de evento en el mapa */
    .evento-marker { background: transparent !important; border: none !important; box-shadow: none !important; pointer-events: none; }
    .evento-pin { display: flex; flex-direction: column; align-items: center; }
    .evento-pin-icon { width: 26px; height: 26px; background: var(--verde, #1a7a3c); border-radius: 50% 50% 50% 0; transform: rotate(-45deg); display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 8px rgba(0,0,0,0.25); border: 2px solid white; }
    .evento-pin-icon i { transform: rotate(45deg); color: white; font-size: 10px; }
    .evento-pin-label { margin-top: 3px; font-family: 'IBM Plex Mono', monospace; font-size: 8px; font-weight: 600; color: #0f5228; background: rgba(255,255,255,0.85); padding: 1px 4px; border-radius: 3px; white-space: nowrap; }


    .panel { width: 260px; background: var(--gris-panel); border-right: 1px solid var(--gris-borde); display: flex; flex-direction: column; flex-shrink: 0; overflow-y: auto; }
    .panel-section { padding: 16px; border-bottom: 1px solid var(--gris-borde); }
    .panel-label { font-size: 10px; font-weight: 600; letter-spacing: 0.12em; text-transform: uppercase; color: var(--texto-dim); margin-bottom: 10px; }
    .stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
    .stat-box { background: var(--blanco); border: 1px solid var(--gris-borde); border-radius: 6px; padding: 10px; }
    .stat-num { font-family: 'IBM Plex Mono', monospace; font-size: 20px; font-weight: 600; color: var(--verde); line-height: 1; }
    .stat-label { font-size: 10px; color: var(--texto-dim); margin-top: 3px; }
    
    .layer-item { display: flex; align-items: center; justify-content: space-between; padding: 8px 0; cursor: pointer; }
    .layer-left { display: flex; align-items: center; gap: 8px; }
    .layer-color { width: 12px; height: 12px; border-radius: 2px; flex-shrink: 0; }
    .layer-name { font-size: 12px; color: var(--texto); transition: color 0.15s; }
    .layer-item:hover .layer-name { color: var(--verde); }

    .toggle { width: 28px; height: 16px; background: #ccc; border-radius: 8px; position: relative; transition: background 0.2s; flex-shrink: 0; }
    .toggle.on { background: var(--verde); }
    .toggle::after { content: ''; position: absolute; width: 12px; height: 12px; background: white; border-radius: 50%; top: 2px; left: 2px; transition: left 0.2s; }
    .toggle.on::after { left: 14px; }

    .btn-export {
      background: var(--blanco); border: 1px solid var(--gris-borde); color: var(--texto);
      padding: 8px; border-radius: 6px; font-size: 11px; font-weight: 600; cursor: pointer;
      transition: all 0.2s; display: flex; justify-content: center; align-items: center; gap: 6px;
    }
    .btn-export:hover { border-color: var(--verde); color: var(--verde); background: #f0f7f0; }

    /* #chart-container removido en rol Zona */

    #info-panel { padding: 16px; display: none; overflow-y: auto; max-height: calc(100vh - 220px); }
    #info-panel.visible { display: block; }

    /* Estilos del panel de datos de BD */
    .db-sep { border: none; border-top: 1px solid var(--gris-borde); margin: 14px 0 10px; }
    .db-section-label { font-size: 9px; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: var(--verde); margin: 14px 0 6px; display: flex; align-items: center; gap: 5px; }
    .info-val.verde { color: var(--verde); font-weight: 700; }
    .db-progress-bg { height: 6px; border-radius: 3px; background: var(--gris-medio); overflow: hidden; margin: 5px 0 3px; }
    .db-progress-fill { height: 100%; border-radius: 3px; background: linear-gradient(90deg, #1a7a3c, #22a050); transition: width 0.5s ease; }
    .db-progress-meta { display: flex; justify-content: space-between; font-size: 9px; color: var(--texto-dim); font-family: 'IBM Plex Mono', monospace; }
    
    .edad-chips { display: flex; flex-wrap: wrap; gap: 3px; margin: 5px 0 3px; }
    .edad-chip { font-size: 9px; font-weight: 600; background: var(--gris-medio); color: var(--texto-dim); padding: 2px 5px; border-radius: 3px; font-family: 'IBM Plex Mono', monospace; }
    
    .act-mini-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 5px; margin: 6px 0; }
    .act-mini-card { background: var(--blanco); border: 1px solid var(--gris-borde); border-radius: 5px; padding: 7px 4px; text-align: center; }
    .act-mini-num { font-family: 'IBM Plex Mono', monospace; font-size: 16px; font-weight: 600; color: var(--verde); line-height: 1; }
    .act-mini-lbl { font-size: 8px; color: var(--texto-dim); margin-top: 2px; letter-spacing: 0.04em; }
    
    .db-open-field { margin-bottom: 7px; }
    .db-open-label { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: var(--texto-dim); margin-bottom: 3px; }
    .db-open-text { font-size: 11px; color: var(--texto); background: var(--gris-panel); border-radius: 4px; padding: 6px 8px; line-height: 1.5; }
    
    #db-spinner { text-align: center; padding: 14px 0; }
    #db-no-data { font-size: 11px; color: var(--texto-dim); background: var(--gris-panel); border-radius: 5px; padding: 8px 10px; text-align: center; margin-top: 10px; }

    .info-title { font-family: 'IBM Plex Mono', monospace; font-size: 22px; font-weight: 600; color: var(--verde); margin-bottom: 12px; }
    .info-row { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid var(--gris-borde); font-size: 12px; }
    .info-row:last-child { border-bottom: none; }
    .info-key { color: var(--texto-dim); }
    .info-val { font-family: 'IBM Plex Mono', monospace; color: var(--texto); }

    /* Mapa */
    #map { flex: 1; }

    .lbl-seccion {
      background: transparent !important; border: none !important; box-shadow: none !important;
      font-family: 'IBM Plex Mono', monospace; font-size: 9px; font-weight: 600; color: #1a7a3c;
      text-shadow: 0 0 3px rgba(255,255,255,1), 0 0 6px rgba(255,255,255,0.8);
      white-space: nowrap; pointer-events: none;
    }

    body.escala-labels-2 .lbl-seccion { font-size: 18px; }
    body.escala-labels-3 .lbl-seccion { font-size: 27px; }

    .btn-escala { display:flex; gap:4px; }
    .btn-escala button { flex:1; font-family:'IBM Plex Mono',monospace; font-size:10px; font-weight:700; padding:5px 0; border-radius:5px; border:1px solid var(--gris-borde); background:var(--blanco); color:var(--texto-dim); cursor:pointer; }
    .btn-escala button.activo { background:var(--verde); color:#fff; border-color:var(--verde); }

    #btn-imprimir-mapa { width:100%; margin-top:8px; padding:8px 0; font-family:'IBM Plex Mono',monospace; font-size:11px; font-weight:700; border-radius:6px; border:1px solid var(--verde); background:var(--verde); color:#fff; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:6px; }
    #btn-imprimir-mapa:hover { filter:brightness(1.08); }

    select#select-capa-base { width:100%; font-size:11px; font-family:'IBM Plex Sans',sans-serif; padding:6px 8px; border-radius:5px; border:1px solid var(--gris-borde); background:var(--blanco); color:var(--texto); outline:none; }

    /* Hoja de impresión (réplica del formato armado a mano: logo + tabla SEC/L.N. + zona + coordinador) */
    #hoja-impresion-pie { display:none; }
    #hoja-impresion-pie {
      grid-template-columns: 100px 1.4fr 1fr 1fr;
      font-family: Arial, sans-serif; color:#111;
    }
    #hoja-impresion-pie > div { border-right:1px solid #333; padding:6px 8px; }
    #hoja-impresion-pie > div:last-child { border-right:none; }
    .hip-logo { display:flex; align-items:center; justify-content:center; }
    .hip-logo img { max-width:88px; max-height:70px; object-fit:contain; }
    .hip-logo-placeholder { width:78px; height:58px; border:1px dashed #999; display:flex; align-items:center; justify-content:center; font-size:8px; color:#999; text-align:center; }
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
      /* Oculta TODO (incluye el menú lateral de la app, header, panel, controles
         de Leaflet, etc. sin importar su markup) y solo muestra el mapa y la
         hoja de datos de impresión */
      body * { visibility: hidden !important; }
      #map, #map *,
      #hoja-impresion-pie, #hoja-impresion-pie * { visibility: visible !important; }

      body { background:#fff !important; padding-left:0 !important; margin:0 !important; }
      .main { display:block !important; }

      #map {
        position: fixed !important; top:0; left:0;
        width:100vw !important; height:74vh !important;
        border:1.5px solid #333;
      }
      #hoja-impresion-pie {
        display:grid !important;
        position: fixed !important; top:74vh; left:0;
        width:100vw !important;
        border:1.5px solid #333; border-top:none;
      }
      .leaflet-control-zoom, .leaflet-control-attribution, .leaflet-control-layers { display:none !important; }
    }


    /* Leaflet overrides */
    .leaflet-container { background: #f4f6f4; }
    .leaflet-interactive:focus { outline: none !important; }
    .leaflet-control-attribution { display: none !important; }
    .leaflet-control-zoom { border: 1px solid var(--gris-borde) !important; border-radius: 6px !important; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,0.08) !important; }
    .leaflet-control-zoom a { background: white !important; color: var(--texto) !important; border-bottom: 1px solid var(--gris-borde) !important; width: 28px !important; height: 28px !important; line-height: 28px !important; }
    .leaflet-control-zoom a:hover { background: var(--gris-medio) !important; color: var(--verde) !important; }

    .ev-count-badge {
      font-family: 'IBM Plex Mono', monospace; font-size: 9px; font-weight: 700;
      background: var(--verde); color: white; padding: 1px 6px; border-radius: 8px; margin-left: 4px;
    }
  </style>
</head>
<body>

<?php include __DIR__ . '/../../includes/components/sidebar_cz.php'; ?>

<?php if ($vistReturnUrl): ?>
<div style="position:fixed;top:0;left:280px;right:0;z-index:9999;background:#1565c0;color:white;padding:10px 20px;display:flex;align-items:center;justify-content:space-between;gap:12px;box-shadow:0 3px 14px rgba(0,0,0,0.25);">
  <div style="display:flex;align-items:center;gap:12px;">
    <i class="fas fa-map-marked-alt" style="font-size:18px;opacity:0.9;"></i>
    <div>
      <div style="font-weight:800;font-size:13px;">VISTA DE ZONA — <span style="font-weight:500;opacity:0.9;"><?= htmlspecialchars($zonaCoordinador) ?></span></div>
      <div style="font-size:11px;opacity:0.85;margin-top:1px;">Secciones de tu zona resaltadas en el mapa</div>
    </div>
  </div>
  <button onclick="window.location.href='<?= $vistReturnUrl ?>'" style="background:white;color:#1565c0;border:none;padding:8px 20px;border-radius:8px;font-weight:800;font-size:13px;cursor:pointer;display:flex;align-items:center;gap:7px;" onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
    <i class="fas fa-arrow-left"></i> Regresar a Estructura
  </button>
</div>
<style>body { padding-top: 48px !important; }</style>
<?php endif; ?>

<header>
  <div class="header-left">
    <div class="logo-dot"></div>
    <h1>Cartografía - ZONA: <?= htmlspecialchars($zonaCoordinador) ?></h1>
    <span id="header-estado-span"><?= htmlspecialchars($estadoCoordinador) ?></span>
  </div>
  <div style="display:flex;align-items:center;gap:16px;">
    <div class="search-box">
      <input type="number" id="buscar-seccion-input" placeholder="Buscar sección..." onkeypress="if(event.key==='Enter') buscarSeccionMapa()">
      <button onclick="buscarSeccionMapa()"><i class="fas fa-search"></i></button>
    </div>
    
    <!-- Agregar Evento removido para Coordinador de Zona (per spec) -->
  </div>
</header>

<div class="main">

  <aside class="panel">

    <div class="panel-section">
      <div class="panel-label">Métricas de Zona</div>
      <div class="stats-grid">
        <div class="stat-box">
          <div class="stat-num" id="stat-secciones"><?= count($seccionesZona) ?></div>
          <div class="stat-label">Secciones Asignadas</div>
        </div>
        <div class="stat-box">
          <div class="stat-num" id="stat-municipios"><?= count($municipiosZona) ?></div>
          <div class="stat-label">Municipios Abarcados</div>
        </div>
      </div>
    </div>

    <div class="panel-section" style="padding:12px 16px;">
      <button class="btn-export" onclick="exportarReporteExcel()" style="width:100%;">
        <i class="fas fa-file-excel"></i> Descargar Reporte Excel
      </button>
    </div>

    <div class="panel-section">
      <div class="panel-label">Filtrar Eventos / Activaciones</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-bottom:4px;">
        <div>
          <div style="font-size:9px;color:var(--texto-dim);font-weight:600;letter-spacing:0.08em;text-transform:uppercase;margin-bottom:3px;">Año</div>
          <select id="filtro-anio" onchange="aplicarFiltros()" style="width:100%;font-size:11px;font-family:'IBM Plex Sans',sans-serif;padding:5px 6px;border-radius:5px;border:1px solid var(--gris-borde);background:var(--blanco);color:var(--texto);outline:none;">
            <option value="">Todos</option>
            <?php for ($y = date('Y'); $y >= 2023; $y--): ?>
            <option value="<?= $y ?>"><?= $y ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div>
          <div style="font-size:9px;color:var(--texto-dim);font-weight:600;letter-spacing:0.08em;text-transform:uppercase;margin-bottom:3px;">Mes</div>
          <select id="filtro-mes" onchange="aplicarFiltros()" style="width:100%;font-size:11px;font-family:'IBM Plex Sans',sans-serif;padding:5px 6px;border-radius:5px;border:1px solid var(--gris-borde);background:var(--blanco);color:var(--texto);outline:none;">
            <option value="">Todos</option>
            <option value="01">Enero</option><option value="02">Febrero</option>
            <option value="03">Marzo</option><option value="04">Abril</option>
            <option value="05">Mayo</option><option value="06">Junio</option>
            <option value="07">Julio</option><option value="08">Agosto</option>
            <option value="09">Septiembre</option><option value="10">Octubre</option>
            <option value="11">Noviembre</option><option value="12">Diciembre</option>
          </select>
        </div>
      </div>
    </div>

    <div class="panel-section">
      <div class="panel-label">Capas Visuales</div>
      <div class="layer-item" id="row-secciones" onclick="toggleCapa('secciones')">
        <div class="layer-left">
          <div class="layer-color" style="background:transparent;border:2px solid #22a050;"></div>
          <span class="layer-name" id="label-secciones">Tu Territorio (Interactivo)</span>
        </div>
        <div class="toggle on" id="toggle-secciones"></div>
      </div>
      <div class="layer-item" id="row-labels" onclick="toggleCapa('labels')">
        <div class="layer-left">
          <div class="layer-color" style="background:#1a7a3c;border-radius:50%;"></div>
          <span class="layer-name">Números de Sección</span>
        </div>
        <div class="toggle on" id="toggle-labels"></div>
      </div>
      <div class="layer-item" id="row-eventos" onclick="toggleCapaEventos()">
        <div class="layer-left">
          <div class="layer-color" style="background:#1a7a3c;border-radius:50%;"></div>
          <span class="layer-name">Eventos <span class="ev-count-badge" id="ev-count-badge">0</span></span>
        </div>
        <div class="toggle on" id="toggle-eventos"></div>
      </div>
      <div class="layer-item" id="row-activaciones" onclick="toggleCapaActivaciones()">
        <div class="layer-left">
          <div class="layer-color" style="background:#e65100;border-radius:50%;"></div>
          <span class="layer-name">Activaciones <span class="ev-count-badge" style="background:#e65100;" id="act-count-badge">0</span></span>
        </div>
        <div class="toggle on" id="toggle-activaciones"></div>
      </div>

      <div style="border-top:1px solid var(--gris-borde);margin:8px 0 6px;"></div>
      <div class="panel-label" style="margin-top:4px;">Capas Administrativas</div>

      <div class="layer-item" id="row-municipio" onclick="toggleCapaAdmin('municipio')">
        <div class="layer-left">
          <div class="layer-color" style="background:transparent;border:2px solid #1565c0;border-radius:2px;"></div>
          <span class="layer-name">Municipio</span>
        </div>
        <div class="toggle" id="toggle-municipio"></div>
      </div>
      <div class="layer-item" id="row-distfederal" onclick="toggleCapaAdmin('distfederal')">
        <div class="layer-left">
          <div class="layer-color" style="background:transparent;border:2px solid #6a1b9a;border-radius:2px;"></div>
          <span class="layer-name">Distrito Federal</span>
        </div>
        <div class="toggle" id="toggle-distfederal"></div>
      </div>
      <div class="layer-item" id="row-distlocal" onclick="toggleCapaAdmin('distlocal')">
        <div class="layer-left">
          <div class="layer-color" style="background:transparent;border:2px solid #e65100;border-radius:2px;"></div>
          <span class="layer-name">Distrito Local</span>
        </div>
        <div class="toggle" id="toggle-distlocal"></div>
      </div>
      <div class="layer-item" id="row-colonia" onclick="toggleCapaAdmin('colonia')">
        <div class="layer-left">
          <div class="layer-color" style="background:transparent;border:2px solid #00838f;border-radius:2px;"></div>
          <span class="layer-name">Colonia</span>
        </div>
        <div class="toggle" id="toggle-colonia"></div>
      </div>
    </div>

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
    </div>

    <div id="info-panel">
      <div class="panel-label">Sección seleccionada</div>
      <div class="info-title" id="info-seccion">—</div>

      <div class="info-row">
        <span class="info-key">Dist. Federal</span>
        <span class="info-val" id="info-df">—</span>
      </div>
      <div class="info-row">
        <span class="info-key">Dist. Local</span>
        <span class="info-val" id="info-dl">—</span>
      </div>
      <div class="info-row">
        <span class="info-key">Municipio</span>
        <span class="info-val" id="info-mun">—</span>
      </div>
      <div class="info-row">
        <span class="info-key">Entidad</span>
        <span class="info-val" id="info-ent">—</span>
      </div>
      <div class="info-row">
        <span class="info-key">Tipo</span>
        <span class="info-val" id="info-tipo">—</span>
      </div>

      <div id="db-spinner" style="display:none;">
        <i class="fas fa-spinner fa-spin" style="color:var(--verde);font-size:15px;"></i>
        <div style="font-size:9px;color:var(--texto-dim);margin-top:4px;letter-spacing:0.05em;">Cargando datos operativos…</div>
      </div>

      <div id="db-no-data" style="display:none;">
        <i class="fas fa-info-circle"></i> Sin datos registrados en BD
      </div>

      <div id="db-panel" style="display:none;">

        <hr class="db-sep">

        <div class="db-section-label"><i class="fas fa-map-pin"></i> Zona de Cobertura</div>
        <div class="info-row">
          <span class="info-key">Región</span>
          <span class="info-val" id="db-region">—</span>
        </div>
        <div class="info-row">
          <span class="info-key">Zona</span>
          <span class="info-val" id="db-zona">—</span>
        </div>

        <div class="db-section-label"><i class="fas fa-users"></i> Lista Nominal</div>
        <div class="info-row">
          <span class="info-key">Total</span>
          <span class="info-val verde" id="db-nominal">—</span>
        </div>
        <div class="info-row">
          <span class="info-key"><i class="fas fa-mars" style="color:#1565c0;"></i> Hombres</span>
          <span class="info-val" id="db-hombres">—</span>
        </div>
        <div class="info-row">
          <span class="info-key"><i class="fas fa-venus" style="color:#c2185b;"></i> Mujeres</span>
          <span class="info-val" id="db-mujeres">—</span>
        </div>
        <div class="edad-chips" id="db-edad-chips"></div>

        <div class="db-section-label"><i class="fas fa-address-book"></i> Contactos Conseguidos</div>
        <div class="info-row">
          <span class="info-key">Meta</span>
          <span class="info-val" id="db-meta">—</span>
        </div>
        <div class="info-row">
          <span class="info-key">Registrados</span>
          <span class="info-val verde" id="db-contactos">—</span>
        </div>
        <div class="db-progress-bg">
          <div class="db-progress-fill" id="db-progress-fill" style="width:0%"></div>
        </div>
        <div class="db-progress-meta">
          <span id="db-pct">0%</span>
          <span id="db-rem">0 restantes</span>
        </div>

        <div class="db-section-label"><i class="fas fa-calendar-check"></i> Resultados</div>
        <div class="info-row">
          <span class="info-key">Eventos realizados</span>
          <span class="info-val verde" id="db-eventos">—</span>
        </div>
        <div class="info-row">
          <span class="info-key">Meta de votos</span>
          <span class="info-val verde" id="db-votos">—</span>
        </div>

        <div class="db-section-label"><i class="fas fa-bolt"></i> Activaciones</div>
        <div class="act-mini-grid">
          <div class="act-mini-card"><div class="act-mini-num" id="db-act-total">—</div><div class="act-mini-lbl">Total</div></div>
          <div class="act-mini-card"><div class="act-mini-num" id="db-act-con">—</div><div class="act-mini-lbl">C/Cand.</div></div>
          <div class="act-mini-card"><div class="act-mini-num" id="db-act-sin">—</div><div class="act-mini-lbl">S/Cand.</div></div>
        </div>

        <div class="db-section-label"><i class="fas fa-align-left"></i> Notas Estratégicas</div>
        <div class="db-open-field" id="db-nec-wrap" style="display:none;">
          <div class="db-open-label">Principales Necesidades</div>
          <div class="db-open-text" id="db-necesidades"></div>
        </div>
        <div class="db-open-field" id="db-enf-wrap" style="display:none;">
          <div class="db-open-label">Enfoque</div>
          <div class="db-open-text" id="db-enfoque"></div>
        </div>
        <div class="db-open-field" id="db-per-wrap" style="display:none;">
          <div class="db-open-label">Perfil Socioeconómico</div>
          <div class="db-open-text" id="db-perfil"></div>
        </div>

      </div>
    </div>
  </aside>

  <div id="map"></div>

</div>

<!-- Hoja de impresión: solo visible al imprimir (imprimirMapa()); replica el formato que se llenaba a mano -->
<div id="hoja-impresion-pie">
  <div class="hip-logo">
    <?php if ($logoPartidoUrl): ?>
      <img src="<?= htmlspecialchars($logoPartidoUrl) ?>" alt="Logo">
    <?php else: ?>
      <div class="hip-logo-placeholder">LOGO</div>
    <?php endif; ?>
  </div>

  <div class="hip-tabla">
    <table>
      <tbody>
        <tr><th>SEC</th><th>L.N.</th><th>SEC</th><th>L.N.</th></tr>
        <?php
          $entradas = [];
          foreach ($seccionesZona as $s) { $entradas[] = [$s, $seccionesDatosZona[$s] ?? 0]; }
          $mitad = (int)ceil(count($entradas) / 2);
          $colIzq = array_slice($entradas, 0, $mitad);
          $colDer = array_slice($entradas, $mitad);
          for ($i = 0; $i < $mitad; $i++):
            $izq = $colIzq[$i] ?? null;
            $der = $colDer[$i] ?? null;
        ?>
        <tr>
          <td><?= $izq ? htmlspecialchars($izq[0]) : '' ?></td>
          <td><?= $izq ? number_format($izq[1]) : '' ?></td>
          <td><?= $der ? htmlspecialchars($der[0]) : '' ?></td>
          <td><?= $der ? number_format($der[1]) : '' ?></td>
        </tr>
        <?php endfor; ?>
      </tbody>
    </table>
  </div>

  <div class="hip-zona">
    <div>
      <div class="hip-zona-label">ZONA :</div>
      <div class="hip-zona-code"><?= htmlspecialchars($zonaCoordinador) ?></div>
    </div>
    <div class="hip-zona-label" style="margin-top:6px;">MUNICIPIO:</div>
    <div class="hip-zona-code" style="font-size:12px;"><?= htmlspecialchars(implode(' / ', $municipiosZona) ?: '—') ?></div>
    <div style="display:flex;gap:10px;margin-top:6px;">
      <div><span class="hip-zona-label">DISTRITO LOCAL:</span> <b><?= htmlspecialchars(implode(', ', $distritosLocalesZona) ?: '—') ?></b></div>
    </div>
    <div style="display:flex;gap:10px;">
      <div><span class="hip-zona-label">DISTRITO FEDERAL:</span> <b><?= htmlspecialchars(implode(', ', $distritosFederalesZona) ?: '—') ?></b></div>
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
    <div class="hip-zona-code"><?= number_format($totalLNZona) ?></div>
  </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// Datos Operativos y Estadísticas (NUEVO)
const ESTADO_CONFIG = <?= json_encode($estadoActivo) ?>;
const SECCIONES_ZONA = <?= json_encode($seccionesZona) ?>;
// ROLES_DATA removido (gráfica de distribución de red quitada en rol Zona)
const METRICAS_ZONA = <?= json_encode($statsData) ?>;

// ──────────────────────────────────────────────
// EXPORTAR REPORTE EXCEL (xlsx via SheetJS CDN)
// ──────────────────────────────────────────────
function exportarReporteExcel() {
    // Carga SheetJS dinámicamente si no está disponible
    function _doExport() {
        const XLSX = window.XLSX;
        const zona = "<?= htmlspecialchars($zonaCoordinador) ?>";
        const fecha = new Date().toLocaleDateString('es-MX');

        // Hoja 1 – Métricas generales
        const metricasRows = [
            ["Reporte de Zona", zona],
            ["Fecha", fecha],
            [],
            ["Métrica", "Valor"],
            ["Secciones Asignadas", SECCIONES_ZONA.length],
            ["Total Registros (Red)", METRICAS_ZONA.total],
            ["Meta Zona", METRICAS_ZONA.meta],
            ["% Avance", METRICAS_ZONA.pct + "%"],
        ];
        const wsMetricas = XLSX.utils.aoa_to_sheet(metricasRows);

        // Hoja 2 – Eventos de la zona
        const evHeaders = ["ID","Título","Eje","Subtipo","Fecha","Inicio","Fin","Horas","Estado","Municipio","Sección","Responsable","Notas"];
        const evRows = eventosData.map(ev => [ev.id,ev.titulo,ev.eje,ev.subtipo,ev.fecha,ev.inicio,ev.fin,ev.horas,ev.estado,ev.municipio,ev.seccion,ev.responsable,ev.notas]);
        const wsEventos = XLSX.utils.aoa_to_sheet([evHeaders, ...evRows]);

        // Hoja 3 – Activaciones de la zona
        const actHeaders = ["ID","Título","Tipo","Estatus","Estado","Municipio","Sección","Fecha","Coordinador","Contactos Est.","Asistentes Est.","Notas"];
        const actRows = activacionesData.map(a => [a.id,a.titulo,a.tipo,a.estatus,a.estado,a.municipio,a.seccion,a.fecha,a.coordinador,a.contactos_estimados,a.asistentes_estimados,a.notas]);
        const wsActs = XLSX.utils.aoa_to_sheet([actHeaders, ...actRows]);

        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, wsMetricas, "Métricas");
        XLSX.utils.book_append_sheet(wb, wsEventos,  "Eventos");
        XLSX.utils.book_append_sheet(wb, wsActs,     "Activaciones");

        const nombreArchivo = "reporte_zona_<?= strtolower(str_replace(' ','_',$zonaCoordinador)) ?>_" + new Date().toISOString().slice(0,10) + ".xlsx";
        XLSX.writeFile(wb, nombreArchivo);
        mostrarToast("Reporte Excel descargado ✓");
    }

    if (window.XLSX) {
        _doExport();
    } else {
        const s = document.createElement('script');
        s.src = 'https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js';
        s.onload = _doExport;
        document.head.appendChild(s);
    }
}

// ──────────────────────────────────────────────
// FILTROS AÑO / MES para Eventos y Activaciones
// ──────────────────────────────────────────────
let filtroAnio = '';
let filtroMes  = '';

function aplicarFiltros() {
    filtroAnio = document.getElementById('filtro-anio').value;
    filtroMes  = document.getElementById('filtro-mes').value;
    repintarEventos();
    repintarActivaciones();
    actualizarBadgesFiltrados();
}

function eventosFiltrados() {
    return eventosData.filter(ev => {
        const f = ev.fecha || '';
        if (filtroAnio && !f.startsWith(filtroAnio)) return false;
        if (filtroMes  && f.slice(5,7) !== filtroMes)  return false;
        return true;
    });
}

function activacionesFiltradas() {
    return activacionesData.filter(a => {
        const f = a.fecha || '';
        if (filtroAnio && !f.startsWith(filtroAnio)) return false;
        if (filtroMes  && f.slice(5,7) !== filtroMes)  return false;
        return true;
    });
}

function actualizarBadgesFiltrados() {
    document.getElementById('ev-count-badge').textContent  = eventosFiltrados().length;
    document.getElementById('act-count-badge').textContent = activacionesFiltradas().length;
}

// ──────────────────────────────────────────────
// CAPAS ADMINISTRATIVAS (Municipio, DF, DL, Colonia)
// ──────────────────────────────────────────────
const capasAdmin = { municipio: null, distfederal: null, distlocal: null, colonia: null };
const capasAdminVisible = { municipio: false, distfederal: false, distlocal: false, colonia: false };

// Colores y opacidades para cada capa administrativa
const adminEstilos = {
    municipio:   { color: '#1565c0', weight: 2,   fillOpacity: 0.05 },
    distfederal: { color: '#6a1b9a', weight: 1.5, fillOpacity: 0.04 },
    distlocal:   { color: '#e65100', weight: 1.5, fillOpacity: 0.04 },
    colonia:     { color: '#00838f', weight: 1,   fillOpacity: 0.06 },
};

// Archivos GeoJSON reutilizando los que ya tiene el sistema
const adminFiles = {
    municipio:   ESTADO_CONFIG.seccionUrl ? ESTADO_CONFIG.seccionUrl.replace('seccion','municipio') : null,
    distfederal: ESTADO_CONFIG.distFedUrl || null,
    distlocal:   ESTADO_CONFIG.distLocUrl || null,
    colonia:     ESTADO_CONFIG.coloniaUrl || null,
};

async function cargarCapaAdmin(nombre) {
    if (capasAdmin[nombre]) return; // ya cargada
    const url = adminFiles[nombre];
    if (!url) { mostrarToast('GeoJSON no disponible para esta capa'); return; }
    mostrarToast('Cargando capa ' + nombre + '…');
    try {
        const res = await fetch(url);
        if (!res.ok) throw new Error('No encontrado');
        const data = await res.json();
        const estilo = adminEstilos[nombre];

        const tooltipConfig = { sticky: true, direction: 'top', offset: [0,-8], opacity: 0.95 };

        if (nombre === 'colonia') {
            capasAdmin[nombre] = L.geoJSON(data, {
                style: { color: estilo.color, weight: estilo.weight, fillColor: estilo.color, fillOpacity: estilo.fillOpacity },
                onEachFeature: (feature, layer) => {
                    const p = feature.properties;
                    const nom = p.nombre || p.NOMBRE || p.colonia || p.nom_colonia || '—';
                    layer.bindTooltip(`<span style="font-family:IBM Plex Sans,sans-serif;font-size:11px;font-weight:600;color:#00838f;">${nom}</span>`, tooltipConfig);
                }
            });
        } else if (nombre === 'municipio') {
            capasAdmin[nombre] = L.geoJSON(data, {
                style: { color: estilo.color, weight: estilo.weight, fillColor: estilo.color, fillOpacity: estilo.fillOpacity },
                onEachFeature: (feature, layer) => {
                    const p = feature.properties;
                    const nom = p.NOM_MUN || p.nombre_mun || p.nombre || p.NOMBRE || p.municipio || '—';
                    layer.bindTooltip(`<span style="font-family:IBM Plex Sans,sans-serif;font-size:11px;font-weight:600;color:#1565c0;">${nom}</span>`, tooltipConfig);
                }
            });
        } else if (nombre === 'distfederal') {
            capasAdmin[nombre] = L.geoJSON(data, {
                style: { color: estilo.color, weight: estilo.weight, fillColor: estilo.color, fillOpacity: estilo.fillOpacity },
                onEachFeature: (feature, layer) => {
                    const p = feature.properties;
                    const num = p.distrito_federal || p.distrito || p.DISTRITO || p.numero || p.id || '—';
                    layer.bindTooltip(`<span style="font-family:IBM Plex Sans,sans-serif;font-size:11px;font-weight:700;color:#6a1b9a;">Dist. Federal ${num}</span>`, tooltipConfig);
                }
            });
        } else if (nombre === 'distlocal') {
            capasAdmin[nombre] = L.geoJSON(data, {
                style: { color: estilo.color, weight: estilo.weight, fillColor: estilo.color, fillOpacity: estilo.fillOpacity },
                onEachFeature: (feature, layer) => {
                    const p = feature.properties;
                    const num = p.distrito_local || p.distrito || p.DISTRITO || p.numero || p.id || '—';
                    layer.bindTooltip(`<span style="font-family:IBM Plex Sans,sans-serif;font-size:11px;font-weight:700;color:#e65100;">Dist. Local ${num}</span>`, tooltipConfig);
                }
            });
        } else {
            capasAdmin[nombre] = L.geoJSON(data, {
                style: { color: estilo.color, weight: estilo.weight, fillColor: estilo.color, fillOpacity: estilo.fillOpacity },
                interactive: false
            });
        }
    } catch(e) {
        mostrarToast('Capa "' + nombre + '" no disponible para este estado');
        capasAdmin[nombre] = false;
    }
}

async function toggleCapaAdmin(nombre) {
    capasAdminVisible[nombre] = !capasAdminVisible[nombre];
    document.getElementById('toggle-' + nombre).classList.toggle('on', capasAdminVisible[nombre]);
    if (capasAdminVisible[nombre]) {
        await cargarCapaAdmin(nombre);
        if (capasAdmin[nombre]) map.addLayer(capasAdmin[nombre]);
    } else {
        if (capasAdmin[nombre]) map.removeLayer(capasAdmin[nombre]);
    }
}

// Función Buscar Sección en el Mapa
function buscarSeccionMapa() {
    const input = document.getElementById('buscar-seccion-input').value.trim();
    if (!input) return;
    
    let seccionEncontrada = false;
    
    if (layerSecciones) {
        layerSecciones.eachLayer(layer => {
            if (String(layer.feature.properties.seccion) === input) {
                map.fitBounds(layer.getBounds(), { padding: [50, 50], maxZoom: 15 });
                layer.fire('click');
                seccionEncontrada = true;
            }
        });
    }
    
    if (!seccionEncontrada) {
        mostrarToast('La sección ' + input + ' no pertenece a tu zona o no existe.');
    }
}

const map = L.map('map', {
  zoomControl: true, attributionControl: false, minZoom: 5, maxZoom: 19
}).setView(ESTADO_CONFIG.centroide, ESTADO_CONFIG.zoom);

const capasBase = {
  claro:    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', { maxZoom: 19 }),
  osm:      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }),
  satelite: L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { maxZoom: 19, attribution: 'Esri' })
};
capasBase.claro.addTo(map);

// Capas
let layerSecciones = null;
let layerLabels = null;
let seccionActiva = null;
const capasVisibles = { secciones: true, labels: true };

// Silueta Base del Estado (Municipios)
if (ESTADO_CONFIG.municipio) {
    L.geoJSON(ESTADO_CONFIG.municipio, {
        style: { color: '#cccccc', weight: 1, fillColor: '#f8f9fa', fillOpacity: 0.4 },
        interactive: false
    }).addTo(map);
}

// Cargar Secciones (Filtradas visual e interactivamente por su Zona)
if (ESTADO_CONFIG.seccionUrl) {
    mostrarToast(`Cargando cartografía de tu zona...`);
    fetch(ESTADO_CONFIG.seccionUrl)
    .then(r => r.json())
    .then(secData => {
        layerSecciones = L.geoJSON(secData, {
            style: function(feature) {
                const secNum = String(feature.properties.seccion);
                // Resaltar SOLO las secciones de su zona
                if (SECCIONES_ZONA.includes(secNum)) {
                    return { color: '#22a050', weight: 1.5, fillColor: '#22a050', fillOpacity: 0.15 };
                } else {
                    return { color: '#e0e0e0', weight: 0.5, fillColor: '#ffffff', fillOpacity: 0.0 };
                }
            },
            onEachFeature: (feature, layer) => {
                const p = feature.properties;
                const secNum = String(p.seccion);
                
                // Hacer interactivas SOLO las secciones de su zona
                if (SECCIONES_ZONA.includes(secNum)) {
                    layer.on('click', function(e) {
                        if (seccionActiva) layerSecciones.resetStyle(seccionActiva);
                        this.setStyle({ fillColor: '#1a7a3c', fillOpacity: 0.45, color: '#0f5228', weight: 2 });
                        this.bringToFront();
                        seccionActiva = this;

                        document.getElementById('info-panel').classList.add('visible');
                        document.getElementById('info-seccion').textContent = `SEC. ${p.seccion}`;
                        document.getElementById('info-df').textContent   = `Distrito ${p.distrito_f}`;
                        document.getElementById('info-dl').textContent   = `Distrito ${p.distrito_l}`;
                        document.getElementById('info-mun').textContent  = p.nombre_mun || `Mun. ${p.municipio}`;
                        document.getElementById('info-ent').textContent  = p.nombre_ent || `Ent. ${p.entidad}`;
                        document.getElementById('info-tipo').textContent = p.tipo == 1 ? 'Urbana' : p.tipo == 2 ? 'Rural' : `Tipo ${p.tipo}`;

                        cargarDatosSeccion(p.seccion);
                        L.DomEvent.stopPropagation(e);
                    });
                } else {
                    layer.interactive = false; // Ignorar el resto del estado
                }
            }
        }).addTo(map);

        // Hacer zoom específico a las secciones de la zona
        if (layerSecciones && SECCIONES_ZONA.length > 0) {
            const bounds = L.latLngBounds();
            layerSecciones.eachLayer(layer => {
                if (SECCIONES_ZONA.includes(String(layer.feature.properties.seccion))) {
                    bounds.extend(layer.getBounds());
                }
            });
            if(bounds.isValid()) map.fitBounds(bounds, { padding: [30, 30] });
        }

        // Labels solo para sus secciones
        layerLabels = L.layerGroup();
        secData.features.forEach(feature => {
            if (!feature.geometry || !SECCIONES_ZONA.includes(String(feature.properties.seccion))) return;
            let latlng;
            try { latlng = L.geoJSON(feature).getBounds().getCenter(); } catch(e) { return; }
            layerLabels.addLayer(L.marker(latlng, {
                icon: L.divIcon({ className: 'lbl-seccion', html: feature.properties.seccion, iconSize: null, iconAnchor: [10, 7] }),
                interactive: false
            }));
        });
        layerLabels.addTo(map);
        
        repintarEventos();
        repintarActivaciones(); // Re-pintar ahora que layerSecciones ya esta disponible
    })
    .catch(err => console.error(err));
}

// Toggles de Capas
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

// Límites (bounds) de SOLO las secciones de la zona del coordinador
function obtenerBoundsZona() {
    if (!layerSecciones || !SECCIONES_ZONA.length) return null;
    const bounds = L.latLngBounds();
    layerSecciones.eachLayer(layer => {
        if (layer.feature && SECCIONES_ZONA.includes(String(layer.feature.properties.seccion))) {
            bounds.extend(layer.getBounds());
        }
    });
    return bounds.isValid() ? bounds : null;
}

// Redimensiona y centra el mapa en la zona; se llama otra vez justo en
// "beforeprint" (cuando el navegador ya aplicó @media print) para que el
// encuadre sea siempre el mismo sin importar cómo esté panneado en pantalla.
function centrarYAjustarParaImpresion() {
    map.invalidateSize();
    const bounds = obtenerBoundsZona();
    if (bounds) map.fitBounds(bounds, { padding: [20, 20] });
}

// Imprimir mapa: agranda etiquetas, cambia a capa con detalle de calles,
// centra la zona y abre el diálogo de impresión
function imprimirMapa() {
    const escalaPrevia = escalaLabelsActual;
    setEscalaLabels(3);
    const selectBase = document.getElementById('select-capa-base');
    if (selectBase) selectBase.value = 'osm';
    cambiarCapaBase('osm');

    window.onbeforeprint = centrarYAjustarParaImpresion;
    window.onafterprint = () => {
        setEscalaLabels(escalaPrevia);
        window.onbeforeprint = null;
        window.onafterprint = null;
    };

    // Dar tiempo a que carguen los tiles de OSM antes de abrir el diálogo
    setTimeout(() => {
        centrarYAjustarParaImpresion();
        window.print();
    }, 400);
}

function toggleCapa(nombre) {
    capasVisibles[nombre] = !capasVisibles[nombre];
    document.getElementById(`toggle-${nombre}`).classList.toggle('on', capasVisibles[nombre]);
    const capaMap = { secciones: layerSecciones, labels: layerLabels };
    const capa = capaMap[nombre];
    if (!capa) return;
    capasVisibles[nombre] ? map.addLayer(capa) : map.removeLayer(capa);
}

// Click para soltar selección
map.on('click', function(e) {
    if (seleccionandoSeccion) {
        seleccionandoSeccion = false;
        map.getContainer().style.cursor = '';
        let found = null;
        if (layerSecciones) {
            layerSecciones.eachLayer(layer => {
                if (found) return;
                try { 
                    if (SECCIONES_ZONA.includes(String(layer.feature.properties.seccion)) && layer.getBounds && layer.getBounds().contains(e.latlng)) {
                        found = layer; 
                    }
                } catch(_) {}
            });
        }
        if (found && found.feature) {
            const p = found.feature.properties;
            const latlng = found.getBounds().getCenter();
            seccionParaEvento = { num: p.seccion, latlng, estado: p.nombre_ent || '', municipio: p.nombre_mun || '' };
        } else {
            mostrarToast('Esta sección no pertenece a tu zona.');
            return; // No dejar asignar en secciones ajenas
        }
        setTimeout(() => {
            actualizarSelectorSeccion();
            document.getElementById('modal-overlay').classList.add('open');
        }, 80);
        return;
    }

    if (seccionActiva && layerSecciones)  { layerSecciones.resetStyle(seccionActiva);  seccionActiva = null; }
    document.getElementById('info-panel').classList.remove('visible');
});

// ──────────────────────────────────────────────
// AGENDA EVENTOS
// ──────────────────────────────────────────────
const AGENDA_API      = '?action=list_eventos'; // Lectura: handler local filtrado por zona
const AGENDA_SAVE_URL = '../../api/agenda/procesar.php'; // Escritura: API de agenda CZ
let eventosData = [];
let capasVisiblesEventos = true;
const layerEventos      = L.layerGroup().addTo(map);
const layerActivaciones = L.layerGroup().addTo(map);
let activacionesData = [];
let capasVisiblesActivaciones = true;

// ── Jitter: separa pines que caen en el mismo punto ──────────────
let _eventoOffsets     = {};
let _activacionOffsets = {};

function jitteredLatLng(lat, lng, offsetMap) {
    const key = parseFloat(lat).toFixed(4) + ',' + parseFloat(lng).toFixed(4);
    const idx  = offsetMap[key] !== undefined ? offsetMap[key] : 0;
    offsetMap[key] = idx + 1;
    if (idx === 0) return [lat, lng]; // primer pin: sin desplazamiento
    const radio  = 0.00028; // ~28 m
    const angulo = ((idx - 1) / 6) * 2 * Math.PI;
    return [parseFloat(lat) + radio * Math.sin(angulo),
            parseFloat(lng) + radio * Math.cos(angulo)];
}
let seleccionandoSeccion = false;
let seccionParaEvento = null;

async function cargarYPintarEventos() {
    try {
        const res  = await fetch(AGENDA_API);
        const data = await res.json();
        if (!data.ok) return;
        // Solo guardar los eventos de las secciones que le corresponden a su zona
        eventosData = (data.eventos || []).filter(ev => SECCIONES_ZONA.includes(String(ev.seccion)));
        repintarEventos();
        actualizarBadgesFiltrados();
    } catch(e) {}
}

function repintarEventos() {
    _eventoOffsets = {};
    layerEventos.clearLayers();
    eventosFiltrados().forEach(ev => colocarMarkerEvento(ev));
}

function toggleCapaEventos() {
    capasVisiblesEventos = !capasVisiblesEventos;
    document.getElementById('toggle-eventos').classList.toggle('on', capasVisiblesEventos);
    capasVisiblesEventos ? map.addLayer(layerEventos) : map.removeLayer(layerEventos);
}

const SUBTIPOS_M = {
    voto: ['Casa por casa','Evento de campaña','Mitin','Brigada vecinal','Recorrido territorial','Firma de compromisos','Contacto directo'],
    comunicacion: ['Entrevista de radio','Entrevista de TV','Rueda de prensa','Publicación redes sociales','Spot / grabación','Vocería','Evento mediático'],
    organizacion: ['Reunión de estructura','Capacitación','Coordinación interna','Planeación estratégica','Logística','Revisión de presupuesto','Reunión de equipo']
};
let ejeSeleccionadoM = null;

function seleccionarEjeM(eje) {
    ejeSeleccionadoM = eje;
    document.querySelectorAll('.eje-btn-ag').forEach(b => b.className = b.dataset.eje === eje ? `eje-btn-ag sel-${eje}` : 'eje-btn-ag');
    const sel = document.getElementById('m-subtipo');
    sel.innerHTML = '<option value="">Selecciona subtipo…</option>';
    (SUBTIPOS_M[eje] || []).forEach(s => {
        const o = document.createElement('option'); o.value = s; o.textContent = s; sel.appendChild(o);
    });
}

function calcHorasM() {
    const ini = document.getElementById('m-inicio').value;
    const fin = document.getElementById('m-fin').value;
    if (ini && fin) {
        const [hi,mi] = ini.split(':').map(Number), [hf,mf] = fin.split(':').map(Number);
        const d = (hf*60+mf - hi*60-mi) / 60;
        if (d > 0) document.getElementById('m-horas').value = d.toFixed(1);
    }
}

function abrirModal() {
    ejeSeleccionadoM = null;
    document.querySelectorAll('.eje-btn-ag').forEach(b => b.className = 'eje-btn-ag');
    document.getElementById('m-subtipo').innerHTML = '<option value="">— Selecciona un eje primero —</option>';
    ['m-titulo','m-inicio','m-fin','m-horas','m-responsable','m-notas'].forEach(id => document.getElementById(id).value = '');
    
    const hoy = new Date();
    document.getElementById('m-fecha').value = `${hoy.getFullYear()}-${String(hoy.getMonth()+1).padStart(2,'0')}-${String(hoy.getDate()).padStart(2,'0')}`;

    if (seccionActiva) {
        const p = seccionActiva.feature.properties;
        const latlng = L.geoJSON(seccionActiva.feature).getBounds().getCenter();
        seccionParaEvento = { num: p.seccion, latlng, estado: ESTADO_CONFIG.label || '', municipio: p.nombre_mun || p.NOM_MUN || '' };
    }
    actualizarSelectorSeccion();
    document.getElementById('modal-overlay').classList.add('open');
}

function cerrarModal() { document.getElementById('modal-overlay').classList.remove('open'); seleccionandoSeccion = false; }
function cerrarModalDet() { document.getElementById('modal-det').classList.remove('open'); }
function cerrarModalDetAct() { document.getElementById('modal-det-act').classList.remove('open'); }

const ESTATUS_STYLES_ACT_M = {
  'planificada':  'background:#fff7ed;color:#d97706;border:1px solid #fde68a;',
  'en progreso':  'background:#eff6ff;color:#1e88e5;border:1px solid #bfdbfe;',
  'completada':   'background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0;',
  'cancelada':    'background:#fff5f5;color:#e53935;border:1px solid #fecaca;',
};

function verDetalleActivacion(act) {
  document.getElementById('det-act-titulo').textContent = act.titulo || '—';
  const estBadge = document.getElementById('det-act-estatus-badge');
  const estKey = (act.estatus || 'Planificada').toString().toLowerCase();
  estBadge.textContent = act.estatus || 'Planificada';
  estBadge.style.cssText = ESTATUS_STYLES_ACT_M[estKey] || ESTATUS_STYLES_ACT_M['planificada'];
  const tipoBadge = document.getElementById('det-act-tipo-badge');
  tipoBadge.textContent = act.tipo || '—';
  tipoBadge.style.cssText = 'background:#f59e0b20;color:#d97706;border:1px solid #f59e0b50;';
  document.getElementById('det-act-fecha').textContent = act.fecha || act.fecha_actividad || '—';
  document.getElementById('det-act-candidato').textContent = act.candidato ? ('🙋 ' + act.candidato) : 'Sin candidato';
  document.getElementById('det-act-coordinador').textContent = act.coordinador || act.responsable || '—';
  document.getElementById('det-act-municipio').textContent = act.municipio || '—';
  document.getElementById('det-act-seccion').textContent = act.seccion ? `Sección ${act.seccion}` : '—';
  document.getElementById('det-act-estado').textContent = act.estado || '—';
  document.getElementById('det-act-notas').textContent = act.notas || 'Sin notas.';
  document.getElementById('modal-det-act').classList.add('open');
}

function activarSeleccionSeccion() {
    cerrarModal();
    seleccionandoSeccion = true;
    map.getContainer().style.cursor = 'crosshair';
    mostrarToast('Haz clic en una sección DE TU ZONA en el mapa');
}

function actualizarSelectorSeccion() {
    const badge = document.getElementById('seccion-badge-text');
    if (seccionParaEvento && seccionParaEvento.num) {
        badge.className = 'seccion-badge';
        badge.innerHTML = `<i class="fas fa-map-marker-alt"></i> Sección ${seccionParaEvento.num}`;
        document.getElementById('m-estado').value = seccionParaEvento.estado || '';
        document.getElementById('m-municipio').value = seccionParaEvento.municipio || '';
    } else {
        badge.className = 'seccion-badge empty';
        badge.innerHTML = `<i class="fa-solid fa-map-marker-alt"></i> Selecciona una sección de tu Zona`;
        document.getElementById('m-estado').value = '';
        document.getElementById('m-municipio').value = '';
    }
}

async function guardarEvento() {
    const titulo = document.getElementById('m-titulo').value.trim();
    const fecha  = document.getElementById('m-fecha').value;
    const btn    = document.getElementById('m-save-btn');

    if (!titulo) { mostrarToast('El título es obligatorio'); return; }
    if (!fecha)  { mostrarToast('La fecha es obligatoria'); return; }
    if (!ejeSeleccionadoM) { mostrarToast('Selecciona un eje'); return; }
    if (!seccionParaEvento) { mostrarToast('Selecciona una sección en el mapa'); return; }

    const ini = document.getElementById('m-inicio').value;
    const fin = document.getElementById('m-fin').value;
    let horas = parseFloat(document.getElementById('m-horas').value) || 0;
    if (!horas && ini && fin) {
        const [hi,mi] = ini.split(':').map(Number), [hf,mf] = fin.split(':').map(Number);
        horas = Math.max(0, (hf*60+mf - hi*60-mi) / 60);
    }
    if (!horas) horas = 1;

    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando…';
    btn.disabled = true;

    const fd = new FormData();
    fd.append('action', 'create');
    fd.append('titulo', titulo);
    fd.append('fecha', fecha);
    fd.append('eje', ejeSeleccionadoM);
    fd.append('subtipo', document.getElementById('m-subtipo').value);
    fd.append('seccion', seccionParaEvento.num);
    fd.append('estado', seccionParaEvento.estado || '');
    fd.append('municipio', seccionParaEvento.municipio || '');
    fd.append('inicio', ini);
    fd.append('fin', fin);
    fd.append('horas', horas);
    fd.append('responsable', document.getElementById('m-responsable').value.trim());
    fd.append('notas', document.getElementById('m-notas').value.trim());
    if (seccionParaEvento.latlng) {
        fd.append('lat', seccionParaEvento.latlng.lat ?? seccionParaEvento.latlng[0] ?? '');
        fd.append('lng', seccionParaEvento.latlng.lng ?? seccionParaEvento.latlng[1] ?? '');
    }

    try {
        const res = await fetch(AGENDA_SAVE_URL, { method: 'POST', body: fd });
        const data = await res.json();
        if (!data.ok) throw new Error(data.msg || 'Error al guardar');
        await cargarYPintarEventos();
        cerrarModal();
        mostrarToast(`Evento programado con éxito`);
    } catch(err) {
        mostrarToast('Error: ' + err.message);
    } finally {
        btn.innerHTML = '<i class="fas fa-check"></i> Guardar Evento';
        btn.disabled = false;
    }
}

function verDetalleEvento(ev) {
    document.getElementById('det-titulo').textContent = ev.titulo || '—';
    const ej = ev.eje || 'voto';
    document.getElementById('det-eje-badge').textContent = ej;
    document.getElementById('det-subtipo').textContent = ev.subtipo || '—';
    document.getElementById('det-fecha').textContent = ev.fecha || '—';
    document.getElementById('det-estado').textContent = ev.estado || '—';
    document.getElementById('det-municipio').textContent = ev.municipio || '—';
    document.getElementById('det-seccion').textContent = ev.seccion ? `Sección ${ev.seccion}` : '—';
    document.getElementById('det-responsable').textContent = ev.responsable || '—';
    document.getElementById('det-horas').textContent = ev.horas ? ev.horas + 'h' : '—';
    document.getElementById('det-notas').textContent = ev.notas || 'Sin notas.';
    document.getElementById('modal-det').classList.add('open');
}


function colocarMarkerEvento(ev) {
    let latlng = null;
    if (ev.lat && ev.lng) {
        latlng = [parseFloat(ev.lat), parseFloat(ev.lng)];
    } else if (layerSecciones) {
        layerSecciones.eachLayer(layer => {
            if (latlng) return;
            try { if (String(layer.feature?.properties.seccion) === String(ev.seccion)) latlng = layer.getBounds().getCenter(); } catch(_) {}
        });
    }
    if (!latlng) return;
    // Normalizar: getCenter() devuelve L.LatLng (.lat/.lng), no un array
    const _lat = latlng.lat !== undefined ? latlng.lat : latlng[0];
    const _lng = latlng.lng !== undefined ? latlng.lng : latlng[1];
    latlng = jitteredLatLng(_lat, _lng, _eventoOffsets);

    const color = { voto: '#1a7a3c', comunicacion: '#2196f3', organizacion: '#9c27b0' }[ev.eje] || '#1a7a3c';
    const iconHtml = `
      <div class="evento-pin">
        <div class="evento-pin-icon" style="background:${color};"><i class="fa-solid fa-location-dot"></i></div>
        <div class="evento-pin-label">SEC.${ev.seccion || '?'}</div>
      </div>`;

    const marker = L.marker(latlng, {
        icon: L.divIcon({ className: 'evento-marker', html: iconHtml, iconSize: [40, 44], iconAnchor: [13, 38] }),
        interactive: true
    });

    const popupHtml = 
        `<div style="font-family:IBM Plex Sans,sans-serif;min-width:160px;">` +
        `<b style="font-size:13px;display:block;margin-bottom:4px;">${ev.titulo || '—'}</b>` +
        `<span style="font-family:IBM Plex Mono,monospace;font-size:11px;color:${color};">Sección ${ev.seccion || '?'}</span>` +
        `<br><button onclick="verDetalleEvento(${JSON.stringify(ev).replace(/"/g,'&quot;')})" style="margin-top:8px;padding:5px 10px;background:${color};color:white;border:none;border-radius:6px;font-size:11px;font-weight:700;cursor:pointer;">Ver detalle</button></div>`;
    marker.bindPopup(popupHtml, { maxWidth: 260 });
    layerEventos.addLayer(marker);
}

cargarYPintarEventos();
cargarYPintarActivaciones();

// ──────────────────────────────────────────────
// ACTIVACIONES EN MAPA
// ──────────────────────────────────────────────
async function cargarYPintarActivaciones() {
    try {
        const res  = await fetch('?action=list_activaciones');
        const data = await res.json();
        if (!data.ok) return;
        activacionesData = data.activaciones || [];
        repintarActivaciones();
        actualizarBadgesFiltrados();
    } catch(e) { console.warn('activaciones error:', e); }
}

function repintarActivaciones() {
    _activacionOffsets = {};
    layerActivaciones.clearLayers();
    activacionesFiltradas().forEach(act => colocarMarkerActivacion(act));
}

function toggleCapaActivaciones() {
    capasVisiblesActivaciones = !capasVisiblesActivaciones;
    document.getElementById('toggle-activaciones').classList.toggle('on', capasVisiblesActivaciones);
    capasVisiblesActivaciones ? map.addLayer(layerActivaciones) : map.removeLayer(layerActivaciones);
}

function colocarMarkerActivacion(act) {
    let latlng = null;
    // Prioridad: centroide del polígono real de la sección (igual que en
    // Estatal y Regional), para que el marcador no dependa de un clic
    // impreciso en el mapa del formulario.
    if (act.seccion && layerSecciones) {
        layerSecciones.eachLayer(layer => {
            if (latlng) return;
            try { if (String(layer.feature?.properties.seccion) === String(act.seccion)) latlng = layer.getBounds().getCenter(); } catch(_) {}
        });
    }
    // Respaldo: coordenadas guardadas, solo si no se pudo ubicar el polígono.
    if (!latlng && act.latitud && act.longitud) {
        latlng = [parseFloat(act.latitud), parseFloat(act.longitud)];
    }
    if (!latlng) return;
    // Normalizar: getCenter() devuelve L.LatLng (.lat/.lng), no un array
    const _lat = latlng.lat !== undefined ? latlng.lat : latlng[0];
    const _lng = latlng.lng !== undefined ? latlng.lng : latlng[1];
    latlng = jitteredLatLng(_lat, _lng, _activacionOffsets);

    // Color por tipo de activacion
    const colorAct = '#e65100'; // naranja oscuro para activaciones
    const iconHtml = `
      <div class="evento-pin">
        <div class="evento-pin-icon" style="background:${colorAct};"><i class="fa-solid fa-bolt"></i></div>
        <div class="evento-pin-label">SEC.${act.seccion || '?'}</div>
      </div>`;

    const marker = L.marker(latlng, {
        icon: L.divIcon({ className: 'evento-marker', html: iconHtml, iconSize: [40, 44], iconAnchor: [13, 38] }),
        interactive: true
    });

    const estatusColor = { 'Completada':'#2d7a3e','En Progreso':'#1a73e8','Planificada':'#ff9800','Cancelada':'#d32f2f' }[act.estatus] || colorAct;
    const popupHtml =
        `<div style="font-family:IBM Plex Sans,sans-serif;min-width:180px;">` +
        `<b style="font-size:13px;display:block;margin-bottom:4px;">${act.titulo || '—'}</b>` +
        `<span style="font-family:IBM Plex Mono,monospace;font-size:11px;color:${colorAct};display:block;margin-bottom:4px;">Sección ${act.seccion || '?'}</span>` +
        (act.tipo ? `<span style="font-size:10px;color:#666;display:block;">Tipo: ${act.tipo}</span>` : '') +
        (act.estatus ? `<span style="font-size:10px;font-weight:700;color:${estatusColor};display:block;margin-top:2px;">${act.estatus}</span>` : '') +
        (act.fecha ? `<span style="font-size:10px;color:#888;display:block;margin-top:2px;">${act.fecha}</span>` : '') +
        `<button onclick="verDetalleActivacion(${JSON.stringify(act).replace(/"/g,'&quot;')})" style="margin-top:8px;padding:5px 10px;background:${colorAct};color:white;border:none;border-radius:6px;font-size:11px;font-weight:700;cursor:pointer;font-family:IBM Plex Sans,sans-serif;">Ver detalle</button>` +
        `</div>`;
    marker.bindPopup(popupHtml, { maxWidth: 260 });
    layerActivaciones.addLayer(marker);
}

// ──────────────────────────────────────────────
// BD Stats Secciones
// ──────────────────────────────────────────────
async function cargarDatosSeccion(numSeccion) {
    document.getElementById('db-panel').style.display = 'none';
    document.getElementById('db-no-data').style.display = 'none';
    document.getElementById('db-spinner').style.display = 'block';
    
    const fmt = n => (n != null && n !== '' && !isNaN(n)) ? parseInt(n).toLocaleString('es-MX') : '—';
    const pct = (c, m) => (!m || m == 0) ? 0 : Math.min(100, Math.round(c / m * 100));

    try {
        const estadoParam = ESTADO_CONFIG.label ? `&estado=${encodeURIComponent(ESTADO_CONFIG.label)}` : '';
        const res = await fetch(`?action=get_seccion_detalle&seccion=${encodeURIComponent(numSeccion)}${estadoParam}`);
        const data = await res.json();
        document.getElementById('db-spinner').style.display = 'none';

        if (!data.ok || !data.seccion) {
            document.getElementById('db-no-data').style.display = 'block';
            return;
        }

        const s = data.seccion;
        document.getElementById('db-region').textContent = s.region_asignada || '—';
        document.getElementById('db-zona').textContent   = s.zona_asignada   || '—';
        document.getElementById('db-nominal').textContent = fmt(s.lista_nominal);
        document.getElementById('db-hombres').textContent = fmt(s.lista_hombres);
        document.getElementById('db-mujeres').textContent = fmt(s.lista_mujeres);
        
        const edadRows = [['18-24', s.grupo_edad_18_24], ['25-34', s.grupo_edad_25_34], ['35-44', s.grupo_edad_35_44], ['45-54', s.grupo_edad_45_54], ['55+', s.grupo_edad_55_mas]].filter(([, v]) => v > 0);
        document.getElementById('db-edad-chips').innerHTML = edadRows.map(([l, v]) => `<span class="edad-chip">${l}: ${fmt(v)}</span>`).join('');

        const meta = parseInt(s.meta_contactos) || 0;
        const cons = parseInt(s.contactos_conseguidos) || 0;
        const p = pct(cons, meta);
        
        document.getElementById('db-meta').textContent = fmt(meta);
        document.getElementById('db-contactos').textContent = fmt(cons);
        document.getElementById('db-progress-fill').style.width = p + '%';
        document.getElementById('db-pct').textContent = p + '%';
        document.getElementById('db-rem').textContent = fmt(Math.max(0, meta - cons)) + ' rest.';

        document.getElementById('db-eventos').textContent = fmt(s.eventos_realizados);
        document.getElementById('db-votos').textContent = fmt(s.metas_votos);

        document.getElementById('db-act-total').textContent = fmt(s.activaciones_totales);
        document.getElementById('db-act-con').textContent = fmt(s.con_candidato);
        document.getElementById('db-act-sin').textContent = fmt(s.sin_candidato);

        const setOpen = (wrapId, textId, val) => {
            const ok = val && val.trim();
            document.getElementById(wrapId).style.display = ok ? 'block' : 'none';
            if (ok) document.getElementById(textId).textContent = val;
        };
        setOpen('db-nec-wrap', 'db-necesidades', s.principales_necesidades);
        setOpen('db-enf-wrap', 'db-enfoque',     s.enfoque);
        setOpen('db-per-wrap', 'db-perfil',      s.perfil_socioeconomico);

        document.getElementById('db-panel').style.display = 'block';

    } catch (err) {
        document.getElementById('db-spinner').style.display = 'none';
        document.getElementById('db-no-data').style.display = 'block';
    }
}

function mostrarToast(msg) {
    let t = document.getElementById('toast-ev');
    if (!t) {
        t = document.createElement('div');
        t.id = 'toast-ev';
        t.style.cssText = `position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:#1a1e2a;color:#fff;font-size:12px;padding:8px 16px;border-radius:6px;z-index:9999;opacity:0;transition:opacity 0.2s;white-space:nowrap;`;
        document.body.appendChild(t);
    }
    t.textContent = msg; t.style.opacity = '1';
    clearTimeout(t._timer);
    t._timer = setTimeout(() => { t.style.opacity = '0'; }, 3000);
}
</script>

<div class="modal-overlay" id="modal-overlay" onclick="if(event.target===this)cerrarModal()">
  <div class="modal">
    <button class="modal-close" onclick="cerrarModal()"><i class="fas fa-times"></i></button>
    <h2 class="modal-title">Registrar Evento Territorial</h2>
    <p class="modal-subtitle">Asigna una actividad en tu zona de responsabilidad (selecciona en el mapa).</p>

    <div class="form-group" style="margin-bottom:20px;">
      <label class="form-label">Sección Electoral (De tu zona)</label>
      <div id="seccion-badge-wrap">
        <span class="seccion-badge empty" id="seccion-badge-text">
          <i class="fa-solid fa-map-marker-alt"></i> Ninguna sección seleccionada
        </span>
      </div>
      <div style="margin-top:6px;">
        <button type="button" onclick="activarSeleccionSeccion()" style="font-size:11px;color:var(--verde);background:none;border:none;cursor:pointer;padding:0;font-weight:700;">
          <i class="fas fa-crosshairs"></i> Haz clic aquí para mapear una nueva sección
        </button>
      </div>
    </div>

    <div class="form-group" style="margin-bottom:20px;">
      <label class="form-label">Eje Estratégico *</label>
      <div class="eje-selector-ag" style="margin-top:5px;">
        <button class="eje-btn-ag" data-eje="voto"         onclick="seleccionarEjeM('voto')"         type="button">🗳️ Voto / Territorio</button>
        <button class="eje-btn-ag" data-eje="comunicacion" onclick="seleccionarEjeM('comunicacion')" type="button">📣 Comunicación</button>
        <button class="eje-btn-ag" data-eje="organizacion" onclick="seleccionarEjeM('organizacion')" type="button">⚙️ Organización</button>
      </div>
    </div>

    <div class="form-group" style="margin-bottom:20px;">
      <label class="form-label">Tipo de Actividad</label>
      <select id="m-subtipo" class="form-input-ag"><option value="">— Selecciona un eje primero —</option></select>
    </div>

    <div class="form-group" style="margin-bottom:20px;">
      <label class="form-label">Título del evento *</label>
      <input type="text" id="m-titulo" class="form-input-ag" placeholder="Ej. Reunión Vecinal con Estructura">
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:20px;">
      <div class="form-group"><label class="form-label">Fecha *</label><input type="date" id="m-fecha" class="form-input-ag"></div>
      <div class="form-group"><label class="form-label">Estado</label><input type="text" id="m-estado" class="form-input-ag" readonly style="background:#f0f4f0;"></div>
      <div class="form-group"><label class="form-label">Municipio</label><input type="text" id="m-municipio" class="form-input-ag" readonly style="background:#f0f4f0;"></div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:20px;">
      <div class="form-group"><label class="form-label">Hora Inicio</label><input type="time" id="m-inicio" class="form-input-ag"></div>
      <div class="form-group"><label class="form-label">Hora Fin</label><input type="time" id="m-fin" class="form-input-ag" onchange="calcHorasM()"></div>
      <div class="form-group"><label class="form-label">Total Horas</label><input type="number" id="m-horas" class="form-input-ag" min="0" step="0.5"></div>
    </div>

    <div class="form-group" style="margin-bottom:20px;">
      <label class="form-label">Enlace / Responsable</label>
      <input type="text" id="m-responsable" class="form-input-ag" placeholder="Nombre de quien reporta o coordina">
    </div>

    <div class="form-group" style="margin-bottom:20px;">
      <label class="form-label">Notas Adicionales</label>
      <textarea id="m-notas" class="form-input-ag" placeholder="Observaciones, necesidades del evento..."></textarea>
    </div>

    <div class="modal-footer-ag">
      <div></div>
      <div style="display:flex;gap:10px;">
        <button class="btn-cancel-ag" onclick="cerrarModal()">Cancelar</button>
        <button class="btn-save-ag" id="m-save-btn" onclick="guardarEvento()">
          <i class="fas fa-check"></i> Guardar Evento
        </button>
      </div>
    </div>
  </div>
</div>

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
      <strong style="font-size:11px;color:var(--texto-dim);text-transform:uppercase;">Notas</strong>
      <div id="det-notas" style="margin-top:8px;font-size:14px;color:#555;line-height:1.6;background:var(--gris-panel);padding:15px;border-radius:10px;border-left:4px solid var(--verde);">—</div>
    </div>
    <div class="modal-footer-ag">
      <div style="display:flex;gap:10px;">
        <button class="btn-cancel-ag" onclick="cerrarModalDet()">Cerrar</button>
      </div>
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
      <strong style="font-size:11px;color:var(--texto-dim);text-transform:uppercase;">Notas</strong>
      <div id="det-act-notas" style="margin-top:8px;font-size:14px;color:#555;line-height:1.6;background:var(--gris-panel);padding:15px;border-radius:10px;border-left:4px solid #f59e0b;">—</div>
    </div>
    <div class="modal-footer-ag">
      <div style="display:flex;gap:10px;">
        <button class="btn-cancel-ag" onclick="cerrarModalDetAct()">Cerrar</button>
      </div>
    </div>
  </div>
</div>

</body>
</html>