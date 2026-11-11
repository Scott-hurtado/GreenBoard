<?php
/**
 * Mapa Electoral - Multi-Estado
 * Requiere archivos geojson de municipios y secciones por estado en carpeta /geojson/
 */

// ── Handler AJAX: detalle completo de sección (mismo JOIN que secciones.php) ──
if (isset($_GET['action']) && $_GET['action'] === 'get_seccion_detalle') {
    header('Content-Type: application/json');
    if (file_exists(__DIR__ . '/../../includes/bootstrap.php')) {
        require_once __DIR__ . '/../../includes/bootstrap.php';
    } else { session_start(); }
    $rol = $_SESSION['user_role'] ?? '';
    $permitidos = ['admin','administrador','superadmin','super admin','circunscripcion'];
    if (!in_array($rol, $permitidos)) { echo json_encode(['ok'=>false,'msg'=>'Sin permisos']); exit; }

    $numSeccion = trim($_GET['seccion'] ?? '');
    $estadoNombre = trim($_GET['estado'] ?? '');
    if (!$numSeccion) { echo json_encode(['ok'=>false,'msg'=>'Sección requerida']); exit; }

    try {
        if (!isset($pdo)) {
            $dbConfigPath = __DIR__ . '/../../includes/database.php';
            $dbConfig = file_exists($dbConfigPath) ? require $dbConfigPath : [
                'host'=>'localhost','database'=>'u881824383_pverde',
                'username'=>'u881824383_proyverde','password'=>'PProyectoverde8#',
                'charset'=>'utf8mb4',
                'options'=>[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
            ];
            $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
            $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options'] ?? []);
        }

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
        if (!$row) {
            echo json_encode(['ok'=>false,'msg'=>'No encontrada']);
            exit;
        }

        // Defaults seguros
        $row['eventos_realizados']  = 0;
        $row['activaciones_totales']= 0;
        $row['con_candidato']       = 0;
        $row['sin_candidato']       = 0;
        $row['eventos_lista']       = [];
        $row['activaciones_lista']  = [];

        // ── Contar y listar eventos de agenda (tabla puede no existir) ──────
        $row['_debug_agenda'] = 'ok';
        try {
            $s2 = $pdo->prepare("SELECT COUNT(*) FROM agenda_eventos WHERE seccion = :s AND activo = 1");
            $s2->execute([':s' => $numSeccion]);
            $row['eventos_realizados'] = (int)$s2->fetchColumn();

            $s3 = $pdo->prepare("
                SELECT id, titulo, eje, subtipo, fecha, inicio, fin,
                       estado, municipio, responsable, notas
                FROM agenda_eventos
                WHERE seccion = :s AND activo = 1
                ORDER BY fecha DESC, inicio DESC
                LIMIT 20
            ");
            $s3->execute([':s' => $numSeccion]);
            $row['eventos_lista'] = $s3->fetchAll();
        } catch (Exception $e2) { $row['_debug_agenda'] = $e2->getMessage(); }

        // ── Contar y listar activaciones ─────────────────────────────────────
        try {
            $s4 = $pdo->prepare("SELECT COUNT(*) FROM activaciones WHERE seccion_electoral = :s AND activo = 1");
            $s4->execute([':s' => $numSeccion]);
            $row['activaciones_totales'] = (int)$s4->fetchColumn();

            $s5 = $pdo->prepare("SELECT COUNT(*) FROM activaciones WHERE seccion_electoral = :s AND activo = 1 AND candidato IS NOT NULL AND candidato <> ''");
            $s5->execute([':s' => $numSeccion]);
            $row['con_candidato'] = (int)$s5->fetchColumn();

            $row['sin_candidato'] = $row['activaciones_totales'] - $row['con_candidato'];

            $s6 = $pdo->prepare("
                SELECT id, titulo, tipo, estatus, fecha_actividad,
                       coordinador, candidato, contactos_estimados, asistentes_estimados
                FROM activaciones
                WHERE seccion_electoral = :s AND activo = 1
                ORDER BY fecha_actividad DESC
                LIMIT 20
            ");
            $s6->execute([':s' => $numSeccion]);
            $row['activaciones_lista'] = $s6->fetchAll();
        } catch (Exception $e3) { /* tabla activaciones o columna candidato no existe */ }

        echo json_encode(['ok'=>true, 'seccion'=>$row]);
    } catch (Exception $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

if (file_exists(__DIR__ . '/../../includes/bootstrap.php')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
} else { session_start(); }
$rol = $_SESSION['user_role'] ?? '';
$user_circ = $_SESSION['user_circunscripcion'] ?? 'Circunscripción 1';
$permitidos = ['admin','administrador','superadmin','super admin', 'circunscripcion'];
if (!in_array($rol, $permitidos)) { header('Location: /public/index.php'); exit(); }

// ── Claves de estados por circunscripción (coinciden con las keys de $estados) ──
$CIRCS_KEYS = [
    'Circunscripción 1' => ['sonora','bc','bcs','chihuahua','durango','jalisco','nayarit','sinaloa'],
    'Circunscripción 2' => ['aguascalientes','coahuila','guanajuato','nuevo-leon','san-luis-potosi','tamaulipas','zacatecas'],
    'Circunscripción 3' => ['campeche','chiapas','oaxaca','quintana-roo','tabasco','veracruz','yucatan'],
    'Circunscripción 4' => ['ciudad-de-mexico','guerrero','hidalgo','morelos','puebla','tlaxcala'],
    'Circunscripción 5' => ['colima','estado-de-mexico','michoacan','queretaro'],
];
$keys_circ = $CIRCS_KEYS[$user_circ] ?? [];

$geojsonPath = __DIR__ . '/../../geojson/';

// ── Catálogo COMPLETO de los 32 estados con sus archivos geojson y centroides ──
$todos_estados = [
    // Circunscripción 1
    'sonora'           => ['label'=>'Sonora',            'municipioFile'=>'municipio.geojson',                  'seccionFile'=>'seccion.geojson',                  'centroide'=>[29.5,  -110.5],  'zoom'=>7],
    'bc'               => ['label'=>'Baja California',   'municipioFile'=>'municipio-bc.geojson',               'seccionFile'=>'seccion-bc.geojson',               'centroide'=>[30.5,  -115.5],  'zoom'=>7],
    'bcs'              => ['label'=>'Baja California Sur','municipioFile'=>'municipio-bcs.geojson',              'seccionFile'=>'seccion-bcs.geojson',              'centroide'=>[24.5,  -111.5],  'zoom'=>7],
    'chihuahua'        => ['label'=>'Chihuahua',         'municipioFile'=>'municipio-chihuahua.geojson',        'seccionFile'=>'seccion-chihuahua.geojson',        'centroide'=>[28.6,  -106.1],  'zoom'=>7],
    'durango'          => ['label'=>'Durango',           'municipioFile'=>'municipio-durango.geojson',          'seccionFile'=>'seccion-durango.geojson',          'centroide'=>[24.5,  -104.7],  'zoom'=>7],
    'sinaloa'          => ['label'=>'Sinaloa',           'municipioFile'=>'municipio-sinaloa.geojson',          'seccionFile'=>'seccion-sinaloa.geojson',          'centroide'=>[24.8,  -107.4],  'zoom'=>7],
    'nayarit'          => ['label'=>'Nayarit',           'municipioFile'=>'municipio-nayarit.geojson',          'seccionFile'=>'seccion-nayarit.geojson',          'centroide'=>[21.75, -104.85], 'zoom'=>8],
    'jalisco'          => ['label'=>'Jalisco',           'municipioFile'=>'municipio-jalisco.geojson',          'seccionFile'=>'seccion-jalisco.geojson',          'centroide'=>[20.6,  -103.3],  'zoom'=>7],
    // Circunscripción 2
    'aguascalientes'   => ['label'=>'Aguascalientes',    'municipioFile'=>'municipio-aguascalientes.geojson',   'seccionFile'=>'seccion-aguascalientes.geojson',   'centroide'=>[21.88, -102.29], 'zoom'=>9],
    'coahuila'         => ['label'=>'Coahuila',          'municipioFile'=>'municipio-coahuila.geojson',         'seccionFile'=>'seccion-coahuila.geojson',         'centroide'=>[27.3,  -102.0],  'zoom'=>7],
    'guanajuato'       => ['label'=>'Guanajuato',        'municipioFile'=>'municipio-guanajuato.geojson',       'seccionFile'=>'seccion-guanajuato.geojson',       'centroide'=>[21.0,  -101.25], 'zoom'=>8],
    'nuevo-leon'       => ['label'=>'Nuevo León',        'municipioFile'=>'municipio-nuevo-leon.geojson',       'seccionFile'=>'seccion-nuevo-leon.geojson',       'centroide'=>[25.5,  -99.9],   'zoom'=>7],
    'san-luis-potosi'  => ['label'=>'San Luis Potosí',   'municipioFile'=>'municipio-san-luis-potosi.geojson',  'seccionFile'=>'seccion-san-luis-potosi.geojson',  'centroide'=>[22.2,  -100.98], 'zoom'=>7],
    'tamaulipas'       => ['label'=>'Tamaulipas',        'municipioFile'=>'municipio-tamaulipas.geojson',       'seccionFile'=>'seccion-tamaulipas.geojson',       'centroide'=>[24.3,  -98.8],   'zoom'=>7],
    'zacatecas'        => ['label'=>'Zacatecas',         'municipioFile'=>'municipio-zacatecas.geojson',        'seccionFile'=>'seccion-zacatecas.geojson',        'centroide'=>[23.0,  -102.5],  'zoom'=>7],
    // Circunscripción 3
    'campeche'         => ['label'=>'Campeche',          'municipioFile'=>'municipio-campeche.geojson',         'seccionFile'=>'seccion-campeche.geojson',         'centroide'=>[18.9,  -90.2],   'zoom'=>7],
    'chiapas'          => ['label'=>'Chiapas',           'municipioFile'=>'municipio-chiapas.geojson',          'seccionFile'=>'seccion-chiapas.geojson',          'centroide'=>[16.75, -92.6],   'zoom'=>7],
    'oaxaca'           => ['label'=>'Oaxaca',            'municipioFile'=>'municipio-oaxaca.geojson',           'seccionFile'=>'seccion-oaxaca.geojson',           'centroide'=>[17.0,  -96.7],   'zoom'=>7],
    'quintana-roo'     => ['label'=>'Quintana Roo',      'municipioFile'=>'municipio-quintana-roo.geojson',     'seccionFile'=>'seccion-quintana-roo.geojson',     'centroide'=>[19.5,  -88.0],   'zoom'=>7],
    'tabasco'          => ['label'=>'Tabasco',           'municipioFile'=>'municipio-tabasco.geojson',          'seccionFile'=>'seccion-tabasco.geojson',          'centroide'=>[18.0,  -92.8],   'zoom'=>8],
    'veracruz'         => ['label'=>'Veracruz',          'municipioFile'=>'municipio-veracruz.geojson',         'seccionFile'=>'seccion-veracruz.geojson',         'centroide'=>[19.5,  -96.9],   'zoom'=>7],
    'yucatan'          => ['label'=>'Yucatán',           'municipioFile'=>'municipio-yucatan.geojson',          'seccionFile'=>'seccion-yucatan.geojson',          'centroide'=>[20.7,  -89.1],   'zoom'=>8],
    // Circunscripción 4
    'ciudad-de-mexico' => ['label'=>'Ciudad de México',  'municipioFile'=>'municipio-ciudad-de-mexico.geojson', 'seccionFile'=>'seccion-ciudad-de-mexico.geojson', 'centroide'=>[19.43, -99.13],  'zoom'=>10],
    'guerrero'         => ['label'=>'Guerrero',          'municipioFile'=>'municipio-guerrero.geojson',         'seccionFile'=>'seccion-guerrero.geojson',         'centroide'=>[17.55, -99.5],   'zoom'=>7],
    'hidalgo'          => ['label'=>'Hidalgo',           'municipioFile'=>'municipio-hidalgo.geojson',          'seccionFile'=>'seccion-hidalgo.geojson',          'centroide'=>[20.5,  -98.8],   'zoom'=>8],
    'morelos'          => ['label'=>'Morelos',           'municipioFile'=>'municipio-morelos.geojson',          'seccionFile'=>'seccion-morelos.geojson',          'centroide'=>[18.7,  -99.1],   'zoom'=>9],
    'puebla'           => ['label'=>'Puebla',            'municipioFile'=>'municipio-puebla.geojson',           'seccionFile'=>'seccion-puebla.geojson',           'centroide'=>[19.0,  -97.9],   'zoom'=>8],
    'tlaxcala'         => ['label'=>'Tlaxcala',          'municipioFile'=>'municipio-tlaxcala.geojson',         'seccionFile'=>'seccion-tlaxcala.geojson',         'centroide'=>[19.32, -98.23],  'zoom'=>9],
    // Circunscripción 5
    'colima'           => ['label'=>'Colima',            'municipioFile'=>'municipio-colima.geojson',           'seccionFile'=>'seccion-colima.geojson',           'centroide'=>[19.12, -103.72], 'zoom'=>9],
    'estado-de-mexico' => ['label'=>'Estado de México',  'municipioFile'=>'municipio-estado-de-mexico.geojson', 'seccionFile'=>'seccion-estado-de-mexico.geojson', 'centroide'=>[19.35, -99.65],  'zoom'=>8],
    'michoacan'        => ['label'=>'Michoacán',         'municipioFile'=>'municipio-michoacan.geojson',        'seccionFile'=>'seccion-michoacan.geojson',        'centroide'=>[19.0,  -101.9],  'zoom'=>7],
    'queretaro'        => ['label'=>'Querétaro',         'municipioFile'=>'municipio-queretaro.geojson',        'seccionFile'=>'seccion-queretaro.geojson',        'centroide'=>[20.6,  -100.4],  'zoom'=>8],
];

// ── Filtrar solo los estados de la circunscripción del usuario ──
$estados = empty($keys_circ)
    ? $todos_estados
    : array_intersect_key($todos_estados, array_flip($keys_circ));

// Cargar datos de cada estado
// Municipios: se pre-cargan en PHP (archivos pequeños ~1-7MB)
// Secciones: se pasan como rutas para fetch lazy en el cliente (archivos grandes)
$estadosData = [];
foreach ($estados as $key => $cfg) {
    $munFile = $geojsonPath . $cfg['municipioFile'];

    $munData = file_exists($munFile) ? json_decode(file_get_contents($munFile), true) : null;

    $seccionUrl = null;
    if ($cfg['seccionFile']) {
        $seccionUrl = '../../geojson/' . $cfg['seccionFile'];
    }

    $distFedNombres = ['bc'=>'baja-california','bcs'=>'baja-california-sur'];
    $distFedUrl = '../../geojson/distrito-federal-' . ($distFedNombres[$key] ?? $key) . '.geojson';
    $distLocUrl = '../../geojson/distrito-local-'   . ($distFedNombres[$key] ?? $key) . '.geojson';
    $coloniaFilesMap = [
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
    $cf = $coloniaFilesMap[$key] ?? null;
    if ($cf) $coloniaUrl = '../../geojson/' . $cf;

    $estadosData[$key] = [
        'label'      => $cfg['label'],
        'centroide'  => $cfg['centroide'],
        'zoom'       => $cfg['zoom'],
        'municipio'  => $munData,
        'seccionUrl' => $seccionUrl,
        'distFedUrl' => $distFedUrl,
        'distLocUrl' => $distLocUrl,
        'coloniaUrl' => $coloniaUrl,
    ];
}

/* ══════════════════════════════════════════════════════════════════
   MODO VISTA — parámetros GET enviados desde zonas_circ.php
══════════════════════════════════════════════════════════════════ */
$modoVista      = (isset($_GET['modo_vista']) && $_GET['modo_vista'] === '1')
               || (isset($_GET['zona_id'])    && (int)$_GET['zona_id'] > 0);
$vistZonaId     = $modoVista ? (int)($_GET['zona_id']     ?? 0) : 0;
$vistZonaNombre = $modoVista ? htmlspecialchars(trim($_GET['zona_nombre'] ?? '')) : '';
$vistReturnUrl  = $modoVista ? htmlspecialchars(trim($_GET['return_url']  ?? '')) : '';
$seccionesZona  = [];
$vistEstadoKey  = '';
$zoomEntidad    = trim($_GET['zoom_entidad'] ?? '');
$zoomTipo       = trim($_GET['zoom_tipo'] ?? 'municipios');
if (!in_array($zoomTipo, ['municipios','distrito_local','distrito_federal'], true)) $zoomTipo = 'municipios';

if ($modoVista && $vistZonaId) {
    try {
        $st = $pdo->prepare("SELECT z.secciones, r.estado FROM zonas_estatales z JOIN regiones_estatales r ON r.id = z.estructura_id WHERE z.id = ? LIMIT 1");
        $st->execute([$vistZonaId]);
        $zr = $st->fetch();
        if ($zr) {
            $seccionesZona = json_decode($zr['secciones'] ?? '[]', true) ?: [];
            $estadoKeyMap = [
                'Sonora'=>'sonora','Chihuahua'=>'chihuahua','Sinaloa'=>'sinaloa',
                'Jalisco'=>'jalisco','Durango'=>'durango','Nayarit'=>'nayarit',
                'Baja California'=>'bc','Baja California Sur'=>'bcs',
                'Aguascalientes'=>'aguascalientes','Coahuila'=>'coahuila',
                'Guanajuato'=>'guanajuato','Nuevo León'=>'nuevo-leon',
                'San Luis Potosí'=>'san-luis-potosi','Tamaulipas'=>'tamaulipas',
                'Zacatecas'=>'zacatecas','Campeche'=>'campeche','Chiapas'=>'chiapas',
                'Oaxaca'=>'oaxaca','Quintana Roo'=>'quintana-roo','Tabasco'=>'tabasco',
                'Veracruz'=>'veracruz','Yucatán'=>'yucatan',
                'Ciudad de México'=>'ciudad-de-mexico','Guerrero'=>'guerrero',
                'Hidalgo'=>'hidalgo','Morelos'=>'morelos','Puebla'=>'puebla',
                'Tlaxcala'=>'tlaxcala','Colima'=>'colima',
                'Estado de México'=>'estado-de-mexico','Michoacán'=>'michoacan',
                'Querétaro'=>'queretaro',
            ];
            $vistEstadoKey = $estadoKeyMap[$zr['estado']] ?? '';
        }
    } catch (Exception $e) {}
}
$seccionesZonaJson = json_encode(array_values(array_map('strval', $seccionesZona)));

$estadosJson = json_encode($estadosData);

/* ── Activaciones de los estados de la circunscripción (para capa del mapa) ── */
$activacionesMapa = [];
if (isset($pdo) && !empty($estadosData)) {
    try {
        $labelsCirc = array_values(array_map(function($cfg){ return $cfg['label']; }, $estadosData));
        // Incluir variantes sin acento (la BD guarda p.ej. 'Michoacan' sin acento)
        $sinAcentos = array_map(function($l){
            return strtr($l, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','ñ'=>'n','Ñ'=>'N']);
        }, $labelsCirc);
        $labelsCirc = array_values(array_unique(array_merge($labelsCirc, $sinAcentos)));
        $inAct = implode(',', array_fill(0, count($labelsCirc), '?'));
        try {
            $stmtAct = $pdo->prepare("
                SELECT id, titulo, tipo, estatus, estado, municipio,
                       DATE_FORMAT(fecha_actividad,'%Y-%m-%d') AS fecha,
                       coordinador, seccion_electoral AS seccion,
                       candidato,
                       latitud AS lat, longitud AS lng
                FROM activaciones
                WHERE activo = 1 AND estado IN ($inAct)
                ORDER BY fecha_actividad DESC
            ");
            $stmtAct->execute($labelsCirc);
            $activacionesMapa = $stmtAct->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $eAct) {
            // Fallback sin columna candidato
            $stmtAct2 = $pdo->prepare("
                SELECT id, titulo, tipo, estatus, estado, municipio,
                       DATE_FORMAT(fecha_actividad,'%Y-%m-%d') AS fecha,
                       coordinador, seccion_electoral AS seccion,
                       NULL AS candidato,
                       latitud AS lat, longitud AS lng
                FROM activaciones
                WHERE activo = 1 AND estado IN ($inAct)
                ORDER BY fecha_actividad DESC
            ");
            $stmtAct2->execute($labelsCirc);
            $activacionesMapa = $stmtAct2->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) { $activacionesMapa = []; }
}

// ── Estructura regiones → zonas por estado (para el panel Pintar Zona) ──
$estructuraZonasPorEstado = [];
if (isset($pdo) && !empty($estadosData)) {
    try {
        foreach ($estadosData as $key => $cfg) {
            $estadoLabel = $cfg['label'];
            $stmtR = $pdo->prepare("SELECT id, nombre FROM regiones_estatales WHERE estado = ? ORDER BY nombre");
            $stmtR->execute([$estadoLabel]);
            $regs = $stmtR->fetchAll(PDO::FETCH_ASSOC);
            $regArr = [];
            foreach ($regs as $reg) {
                $stmtZ = $pdo->prepare("SELECT nombre, secciones FROM zonas_estatales WHERE estructura_id = ? ORDER BY nombre");
                $stmtZ->execute([$reg['id']]);
                $zonasArr = [];
                foreach ($stmtZ->fetchAll(PDO::FETCH_ASSOC) as $z) {
                    $zonasArr[] = [
                        'nombre'    => $z['nombre'],
                        'secciones' => json_decode($z['secciones'] ?? '[]', true) ?: [],
                    ];
                }
                $regArr[] = ['id' => (int)$reg['id'], 'nombre' => $reg['nombre'], 'zonas' => $zonasArr];
            }
            $estructuraZonasPorEstado[$estadoLabel] = $regArr;
        }
    } catch (Exception $e) { $estructuraZonasPorEstado = []; }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cartografía Electoral - Admin</title>

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

    /* Header */
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

    .btn-evento {
      display: flex;
      align-items: center;
      gap: 7px;
      background: var(--verde);
      color: white;
      font-size: 11px;
      font-weight: 600;
      padding: 0 14px;
      height: 32px;
      border-radius: 5px;
      border: none;
      cursor: pointer;
      letter-spacing: 0.04em;
      font-family: 'IBM Plex Sans', sans-serif;
      transition: background 0.15s;
    }
    .btn-evento:hover { background: var(--verde-hover); }
    .btn-evento i { font-size: 12px; }

    /* ── Modal estilo Agenda ── */
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
    .form-label {
      font-size: 12px; font-weight: 700; text-transform: uppercase;
      color: var(--texto-dim); letter-spacing: 0.5px;
    }
    .form-input-ag {
      width: 100%; padding: 12px 15px; border-radius: 10px;
      border: 2px solid var(--gris-borde); background: var(--gris-panel);
      font-size: 14px; font-weight: 600; color: var(--texto);
      transition: all 0.3s; outline: none; appearance: none;
      font-family: 'IBM Plex Sans', sans-serif;
    }
    .form-input-ag:focus { border-color: var(--verde); background: var(--blanco); }
    textarea.form-input-ag { resize: vertical; min-height: 90px; font-weight: 400; }

    .eje-selector-ag { display: grid; grid-template-columns: repeat(3,1fr); gap: 8px; }

    /* ── Estilos unificados con agenda_circ ── */
    .eje-btn-ag {
      padding:10px 8px; border-radius:10px; border:2px solid #e5e7eb;
      font-size:11px; font-weight:700; text-align:center; cursor:pointer;
      background:#f9fafb; color:#6b7280; transition:all 0.15s; font-family:inherit;
    }
    .eje-btn-ag.sel-voto        { background:rgba(45,122,62,0.12);  border-color:#2d7a3e; color:#2d7a3e; }
    .eje-btn-ag.sel-comunicacion{ background:rgba(26,115,232,0.12); border-color:#1a73e8; color:#1a73e8; }
    .eje-btn-ag.sel-organizacion{ background:rgba(139,0,212,0.12);  border-color:#8b00d4; color:#8b00d4; }
    .eje-btn-ag {
      padding: 10px 6px; border-radius: 10px; border: 2px solid var(--gris-borde);
      font-size: 11px; font-weight: 700; cursor: pointer; text-align: center;
      background: var(--gris-panel); color: var(--texto-dim);
      transition: 0.2s; font-family: 'IBM Plex Sans', sans-serif;
    }
    .eje-btn-ag.sel-voto         { background: rgba(26,122,60,.12);  border-color: var(--verde);   color: var(--verde); }
    .eje-btn-ag.sel-comunicacion { background: rgba(33,150,243,.12); border-color: #2196f3; color: #2196f3; }
    .eje-btn-ag.sel-organizacion { background: rgba(156,39,176,.12); border-color: #9c27b0; color: #9c27b0; }

    .modal-footer-ag {
      display: flex; justify-content: space-between; align-items: center;
      margin-top: 30px; padding-top: 25px; border-top: 1px solid var(--gris-borde);
    }
    .btn-save-ag {
      background: var(--verde); color: white; border: none;
      padding: 12px 28px; border-radius: 12px; font-weight: 700;
      cursor: pointer; transition: all 0.3s; font-size: 14px;
      font-family: 'IBM Plex Sans', sans-serif;
    }
    .btn-save-ag:hover { background: var(--verde-hover); transform: translateY(-1px); }
    .btn-cancel-ag {
      background: var(--blanco); color: var(--texto-dim);
      border: 1px solid var(--gris-borde); padding: 12px 22px;
      border-radius: 12px; font-weight: 600; cursor: pointer;
      transition: 0.3s; font-size: 13px; font-family: 'IBM Plex Sans', sans-serif;
    }
    .btn-cancel-ag:hover { border-color: var(--texto); color: var(--texto); }
    .btn-danger-ag {
      background: #ffeaea; color: #d32f2f; border: 1px solid #fecaca;
      padding: 12px 20px; border-radius: 12px; font-weight: 700;
      cursor: pointer; font-size: 13px; font-family: 'IBM Plex Sans', sans-serif;
    }
    .btn-danger-ag:hover { background: #fee2e2; }

    /* sección seleccionada highlight */
    .seccion-badge {
      display: inline-flex; align-items: center; gap: 8px;
      background: rgba(26,122,60,0.1); color: var(--verde);
      border: 1px solid rgba(26,122,60,0.3); border-radius: 8px;
      padding: 8px 14px; font-size: 13px; font-weight: 700;
      font-family: 'IBM Plex Mono', monospace;
    }
    .seccion-badge.empty { background: var(--gris-panel); color: var(--texto-dim); border-color: var(--gris-borde); font-family: 'IBM Plex Sans', sans-serif; font-weight: 500; }

    /* detail grid (modal detalle) */
    .detail-grid-ag { display: grid; grid-template-columns: repeat(3,1fr); gap: 20px; margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--gris-borde); }
    .detail-item-ag strong { display: block; font-size: 11px; color: var(--texto-dim); text-transform: uppercase; margin-bottom: 5px; letter-spacing: 0.5px; }
    .detail-item-ag span { font-size: 15px; color: var(--texto); font-weight: 600; font-family: 'IBM Plex Sans', sans-serif; }
    .eje-badge-det { display: inline-flex; align-items: center; gap: 6px; padding: 4px 14px; border-radius: 20px; font-size: 12px; font-weight: 700; border: 1px solid; margin-bottom: 15px; }

    /* Icono de evento en el mapa */
    .evento-marker {
      background: transparent !important;
      border: none !important;
      box-shadow: none !important;
      pointer-events: none;
    }
    .evento-pin {
      display: flex;
      flex-direction: column;
      align-items: center;
    }
    .evento-pin-icon {
      width: 26px; height: 26px;
      background: var(--verde, #1a7a3c);
      border-radius: 50% 50% 50% 0;
      transform: rotate(-45deg);
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 2px 8px rgba(0,0,0,0.25);
      border: 2px solid white;
    }
    .evento-pin-icon i {
      transform: rotate(45deg);
      color: white;
      font-size: 10px;
    }
    .evento-pin-label {
      margin-top: 3px;
      font-family: 'IBM Plex Mono', monospace;
      font-size: 8px;
      font-weight: 600;
      color: #0f5228;
      background: rgba(255,255,255,0.85);
      padding: 1px 4px;
      border-radius: 3px;
      white-space: nowrap;
      text-shadow: none;
    }

    /* Pin de activaciones (diamante) */
    .activacion-pin { display:flex; flex-direction:column; align-items:center; }
    .activacion-pin-icon {
      width: 26px; height: 26px;
      border-radius: 4px;
      transform: rotate(45deg);
      display: flex; align-items: center; justify-content: center;
      box-shadow: 0 2px 8px rgba(0,0,0,0.25);
      border: 2px solid white;
    }
    .activacion-pin-icon i { transform: rotate(-45deg); color: white; font-size: 10px; }
    .activacion-pin-label {
      margin-top: 3px;
      font-family: 'IBM Plex Mono', monospace;
      font-size: 8px; font-weight: 600;
      color: #92400e;
      background: rgba(255,255,255,0.85);
      padding: 1px 4px; border-radius: 3px; white-space: nowrap;
    }

    /* Layout */
    .main { display: flex; flex: 1; overflow: hidden; }

    /* Panel */
    .panel {
      width: 260px;
      background: var(--gris-panel);
      border-right: 1px solid var(--gris-borde);
      display: flex;
      flex-direction: column;
      flex-shrink: 0;
      overflow-y: auto;
    }

    .panel-section {
      padding: 16px;
      border-bottom: 1px solid var(--gris-borde);
    }

    .panel-label {
      font-size: 10px;
      font-weight: 600;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      color: var(--texto-dim);
      margin-bottom: 10px;
    }

    .stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }

    .stat-box {
      background: var(--blanco);
      border: 1px solid var(--gris-borde);
      border-radius: 6px;
      padding: 10px;
    }

    .stat-num {
      font-family: 'IBM Plex Mono', monospace;
      font-size: 20px;
      font-weight: 600;
      color: var(--verde);
      line-height: 1;
    }

    .stat-label { font-size: 10px; color: var(--texto-dim); margin-top: 3px; }

    .layer-item {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 8px 0;
      cursor: pointer;
    }

    .layer-left { display: flex; align-items: center; gap: 8px; }

    .layer-color { width: 12px; height: 12px; border-radius: 2px; flex-shrink: 0; }

    .layer-name { font-size: 12px; color: var(--texto); transition: color 0.15s; }
    .layer-item:hover .layer-name { color: var(--verde); }

    .toggle {
      width: 28px; height: 16px;
      background: #ccc;
      border-radius: 8px;
      position: relative;
      transition: background 0.2s;
      flex-shrink: 0;
    }
    .toggle.on { background: var(--verde); }
    .toggle::after {
      content: '';
      position: absolute;
      width: 12px; height: 12px;
      background: white;
      border-radius: 50%;
      top: 2px; left: 2px;
      transition: left 0.2s;
    }
    .toggle.on::after { left: 14px; }

    #info-panel { padding: 16px; display: none; overflow-y: auto; max-height: calc(100vh - 220px); }
    #info-panel.visible { display: block; }

    /* ── Estilos del panel de datos de BD ── */
    .db-sep {
      border: none; border-top: 1px solid var(--gris-borde);
      margin: 14px 0 10px;
    }
    .db-section-label {
      font-size: 9px; font-weight: 700; letter-spacing: 0.12em;
      text-transform: uppercase; color: var(--verde);
      margin: 14px 0 6px;
      display: flex; align-items: center; gap: 5px;
    }
    .db-section-label i { font-size: 9px; }
    .info-val.verde { color: var(--verde); font-weight: 700; }

    .db-progress-bg {
      height: 6px; border-radius: 3px;
      background: var(--gris-medio); overflow: hidden;
      margin: 5px 0 3px;
    }
    .db-progress-fill {
      height: 100%; border-radius: 3px;
      background: linear-gradient(90deg, #1a7a3c, #22a050);
      transition: width 0.5s ease;
    }
    .db-progress-meta {
      display: flex; justify-content: space-between;
      font-size: 9px; color: var(--texto-dim);
      font-family: 'IBM Plex Mono', monospace;
    }

    .edad-chips { display: flex; flex-wrap: wrap; gap: 3px; margin: 5px 0 3px; }
    .edad-chip {
      font-size: 9px; font-weight: 600;
      background: var(--gris-medio); color: var(--texto-dim);
      padding: 2px 5px; border-radius: 3px;
      font-family: 'IBM Plex Mono', monospace;
    }

    .act-mini-grid {
      display: grid; grid-template-columns: repeat(3, 1fr);
      gap: 5px; margin: 6px 0;
    }
    .act-mini-card {
      background: var(--blanco); border: 1px solid var(--gris-borde);
      border-radius: 5px; padding: 7px 4px; text-align: center;
    }
    .act-mini-num {
      font-family: 'IBM Plex Mono', monospace;
      font-size: 16px; font-weight: 600; color: var(--verde); line-height: 1;
    }
    .act-mini-lbl {
      font-size: 8px; color: var(--texto-dim);
      margin-top: 2px; letter-spacing: 0.04em;
    }

    .db-open-field { margin-bottom: 7px; }
    .db-open-label {
      font-size: 9px; font-weight: 700; text-transform: uppercase;
      letter-spacing: 0.08em; color: var(--texto-dim); margin-bottom: 3px;
    }
    .db-open-text {
      font-size: 11px; color: var(--texto);
      background: var(--gris-panel); border-radius: 4px;
      padding: 6px 8px; line-height: 1.5;
    }

    .db-link-btn {
      display: flex; align-items: center; justify-content: center; gap: 6px;
      margin-top: 14px; padding: 8px;
      background: var(--verde); color: white;
      border-radius: 5px; font-size: 11px; font-weight: 600;
      text-decoration: none; letter-spacing: 0.04em;
      transition: background 0.15s;
    }
    .db-link-btn:hover { background: var(--verde-hover); }

    #db-spinner {
      text-align: center; padding: 14px 0;
    }
    #db-no-data {
      font-size: 11px; color: var(--texto-dim);
      background: var(--gris-panel); border-radius: 5px;
      padding: 8px 10px; text-align: center; margin-top: 10px;
    }

    .info-title {
      font-family: 'IBM Plex Mono', monospace;
      font-size: 22px;
      font-weight: 600;
      color: var(--verde);
      margin-bottom: 12px;
    }

    .info-row {
      display: flex;
      justify-content: space-between;
      padding: 6px 0;
      border-bottom: 1px solid var(--gris-borde);
      font-size: 12px;
    }
    .info-row:last-child { border-bottom: none; }
    .info-key { color: var(--texto-dim); }
    .info-val { font-family: 'IBM Plex Mono', monospace; color: var(--texto); }

    /* Mapa */
    #map { flex: 1; }

    .lbl-seccion {
      background: transparent !important;
      border: none !important;
      box-shadow: none !important;
      font-family: 'IBM Plex Mono', monospace;
      font-size: 9px;
      font-weight: 600;
      color: #1a7a3c;
      text-shadow: 0 0 3px rgba(255,255,255,1), 0 0 6px rgba(255,255,255,0.8);
      white-space: nowrap;
      pointer-events: none;
    }

    body.escala-labels-2 .lbl-seccion { font-size: 18px; }
    body.escala-labels-3 .lbl-seccion { font-size: 27px; }

    .btn-escala { display:flex; gap:4px; }
    .btn-escala button { flex:1; font-family:'IBM Plex Mono',monospace; font-size:10px; font-weight:700; padding:5px 0; border-radius:5px; border:1px solid var(--gris-borde); background:var(--blanco); color:var(--texto-dim); cursor:pointer; }
    .btn-escala button.activo { background:var(--verde); color:#fff; border-color:var(--verde); }

    #btn-imprimir-mapa { width:100%; margin-top:8px; padding:8px 0; font-family:'IBM Plex Mono',monospace; font-size:11px; font-weight:700; border-radius:6px; border:1px solid var(--verde); background:var(--verde); color:#fff; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:6px; }
    #btn-imprimir-mapa:hover { filter:brightness(1.08); }

    select#select-capa-base { width:100%; font-size:11px; font-family:'IBM Plex Sans',sans-serif; padding:6px 8px; border-radius:5px; border:1px solid var(--gris-borde); background:var(--blanco); color:var(--texto); outline:none; }

    @media print {
      header, aside.panel, #info-panel, .leaflet-control-zoom, .leaflet-control-layers { display: none !important; }
      .main { display:block !important; }
      #map { width:100% !important; height:100vh !important; }
      body { background:#fff !important; }
    }


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

    /* Leaflet */
    .leaflet-container { background: #f4f6f4; }
    .leaflet-interactive:focus { outline: none !important; }
    .leaflet-control-attribution { display: none !important; }
    .leaflet-control-zoom {
      border: 1px solid var(--gris-borde) !important;
      border-radius: 6px !important;
      overflow: hidden;
      box-shadow: 0 1px 4px rgba(0,0,0,0.08) !important;
    }
    .leaflet-control-zoom a {
      background: white !important;
      color: var(--texto) !important;
      border-bottom: 1px solid var(--gris-borde) !important;
      width: 28px !important;
      height: 28px !important;
      line-height: 28px !important;
    }
    .leaflet-control-zoom a:hover {
      background: var(--gris-medio) !important;
      color: var(--verde) !important;
    }

    /* Dropdown de Estado */
    .estado-selector-wrap {
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .estado-selector-label {
      font-size: 10px;
      font-weight: 600;
      letter-spacing: 0.1em;
      text-transform: uppercase;
      color: var(--texto-dim);
      white-space: nowrap;
    }
    .estado-select {
      height: 32px;
      padding: 0 28px 0 10px;
      border: 1px solid var(--gris-borde);
      border-radius: 5px;
      background: var(--blanco);
      font-family: 'IBM Plex Sans', sans-serif;
      font-size: 12px;
      font-weight: 600;
      color: var(--texto);
      cursor: pointer;
      outline: none;
      appearance: none;
      -webkit-appearance: none;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%236b7280'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 9px center;
      transition: border-color 0.15s;
      min-width: 160px;
    }
    .estado-select:focus { border-color: var(--verde); }
    .estado-select:hover { border-color: var(--verde-acento); }

    /* ── Modal Detalle Sección (desde secciones.php) ── */
    .sec-modal-overlay {
      display: none; position: fixed; inset: 0;
      background: rgba(255,255,255,0.9); backdrop-filter: blur(5px);
      z-index: 9500; align-items: center; justify-content: center;
      opacity: 0; transition: opacity 0.3s ease;
    }
    .sec-modal-overlay.open { display: flex; opacity: 1; }
    .sec-modal-box {
      background: #fff; border-radius: 20px;
      border: 1px solid #e9ecef;
      box-shadow: 0 25px 50px rgba(0,0,0,0.12);
      width: 92%; max-width: 780px; padding: 40px;
      position: relative; transform: translateY(30px); opacity: 0;
      transition: all 0.4s ease-out; max-height: 92vh; overflow-y: auto;
      font-family: 'IBM Plex Sans', sans-serif;
    }
    .sec-modal-overlay.open .sec-modal-box { transform: translateY(0); opacity: 1; }
    .sec-modal-close {
      position: absolute; top: 20px; right: 20px;
      font-size: 20px; cursor: pointer; color: #ccc;
      width: 36px; height: 36px; display: flex; align-items: center;
      justify-content: center; border-radius: 50%; border: none;
      background: transparent; transition: all 0.3s;
    }
    .sec-modal-close:hover { color: var(--verde); background: #f0f7f0; transform: rotate(90deg); }
    .sec-modal-title { font-size: 36px; font-weight: 900; color: #1a1e2a; line-height: 1; margin-bottom: 6px; }
    .sec-modal-subtitle { font-size: 14px; color: #999; margin-bottom: 22px; }
    .sec-det-grid {
      display: grid; grid-template-columns: repeat(2, 1fr);
      gap: 12px; margin-bottom: 20px;
    }
    .sec-det-item { display: flex; flex-direction: column; gap: 4px; }
    .sec-det-item label {
      font-size: 11px; font-weight: 700; text-transform: uppercase;
      letter-spacing: 0.5px; color: #6c757d;
    }
    .sec-det-item span { font-size: 14px; color: #1a1e2a; font-weight: 600; }
    .sec-section-title {
      font-size: 13px; font-weight: 800; text-transform: uppercase;
      letter-spacing: 1px; color: #1a7a3c;
      margin: 20px 0 12px; padding-bottom: 8px;
      border-bottom: 2px solid #e9ecef;
      display: flex; align-items: center; gap: 8px;
    }
    .sec-nominal-grid {
      display: grid; grid-template-columns: repeat(3, 1fr);
      gap: 12px; margin-bottom: 16px;
    }
    .sec-nominal-card {
      background: #f8f9fa; border-radius: 12px; padding: 14px;
      text-align: center; border: 1px solid #e9ecef;
    }
    .sec-nominal-num { font-size: 24px; font-weight: 900; line-height: 1; }
    .sec-nominal-lbl { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #6c757d; margin-top: 3px; }
    .sec-modal-progress-bg {
      height: 10px; border-radius: 5px; background: #e9ecef; overflow: hidden; margin: 8px 0 4px;
    }
    .sec-modal-progress-fill {
      height: 100%; border-radius: 5px;
      background: linear-gradient(90deg, #1a7a3c, #22a050); transition: width 0.6s ease;
    }
    .sec-modal-progress-lbl {
      display: flex; justify-content: space-between;
      font-size: 12px; font-weight: 700; color: #6c757d;
    }
    .sec-act-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 10px; margin: 10px 0; }
    .sec-act-card {
      background: #f8f9fa; border-radius: 10px; padding: 14px; text-align: center;
    }
    .sec-act-num { font-size: 26px; font-weight: 900; color: #1a7a3c; }
    .sec-act-lbl { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #6c757d; margin-top: 2px; }
    .sec-open-field { margin-bottom: 10px; }
    .sec-open-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #6c757d; margin-bottom: 4px; }
    .sec-open-text {
      font-size: 13px; color: #1a1e2a; background: #f8f9fa;
      border-radius: 8px; padding: 10px 12px; line-height: 1.5;
    }
    .sec-modal-footer { display: flex; justify-content: flex-end; margin-top: 24px; padding-top: 20px; border-top: 1px solid #e9ecef; }
    .sec-modal-btn-close {
      background: #fff; color: #6c757d; border: 1px solid #e9ecef;
      padding: 11px 22px; border-radius: 12px; font-weight: 600;
      cursor: pointer; font-size: 13px; font-family: 'IBM Plex Sans', sans-serif;
      transition: 0.2s;
    }
    .sec-modal-btn-close:hover { border-color: #1a7a3c; color: #1a7a3c; }

    /* botón Detalles en el panel aside */
    .btn-ver-detalles {
      display: flex; align-items: center; justify-content: center; gap: 6px;
      width: 100%; margin-top: 14px; padding: 9px;
      background: rgba(26,122,60,0.1); color: var(--verde);
      border: 1.5px solid rgba(26,122,60,0.3);
      border-radius: 6px; font-size: 11px; font-weight: 700;
      cursor: pointer; letter-spacing: 0.04em;
      font-family: 'IBM Plex Sans', sans-serif;
      transition: all 0.15s;
    }
    .btn-ver-detalles:hover { background: var(--verde); color: white; border-color: var(--verde); }

    /* Eventos layer toggle */
    .ev-count-badge {
      font-family: 'IBM Plex Mono', monospace;
      font-size: 9px; font-weight: 700;
      background: var(--verde); color: white;
      padding: 1px 6px; border-radius: 8px; margin-left: 4px;
    }
    .zona-select {
      width:100%; height:30px; padding:0 10px;
      border:1px solid var(--gris-borde); border-radius:5px;
      background:var(--blanco); font-family:'IBM Plex Sans',sans-serif;
      font-size:11px; font-weight:600; color:var(--texto);
      cursor:pointer; outline:none; appearance:none; -webkit-appearance:none;
      background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%236b7280'/%3E%3C/svg%3E");
      background-repeat:no-repeat; background-position:right 8px center;
      margin-bottom:6px; transition:border-color 0.15s;
    }
    .zona-select:focus { border-color:var(--verde); }
    .zona-btn-limpiar {
      width:100%; padding:6px;
      border:1px solid var(--gris-borde); border-radius:5px;
      background:var(--gris-panel); font-size:10px; font-weight:600;
      color:var(--texto-dim); cursor:pointer;
      font-family:'IBM Plex Sans',sans-serif; transition:all 0.15s; margin-top:2px;
    }
    .zona-btn-limpiar:hover { background:var(--gris-borde); color:var(--texto); }
    .filter-row { display:grid; grid-template-columns:1fr 1fr; gap:6px; margin-bottom:6px; }
    .filter-select {
      height:28px; padding:0 6px;
      border:1px solid var(--gris-borde); border-radius:5px;
      background:var(--blanco); font-family:'IBM Plex Sans',sans-serif;
      font-size:10px; font-weight:600; color:var(--texto);
      cursor:pointer; outline:none; appearance:none; -webkit-appearance:none;
      transition:border-color 0.15s;
    }
    .filter-select:focus { border-color:var(--verde); }
    .filter-label-row { display:flex; justify-content:space-between; align-items:center; margin-bottom:6px; }
    .filter-clear-btn {
      font-size:9px; color:var(--verde); cursor:pointer;
      background:none; border:none; font-family:'IBM Plex Sans',sans-serif;
      font-weight:600; padding:0;
    }
    .filter-clear-btn:hover { text-decoration:underline; }
  </style>
</head>
<body>

<?php if (file_exists(__DIR__ . '/../../includes/components/sidebar_circunscripcion.php')) include __DIR__ . '/../../includes/components/sidebar_circunscripcion.php'; ?>

<?php if ($modoVista): ?>
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
    <span id="header-estado-span">— Selecciona un estado</span>
  </div>
  <div style="display:flex;align-items:center;gap:16px;">
    <div class="estado-selector-wrap">
      <span class="estado-selector-label">Estado</span>
      <select class="estado-select" id="estado-select" onchange="cambiarEstado(this.value)">
        <option value="">— Seleccionar estado —</option>
        <?php foreach ($estadosData as $key => $cfg): ?>
        <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($cfg['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

  </div>
</header>

<div class="main">

  <aside class="panel">

    <div class="panel-section">
      <div class="panel-label">Estadísticas</div>
      <div class="stats-grid">
        <div class="stat-box">
          <div class="stat-num" id="stat-secciones">—</div>
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

      <div class="layer-item" id="row-municipio" onclick="toggleCapa('municipio')" style="display:none;">
        <div class="layer-left">
          <div class="layer-color" style="background:transparent;border:2px solid #1565c0;border-radius:2px;"></div>
          <span class="layer-name" id="label-municipio">Municipio</span>
        </div>
        <div class="toggle on" id="toggle-municipio"></div>
      </div>

      <div class="layer-item" id="row-distfed" onclick="toggleCapa('distFed')" style="display:none;">
        <div class="layer-left">
          <div class="layer-color" style="background:transparent;border:2px solid #1565c0;border-style:dashed;"></div>
          <span class="layer-name" id="label-distfed">Distritos Federales</span>
        </div>
        <div class="toggle" id="toggle-distFed"></div>
      </div>

      <div class="layer-item" id="row-distloc" onclick="toggleCapa('distLoc')" style="display:none;">
        <div class="layer-left">
          <div class="layer-color" style="background:transparent;border:2px solid #6a1b9a;border-style:dashed;"></div>
          <span class="layer-name" id="label-distloc">Distritos Locales</span>
        </div>
        <div class="toggle" id="toggle-distLoc"></div>
      </div>

      <div class="layer-item" id="row-secciones" onclick="toggleCapa('secciones')" style="display:none;">
        <div class="layer-left">
          <div class="layer-color" style="background:transparent;border:2px solid #22a050;"></div>
          <span class="layer-name" id="label-secciones">Secciones Electorales</span>
        </div>
        <div class="toggle on" id="toggle-secciones"></div>
      </div>

      <div class="layer-item" id="row-labels" onclick="toggleCapa('labels')" style="display:none;">
        <div class="layer-left">
          <div class="layer-color" style="background:#1a7a3c;border-radius:50%;"></div>
          <span class="layer-name">Número de Sección</span>
        </div>
        <div class="toggle on" id="toggle-labels"></div>
      </div>

      <div class="layer-item" id="row-colonias" onclick="toggleCapa('colonias')" style="display:none;">
        <div class="layer-left">
          <div class="layer-color" style="background:transparent;border:2px solid #00838f;border-radius:2px;"></div>
          <span class="layer-name" id="label-colonias">Colonias</span>
        </div>
        <div class="toggle" id="toggle-colonias"></div>
      </div>

      <div class="layer-item" id="row-eventos" onclick="toggleCapaEventos()">
        <div class="layer-left">
          <div class="layer-color" style="background:#1a7a3c;border-radius:50%;"></div>
          <span class="layer-name">Eventos <span class="ev-count-badge" id="ev-count-badge" style="display:none;">0</span></span>
        </div>
        <div class="toggle" id="toggle-eventos"></div>
      </div>

      <div class="layer-item" id="row-activaciones" onclick="toggleCapaActivaciones()">
        <div class="layer-left">
          <div class="layer-color" style="background:#f59e0b;transform:rotate(45deg);border-radius:2px;"></div>
          <span class="layer-name">Activaciones <span class="ev-count-badge" id="act-count-badge" style="background:#f59e0b;display:none;">0</span></span>
        </div>
        <div class="toggle" id="toggle-activaciones"></div>
      </div>

      <div id="capas-placeholder" style="font-size:11px;color:var(--texto-dim);padding:6px 0;">
        Selecciona un estado para ver las capas disponibles.
      </div>

    </div>

    <!-- ── Pintar Zona (Región → Zona) ── -->
    <div class="panel-section" id="panel-zona-selector">
      <div class="panel-label">Pintar Zona</div>
      <select id="sel-region" class="zona-select" onchange="onRegionChange()">
        <option value="">— Región —</option>
      </select>
      <select id="sel-zona" class="zona-select" onchange="onZonaChange()" disabled>
        <option value="">— Zona —</option>
      </select>
      <button class="zona-btn-limpiar" onclick="limpiarZona()">
        <i class="fas fa-times-circle"></i> Limpiar selección de zona
      </button>
    </div>

    <!-- ── Filtros de fecha ── -->
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
      <div style="font-size:9px;color:var(--texto-dim);line-height:1.4;">Aplica a Eventos y Activaciones visibles.</div>
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

      <!-- GeoJSON base -->
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

      <!-- Botón Detalles — siempre visible al seleccionar sección -->
      <button class="btn-ver-detalles" id="btn-ver-detalles" onclick="abrirModalDetalle()" style="margin-top:12px;display:none;">
        <i class="fas fa-table-list"></i> Ver Detalles de Sección
      </button>

      <!-- Spinner -->
      <div id="db-spinner" style="display:none;">
        <i class="fas fa-spinner fa-spin" style="color:var(--verde);font-size:15px;"></i>
        <div style="font-size:9px;color:var(--texto-dim);margin-top:4px;letter-spacing:0.05em;">Cargando datos…</div>
      </div>

      <!-- Sin datos -->
      <div id="db-no-data" style="display:none;">
        <i class="fas fa-info-circle"></i> Sin datos registrados para esta sección
      </div>

      <!-- Datos BD -->
      <div id="db-panel" style="display:none;">

        <hr class="db-sep">

        <!-- Zona / Región -->
        <div class="db-section-label"><i class="fas fa-map-pin"></i> Zona</div>
        <div class="info-row">
          <span class="info-key">Región</span>
          <span class="info-val" id="db-region">—</span>
        </div>
        <div class="info-row">
          <span class="info-key">Zona</span>
          <span class="info-val" id="db-zona">—</span>
        </div>

        <!-- Lista Nominal + Grupos de edad -->
        <div class="db-section-label"><i class="fas fa-users"></i> Lista Nominal</div>
        <div class="info-row">
          <span class="info-key">Total</span>
          <span class="info-val verde" id="db-nominal">—</span>
        </div>
        <div class="edad-chips" id="db-edad-chips"></div>

        <!-- Contactos -->
        <div class="db-section-label"><i class="fas fa-address-book"></i> Contactos</div>
        <div class="info-row">
          <span class="info-key">Meta</span>
          <span class="info-val" id="db-meta">—</span>
        </div>
        <div class="info-row">
          <span class="info-key">Conseguidos</span>
          <span class="info-val verde" id="db-contactos">—</span>
        </div>
        <div class="db-progress-bg">
          <div class="db-progress-fill" id="db-progress-fill" style="width:0%"></div>
        </div>
        <div class="db-progress-meta">
          <span id="db-pct">0%</span>
          <span id="db-rem">0 restantes</span>
        </div>

        <!-- Eventos y Votos -->
        <div class="db-section-label"><i class="fas fa-calendar-check"></i> Resultados</div>
        <div class="info-row">
          <span class="info-key">Eventos realizados</span>
          <span class="info-val verde" id="db-eventos">—</span>
        </div>
        <div class="info-row">
          <span class="info-key">Meta de votos</span>
          <span class="info-val verde" id="db-votos">—</span>
        </div>

        <!-- Activaciones -->
        <div class="db-section-label"><i class="fas fa-bolt"></i> Activaciones</div>
        <div class="act-mini-grid">
          <div class="act-mini-card">
            <div class="act-mini-num" id="db-act-total">—</div>
            <div class="act-mini-lbl">Total</div>
          </div>
          <div class="act-mini-card">
            <div class="act-mini-num" id="db-act-con">—</div>
            <div class="act-mini-lbl">Con Cand.</div>
          </div>
          <div class="act-mini-card">
            <div class="act-mini-num" id="db-act-sin">—</div>
            <div class="act-mini-lbl">Sin Cand.</div>
          </div>
        </div>

        <!-- Campos abiertos -->
        <div class="db-section-label"><i class="fas fa-align-left"></i> Información</div>
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

        <a id="db-link" href="/App/admin/secciones.php" target="_blank" class="db-link-btn">
          <i class="fas fa-external-link-alt"></i> Ver detalle completo
        </a>

      </div><!-- /db-panel -->
    </div>

  </aside>

  <div id="map"></div>

</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// ──────────────────────────────────────────────
// Datos de todos los estados (cargados desde PHP)
// ──────────────────────────────────────────────
const ESTADOS_DATA = <?= $estadosJson ?>;
const ESTRUCTURA_ZONAS_POR_ESTADO = <?= json_encode($estructuraZonasPorEstado ?? [], JSON_UNESCAPED_UNICODE) ?>;

let layerZonaResaltada = null;

/* ── Modo Vista ── */
const MODO_VISTA       = <?= $modoVista ? 'true' : 'false' ?>;
const VIST_ZONA_ID     = <?= $vistZonaId ?>;
const VIST_ZONA_NOMBRE = <?= json_encode($vistZonaNombre) ?>;
const VIST_RETURN_URL  = <?= json_encode($vistReturnUrl) ?>;
const VIST_ESTADO_KEY  = <?= json_encode($vistEstadoKey) ?>;
const SECCIONES_ZONA   = new Set(<?= $seccionesZonaJson ?>);

// Límites geográficos de México
const MEXICO_BOUNDS = L.latLngBounds(
  L.latLng(14.5, -118.5),  // SW: esquina suroeste
  L.latLng(32.7, -86.7)    // NE: esquina noreste
);

const map = L.map('map', {
  zoomControl: true,
  attributionControl: false,
  minZoom: 5,
  maxZoom: 19,
  maxBounds: MEXICO_BOUNDS,
  maxBoundsViscosity: 1.0   // 1.0 = no se puede arrastrar fuera
}).setView([23.6345, -102.5528], 5);

const capasBase = {
  claro:    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', { maxZoom: 19 }),
  osm:      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }),
  satelite: L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { maxZoom: 19, attribution: 'Esri' })
};
capasBase.claro.addTo(map);

// ──────────────────────────────────────────────
// Capas activas
// ──────────────────────────────────────────────
let layerEstadoSilueta = null;   // contorno unificado del estado (defecto)
let layerMunicipio     = null;   // municipios con líneas internas
let layerSecciones     = null;   // secciones electorales
let layerLabels        = null;   // números de sección

let seccionActiva  = null;
let municipioActivo = null;
let estadoActual   = null;
let seccionDbData  = null;

let layerDistFed  = null;
let layerDistLoc  = null;
let layerDistLocLabels = null;   // números de distrito local
let layerColonias = null;

const ZOOM_ENTIDAD = <?= json_encode($zoomEntidad, JSON_UNESCAPED_UNICODE) ?>;
const ZOOM_TIPO    = <?= json_encode($zoomTipo, JSON_UNESCAPED_UNICODE) ?>;
const capasVisibles = { municipio: true, secciones: true, labels: true, distFed: (ZOOM_ENTIDAD && ZOOM_TIPO === 'distrito_federal'), distLoc: (ZOOM_ENTIDAD && ZOOM_TIPO === 'distrito_local'), colonias: false };

// ──────────────────────────────────────────────
// Helpers: limpiar capas
// ──────────────────────────────────────────────
function limpiarCapas() {
  if (layerEstadoSilueta) { map.removeLayer(layerEstadoSilueta); layerEstadoSilueta = null; }
  if (layerMunicipio)     { map.removeLayer(layerMunicipio);     layerMunicipio = null; }
  if (layerSecciones)     { map.removeLayer(layerSecciones);     layerSecciones = null; }
  if (layerLabels)        { map.removeLayer(layerLabels);        layerLabels = null; }
  if (layerDistFed)       { map.removeLayer(layerDistFed);       layerDistFed = null; }
  if (layerDistLoc)       { map.removeLayer(layerDistLoc);       layerDistLoc = null; }
  if (layerDistLocLabels) { map.removeLayer(layerDistLocLabels); layerDistLocLabels = null; }
  if (layerColonias)      { map.removeLayer(layerColonias);      layerColonias = null; }
  seccionActiva = null;
  municipioActivo = null;
  document.getElementById('info-panel').classList.remove('visible');
}

// ──────────────────────────────────────────────
// Silueta del estado (solo contorno exterior, sin municipios)
// ──────────────────────────────────────────────
function cargarSilueta(munData) {
  // Dibuja todos los municipios pero con estilo uniforme (sin distinción interna),
  // dando la apariencia de silueta de estado con solo la línea exterior verde.
  layerEstadoSilueta = L.geoJSON(munData, {
    style: {
      color: '#1a7a3c',
      weight: 2,
      fillColor: '#e8f5ec',
      fillOpacity: 0.25,
      dashArray: null
    },
    interactive: false   // sin interacción — solo visual
  }).addTo(map);
  map.fitBounds(layerEstadoSilueta.getBounds(), { padding: [24, 24] });
}

// ──────────────────────────────────────────────
// Municipios con líneas internas (al seleccionar estado)
// ──────────────────────────────────────────────
function cargarMunicipios(munData) {
  municipioActivo = null;
  layerMunicipio = L.geoJSON(munData, {
    style: {
      color: '#4a7c5e',
      weight: 1.2,
      fillColor: '#e8f0eb',
      fillOpacity: 0.30,
      dashArray: '4,4'
    },
    onEachFeature: (feature, layer) => {
      const p = feature.properties;
      const nombre = p.nombre_mun || p.NOM_MUN || p.nom_mun || p.NOMBRE || p.nombre || p.municipio || '—';

      layer.on('mouseover', function() {
        if (municipioActivo !== this) {
          this.setStyle({ fillOpacity: 0.55, color: '#1a7a3c', weight: 2, dashArray: null });
          this.bringToFront();
        }
      });
      layer.on('mouseout', function() {
        if (municipioActivo !== this) layerMunicipio.resetStyle(this);
      });
      layer.on('click', function(e) {
        if (municipioActivo && municipioActivo !== this) layerMunicipio.resetStyle(municipioActivo);
        this.setStyle({ fillColor: '#1a7a3c', fillOpacity: 0.45, color: '#0f5228', weight: 2, dashArray: null });
        this.bringToFront();
        municipioActivo = this;
        L.DomEvent.stopPropagation(e);
      });
      layer.bindTooltip(
        `<span style="font-family:IBM Plex Sans,sans-serif;font-size:11px;font-weight:600;color:#1a1e2a;">${nombre}</span>`,
        { sticky: true, direction: 'top', offset: [0, -8], opacity: 0.95 }
      );
    }
  }).addTo(map);

  // Respetar toggle
  if (!capasVisibles.municipio) map.removeLayer(layerMunicipio);

  map.fitBounds(layerMunicipio.getBounds(), { padding: [24, 24] });

  // Acercamiento a un municipio específico (viene del listado "Municipios de la región")
  if (ZOOM_ENTIDAD && ZOOM_TIPO === 'municipios') {
    let matched = null;
    layerMunicipio.eachLayer(l => {
      const p = l.feature.properties;
      const nombre = p.nombre_mun || p.NOM_MUN || p.nom_mun || p.NOMBRE || p.nombre || p.municipio || '';
      if (nombre && nombre.trim().toLowerCase() === ZOOM_ENTIDAD.trim().toLowerCase()) matched = l;
    });
    if (matched) {
      municipioActivo = matched;
      matched.setStyle({ fillColor:'#1a7a3c', fillOpacity:0.45, color:'#0f5228', weight:2, dashArray:null });
      matched.bringToFront();
      map.fitBounds(matched.getBounds(), { padding:[40,40], maxZoom:14 });
    }
  }
}

// ──────────────────────────────────────────────
// Secciones electorales
// ──────────────────────────────────────────────
function cargarSecciones(secData) {
  if (!secData || !secData.features || secData.features.length === 0) return;

  layerSecciones = L.geoJSON(secData, {
    style: feature => {
      const sec = String(feature.properties.seccion);
      if (MODO_VISTA && SECCIONES_ZONA.has(sec)) {
        return { color:'#1565c0', weight:2.5, fillColor:'#1565c0', fillOpacity:0.35 };
      }
      return { color: '#22a050', weight: 1, fillColor: '#ffffff', fillOpacity: 0.01 };
    },
    onEachFeature: (feature, layer) => {
      const p = feature.properties;
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

        // Pre-cargar datos GeoJSON en seccionDbData para el modal (aunque no haya datos BD)
        seccionDbData = {
          nombre_seccion:  p.seccion,
          seccion:         p.seccion,
          estado:          ESTADOS_DATA[estadoActual]?.label || '',
          municipio:       p.nombre_mun || `Mun. ${p.municipio}`,
          distrito_federal: `Distrito ${p.distrito_f}`,
          distrito_local:   `Distrito ${p.distrito_l}`,
        };
        // Mostrar el botón siempre que haya sección seleccionada
        document.getElementById('btn-ver-detalles').style.display = 'flex';

        // Cargar datos desde BD
        cargarDatosSeccion(p.seccion);

        L.DomEvent.stopPropagation(e);
      });
    }
  }).addTo(map);

  if (!capasVisibles.secciones) map.removeLayer(layerSecciones);

  // Labels de número de sección
  layerLabels = L.layerGroup();
  secData.features.forEach(feature => {
    if (!feature.geometry) return;
    let latlng;
    try { latlng = L.geoJSON(feature).getBounds().getCenter(); } catch(e) { return; }
    layerLabels.addLayer(L.marker(latlng, {
      icon: L.divIcon({
        className: 'lbl-seccion',
        html: feature.properties.seccion,
        iconSize: null,
        iconAnchor: [10, 7]
      }),
      interactive: false
    }));
  });
  layerLabels.addTo(map);
  if (!capasVisibles.labels) map.removeLayer(layerLabels);

  // Estadísticas
  document.getElementById('stat-secciones').textContent = secData.features.length;

  // ── MODO VISTA: auto-zoom a secciones de la zona ──
  if (MODO_VISTA && SECCIONES_ZONA.size > 0) {
    const boundsZona = [];
    layerSecciones.eachLayer(layer => {
      if (!layer.feature) return;
      if (SECCIONES_ZONA.has(String(layer.feature.properties.seccion ?? ''))) {
        try { boundsZona.push(layer.getBounds()); } catch(_) {}
      }
    });
    if (boundsZona.length > 0) {
      let b = boundsZona[0];
      boundsZona.forEach(bb => { b = b.extend(bb); });
      map.fitBounds(b, { padding:[40,40], maxZoom:13 });
    }
  }
}

// ──────────────────────────────────────────────
// Cambiar estado desde el dropdown
// ──────────────────────────────────────────────
function cambiarEstado(key) {
  limpiarCapas();

  // Reset stats
  document.getElementById('stat-secciones').textContent = '—';
  document.getElementById('stat-municipios').textContent = '—';

  // Reset capas panel
  document.getElementById('row-municipio').style.display  = 'none';
  document.getElementById('row-secciones').style.display  = 'none';
  document.getElementById('row-labels').style.display     = 'none';
  document.getElementById('row-distfed').style.display    = 'none';
  document.getElementById('row-distloc').style.display    = 'none';
  document.getElementById('row-colonias').style.display   = 'none';
  document.getElementById('capas-placeholder').style.display = 'block';

  // Reset toggles
  ['municipio','secciones','labels'].forEach(n => {
    capasVisibles[n] = true;
    document.getElementById(`toggle-${n}`).classList.add('on');
  });
  ['distFed','distLoc','colonias'].forEach(n => {
    capasVisibles[n] = false;
    document.getElementById(`toggle-${n}`).classList.remove('on');
  });

  if (!key || !ESTADOS_DATA[key]) {
    document.getElementById('header-estado-span').textContent = '— Selecciona un estado';
    map.setView([23.6345, -102.5528], 5);
    // Sin estado: apagar Eventos y Activaciones, ocultar badges
    estadoActual = null;
    capasVisiblesEventos = false;
    map.removeLayer(layerEventos);
    document.getElementById('toggle-eventos').classList.remove('on');
    layerEventos.clearLayers();
    actualizarBadge();
    capasVisiblesActivaciones = false;
    map.removeLayer(layerActivaciones);
    document.getElementById('toggle-activaciones').classList.remove('on');
    layerActivaciones.clearLayers();
    actualizarBadgeActivaciones();
    return;
  }

  estadoActual = key;
  const cfg = ESTADOS_DATA[key];
  document.getElementById('header-estado-span').textContent = cfg.label;

  // Actualizar selector de Región con las regiones del nuevo estado
  limpiarZona();
  const selRegion = document.getElementById('sel-region');
  const selZona   = document.getElementById('sel-zona');
  selRegion.innerHTML = '<option value="">— Región —</option>';
  selZona.innerHTML   = '<option value="">— Zona —</option>';
  selZona.disabled = true;
  const regsEstado = ESTRUCTURA_ZONAS_POR_ESTADO[cfg.label] || [];
  regsEstado.forEach(r => {
    const opt = document.createElement('option');
    opt.value = r.nombre; opt.textContent = r.nombre;
    selRegion.appendChild(opt);
  });

  // Encender Eventos y Activaciones del estado seleccionado
  capasVisiblesEventos = true;
  map.addLayer(layerEventos);
  document.getElementById('toggle-eventos').classList.add('on');
  repintarEventos();
  actualizarBadge();
  capasVisiblesActivaciones = true;
  map.addLayer(layerActivaciones);
  document.getElementById('toggle-activaciones').classList.add('on');
  pintarActivaciones();

  // Actualizar etiquetas dinámicamente
  document.getElementById('label-municipio').textContent = `Municipios de ${cfg.label}`;
  document.getElementById('label-secciones').textContent = `Secciones — ${cfg.label}`;

  const munData = cfg.municipio;

  if (!munData) {
    mostrarToast(`Sin datos de municipios para ${cfg.label}`);
    return;
  }

  // Mostrar fila municipios
  document.getElementById('row-municipio').style.display = 'flex';
  document.getElementById('capas-placeholder').style.display = 'none';

  // PASO 1: silueta suave
  cargarSilueta(munData);

  // PASO 2: municipios con líneas internas
  cargarMunicipios(munData);
  document.getElementById('stat-municipios').textContent = munData.features ? munData.features.length : '—';

  // PASO 3: Distritos Federales
  if (cfg.distFedUrl) {
    document.getElementById('row-distfed').style.display = 'flex';
    if (document.getElementById('label-distfed'))
      document.getElementById('label-distfed').textContent = `Distritos Federales — ${cfg.label}`;
    fetch(cfg.distFedUrl)
      .then(r => { if (!r.ok) throw new Error(`HTTP ${r.status}`); return r.json(); })
      .then(data => {
        if (estadoActual !== key) return;
        layerDistFed = L.geoJSON(data, {
          style: { color: '#1565c0', weight: 1.5, fillOpacity: 0.0, dashArray: '6,3' },
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
      .catch(err => {
        console.warn('Dist. Federal fetch error:', err);
        document.getElementById('row-distfed').style.display = 'none';
      });
  }

  // PASO 4: Distritos Locales
  if (cfg.distLocUrl) {
    document.getElementById('row-distloc').style.display = 'flex';
    if (document.getElementById('label-distloc'))
      document.getElementById('label-distloc').textContent = `Distritos Locales — ${cfg.label}`;
    fetch(cfg.distLocUrl)
      .then(r => { if (!r.ok) throw new Error(`HTTP ${r.status}`); return r.json(); })
      .then(data => {
        if (estadoActual !== key) return;
        layerDistLoc = L.geoJSON(data, {
          style: { color: '#6a1b9a', weight: 1.5, fillOpacity: 0.0, dashArray: '4,4' },
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
      .catch(err => {
        console.warn('Dist. Local fetch error:', err);
        document.getElementById('row-distloc').style.display = 'none';
      });
  }

  // PASO 5: Colonias
  if (cfg.coloniaUrl) {
    document.getElementById('row-colonias').style.display = 'flex';
    if (document.getElementById('label-colonias'))
      document.getElementById('label-colonias').textContent = `Colonias — ${cfg.label}`;
    fetch(cfg.coloniaUrl)
      .then(r => { if (!r.ok) throw new Error(`HTTP ${r.status}`); return r.json(); })
      .then(data => {
        if (estadoActual !== key) return;
        layerColonias = L.geoJSON(data, {
          style: { color: '#00838f', weight: 0.8, fillColor: '#e0f7fa', fillOpacity: 0.0 },
          onEachFeature: (feature, layer) => {
            const p = feature.properties;
            const nombre = p.nombre || p.NOMBRE || p.colonia || p.nom_colonia || '—';
            layer.bindTooltip(`<span style="font-family:IBM Plex Sans,sans-serif;font-size:11px;font-weight:600;color:#00838f;">${nombre}</span>`,
              { sticky:true, direction:'top', offset:[0,-8], opacity:0.95 });
          }
        });
        if (capasVisibles.colonias) layerColonias.addTo(map);
      })
      .catch(err => {
        console.warn('Colonias fetch error:', err);
        document.getElementById('row-colonias').style.display = 'none';
      });
  }

  // PASO 6: secciones — fetch lazy si hay URL
  if (cfg.seccionUrl) {
    document.getElementById('stat-secciones').textContent = '…';
    mostrarToast(`Cargando secciones de ${cfg.label}…`);

    fetch(cfg.seccionUrl)
      .then(r => {
        if (!r.ok) throw new Error(`HTTP ${r.status}`);
        return r.json();
      })
      .then(secData => {
        // Verificar que el estado no cambió mientras cargaba
        if (estadoActual !== key) return;
        cargarSecciones(secData);
        document.getElementById('row-secciones').style.display = 'flex';
        document.getElementById('row-labels').style.display    = 'flex';
        // Re-pintar eventos sobre las nuevas secciones cargadas (incluye los sin lat/lng)
        repintarEventos();
        actualizarBadge();
        pintarActivaciones();
      })
      .catch(err => {
        if (estadoActual !== key) return;
        document.getElementById('stat-secciones').textContent = '—';
        mostrarToast(`No se encontró seccion-${key}.geojson`);
        console.warn('Secciones fetch error:', err);
      });
  }
}

// ──────────────────────────────────────────────
// Toggle capas del panel
// ──────────────────────────────────────────────
function regresarEstructura() {
  if (VIST_RETURN_URL) window.location.href = VIST_RETURN_URL;
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

// Imprimir mapa: agranda etiquetas, cambia a capa con detalle de calles y abre el diálogo de impresión
function imprimirMapa() {
    const escalaPrevia = escalaLabelsActual;
    setEscalaLabels(3);
    const selectBase = document.getElementById('select-capa-base');
    if (selectBase) selectBase.value = 'osm';
    cambiarCapaBase('osm');
    setTimeout(() => {
        map.invalidateSize();
        window.print();
    }, 300);
    window.onafterprint = () => {
        setEscalaLabels(escalaPrevia);
        window.onafterprint = null;
    };
}

function toggleCapa(nombre) {
  capasVisibles[nombre] = !capasVisibles[nombre];
  document.getElementById(`toggle-${nombre}`).classList.toggle('on', capasVisibles[nombre]);

  const capaMap = { municipio: layerMunicipio, secciones: layerSecciones, labels: layerLabels, distFed: layerDistFed, distLoc: layerDistLoc, colonias: layerColonias };
  const capa = capaMap[nombre];
  if (!capa) return;
  capasVisibles[nombre] ? map.addLayer(capa) : map.removeLayer(capa);

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

// ──────────────────────────────────────────────
// Click en mapa: deseleccionar
// ──────────────────────────────────────────────
map.on('click', function(e) {
  if (seleccionandoSeccion) {
    seleccionandoSeccion = false;
    map.getContainer().style.cursor = '';
    let found = null;
    if (layerSecciones) {
      layerSecciones.eachLayer(layer => {
        if (found) return;
        try { if (layer.getBounds && layer.getBounds().contains(e.latlng)) found = layer; } catch(_) {}
      });
    }
    if (found && found.feature) {
      const p = found.feature.properties;
      const latlng = found.getBounds().getCenter();
      seccionParaEvento = { num: p.seccion, latlng };
    } else {
      seccionParaEvento = { num: '—', latlng: e.latlng };
    }
    setTimeout(() => {
      actualizarSelectorSeccion();
      document.getElementById('modal-overlay').classList.add('open');
    }, 80);
    return;
  }

  if (seccionActiva && layerSecciones)  { layerSecciones.resetStyle(seccionActiva);  seccionActiva = null; }
  if (municipioActivo && layerMunicipio) { layerMunicipio.resetStyle(municipioActivo); municipioActivo = null; }
  document.getElementById('info-panel').classList.remove('visible');
  // Reset DB panel
  document.getElementById('db-panel').style.display        = 'none';
  document.getElementById('db-no-data').style.display      = 'none';
  document.getElementById('db-spinner').style.display      = 'none';
  document.getElementById('btn-ver-detalles').style.display = 'none';
  seccionDbData = null;
});


// ══════════════════════════════════════════════════════════════
//  PINTAR ZONA — Región → Zona (igual que territorio_ce.php)
// ══════════════════════════════════════════════════════════════
function onRegionChange() {
  const region  = document.getElementById('sel-region').value;
  const selZona = document.getElementById('sel-zona');
  selZona.innerHTML = '<option value="">— Zona —</option>';
  selZona.disabled  = true;
  limpiarZona();
  if (!region) return;

  const estadoLabel = (estadoActual && ESTADOS_DATA[estadoActual]) ? ESTADOS_DATA[estadoActual].label : null;
  if (!estadoLabel) return;
  const regs = ESTRUCTURA_ZONAS_POR_ESTADO[estadoLabel] || [];
  const regData = regs.find(r => r.nombre === region);
  if (!regData || !regData.zonas || regData.zonas.length === 0) {
    mostrarToast('Sin zonas registradas para esta región');
    return;
  }
  regData.zonas.forEach(z => {
    const opt = document.createElement('option');
    opt.value = z.nombre; opt.textContent = z.nombre;
    selZona.appendChild(opt);
  });
  selZona.disabled = false;
}

async function onZonaChange() {
  const zona   = document.getElementById('sel-zona').value;
  const region = document.getElementById('sel-region').value;
  limpiarZona();
  if (!zona) return;

  const estadoLabel = (estadoActual && ESTADOS_DATA[estadoActual]) ? ESTADOS_DATA[estadoActual].label : null;
  if (!estadoLabel) return;
  const regs = ESTRUCTURA_ZONAS_POR_ESTADO[estadoLabel] || [];
  const regData  = regs.find(r => r.nombre === region);
  const zonaData = regData?.zonas?.find(z => z.nombre === zona);
  const secciones = zonaData?.secciones || [];

  if (secciones.length === 0) {
    mostrarToast('Sin secciones registradas para esta zona');
    return;
  }

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

  try {
    const secSet = new Set(secciones.map(String));
    const bounds = [];
    layerZonaResaltada = L.layerGroup();

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
      mostrarToast(`Zona "${zona}": ${secSet.size} secciones`);
    } else {
      mostrarToast('Activa la capa Secciones para ver el resaltado');
    }
  } catch(e) {
    mostrarToast('Error al cargar secciones de la zona');
  }
}

function limpiarZona() {
  if (layerZonaResaltada) { map.removeLayer(layerZonaResaltada); layerZonaResaltada = null; }
}

// ══════════════════════════════════════════════════════════════
//  FILTROS DE FECHA — eventos y activaciones por año/mes
// ══════════════════════════════════════════════════════════════
function aplicarFiltros() {
  const year  = document.getElementById('filter-year').value;
  const month = document.getElementById('filter-month').value;

  _eventoOffsets = {};
  layerEventos.clearLayers();
  eventosFiltrados().forEach(ev => {
    if (!ev.fecha) { colocarMarkerEvento(ev); return; }
    const [y, m] = ev.fecha.split('-');
    if (year  && y !== year)  return;
    if (month && m !== month) return;
    colocarMarkerEvento(ev);
  });

  _activacionOffsets = {};
  layerActivaciones.clearLayers();
  activacionesFiltradas().forEach(act => {
    const fecha = act.fecha || act.fecha_actividad || '';
    if (fecha) {
      const [y, m] = fecha.split('-');
      if (year  && y !== year)  return;
      if (month && m !== month) return;
    }
    colocarMarkerActivacion(act);
  });

  const evCount  = layerEventos.getLayers().length;
  const actCount = layerActivaciones.getLayers().length;
  document.getElementById('ev-count-badge').textContent  = evCount;
  document.getElementById('act-count-badge').textContent = actCount;

  if (year || month) {
    const mLabel = month ? document.getElementById('filter-month').options[document.getElementById('filter-month').selectedIndex].text : '';
    mostrarToast(`Filtro: ${[year, mLabel].filter(Boolean).join(' / ')} — ${evCount} eventos, ${actCount} activaciones`);
  }
}

function limpiarFiltros() {
  document.getElementById('filter-year').value  = '';
  document.getElementById('filter-month').value = '';
  aplicarFiltros();
}

// ──────────────────────────────────────────────
// Eventos — persisten en DB via agenda/procesar.php
// ──────────────────────────────────────────────
const AGENDA_API = '/api/agenda/procesar.php'; // mismo endpoint que agenda_circ.php
let eventosData   = [];          // eventos cargados de DB
let capasVisiblesEventos = false;          // apagado hasta seleccionar estado
const layerEventos = L.layerGroup();        // se agrega al mapa al elegir estado

let seleccionandoSeccion = false;
let seccionParaEvento    = null;

// ── Cargar desde DB y pintar ──
async function cargarYPintarEventos() {
  try {
    const res  = await fetch(AGENDA_API + '?action=list');
    const data = await res.json();
    if (!data.ok) return;
    eventosData = data.eventos || [];
    repintarEventos();
    actualizarBadge();
  } catch(e) {
    console.warn('cargarYPintarEventos error:', e);
  }
}

// ── Helpers: filtrar por el estado seleccionado ──
function _estadoLabelActual() {
  return (estadoActual && ESTADOS_DATA[estadoActual]) ? ESTADOS_DATA[estadoActual].label : null;
}
function _sinAcentos(s) {
  return String(s).normalize('NFD').replace(/[\u0300-\u036f]/g, '');
}
function _mismoEstado(a, b) {
  return !!a && !!b &&
    _sinAcentos(a).trim().toUpperCase() === _sinAcentos(b).trim().toUpperCase();
}
function eventosFiltrados() {
  const lbl = _estadoLabelActual();
  if (!lbl) return [];
  return eventosData.filter(ev => _mismoEstado(ev.estado, lbl));
}

function repintarEventos() {
  layerEventos.clearLayers();
  eventosFiltrados().forEach(ev => colocarMarkerEvento(ev));
}

// Repintar una vez que se carguen secciones de un estado
function repintarEventosTras(estadoLabel) {
  // Pequeño delay para que el layer se termine de cargar
  setTimeout(() => {
    layerEventos.clearLayers();
    eventosFiltrados().forEach(ev => colocarMarkerEvento(ev));
    actualizarBadge();
  }, 500);
}

function actualizarBadge() {
  const badge = document.getElementById('ev-count-badge');
  const lbl = _estadoLabelActual();
  if (!lbl) { badge.style.display = 'none'; badge.textContent = '0'; return; }
  badge.textContent = eventosFiltrados().length;
  badge.style.display = '';
}

// ── Toggle capa eventos ──
function toggleCapaEventos() {
  capasVisiblesEventos = !capasVisiblesEventos;
  document.getElementById('toggle-eventos').classList.toggle('on', capasVisiblesEventos);
  capasVisiblesEventos ? map.addLayer(layerEventos) : map.removeLayer(layerEventos);
}

// ──────────────────────────────────────────────
// Activaciones en mapa (mismo comportamiento que Eventos: por estado)
// ──────────────────────────────────────────────
const ACTIVACIONES_MAPA = <?= json_encode($activacionesMapa, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const layerActivaciones = L.layerGroup();
let capasVisiblesActivaciones = false;
let _activacionOffsets = {};

// Jitter: separa pines que caen en el mismo punto
function jitteredLatLng(lat, lng, offsetMap) {
  const key = parseFloat(lat).toFixed(4) + ',' + parseFloat(lng).toFixed(4);
  const idx = offsetMap[key] !== undefined ? offsetMap[key] : 0;
  offsetMap[key] = idx + 1;
  if (idx === 0) return [lat, lng];
  const radio  = 0.00028;
  const angulo = ((idx - 1) / 6) * 2 * Math.PI;
  return [parseFloat(lat) + radio * Math.sin(angulo),
          parseFloat(lng) + radio * Math.cos(angulo)];
}

const TIPO_LABELS_ACT = {
  territorial:'Territorial', digital:'Digital',
  simbolica:'Simbólica / Impacto', estructura:'Estructura',
};
const TIPO_COLORS_ACT = {
  territorial:'#1a7a3c', digital:'#2196f3',
  simbolica:'#8b5cf6', estructura:'#f59e0b',
};
const ESTATUS_STYLES_ACT = {
  programada:  'background:#fff7ed;color:#d97706;border:1px solid #fde68a;',
  'en-curso':  'background:#eff6ff;color:#1e88e5;border:1px solid #bfdbfe;',
  completada:  'background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0;',
  cancelada:   'background:#fff5f5;color:#e53935;border:1px solid #fecaca;',
};

function activacionesFiltradas() {
  const lbl = _estadoLabelActual();
  if (!lbl) return [];
  return ACTIVACIONES_MAPA.filter(act => _mismoEstado(act.estado, lbl));
}

function pintarActivaciones() {
  _activacionOffsets = {};
  layerActivaciones.clearLayers();
  activacionesFiltradas().forEach(act => colocarMarkerActivacion(act));
  actualizarBadgeActivaciones();
}

function actualizarBadgeActivaciones() {
  const badge = document.getElementById('act-count-badge');
  const lbl = _estadoLabelActual();
  if (!lbl) { badge.style.display = 'none'; badge.textContent = '0'; return; }
  badge.textContent = activacionesFiltradas().length;
  badge.style.display = '';
}

function toggleCapaActivaciones() {
  capasVisiblesActivaciones = !capasVisiblesActivaciones;
  document.getElementById('toggle-activaciones').classList.toggle('on', capasVisiblesActivaciones);
  capasVisiblesActivaciones ? map.addLayer(layerActivaciones) : map.removeLayer(layerActivaciones);
}

function colocarMarkerActivacion(act) {
  let latlng = null;
  if (act.lat && act.lng && parseFloat(act.lat) !== 0) {
    latlng = [parseFloat(act.lat), parseFloat(act.lng)];
  }
  if (!latlng && act.seccion && layerSecciones) {
    layerSecciones.eachLayer(layer => {
      if (latlng) return;
      try {
        const p = layer.feature?.properties;
        if (p && String(p.seccion) === String(act.seccion)) {
          latlng = layer.getBounds().getCenter();
        }
      } catch(_) {}
    });
  }
  if (!latlng) return;

  const _lat = latlng.lat !== undefined ? latlng.lat : latlng[0];
  const _lng = latlng.lng !== undefined ? latlng.lng : latlng[1];
  latlng = jitteredLatLng(_lat, _lng, _activacionOffsets);

  const color    = TIPO_COLORS_ACT[act.tipo] || '#f59e0b';
  const label    = TIPO_LABELS_ACT[act.tipo]  || act.tipo || '—';
  const estStyle = ESTATUS_STYLES_ACT[act.estatus] || ESTATUS_STYLES_ACT['programada'];
  const estatusLabel = (act.estatus || 'programada').replace('-', ' ').replace(/\b\w/g, ch => ch.toUpperCase());
  const candStr  = act.candidato ? `🙋 ${act.candidato}` : '🚫 Sin candidato';

  const iconHtml = `
    <div class="activacion-pin">
      <div class="activacion-pin-icon" style="background:${color};">
        <i class="fas fa-bolt"></i>
      </div>
      <div class="activacion-pin-label">SEC.${act.seccion || '?'}</div>
    </div>`;

  const popupHtml =
    `<div style="font-family:IBM Plex Sans,sans-serif;min-width:180px;">` +
    `<b style="font-size:13px;display:block;margin-bottom:6px;">${act.titulo || '—'}</b>` +
    `<span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:10px;${estStyle}">${estatusLabel}</span>` +
    `<div style="margin-top:7px;font-size:11px;color:#374151;">` +
    `<span style="display:inline-block;background:${color}20;color:${color};padding:1px 6px;border-radius:6px;font-weight:700;font-size:10px;margin-bottom:4px;">${label}</span><br>` +
    `<i class="fas fa-map-marker-alt" style="color:#6b7280;margin-right:3px;"></i>` +
    `<span style="font-family:IBM Plex Mono,monospace;color:${color};">Sección ${act.seccion || '?'}</span>` +
    (act.fecha ? ` — ${act.fecha}` : '') +
    `<br><i class="fas fa-user" style="color:#6b7280;margin-right:3px;"></i>${act.coordinador || '—'}` +
    `<br><span style="color:#6b7280;">${candStr}</span>` +
    `</div></div>`;

  const marker = L.marker(latlng, {
    icon: L.divIcon({ className:'activacion-marker', html: iconHtml, iconSize:[40,44], iconAnchor:[13,38] }),
    interactive: true
  });
  marker.bindPopup(popupHtml, { maxWidth: 260 });
  layerActivaciones.addLayer(marker);
}

// ── Constantes agenda ──
const SUBTIPOS_M = {
  voto:         ['Casa por casa','Evento de campaña','Mitin','Brigada vecinal','Recorrido territorial','Firma de compromisos','Contacto directo'],
  comunicacion: ['Entrevista de radio','Entrevista de TV','Rueda de prensa','Publicación redes sociales','Spot / grabación','Vocería','Evento mediático'],
  organizacion: ['Reunión de estructura','Capacitación','Coordinación interna','Planeación estratégica','Logística','Revisión de presupuesto','Reunión de equipo']
};
const EJE_LABELS_M = { voto:'🗳️ Voto / Territorio', comunicacion:'📣 Comunicación', organizacion:'⚙️ Organización' };
const EJE_COLORS_M = { voto:'#1a7a3c', comunicacion:'#2196f3', organizacion:'#9c27b0' };
let ejeSeleccionadoM = null;
let editandoIdM      = null;  // id del evento en edición desde el mapa

function seleccionarEjeM(eje) {
  ejeSeleccionadoM = eje;
  document.querySelectorAll('.eje-btn-ag').forEach(b =>
    b.className = b.dataset.eje === eje ? `eje-btn-ag sel-${eje}` : 'eje-btn-ag'
  );
  const sel = document.getElementById('m-subtipo');
  sel.innerHTML = '<option value="">Selecciona subtipo…</option>';
  (SUBTIPOS_M[eje] || []).forEach(s => {
    const o = document.createElement('option'); o.value = s; o.textContent = s; sel.appendChild(o);
  });
}

function calcHorasM() {
  const ini   = document.getElementById('m-inicio').value;
  const fin   = document.getElementById('m-fin').value;
  const errEl = document.getElementById('m-hora-error');
  if (ini && fin) {
    const [hi,mi] = ini.split(':').map(Number), [hf,mf] = fin.split(':').map(Number);
    const diff = (hf*60+mf - hi*60-mi) / 60;
    if (diff <= 0) {
      if (errEl) { errEl.style.display='block'; setTimeout(()=>errEl.style.display='none',3000); }
      return;
    }
    if (errEl) errEl.style.display = 'none';
  }
}

// ── Modal ──
function poblarSelectHorasM(selId, valorActual='') {
  const sel = document.getElementById(selId);
  if (!sel) return;
  const cur = valorActual || sel.value;
  sel.innerHTML = '<option value="">-- : --</option>';
  for (let h = 0; h < 24; h++) {
    for (let m of [0, 30]) {
      const hh   = String(h).padStart(2,'0');
      const mm   = String(m).padStart(2,'0');
      const val  = `${hh}:${mm}`;
      const h12  = h % 12 || 12;
      const ampm = h < 12 ? 'am' : 'pm';
      const o    = document.createElement('option');
      o.value = val; o.textContent = `${h12}:${mm} ${ampm}`;
      if (val === cur) o.selected = true;
      sel.appendChild(o);
    }
  }
}

function abrirModal() {
  // Reset form
  ejeSeleccionadoM = null;
  document.querySelectorAll('.eje-btn-ag').forEach(b => b.className = 'eje-btn-ag');
  document.getElementById('m-subtipo').innerHTML = '<option value="">— Selecciona un eje primero —</option>';
  poblarSelectHorasM('m-inicio');
  poblarSelectHorasM('m-fin');
  ['m-titulo','m-responsable','m-notas'].forEach(id => {
    document.getElementById(id).value = '';
  });
  // Fecha = hoy por defecto
  const hoy = new Date();
  document.getElementById('m-fecha').value =
    `${hoy.getFullYear()}-${String(hoy.getMonth()+1).padStart(2,'0')}-${String(hoy.getDate()).padStart(2,'0')}`;

  // Pre-llenar sección si hay activa en el mapa
  if (seccionActiva) {
    const p = seccionActiva.feature.properties;
    const latlng = L.geoJSON(seccionActiva.feature).getBounds().getCenter();
    seccionParaEvento = {
      num:       p.seccion,
      latlng,
      estado:    ESTADOS_DATA[estadoActual]?.label || '',
      municipio: p.nombre_mun || p.NOM_MUN || ''
    };
  }
  actualizarSelectorSeccion();
  document.getElementById('modal-overlay').classList.add('open');
}

function cerrarModal() {
  editandoIdM = null;
  const delBtn = document.getElementById('m-del-btn');
  if (delBtn) delBtn.style.visibility = 'hidden';
  document.getElementById('modal-overlay').classList.remove('open');
  seleccionandoSeccion = false;
}
function cerrarModalDet() {
  document.getElementById('modal-det').classList.remove('open');
}

function cerrarModalOverlay(e) {
  if (e.target === document.getElementById('modal-overlay')) cerrarModal();
}

function activarSeleccionSeccion() {
  cerrarModal();
  seleccionandoSeccion = true;
  map.getContainer().style.cursor = 'crosshair';
  mostrarToast('Haz clic en una sección del mapa');
}

function actualizarSelectorSeccion() {
  const badge = document.getElementById('seccion-badge-text');
  if (seccionParaEvento && seccionParaEvento.num) {
    badge.className = 'seccion-badge';
    badge.innerHTML = `<i class="fas fa-map-marker-alt"></i> Sección ${seccionParaEvento.num}`;
    document.getElementById('m-estado').value    = seccionParaEvento.estado    || '';
    document.getElementById('m-municipio').value = seccionParaEvento.municipio || '';
  } else {
    badge.className = 'seccion-badge empty';
    badge.innerHTML = `<i class="fa-solid fa-map-marker-alt"></i> Ninguna sección seleccionada`;
    document.getElementById('m-estado').value    = '';
    document.getElementById('m-municipio').value = '';
  }
}

// ── Guardar en DB ──
async function guardarEvento() {
  const titulo = document.getElementById('m-titulo').value.trim();
  const fecha  = document.getElementById('m-fecha').value;
  const btn    = document.getElementById('m-save-btn');

  if (!titulo)           { document.getElementById('m-titulo').focus(); mostrarToast('El título es obligatorio'); return; }
  if (!fecha)            { mostrarToast('La fecha es obligatoria'); return; }
  if (!ejeSeleccionadoM) { mostrarToast('Selecciona un eje'); return; }
  if (!editandoIdM && !seccionParaEvento) { mostrarToast('Selecciona una sección en el mapa'); return; }

  const ini  = document.getElementById('m-inicio').value;
  const fin  = document.getElementById('m-fin').value;
  let horas = 0;
  if (ini && fin) {
    const [hi,mi] = ini.split(':').map(Number), [hf,mf] = fin.split(':').map(Number);
    horas = Math.max(0, (hf*60+mf - hi*60-mi) / 60);
  }
  if (!horas) horas = 1;

  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando…';
  btn.disabled  = true;

  const secNum   = seccionParaEvento ? seccionParaEvento.num : (document.getElementById('seccion-badge-text').dataset.num || '');
  const estado   = document.getElementById('m-estado').value || (seccionParaEvento?.estado || '');
  const municipio= document.getElementById('m-municipio').value || (seccionParaEvento?.municipio || '');

  const fd = new FormData();
  fd.append('action',      editandoIdM ? 'update' : 'create');
  if (editandoIdM) fd.append('id', editandoIdM);
  fd.append('titulo',      titulo);
  fd.append('fecha',       fecha);
  fd.append('eje',         ejeSeleccionadoM);
  fd.append('subtipo',     document.getElementById('m-subtipo').value);
  fd.append('seccion',     secNum);
  fd.append('estado',      estado);
  fd.append('municipio',   municipio);
  fd.append('inicio',      ini);
  fd.append('fin',         fin);
  fd.append('horas',       horas);
  fd.append('responsable', document.getElementById('m-responsable').value.trim());
  fd.append('notas',       document.getElementById('m-notas').value.trim() ||
                           (seccionParaEvento ? `Evento registrado desde el mapa electoral. Sección ${secNum}.` : ''));
  if (seccionParaEvento?.latlng) {
    fd.append('lat', seccionParaEvento.latlng.lat ?? seccionParaEvento.latlng[0] ?? '');
    fd.append('lng', seccionParaEvento.latlng.lng ?? seccionParaEvento.latlng[1] ?? '');
  }

  try {
    const res  = await fetch(AGENDA_API, { method: 'POST', body: fd });
    const text = await res.text();
    let data;
    try { data = JSON.parse(text); } catch(_) {
      console.error('Respuesta no-JSON:', text);
      mostrarToast('Error del servidor. Revisa la consola (F12).');
      return;
    }
    if (!data.ok) throw new Error(data.msg || 'Error al guardar');
    await cargarYPintarEventos();
    cerrarModal();
    mostrarToast(editandoIdM ? 'Evento actualizado' : `Evento guardado — Sec. ${secNum}`);
  } catch(err) {
    mostrarToast('Error: ' + err.message);
    console.error(err);
  } finally {
    btn.innerHTML = '<i class="fas fa-check"></i> Guardar Evento';
    btn.disabled  = false;
  }
}

async function eliminarEventoMapa(id) {
  if (!id || !confirm('¿Eliminar este evento?')) return;
  const fd = new FormData();
  fd.append('action', 'delete');
  fd.append('id', id);
  try {
    const res  = await fetch(AGENDA_API, { method: 'POST', body: fd });
    const data = await res.json();
    if (!data.ok) throw new Error(data.msg);
    editandoIdM = null;
    await cargarYPintarEventos();
    cerrarModal();
    mostrarToast('Evento eliminado');
  } catch(err) { mostrarToast('Error: ' + err.message); }
}

// ── Ver detalle de evento ──
function verDetalleEvento(ev) {
  document.getElementById('det-titulo').textContent  = ev.titulo || '—';
  const b  = document.getElementById('det-eje-badge');
  const ej = ev.eje || 'voto';
  b.textContent  = EJE_LABELS_M[ej] || ej;
  b.style.cssText = `background:${EJE_COLORS_M[ej]}20;color:${EJE_COLORS_M[ej]};border-color:${EJE_COLORS_M[ej]}50;`;
  document.getElementById('det-subtipo').textContent    = ev.subtipo    || '—';
  document.getElementById('det-fecha').textContent      = ev.fecha      || '—';
  document.getElementById('det-estado').textContent     = ev.estado     || '—';
  document.getElementById('det-municipio').textContent  = ev.municipio  || '—';
  document.getElementById('det-seccion').textContent    = ev.seccion    ? `Sección ${ev.seccion}` : '—';
  document.getElementById('det-responsable').textContent= ev.responsable|| '—';
  document.getElementById('det-horas').textContent      = ev.horas      ? ev.horas + 'h' : '—';
  const fmtH = t => { if(!t)return'—'; const[h,m]=t.split(':'); const hh=parseInt(h); return `${hh%12||12}:${m} ${hh>=12?'p.m.':'a.m.'}`; };
  document.getElementById('det-horario').textContent = (ev.inicio||ev.fin) ? `${fmtH(ev.inicio)} – ${fmtH(ev.fin)}` : '—';
  document.getElementById('det-notas').textContent   = ev.notas || 'Sin notas.';
  document.getElementById('modal-det').classList.add('open');
}

// ── Pintar marker en el mapa ──
function colocarMarkerEvento(ev) {
  // Calcular posición: lat/lng guardados, o centroide de la sección en el layer, o API
  let latlng = null;
  if (ev.lat && ev.lng && parseFloat(ev.lat) !== 0) {
    latlng = [parseFloat(ev.lat), parseFloat(ev.lng)];
  } else if (layerSecciones) {
    layerSecciones.eachLayer(layer => {
      if (latlng) return;
      try {
        const p = layer.feature?.properties;
        if (p && String(p.seccion) === String(ev.seccion)) {
          latlng = layer.getBounds().getCenter();
        }
      } catch(_) {}
    });
  }
  // Si todavía no hay coords, intentar buscar en todas las capas de secciones cargadas
  if (!latlng) {
    map.eachLayer(layer => {
      if (latlng) return;
      if (layer.feature?.properties) {
        const p = layer.feature.properties;
        if (String(p.seccion) === String(ev.seccion)) {
          try { latlng = L.geoJSON(layer.feature).getBounds().getCenter(); } catch(_) {}
        }
      }
    });
  }
  if (!latlng) return; // sin coordenadas no se puede pintar

  const ejeColors = { voto: '#1a7a3c', comunicacion: '#2196f3', organizacion: '#9c27b0' };
  const color = ejeColors[ev.eje] || '#1a7a3c';

  const iconHtml = `
    <div class="evento-pin">
      <div class="evento-pin-icon" style="background:${color};"><i class="fa-solid fa-location-dot"></i></div>
      <div class="evento-pin-label">SEC.${ev.seccion || '?'}</div>
    </div>`;

  const marker = L.marker(latlng, {
    icon: L.divIcon({ className: 'evento-marker', html: iconHtml, iconSize: [40, 44], iconAnchor: [13, 38] }),
    interactive: true
  });

  const fechaStr = ev.fecha ? ` — ${ev.fecha}` : '';
  const ejeStr   = ev.eje   ? ` · ${ev.eje.charAt(0).toUpperCase() + ev.eje.slice(1)}` : '';
  const popupHtml =
    `<div style="font-family:IBM Plex Sans,sans-serif;min-width:160px;">` +
    `<b style="font-size:13px;display:block;margin-bottom:4px;">${ev.titulo || '—'}</b>` +
    `<span style="font-family:IBM Plex Mono,monospace;font-size:11px;color:${color};">Sección ${ev.seccion || '?'}${fechaStr}</span>` +
    `<br><span style="font-size:11px;color:#666;">${ejeStr.replace(' · ','')}</span>` +
    `<br><button onclick="verDetalleEvento(${JSON.stringify(ev).replace(/"/g,'&quot;')})" ` +
    `style="margin-top:8px;padding:5px 10px;background:${color};color:white;border:none;border-radius:6px;` +
    `font-size:11px;font-weight:700;cursor:pointer;font-family:IBM Plex Sans,sans-serif;">` +
    `Ver detalle</button></div>`;
  marker.bindPopup(popupHtml, { maxWidth: 260 });
  layerEventos.addLayer(marker);
}

// Cargar eventos al inicio
cargarYPintarEventos();

// ── MODO VISTA: auto-cargar el estado de la zona ──
if (MODO_VISTA && VIST_ESTADO_KEY) {
  const sel = document.getElementById('estado-select');
  if (sel) sel.value = VIST_ESTADO_KEY;
  cambiarEstado(VIST_ESTADO_KEY);
}

// ──────────────────────────────────────────────
// Datos de sección desde BD
// ──────────────────────────────────────────────
async function cargarDatosSeccion(numSeccion) {
  // Reset
  document.getElementById('db-panel').style.display   = 'none';
  document.getElementById('db-no-data').style.display = 'none';
  document.getElementById('db-spinner').style.display = 'block';

  const fmt = n => (n != null && n !== '' && !isNaN(n))
    ? parseInt(n).toLocaleString('es-MX') : '—';
  const pct = (c, m) => (!m || m == 0) ? 0 : Math.min(100, Math.round(c / m * 100));

  try {
    const res  = await fetch(`/api/secciones/secciones_api.php?action=get_by_num&seccion=${encodeURIComponent(numSeccion)}`);
    const data = await res.json();

    document.getElementById('db-spinner').style.display = 'none';

    if (!data.ok || !data.seccion) {
      document.getElementById('db-no-data').style.display = 'block';
      return;
    }

    const s = data.seccion;

    // Zona
    document.getElementById('db-region').textContent = s.region_asignada || '—';
    document.getElementById('db-zona').textContent   = s.zona_asignada   || '—';

    // Lista nominal + grupos de edad
    document.getElementById('db-nominal').textContent = fmt(s.lista_nominal);
    const edadRows = [
      ['18-24', s.grupo_edad_18_24], ['25-34', s.grupo_edad_25_34],
      ['35-44', s.grupo_edad_35_44], ['45-54', s.grupo_edad_45_54],
      ['55+',   s.grupo_edad_55_mas],
    ].filter(([, v]) => v > 0);
    document.getElementById('db-edad-chips').innerHTML = edadRows
      .map(([l, v]) => `<span class="edad-chip">${l}: ${fmt(v)}</span>`).join('');

    // Contactos + barra
    const meta = parseInt(s.meta_contactos) || 0;
    const cons = parseInt(s.contactos_conseguidos) || 0;
    const p    = pct(cons, meta);
    document.getElementById('db-meta').textContent      = fmt(meta);
    document.getElementById('db-contactos').textContent = fmt(cons);
    document.getElementById('db-progress-fill').style.width = p + '%';
    document.getElementById('db-pct').textContent  = p + '%';
    document.getElementById('db-rem').textContent  = fmt(Math.max(0, meta - cons)) + ' rest.';

    // Resultados
    document.getElementById('db-eventos').textContent = fmt(s.eventos_realizados);
    document.getElementById('db-votos').textContent   = fmt(s.metas_votos);

    // Activaciones
    document.getElementById('db-act-total').textContent = fmt(s.activaciones_totales);
    document.getElementById('db-act-con').textContent   = fmt(s.con_candidato);
    document.getElementById('db-act-sin').textContent   = fmt(s.sin_candidato);

    // Campos abiertos
    const setOpen = (wrapId, textId, val) => {
      const ok = val && val.trim();
      document.getElementById(wrapId).style.display = ok ? 'block' : 'none';
      if (ok) document.getElementById(textId).textContent = val;
    };
    setOpen('db-nec-wrap', 'db-necesidades', s.principales_necesidades);
    setOpen('db-enf-wrap', 'db-enfoque',     s.enfoque);
    setOpen('db-per-wrap', 'db-perfil',      s.perfil_socioeconomico);

    document.getElementById('db-panel').style.display = 'block';

    // Merge datos BD sobre los datos GeoJSON ya guardados en seccionDbData
    seccionDbData = Object.assign(seccionDbData || {}, s);

  } catch (err) {
    document.getElementById('db-spinner').style.display = 'none';
    document.getElementById('db-no-data').style.display = 'block';
    console.warn('cargarDatosSeccion error:', err);
  }
}

// ──────────────────────────────────────────────
// Modal Detalle Sección
// ──────────────────────────────────────────────

async function abrirModalDetalle() {
  if (!seccionDbData) return;

  const numSeccion  = seccionDbData.seccion || seccionDbData.nombre_seccion;
  const estadoLabel = ESTADOS_DATA[estadoActual]?.label || '';

  // Mostrar modal con spinner mientras carga
  const overlay = document.getElementById('sec-modal-det');
  overlay.classList.add('open');
  document.getElementById('sdet-titulo').textContent    = `SEC. ${numSeccion}`;
  document.getElementById('sdet-ubicacion').textContent = 'Cargando datos…';
  document.getElementById('sdet-spinner').style.display = 'block';
  document.getElementById('sdet-contenido').style.display = 'none';

  const fmt = n => (n != null && n !== '' && !isNaN(n))
    ? parseInt(n).toLocaleString('es-MX') : '—';
  const pct = (c, m) => (!m || m == 0) ? 0 : Math.min(100, Math.round(c / m * 100));

  try {
    const res  = await fetch(`?action=get_seccion_detalle&seccion=${encodeURIComponent(numSeccion)}&estado=${encodeURIComponent(estadoLabel)}`);
    const data = await res.json();

    document.getElementById('sdet-spinner').style.display  = 'none';
    document.getElementById('sdet-contenido').style.display = 'block';

    if (!data.ok || !data.seccion) {
      document.getElementById('sdet-ubicacion').textContent = estadoLabel;
      document.getElementById('sdet-sin-datos').style.display = 'block';
      return;
    }

    const s = data.seccion;

    document.getElementById('sdet-titulo').textContent    = `SEC. ${s.nombre_seccion}`;
    document.getElementById('sdet-ubicacion').textContent = [s.estado, s.municipio].filter(Boolean).join(' › ');
    document.getElementById('sdet-sin-datos').style.display = 'none';

    // Ubicación
    document.getElementById('sdet-seccion').textContent   = s.nombre_seccion  || '—';
    document.getElementById('sdet-municipio').textContent = s.municipio        || '—';
    document.getElementById('sdet-df').textContent        = s.distrito_federal || '—';
    document.getElementById('sdet-dl').textContent        = s.distrito_local   || '—';
    document.getElementById('sdet-region').textContent    = s.region_asignada  || '—';
    document.getElementById('sdet-zona').textContent      = s.zona_asignada    || '—';

    // Lista nominal
    document.getElementById('sdet-padron').textContent   = fmt(s.padron_electoral);
    document.getElementById('sdet-hombres').textContent  = fmt(s.lista_hombres);
    document.getElementById('sdet-mujeres').textContent  = fmt(s.lista_mujeres);

    // Contactos
    const meta = parseInt(s.meta_contactos) || 0;
    const cons = parseInt(s.contactos_conseguidos) || 0;
    const p    = pct(cons, meta);
    document.getElementById('sdet-meta').textContent      = fmt(meta);
    document.getElementById('sdet-contactos').textContent = fmt(cons);
    document.getElementById('sdet-prog-fill').style.width = p + '%';
    document.getElementById('sdet-prog-pct').textContent  = p + '%';
    document.getElementById('sdet-prog-rem').textContent  = fmt(Math.max(0, meta - cons)) + ' restantes';

    // Resultados
    document.getElementById('sdet-eventos').textContent = fmt(s.eventos_realizados);
    document.getElementById('sdet-votos').textContent   = fmt(s.metas_votos);

    // Activaciones
    document.getElementById('sdet-act-total').textContent = fmt(s.activaciones_totales);
    document.getElementById('sdet-act-con').textContent   = fmt(s.con_candidato);
    document.getElementById('sdet-act-sin').textContent   = fmt(s.sin_candidato);

    // Campos abiertos
    const hayCampos = s.principales_necesidades || s.enfoque || s.perfil_socioeconomico;
    document.getElementById('sdet-info-wrap').style.display = hayCampos ? 'block' : 'none';
    const setOpen = (wrapId, textId, val) => {
      const ok = val && val.trim();
      document.getElementById(wrapId).style.display = ok ? 'block' : 'none';
      if (ok) document.getElementById(textId).textContent = val;
    };
    setOpen('sdet-nec-wrap', 'sdet-necesidades', s.principales_necesidades);
    setOpen('sdet-enf-wrap', 'sdet-enfoque',     s.enfoque);
    setOpen('sdet-per-wrap', 'sdet-perfil',      s.perfil_socioeconomico);

    // ── Lista de EVENTOS DE AGENDA reales ──────────────────────────────────
    const ejeColor = { voto:'#2d7a3e', comunicacion:'#1a73e8', organizacion:'#8b00d4' };
    const ejeLabel = { voto:'🗳️ Voto', comunicacion:'📣 Comunicación', organizacion:'⚙️ Organización' };
    const listEv = document.getElementById('sdet-eventos-lista');
    const wrapEv = document.getElementById('sdet-eventos-wrap');
    const evs = s.eventos_lista || [];
    if (evs.length) {
      wrapEv.style.display = 'block';
      listEv.innerHTML = evs.map(ev => {
        const c = ejeColor[ev.eje] || '#666';
        const hora = ev.inicio ? ` · ${ev.inicio.substring(0,5)}` : '';
        return `<div style="display:flex;gap:10px;align-items:flex-start;padding:8px 0;border-bottom:1px solid #f0f0f0;">
          <span style="width:8px;height:8px;border-radius:50%;background:${c};flex-shrink:0;margin-top:5px;"></span>
          <div>
            <div style="font-size:13px;font-weight:700;color:#1a1e2a;">${ev.titulo || '—'}</div>
            <div style="font-size:11px;color:#6b7280;">${ejeLabel[ev.eje]||ev.eje||''} · ${ev.fecha||''}${hora}</div>
            ${ev.responsable ? `<div style="font-size:11px;color:#6b7280;">👤 ${ev.responsable}</div>` : ''}
          </div>
        </div>`;
      }).join('');
    } else {
      wrapEv.style.display = 'block';
      listEv.innerHTML = '<div style="font-size:12px;color:#9ca3af;padding:8px 0;">Sin eventos registrados para esta sección.</div>';
    }

    // ── Lista de ACTIVACIONES reales ───────────────────────────────────────
    const estatusBadge = {
      programada: 'background:#fff7ed;color:#d97706;border:1px solid #fde68a;',
      'en-curso':  'background:#eff6ff;color:#1e88e5;border:1px solid #bfdbfe;',
      completada:  'background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0;',
      cancelada:   'background:#fff5f5;color:#e53935;border:1px solid #fecaca;',
    };
    const listAct = document.getElementById('sdet-act-lista');
    const wrapAct = document.getElementById('sdet-act-lista-wrap');
    const acts = s.activaciones_lista || [];
    if (acts.length) {
      wrapAct.style.display = 'block';
      listAct.innerHTML = acts.map(a => {
        const badge = estatusBadge[a.estatus] || '';
        const cand  = a.candidato ? `🙋 ${a.candidato}` : '🚫 Sin candidato';
        return `<div style="padding:8px 0;border-bottom:1px solid #f0f0f0;">
          <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;">
            <span style="font-size:13px;font-weight:700;color:#1a1e2a;">${a.titulo || '—'}</span>
            <span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:12px;${badge}">${(a.estatus||'').replace('-',' ')}</span>
          </div>
          <div style="font-size:11px;color:#6b7280;margin-top:3px;">${a.fecha_actividad||''} · ${cand}</div>
          ${a.coordinador ? `<div style="font-size:11px;color:#6b7280;">👤 ${a.coordinador}</div>` : ''}
        </div>`;
      }).join('');
    } else {
      wrapAct.style.display = 'block';
      listAct.innerHTML = '<div style="font-size:12px;color:#9ca3af;padding:8px 0;">Sin activaciones registradas para esta sección.</div>';
    }

  } catch (err) {
    document.getElementById('sdet-spinner').style.display  = 'none';
    document.getElementById('sdet-contenido').style.display = 'block';
    document.getElementById('sdet-sin-datos').style.display = 'block';
    document.getElementById('sdet-ubicacion').textContent  = estadoLabel;
    console.warn('abrirModalDetalle error:', err);
  }
}

function cerrarModalDetalle() {
  document.getElementById('sec-modal-det').classList.remove('open');
}

// Toast
function mostrarToast(msg) {
  let t = document.getElementById('toast-ev');
  if (!t) {
    t = document.createElement('div');
    t.id = 'toast-ev';
    t.style.cssText = `
      position:fixed;bottom:24px;left:50%;transform:translateX(-50%);
      background:#1a1e2a;color:#fff;font-family:IBM Plex Sans,sans-serif;
      font-size:12px;padding:8px 16px;border-radius:6px;z-index:9999;
      pointer-events:none;opacity:0;transition:opacity 0.2s;white-space:nowrap;`;
    document.body.appendChild(t);
  }
  t.textContent = msg;
  t.style.opacity = '1';
  clearTimeout(t._timer);
  t._timer = setTimeout(() => { t.style.opacity = '0'; }, 2500);
}

</script>

<!-- ── Modal Agregar Evento (estilo Agenda) ── -->
<div class="modal-overlay" id="modal-overlay" onclick="cerrarModalOverlay(event)">
  <div class="modal" style="background:#fff;border-radius:20px;box-shadow:0 25px 50px rgba(0,0,0,0.25);width:90%;max-width:620px;padding:36px;position:relative;max-height:90vh;overflow-y:auto;">
    <button class="modal-close" onclick="cerrarModal()"><i class="fas fa-times"></i></button>
    <h2 style="font-size:22px;font-weight:700;margin-bottom:4px;color:#111827;" id="m-modal-title">Nuevo Evento</h2>
    <p style="font-size:13px;color:#6b7280;margin-bottom:24px;">Registra un evento en la agenda. La sección se toma del mapa.</p>

    <!-- Sección badge — exclusivo del mapa -->
    <div style="display:flex;flex-direction:column;gap:6px;margin-bottom:18px;">
      <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:#6b7280;">Sección Electoral <span style="font-size:10px;font-weight:400;">(selecciona en el mapa)</span></label>
      <div id="seccion-badge-wrap">
        <span class="seccion-badge empty" id="seccion-badge-text">
          <i class="fa-solid fa-map-marker-alt"></i> Ninguna sección seleccionada
        </span>
      </div>
      <button type="button" onclick="activarSeleccionSeccion()" style="font-size:11px;color:var(--verde);background:none;border:none;cursor:pointer;padding:0;text-align:left;font-family:'IBM Plex Sans',sans-serif;">
        <i class="fas fa-crosshairs"></i> Haz clic aquí, luego en el mapa para cambiar sección
      </button>
    </div>

    <!-- Eje — igual que agenda -->
    <div style="display:flex;flex-direction:column;gap:6px;margin-bottom:18px;">
      <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:#6b7280;">Eje *</label>
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;">
        <button class="eje-btn-ag" data-eje="voto"         onclick="seleccionarEjeM('voto')"         type="button">🗳️ Voto / Territorio</button>
        <button class="eje-btn-ag" data-eje="comunicacion" onclick="seleccionarEjeM('comunicacion')" type="button">📣 Comunicación</button>
        <button class="eje-btn-ag" data-eje="organizacion" onclick="seleccionarEjeM('organizacion')" type="button">⚙️ Organización</button>
      </div>
    </div>

    <!-- Subtipo -->
    <div style="display:flex;flex-direction:column;gap:6px;margin-bottom:18px;">
      <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:#6b7280;">Subtipo</label>
      <select id="m-subtipo" style="width:100%;padding:11px 14px;border-radius:10px;border:1.5px solid #e5e7eb;background:#f9fafb;font-size:14px;font-weight:500;color:#111827;outline:none;font-family:inherit;appearance:none;">
        <option value="">— Selecciona un eje primero —</option>
      </select>
    </div>

    <!-- Título -->
    <div style="display:flex;flex-direction:column;gap:6px;margin-bottom:18px;">
      <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:#6b7280;">Título *</label>
      <input type="text" id="m-titulo" style="width:100%;padding:11px 14px;border-radius:10px;border:1.5px solid #e5e7eb;background:#f9fafb;font-size:14px;font-weight:500;color:#111827;outline:none;font-family:inherit;" placeholder="Ej. Recorrido Colonia Pitic">
    </div>

    <!-- Fecha / Estado / Municipio -->
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-bottom:18px;">
      <div style="display:flex;flex-direction:column;gap:6px;">
        <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:#6b7280;">Fecha *</label>
        <input type="date" id="m-fecha" style="width:100%;padding:11px 14px;border-radius:10px;border:1.5px solid #e5e7eb;background:#f9fafb;font-size:14px;font-weight:500;color:#111827;outline:none;font-family:inherit;">
      </div>
      <div style="display:flex;flex-direction:column;gap:6px;">
        <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:#6b7280;">Estado</label>
        <input type="text" id="m-estado" readonly placeholder="Auto del mapa" style="width:100%;padding:11px 14px;border-radius:10px;border:1.5px solid #e5e7eb;background:#f3f4f6;font-size:14px;font-weight:500;color:#6b7280;outline:none;font-family:inherit;cursor:not-allowed;">
      </div>
      <div style="display:flex;flex-direction:column;gap:6px;">
        <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:#6b7280;">Municipio</label>
        <input type="text" id="m-municipio" readonly placeholder="Auto del mapa" style="width:100%;padding:11px 14px;border-radius:10px;border:1.5px solid #e5e7eb;background:#f3f4f6;font-size:14px;font-weight:500;color:#6b7280;outline:none;font-family:inherit;cursor:not-allowed;">
      </div>
    </div>

    <!-- Inicio / Fin -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:18px;">
      <div style="display:flex;flex-direction:column;gap:6px;">
        <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:#6b7280;">Inicio</label>
        <select id="m-inicio" onchange="calcHorasM()" style="width:100%;padding:11px 14px;border-radius:10px;border:1.5px solid #e5e7eb;background:#f9fafb;font-size:14px;font-weight:500;color:#111827;outline:none;font-family:inherit;appearance:none;">
          <option value="">-- : --</option>
        </select>
      </div>
      <div style="display:flex;flex-direction:column;gap:6px;">
        <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:#6b7280;">Fin</label>
        <select id="m-fin" onchange="calcHorasM()" style="width:100%;padding:11px 14px;border-radius:10px;border:1.5px solid #e5e7eb;background:#f9fafb;font-size:14px;font-weight:500;color:#111827;outline:none;font-family:inherit;appearance:none;">
          <option value="">-- : --</option>
        </select>
        <span id="m-hora-error" style="display:none;font-size:11px;font-weight:700;color:#dc2626;margin-top:2px;">
          <i class="fas fa-exclamation-circle"></i> El fin debe ser posterior al inicio
        </span>
      </div>
    </div>

    <!-- Responsable -->
    <div style="display:flex;flex-direction:column;gap:6px;margin-bottom:18px;">
      <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:#6b7280;">Responsable</label>
      <input type="text" id="m-responsable" style="width:100%;padding:11px 14px;border-radius:10px;border:1.5px solid #e5e7eb;background:#f9fafb;font-size:14px;font-weight:500;color:#111827;outline:none;font-family:inherit;" placeholder="Nombre del responsable">
    </div>

    <!-- Notas -->
    <div style="display:flex;flex-direction:column;gap:6px;margin-bottom:24px;">
      <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:#6b7280;">Notas</label>
      <textarea id="m-notas" style="width:100%;padding:11px 14px;border-radius:10px;border:1.5px solid #e5e7eb;background:#f9fafb;font-size:14px;font-weight:400;color:#111827;outline:none;font-family:inherit;resize:vertical;min-height:80px;" placeholder="Observaciones, materiales, contexto…"></textarea>
    </div>

    <!-- Footer — igual que agenda -->
    <div style="display:flex;justify-content:space-between;align-items:center;padding-top:20px;border-top:1px solid #e5e7eb;">
      <button id="m-del-btn" style="visibility:hidden;padding:11px 24px;border-radius:12px;font-size:14px;font-weight:600;cursor:pointer;border:1px solid #fecaca;background:#fef2f2;color:#dc2626;font-family:inherit;" onclick="eliminarEventoMapa(editandoIdM)">
        <i class="fas fa-trash-alt"></i> Eliminar
      </button>
      <div style="display:flex;gap:10px;">
        <button onclick="cerrarModal()" style="padding:11px 24px;border-radius:12px;font-size:14px;font-weight:600;cursor:pointer;border:1px solid #e5e7eb;background:#fff;color:#6b7280;font-family:inherit;">Cancelar</button>
        <button id="m-save-btn" onclick="guardarEvento()" style="padding:11px 24px;border-radius:12px;font-size:14px;font-weight:600;cursor:pointer;border:none;background:#2d7a3e;color:white;box-shadow:0 2px 8px rgba(45,122,62,0.3);font-family:inherit;">
          <i class="fas fa-check"></i> Guardar Evento
        </button>
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
      <div style="display:flex;gap:10px;">
        <button class="btn-cancel-ag" onclick="cerrarModalDet()">Cerrar</button>
      </div>
    </div>
  </div>
</div>
<!-- ── Modal Detalle Sección (datos de secciones.php) ── -->
<div class="sec-modal-overlay" id="sec-modal-det" onclick="if(event.target===this)cerrarModalDetalle()">
  <div class="sec-modal-box">
    <button class="sec-modal-close" onclick="cerrarModalDetalle()"><i class="fas fa-times"></i></button>
    <div class="sec-modal-title" id="sdet-titulo">—</div>
    <div class="sec-modal-subtitle" id="sdet-ubicacion">—</div>

    <!-- Spinner de carga -->
    <div id="sdet-spinner" style="text-align:center;padding:30px 0;display:none;">
      <i class="fas fa-spinner fa-spin" style="font-size:24px;color:#1a7a3c;"></i>
      <div style="font-size:12px;color:#6b7280;margin-top:8px;">Cargando datos…</div>
    </div>

    <!-- Sin datos -->
    <div id="sdet-sin-datos" style="display:none;background:#f8f9fa;border-radius:8px;padding:14px;font-size:13px;color:#6b7280;margin-bottom:16px;">
      <i class="fas fa-info-circle"></i> Sin datos registrados para esta sección
    </div>

    <!-- Contenido principal -->
    <div id="sdet-contenido" style="display:none;">

      <!-- Ubicación -->
      <div class="sec-section-title"><i class="fas fa-map-marker-alt"></i> Ubicación</div>
      <div class="sec-det-grid">
        <div class="sec-det-item"><label>Sección</label><span id="sdet-seccion">—</span></div>
        <div class="sec-det-item"><label>Municipio</label><span id="sdet-municipio">—</span></div>
        <div class="sec-det-item"><label>Distrito Federal</label><span id="sdet-df">—</span></div>
        <div class="sec-det-item"><label>Distrito Local</label><span id="sdet-dl">—</span></div>
        <div class="sec-det-item"><label>Región Asignada</label><span id="sdet-region">—</span></div>
        <div class="sec-det-item"><label>Zona Asignada</label><span id="sdet-zona">—</span></div>
      </div>

      <!-- Lista Nominal -->
      <div class="sec-section-title"><i class="fas fa-users"></i> Lista Nominal</div>
      <div class="sec-nominal-grid">
        <div class="sec-nominal-card">
          <div class="sec-nominal-num" id="sdet-padron" style="color:#374151;">—</div>
          <div class="sec-nominal-lbl">Padrón Electoral</div>
        </div>
        <div class="sec-nominal-card">
          <div class="sec-nominal-num" id="sdet-hombres" style="color:#3b82f6;">—</div>
          <div class="sec-nominal-lbl"><i class="fas fa-mars" style="color:#3b82f6;"></i> Hombres</div>
        </div>
        <div class="sec-nominal-card">
          <div class="sec-nominal-num" id="sdet-mujeres" style="color:#ec4899;">—</div>
          <div class="sec-nominal-lbl"><i class="fas fa-venus" style="color:#ec4899;"></i> Mujeres</div>
        </div>
      </div>

      <!-- Contactos y Avance -->
      <div class="sec-section-title"><i class="fas fa-address-book"></i> Contactos y Avance</div>
      <div class="sec-det-grid" style="margin-bottom:10px;">
        <div class="sec-det-item">
          <label>Meta de Contactos</label>
          <span id="sdet-meta" style="font-size:20px;font-weight:900;color:#1a1e2a;">—</span>
        </div>
        <div class="sec-det-item">
          <label>Contactos Conseguidos</label>
          <span id="sdet-contactos" style="font-size:20px;font-weight:900;color:#1a7a3c;">—</span>
        </div>
      </div>
      <div class="sec-modal-progress-bg">
        <div class="sec-modal-progress-fill" id="sdet-prog-fill" style="width:0%"></div>
      </div>
      <div class="sec-modal-progress-lbl">
        <span id="sdet-prog-pct">0%</span>
        <span id="sdet-prog-rem">0 restantes</span>
      </div>

      <!-- Resultados -->
      <div class="sec-section-title"><i class="fas fa-calendar-check"></i> Resultados</div>
      <div class="sec-det-grid">
        <div class="sec-det-item"><label>Eventos Realizados</label><span id="sdet-eventos">—</span></div>
        <div class="sec-det-item"><label>Metas de Votos</label><span id="sdet-votos">—</span></div>
      </div>

      <!-- Activaciones -->
      <div class="sec-section-title"><i class="fas fa-bolt"></i> Activaciones Totales</div>
      <div class="sec-act-grid">
        <div class="sec-act-card"><div class="sec-act-num" id="sdet-act-total">—</div><div class="sec-act-lbl">Total</div></div>
        <div class="sec-act-card"><div class="sec-act-num" id="sdet-act-con">—</div><div class="sec-act-lbl">Con Candidato</div></div>
        <div class="sec-act-card"><div class="sec-act-num" id="sdet-act-sin">—</div><div class="sec-act-lbl">Sin Candidato</div></div>
      </div>

      <!-- Lista de activaciones reales -->
      <div id="sdet-act-lista-wrap" style="display:none;margin-top:6px;">
        <div id="sdet-act-lista"></div>
      </div>

      <!-- Lista de eventos de agenda reales -->
      <div id="sdet-eventos-wrap" style="display:none;margin-top:12px;">
        <div class="sec-section-title"><i class="fas fa-calendar-check"></i> Eventos de Agenda</div>
        <div id="sdet-eventos-lista"></div>
      </div>

      <!-- Campos Abiertos -->
      <div id="sdet-info-wrap" style="display:none;">
        <div class="sec-section-title"><i class="fas fa-align-left"></i> Información</div>
        <div class="sec-open-field" id="sdet-nec-wrap" style="display:none;">
          <div class="sec-open-label">Principales Necesidades</div>
          <div class="sec-open-text" id="sdet-necesidades"></div>
        </div>
        <div class="sec-open-field" id="sdet-enf-wrap" style="display:none;">
          <div class="sec-open-label">Enfoque</div>
          <div class="sec-open-text" id="sdet-enfoque"></div>
        </div>
        <div class="sec-open-field" id="sdet-per-wrap" style="display:none;">
          <div class="sec-open-label">Perfil Socioeconómico</div>
          <div class="sec-open-text" id="sdet-perfil"></div>
        </div>
      </div>

    </div><!-- /sdet-contenido -->

    <div class="sec-modal-footer">
      <button class="sec-modal-btn-close" onclick="cerrarModalDetalle()"><i class="fas fa-times"></i> Cerrar</button>
    </div>
  </div>
</div>

</body>
</html>