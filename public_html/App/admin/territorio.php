<?php
// Habilitar visualización de errores para debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// 1. Carga del sistema y seguridad
require_once __DIR__ . '/../../includes/bootstrap.php';

// Configuración Google Sheets
define('GOOGLE_SHEET_ID', '1PB10SQiyOBoRoD7lIoA4dhSC9ITLvixMP5VrOoIRQ-E');
define('GOOGLE_SHEET_NAME', 'DB');

$rol = $_SESSION['user_role'] ?? '';
$permitidos = ['admin', 'administrador', 'superadmin', 'super admin'];

if (!in_array($rol, $permitidos)) {
    header('Location: /public/index.php'); 
    exit();
}

// Función para obtener datos de Google Sheets
function getGoogleSheetData() {
    $sheetId = GOOGLE_SHEET_ID;
    $sheetName = GOOGLE_SHEET_NAME;
    
    // URL para obtener TODAS las columnas del sheet (A-N)
    $url = "https://docs.google.com/spreadsheets/d/{$sheetId}/gviz/tq?tqx=out:csv&sheet={$sheetName}";
    
    $users = [];
    
    try {
        // Configurar contexto para la petición
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]
        ]);
        
        $csvData = @file_get_contents($url, false, $context);
        
        if ($csvData === false) {
            throw new Exception("No se pudo conectar con Google Sheets");
        }
        
        // Parsear CSV
        $lines = explode("\n", $csvData);
        $headers = null;
        
        foreach ($lines as $index => $line) {
            if (empty(trim($line))) continue;
            
            // Parsear la línea CSV manualmente para manejar comillas
            $row = str_getcsv($line);
            
            // Primera fila son los encabezados
            if ($index === 0) {
                $headers = $row;
                continue;
            }
            
            // Mapeo de columnas según la estructura del Google Sheet:
            // A = fecha
            // B = celular  
            // C = tienes_codigo
            // D = codigo
            // E = nombre
            // F = apellido
            // G = municipio
            // H = estado
            // I = colonia
            // J = sabes_seccion (no se usa)
            // K = seccion
            // L = identificacion/rol
            // M = temas_interes
            // N = propuesta
            
            // Asegurarse de que tengamos al menos 14 columnas (A-N)
            $row = array_pad($row, 14, '');
            
            $nombre_completo = trim($row[4] ?? '') . ' ' . trim($row[5] ?? ''); // E + F (nombre + apellido)
            
            $users[] = [
                'fecha' => trim($row[0] ?? ''),                    // A - fecha
                'celular' => trim($row[1] ?? ''),                  // B - celular
                'tiene_codigo' => trim($row[2] ?? ''),             // C - tienes_codigo
                'codigo' => trim($row[3] ?? ''),                   // D - codigo
                'nombre' => trim($nombre_completo),                // E + F - nombre completo
                'apellido' => trim($row[5] ?? ''),                 // F - apellido (guardado aparte)
                'municipio' => trim($row[6] ?? ''),                // G - municipio
                'estado' => trim($row[7] ?? ''),                   // H - estado
                'colonia' => trim($row[8] ?? ''),                  // I - colonia
                'seccion' => trim($row[10] ?? ''),                 // K - seccion electoral
                'rol' => trim($row[11] ?? ''),                     // L - identificacion/rol
                'identificacion' => trim($row[11] ?? ''),          // L - como te identificas
                'temas_interes' => trim($row[12] ?? ''),           // M - temas de interes
                'propuesta' => trim($row[13] ?? ''),               // N - propuesta
                'foto' => ''
            ];
        }
        
    } catch (Exception $e) {
        error_log("Error al obtener datos de Google Sheets: " . $e->getMessage());
        // Retornar array vacío en caso de error
        $users = [];
    }
    
    return $users;
}

// Función para obtener secciones del DROPDOWN desde la BD
// IMPORTANTE: Esto NO afecta los datos de usuarios que siguen viniendo de Google Sheets
function getSeccionesPorEstado($estado, $municipio = 'all') {
    global $pdo;
    
    if (!$pdo) return [];
    
    $secciones = [];
    
    try {
        // Mapeo de estados a tablas SQL
        $tablaEstados = [
            'Baja California' => 'territorio_baja_california',
            'Baja California Sur' => 'territorio_baja_california_sur',
            'Sonora' => 'territorio_sonora',
            'Chihuahua' => 'territorio_chihuahua',
            'Sinaloa' => 'territorio_sinaloa',
            'Durango' => 'territorio_durango',
            'Nayarit' => 'territorio_nayarit',
            'Jalisco' => 'territorio_jalisco',
        ];
        
        // Si el estado no tiene tabla SQL, retornar vacío
        if (!isset($tablaEstados[$estado])) {
            return [];
        }
        
        $tabla = $tablaEstados[$estado];
        
        // Construir query
        $sql = "SELECT secciones FROM {$tabla} WHERE 1=1";
        $params = [];
        
        if ($municipio !== 'all') {
            $sql .= " AND municipio = :municipio";
            $params[':municipio'] = $municipio;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Procesar secciones (están separadas por comas en la BD)
        foreach ($results as $row) {
            if (!empty($row['secciones'])) {
                $seccionesArray = explode(',', $row['secciones']);
                $secciones = array_merge($secciones, $seccionesArray);
            }
        }
        
        // Limpiar, eliminar duplicados y ordenar
        $secciones = array_map('trim', $secciones);
        $secciones = array_unique($secciones);
        $secciones = array_filter($secciones, function($s) { 
            return !empty($s) && is_numeric($s); 
        });
        sort($secciones, SORT_NUMERIC);
        
    } catch (PDOException $e) {
        error_log("Error al obtener secciones de BD: " . $e->getMessage());
        return [];
    }
    
    return $secciones;
}

$users = getGoogleSheetData();



// Estados de la región noroeste con sus municipios
$estadosRegion = [
    'Baja California' => ['Ensenada', 'Mexicali', 'Tecate', 'Tijuana', 'Playas de Rosarito'],
    'Baja California Sur' => ['Comondú', 'La Paz', 'Loreto', 'Los Cabos', 'Mulegé'],
    'Sonora' => ['Aconchi', 'Agua Prieta', 'Alamos', 'Altar', 'Arivechi', 'Arizpe', 'Atil', 'Bacadéhuachi', 'Bacanora', 'Bacerac', 'Bacoachi', 'Bácum', 'Banámichi', 'Baviácora', 'Bavispe', 'Benjamín Hill', 'Caborca', 'Cajeme', 'Cananea', 'Carbó', 'La Colorada', 'Cucurpe', 'Cumpas', 'Divisaderos', 'Empalme', 'Etchojoa', 'Fronteras', 'Granados', 'Guaymas', 'Hermosillo', 'Huachinera', 'Huásabas', 'Huatabampo', 'Huépac', 'Ímuris', 'Magdalena', 'Mazatán', 'Moctezuma', 'Naco', 'Nácori Chico', 'Nacozari de García', 'Navojoa', 'Nogales', 'Onavas', 'Opodepe', 'Oquitoa', 'Pitiquito', 'Puerto Peñasco', 'Quiriego', 'Rayón', 'Rosario', 'Sahuaripa', 'San Felipe de Jesús', 'San Javier', 'San Luis Río Colorado', 'San Miguel de Horcasitas', 'San Pedro de la Cueva', 'Santa Ana', 'Santa Cruz', 'Sáric', 'Soyopa', 'Suaqui Grande', 'Tepache', 'Trincheras', 'Tubutama', 'Ures', 'Villa Hidalgo', 'Villa Pesqueira', 'Yécora', 'General Plutarco Elías Calles', 'Benito Juárez', 'San Ignacio Río Muerto'],
    'Chihuahua' => ['Ahumada', 'Aldama', 'Allende', 'Aquiles Serdán', 'Ascensión', 'Bachíniva', 'Balleza', 'Batopilas', 'Bocoyna', 'Buenaventura', 'Camargo', 'Carichí', 'Casas Grandes', 'Chihuahua', 'Chínipas', 'Coronado', 'Coyame del Sotol', 'Cuauhtémoc', 'Cusihuiriachi', 'Delicias', 'Dr. Belisario Domínguez', 'El Tule', 'Galeana', 'Gómez Farías', 'Gran Morelos', 'Guachochi', 'Guadalupe', 'Guadalupe y Calvo', 'Guazapares', 'Guerrero', 'Hidalgo del Parral', 'Huejotitán', 'Ignacio Zaragoza', 'Janos', 'Jiménez', 'Juárez', 'Julimes', 'La Cruz', 'López', 'Madera', 'Maguarichi', 'Manuel Benavides', 'Matachí', 'Matamoros', 'Meoqui', 'Morelos', 'Moris', 'Namiquipa', 'Nonoava', 'Nuevo Casas Grandes', 'Ocampo', 'Ojinaga', 'Praxedis G. Guerrero', 'Riva Palacio', 'Rosales', 'Rosario', 'San Francisco de Borja', 'San Francisco de Conchos', 'San Francisco del Oro', 'Santa Bárbara', 'Santa Isabel', 'Satevó', 'Saucillo', 'Temósachic', 'Urique', 'Uruachi', 'Valle de Zaragoza'],
    'Sinaloa' => ['Ahome', 'Angostura', 'Badiraguato', 'Choix', 'Concordia', 'Cosalá', 'Culiacán', 'El Fuerte', 'Elota', 'Escuinapa', 'Guasave', 'Mazatlán', 'Mocorito', 'Navolato', 'Rosario', 'Salvador Alvarado', 'San Ignacio', 'Sinaloa'],
    'Durango' => ['Canatlán', 'Canelas', 'Coneto de Comonfort', 'Cuencamé', 'Durango', 'El Oro', 'General Simón Bolívar', 'Gómez Palacio', 'Guadalupe Victoria', 'Guanaceví', 'Hidalgo', 'Indé', 'Lerdo', 'Mapimí', 'Mezquital', 'Nazas', 'Nombre de Dios', 'Nuevo Ideal', 'Ocampo', 'Otáez', 'Pánuco de Coronado', 'Peñón Blanco', 'Poanas', 'Pueblo Nuevo', 'Rodeo', 'San Bernardo', 'San Dimas', 'San Juan de Guadalupe', 'San Juan del Río', 'San Luis del Cordero', 'San Pedro del Gallo', 'Santa Clara', 'Santiago Papasquiaro', 'Súchil', 'Tamazula', 'Tepehuanes', 'Tlahualilo', 'Topia', 'Vicente Guerrero'],
    'Nayarit' => ['Acaponeta', 'Ahuacatlán', 'Amatlán de Cañas', 'Bahía de Banderas', 'Compostela', 'Del Nayar', 'Huajicori', 'Ixtlán del Río', 'Jala', 'La Yesca', 'Rosamorada', 'Ruíz', 'San Blas', 'San Pedro Lagunillas', 'Santa María del Oro', 'Santiago Ixcuintla', 'Tecuala', 'Tepic', 'Tuxpan', 'Xalisco'],
    'Jalisco' => ['Acatic', 'Acatlán de Juárez', 'Ahualulco de Mercado', 'Amacueca', 'Amatitán', 'Ameca', 'Arandas', 'Atemajac de Brizuela', 'Atengo', 'Atenguillo', 'Atotonilco el Alto', 'Atoyac', 'Autlán de Navarro', 'Ayotlán', 'Ayutla', 'Bolaños', 'Cabo Corrientes', 'Cañadas de Obregón', 'Casimiro Castillo', 'Chapala', 'Chimaltitán', 'Chiquilistlán', 'Cocula', 'Colotlán', 'Concepción de Buenos Aires', 'Cuautitlán de García Barragán', 'Cuautla', 'Cuculo', 'Degollado', 'Ejutla', 'El Arenal', 'El Grullo', 'El Limón', 'El Salto', 'Encarnación de Díaz', 'Etzatlán', 'Gómez Farías', 'Guachinango', 'Guadalajara', 'Hostotipaquillo', 'Huejúcar', 'Huejuquilla el Alto', 'Ixtlahuacán de los Membrillos', 'Ixtlahuacán del Río', 'Jalostotitlán', 'Jamay', 'Jesús María', 'Jilotlán de los Dolores', 'Jocotepec', 'Juanacatlán', 'Juchitlán', 'La Barca', 'La Huerta', 'La Manzanilla de la Paz', 'Lagos de Moreno', 'Magdalena', 'Mascota', 'Mazamitla', 'Mexticacán', 'Mezquitic', 'Mixtlán', 'Ocotlán', 'Ojuelos de Jalisco', 'Pihuamo', 'Poncitlán', 'Puerto Vallarta', 'Quitupan', 'San Cristóbal de la Barranca', 'San Diego de Alejandría', 'San Gabriel', 'San Juan de los Lagos', 'San Juanito de Escobedo', 'San Julián', 'San Marcos', 'San Martín de Bolaños', 'San Martín de Hidalgo', 'San Miguel el Alto', 'San Sebastián del Oeste', 'Santa María de los Ángeles', 'Santa María del Oro', 'Sayula', 'Tala', 'Talpa de Allende', 'Tamazula de Gordiano', 'Tapalpa', 'Tecalitlán', 'Techaluta de Montenegro', 'Tenamaxtlán', 'Teocaltiche', 'Teocuitatlán de Corona', 'Tepatitlán de Morelos', 'Tequila', 'Teuchitlán', 'Tizapán el Alto', 'Tlajomulco de Zúñiga', 'Tlaquepaque', 'Tolimán', 'Tomatlán', 'Tonalá', 'Tonaya', 'Tonila', 'Totatiche', 'Tototlán', 'Tuxcacuesco', 'Tuxcueca', 'Tuxpan', 'Unión de San Antonio', 'Unión de Tula', 'Valle de Guadalupe', 'Valle de Juárez', 'Villa Corona', 'Villa Guerrero', 'Villa Hidalgo', 'Villa Purificación', 'Yahualica de González Gallo', 'Zacoalco de Torres', 'Zapopan', 'Zapotiltic', 'Zapotlán del Rey', 'Zapotlán del Vadillo', 'Zapotlán el Grande', 'Zapotlanejo']
];

// Capitales de cada estado (primer municipio alfabético de la capital)
$capitales = [
    'Baja California' => 'Mexicali',
    'Baja California Sur' => 'La Paz',
    'Sonora' => 'Hermosillo',
    'Chihuahua' => 'Chihuahua',
    'Sinaloa' => 'Culiacán',
    'Durango' => 'Durango',
    'Nayarit' => 'Tepic',
    'Jalisco' => 'Guadalajara'
];

// Metas reales por estado (tomadas de dashboard.php)
$metasPorEstado = [
    'Baja California' => 130984,
    'Baja California Sur' => 29652,
    'Sonora' => 106892,
    'Chihuahua' => 140326,
    'Sinaloa' => 120082,
    'Durango' => 69630,
    'Nayarit' => 48421,
    'Jalisco' => 362132
];

// Obtener filtros
$estadoFiltro = $_GET['estado'] ?? 'all';
$municipioFiltro = $_GET['municipio'] ?? 'all';
$seccionFiltro = $_GET['seccion'] ?? 'all';
$rolFiltro = $_GET['rol'] ?? 'all';

// Obtener estados únicos y ordenarlos alfabéticamente
$estadosUnicos = array_unique(array_column($users, 'estado'));
$estadosUnicos = array_filter($estadosUnicos, function($estado) {
    // Filtrar estados vacíos o que sean números/coordenadas
    return !empty($estado) && !is_numeric($estado) && !preg_match('/^[\d\.\,\-]+$/', $estado);
});
sort($estadosUnicos);

// Obtener municipios del estado seleccionado
$municipios = [];
if ($estadoFiltro !== 'all') {
    $municipiosData = array_filter($users, function($user) use ($estadoFiltro) {
        return $user['estado'] === $estadoFiltro;
    });
    $municipios = array_unique(array_column($municipiosData, 'municipio'));
    $municipios = array_filter($municipios, function($municipio) {
        return !empty($municipio);
    });
    sort($municipios);
}

// Obtener secciones para el DROPDOWN
// PRIORIDAD: Si el estado tiene tabla SQL, usa eso. Si no, usa Google Sheets
$seccionesUnicas = [];

if ($estadoFiltro !== 'all') {
    // Intentar obtener secciones de la BD primero
    $seccionesUnicas = getSeccionesPorEstado($estadoFiltro, $municipioFiltro);
    
    // Si la BD no tiene datos (estado sin tabla SQL), usar Google Sheets
    if (empty($seccionesUnicas)) {
        $seccionesTemp = array_filter($users, function($user) use ($estadoFiltro, $municipioFiltro) {
            $matchEstado = ($user['estado'] === $estadoFiltro);
            $matchMunicipio = ($municipioFiltro === 'all' || $user['municipio'] === $municipioFiltro);
            return $matchEstado && $matchMunicipio;
        });
        $seccionesUnicas = array_unique(array_column($seccionesTemp, 'seccion'));
        $seccionesUnicas = array_filter($seccionesUnicas, function($seccion) {
            return !empty($seccion) && is_numeric($seccion);
        });
        sort($seccionesUnicas, SORT_NUMERIC);
    }
} else {
    // Si no hay estado seleccionado, obtener TODAS de Google Sheets
    $seccionesUnicas = array_unique(array_column($users, 'seccion'));
    $seccionesUnicas = array_filter($seccionesUnicas, function($seccion) {
        return !empty($seccion);
    });
    sort($seccionesUnicas);
}

// Filtrar usuarios
$usersFiltrados = array_filter($users, function($user) use ($estadoFiltro, $municipioFiltro, $seccionFiltro, $rolFiltro) {
    $matchEstado = ($estadoFiltro === 'all' || $user['estado'] === $estadoFiltro);
    $matchMunicipio = ($municipioFiltro === 'all' || $user['municipio'] === $municipioFiltro);
    $matchSeccion = ($seccionFiltro === 'all' || $user['seccion'] === $seccionFiltro);
    $matchRol = ($rolFiltro === 'all' || stripos($user['rol'], $rolFiltro) !== false);
    
    return $matchEstado && $matchMunicipio && $matchSeccion && $matchRol;
});

// Paginación
$per_page = 20;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $per_page;
$total_items = count($usersFiltrados);
$total_pages = ceil($total_items / $per_page);
$usersPaginados = array_slice($usersFiltrados, $offset, $per_page);

// Calcular estadísticas
// Determinar la meta según el estado seleccionado
if ($estadoFiltro !== 'all' && isset($metasPorEstado[$estadoFiltro])) {
    $meta = $metasPorEstado[$estadoFiltro];
} else {
    // Si es "all", usar la suma de todas las metas
    $meta = array_sum($metasPorEstado);
}
$total = count($usersFiltrados);
$pct = round(($total / $meta) * 100, 2);

// Contar roles (nuevas categorías)
$rolesCount = [
    'simpatizante' => 0,
    'militante' => 0,
    'voluntario' => 0,
    'evento' => 0,
    'indeciso' => 0
];

foreach ($usersFiltrados as $user) {
    $rolLower = strtolower($user['rol']);
    if (stripos($rolLower, 'simpatizante') !== false) {
        $rolesCount['simpatizante']++;
    } elseif (stripos($rolLower, 'militante') !== false) {
        $rolesCount['militante']++;
    } elseif (stripos($rolLower, 'voluntario') !== false || stripos($rolLower, 'activista') !== false) {
        $rolesCount['voluntario']++;
    } elseif (stripos($rolLower, 'evento') !== false || stripos($rolLower, 'vine') !== false) {
        $rolesCount['evento']++;
    } else {
        $rolesCount['indeciso']++;
    }
}

$statsData = [
    'total' => $total,
    'meta' => $meta,
    'pct' => $pct,
    'roles' => $rolesCount
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Territorio - Proyecto Verde</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* --- VARIABLES GLOBALES --- */
        :root {
            --primary: #2d7a3e;
            --primary-dark: #1f5a2d;
            --dark: #1a1a1a;
            --light: #f0f7f0;
            --white: #ffffff;
            --gray-100: #f8f9fa;
            --gray-200: #e9ecef;
            --text-muted: #6c757d;
            --shadow-sm: 0 10px 30px rgba(0,0,0,0.05);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Arial', sans-serif; 
            background: var(--light); 
            color: var(--dark); 
            overflow-x: hidden; 
        }

        .container { 
            max-width: 100%; margin: 0; background: var(--white); min-height: 100vh; 
            position: relative; padding-left: 330px; padding-right: 50px; padding-bottom: 40px;
        }

        /* --- HEADER --- */
        .page-header { padding: 50px 0 40px 0; display: flex; justify-content: space-between; align-items: flex-end; }
        .ai-badge { 
            color: var(--primary); font-size: 13px; font-weight: 800; letter-spacing: 4px; 
            margin-bottom: 15px; display: inline-block; text-transform: uppercase; 
            background: rgba(45,122,62,0.1); padding: 5px 15px; border-radius: 20px;
        }
        .main-title { font-size: 54px; font-weight: 900; line-height: 1; margin-bottom: 15px; color: var(--dark); }
        .description { color: #666; font-size: 16px; line-height: 1.6; max-width: 600px; }

        .header-actions { display: flex; gap: 10px; }
        .btn-action {
            background: var(--white); color: var(--dark); padding: 12px 25px; 
            border-radius: 12px; border: 2px solid var(--gray-200); font-weight: 700; cursor: pointer;
            display: flex; align-items: center; gap: 10px; text-decoration: none; font-size: 14px;
        }
        .btn-action:hover { border-color: var(--primary); color: var(--primary); }

        /* --- FILTROS --- */
        .filters-bar {
            background: var(--white); border: 1px solid var(--gray-200);
            border-radius: 16px; padding: 20px; margin-bottom: 30px;
            display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px;
            box-shadow: var(--shadow-sm);
        }
        .form-group { display: flex; flex-direction: column; gap: 8px; }
        .form-label { font-size: 12px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); }
        .form-select {
            width: 100%; padding: 12px 15px; border-radius: 10px;
            border: 2px solid var(--gray-200); background: var(--gray-100);
            font-size: 14px; font-weight: 600; color: var(--dark); cursor: pointer;
        }

        /* --- MAPA GRID --- */
        .map-grid {
            display: grid; grid-template-columns: 2fr 1fr; gap: 30px; margin-bottom: 40px; height: 600px; 
        }

        .map-card {
            background: var(--gray-100); border-radius: 20px; border: 1px solid var(--gray-200);
            position: relative; overflow: hidden;
            display: flex; align-items: center; justify-content: center;
            cursor: grab;
        }
        .map-card:active { cursor: grabbing; }

        .map-controls {
            position: absolute; bottom: 20px; right: 20px;
            display: flex; flex-direction: column; gap: 10px; z-index: 10;
        }
        .ctrl-btn {
            width: 40px; height: 40px; background: var(--white); border: none;
            border-radius: 50%;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15); cursor: pointer;
            font-size: 16px; color: #555; transition: 0.2s;
            display: flex; align-items: center; justify-content: center;
        }
        .ctrl-btn:hover { background: var(--dark); color: white; transform: scale(1.1); }
        
        #mapWrapper { 
            width: 100%; height: 100%; 
            display: block;
            transform-origin: center center;
            will-change: transform;
        }
        #mx-map { 
            width: 100%; height: 100%; 
            display: block; pointer-events: none;
        }

        /* --- TARJETA DE PROGRESO --- */
        .progress-card {
            background: linear-gradient(135deg, var(--primary) 0%, #1a4a25 100%);
            color: white; border-radius: 20px; padding: 30px;
            display: flex; flex-direction: column; position: relative; overflow: hidden;
            box-shadow: 0 20px 40px rgba(45, 122, 62, 0.3);
        }
        .stat-title { font-size: 28px; font-weight: 800; line-height: 1.1; margin-bottom: 15px; }
        .stat-number-big { font-size: 60px; font-weight: 900; line-height: 1; margin-bottom: 10px; }
        .custom-progress { height: 8px; background: rgba(255,255,255,0.2); border-radius: 10px; overflow: hidden; margin-bottom: 10px; }
        .custom-bar { height: 100%; background: white; width: 0; border-radius: 10px; transition: width 1s ease; }
        
        /* Chart dentro de la card */
        .chart-wrapper {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.2);
        }
        .chart-title {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 15px;
            text-align: center;
        }

        /* Leyenda de roles */
        .roles-legend {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-top: 15px;
        }
        .legend-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 12px;
            font-weight: 600;
        }
        .legend-color {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        /* Municipios list */
        .municipios-container {
            background: rgba(255, 255, 255, 0.1); 
            border-radius: 15px; 
            padding: 15px; 
            margin-top: 20px;
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .municipios-title {
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 10px;
            color: rgba(255,255,255,0.95);
        }
        .municipio-item {
            padding: 8px 12px;
            margin-bottom: 5px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
        }

        /* --- TABLA --- */
        .table-section {
            background: var(--white); border-radius: 20px; padding: 30px;
            border: 1px solid var(--gray-200); margin-bottom: 40px;
        }
        .section-title {
            font-size: 20px;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 20px;
        }
        .modern-table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .modern-table th { 
            text-align: left; padding: 15px 20px; color: var(--text-muted); 
            font-size: 12px; text-transform: uppercase; letter-spacing: 1px;
            border-bottom: 2px solid var(--gray-200); 
        }
        .modern-table td { 
            padding: 20px; border-bottom: 1px solid var(--gray-200); 
            font-size: 14px; color: var(--dark); font-weight: 500; vertical-align: middle;
        }
        .modern-table tr:hover td { background: var(--gray-100); }
        .modern-table tr:last-child td { border-bottom: none; }
        
        .badge { 
            padding: 6px 12px; border-radius: 30px; font-size: 11px; font-weight: 700; 
            text-transform: uppercase; display: inline-block;
        }
        .badge-coord { background: #e3f2fd; color: #1565c0; }
        .badge-promo { background: #e8f5e9; color: #2e7d32; }

        /* Dropdown de acciones (3 puntitos) */
        .action-cell {
            position: relative;
        }
        .dropdown-trigger {
            background: transparent;
            border: none;
            cursor: pointer;
            font-size: 18px;
            color: var(--text-muted);
            padding: 5px 10px;
            transition: all 0.2s ease;
            border-radius: 8px;
        }
        .dropdown-trigger:hover {
            color: var(--dark);
            background: var(--gray-100);
        }
        .dropdown-menu {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            z-index: 1000;
            min-width: 200px;
            overflow: hidden;
            opacity: 0;
            transform: translateY(-10px) scale(0.95);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .dropdown-menu.show {
            display: block;
            opacity: 1;
            transform: translateY(0) scale(1);
        }
        .dropdown-item {
            padding: 12px 20px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            color: var(--dark);
            text-decoration: none;
            font-weight: 500;
            border-bottom: 1px solid transparent;
        }
        .dropdown-item:hover {
            background: var(--gray-100);
            padding-left: 25px;
        }
        .dropdown-item:last-child {
            border-bottom: none;
        }
        .dropdown-item i {
            width: 18px;
            text-align: center;
            font-size: 14px;
        }
        .dropdown-item.danger {
            color: #d32f2f;
        }
        .dropdown-item.danger:hover {
            background: #ffebee;
            color: #c62828;
        }

        /* --- PAGINACION --- */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }
        .pagination a {
            padding: 10px 15px;
            background: var(--gray-100);
            border-radius: 8px;
            text-decoration: none;
            color: var(--dark);
            font-weight: 600;
        }
        .pagination a:hover {
            background: var(--primary);
            color: white;
        }
        .pagination .active {
            background: var(--primary);
            color: white;
        }
        .pagination .disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* --- MODAL DE DETALLES --- */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .modal-overlay.open {
            display: flex;
            opacity: 1;
        }
        .modal-content {
            background: white;
            border-radius: 20px;
            width: 90%;
            max-width: 700px;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            transform: scale(0.9) translateY(20px);
            transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .modal-overlay.open .modal-content {
            transform: scale(1) translateY(0);
        }
        .modal-header {
            padding: 30px;
            border-bottom: 2px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-title {
            font-size: 24px;
            font-weight: 900;
            color: var(--dark);
        }
        .modal-close {
            background: var(--gray-100);
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: var(--text-muted);
            transition: all 0.2s;
        }
        .modal-close:hover {
            background: var(--primary);
            color: white;
            transform: rotate(90deg);
        }
        .modal-body {
            padding: 30px;
        }
        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }
        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .detail-item.full-width {
            grid-column: 1 / -1;
        }
        .detail-label {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--text-muted);
            letter-spacing: 0.5px;
        }
        .detail-value {
            font-size: 16px;
            font-weight: 600;
            color: var(--dark);
            padding: 12px 15px;
            background: var(--gray-100);
            border-radius: 10px;
            min-height: 45px;
            display: flex;
            align-items: center;
        }
        .detail-value.highlight {
            background: linear-gradient(135deg, var(--primary), #1a4a25);
            color: white;
        }

        @media (max-width: 768px) {
            .detail-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 1200px) { 
            .map-grid { grid-template-columns: 1fr; height: auto; } 
            .progress-card { height: auto; min-height: 500px; } 
        }
        @media (max-width: 768px) { 
            .container { padding-left: 20px; padding-right: 20px; } 
            .filters-bar { grid-template-columns: 1fr; } 
            .page-header { flex-direction: column; } 
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/components/sidebar_admin.php'; ?>

    <div class="container">
        <div class="page-header">
            <div>
                <span class="ai-badge">GESTIÓN TERRITORIAL</span>
                <h1 class="main-title">Territorio</h1>
                <p class="description">
                    Visualiza y gestiona la estructura territorial del partido político.
                </p>
            </div>
            <div class="header-actions">
                <a href="territorio.php" class="btn-action">
                    <i class="fas fa-sync-alt"></i> Actualizar
                </a>
            </div>
        </div>

        <!-- FILTROS -->
        <form method="GET" class="filters-bar" id="filtrosForm">
            <div class="form-group">
                <label class="form-label">Estado</label>
                <select name="estado" id="estadoSelect" class="form-select" onchange="actualizarMunicipios(this.value); this.form.submit()">
                    <option value="all" <?= $estadoFiltro === 'all' ? 'selected' : '' ?>>Todos los Estados</option>
                    <?php foreach ($estadosRegion as $estado => $capital): ?>
                        <option value="<?= htmlspecialchars($estado) ?>" <?= $estadoFiltro === $estado ? 'selected' : '' ?>>
                            <?= htmlspecialchars($estado) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Municipio</label>
                <select name="municipio" id="municipioSelect" class="form-select" onchange="this.form.submit()" <?= $estadoFiltro === 'all' ? 'disabled' : '' ?>>
                    <option value="all">Todos los Municipios</option>
                    <?php if ($estadoFiltro !== 'all' && isset($estadosRegion[$estadoFiltro])): ?>
                        <?php 
                        // Obtener capital del estado
                        $capital = $capitales[$estadoFiltro] ?? null;
                        
                        // Mostrar capital primero si existe
                        if ($capital) {
                            echo '<option value="' . htmlspecialchars($capital) . '"';
                            if ($municipioFiltro === $capital) echo ' selected';
                            echo '>' . htmlspecialchars($capital) . ' (Capital)</option>';
                        }
                        
                        // Mostrar municipios predefinidos del estado
                        foreach ($estadosRegion[$estadoFiltro] as $mun): 
                            // Saltar la capital ya que ya la mostramos
                            if ($mun === $capital) continue;
                        ?>
                            <option value="<?= htmlspecialchars($mun) ?>" <?= $municipioFiltro === $mun ? 'selected' : '' ?>>
                                <?= htmlspecialchars($mun) ?>
                            </option>
                        <?php endforeach; ?>
                        
                        <?php 
                        // Agregar municipios adicionales de la BD que no estén en la lista predefinida
                        foreach ($municipios as $mun): 
                            if (!in_array($mun, $estadosRegion[$estadoFiltro]) && $mun !== $capital):
                        ?>
                            <option value="<?= htmlspecialchars($mun) ?>" <?= $municipioFiltro === $mun ? 'selected' : '' ?>>
                                <?= htmlspecialchars($mun) ?> *
                            </option>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                    <?php endif; ?>
                </select>
            </div>
<div class="form-group">
    <label class="form-label">Sección Electoral</label>
    <select name="seccion" id="seccionSelect" class="form-select" onchange="this.form.submit()" <?= $estadoFiltro === 'all' ? 'disabled' : '' ?>>
        <option value="all" <?= $seccionFiltro === 'all' ? 'selected' : '' ?>>
            <?= $estadoFiltro === 'all' ? 'Selecciona un estado primero' : 'Todas las Secciones' ?>
        </option>
        <?php if (!empty($seccionesUnicas)): ?>
            <?php foreach ($seccionesUnicas as $seccion): ?>
                <option value="<?= htmlspecialchars($seccion) ?>" <?= $seccionFiltro === $seccion ? 'selected' : '' ?>>
                    Sección <?= htmlspecialchars($seccion) ?>
                </option>
            <?php endforeach; ?>
        <?php endif; ?>
    </select>
</div>
            <div class="form-group">
                <label class="form-label">Rol</label>
                <select name="rol" class="form-select" onchange="this.form.submit()">
                    <option value="all" <?= $rolFiltro === 'all' ? 'selected' : '' ?>>Todos los Roles</option>
                    <option value="simpatizante" <?= $rolFiltro === 'simpatizante' ? 'selected' : '' ?>>Simpatizante</option>
                    <option value="militante" <?= $rolFiltro === 'militante' ? 'selected' : '' ?>>Militante</option>
                    <option value="voluntario" <?= $rolFiltro === 'voluntario' ? 'selected' : '' ?>>Voluntario/Activista</option>
                    <option value="evento" <?= $rolFiltro === 'evento' ? 'selected' : '' ?>>Solo vine al evento</option>
                    <option value="indeciso" <?= $rolFiltro === 'indeciso' ? 'selected' : '' ?>>Aún no decido</option>
                </select>
            </div>
        </form>

        <!-- MAPA Y STATS -->
        <div class="map-grid">
            <div class="map-card" id="mapContainer">
                <div class="map-controls">
                    <button class="ctrl-btn" id="zoomIn"><i class="fas fa-plus"></i></button>
                    <button class="ctrl-btn" id="zoomOut"><i class="fas fa-minus"></i></button>
                    <button class="ctrl-btn" id="resetZoom"><i class="fas fa-redo"></i></button>
                </div>
                <div id="mapWrapper">
                    <object data="../../assets/images/mx.svg" type="image/svg+xml" id="mx-map">
                        Mapa no encontrado
                    </object>
                </div>
            </div>

            <div class="progress-card">
<div class="stat-title" id="estadoTitle">
    <?php 
    if ($estadoFiltro !== 'all') {
        echo htmlspecialchars($estadoFiltro);
        if ($municipioFiltro !== 'all') {
            echo ' - ' . htmlspecialchars($municipioFiltro);
        }
    } else {
        echo 'Región Noroeste';
    }
    ?>
</div>
                <div class="stat-number-big" id="metaPercent">0%</div>
                <div class="custom-progress">
                    <div class="custom-bar" id="progressBar"></div>
                </div>
                <div style="font-size: 14px; opacity: 0.9; font-weight: 600;" id="metaDetails">
                    0 de 123,750 afiliados
                </div>

                <!-- Gráfico de distribución por rol -->
                <div class="chart-wrapper">
                    <div class="chart-title">Distribución por Rol</div>
                    <div style="height: 180px; position: relative;">
                        <canvas id="rolesChart"></canvas>
                    </div>
                    <!-- Leyenda de roles -->
                    <div class="roles-legend">
                        <div class="legend-item">
                            <div class="legend-color" style="background: #2d7a3e;"></div>
                            <span>Simpatizante</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background: #4caf50;"></div>
                            <span>Militante</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background: #81c784;"></div>
                            <span>Voluntario/Activista</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background: #a5d6a7;"></div>
                            <span>Solo vine al evento</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background: #c8e6c9;"></div>
                            <span>Aún no decido</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TABLA DE ESTRUCTURA ASIGNADA -->
        <div class="table-section">
            <h3 class="section-title">
                <i class="fas fa-users"></i> Estructura Asignada
            </h3>
            
            <?php if (count($usersPaginados) > 0): ?>
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>NOMBRE</th>
                        <th>CELULAR</th>
                        <th>ESTADO</th>
                        <th>MUNICIPIO</th>
                        <th>SECCIÓN</th>
                        <th>ROL</th>
                        <th style="text-align: center;">ACCIONES</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usersPaginados as $index => $user): ?>
                    <tr>
                        <td style="font-weight: 600;"><?= htmlspecialchars($user['nombre']) ?></td>
                        <td><?= htmlspecialchars($user['celular']) ?></td>
                        <td><?= htmlspecialchars($user['estado']) ?></td>
                        <td><?= htmlspecialchars($user['municipio']) ?></td>
                        <td><?= htmlspecialchars($user['seccion']) ?></td>
                        <td>
                            <span class="badge badge-promo"><?= htmlspecialchars($user['rol']) ?></span>
                        </td>
                        <td class="action-cell" style="text-align: center;">
                            <button class="dropdown-trigger" onclick="toggleDropdown(event, this)">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <div class="dropdown-menu">
                                <a href="#" class="dropdown-item" onclick="event.preventDefault(); verDetalles(<?= $index ?>)">
                                    <i class="fas fa-info-circle"></i> Detalles
                                </a>
                                <a href="#" class="dropdown-item" onclick="event.preventDefault(); editarUsuario('<?= htmlspecialchars($user['nombre']) ?>')">
                                    <i class="fas fa-edit"></i> Editar
                                </a>
                                <a href="#" class="dropdown-item danger" onclick="event.preventDefault(); eliminarUsuario('<?= htmlspecialchars($user['nombre']) ?>')">
                                    <i class="fas fa-trash"></i> Eliminar
                                </a>
                                <a href="https://wa.me/52<?= preg_replace('/[^0-9]/', '', $user['celular']) ?>" target="_blank" class="dropdown-item">
                                    <i class="fab fa-whatsapp"></i> Mandar WhatsApp
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="pagination">
                <?php
                $prev = $page - 1;
                $next = $page + 1;

                if ($prev > 0) {
                    $params = http_build_query(array_merge($_GET, ['page' => $prev]));
                    echo "<a href='?$params'>Anterior</a>";
                } else {
                    echo "<a class='disabled'>Anterior</a>";
                }

                for ($i = 1; $i <= $total_pages; $i++) {
                    $active = ($i == $page) ? 'active' : '';
                    $params = http_build_query(array_merge($_GET, ['page' => $i]));
                    echo "<a href='?$params' class='$active'>$i</a>";
                }

                if ($next <= $total_pages) {
                    $params = http_build_query(array_merge($_GET, ['page' => $next]));
                    echo "<a href='?$params'>Siguiente</a>";
                } else {
                    echo "<a class='disabled'>Siguiente</a>";
                }
                ?>
            </div>
            <?php else: ?>
            <p style="text-align: center; padding: 40px; color: var(--text-muted);">
                No se encontraron resultados con los filtros seleccionados.
            </p>
            <?php endif; ?>
        </div>
    </div>

    <!-- MODAL DE DETALLES -->
    <div class="modal-overlay" id="detallesModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-user-circle"></i> Detalles del Afiliado
                </h3>
                <button class="modal-close" onclick="cerrarDetalles()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="detail-grid">
                    <div class="detail-item full-width">
                        <span class="detail-label">Nombre Completo</span>
                        <div class="detail-value highlight" id="detNombre">-</div>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Fecha de Afiliación</span>
                        <div class="detail-value" id="detFecha">-</div>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Celular</span>
                        <div class="detail-value" id="detCelular">-</div>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">¿Tienes código de afiliación?</span>
                        <div class="detail-value" id="detTieneCodigo">-</div>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Código de Afiliación</span>
                        <div class="detail-value" id="detCodigo">-</div>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Municipio</span>
                        <div class="detail-value" id="detMunicipio">-</div>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Estado</span>
                        <div class="detail-value" id="detEstado">-</div>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Colonia</span>
                        <div class="detail-value" id="detColonia">-</div>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Sección Electoral</span>
                        <div class="detail-value" id="detSeccion">-</div>
                    </div>
                    <div class="detail-item full-width">
                        <span class="detail-label">¿Cómo te identificas con el partido?</span>
                        <div class="detail-value" id="detIdentificacion">-</div>
                    </div>
                    <div class="detail-item full-width">
                        <span class="detail-label">¿Qué temas te importan más?</span>
                        <div class="detail-value" id="detTemas">-</div>
                    </div>
                    <div class="detail-item full-width">
                        <span class="detail-label">Si pudieras mejorar una sola cosa en tu colonia, ¿cuál sería? - Propuesta</span>
                        <div class="detail-value" id="detPropuesta">-</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Datos PHP convertidos a JavaScript
        const usersData = <?php echo json_encode($usersPaginados); ?>;
        const statsData = <?php echo json_encode($statsData); ?>;
        const estadoSeleccionado = <?php echo json_encode($estadoFiltro); ?>;
        
        // Metas reales por estado
        const metasPorEstado = <?php echo json_encode($metasPorEstado); ?>;

        // Formateador de números con coma
        const formatNum = (num) => new Intl.NumberFormat('es-MX').format(num);

        // Actualizar datos tarjeta
        if(typeof statsData !== 'undefined') {
            document.getElementById('metaPercent').innerText = statsData.pct + '%';
            document.getElementById('progressBar').style.width = statsData.pct + '%';
            document.getElementById('metaDetails').innerText = `${formatNum(statsData.total)} de ${formatNum(statsData.meta)} afiliados`;
        }

        // --- GRÁFICO DE DISTRIBUCIÓN POR ROL ---
        const rolesCtx = document.getElementById('rolesChart').getContext('2d');
        const rolesData = [
            statsData.roles.simpatizante,
            statsData.roles.militante,
            statsData.roles.voluntario,
            statsData.roles.evento,
            statsData.roles.indeciso
        ];

        const rolesChart = new Chart(rolesCtx, {
            type: 'doughnut',
            data: {
                labels: ['Simpatizante', 'Militante', 'Voluntario/Activista', 'Solo vine al evento', 'Aún no decido'],
                datasets: [{
                    data: rolesData,
                    backgroundColor: ['#2d7a3e', '#4caf50', '#81c784', '#a5d6a7', '#c8e6c9'],
                    borderWidth: 0,
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: { 
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        titleFont: { size: 14, weight: 'bold' },
                        bodyFont: { size: 13 },
                        callbacks: {
                            label: function(context) {
                                const value = context.parsed;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((value / total) * 100).toFixed(1);
                                return ` ${value.toLocaleString()} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });

        // --- MAPA INTERACTIVO ---
        const mapContainer = document.getElementById('mapContainer');
        const mapWrapper = document.getElementById('mapWrapper');
        
        let state = {
            scale: 1,
            panning: false,
            pointX: 0,
            pointY: 0,
            startX: 0,
            startY: 0
        };

        const setTransform = () => {
            mapWrapper.style.transform = `translate(${state.pointX}px, ${state.pointY}px) scale(${state.scale})`;
        }

        mapContainer.onmousedown = (e) => {
            e.preventDefault();
            state.startX = e.clientX - state.pointX;
            state.startY = e.clientY - state.pointY;
            state.panning = true;
            mapContainer.style.cursor = 'grabbing';
        }

        mapContainer.onmouseup = (e) => {
            e.preventDefault();
            state.panning = false;
            mapContainer.style.cursor = 'grab';
        }

        mapContainer.onmousemove = (e) => {
            e.preventDefault();
            if (!state.panning) return;
            state.pointX = e.clientX - state.startX;
            state.pointY = e.clientY - state.startY;
            setTransform();
        }
        
        mapContainer.onmouseleave = () => {
            state.panning = false;
            mapContainer.style.cursor = 'grab';
        }

        mapContainer.addEventListener('wheel', (e) => {
            e.preventDefault();
            const delta = -e.deltaY;
            const factor = delta > 0 ? 1.1 : 0.9;
            let newScale = state.scale * factor;
            if (newScale < 1) newScale = 1;
            if (newScale > 8) newScale = 8;
            state.scale = newScale;
            setTransform();
        });

        document.getElementById('zoomIn').onclick = () => { 
            state.scale = Math.min(state.scale * 1.2, 8); 
            setTransform(); 
        };
        document.getElementById('zoomOut').onclick = () => { 
            state.scale = Math.max(state.scale / 1.2, 1); 
            setTransform(); 
        };
        document.getElementById('resetZoom').onclick = () => { 
            state.scale = 1; 
            state.pointX = 0; 
            state.pointY = 0; 
            setTransform(); 
        };

        // --- COLOREADO Y FUNCIONALIDAD DEL MAPA ---
        let currentHoverState = null;
        
        // Sincronizar dropdown con mapa
        const estadoDropdown = document.querySelector('select[name="estado"]');
        if (estadoDropdown) {
            estadoDropdown.addEventListener('change', function() {
                // El formulario se enviará automáticamente por el onchange
            });
        }
        
        window.addEventListener('load', function() {
            const svgObject = document.getElementById('mx-map');
            
            // Identificadores de estados de la región noroeste
            const targets = [
                'mx-son', 'sonora', 'son',
                'mx-chh', 'chihuahua', 'chh',
                'mx-sin', 'sinaloa', 'sin',
                'mx-jal', 'jalisco', 'jal',
                'mx-dur', 'durango', 'dur',
                'mx-nay', 'nayarit', 'nay',
                'mx-bcn', 'baja california', 'bc', 'bja',
                'mx-bcs', 'baja california sur', 'bcs'
            ];

            // Mapeo de identificadores a nombres de estados
            // IMPORTANTE: Baja California Sur debe ir ANTES que Baja California para evitar matches incorrectos
            const estadosMap = {
                'sonora': 'Sonora',
                'son': 'Sonora',
                'chihuahua': 'Chihuahua',
                'chh': 'Chihuahua',
                'sinaloa': 'Sinaloa',
                'sin': 'Sinaloa',
                'jalisco': 'Jalisco',
                'jal': 'Jalisco',
                'durango': 'Durango',
                'dur': 'Durango',
                'nayarit': 'Nayarit',
                'nay': 'Nayarit',
                'baja california sur': 'Baja California Sur',  // PRIMERO BCS
                'bcs': 'Baja California Sur',
                'mx-bcs': 'Baja California Sur',
                'baja california': 'Baja California',  // DESPUÉS BC
                'bc': 'Baja California',
                'bcn': 'Baja California',
                'bja': 'Baja California',
                'mx-bcn': 'Baja California'
            };

            const colorGreen = '#2d7a3e';
            const colorGray = '#e0e0e0';
            const colorHoverGreen = '#1f5a2d';
            const colorHoverGray = '#cccccc';
            const colorDynamicHover = '#4caf50'; // Color más claro para hover dinámico

            if (svgObject) {
                const initMap = () => {
                    const svgDoc = svgObject.contentDocument;
                    if (!svgDoc) return;

                    const svgRoot = svgDoc.querySelector('svg');
                    if(svgRoot) {
                        svgRoot.setAttribute('width', '100%');
                        svgRoot.setAttribute('height', '100%');
                        if(!svgRoot.hasAttribute('viewBox')) {
                            svgRoot.setAttribute('viewBox', '0 0 1000 700'); 
                        }
                    }

                    const paths = svgDoc.querySelectorAll('path, polygon');
                    
                    paths.forEach(path => {
                        let id = (path.getAttribute('id') || '').toLowerCase();
                        let name = (path.getAttribute('name') || '').toLowerCase();
                        let dataName = (path.getAttribute('data-name') || '').toLowerCase();
                        let className = (path.getAttribute('class') || '').toLowerCase();
                        
                        let parent = path.parentNode;
                        let parentId = (parent && parent.tagName === 'g') ? (parent.getAttribute('id') || '').toLowerCase() : '';
                        let parentName = (parent && parent.tagName === 'g') ? (parent.getAttribute('name') || '').toLowerCase() : '';

                        const clean = (str) => str.normalize("NFD").replace(/[\u0300-\u036f]/g, "");
                        
                        const allIdentifiers = [
                            clean(id), clean(name), clean(dataName), clean(className), 
                            clean(parentId), clean(parentName)
                        ];

                        // Determinar si este path pertenece a un estado del noroeste
                        let isNoroeste = targets.some(target => 
                            allIdentifiers.some(ident => ident.includes(target) && ident !== '')
                        );

                        // Determinar el nombre del estado para este path
                        let estadoNombre = null;
                        
                        // Buscar primero coincidencias específicas de BCS para evitar confusión con BC
                        for (const identifier of allIdentifiers) {
                            const lowerIdent = identifier.toLowerCase();
                            // Prioridad 1: Baja California Sur (matches específicos)
                            if (lowerIdent.includes('baja california sur') || 
                                lowerIdent === 'bcs' || 
                                lowerIdent === 'mx-bcs') {
                                estadoNombre = 'Baja California Sur';
                                break;
                            }
                        }
                        
                        // Si no encontramos BCS, buscar BC y otros estados
                        if (!estadoNombre) {
                            for (const identifier of allIdentifiers) {
                                const lowerIdent = identifier.toLowerCase();
                                
                                // Evitar que "baja california" solo coincida con BCS
                                if (lowerIdent.includes('baja california') && 
                                    !lowerIdent.includes('sur') && 
                                    !lowerIdent.includes('bcs')) {
                                    estadoNombre = 'Baja California';
                                    break;
                                }
                                
                                // Buscar otros estados
                                for (const [key, value] of Object.entries(estadosMap)) {
                                    if (key === 'baja california sur' || key === 'baja california') continue; // Ya procesados arriba
                                    if (lowerIdent.includes(key)) {
                                        estadoNombre = value;
                                        break;
                                    }
                                }
                                if (estadoNombre) break;
                            }
                        }

                        // Función para actualizar el color del path
                        const updateColor = () => {
                            let shouldBeGreen = false;
                            let isHovered = (currentHoverState === estadoNombre);
                            
                            if (estadoSeleccionado !== 'all' && estadoSeleccionado !== null) {
                                // Solo el estado seleccionado en verde
                                shouldBeGreen = (estadoNombre === estadoSeleccionado);
                            } else {
                                // Todos los estados del noroeste en verde
                                shouldBeGreen = isNoroeste;
                            }

                            // Aplicar color según estado y hover
                            if (isHovered && isNoroeste) {
                                path.style.fill = colorDynamicHover;
                            } else if (shouldBeGreen) {
                                path.style.fill = colorGreen;
                            } else {
                                path.style.fill = colorGray;
                            }
                        };

                        // Configuración inicial del path
                        path.style.pointerEvents = 'all'; 
                        path.style.stroke = '#ffffff';
                        path.style.strokeWidth = '0.5';
                        path.style.transition = 'fill 0.3s ease';
                        path.style.cursor = isNoroeste ? 'pointer' : 'default';
                        
                        updateColor();
                        
                        // Eventos de hover para estados del noroeste
                        if (isNoroeste && estadoNombre) {
                            path.addEventListener('mouseenter', () => {
                                currentHoverState = estadoNombre;
                                // Actualizar título dinámicamente
                                document.getElementById('estadoTitle').textContent = estadoNombre;
                                // Actualizar todos los paths
                                paths.forEach(p => {
                                    if (p._updateColor) p._updateColor();
                                });
                            });
                            
path.addEventListener('mouseleave', () => {
    currentHoverState = null;
    // Restaurar título según filtros activos
    const estadoActual = '<?= $estadoFiltro ?>';
    const municipioActual = '<?= $municipioFiltro ?>';
    
    let titleText = 'Región Noroeste';
    if (estadoActual !== 'all') {
        titleText = estadoActual;
        if (municipioActual !== 'all') {
            titleText += ' - ' + municipioActual;
        }
    }
    document.getElementById('estadoTitle').textContent = titleText;
                                // Actualizar todos los paths
                                paths.forEach(p => {
                                    if (p._updateColor) p._updateColor();
                                });
                            });
                        }
                        
                        // Click para filtrar por estado
                        if (isNoroeste && estadoNombre) {
                            path.addEventListener('click', () => {
                                window.location.href = `?estado=${encodeURIComponent(estadoNombre)}`;
                            });
                        }

                        // Guardar función de actualización
                        path._updateColor = updateColor;
                    });
                };
                
                svgObject.addEventListener('load', initMap);
                // Intentar inicializar inmediatamente en caso de que ya esté cargado
                setTimeout(initMap, 100);
            }
        });

        // --- DROPDOWN DE ACCIONES ---
        function toggleDropdown(event, btn) {
            event.stopPropagation();
            
            const menu = btn.nextElementSibling;
            const isCurrentlyOpen = menu.classList.contains('show');
            
            // Cerrar todos los dropdowns abiertos
            document.querySelectorAll('.dropdown-menu.show').forEach(m => {
                m.classList.remove('show');
            });
            
            // Si no estaba abierto, abrirlo
            if (!isCurrentlyOpen) {
                // Forzar reflow para la animación
                menu.style.display = 'block';
                setTimeout(() => {
                    menu.classList.add('show');
                }, 10);
            }
        }

        // Cerrar dropdowns al hacer click fuera
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.action-cell')) {
                document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                    menu.classList.remove('show');
                    setTimeout(() => {
                        if (!menu.classList.contains('show')) {
                            menu.style.display = 'none';
                        }
                    }, 300);
                });
            }
        });

        // Funciones de acciones
        function verDetalles(index) {
            const user = usersData[index];
            if (!user) return;
            
            // Llenar el modal con los datos
            document.getElementById('detNombre').textContent = user.nombre;
            document.getElementById('detFecha').textContent = user.fecha || 'N/A';
            document.getElementById('detCelular').textContent = user.celular;
            document.getElementById('detTieneCodigo').textContent = user.tiene_codigo || 'N/A';
            document.getElementById('detCodigo').textContent = user.codigo || 'N/A';
            document.getElementById('detMunicipio').textContent = user.municipio || 'N/A';
            document.getElementById('detEstado').textContent = user.estado;
            document.getElementById('detColonia').textContent = user.colonia || 'N/A';
            document.getElementById('detSeccion').textContent = user.seccion || 'N/A';
            document.getElementById('detIdentificacion').textContent = user.identificacion || user.rol;
            document.getElementById('detTemas').textContent = user.temas_interes || 'N/A';
            document.getElementById('detPropuesta').textContent = user.propuesta || 'N/A';
            
            // Mostrar el modal
            const modal = document.getElementById('detallesModal');
            modal.style.display = 'flex';
            setTimeout(() => {
                modal.classList.add('open');
                document.body.style.overflow = 'hidden';
            }, 10);
        }

        function cerrarDetalles() {
            const modal = document.getElementById('detallesModal');
            modal.classList.remove('open');
            document.body.style.overflow = '';
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300);
        }

        // Cerrar modal al hacer click en el overlay
        document.getElementById('detallesModal').addEventListener('click', function(e) {
            if (e.target === this) {
                cerrarDetalles();
            }
        });

        function editarUsuario(nombre) {
            alert('Editar usuario: ' + nombre + '\n\n⚠️ Funcionalidad en desarrollo');
        }

        function eliminarUsuario(nombre) {
            if (confirm('¿Estás seguro de eliminar a ' + nombre + ' de la vista?\n\n')) {
                // Buscar la fila en la tabla
                const rows = document.querySelectorAll('.modern-table tbody tr');
                rows.forEach(row => {
                    const nombreCell = row.querySelector('td:first-child');
                    if (nombreCell && nombreCell.textContent.trim() === nombre) {
                        // Añadir animación de salida
                        row.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                        row.style.opacity = '0';
                        row.style.transform = 'translateX(-20px)';
                        
                        // Eliminar después de la animación
                        setTimeout(() => {
                            row.remove();
                            // Mostrar mensaje de éxito
                            alert('✓ ' + nombre + ' ha sido eliminado de la vista');
                        }, 300);
                    }
                });
            }
        }

        // --- FUNCIÓN PARA ACTUALIZAR MUNICIPIOS ---
function actualizarMunicipios(estado) {
    const municipioSelect = document.getElementById('municipioSelect');
    const seccionSelect = document.getElementById('seccionSelect');
    
    if (estado === 'all') {
        municipioSelect.disabled = true;
        municipioSelect.innerHTML = '<option value="all">Todos los Municipios</option>';
        seccionSelect.disabled = true;
        seccionSelect.innerHTML = '<option value="all">Selecciona un estado primero</option>';
        // Restaurar título a Región Noroeste
        document.getElementById('estadoTitle').textContent = 'Región Noroeste';
        return;
    }
    
    // Actualizar título con el estado seleccionado
    document.getElementById('estadoTitle').textContent = estado;
    
    // Habilitar secciones cuando hay estado seleccionado
    seccionSelect.disabled = false;
            
            municipioSelect.disabled = false;
            
            // Municipios predefinidos por estado
            const municipiosPredefinidos = {
                'Baja California': ['Ensenada', 'Mexicali', 'Tecate', 'Tijuana', 'Playas de Rosarito'],
                'Baja California Sur': ['Comondú', 'La Paz', 'Loreto', 'Los Cabos', 'Mulegé'],
                'Sonora': ['Aconchi', 'Agua Prieta', 'Alamos', 'Altar', 'Arivechi', 'Arizpe', 'Atil', 'Bacadéhuachi', 'Bacanora', 'Bacerac', 'Bacoachi', 'Bácum', 'Banámichi', 'Baviácora', 'Bavispe', 'Benjamín Hill', 'Caborca', 'Cajeme', 'Cananea', 'Carbó', 'La Colorada', 'Cucurpe', 'Cumpas', 'Divisaderos', 'Empalme', 'Etchojoa', 'Fronteras', 'Granados', 'Guaymas', 'Hermosillo', 'Huachinera', 'Huásabas', 'Huatabampo', 'Huépac', 'Ímuris', 'Magdalena', 'Mazatán', 'Moctezuma', 'Naco', 'Nácori Chico', 'Nacozari de García', 'Navojoa', 'Nogales', 'Onavas', 'Opodepe', 'Oquitoa', 'Pitiquito', 'Puerto Peñasco', 'Quiriego', 'Rayón', 'Rosario', 'Sahuaripa', 'San Felipe de Jesús', 'San Javier', 'San Luis Río Colorado', 'San Miguel de Horcasitas', 'San Pedro de la Cueva', 'Santa Ana', 'Santa Cruz', 'Sáric', 'Soyopa', 'Suaqui Grande', 'Tepache', 'Trincheras', 'Tubutama', 'Ures', 'Villa Hidalgo', 'Villa Pesqueira', 'Yécora', 'General Plutarco Elías Calles', 'Benito Juárez', 'San Ignacio Río Muerto'],
                'Chihuahua': ['Ahumada', 'Aldama', 'Allende', 'Aquiles Serdán', 'Ascensión', 'Bachíniva', 'Balleza', 'Batopilas', 'Bocoyna', 'Buenaventura', 'Camargo', 'Carichí', 'Casas Grandes', 'Chihuahua', 'Chínipas', 'Coronado', 'Coyame del Sotol', 'Cuauhtémoc', 'Cusihuiriachi', 'Delicias', 'Dr. Belisario Domínguez', 'El Tule', 'Galeana', 'Gómez Farías', 'Gran Morelos', 'Guachochi', 'Guadalupe', 'Guadalupe y Calvo', 'Guazapares', 'Guerrero', 'Hidalgo del Parral', 'Huejotitán', 'Ignacio Zaragoza', 'Janos', 'Jiménez', 'Juárez', 'Julimes', 'La Cruz', 'López', 'Madera', 'Maguarichi', 'Manuel Benavides', 'Matachí', 'Matamoros', 'Meoqui', 'Morelos', 'Moris', 'Namiquipa', 'Nonoava', 'Nuevo Casas Grandes', 'Ocampo', 'Ojinaga', 'Praxedis G. Guerrero', 'Riva Palacio', 'Rosales', 'Rosario', 'San Francisco de Borja', 'San Francisco de Conchos', 'San Francisco del Oro', 'Santa Bárbara', 'Santa Isabel', 'Satevó', 'Saucillo', 'Temósachic', 'Urique', 'Uruachi', 'Valle de Zaragoza'],
                'Sinaloa': ['Ahome', 'Angostura', 'Badiraguato', 'Choix', 'Concordia', 'Cosalá', 'Culiacán', 'El Fuerte', 'Elota', 'Escuinapa', 'Guasave', 'Mazatlán', 'Mocorito', 'Navolato', 'Rosario', 'Salvador Alvarado', 'San Ignacio', 'Sinaloa'],
                'Durango': ['Canatlán', 'Canelas', 'Coneto de Comonfort', 'Cuencamé', 'Durango', 'El Oro', 'General Simón Bolívar', 'Gómez Palacio', 'Guadalupe Victoria', 'Guanaceví', 'Hidalgo', 'Indé', 'Lerdo', 'Mapimí', 'Mezquital', 'Nazas', 'Nombre de Dios', 'Nuevo Ideal', 'Ocampo', 'Otáez', 'Pánuco de Coronado', 'Peñón Blanco', 'Poanas', 'Pueblo Nuevo', 'Rodeo', 'San Bernardo', 'San Dimas', 'San Juan de Guadalupe', 'San Juan del Río', 'San Luis del Cordero', 'San Pedro del Gallo', 'Santa Clara', 'Santiago Papasquiaro', 'Súchil', 'Tamazula', 'Tepehuanes', 'Tlahualilo', 'Topia', 'Vicente Guerrero'],
                'Nayarit': ['Acaponeta', 'Ahuacatlán', 'Amatlán de Cañas', 'Bahía de Banderas', 'Compostela', 'Del Nayar', 'Huajicori', 'Ixtlán del Río', 'Jala', 'La Yesca', 'Rosamorada', 'Ruíz', 'San Blas', 'San Pedro Lagunillas', 'Santa María del Oro', 'Santiago Ixcuintla', 'Tecuala', 'Tepic', 'Tuxpan', 'Xalisco'],
                'Jalisco': ['Acatic', 'Acatlán de Juárez', 'Ahualulco de Mercado', 'Amacueca', 'Amatitán', 'Ameca', 'Arandas', 'Atemajac de Brizuela', 'Atengo', 'Atenguillo', 'Atotonilco el Alto', 'Atoyac', 'Autlán de Navarro', 'Ayotlán', 'Ayutla', 'Bolaños', 'Cabo Corrientes', 'Cañadas de Obregón', 'Casimiro Castillo', 'Chapala', 'Chimaltitán', 'Chiquilistlán', 'Cocula', 'Colotlán', 'Concepción de Buenos Aires', 'Cuautitlán de García Barragán', 'Cuautla', 'Cuculo', 'Degollado', 'Ejutla', 'El Arenal', 'El Grullo', 'El Limón', 'El Salto', 'Encarnación de Díaz', 'Etzatlán', 'Gómez Farías', 'Guachinango', 'Guadalajara', 'Hostotipaquillo', 'Huejúcar', 'Huejuquilla el Alto', 'Ixtlahuacán de los Membrillos', 'Ixtlahuacán del Río', 'Jalostotitlán', 'Jamay', 'Jesús María', 'Jilotlán de los Dolores', 'Jocotepec', 'Juanacatlán', 'Juchitlán', 'La Barca', 'La Huerta', 'La Manzanilla de la Paz', 'Lagos de Moreno', 'Magdalena', 'Mascota', 'Mazamitla', 'Mexticacán', 'Mezquitic', 'Mixtlán', 'Ocotlán', 'Ojuelos de Jalisco', 'Pihuamo', 'Poncitlán', 'Puerto Vallarta', 'Quitupan', 'San Cristóbal de la Barranca', 'San Diego de Alejandría', 'San Gabriel', 'San Juan de los Lagos', 'San Juanito de Escobedo', 'San Julián', 'San Marcos', 'San Martín de Bolaños', 'San Martín de Hidalgo', 'San Miguel el Alto', 'San Sebastián del Oeste', 'Santa María de los Ángeles', 'Santa María del Oro', 'Sayula', 'Tala', 'Talpa de Allende', 'Tamazula de Gordiano', 'Tapalpa', 'Tecalitlán', 'Techaluta de Montenegro', 'Tenamaxtlán', 'Teocaltiche', 'Teocuitatlán de Corona', 'Tepatitlán de Morelos', 'Tequila', 'Teuchitlán', 'Tizapán el Alto', 'Tlajomulco de Zúñiga', 'Tlaquepaque', 'Tolimán', 'Tomatlán', 'Tonalá', 'Tonaya', 'Tonila', 'Totatiche', 'Tototlán', 'Tuxcacuesco', 'Tuxcueca', 'Tuxpan', 'Unión de San Antonio', 'Unión de Tula', 'Valle de Guadalupe', 'Valle de Juárez', 'Villa Corona', 'Villa Guerrero', 'Villa Hidalgo', 'Villa Purificación', 'Yahualica de González Gallo', 'Zacoalco de Torres', 'Zapopan', 'Zapotiltic', 'Zapotlán del Rey', 'Zapotlán del Vadillo', 'Zapotlán el Grande', 'Zapotlanejo']
            };
            
            // Capitales de los estados
            const capitales = {
                'Baja California': 'Mexicali',
                'Baja California Sur': 'La Paz',
                'Sonora': 'Hermosillo',
                'Chihuahua': 'Chihuahua',
                'Sinaloa': 'Culiacán',
                'Durango': 'Durango',
                'Nayarit': 'Tepic',
                'Jalisco': 'Guadalajara'
            };
            
            let options = '<option value="all">Todos los Municipios</option>';
            
            // Agregar capital primero si existe
            if (capitales[estado]) {
                options += `<option value="${capitales[estado]}">${capitales[estado]} (Capital)</option>`;
            }
            
            // Agregar municipios predefinidos del estado (excepto la capital)
            const municipiosEstado = municipiosPredefinidos[estado] || [];
            municipiosEstado.forEach(municipio => {
                if (municipio !== capitales[estado]) {
                    options += `<option value="${municipio}">${municipio}</option>`;
                }
            });
            
            // Agregar municipios de la BD que no estén en la lista predefinida
            const usersData = <?php echo json_encode(array_values($users)); ?>;
            const usuariosEstado = usersData.filter(u => u.estado === estado);
            const municipiosBD = [...new Set(usuariosEstado.map(u => u.municipio).filter(m => m))].sort();
            
            municipiosBD.forEach(municipio => {
                if (!municipiosEstado.includes(municipio) && municipio !== capitales[estado]) {
                    options += `<option value="${municipio}">${municipio} *</option>`;
                }
            });
            
            municipioSelect.innerHTML = options;
        }
// Actualizar título cuando cambie el municipio
        document.getElementById('municipioSelect').addEventListener('change', function() {
            const estadoActual = document.getElementById('estadoSelect').value;
            const municipioActual = this.value;
            
            if (estadoActual !== 'all') {
                if (municipioActual !== 'all') {
                    document.getElementById('estadoTitle').textContent = estadoActual + ' - ' + municipioActual;
                } else {
                    document.getElementById('estadoTitle').textContent = estadoActual;
                }
            }
        });
    </script>
</body>
</html>