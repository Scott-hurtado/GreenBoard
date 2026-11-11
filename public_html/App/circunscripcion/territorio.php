<?php
/**
 * Mapa Electoral + Territorio - Coordinador de Circunscripción
 * Al cargar: mapa de México sin capas, selector de estado.
 * Al seleccionar estado: misma funcionalidad que rol estatal.
 */

if (file_exists(__DIR__ . '/../../includes/bootstrap.php')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
} else { session_start(); }

// ── API: guardar secciones en sesión ──
if (isset($_GET['api']) && $_GET['api'] === 'guardar_secs_session') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $_SESSION['mapa_secciones_actuales'] = $body['secciones_actuales'] ?? '';
    $_SESSION['mapa_secciones_ocupadas'] = $body['secciones_ocupadas'] ?? '';
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

$rol = $_SESSION['user_role'] ?? '';
$permitidos = ['coordinador_circunscripcion', 'admin', 'administrador', 'superadmin', 'super admin'];
if (!in_array(strtolower($rol), $permitidos)) { header('Location: /public/index.php'); exit(); }

// ── Para circunscripción el "estado del coordinador" no aplica;
//    el mapa arranca en vista nacional y el usuario elige el estado.
//    Usamos 'Sonora' como fallback para que el PHP de CE no explote,
//    pero el JS arrancará sin estado seleccionado.
$estadoCoordinadorRaw     = '';
$estadoCoordinadorLimpiado = '';

// ── Conexión a BD para Regiones y Zonas ──
$regiones_estatales = [];
$zonas_estatales = [];
try {
    $dbConfigPath = __DIR__ . '/../../includes/database.php';
    if (file_exists($dbConfigPath)) {
        $dbConfig = require $dbConfigPath;
        $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
        $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        
        // 1. Cargar Regiones Reales
        $stmtR = $pdo->prepare("SELECT nombre FROM regiones_estatales WHERE estado = ? ORDER BY nombre");
        $stmtR->execute([$estadoCoordinadorLimpiado]);
        $regiones_estatales = $stmtR->fetchAll(PDO::FETCH_COLUMN);

        // 2. Cargar Zonas Reales
        $stmtZ = $pdo->prepare("SELECT z.nombre FROM zonas_estatales z INNER JOIN regiones_estatales r ON z.estructura_id = r.id WHERE r.estado = ? ORDER BY z.nombre");
        $stmtZ->execute([$estadoCoordinadorLimpiado]);
        $zonas_estatales = $stmtZ->fetchAll(PDO::FETCH_COLUMN);
    }
} catch (Exception $e) { error_log($e->getMessage()); }

// ── Activaciones para el mapa (try/catch propio para no depender del bloque anterior) ──
$activacionesMapa = [];
if (isset($pdo) && !empty($estadoCoordinadorLimpiado)) {
    try {
        // Intentar con candidato primero
        $sqlAct = "
            SELECT id, titulo, tipo, estatus, municipio,
                   DATE_FORMAT(fecha_actividad,'%Y-%m-%d') AS fecha,
                   coordinador, seccion_electoral AS seccion,
                   candidato,
                   latitud AS lat, longitud AS lng
            FROM activaciones
            WHERE activo = 1
              AND UPPER(TRIM(estado)) = UPPER(TRIM(?))
            ORDER BY fecha_actividad DESC
        ";
        $stmtAct = $pdo->prepare($sqlAct);
        $stmtAct->execute([$estadoCoordinadorLimpiado]);
        $activacionesMapa = $stmtAct->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $eAct) {
        // Fallback sin candidato (por si la columna no existe en esta BD)
        try {
            $sqlActFb = "
                SELECT id, titulo, tipo, estatus, municipio,
                       DATE_FORMAT(fecha_actividad,'%Y-%m-%d') AS fecha,
                       coordinador, seccion_electoral AS seccion,
                       NULL AS candidato,
                       latitud AS lat, longitud AS lng
                FROM activaciones
                WHERE activo = 1
                  AND UPPER(TRIM(estado)) = UPPER(TRIM(?))
                ORDER BY fecha_actividad DESC
            ";
            $stmtAct2 = $pdo->prepare($sqlActFb);
            $stmtAct2->execute([$estadoCoordinadorLimpiado]);
            $activacionesMapa = $stmtAct2->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $eAct2) {
            error_log('territorio activaciones_mapa fallback: ' . $eAct2->getMessage());
        }
    }
}

// ── Google Sheets ──
define('GOOGLE_SHEET_ID',   '1PB10SQiyOBoRoD7lIoA4dhSC9ITLvixMP5VrOoIRQ-E');
define('GOOGLE_SHEET_NAME', 'DB');

function getGoogleSheetData() {
    $url  = 'https://docs.google.com/spreadsheets/d/' . GOOGLE_SHEET_ID . '/gviz/tq?tqx=out:csv&sheet=' . GOOGLE_SHEET_NAME;
    $users = [];
    try {
        $ctx  = stream_context_create(['http' => ['timeout' => 30, 'user_agent' => 'Mozilla/5.0']]);
        $csv  = @file_get_contents($url, false, $ctx);
        if (!$csv) throw new Exception('No se pudo conectar');
        foreach (explode("\n", $csv) as $i => $line) {
            if ($i === 0 || empty(trim($line))) continue;
            $row = array_pad(str_getcsv($line), 14, '');
            $users[] = [
                'fecha'         => trim($row[0]),
                'celular'       => trim($row[1]),
                'tiene_codigo'  => trim($row[2]),
                'codigo'        => trim($row[3]),
                'nombre'        => trim($row[4]) . ' ' . trim($row[5]),
                'municipio'     => trim($row[6]),
                'estado'        => trim($row[7]),
                'colonia'       => trim($row[8]),
                'seccion'       => trim($row[10]),
                'rol'           => trim($row[11]),
                'temas_interes' => trim($row[12]),
                'propuesta'     => trim($row[13]),
            ];
        }
    } catch (Exception $e) { $users = []; }
    return $users;
}

// Filtrar por estado del coordinador
$allUsers = getGoogleSheetData();
$usersFiltrados = array_values(array_filter($allUsers, function($u) use ($estadoCoordinadorLimpiado) {
    return mb_strtolower(trim($u['estado']), 'UTF-8') === mb_strtolower($estadoCoordinadorLimpiado, 'UTF-8');
}));

$rolesCount = ['simpatizante'=>0,'militante'=>0,'voluntario'=>0,'evento'=>0,'indeciso'=>0];
foreach ($usersFiltrados as $u) {
    $r = strtolower($u['rol']);
    if (stripos($r,'simpatizante')!==false)                                       $rolesCount['simpatizante']++;
    elseif (stripos($r,'militante')!==false)                                      $rolesCount['militante']++;
    elseif (stripos($r,'voluntario')!==false||stripos($r,'activista')!==false)$rolesCount['voluntario']++;
    elseif (stripos($r,'evento')!==false||stripos($r,'vine')!==false)         $rolesCount['evento']++;
    else                                                                          $rolesCount['indeciso']++;
}

$geojsonPath = __DIR__ . '/../../geojson/';

// Catálogo completo de estados con sus archivos geojson y centroides
// Función helper: genera nombre de archivo geojson con sufijo opcional
// Sonora no lleva sufijo (ej. municipio.geojson), los demás sí (ej. municipio-bc.geojson)
function _gj(string $base, string $key): string {
    return $key === 'sonora' ? "{$base}.geojson" : "{$base}-{$key}.geojson";
}

$catalogoEstados = [
    'Sonora'             => ['key'=>'sonora',           'centroide'=>[29.5,  -110.5],  'zoom'=>7],
    'Baja California'    => ['key'=>'bc',               'centroide'=>[30.5,  -115.5],  'zoom'=>7],
    'Baja California Sur'=> ['key'=>'bcs',              'centroide'=>[24.5,  -111.5],  'zoom'=>7],
    'Chihuahua'          => ['key'=>'chihuahua',        'centroide'=>[28.6,  -106.1],  'zoom'=>7],
    'Durango'            => ['key'=>'durango',          'centroide'=>[24.5,  -104.7],  'zoom'=>7],
    'Sinaloa'            => ['key'=>'sinaloa',          'centroide'=>[24.8,  -107.4],  'zoom'=>7],
    'Nayarit'            => ['key'=>'nayarit',          'centroide'=>[21.75, -104.85], 'zoom'=>8],
    'Jalisco'            => ['key'=>'jalisco',          'centroide'=>[20.6,  -103.3],  'zoom'=>7],
    'Aguascalientes'     => ['key'=>'aguascalientes',   'centroide'=>[21.88, -102.29], 'zoom'=>9],
    'Coahuila'           => ['key'=>'coahuila',         'centroide'=>[27.3,  -102.0],  'zoom'=>7],
    'Guanajuato'         => ['key'=>'guanajuato',       'centroide'=>[21.0,  -101.25], 'zoom'=>8],
    'Nuevo León'         => ['key'=>'nuevo-leon',       'centroide'=>[25.5,  -99.9],   'zoom'=>7],
    'San Luis Potosí'    => ['key'=>'san-luis-potosi',  'centroide'=>[22.2,  -100.98], 'zoom'=>7],
    'Tamaulipas'         => ['key'=>'tamaulipas',       'centroide'=>[24.3,  -98.8],   'zoom'=>7],
    'Zacatecas'          => ['key'=>'zacatecas',        'centroide'=>[23.0,  -102.5],  'zoom'=>7],
    'Campeche'           => ['key'=>'campeche',         'centroide'=>[18.9,  -90.2],   'zoom'=>7],
    'Chiapas'            => ['key'=>'chiapas',          'centroide'=>[16.75, -92.6],   'zoom'=>7],
    'Oaxaca'             => ['key'=>'oaxaca',           'centroide'=>[17.0,  -96.7],   'zoom'=>7],
    'Quintana Roo'       => ['key'=>'quintana-roo',     'centroide'=>[19.5,  -88.0],   'zoom'=>7],
    'Tabasco'            => ['key'=>'tabasco',          'centroide'=>[18.0,  -92.8],   'zoom'=>8],
    'Veracruz'           => ['key'=>'veracruz',         'centroide'=>[19.5,  -96.9],   'zoom'=>7],
    'Yucatán'            => ['key'=>'yucatan',          'centroide'=>[20.7,  -89.1],   'zoom'=>8],
    'Ciudad de México'   => ['key'=>'ciudad-de-mexico', 'centroide'=>[19.43, -99.13],  'zoom'=>10],
    'Guerrero'           => ['key'=>'guerrero',         'centroide'=>[17.55, -99.5],   'zoom'=>7],
    'Hidalgo'            => ['key'=>'hidalgo',          'centroide'=>[20.5,  -98.8],   'zoom'=>8],
    'Morelos'            => ['key'=>'morelos',          'centroide'=>[18.7,  -99.1],   'zoom'=>9],
    'Puebla'             => ['key'=>'puebla',           'centroide'=>[19.0,  -97.9],   'zoom'=>8],
    'Tlaxcala'           => ['key'=>'tlaxcala',         'centroide'=>[19.32, -98.23],  'zoom'=>9],
    'Colima'             => ['key'=>'colima',           'centroide'=>[19.12, -103.72], 'zoom'=>9],
    'Estado de México'   => ['key'=>'estado-de-mexico', 'centroide'=>[19.35, -99.65],  'zoom'=>8],
    'Michoacán'          => ['key'=>'michoacan',        'centroide'=>[19.0,  -101.9],  'zoom'=>7],
    'Querétaro'          => ['key'=>'queretaro',        'centroide'=>[20.6,  -100.4],  'zoom'=>8],
];

// Agregar rutas de archivos usando el helper
foreach ($catalogoEstados as $nombre => &$cfg) {
    $k = $cfg['key'];
    $cfg['municipioFile']  = _gj('municipio', $k);
    $cfg['seccionFile']    = _gj('seccion', $k);
    $cfg['distFedFile']    = _gj('distrito-federal', $k);
    $cfg['distLocFile']    = _gj('distrito-local', $k);
    $cfg['coloniaFile']    = _gj('colonia', $k);
}
unset($cfg);

// ── Circunscripción: no hay estado fijo, el usuario lo elige en el mapa ──
$cfgEstado         = $catalogoEstados['Sonora'];   // fallback para PHP vars que lo requieran
$estadoCoordinador = '';                            // vacío → JS arranca sin estado
$estadoKey         = '';                            // vacío → selector sin preselección

// Stats: sin estado fijo, stats en cero hasta que el JS cargue datos
$meta      = 0;
$total     = 0;
$pct       = 0;
$statsData = ['total'=>0,'meta'=>0,'pct'=>0,'roles'=>$rolesCount];

// Circunscripción: incluir TODOS los estados del catálogo
$estadosData = [];
foreach ($catalogoEstados as $nombreEstado => $cfgE) {
    $k = $cfgE['key'];
    $munFile = $geojsonPath . $cfgE['municipioFile'];
    $munData = file_exists($munFile) ? json_decode(file_get_contents($munFile), true) : null;

    $seccionUrl = !empty($cfgE['seccionFile'])  ? '../../geojson/' . $cfgE['seccionFile']  : null;
    $distFedUrl = !empty($cfgE['distFedFile'])  ? '../../geojson/' . $cfgE['distFedFile']  : null;
    $distLocUrl = !empty($cfgE['distLocFile'])  ? '../../geojson/' . $cfgE['distLocFile']  : null;
    $coloniaUrl = !empty($cfgE['coloniaFile'])  ? '../../geojson/' . $cfgE['coloniaFile']  : null;

    $estadosData[$k] = [
        'label'      => $nombreEstado,
        'centroide'  => $cfgE['centroide'],
        'zoom'       => $cfgE['zoom'],
        'municipio'  => $munData,
        'seccionUrl' => $seccionUrl,
        'distFedUrl' => $distFedUrl,
        'distLocUrl' => $distLocUrl,
        'coloniaUrl' => $coloniaUrl,
    ];
}
$estadosJson = json_encode($estadosData);

/* ══════════════════════════════════════════════════════════════════
   MODO SELECCIÓN — parámetros GET enviados desde zonas.php
══════════════════════════════════════════════════════════════════ */
$modoSeleccion    = isset($_GET['modo_seleccion']) && $_GET['modo_seleccion'] === '1';
$selZonaId        = $modoSeleccion ? (int)($_GET['zona_id']  ?? 0) : 0;
$selZonaNombre    = $modoSeleccion ? htmlspecialchars(trim($_GET['zona_nombre'] ?? '')) : '';
$selReturnUrl     = $modoSeleccion ? htmlspecialchars(trim($_GET['return_url']  ?? '')) : '';

// Las secciones pueden venir por GET (URL directa) o por $_SESSION
// (guardadas antes del redirect para evitar URL 414 con listas grandes).
$selSecActualesStr= $modoSeleccion
    ? (trim($_GET['secciones_actuales'] ?? '') ?: trim($_SESSION['mapa_secciones_actuales'] ?? ''))
    : '';
$selSecOcupadasStr= $modoSeleccion
    ? (trim($_GET['secciones_ocupadas'] ?? '') ?: trim($_SESSION['mapa_secciones_ocupadas'] ?? ''))
    : '';

// Consumir y limpiar la sesión para no reutilizar en cargas futuras
if ($modoSeleccion) {
    unset($_SESSION['mapa_secciones_actuales'], $_SESSION['mapa_secciones_ocupadas']);
}

$selSecActualesArr= ($modoSeleccion && $selSecActualesStr)
    ? array_filter(explode(',', $selSecActualesStr))
    : [];
$selSecOcupadasArr= ($modoSeleccion && $selSecOcupadasStr)
    ? array_filter(explode(',', $selSecOcupadasStr))
    : [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cartografía Electoral - Circunscripción</title>

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;600&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

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
      padding-left: 280px; 
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

    .form-group { display: flex; flex-direction: column; gap: 8px; margin-bottom:20px; }
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
    .eje-btn-ag {
      padding: 10px 6px; border-radius: 10px; border: 2px solid var(--gris-borde);
      font-size: 11px; font-weight: 700; cursor: pointer; text-align: center;
      background: var(--gris-panel); color: var(--texto-dim);
      transition: 0.2s; font-family: 'IBM Plex Sans', sans-serif;
    }
    .eje-btn-ag.sel-voto         { background: rgba(26,122,60,.12);  border-color: var(--verde);  color: var(--verde); }
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

    /* ── ESTILOS NUEVO MODAL DETALLES SECCIÓN ── */
    .sec-det-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px; }
    .sec-det-item label {
        font-size: 11px; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.5px; color: var(--texto-dim); display: block; margin-bottom: 4px;
    }
    .sec-det-item span { font-size: 15px; font-weight: 700; color: var(--texto); }
    .sec-det-item.full { grid-column: 1 / -1; }

    .sec-det-section-title {
        font-size: 13px; font-weight: 800; text-transform: uppercase;
        letter-spacing: 1px; color: var(--verde); margin: 20px 0 12px;
        padding-bottom: 6px; border-bottom: 2px solid var(--gris-borde);
    }

    .sec-act-stats { display: grid; grid-template-columns: repeat(3,1fr); gap: 12px; }
    .sec-act-stat-card { background: var(--gris-panel); border-radius: 12px; padding: 16px; text-align: center; border: 1px solid var(--gris-borde); }
    .sec-act-stat-num { font-size: 28px; font-weight: 900; color: var(--verde); font-family: 'IBM Plex Mono', monospace; }
    .sec-act-stat-lbl { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--texto-dim); margin-top: 2px; }

    .sec-field-readonly {
        background: var(--gris-panel); border-radius: 10px; padding: 14px 16px;
        font-size: 14px; color: var(--texto); min-height: 60px; line-height: 1.5;
        border: 1px solid var(--gris-borde);
    }

    .sec-modal-progress-wrap { margin: 10px 0; }
    .sec-modal-progress-bg { height: 12px; border-radius: 6px; background: var(--gris-medio); overflow: hidden; }
    .sec-modal-progress-fill { height: 100%; border-radius: 6px; background: linear-gradient(90deg, var(--verde), var(--verde-acento)); transition: width 0.6s ease; }
    .sec-modal-progress-label { display: flex; justify-content: space-between; font-size: 12px; font-weight: 700; color: var(--texto-dim); margin-top: 4px; }


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

    /* Pin de Activación (◆ diamante, distinto del evento de agenda) */
    .activacion-marker {
      background: transparent !important;
      border: none !important;
      box-shadow: none !important;
      pointer-events: none;
    }
    .activacion-pin {
      display: flex;
      flex-direction: column;
      align-items: center;
    }
    .activacion-pin-icon {
      width: 26px; height: 26px;
      border-radius: 4px;
      transform: rotate(45deg);
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 2px 8px rgba(0,0,0,0.25);
      border: 2px solid white;
    }
    .activacion-pin-icon i {
      transform: rotate(-45deg);
      color: white;
      font-size: 10px;
    }
    .activacion-pin-label {
      margin-top: 3px;
      font-family: 'IBM Plex Mono', monospace;
      font-size: 8px;
      font-weight: 600;
      color: #92400e;
      background: rgba(255,255,255,0.85);
      padding: 1px 4px;
      border-radius: 3px;
      white-space: nowrap;
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

    /* ESTILOS DE EDAD ACTUALIZADOS */
    .edad-chips { display: flex; flex-wrap: wrap; gap: 4px; margin: 5px 0 3px; }
    .edad-chip {
      font-size: 9px; font-weight: 700;
      padding: 3px 6px; border-radius: 4px;
      font-family: 'IBM Plex Mono', monospace;
      display: inline-block;
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
      cursor: pointer;
      border: none;
      width: 100%;
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

    /* Eventos layer toggle */
    .ev-count-badge {
      font-family: 'IBM Plex Mono', monospace;
      font-size: 9px; font-weight: 700;
      background: var(--verde); color: white;
      padding: 1px 6px; border-radius: 8px; margin-left: 4px;
    }

    /* ── Selector de Zona (pintar secciones) ── */
    .zona-select {
      width: 100%;
      height: 30px;
      padding: 0 10px;
      border: 1px solid var(--gris-borde);
      border-radius: 5px;
      background: var(--blanco);
      font-family: 'IBM Plex Sans', sans-serif;
      font-size: 11px;
      font-weight: 600;
      color: var(--texto);
      cursor: pointer;
      outline: none;
      appearance: none;
      -webkit-appearance: none;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%236b7280'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 8px center;
      margin-bottom: 8px;
      transition: border-color 0.15s;
    }
    .zona-select:focus { border-color: var(--verde); }

    .zona-btn-limpiar {
      width: 100%;
      padding: 6px;
      border: 1px solid var(--gris-borde);
      border-radius: 5px;
      background: var(--gris-panel);
      font-size: 10px;
      font-weight: 600;
      color: var(--texto-dim);
      cursor: pointer;
      font-family: 'IBM Plex Sans', sans-serif;
      transition: all 0.15s;
      margin-top: 2px;
    }
    .zona-btn-limpiar:hover { background: var(--gris-borde); color: var(--texto); }

    /* ── Filtros de año / mes ── */
    .filter-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 6px;
      margin-bottom: 6px;
    }
    .filter-select {
      height: 28px;
      padding: 0 6px;
      border: 1px solid var(--gris-borde);
      border-radius: 5px;
      background: var(--blanco);
      font-family: 'IBM Plex Sans', sans-serif;
      font-size: 10px;
      font-weight: 600;
      color: var(--texto);
      cursor: pointer;
      outline: none;
      appearance: none;
      -webkit-appearance: none;
      transition: border-color 0.15s;
    }
    .filter-select:focus { border-color: var(--verde); }
    .filter-label-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 6px;
    }
    .filter-clear-btn {
      font-size: 9px;
      color: var(--verde);
      cursor: pointer;
      background: none;
      border: none;
      font-family: 'IBM Plex Sans', sans-serif;
      font-weight: 600;
      padding: 0;
    }
    .filter-clear-btn:hover { text-decoration: underline; }
  </style>
</head>
<body>

<?php if (file_exists(__DIR__ . '/../../includes/components/sidebar_circunscripcion.php')) include __DIR__ . '/../../includes/components/sidebar_circunscripcion.php'; ?>

<?php if ($modoSeleccion): ?>
<div id="sel-banner" style="position:fixed;top:0;left:280px;right:0;z-index:9999;background:#1a7a3c;color:white;padding:10px 20px;display:flex;align-items:center;justify-content:space-between;gap:12px;box-shadow:0 3px 14px rgba(0,0,0,0.25);">
  <div style="display:flex;align-items:center;gap:12px;">
    <i class="fas fa-vector-square" style="font-size:20px;opacity:0.9;"></i>
    <div>
      <div style="font-weight:800;font-size:13px;letter-spacing:0.02em;">
        MODO SELECCIÓN — <span style="font-weight:500;opacity:0.9;"><?= htmlspecialchars($selZonaNombre) ?: 'Zona' ?></span>
      </div>
      <div id="sel-counter-txt" style="font-size:11px;opacity:0.85;margin-top:1px;">Haz clic en las secciones del mapa para seleccionarlas o deseleccionarlas</div>
    </div>
  </div>
  <div style="display:flex;gap:10px;align-items:center;flex-shrink:0;">
    <div style="background:rgba(255,255,255,0.18);padding:5px 14px;border-radius:20px;font-size:12px;font-weight:700;" id="sel-badge-cnt">0 seleccionadas</div>
    <button onclick="confirmarSeleccion()" style="background:white;color:#1a7a3c;border:none;padding:8px 20px;border-radius:8px;font-weight:800;font-size:13px;cursor:pointer;display:flex;align-items:center;gap:7px;" onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
      <i class="fas fa-check-circle"></i> Confirmar Selección
    </button>
    <button onclick="cancelarSeleccion()" style="background:rgba(255,255,255,0.12);color:white;border:1px solid rgba(255,255,255,0.35);padding:8px 14px;border-radius:8px;font-weight:600;font-size:12px;cursor:pointer;">
      <i class="fas fa-times"></i> Cancelar
    </button>
  </div>
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
    <div class="search-box">
      <input type="number" id="buscar-seccion-input" placeholder="Buscar sección..." onkeypress="if(event.key==='Enter') buscarSeccionMapa()">
      <button onclick="buscarSeccionMapa()"><i class="fas fa-search"></i></button>
    </div>
    <div class="estado-selector-wrap">
      <span class="estado-selector-label">Estado</span>
      <select class="estado-select" id="estado-select" onchange="cambiarEstado(this.value)">
        <option value="">— Seleccionar estado —</option>
        <?php foreach ($estadosData as $key => $cfg): ?>
        <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($cfg['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <!-- Agregar Evento removido para Coordinador de Circunscripción -->
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

      <!-- 5. Número de Sección -->
      <div class="layer-item" id="row-labels" onclick="toggleCapa('labels')" style="display:none;">
        <div class="layer-left">
          <div class="layer-color" style="background:#1a7a3c;border-radius:50%;"></div>
          <span class="layer-name">Número de Sección</span>
        </div>
        <div class="toggle" id="toggle-labels"></div>
      </div>

      <!-- 6. Colonias -->
      <div class="layer-item" id="row-colonias" onclick="toggleCapa('colonias')" style="display:none;">
        <div class="layer-left">
          <div class="layer-color" style="background:#fff3e0;border:2px solid #e65100;"></div>
          <span class="layer-name" id="label-colonias">Colonias</span>
        </div>
        <div class="toggle" id="toggle-colonias"></div>
      </div>

      <!-- 7. Eventos -->
      <div class="layer-item" id="row-eventos" onclick="toggleCapaEventos()">
        <div class="layer-left">
          <div class="layer-color" style="background:#1a7a3c;border-radius:50%;"></div>
          <span class="layer-name">Eventos <span class="ev-count-badge" id="ev-count-badge">0</span></span>
        </div>
        <div class="toggle " id="toggle-eventos"></div>
      </div>

      <!-- 8. Activaciones -->
      <div class="layer-item" id="row-activaciones" onclick="toggleCapaActivaciones()">
        <div class="layer-left">
          <div class="layer-color" style="background:#f59e0b;border-radius:2px;transform:rotate(45deg);width:10px;height:10px;margin:2px;"></div>
          <span class="layer-name">Activaciones <span class="ev-count-badge" id="act-count-badge" style="background:#f59e0b;">0</span></span>
        </div>
        <div class="toggle" id="toggle-activaciones"></div>
      </div>

      <div id="capas-placeholder" style="font-size:11px;color:var(--texto-dim);padding:6px 0;">
        Selecciona un estado para ver las capas disponibles.
      </div>

    </div>

    <!-- ── Selector de Zona ── -->
    <div class="panel-section" id="panel-zona-selector" style="display:none;">
      <div class="panel-label">Pintar Zona</div>
      <select id="sel-region" class="zona-select" onchange="onRegionChange()">
        <option value="">— Región —</option>
        <?php foreach($regiones_estatales as $r): ?>
          <option value="<?= htmlspecialchars($r) ?>"><?= htmlspecialchars($r) ?></option>
        <?php endforeach; ?>
      </select>
      <select id="sel-zona" class="zona-select" onchange="onZonaChange()" disabled>
        <option value="">— Zona —</option>
      </select>
      <button class="zona-btn-limpiar" onclick="limpiarZona()">
        <i class="fas fa-times-circle"></i> Limpiar selección de zona
      </button>
    </div>

    <!-- ── Filtros de Eventos / Activaciones ── -->
    <div class="panel-section" id="panel-filtros">
      <div class="filter-label-row">
        <span class="panel-label" style="margin-bottom:0;">Filtrar por fecha</span>
        <button class="filter-clear-btn" onclick="limpiarFiltros()"><i class="fas fa-undo"></i> Limpiar</button>
      </div>
      <div class="filter-row">
        <select id="filter-year" class="filter-select" onchange="aplicarFiltros()">
          <option value="">Año</option>
          <?php
          $currentYear = (int)date('Y');
          for ($y = $currentYear; $y >= $currentYear - 4; $y--) {
              echo "<option value=\"$y\">$y</option>";
          }
          ?>
        </select>
        <select id="filter-month" class="filter-select" onchange="aplicarFiltros()">
          <option value="">Mes</option>
          <?php
          $meses = ['01'=>'Enero','02'=>'Febrero','03'=>'Marzo','04'=>'Abril','05'=>'Mayo','06'=>'Junio',
                    '07'=>'Julio','08'=>'Agosto','09'=>'Septiembre','10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre'];
          foreach ($meses as $num => $nombre) {
              echo "<option value=\"$num\">$nombre</option>";
          }
          ?>
        </select>
      </div>
      <div style="font-size:9px;color:var(--texto-dim);line-height:1.4;">
        Aplica a Eventos y Activaciones visibles en el mapa.
      </div>
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
        <div style="font-size:9px;color:var(--texto-dim);margin-top:4px;letter-spacing:0.05em;">Cargando datos…</div>
      </div>

      <div id="db-no-data" style="display:none;">
        <i class="fas fa-info-circle"></i> Sin datos registrados para esta sección
      </div>

      <div id="db-panel" style="display:none;">

        <hr class="db-sep">

        <div class="db-section-label"><i class="fas fa-map-pin"></i> Zona</div>
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
          <span class="info-key">TOTAL</span>
          <span class="info-val verde" id="db-nominal">—</span>
        </div>
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

        <div id="db-act-lista-wrap" style="display:none;margin-top:6px;">
          <div id="db-act-lista"></div>
        </div>

        <div id="db-eventos-wrap" style="display:none;margin-top:10px;">
          <div class="db-section-label"><i class="fas fa-calendar-check"></i> Eventos de Agenda</div>
          <div id="db-eventos-lista"></div>
        </div>

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

        <button id="db-link" class="db-link-btn" type="button">
          <i class="fas fa-external-link-alt"></i> Ver detalle completo
        </button>

      </div></div>

  </aside>

  <div id="map"></div>

</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// ──────────────────────────────────────────────
// Variable global para almacenar los datos del AJAX
// ──────────────────────────────────────────────
let seccionDetalleActual = null;

// ──────────────────────────────────────────────
// Datos de todos los estados (cargados desde PHP)
// ──────────────────────────────────────────────
const ESTADOS_DATA = <?= $estadosJson ?>;

/* ── Modo Selección de Secciones ── */
const MODO_SELECCION     = <?= $modoSeleccion ? 'true' : 'false' ?>;
const SEL_ZONA_ID        = <?= $selZonaId ?>;
const SEL_RETURN_URL     = <?= json_encode($selReturnUrl) ?>;
const SEL_SECS_INICIALES = new Set(<?= json_encode(array_values($selSecActualesArr)) ?>);
const SEL_SECS_OCUPADAS  = new Set(<?= json_encode(array_values($selSecOcupadasArr)) ?>);
let   seccionesZonaSet   = new Set(SEL_SECS_INICIALES);

// ── Fallback: si PHP no recibió secciones, leerlas de sessionStorage ──
// zonas_coest.php las guarda ahí antes de redirigir, evitando URLs largas (414).
if (MODO_SELECCION && seccionesZonaSet.size === 0) {
  const _storedAct = sessionStorage.getItem('mapa_secciones_actuales') || '';
  const _storedOcu = sessionStorage.getItem('mapa_secciones_ocupadas') || '';
  if (_storedAct) {
    _storedAct.split(',').filter(Boolean).forEach(s => seccionesZonaSet.add(s.trim()));
  }
  if (_storedOcu) {
    _storedOcu.split(',').filter(Boolean).forEach(s => SEL_SECS_OCUPADAS.add(s.trim()));
  }
  // Consumir: evita que recarga futura reutilice las mismas secciones
  sessionStorage.removeItem('mapa_secciones_actuales');
  sessionStorage.removeItem('mapa_secciones_ocupadas');
}

// Límites geográficos de México
// ── Buscar sección en el mapa ──────────────────────────────────────────
function buscarSeccionMapa() {
    const input = document.getElementById('buscar-seccion-input').value.trim();
    if (!input) return;
    if (!layerSecciones) { mostrarToast('Primero selecciona un estado en el mapa'); return; }
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
    if (!encontrada) mostrarToast('Sección ' + input + ' no encontrada en el estado actual');
}

const MEXICO_BOUNDS = L.latLngBounds(
  L.latLng(14.5, -118.5),
  L.latLng(32.7, -86.7)
);

const map = L.map('map', {
  zoomControl: true,
  attributionControl: false,
  minZoom: 5,
  maxZoom: 19,
  maxBounds: MEXICO_BOUNDS,
  maxBoundsViscosity: 1.0
}).setView([23.6345, -102.5528], 5);

L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
  maxZoom: 19
}).addTo(map);

// ──────────────────────────────────────────────
// Capas activas
// ──────────────────────────────────────────────
let layerEstadoSilueta = null;   // contorno unificado del estado (defecto)
let layerMunicipio     = null;   // municipios con líneas internas
let layerSecciones     = null;   // secciones electorales
let layerLabels        = null;   // números de sección
let layerDistFed       = null;   // distritos federales
let layerDistLoc       = null;   // distritos locales
let layerColonias      = null;   // colonias

let seccionActiva  = null;
let municipioActivo = null;
let estadoActual   = null;

const capasVisibles = { municipio: true, secciones: false, labels: false, distFed: false, distLoc: false, colonias: false };

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
  if (layerColonias)      { map.removeLayer(layerColonias);      layerColonias = null; }
  seccionActiva = null;
  municipioActivo = null;
  document.getElementById('info-panel').classList.remove('visible');
}

// ──────────────────────────────────────────────
// Silueta del estado (solo contorno exterior, sin municipios)
// ──────────────────────────────────────────────
function cargarSilueta(munData) {
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

  if (!capasVisibles.municipio) map.removeLayer(layerMunicipio);
  map.fitBounds(layerMunicipio.getBounds(), { padding: [24, 24] });
}

// ──────────────────────────────────────────────
// Secciones electorales
// ──────────────────────────────────────────────
function cargarSecciones(secData) {
  if (!secData || !secData.features || secData.features.length === 0) return;

  layerSecciones = L.geoJSON(secData, {
    style: {
      color: '#22a050',
      weight: 1,
      fillColor: '#ffffff',
      fillOpacity: 0.01
    },
    onEachFeature: (feature, layer) => {
      const p   = feature.properties;
      const sec = String(p.seccion ?? '');

      // ── MODO SELECCIÓN MÚLTIPLE ──────────────────────────────
      if (MODO_SELECCION) {
        const esOcupada = SEL_SECS_OCUPADAS.has(sec) && !seccionesZonaSet.has(sec);

        // Estilo inicial según estado
        if (seccionesZonaSet.has(sec)) {
          layer.setStyle({ fillColor:'#d32f2f', fillOpacity:0.55, color:'#b71c1c', weight:2 });
        } else if (esOcupada) {
          layer.setStyle({ fillColor:'#c62828', fillOpacity:0.30, color:'#b71c1c', weight:1 });
        }

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
            this.setStyle({ fillColor:'#ffffff', fillOpacity:0.01, color:'#22a050', weight:1 });
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
            this.setStyle({ fillColor:'#ffffff', fillOpacity:0.01, color:'#22a050', weight:1 });
        });
        layer.bindTooltip(
          `<span style="font-family:IBM Plex Sans,sans-serif;font-size:11px;font-weight:700;">Sec. ${sec}${seccionesZonaSet.has(sec)?' <span style="color:#d32f2f;">● Asignada</span>':''}</span>`,
          { sticky:true, direction:'top', offset:[0,-8], opacity:0.95 }
        );
        return; // fin modo selección
      }

      // ── MODO NORMAL ──────────────────────────────────────────
      layer.on('click', function(e) {
        if (seccionActiva) layerSecciones.resetStyle(seccionActiva);
        this.setStyle({ fillColor: '#1a7a3c', fillOpacity: 0.45, color: '#0f5228', weight: 2 });
        this.bringToFront();
        seccionActiva = this;

        document.getElementById('info-panel').classList.add('visible');
        document.getElementById('info-seccion').textContent = `SEC. ${p.seccion}`;
        document.getElementById('info-df').textContent   = `Distrito ${p.distrito_federal || p.distrito_f || '—'}`;
        document.getElementById('info-dl').textContent   = `Distrito ${p.distrito_local || p.distrito_l || '—'}`;
        document.getElementById('info-mun').textContent  = p.nombre_mun || `Mun. ${p.municipio}`;
        document.getElementById('info-ent').textContent  = p.nombre_ent || `Ent. ${p.entidad}`;
        document.getElementById('info-tipo').textContent = p.tipo == 1 ? 'Urbana' : p.tipo == 2 ? 'Rural' : `Tipo ${p.tipo}`;

        // Cargar datos desde BD
        cargarDatosSeccion(p.seccion);

        L.DomEvent.stopPropagation(e);
      });
    }
  }).addTo(map);

  if (!capasVisibles.secciones) map.removeLayer(layerSecciones);

  // En modo selección, actualizar contador con las ya marcadas
  if (MODO_SELECCION) actualizarBannerSeleccion();

  // ── AUTO-ZOOM a secciones ya asignadas en modo selección ──────────
  if (MODO_SELECCION && seccionesZonaSet.size > 0) {
    const boundsAsignadas = [];
    layerSecciones.eachLayer(layer => {
      if (!layer.feature) return;
      const s = String(layer.feature.properties.seccion ?? '');
      if (seccionesZonaSet.has(s)) {
        try { boundsAsignadas.push(layer.getBounds()); } catch(_) {}
      }
    });
    if (boundsAsignadas.length > 0) {
      const combined = boundsAsignadas.reduce((acc, b) => acc.extend(b), boundsAsignadas[0]);
      map.fitBounds(combined, { padding: [60, 60], maxZoom: 13 });
    }
  }

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

  document.getElementById('stat-secciones').textContent = secData.features.length;
}

// ──────────────────────────────────────────────
// Cambiar estado desde el dropdown
// ──────────────────────────────────────────────
function cambiarEstado(key) {
  limpiarCapas();
  limpiarZona();

  document.getElementById('stat-secciones').textContent = '—';
  document.getElementById('stat-municipios').textContent = '—';

  // Ocultar todas las filas de capa
  ['row-municipio','row-secciones','row-labels','row-distfed','row-distloc','row-colonias'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.style.display = 'none';
  });
  document.getElementById('capas-placeholder').style.display = 'block';

  // Resetear estado de capas: solo municipio ON por defecto
  ['municipio','secciones','labels','distFed','distLoc','colonias'].forEach(n => {
    capasVisibles[n] = (n === 'municipio');
    const tog = document.getElementById(`toggle-${n}`);
    if (tog) tog.classList.toggle('on', capasVisibles[n]);
  });

  if (!key || !ESTADOS_DATA[key]) {
    document.getElementById('header-estado-span').textContent = '— Selecciona un estado';
    document.getElementById('panel-zona-selector').style.display = 'none';
    map.setView([23.6345, -102.5528], 5);
    return;
  }

  estadoActual = key;
  const cfg = ESTADOS_DATA[key];
  document.getElementById('header-estado-span').textContent = cfg.label;

  document.getElementById('label-municipio').textContent = `Municipio — ${cfg.label}`;
  document.getElementById('label-secciones').textContent = `Secciones — ${cfg.label}`;
  if (document.getElementById('label-distfed'))
    document.getElementById('label-distfed').textContent  = `Distritos Federales — ${cfg.label}`;
  if (document.getElementById('label-distloc'))
    document.getElementById('label-distloc').textContent  = `Distritos Locales — ${cfg.label}`;
  if (document.getElementById('label-colonias'))
    document.getElementById('label-colonias').textContent = `Colonias — ${cfg.label}`;

  const munData = cfg.municipio;

  if (!munData) {
    mostrarToast(`Sin datos de municipios para ${cfg.label}`);
    return;
  }

  document.getElementById('row-municipio').style.display = 'flex';
  document.getElementById('capas-placeholder').style.display = 'none';

  cargarSilueta(munData);
  cargarMunicipios(munData);
  document.getElementById('stat-municipios').textContent = munData.features ? munData.features.length : '—';

  // ── Secciones ──
  if (cfg.seccionUrl) {
    document.getElementById('stat-secciones').textContent = '…';
    fetch(cfg.seccionUrl)
      .then(r => { if (!r.ok) throw new Error(`HTTP ${r.status}`); return r.json(); })
      .then(secData => {
        if (estadoActual !== key) return;
        cargarSecciones(secData);
        document.getElementById('row-secciones').style.display = 'flex';
        document.getElementById('row-labels').style.display    = 'flex';
        repintarEventos();
      })
      .catch(err => {
        if (estadoActual !== key) return;
        document.getElementById('stat-secciones').textContent = '—';
        console.warn('Secciones fetch error:', err);
      });
  }

  // ── Distritos Federales ──
  if (cfg.distFedUrl) {
    fetch(cfg.distFedUrl)
      .then(r => { if (!r.ok) throw new Error(`HTTP ${r.status}`); return r.json(); })
      .then(data => {
        if (estadoActual !== key) return;
        layerDistFed = L.geoJSON(data, {
          style: { color: '#1565c0', weight: 1.5, fillColor: '#e3f2fd', fillOpacity: 0.0, dashArray: '6,3' },
          onEachFeature: (feature, layer) => {
            const p = feature.properties;
            const num = p.distrito_federal || p.distrito || p.DISTRITO || p.numero || p.id || '—';
            layer.bindTooltip(`<span style="font-family:IBM Plex Sans,sans-serif;font-size:11px;font-weight:700;color:#1565c0;">Dist. Federal ${num}</span>`,
              { sticky:true, direction:'top', offset:[0,-8], opacity:0.95 });
          }
        });
        if (capasVisibles.distFed) layerDistFed.addTo(map);
        document.getElementById('row-distfed').style.display = 'flex';
      })
      .catch(err => console.warn('Dist. Federal fetch error:', err));
  }

  // ── Distritos Locales ──
  if (cfg.distLocUrl) {
    fetch(cfg.distLocUrl)
      .then(r => { if (!r.ok) throw new Error(`HTTP ${r.status}`); return r.json(); })
      .then(data => {
        if (estadoActual !== key) return;
        layerDistLoc = L.geoJSON(data, {
          style: { color: '#6a1b9a', weight: 1.5, fillColor: '#f3e5f5', fillOpacity: 0.0, dashArray: '4,4' },
          onEachFeature: (feature, layer) => {
            const p = feature.properties;
            const num = p.distrito_local || p.distrito || p.DISTRITO || p.numero || p.id || '—';
            layer.bindTooltip(`<span style="font-family:IBM Plex Sans,sans-serif;font-size:11px;font-weight:700;color:#6a1b9a;">Dist. Local ${num}</span>`,
              { sticky:true, direction:'top', offset:[0,-8], opacity:0.95 });
          }
        });
        if (capasVisibles.distLoc) layerDistLoc.addTo(map);
        document.getElementById('row-distloc').style.display = 'flex';
      })
      .catch(err => console.warn('Dist. Local fetch error:', err));
  }

  // ── Colonias ──
  if (cfg.coloniaUrl) {
    fetch(cfg.coloniaUrl)
      .then(r => { if (!r.ok) throw new Error(`HTTP ${r.status}`); return r.json(); })
      .then(data => {
        if (estadoActual !== key) return;
        layerColonias = L.geoJSON(data, {
          style: { color: '#e65100', weight: 0.8, fillColor: '#fff3e0', fillOpacity: 0.0 },
          onEachFeature: (feature, layer) => {
            const p = feature.properties;
            const nombre = p.nombre || p.NOMBRE || p.colonia || p.nom_colonia || '—';
            layer.bindTooltip(`<span style="font-family:IBM Plex Sans,sans-serif;font-size:11px;font-weight:600;color:#e65100;">${nombre}</span>`,
              { sticky:true, direction:'top', offset:[0,-8], opacity:0.95 });
          }
        });
        if (capasVisibles.colonias) layerColonias.addTo(map);
        document.getElementById('row-colonias').style.display = 'flex';
      })
      .catch(err => console.warn('Colonias fetch error:', err));
  }

  // Mostrar selector de zona
  document.getElementById('panel-zona-selector').style.display = 'block';
}

// ──────────────────────────────────────────────
// Toggle capas del panel
// ──────────────────────────────────────────────
function toggleCapa(nombre) {
  capasVisibles[nombre] = !capasVisibles[nombre];
  const tog = document.getElementById(`toggle-${nombre}`);
  if (tog) tog.classList.toggle('on', capasVisibles[nombre]);

  const capaMap = {
    municipio: layerMunicipio,
    secciones: layerSecciones,
    labels:    layerLabels,
    distFed:   layerDistFed,
    distLoc:   layerDistLoc,
    colonias:  layerColonias,
  };
  const capa = capaMap[nombre];
  if (!capa) return;
  capasVisibles[nombre] ? map.addLayer(capa) : map.removeLayer(capa);
}

// ──────────────────────────────────────────────
// Click en mapa: deseleccionar o seleccionar sección para evento
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
      seccionParaEvento = { 
        num: p.seccion, 
        latlng, 
        estado: ESTADOS_DATA[estadoActual]?.label || '', 
        municipio: p.nombre_mun || p.NOM_MUN || '' 
      };
    } else {
      seccionParaEvento = { num: '—', latlng: e.latlng, estado: '', municipio: '' };
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
  document.getElementById('db-panel').style.display   = 'none';
  document.getElementById('db-no-data').style.display = 'none';
  document.getElementById('db-spinner').style.display = 'none';
});

// ──────────────────────────────────────────────
// Eventos — persisten en DB via agenda/procesar.php
// ──────────────────────────────────────────────
const AGENDA_API = '/api/agenda/procesar.php';
let eventosData   = [];          // eventos cargados de DB
let capasVisiblesEventos = false;
const layerEventos = L.layerGroup();

// ── Activaciones en mapa ──────────────────────────────────
const ACTIVACIONES_MAPA = <?= json_encode($activacionesMapa, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const layerActivaciones  = L.layerGroup();
let capasVisiblesActivaciones = false;
let _activacionOffsets = {};

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
    eventosData = data.eventos || [];
    repintarEventos();
    actualizarBadge();
  } catch(e) { console.warn('cargarYPintarEventos error:', e); }
}

function repintarEventos() {
  _eventoOffsets = {};
  layerEventos.clearLayers();
  eventosData.forEach(ev => colocarMarkerEvento(ev));
}

function actualizarBadge() {
  document.getElementById('ev-count-badge').textContent = eventosData.length;
}

function toggleCapaEventos() {
  capasVisiblesEventos = !capasVisiblesEventos;
  document.getElementById('toggle-eventos').classList.toggle('on', capasVisiblesEventos);
  capasVisiblesEventos ? map.addLayer(layerEventos) : map.removeLayer(layerEventos);
}

// ──────────────────────────────────────────────
// Activaciones en mapa
// ──────────────────────────────────────────────
const TIPO_LABELS_ACT = {
  jornada_medica:  'Jornada Médica',
  casa_por_casa:   'Casa por Casa',
  mitin:           'Mitin',
  brigada:         'Brigada',
  capacitacion:    'Capacitación',
  reunion:         'Reunión',
  evento_cultural: 'Evento Cultural',
  recorrido:       'Recorrido',
  otro:            'Otro',
};
const TIPO_COLORS_ACT = {
  jornada_medica:  '#ec4899',
  casa_por_casa:   '#f59e0b',
  mitin:           '#8b5cf6',
  brigada:         '#06b6d4',
  capacitacion:    '#10b981',
  reunion:         '#6366f1',
  evento_cultural: '#f97316',
  recorrido:       '#14b8a6',
  otro:            '#f59e0b',
};
const ESTATUS_STYLES_ACT = {
  programada:  'background:#fff7ed;color:#d97706;border:1px solid #fde68a;',
  'en-curso':  'background:#eff6ff;color:#1e88e5;border:1px solid #bfdbfe;',
  completada:  'background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0;',
  cancelada:   'background:#fff5f5;color:#e53935;border:1px solid #fecaca;',
};

function pintarActivaciones() {
  _activacionOffsets = {};
  layerActivaciones.clearLayers();
  ACTIVACIONES_MAPA.forEach(act => colocarMarkerActivacion(act));
  document.getElementById('act-count-badge').textContent = ACTIVACIONES_MAPA.length;
}

function toggleCapaActivaciones() {
  capasVisiblesActivaciones = !capasVisiblesActivaciones;
  document.getElementById('toggle-activaciones').classList.toggle('on', capasVisiblesActivaciones);
  capasVisiblesActivaciones ? map.addLayer(layerActivaciones) : map.removeLayer(layerActivaciones);
}

function colocarMarkerActivacion(act) {
  let latlng = null;

  // 1. Coordenadas directas del registro
  if (act.lat && act.lng && parseFloat(act.lat) !== 0) {
    latlng = [parseFloat(act.lat), parseFloat(act.lng)];
  }
  // 2. Fallback: resolver centroide desde la sección en el mapa
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

  // Normalizar L.LatLng vs array
  const _lat = latlng.lat !== undefined ? latlng.lat : latlng[0];
  const _lng = latlng.lng !== undefined ? latlng.lng : latlng[1];
  latlng = jitteredLatLng(_lat, _lng, _activacionOffsets);

  const color   = TIPO_COLORS_ACT[act.tipo] || '#f59e0b';
  const label   = TIPO_LABELS_ACT[act.tipo]  || act.tipo || '—';
  const estStyle = ESTATUS_STYLES_ACT[act.estatus] || ESTATUS_STYLES_ACT['programada'];
  const estatusLabel = (act.estatus || 'programada').replace('-', ' ').replace(/\b\w/g, c => c.toUpperCase());
  const candStr = act.candidato ? `🙋 ${act.candidato}` : '🚫 Sin candidato';

  // Pin con forma de diamante (◆) para distinguir de los eventos (📍)
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
    `<br><button onclick="verDetalleActivacionById('${act.id}')" ` +
    `style="margin-top:8px;padding:5px 10px;background:${color};color:white;border:none;border-radius:6px;` +
    `font-size:11px;font-weight:700;cursor:pointer;font-family:IBM Plex Sans,sans-serif;">` +
    `Ver detalle</button></div></div>`;

  const marker = L.marker(latlng, {
    icon: L.divIcon({
      className: 'activacion-marker',
      html: iconHtml,
      iconSize: [40, 44],
      iconAnchor: [13, 38]
    }),
    interactive: true
  });
  marker.bindPopup(popupHtml, { maxWidth: 260 });
  layerActivaciones.addLayer(marker);
}

const SUBTIPOS_M = {
  voto:         ['Casa por casa','Evento de campaña','Mitin','Brigada vecinal','Recorrido territorial','Firma de compromisos','Contacto directo'],
  comunicacion: ['Entrevista de radio','Entrevista de TV','Rueda de prensa','Publicación redes sociales','Spot / grabación','Vocería','Evento mediático'],
  organizacion: ['Reunión de estructura','Capacitación','Coordinación interna','Planeación estratégica','Logística','Revisión de presupuesto','Reunión de equipo']
};
const EJE_LABELS_M = { voto:'🗳️ Voto / Territorio', comunicacion:'📣 Comunicación', organizacion:'⚙️ Organización' };
const EJE_COLORS_M = { voto:'#1a7a3c', comunicacion:'#2196f3', organizacion:'#9c27b0' };
let ejeSeleccionadoM = null;

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
  const ini = document.getElementById('m-inicio').value;
  const fin = document.getElementById('m-fin').value;
  if (ini && fin) {
    const [hi,mi] = ini.split(':').map(Number), [hf,mf] = fin.split(':').map(Number);
    const d = (hf*60+mf - hi*60-mi) / 60;
    if (d > 0) document.getElementById('m-horas').value = d.toFixed(1);
  }
}

// ── Modales de Agenda ──
function abrirModal() {
  ejeSeleccionadoM = null;
  document.querySelectorAll('.eje-btn-ag').forEach(b => b.className = 'eje-btn-ag');
  document.getElementById('m-subtipo').innerHTML = '<option value="">— Selecciona un eje primero —</option>';
  
  ['m-titulo','m-inicio','m-fin','m-horas','m-responsable','m-notas','m-region','m-zona'].forEach(id => {
    document.getElementById(id).value = '';
  });

  const hoy = new Date();
  document.getElementById('m-fecha').value =
    `${hoy.getFullYear()}-${String(hoy.getMonth()+1).padStart(2,'0')}-${String(hoy.getDate()).padStart(2,'0')}`;

  if (seccionActiva) {
    const p = seccionActiva.feature.properties;
    const latlng = L.geoJSON(seccionActiva.feature).getBounds().getCenter();
    seccionParaEvento = { num: p.seccion, latlng, estado: ESTADOS_DATA[estadoActual]?.label || '', municipio: p.nombre_mun || p.NOM_MUN || '' };
  }
  actualizarSelectorSeccion();
  document.getElementById('modal-overlay').classList.add('open');
}

function cerrarModal() {
  document.getElementById('modal-overlay').classList.remove('open');
  seleccionandoSeccion = false;
}
function cerrarModalDet() {
  document.getElementById('modal-det').classList.remove('open');
}
function cerrarModalDetAct() { document.getElementById('modal-det-act').classList.remove('open'); }

function verDetalleActivacionById(id) {
  const act = ACTIVACIONES_MAPA.find(a => String(a.id) === String(id));
  if (act) verDetalleActivacion(act);
}

function verDetalleActivacion(act) {
  document.getElementById('det-act-titulo').textContent = act.titulo || '—';
  const estBadge = document.getElementById('det-act-estatus-badge');
  const estKey = (act.estatus || 'programada').toLowerCase();
  estBadge.textContent = (act.estatus || 'programada').replace('-', ' ').replace(/\b\w/g, c => c.toUpperCase());
  estBadge.style.cssText = ESTATUS_STYLES_ACT[estKey] || ESTATUS_STYLES_ACT['programada'];
  const tipoBadge = document.getElementById('det-act-tipo-badge');
  tipoBadge.textContent = TIPO_LABELS_ACT[act.tipo] || act.tipo || '—';
  const tipoColor = TIPO_COLORS_ACT[act.tipo] || '#f59e0b';
  tipoBadge.style.cssText = `background:${tipoColor}20;color:${tipoColor};border:1px solid ${tipoColor}50;`;
  document.getElementById('det-act-fecha').textContent = act.fecha || '—';
  document.getElementById('det-act-candidato').textContent = act.candidato ? ('🙋 ' + act.candidato) : 'Sin candidato';
  document.getElementById('det-act-coordinador').textContent = act.coordinador || '—';
  document.getElementById('det-act-municipio').textContent = act.municipio || '—';
  document.getElementById('det-act-seccion').textContent = act.seccion ? `Sección ${act.seccion}` : '—';
  document.getElementById('det-act-estado').textContent = act.estado || '—';
  document.getElementById('det-act-notas').textContent = act.notas || 'Sin notas.';
  document.getElementById('modal-det-act').classList.add('open');
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
  if (seccionParaEvento && seccionParaEvento.num && seccionParaEvento.num !== '—') {
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

async function guardarEvento() {
  const titulo = document.getElementById('m-titulo').value.trim();
  const fecha  = document.getElementById('m-fecha').value;
  const btn    = document.getElementById('m-save-btn');

  if (!titulo)             { document.getElementById('m-titulo').focus(); mostrarToast('El título es obligatorio'); return; }
  if (!fecha)              { mostrarToast('La fecha es obligatoria'); return; }
  if (!ejeSeleccionadoM)   { mostrarToast('Selecciona un eje'); return; }
  if (!seccionParaEvento || seccionParaEvento.num === '—') { mostrarToast('Selecciona una sección en el mapa'); return; }

  const ini  = document.getElementById('m-inicio').value;
  const fin  = document.getElementById('m-fin').value;
  let horas  = parseFloat(document.getElementById('m-horas').value) || 0;
  if (!horas && ini && fin) {
    const [hi,mi] = ini.split(':').map(Number), [hf,mf] = fin.split(':').map(Number);
    horas = Math.max(0, (hf*60+mf - hi*60-mi) / 60);
  }
  if (!horas) horas = 1;

  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando…';
  btn.disabled  = true;

  const secNum = seccionParaEvento.num;
  const fd = new FormData();
  fd.append('action',      'create');
  fd.append('titulo',      titulo);
  fd.append('fecha',       fecha);
  fd.append('eje',         ejeSeleccionadoM);
  fd.append('subtipo',     document.getElementById('m-subtipo').value);
  fd.append('seccion',     secNum);
  fd.append('estado',      seccionParaEvento.estado    || '');
  fd.append('municipio',   seccionParaEvento.municipio || '');
  fd.append('region',      document.getElementById('m-region').value);
  fd.append('zona',        document.getElementById('m-zona').value);
  fd.append('inicio',      ini);
  fd.append('fin',         fin);
  fd.append('horas',       horas);
  fd.append('responsable', document.getElementById('m-responsable').value.trim());
  fd.append('notas',       document.getElementById('m-notas').value.trim() ||
                           `Evento registrado desde el mapa electoral. Sección ${secNum}.`);
  
  if (seccionParaEvento.latlng) {
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
    mostrarToast(`Evento guardado — Sec. ${secNum}`);
  } catch(err) {
    mostrarToast('Error: ' + err.message);
    console.error(err);
  } finally {
    btn.innerHTML = '<i class="fas fa-check"></i> Guardar Evento';
    btn.disabled  = false;
  }
}

function verDetalleEventoById(id) {
  const ev = eventosData.find(e => String(e.id) === String(id));
  if (ev) verDetalleEvento(ev);
}

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

function colocarMarkerEvento(ev) {
  let latlng = null;
  if (ev.lat && ev.lng) {
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
  
  if (!latlng) return;
  // Normalizar: getCenter() devuelve L.LatLng (.lat/.lng), no un array
  const _lat = latlng.lat !== undefined ? latlng.lat : latlng[0];
  const _lng = latlng.lng !== undefined ? latlng.lng : latlng[1];
  latlng = jitteredLatLng(_lat, _lng, _eventoOffsets);

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
    `<br><button onclick="verDetalleEventoById('${ev.id}')" ` +
    `style="margin-top:8px;padding:5px 10px;background:${color};color:white;border:none;border-radius:6px;` +
    `font-size:11px;font-weight:700;cursor:pointer;font-family:IBM Plex Sans,sans-serif;">` +
    `Ver detalle</button></div>`;
  marker.bindPopup(popupHtml, { maxWidth: 260 });
  layerEventos.addLayer(marker);
}

cargarYPintarEventos();
// Activaciones: datos embebidos desde PHP, pintar inmediatamente
pintarActivaciones();
// Circunscripción: no preseleccionar estado, el usuario lo elige
window.addEventListener('load', () => {
  // Nada — el mapa arranca en vista nacional sin capas
});


// ──────────────────────────────────────────────
// Datos de sección desde BD
// ──────────────────────────────────────────────
async function cargarDatosSeccion(numSeccion) {
  document.getElementById('db-panel').style.display   = 'none';
  document.getElementById('db-no-data').style.display = 'none';
  document.getElementById('db-spinner').style.display = 'block';

  // Configurar enlace dinámico hacia el nuevo Modal Interno
  const btnDetalle = document.getElementById('db-link');
  if(btnDetalle) {
    btnDetalle.onclick = (e) => {
        e.preventDefault();
        abrirModalSeccionCompleta();
    };
  }

  const fmt = n => (n != null && n !== '' && !isNaN(n))
    ? parseInt(n).toLocaleString('es-MX') : '—';
  const pct = (c, m) => (!m || m == 0) ? 0 : Math.min(100, Math.round(c / m * 100));

  try {
    const estadoReq = ESTADOS_DATA[estadoActual]?.label || '<?= htmlspecialchars($estadoCoordinador) ?>';
    const res  = await fetch(`/api/secciones/secciones_api.php?action=get_by_num&seccion=${encodeURIComponent(numSeccion)}&estado=${encodeURIComponent(estadoReq)}`);
    const data = await res.json();

    document.getElementById('db-spinner').style.display = 'none';

    if (!data.ok || !data.seccion) {
      document.getElementById('db-no-data').style.display = 'block';
      return;
    }

    const s = data.seccion;
    
    // ── GUARDAR EN MEMORIA PARA EL MODAL COMPLETO ──
    seccionDetalleActual = s;

    document.getElementById('db-region').textContent = s.region_asignada || '—';
    document.getElementById('db-zona').textContent   = s.zona_asignada   || '—';
    // Mostrar lista_nominal (hombres + mujeres) como TOTAL; fallback a suma de géneros si no está disponible
    const _totalNominal = parseInt(s.lista_nominal) > 0
        ? parseInt(s.lista_nominal)
        : (parseInt(s.lista_hombres||0) + parseInt(s.lista_mujeres||0));
    document.getElementById('db-nominal').textContent       = fmt(_totalNominal);
    document.getElementById('db-lista-hombres').textContent = fmt(s.lista_hombres);
    document.getElementById('db-lista-mujeres').textContent = fmt(s.lista_mujeres);
    
    const coloresEdad = {
        '18-24': '#3498db', '25-34': '#2ecc71', '35-44': '#f1c40f', '45-54': '#e67e22', '55+':   '#9b59b6'
    };

    const edadRows = [
      ['18-24', s.grupo_edad_18_24], ['25-34', s.grupo_edad_25_34],
      ['35-44', s.grupo_edad_35_44], ['45-54', s.grupo_edad_45_54],
      ['55+',   s.grupo_edad_55_mas],
    ].filter(([, v]) => v > 0);

    document.getElementById('db-edad-chips').innerHTML = edadRows
      .map(([l, v]) => {
          const color = coloresEdad[l] || '#6b7280';
          return `<span class="edad-chip" style="background:${color}20; color:${color}; border:1px solid ${color}40;">${l}: ${fmt(v)}</span>`;
      }).join('');

    const meta = parseInt(s.meta_contactos) || 0;
    const cons = parseInt(s.contactos_conseguidos) || 0;
    const p    = pct(cons, meta);
    document.getElementById('db-meta').textContent      = fmt(meta);
    document.getElementById('db-contactos').textContent = fmt(cons);
    document.getElementById('db-progress-fill').style.width = p + '%';
    document.getElementById('db-pct').textContent  = p + '%';
    document.getElementById('db-rem').textContent  = fmt(Math.max(0, meta - cons)) + ' rest.';

    document.getElementById('db-eventos').textContent = fmt(s.eventos_realizados);
    document.getElementById('db-votos').textContent   = fmt(s.metas_votos);

    document.getElementById('db-act-total').textContent = fmt(s.activaciones_totales);
    document.getElementById('db-act-con').textContent   = fmt(s.con_candidato);
    document.getElementById('db-act-sin').textContent   = fmt(s.sin_candidato);

    const setOpen = (wrapId, textId, val) => {
      const ok = val && val.trim();
      document.getElementById(wrapId).style.display = ok ? 'block' : 'none';
      if (ok) document.getElementById(textId).textContent = val;
    };
    setOpen('db-nec-wrap', 'db-necesidades', s.principales_necesidades);
    setOpen('db-enf-wrap', 'db-enfoque',     s.enfoque);
    setOpen('db-per-wrap', 'db-perfil',      s.perfil_socioeconomico);

    const ejeColor = { voto:'#2d7a3e', comunicacion:'#1a73e8', organizacion:'#8b00d4', personal:'#e67e22' };
    const ejeLabel = { voto:'🗳️ Voto', comunicacion:'📣 Comunicación', organizacion:'⚙️ Organización', personal:'👤 Personal' };
    const listEv = document.getElementById('db-eventos-lista');
    const wrapEv = document.getElementById('db-eventos-wrap');
    const evs = s.eventos_lista || [];
    wrapEv.style.display = 'block';
    if (evs.length) {
      listEv.innerHTML = evs.map(ev => {
        const c = ejeColor[ev.eje] || '#666';
        const hora = ev.inicio ? ' · ' + ev.inicio.substring(0,5) : '';
        return '<div style="display:flex;gap:8px;align-items:flex-start;padding:6px 0;border-bottom:1px solid #f0f0f0;">' +
          '<span style="width:7px;height:7px;border-radius:50%;background:' + c + ';flex-shrink:0;margin-top:4px;"></span>' +
          '<div><div style="font-size:12px;font-weight:700;color:#1a1e2a;">' + (ev.titulo || '—') + '</div>' +
          '<div style="font-size:10px;color:#6b7280;">' + (ejeLabel[ev.eje] || ev.eje || '') + ' · ' + (ev.fecha || '') + hora + '</div>' +
          (ev.responsable ? '<div style="font-size:10px;color:#6b7280;">👤 ' + ev.responsable + '</div>' : '') +
          '</div></div>';
      }).join('');
    } else {
      listEv.innerHTML = '<div style="font-size:11px;color:#9ca3af;padding:6px 0;">Sin eventos registrados para esta sección.</div>';
    }

    const estatusBadge = {
      programada:  'background:#fff7ed;color:#d97706;border:1px solid #fde68a;',
      'en-curso':  'background:#eff6ff;color:#1e88e5;border:1px solid #bfdbfe;',
      completada:  'background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0;',
      cancelada:   'background:#fff5f5;color:#e53935;border:1px solid #fecaca;',
    };
    const listAct = document.getElementById('db-act-lista');
    const wrapAct = document.getElementById('db-act-lista-wrap');
    const acts = s.activaciones_lista || [];
    wrapAct.style.display = 'block';
    if (acts.length) {
      listAct.innerHTML = acts.map(a => {
        const badge = estatusBadge[a.estatus] || '';
        const cand  = a.candidato ? '🙋 ' + a.candidato : '🚫 Sin candidato';
        return '<div style="padding:6px 0;border-bottom:1px solid #f0f0f0;">' +
          '<div style="display:flex;justify-content:space-between;align-items:center;gap:6px;flex-wrap:wrap;">' +
          '<span style="font-size:12px;font-weight:700;color:#1a1e2a;">' + (a.titulo || '—') + '</span>' +
          '<span style="font-size:9px;font-weight:700;padding:2px 7px;border-radius:10px;' + badge + '">' + (a.estatus || '').replace('-',' ') + '</span>' +
          '</div>' +
          '<div style="font-size:10px;color:#6b7280;margin-top:2px;">' + (a.fecha_actividad || '') + ' · ' + cand + '</div>' +
          (a.coordinador ? '<div style="font-size:10px;color:#6b7280;">👤 ' + a.coordinador + '</div>' : '') +
          '</div>';
      }).join('');
    } else {
      listAct.innerHTML = '<div style="font-size:11px;color:#9ca3af;padding:6px 0;">Sin activaciones registradas para esta sección.</div>';
    }

    document.getElementById('db-panel').style.display = 'block';

  } catch (err) {
    document.getElementById('db-spinner').style.display = 'none';
    document.getElementById('db-no-data').style.display = 'block';
    console.warn('cargarDatosSeccion error:', err);
  }
}

// ──────────────────────────────────────────────
// MODAL SECUNDARIO: Detalle de Sección (Visualización Pura)
// ──────────────────────────────────────────────
function abrirModalSeccionCompleta() {
    if (!seccionDetalleActual) {
        mostrarToast('Error: No se ha cargado la información de la sección.');
        return;
    }

    const s = seccionDetalleActual;
    const fmt = n => (n != null && n !== '' && !isNaN(n)) ? parseInt(n).toLocaleString('es-MX') : '—';

    document.getElementById('sec-det-titulo').textContent = s.nombre_seccion || '—';
    document.getElementById('sec-det-ubicacion').textContent = [s.estado_nombre || s.estado, s.municipio].filter(Boolean).join(' › ') || '—';
    
    document.getElementById('sec-det-seccion').textContent = s.seccion || '—';
    document.getElementById('sec-det-municipio').textContent = s.municipio || '—';
    document.getElementById('sec-det-df').textContent = s.distrito_federal || '—';
    document.getElementById('sec-det-dl').textContent = s.distrito_local || '—';
    document.getElementById('sec-det-region').textContent = s.region_asignada || '—';
    document.getElementById('sec-det-zona').textContent = s.zona_asignada || '—';

    // Mostrar lista_nominal (hombres + mujeres) como TOTAL; fallback a suma de géneros
    const _modalTotal = parseInt(s.lista_nominal) > 0
        ? parseInt(s.lista_nominal)
        : (parseInt(s.lista_hombres||0) + parseInt(s.lista_mujeres||0));
    document.getElementById('sec-det-padron-electoral').textContent = fmt(_modalTotal);
    document.getElementById('sec-det-lista-hombres').textContent = fmt(s.lista_hombres);
    document.getElementById('sec-det-lista-mujeres').textContent = fmt(s.lista_mujeres);

    let meta = parseInt(s.meta_contactos)||0;
    const cons = parseInt(s.contactos_conseguidos)||0;
    // Base para meta: lista_nominal (dato correcto por sección); fallback a suma de géneros
    if (meta === 0) {
        const base = parseInt(s.lista_nominal) > 0
            ? parseInt(s.lista_nominal)
            : (parseInt(s.lista_hombres||0) + parseInt(s.lista_mujeres||0));
        if (base > 0) meta = Math.round(base * 0.12);
    }
    const p = (!meta || meta === 0) ? 0 : Math.min(100, Math.round((cons / meta) * 100));

    document.getElementById('sec-det-meta-contactos').textContent = fmt(meta);
    document.getElementById('sec-det-contactos').textContent = fmt(cons);
    document.getElementById('sec-det-progress-fill').style.width = p + '%';
    document.getElementById('sec-det-progress-pct').textContent = p + '%';
    document.getElementById('sec-det-progress-rem').textContent = fmt(Math.max(0, meta - cons)) + ' restantes';

    document.getElementById('sec-det-eventos').textContent = fmt(s.eventos_realizados);
    document.getElementById('sec-det-votos').textContent = fmt(s.metas_votos);

    document.getElementById('sec-det-necesidades').innerHTML = s.principales_necesidades ? s.principales_necesidades : `<em style="color:var(--texto-dim)">Sin información</em>`;
    document.getElementById('sec-det-enfoque').innerHTML = s.enfoque ? s.enfoque : `<em style="color:var(--texto-dim)">Sin información</em>`;
    document.getElementById('sec-det-perfil').innerHTML = s.perfil_socioeconomico ? s.perfil_socioeconomico : `<em style="color:var(--texto-dim)">Sin información</em>`;

    document.getElementById('sec-det-act-total').textContent = fmt(s.activaciones_totales);
    document.getElementById('sec-det-act-con').textContent = fmt(s.con_candidato);
    document.getElementById('sec-det-act-sin').textContent = fmt(s.sin_candidato);

    document.getElementById('modal-seccion-completa').classList.add('open');
}

function cerrarModalSeccion() {
    document.getElementById('modal-seccion-completa').classList.remove('open');
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

/* ══════════════════════════════════════════════
   MODO SELECCIÓN DE SECCIONES (desde zonas.php)
══════════════════════════════════════════════ */
function actualizarBannerSeleccion() {
  if (!MODO_SELECCION) return;
  const n     = seccionesZonaSet.size;
  const badge = document.getElementById('sel-badge-cnt');
  const txt   = document.getElementById('sel-counter-txt');
  if (badge) badge.textContent = n + ' seleccionada' + (n !== 1 ? 's' : '');
  if (txt)   txt.textContent   = n > 0
    ? `${n} sección${n!==1?'es':''} marcada${n!==1?'s':''} — clic de nuevo para quitar`
    : 'Haz clic en las secciones del mapa para seleccionarlas';
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

// ══════════════════════════════════════════════════════════════
//  SELECTOR DE ZONA — pinta secciones de la zona seleccionada
// ══════════════════════════════════════════════════════════════
let layerZonaResaltada = null;   // secciones pintadas de la zona activa

async function onRegionChange() {
  const region = document.getElementById('sel-region').value;
  const selZona = document.getElementById('sel-zona');
  selZona.innerHTML = '<option value="">— Zona —</option>';
  selZona.disabled = true;
  limpiarZona();
  if (!region) return;

  try {
    const estadoReq = ESTADOS_DATA[estadoActual]?.label || '';
    const res  = await fetch(`/api/secciones/secciones_api.php?action=get_zonas&region=${encodeURIComponent(region)}&estado=${encodeURIComponent(estadoReq)}`);
    const data = await res.json();
    if (!data.ok || !data.zonas) return;
    data.zonas.forEach(z => {
      const opt = document.createElement('option');
      opt.value = z; opt.textContent = z;
      selZona.appendChild(opt);
    });
    selZona.disabled = false;
  } catch(e) {
    console.warn('onRegionChange error:', e);
    mostrarToast('No se pudieron cargar las zonas');
  }
}

async function onZonaChange() {
  const zona   = document.getElementById('sel-zona').value;
  const region = document.getElementById('sel-region').value;
  limpiarZona();
  if (!zona) return;

  try {
    const estadoReq = ESTADOS_DATA[estadoActual]?.label || '';
    const res  = await fetch(`/api/secciones/secciones_api.php?action=get_secciones_zona&zona=${encodeURIComponent(zona)}&region=${encodeURIComponent(region)}&estado=${encodeURIComponent(estadoReq)}`);
    const data = await res.json();
    if (!data.ok || !data.secciones || data.secciones.length === 0) {
      mostrarToast('Sin secciones registradas para esta zona');
      return;
    }

    const secSet = new Set(data.secciones.map(String));
    const bounds = [];

    layerZonaResaltada = L.layerGroup();

    if (layerSecciones) {
      layerSecciones.eachLayer(layer => {
        if (!layer.feature) return;
        const sec = String(layer.feature.properties.seccion ?? '');
        if (secSet.has(sec)) {
          try {
            const b = layer.getBounds();
            bounds.push(b);
            // Resaltar sección en el mapa con color de zona
            const highlight = L.geoJSON(layer.feature, {
              style: { color:'#d32f2f', weight:2, fillColor:'#ef9a9a', fillOpacity:0.55 }
            });
            layerZonaResaltada.addLayer(highlight);
          } catch(_) {}
        }
      });
    }

    layerZonaResaltada.addTo(map);

    // Zoom a las secciones de la zona
    if (bounds.length > 0) {
      const combined = bounds.reduce((acc, b) => acc.extend(b), bounds[0]);
      map.fitBounds(combined, { padding:[40,40], maxZoom:13 });
      mostrarToast(`Zona "${zona}": ${secSet.size} secciones`);
    } else {
      mostrarToast('Activa la capa Secciones para ver el resaltado');
    }
  } catch(e) {
    console.warn('onZonaChange error:', e);
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

  // Filtrar eventos
  _eventoOffsets = {};
  layerEventos.clearLayers();
  eventosData.forEach(ev => {
    if (!ev.fecha) { colocarMarkerEvento(ev); return; }
    const [y, m] = ev.fecha.split('-');
    if (year  && y !== year)  return;
    if (month && m !== month) return;
    colocarMarkerEvento(ev);
  });

  // Filtrar activaciones
  _activacionOffsets = {};
  layerActivaciones.clearLayers();
  ACTIVACIONES_MAPA.forEach(act => {
    if (!act.fecha) { colocarMarkerActivacion(act); return; }
    const [y, m] = act.fecha.split('-');
    if (year  && y !== year)  return;
    if (month && m !== month) return;
    colocarMarkerActivacion(act);
  });

  const evCount  = layerEventos.getLayers().length;
  const actCount = layerActivaciones.getLayers().length;
  document.getElementById('ev-count-badge').textContent  = evCount;
  document.getElementById('act-count-badge').textContent = actCount;

  if (year || month) {
    const label = [year, month ? document.getElementById('filter-month').options[document.getElementById('filter-month').selectedIndex].text : ''].filter(Boolean).join(' / ');
    mostrarToast(`Filtro: ${label} — ${evCount} eventos, ${actCount} activaciones`);
  }
}

function limpiarFiltros() {
  document.getElementById('filter-year').value  = '';
  document.getElementById('filter-month').value = '';
  aplicarFiltros();
}

</script>

<div class="modal-overlay" id="modal-overlay" onclick="cerrarModalOverlay(event)">
  <div class="modal">
    <button class="modal-close" onclick="cerrarModal()"><i class="fas fa-times"></i></button>
    <h2 class="modal-title">Nuevo Evento</h2>
    <p class="modal-subtitle">Registra un evento en la agenda. La sección se toma del mapa.</p>

    <div class="form-group" style="margin-bottom:20px;">
      <label class="form-label">Sección Electoral <span style="font-size:10px;font-weight:400;color:var(--texto-dim);">(selecciona en el mapa)</span></label>
      <div id="seccion-badge-wrap">
        <span class="seccion-badge empty" id="seccion-badge-text">
          <i class="fa-solid fa-map-marker-alt"></i> Ninguna sección seleccionada
        </span>
      </div>
      <div style="margin-top:6px;">
        <button type="button" onclick="activarSeleccionSeccion()" style="font-size:11px;color:var(--verde);background:none;border:none;cursor:pointer;padding:0;font-family:'IBM Plex Sans',sans-serif;">
          <i class="fas fa-crosshairs"></i> Haz clic aquí, luego en el mapa para cambiar sección
        </button>
      </div>
    </div>

    <div class="form-group" style="margin-bottom:20px;">
      <label class="form-label">Eje *</label>
      <div class="eje-selector-ag" style="margin-top:5px;">
        <button class="eje-btn-ag" data-eje="voto"         onclick="seleccionarEjeM('voto')"         type="button">🗳️ Voto / Territorio</button>
        <button class="eje-btn-ag" data-eje="comunicacion" onclick="seleccionarEjeM('comunicacion')" type="button">📣 Comunicación</button>
        <button class="eje-btn-ag" data-eje="organizacion" onclick="seleccionarEjeM('organizacion')" type="button">⚙️ Organización</button>
      </div>
    </div>

    <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:20px;">
        <div class="form-group" style="margin-bottom:0;">
            <label class="form-label">Subtipo</label>
            <select id="m-subtipo" class="form-input-ag"><option value="">— Selecciona un eje primero —</option></select>
        </div>
        <div class="form-group" style="margin-bottom:0;">
            <label class="form-label">Título *</label>
            <input type="text" id="m-titulo" class="form-input-ag" placeholder="Ej. Recorrido Colonia Pitic">
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:20px;">
      <div class="form-group" style="margin-bottom:0;">
        <label class="form-label">Fecha *</label>
        <input type="date" id="m-fecha" class="form-input-ag">
      </div>
      <div class="form-group" style="margin-bottom:0;">
        <label class="form-label">Estado</label>
        <input type="text" id="m-estado" class="form-input-ag" placeholder="Auto del mapa" readonly style="background:#f0f4f0;">
      </div>
      <div class="form-group" style="margin-bottom:0;">
        <label class="form-label">Municipio</label>
        <input type="text" id="m-municipio" class="form-input-ag" placeholder="Auto del mapa" readonly style="background:#f0f4f0;">
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:20px;">
      <div class="form-group" style="margin-bottom:0;">
        <label class="form-label">Inicio</label>
        <select id="m-inicio" class="form-input-ag" onchange="calcHorasM()">
            <option value="">—</option>
            <?php for($h=0;$h<24;$h++) for($m=0;$m<60;$m+=30) { $t=sprintf('%02d:%02d',$h,$m); echo "<option value='$t'>$t</option>"; } ?>
        </select>
      </div>
      <div class="form-group" style="margin-bottom:0;">
        <label class="form-label">Fin</label>
        <select id="m-fin" class="form-input-ag" onchange="calcHorasM()">
            <option value="">—</option>
            <?php for($h=0;$h<24;$h++) for($m=0;$m<60;$m+=30) { $t=sprintf('%02d:%02d',$h,$m); echo "<option value='$t'>$t</option>"; } ?>
        </select>
      </div>
      <div class="form-group" style="margin-bottom:0;">
        <label class="form-label">Horas</label>
        <input type="number" id="m-horas" class="form-input-ag" min="0" max="24" step="0.5" placeholder="0">
      </div>
    </div>

    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:20px;">
        <div class="form-group" style="margin-bottom:0;">
            <label class="form-label">Región</label>
            <select id="m-region" class="form-input-ag">
                <option value="">— Selecciona —</option>
                <?php foreach($regiones_estatales as $r): ?>
                    <option value="<?= htmlspecialchars($r) ?>"><?= htmlspecialchars($r) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin-bottom:0;">
            <label class="form-label">Zona</label>
            <select id="m-zona" class="form-input-ag">
                <option value="">— Selecciona —</option>
                <?php foreach($zonas_estatales as $z): ?>
                    <option value="<?= htmlspecialchars($z) ?>"><?= htmlspecialchars($z) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="form-group" style="margin-bottom:20px;">
      <label class="form-label">Responsable</label>
      <input type="text" id="m-responsable" class="form-input-ag" placeholder="Nombre del responsable">
    </div>

    <div class="form-group" style="margin-bottom:20px;">
      <label class="form-label">Notas</label>
      <textarea id="m-notas" class="form-input-ag" placeholder="Observaciones, materiales, contexto…"></textarea>
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
      <div style="display:flex;gap:10px;">
        <button class="btn-cancel-ag" onclick="cerrarModalDetAct()">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<div class="modal-overlay" id="modal-seccion-completa" onclick="if(event.target===this)cerrarModalSeccion()">
  <div class="modal" style="max-width:760px;">
    <button class="modal-close" onclick="cerrarModalSeccion()"><i class="fas fa-times"></i></button>
    <h2 class="modal-title" id="sec-det-titulo">—</h2>
    <div class="modal-subtitle" id="sec-det-ubicacion">—</div>

    <div class="sec-det-grid">
      <div class="sec-det-item"><label>Sección</label><span id="sec-det-seccion">—</span></div>
      <div class="sec-det-item"><label>Municipio</label><span id="sec-det-municipio">—</span></div>
      <div class="sec-det-item"><label>Distrito Federal</label><span id="sec-det-df">—</span></div>
      <div class="sec-det-item"><label>Distrito Local</label><span id="sec-det-dl">—</span></div>
      <div class="sec-det-item"><label>Región Asignada</label><span id="sec-det-region">—</span></div>
      <div class="sec-det-item"><label>Zona Asignada</label><span id="sec-det-zona">—</span></div>
    </div>

    <div class="sec-det-section-title"><i class="fas fa-users"></i> Lista Nominal</div>
    <div class="sec-det-grid" style="margin-bottom:16px;grid-template-columns:1fr 1fr 1fr;">
      <div class="sec-det-item">
        <label><i class="fas fa-users" style="color:#6b7280;margin-right:4px;"></i> TOTAL</label>
        <span id="sec-det-padron-electoral" style="font-size:22px;font-weight:900;color:#374151;">—</span>
      </div>
      <div class="sec-det-item">
        <label><i class="fas fa-mars" style="color:#3b82f6;margin-right:4px;"></i> Hombres</label>
        <span id="sec-det-lista-hombres" style="font-size:22px;font-weight:900;color:#3b82f6;">—</span>
      </div>
      <div class="sec-det-item">
        <label><i class="fas fa-venus" style="color:#ec4899;margin-right:4px;"></i> Mujeres</label>
        <span id="sec-det-lista-mujeres" style="font-size:22px;font-weight:900;color:#ec4899;">—</span>
      </div>
    </div>

    <div class="sec-det-section-title"><i class="fas fa-address-book"></i> Contactos y Avance</div>
    <div class="sec-det-grid">
      <div class="sec-det-item">
        <label>Meta de Contactos</label>
        <div style="margin-top:4px;"><span id="sec-det-meta-contactos" style="font-size:20px;font-weight:900;color:var(--texto);">—</span></div>
      </div>
      <div class="sec-det-item">
        <label>Contactos Conseguidos</label>
        <div style="margin-top:4px;"><span id="sec-det-contactos" style="font-size:20px;font-weight:900;color:var(--verde);">—</span></div>
      </div>
    </div>
    
    <div class="sec-modal-progress-wrap">
      <div class="sec-modal-progress-bg">
        <div class="sec-modal-progress-fill" id="sec-det-progress-fill" style="width:0%"></div>
      </div>
      <div class="sec-modal-progress-label">
        <span id="sec-det-progress-pct">0%</span>
        <span id="sec-det-progress-rem">0 restantes</span>
      </div>
    </div>

    <div class="sec-det-grid" style="margin-top:16px;">
      <div class="sec-det-item">
        <label>Eventos Realizados</label>
        <span id="sec-det-eventos">—</span>
      </div>
      <div class="sec-det-item">
        <label>Metas de Votos</label>
        <span id="sec-det-votos">—</span>
      </div>
    </div>

    <div class="sec-det-section-title"><i class="fas fa-edit"></i> Campos Abiertos</div>
    <div style="margin-bottom:14px;">
      <label class="form-label">Principales Necesidades</label>
      <div class="sec-field-readonly" id="sec-det-necesidades">—</div>
    </div>
    <div style="margin-bottom:14px;">
      <label class="form-label">Enfoque</label>
      <div class="sec-field-readonly" id="sec-det-enfoque">—</div>
    </div>
    <div style="margin-bottom:14px;">
      <label class="form-label">Perfil Socioeconómico</label>
      <div class="sec-field-readonly" id="sec-det-perfil">—</div>
    </div>

    <div class="sec-det-section-title"><i class="fas fa-bolt"></i> Activaciones Totales / Eventos</div>
    <div class="sec-act-stats">
      <div class="sec-act-stat-card">
        <div class="sec-act-stat-num" id="sec-det-act-total">—</div>
        <div class="sec-act-stat-lbl">Total</div>
      </div>
      <div class="sec-act-stat-card">
        <div class="sec-act-stat-num" id="sec-det-act-con">—</div>
        <div class="sec-act-stat-lbl">Con Candidato</div>
      </div>
      <div class="sec-act-stat-card">
        <div class="sec-act-stat-num" id="sec-det-act-sin">—</div>
        <div class="sec-act-stat-lbl">Sin Candidato</div>
      </div>
    </div>

    <div class="modal-footer-ag">
      <div style="margin-left:auto;">
        <button class="btn-cancel-ag" onclick="cerrarModalSeccion()">Cerrar</button>
      </div>
    </div>
  </div>
</div>

</body>
</html>