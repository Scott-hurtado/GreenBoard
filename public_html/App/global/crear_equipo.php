<?php
// 1. Carga del sistema y seguridad
require_once __DIR__ . '/../../includes/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$usuario_id = $_SESSION['user_id'] ?? 0;
$rol_sesion = strtolower($_SESSION['user_role'] ?? '');

// --- 2. CONFIGURACIÓN DE JERARQUÍAS ---
$jerarquia_roles = [
    'admin'                         => 1,
    'administrador'                 => 1,
    'superadmin'                    => 1,
    'super admin'                   => 1,
    'coordinadores_circunscripcion' => 2,
    'coordinador_circunscripcion'   => 2, 
    'circunscripcion'               => 2,
    'coordinador_estatal'           => 3,
    'coordinador_regional'          => 4,
    'coordinador_zona'              => 5
];

$mi_nivel = $jerarquia_roles[$rol_sesion] ?? 6;
$es_admin = ($mi_nivel === 1);
$es_cz    = ($rol_sesion === 'coordinador_zona');

if ($mi_nivel > 5) {
    header('Location: /public/index.php'); 
    exit();
}

$db = Database::getInstance()->getConnection();

// --- 3. MANEJO AJAX PARA REGIONES Y ZONAS (Nuevas tablas) ---
if (isset($_GET['ajax_geo'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_GET['action'] ?? '';
    
    try {
        if ($action === 'get_regiones') {
            $estado_req = $_GET['estado'] ?? '';

            if ($rol_sesion === 'coordinador_regional') {
                // Un Regional solo puede ver/asignar dentro de su propia región
                $stmtMiRegion = $db->prepare("SELECT region FROM coordinador_regional WHERE id = ?");
                $stmtMiRegion->execute([$usuario_id]);
                $mi_region_id = (int)($stmtMiRegion->fetchColumn() ?: 0);

                if ($mi_region_id > 0) {
                    $stmt = $db->prepare("SELECT id, nombre, coordinador_id FROM regiones_estatales WHERE estado = ? AND id = ? ORDER BY nombre ASC");
                    $stmt->execute([$estado_req, $mi_region_id]);
                } else {
                    echo json_encode(['success' => true, 'regiones' => []]);
                    exit;
                }
            } elseif ($rol_sesion === 'coordinador_zona') {
                // Un Coordinador de Zona solo ve la región a la que pertenece su zona
                $stmtMiZona = $db->prepare("
                    SELECT z.estructura_id
                    FROM coordinador_zona cz
                    INNER JOIN zonas_estatales z ON cz.zona = z.id
                    WHERE cz.id = ? LIMIT 1
                ");
                $stmtMiZona->execute([$usuario_id]);
                $mi_region_id = (int)($stmtMiZona->fetchColumn() ?: 0);

                if ($mi_region_id > 0) {
                    $stmt = $db->prepare("SELECT id, nombre, coordinador_id FROM regiones_estatales WHERE estado = ? AND id = ? ORDER BY nombre ASC");
                    $stmt->execute([$estado_req, $mi_region_id]);
                } else {
                    echo json_encode(['success' => true, 'regiones' => []]);
                    exit;
                }
            } else {
                $stmt = $db->prepare("SELECT id, nombre, coordinador_id FROM regiones_estatales WHERE estado = ? ORDER BY nombre ASC");
                $stmt->execute([$estado_req]);
            }

            $regiones = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach($regiones as &$r) {
                $r['is_ocupada'] = !empty($r['coordinador_id']);
            }
            echo json_encode(['success' => true, 'regiones' => $regiones]);
            exit;
        }
        
        if ($action === 'get_zonas') {
            $region_id = (int)($_GET['region_id'] ?? 0);
            $stmt = $db->prepare("SELECT id, nombre, clave, coordinador_id FROM zonas_estatales WHERE estructura_id = ? ORDER BY nombre ASC");
            $stmt->execute([$region_id]);
            $zonas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach($zonas as &$z) {
                $z['is_ocupada'] = !empty($z['coordinador_id']);
            }
            echo json_encode(['success' => true, 'zonas' => $zonas]);
            exit;
        }

        // Devuelve las secciones de la zona del Coordinador de Zona en sesión
        // (para asignarlas a operadores, movilizadores y ciudadanos).
        if ($action === 'get_secciones_zona') {
            if ($rol_sesion !== 'coordinador_zona') {
                echo json_encode(['success' => false, 'error' => 'Solo disponible para Coordinador de Zona.']);
                exit;
            }

            $stmtZ = $db->prepare("
                SELECT z.id, z.nombre, z.clave, z.estructura_id, z.secciones, TRIM(cz.estado) AS estado
                FROM coordinador_zona cz
                INNER JOIN zonas_estatales z ON cz.zona = z.id
                WHERE cz.id = ? LIMIT 1
            ");
            $stmtZ->execute([$usuario_id]);
            $zonaInfo = $stmtZ->fetch(PDO::FETCH_ASSOC);

            if (!$zonaInfo) {
                echo json_encode(['success' => false, 'error' => 'No tienes una zona asignada.']);
                exit;
            }

            $seccionesIds = [];
            if (!empty($zonaInfo['secciones'])) {
                $arr = json_decode($zonaInfo['secciones'], true);
                if (is_array($arr)) {
                    usort($arr, fn($a, $b) => (int)$a - (int)$b);
                    $seccionesIds = array_map('strval', $arr);
                }
            }

            // Datos complementarios (municipio y lista nominal)
            $datosSec = [];
            if (!empty($seccionesIds) && !empty($zonaInfo['estado'])) {
                $ph = implode(',', array_fill(0, count($seccionesIds), '?'));
                $stmtS = $db->prepare("
                    SELECT nombre_seccion, municipio,
                           COALESCE(lista_nominal, padron_electoral, 0) AS lista_nominal
                    FROM secciones_electorales
                    WHERE estado = ? AND nombre_seccion IN ($ph) AND activo = 1
                ");
                $stmtS->execute(array_merge([$zonaInfo['estado']], $seccionesIds));
                foreach ($stmtS->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $datosSec[(string)$row['nombre_seccion']] = $row;
                }
            }

            $secciones = [];
            foreach ($seccionesIds as $num) {
                $d = $datosSec[$num] ?? null;
                $secciones[] = [
                    'seccion'   => $num,
                    'municipio' => ($d['municipio'] ?? '') ?: 'Sin Municipio',
                    'ln'        => (int)($d['lista_nominal'] ?? 0),
                ];
            }

            echo json_encode([
                'success'   => true,
                'zona'      => [
                    'id'        => (int)$zonaInfo['id'],
                    'nombre'    => $zonaInfo['nombre'],
                    'clave'     => $zonaInfo['clave'],
                    'region_id' => (int)$zonaInfo['estructura_id'],
                ],
                'secciones' => $secciones
            ]);
            exit;
        }

        // Devuelve los titulares (circunscripción/estatal/regional) disponibles
        // para asignarles un usuario espejo: dentro del alcance de quien
        // consulta, que no sean ya un espejo, y que aún no tengan uno asignado.
        //
        // NOTA: este bloque corre ANTES de que se calculen $mis_estados y
        // $mapa_circunscripciones (sección 4/5 más abajo en el archivo), así
        // que resuelve su propio alcance de forma autónoma.
        if ($action === 'get_titulares_espejo') {
            $tipoEspejo = $_GET['tipo'] ?? '';
            $mapaTablaEspejo = [
                'circunscripcion' => 'coordinadores_circunscripcion',
                'estatal'         => 'coordinador_estatal',
                'regional'        => 'coordinador_regional'
            ];
            $tablaEspejo = $mapaTablaEspejo[$tipoEspejo] ?? '';

            if (!$tablaEspejo) {
                echo json_encode(['success' => false, 'error' => 'Tipo inválido para usuario espejo.']);
                exit;
            }

            // Alcance propio, resuelto en el momento (independiente de la
            // sección 4/5 que corre después de este bloque).
            $mapaCircAjax = [
                'Circunscripción 1' => ['Baja California', 'Baja California Sur', 'Chihuahua', 'Durango', 'Jalisco', 'Nayarit', 'Sinaloa', 'Sonora'],
                'Circunscripción 2' => ['Aguascalientes', 'Coahuila', 'Guanajuato', 'Nuevo León', 'San Luis Potosí', 'Tamaulipas', 'Zacatecas'],
                'Circunscripción 3' => ['Campeche', 'Chiapas', 'Oaxaca', 'Quintana Roo', 'Tabasco', 'Veracruz', 'Yucatán'],
                'Circunscripción 4' => ['Ciudad de México', 'Guerrero', 'Hidalgo', 'Morelos', 'Puebla', 'Tlaxcala'],
                'Circunscripción 5' => ['Colima', 'Estado de México', 'Michoacán', 'Querétaro']
            ];
            $misEstadosAjax = [];
            if (!$es_admin) {
                if ($mi_nivel === 2) {
                    $stmtCircAjax = $db->prepare("SELECT circunscripcion FROM coordinadores_circunscripcion WHERE id = ?");
                    $stmtCircAjax->execute([$usuario_id]);
                    $miCircAjax = trim($stmtCircAjax->fetchColumn());
                    $misEstadosAjax = $mapaCircAjax[$miCircAjax] ?? [];
                } else {
                    $stmtEstAjax = $db->prepare("SELECT TRIM(estado) FROM `$rol_sesion` WHERE id = ?");
                    $stmtEstAjax->execute([$usuario_id]);
                    $estadoIndividualAjax = $stmtEstAjax->fetchColumn();
                    if ($estadoIndividualAjax) $misEstadosAjax = [$estadoIndividualAjax];
                }
            }

            $params = [];
            if ($tablaEspejo === 'coordinadores_circunscripcion') {
                $sql = "SELECT id, nombre_completo as nombre, circunscripcion as etiqueta 
                        FROM coordinadores_circunscripcion WHERE es_espejo = 0";
            } else {
                $sql = "SELECT id, nombre, estado as etiqueta FROM `$tablaEspejo` WHERE es_espejo = 0";
                if (!$es_admin && !empty($misEstadosAjax)) {
                    $inTit = implode(',', array_fill(0, count($misEstadosAjax), '?'));
                    $sql .= " AND estado IN ($inTit)";
                    $params = array_values($misEstadosAjax);
                }
                // Un regional solo puede reflejar dentro de su propia región
                if ($tablaEspejo === 'coordinador_regional' && $rol_sesion === 'coordinador_regional') {
                    $stmtMiRegionEsp = $db->prepare("SELECT region FROM coordinador_regional WHERE id = ?");
                    $stmtMiRegionEsp->execute([$usuario_id]);
                    $mi_region_id_esp = (int)($stmtMiRegionEsp->fetchColumn() ?: 0);
                    $sql .= " AND region = " . ($mi_region_id_esp > 0 ? (int)$mi_region_id_esp : "0");
                }
            }

            // Solo titulares que todavía no tienen un espejo asignado
            $sql .= " AND id NOT IN (SELECT espejo_de FROM `$tablaEspejo` WHERE espejo_de IS NOT NULL)";
            $sql .= " ORDER BY nombre ASC";

            try {
                $stmtTit = $db->prepare($sql);
                $stmtTit->execute($params);
                $titulares = $stmtTit->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'titulares' => $titulares]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        }

        // Sincroniza al coordinador recién creado con la tabla de estructuras
        if ($action === 'link_coordinador') {
            $tipo = $_POST['tipo'] ?? '';
            $estruc_id = (int)($_POST['estructura_id'] ?? 0);
            $coord_id = (int)($_POST['coordinador_id'] ?? 0);

            // Un Coordinador Regional solo puede ligar zonas de su propia región,
            // y nunca puede ligar/reasignar una región (eso es de un rol superior).
            if ($rol_sesion === 'coordinador_regional') {
                if ($tipo !== 'zona') {
                    echo json_encode(['success' => false, 'error' => 'No tienes permiso para asignar esta estructura.']);
                    exit;
                }
                $stmtMiRegion = $db->prepare("SELECT region FROM coordinador_regional WHERE id = ?");
                $stmtMiRegion->execute([$usuario_id]);
                $mi_region_id = (int)($stmtMiRegion->fetchColumn() ?: 0);

                $stmtZonaCheck = $db->prepare("SELECT estructura_id FROM zonas_estatales WHERE id = ?");
                $stmtZonaCheck->execute([$estruc_id]);
                $zona_region_id = (int)($stmtZonaCheck->fetchColumn() ?: 0);

                if ($mi_region_id <= 0 || $zona_region_id !== $mi_region_id) {
                    echo json_encode(['success' => false, 'error' => 'Esa zona no pertenece a tu región.']);
                    exit;
                }
            }

            if ($estruc_id > 0 && $coord_id > 0) {
                if ($tipo === 'regional') {
                    $db->prepare("UPDATE regiones_estatales SET coordinador_id = ? WHERE id = ?")->execute([$coord_id, $estruc_id]);
                } elseif ($tipo === 'zona') {
                    $db->prepare("UPDATE zonas_estatales SET coordinador_id = ? WHERE id = ?")->execute([$coord_id, $estruc_id]);
                }
            }
            echo json_encode(['success' => true]);
            exit;
        }
    } catch(Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// --- 4. DICCIONARIO DE CIRCUNSCRIPCIONES ---
$mapa_circunscripciones = [
    'Circunscripción 1' => ['Baja California', 'Baja California Sur', 'Chihuahua', 'Durango', 'Jalisco', 'Nayarit', 'Sinaloa', 'Sonora'],
    'Circunscripción 2' => ['Aguascalientes', 'Coahuila', 'Guanajuato', 'Nuevo León', 'San Luis Potosí', 'Tamaulipas', 'Zacatecas'],
    'Circunscripción 3' => ['Campeche', 'Chiapas', 'Oaxaca', 'Quintana Roo', 'Tabasco', 'Veracruz', 'Yucatán'],
    'Circunscripción 4' => ['Ciudad de México', 'Guerrero', 'Hidalgo', 'Morelos', 'Puebla', 'Tlaxcala'],
    'Circunscripción 5' => ['Colima', 'Estado de México', 'Michoacán', 'Querétaro']
];

// --- 5. OBTENER LOS ESTADOS PERMITIDOS PARA EL USUARIO ---
$mis_estados = []; 
$titulo_vista = 'Global'; 

if (!$es_admin) {
    try {
        if ($mi_nivel === 2) {
            $stmtE = $db->prepare("SELECT circunscripcion FROM coordinadores_circunscripcion WHERE id = ?");
            $stmtE->execute([$usuario_id]);
            $mi_circ = trim($stmtE->fetchColumn());
            
            $mis_estados = $mapa_circunscripciones[$mi_circ] ?? [];
            $titulo_vista = $mi_circ;
        } else {
            $stmtE = $db->prepare("SELECT TRIM(estado) FROM `$rol_sesion` WHERE id = ?");
            $stmtE->execute([$usuario_id]);
            $estado_individual = $stmtE->fetchColumn();
            
            if ($estado_individual) {
                $mis_estados = [$estado_individual];
                $titulo_vista = $estado_individual;
            }
        }
    } catch (PDOException $e) {
        error_log("Error obteniendo ubicación: " . $e->getMessage());
    }
    
    if (empty($mis_estados)) {
        die("Error: No tienes un área de cobertura asignada en el sistema.");
    }
}

$estados_disponibles = [];
if ($es_admin) {
    foreach ($mapa_circunscripciones as $estados) {
        $estados_disponibles = array_merge($estados_disponibles, $estados);
    }
} else {
    $estados_disponibles = $mis_estados;
}
sort($estados_disponibles);

// --- 6. AGRUPAR ESTADOS DISPONIBLES POR CIRCUNSCRIPCIÓN (Para los Tabs) ---
$agrupacion_estados = [];
foreach ($mapa_circunscripciones as $circ => $estados_circ) {
    $interseccion = array_intersect($estados_circ, $estados_disponibles);
    if (!empty($interseccion)) {
        $agrupacion_estados[$circ] = array_values($interseccion);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Crear Usuario - Proyecto Verde</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
  :root {
    --primary: #2d7a3e;
    --primary-dark: #1f5a2d;
    --primary-light: #4caf50;
    --accent: #e8a020;
    --dark: #1a1a1a;
    --light: #f0f7f0;
    --white: #ffffff;
    --danger: #ef4444;
    --text-muted: #888;
    --border: #f0f0f0;
    --shadow-sm: 0 10px 30px rgba(0,0,0,0.05);
    --shadow-hover: 0 20px 40px rgba(45,122,62,0.15);
    --radius: 20px;
  }

  * { margin: 0; padding: 0; box-sizing: border-box; }

  body {
    font-family: 'Arial', sans-serif;
    background: var(--light);
    color: var(--dark);
    overflow-x: hidden;
  }

  .container {
    max-width: 100%;
    margin: 0;
    background: var(--white);
    min-height: 100vh;
    padding-left: 330px;
    padding-right: 40px;
    padding-bottom: 60px;
    transition: padding-left 0.3s ease;
  }

  .design-text {
    position: fixed;
    left: 290px; top: 50%;
    writing-mode: vertical-rl;
    font-size: 13px; font-weight: 500;
    color: var(--primary); opacity: 0.3;
    letter-spacing: 2px; z-index: 0; pointer-events: none;
  }

  .page-header {
    padding: 50px 0 36px 0;
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    position: relative; z-index: 2;
  }
  .ai-badge {
    color: var(--primary); font-size: 12px; font-weight: 800;
    letter-spacing: 4px; margin-bottom: 14px; display: inline-block;
    text-transform: uppercase; background: rgba(45,122,62,0.1);
    padding: 5px 15px; border-radius: 20px;
  }
  .main-title { font-size: 48px; font-weight: 900; line-height: 1; margin-bottom: 10px; color: var(--dark); }
  .description { color: #666; font-size: 15px; }

  /* ── ESTILOS PARA TABS CIRCUNSCRIPCIONES ── */
  .tabs-bar { display:flex; gap:4px; flex-wrap:wrap; border-bottom:2px solid var(--gray-200); margin-bottom:15px; padding-bottom:0; }
  .tab-btn { padding:10px 20px; border:none; background:transparent; font-family:Arial,sans-serif; font-size:14px; font-weight:600; color:var(--text-muted); cursor:pointer; border-bottom:3px solid transparent; margin-bottom:-2px; transition:0.2s; border-radius:8px 8px 0 0; }
  .tab-btn:hover { color:var(--dark); background:var(--gray-100); }
  .tab-btn.active { color:var(--primary); border-bottom-color:var(--primary); background:var(--light); }

  .tipo-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 36px;
  }
  .tipo-card {
    background: var(--white);
    border: 2px solid var(--border);
    border-radius: var(--radius);
    padding: 24px 20px;
    cursor: pointer;
    transition: all 0.25s ease;
    box-shadow: var(--shadow-sm);
    display: flex;
    align-items: center;
    gap: 15px;
    position: relative;
    overflow: hidden;
  }
  .tipo-card:hover {
    border-color: var(--primary);
    transform: translateY(-3px);
    box-shadow: var(--shadow-hover);
  }
  .tipo-card.selected {
    border-color: var(--primary);
    background: rgba(45,122,62,0.04);
    box-shadow: 0 0 0 4px rgba(45,122,62,0.1), var(--shadow-hover);
  }
  .tipo-icon {
    width: 50px; height: 50px;
    background: #f4fcf6;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 22px;
    color: var(--primary);
    flex-shrink: 0;
    transition: 0.25s;
  }
  .tipo-card.selected .tipo-icon { background: var(--primary); color: white; }
  .tipo-info { flex: 1; }
  .tipo-name { font-size: 15px; font-weight: 800; color: var(--dark); margin-bottom: 4px; }
  .tipo-desc { font-size: 12px; color: var(--text-muted); line-height: 1.3; }
  .badge-no-sys { 
    display: inline-block; margin-top: 5px; font-size: 10px; 
    background: #fee2e2; color: #b91c1c; padding: 2px 6px; border-radius: 4px; font-weight: bold;
  }

  .form-card {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    box-shadow: var(--shadow-sm);
    overflow: hidden;
    margin-bottom: 24px;
    transition: all 0.3s;
  }
  .form-card-header {
    padding: 22px 30px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 12px;
  }
  .form-card-icon {
    width: 36px; height: 36px;
    background: #f4fcf6;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    color: var(--primary); font-size: 16px;
  }
  .form-card-title { font-size: 16px; font-weight: 800; color: var(--dark); }
  .form-card-body { padding: 30px; }

  .form-grid { display: grid; gap: 20px; }
  .form-grid-3 { grid-template-columns: 1fr 1fr 1fr; }
  .form-grid-2 { grid-template-columns: 1fr 1fr; }

  .form-group { display: flex; flex-direction: column; gap: 7px; }
  
  .form-label {
    font-size: 12px; font-weight: 700;
    color: #555; text-transform: uppercase; letter-spacing: 0.5px;
  }
  .form-label .required { color: var(--danger); margin-left: 2px; }

  .form-input, .form-select {
    padding: 13px 16px; border: 2px solid #eee; border-radius: 12px;
    font-size: 14px; font-weight: 500; color: var(--dark);
    background: var(--white); outline: none; transition: all 0.2s;
    width: 100%; font-family: 'Arial', sans-serif;
    appearance: none; -webkit-appearance: none;
  }
  .form-input:focus, .form-select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(45,122,62,0.1);
  }
  .form-input.error { border-color: var(--danger); }

  .select-wrapper { position: relative; }
  .select-wrapper::after {
    content: '\f107'; font-family: 'Font Awesome 6 Free'; font-weight: 900;
    position: absolute; right: 16px; top: 50%; transform: translateY(-50%);
    color: var(--text-muted); pointer-events: none;
  }
  .input-wrapper { position: relative; }
  .input-wrapper .form-input { padding-right: 46px; }
  .pwd-toggle {
    position: absolute; right: 14px; top: 50%; transform: translateY(-50%);
    background: none; border: none; cursor: pointer; color: var(--text-muted);
    font-size: 15px; transition: color 0.2s;
  }
  .pwd-toggle:hover { color: var(--primary); }

  .estado-chips { display: flex; flex-wrap: wrap; gap: 8px; }
  .estado-chip {
    padding: 7px 16px; border-radius: 20px; border: 2px solid #eee;
    font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.15s;
    color: #555; background: var(--white); user-select: none;
  }
  .estado-chip:hover { border-color: var(--primary); color: var(--primary); }
  .estado-chip.selected { background: var(--primary); border-color: var(--primary); color: white; cursor: default;}

  /* ── SELECTOR MÚLTIPLE DE SECCIONES (Coord. de Zona) ── */
  .secciones-multi { margin-top: 24px; flex-direction: column; gap: 10px; }
  .secciones-toolbar { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
  .secciones-toolbar .form-input { flex: 1; min-width: 200px; padding: 10px 14px; }
  .btn-mini {
    padding: 9px 14px; border-radius: 10px; border: 2px solid #eee; background: var(--white);
    font-size: 12px; font-weight: 700; color: #555; cursor: pointer; transition: 0.15s;
    font-family: Arial, sans-serif; display: flex; align-items: center; gap: 6px;
  }
  .btn-mini:hover { border-color: var(--primary); color: var(--primary); }
  .secciones-contador {
    font-size: 12px; font-weight: 800; color: var(--primary);
    background: rgba(45,122,62,0.1); padding: 6px 12px; border-radius: 20px; white-space: nowrap;
  }
  .secciones-lista {
    border: 2px solid #eee; border-radius: 12px; padding: 14px; max-height: 320px; overflow-y: auto;
  }
  .secciones-lista.error { border-color: var(--danger); }
  .secciones-lista::-webkit-scrollbar { width: 6px; }
  .secciones-lista::-webkit-scrollbar-thumb { background: #ccc; border-radius: 6px; }
  .muni-grupo + .muni-grupo { margin-top: 14px; }
  .muni-grupo-titulo {
    font-size: 11px; font-weight: 800; color: var(--text-muted); text-transform: uppercase;
    letter-spacing: 0.5px; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;
  }
  .seccion-chip { padding: 6px 12px; display: inline-flex; align-items: center; gap: 6px; }
  .seccion-chip.selected { cursor: pointer; }
  .seccion-chip-ln { font-size: 10px; font-weight: 700; opacity: 0.7; }
  .secciones-vacio { text-align: center; padding: 24px; color: var(--text-muted); font-size: 13px; }

  .section-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 20px; margin-top: 36px;
  }
  .section-title { font-size: 20px; font-weight: 800; color: var(--dark); display: flex; align-items: center; gap: 10px; }
  .section-title-bar { display: block; width: 4px; height: 22px; background: var(--accent); border-radius: 2px; }
  .form-actions {
    display: flex; justify-content: flex-end; gap: 12px;
    padding-top: 24px; border-top: 1px solid var(--border); margin-top: 10px;
  }
  .btn-action {
    background: var(--dark); color: white; padding: 13px 28px; border-radius: 12px;
    border: none; font-weight: 700; cursor: pointer; transition: all 0.3s;
    display: flex; align-items: center; gap: 8px; font-size: 14px;
  }
  .btn-action.btn-primary { background: var(--primary); }
  .btn-action.btn-outline { background: white; color: var(--dark); border: 2px solid #eee; }
  
  .alert {
    padding: 14px 18px; border-radius: 12px; font-size: 14px; font-weight: 600;
    display: flex; align-items: center; gap: 10px; margin-bottom: 20px; display: none;
  }
  .alert.show { display: flex; }
  .alert-success { background: #dcfce7; color: #15803d; border: 1px solid #a7f3d0; }
  .alert-error { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }

  .field-conditional { display: none; }
  .field-conditional.visible { display: flex; } 
  .form-hint { font-size: 11px; color: var(--text-muted); margin-top: 3px; }
  
  .tipo-card::after {
    content: '\f00c'; 
    font-family: 'Font Awesome 6 Free';
    font-weight: 900;
    position: absolute;
    top: 15px;
    right: 15px;
    width: 24px;
    height: 24px;
    background-color: var(--primary);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    opacity: 0;
    transform: scale(0);
    transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
  }

  .tipo-card.selected::after { opacity: 1; transform: scale(1); }

  @media (max-width: 1024px) {
    .container { padding-left: 20px; padding-right: 20px; }
  }
  @media (max-width: 768px) {
    .form-grid-3, .form-grid-2 { grid-template-columns: 1fr; }
  }
</style>
</head>
<body>

<?php 
$path_sidebar = __DIR__ . '/../../includes/components/';
if ($mi_nivel === 1) {
    include $path_sidebar . 'sidebar_admin.php'; 
} elseif ($mi_nivel === 2) {
    include $path_sidebar . 'sidebar_circunscripcion.php'; 
} elseif ($mi_nivel === 3) {
    include $path_sidebar . 'sidebar_ce.php'; 
} elseif ($mi_nivel === 4) {
    include $path_sidebar . 'sidebar_regional.php'; 
} elseif ($mi_nivel === 5) {
    include $path_sidebar . 'sidebar_cz.php'; 
} 
?>

<div class="design-text">USUARIOS</div>

<div class="container">

  <div class="page-header">
    <div class="header-content">
      <span class="ai-badge">CREACIÓN - <?php echo htmlspecialchars(strtoupper($titulo_vista)); ?></span>
      <h1 class="main-title">Crear Usuario</h1>
      <p class="description">Registra un nuevo perfil de tu equipo operativo en el sistema.</p>
    </div>
    <div style="display:flex;gap:12px">
      <a href="directorio_equipo.php" class="btn-action btn-outline">
        <i class="fas fa-users"></i> Ver Usuarios
      </a>
    </div>
  </div>

  <div class="alert alert-success" id="alert-success">
    <i class="fas fa-check-circle"></i> <span id="alert-msg">Usuario creado exitosamente.</span>
  </div>
  <div class="alert alert-error" id="alert-error">
    <i class="fas fa-exclamation-circle"></i> <span id="alert-err-msg">Ocurrió un error.</span>
  </div>

  <div class="section-header">
    <h3 class="section-title"><span class="section-title-bar"></span>Tipo de Perfil</h3>
  </div>

  <div class="tipo-grid">
    <?php if ($es_admin): ?>
    <div class="tipo-card" id="tipo-espejo_circunscripcion" onclick="selectTipo('espejo_circunscripcion')">
      <div class="tipo-icon"><i class="fas fa-people-arrows"></i></div>
      <div class="tipo-info">
        <div class="tipo-name">Espejo de Circunscripción</div>
        <div class="tipo-desc">Mismo alcance que un titular de circunscripción ya existente.</div>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($mi_nivel < 3): ?>
    <div class="tipo-card" id="tipo-espejo_estatal" onclick="selectTipo('espejo_estatal')">
      <div class="tipo-icon"><i class="fas fa-people-arrows"></i></div>
      <div class="tipo-info">
        <div class="tipo-name">Espejo Estatal</div>
        <div class="tipo-desc">Mismo alcance que un titular estatal ya existente.</div>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($mi_nivel < 4): ?>
    <div class="tipo-card" id="tipo-espejo_regional" onclick="selectTipo('espejo_regional')">
      <div class="tipo-icon"><i class="fas fa-people-arrows"></i></div>
      <div class="tipo-info">
        <div class="tipo-name">Espejo Regional</div>
        <div class="tipo-desc">Mismo alcance que un titular regional ya existente.</div>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($mi_nivel < 4): ?>
    <div class="tipo-card" id="tipo-regional" onclick="selectTipo('regional')">
      <div class="tipo-icon"><i class="fas fa-location-arrow"></i></div>
      <div class="tipo-info">
        <div class="tipo-name">Coord. Regional</div>
        <div class="tipo-desc">Acceso a su región asignada.</div>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($mi_nivel < 5): ?>
    <div class="tipo-card" id="tipo-zona" onclick="selectTipo('zona')">
      <div class="tipo-icon"><i class="fas fa-map-pin"></i></div>
      <div class="tipo-info">
        <div class="tipo-name">Coord. de Zona</div>
        <div class="tipo-desc">Acceso a su zona asignada.</div>
      </div>
    </div>
    <?php endif; ?>

    <div class="tipo-card" id="tipo-operador" onclick="selectTipo('operador')">
      <div class="tipo-icon"><i class="fas fa-hard-hat"></i></div>
      <div class="tipo-info">
        <div class="tipo-name">Operador / Brigadista</div>
        <span class="badge-no-sys">Sin acceso al sistema</span>
      </div>
    </div>
    <div class="tipo-card" id="tipo-movilizador" onclick="selectTipo('movilizador')">
      <div class="tipo-icon"><i class="fas fa-bullhorn"></i></div>
      <div class="tipo-info">
        <div class="tipo-name">Movilizador / Enlace</div>
        <span class="badge-no-sys">Sin acceso al sistema</span>
      </div>
    </div>
    <div class="tipo-card" id="tipo-ciudadano" onclick="selectTipo('ciudadano')">
      <div class="tipo-icon"><i class="fas fa-user-friends"></i></div>
      <div class="tipo-info">
        <div class="tipo-name">Ciudadano Afín</div>
        <span class="badge-no-sys">Sin acceso al sistema</span>
      </div>
    </div>
  </div>

  <div class="section-header">
    <h3 class="section-title"><span class="section-title-bar"></span>Información Personal</h3>
  </div>

  <div class="form-card">
    <div class="form-card-header">
      <div class="form-card-icon"><i class="fas fa-user"></i></div>
      <div class="form-card-title">Datos principales</div>
    </div>
    <div class="form-card-body">
      <div class="form-grid form-grid-3">
        <div class="form-group">
          <label class="form-label">Nombre(s) <span class="required">*</span></label>
          <input type="text" class="form-input" id="nombre" placeholder="Ej: Carlos Alberto">
        </div>
        <div class="form-group">
          <label class="form-label">Apellido Paterno <span class="required">*</span></label>
          <input type="text" class="form-input" id="apellido_p" placeholder="Ej: López">
        </div>
        <div class="form-group">
          <label class="form-label">Apellido Materno</label>
          <input type="text" class="form-input" id="apellido_m" placeholder="Ej: Torres">
        </div>
        <div class="form-group">
          <label class="form-label">Teléfono <span class="required">*</span></label>
          <input type="tel" class="form-input" id="telefono" placeholder="Ej: 6441234567" maxlength="10">
        </div>
        <div class="form-group">
          <label class="form-label">Clave de Elector (INE)</label>
          <input type="text" class="form-input" id="ine" placeholder="18 caracteres" maxlength="18" style="text-transform:uppercase">
        </div>
        <div class="form-group">
          <label class="form-label">CURP</label>
          <input type="text" class="form-input" id="curp" placeholder="XXXX000000XXXXXX00" maxlength="18" style="text-transform:uppercase">
        </div>
      </div>
    </div>
  </div>

  <div id="seccion-acceso">
    <div class="section-header">
      <h3 class="section-title"><span class="section-title-bar"></span>Acceso al Sistema</h3>
    </div>
    <div class="form-card">
      <div class="form-card-header">
        <div class="form-card-icon"><i class="fas fa-lock"></i></div>
        <div class="form-card-title">Credenciales de acceso</div>
      </div>
      <div class="form-card-body">
        <div class="form-grid form-grid-3">
          <div class="form-group" style="grid-column: 1 / -1;">
            <label class="form-label">Correo Electrónico (Usuario) <span class="required">*</span></label>
            <input type="email" class="form-input" id="correo" placeholder="ejemplo@correo.com" value="@greenboard.mx">
            <span class="form-hint">Este será el identificador principal para iniciar sesión.</span>
          </div>
          <div class="form-group">
            <label class="form-label">Contraseña <span class="required">*</span></label>
            <div class="input-wrapper">
              <input type="password" class="form-input" id="password" placeholder="Mínimo 8 caracteres">
              <button type="button" class="pwd-toggle" onclick="togglePwd('password', this)"><i class="fas fa-eye"></i></button>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Confirmar Contraseña <span class="required">*</span></label>
            <div class="input-wrapper">
              <input type="password" class="form-input" id="password2" placeholder="Repetir contraseña">
              <button type="button" class="pwd-toggle" onclick="togglePwd('password2', this)"><i class="fas fa-eye"></i></button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="form-group field-conditional" id="field-titular-espejo" style="margin-bottom:24px">
    <div class="section-header">
      <h3 class="section-title"><span class="section-title-bar"></span>Titular a Reflejar</h3>
    </div>
    <label class="form-label">Selecciona el titular existente <span class="required">*</span></label>
    <div class="select-wrapper">
      <select class="form-select" id="titularEspejo">
        <option value="">— Selecciona primero un titular —</option>
      </select>
    </div>
    <span class="form-hint">El usuario espejo hereda automáticamente el mismo estado/región/circunscripción que este titular. Solo se permite un espejo por titular.</span>
  </div>

  <div id="seccion-territorial">
  <div class="section-header">
    <h3 class="section-title"><span class="section-title-bar"></span>Asignación Territorial y Domicilio</h3>
  </div>

  <div class="form-card">
    <div class="form-card-header">
      <div class="form-card-icon"><i class="fas fa-map"></i></div>
      <div class="form-card-title">Ubicación del perfil</div>
    </div>
    <div class="form-card-body">

      <div class="form-group" style="margin-bottom:24px">
        <label class="form-label">Domicilio Completo (Calle, Número, Colonia)</label>
        <input type="text" class="form-input" id="domicilio" placeholder="Ej: Av. Reforma #123, Col. Centro">
      </div>

      <div class="form-group" style="margin-bottom:24px">
        <label class="form-label">Estado Asignado <span class="required">*</span></label>
        
        <?php if (count($agrupacion_estados) > 1): ?>
        <div class="tabs-bar">
            <?php $first = true; foreach($agrupacion_estados as $circ => $ests): ?>
                <button class="tab-btn <?= $first ? 'active' : '' ?>" onclick="mostrarCircunscripcion('<?= md5($circ) ?>', this)" type="button">
                    <?= htmlspecialchars($circ) ?>
                </button>
            <?php $first = false; endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="estado-chips" id="chips-estados">
          <?php
          $first_circ = array_key_first($agrupacion_estados);
          foreach($agrupacion_estados as $circ => $ests):
              // Mostrar solo el primer grupo de inicio (o todos si solo hay 1)
              $display = ($circ === $first_circ || count($agrupacion_estados) === 1) ? 'flex' : 'none';
              echo "<div class='circ-group' id='grupo-".md5($circ)."' style='display:$display; gap:8px; flex-wrap:wrap;'>";
              foreach($ests as $e):
                  echo "<div class='estado-chip' onclick='selectOnlyOne(this, \"".htmlspecialchars($e)."\")'>" . htmlspecialchars($e) . "</div>";
              endforeach;
              echo "</div>";
          endforeach;
          ?>
        </div>
        <span class="form-hint">Solo puedes registrar perfiles en los estados habilitados para tu nivel.</span>
      </div>

      <div class="form-grid form-grid-3">
          <div class="form-group field-conditional" id="field-region">
            <label class="form-label">Región Asignada <span class="required">*</span></label>
            <div class="select-wrapper">
              <select class="form-select" id="region">
                <option value="">— Selecciona primero un estado —</option>
              </select>
            </div>
          </div>

          <div class="form-group field-conditional" id="field-zona">
            <label class="form-label">Zona Asignada <span class="required">*</span></label>
            <div class="select-wrapper">
              <select class="form-select" id="zona">
                <option value="">— Selecciona primero una región —</option>
              </select>
            </div>
          </div>

          <div class="form-group" id="field-seccion-unica">
            <label class="form-label">Sección Electoral</label>
            <input type="text" class="form-input" id="seccion" placeholder="Ej: 1234" maxlength="4" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
          </div>
      </div>

      <!-- Selector múltiple de secciones: solo Coord. de Zona creando Operador / Movilizador -->
      <div class="form-group field-conditional secciones-multi" id="field-secciones-multi">
        <label class="form-label">Secciones Asignadas <span class="required">*</span> <span id="secciones-zona-nombre" style="text-transform:none;font-weight:600;color:var(--text-muted);"></span></label>
        <div class="secciones-toolbar">
          <input type="text" class="form-input" id="buscarSeccion" placeholder="Filtrar sección por número..." oninput="this.value = this.value.replace(/[^0-9]/g, ''); renderSeccionesZona()">
          <button type="button" class="btn-mini" onclick="seleccionarVisibles(true)"><i class="fas fa-check-double"></i> Todas</button>
          <button type="button" class="btn-mini" onclick="seleccionarVisibles(false)"><i class="fas fa-times"></i> Ninguna</button>
          <span class="secciones-contador" id="secciones-contador">0 seleccionadas</span>
        </div>
        <div class="secciones-lista" id="secciones-zona-lista">
          <div class="secciones-vacio"><i class="fas fa-circle-notch fa-spin"></i> Cargando secciones de tu zona...</div>
        </div>
        <span class="form-hint">Puedes asignar una o varias secciones de tu zona a este perfil.</span>
      </div>

    </div>
  </div>
  </div>

  <div class="form-actions">
    <button class="btn-action btn-outline" onclick="resetForm()">
      <i class="fas fa-undo"></i> Limpiar
    </button>
    <button class="btn-action btn-primary" id="btn-guardar" onclick="guardarUsuario()">
      <i class="fas fa-save"></i> Guardar Perfil
    </button>
  </div>

</div>

<script>
// La ruta dinámica a este mismo archivo para consumir el bloque ajax_geo
const ZONAS_CIRC_URL = window.location.pathname + '?ajax_geo=1';
const ES_CZ = <?= $es_cz ? 'true' : 'false' ?>;
const TIPOS_SIN_ACCESO = ['operador', 'movilizador', 'ciudadano'];
// Perfiles a los que el Coord. de Zona puede asignar varias secciones
const TIPOS_CON_SECCIONES = ['operador', 'movilizador'];

let tipoSeleccionado = null;
let requiereAcceso = true;
let regionesDataGlobal = [];
let estadoSeleccionado = '';

// Estado del selector múltiple de secciones (Coord. de Zona)
let zonaCZ = null;
let seccionesZona = [];
let seccionesSeleccionadas = new Set();
let seccionesZonaCargadas = false;

function usaSeccionesMultiples() {
  return ES_CZ && TIPOS_CON_SECCIONES.includes(tipoSeleccionado);
}

// TABS Lógica
function mostrarCircunscripcion(hash, btn) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    
    document.querySelectorAll('.circ-group').forEach(g => g.style.display = 'none');
    document.getElementById('grupo-' + hash).style.display = 'flex';
}

// Auto-seleccionar estado si el usuario solo tiene permiso para 1 
window.addEventListener('load', () => {
    const chips = document.querySelectorAll('#chips-estados .estado-chip');
    if (chips.length === 1) {
        selectOnlyOne(chips[0], chips[0].textContent.trim());
    }
});

function selectTipo(tipo) {
  document.querySelectorAll('.tipo-card').forEach(c => c.classList.remove('selected'));
  const card = document.getElementById('tipo-' + tipo);
  if(card) card.classList.add('selected');
  tipoSeleccionado = tipo;

  const fieldRegion = document.getElementById('field-region');
  const fieldZona = document.getElementById('field-zona');
  const seccionAcceso = document.getElementById('seccion-acceso');
  const fieldTitularEspejo = document.getElementById('field-titular-espejo');
  const seccionTerritorial = document.getElementById('seccion-territorial');
  const fieldSeccionUnica = document.getElementById('field-seccion-unica');
  const fieldSeccionesMulti = document.getElementById('field-secciones-multi');

  fieldRegion.classList.remove('visible');
  fieldZona.classList.remove('visible');
  fieldTitularEspejo.classList.remove('visible');
  fieldSeccionesMulti.classList.remove('visible');
  fieldSeccionUnica.style.display = '';
  seccionTerritorial.style.display = '';
  seccionAcceso.style.display = 'block';
  requiereAcceso = true;

  const esEspejo = tipo.startsWith('espejo_');

  if (esEspejo) {
    // El espejo hereda el alcance del titular: se oculta toda la
    // sección territorial y solo se muestra el selector de titular.
    seccionTerritorial.style.display = 'none';
    fieldTitularEspejo.classList.add('visible');
    estadoSeleccionado = '';
    cargarTitularesEspejo(tipo.replace('espejo_', ''));
  } else if (TIPOS_SIN_ACCESO.includes(tipo)) {
    seccionAcceso.style.display = 'none';
    requiereAcceso = false;
    fieldRegion.classList.add('visible'); 

    if (ES_CZ && TIPOS_CON_SECCIONES.includes(tipo)) {
      fieldSeccionUnica.style.display = 'none';
      fieldSeccionesMulti.classList.add('visible');
      if (!seccionesZonaCargadas) cargarSeccionesZona();
      else renderSeccionesZona();
    }
  } else {
    if (tipo === 'regional') {
      fieldRegion.classList.add('visible');
    } else if (tipo === 'zona') {
      fieldRegion.classList.add('visible');
      fieldZona.classList.add('visible');
    }
  }

  if(regionesDataGlobal.length > 0) {
      renderizarRegiones();
  }
}

/* ══════════════════════════════════════════════
   SECCIONES MÚLTIPLES (Coord. de Zona)
══════════════════════════════════════════════ */
async function cargarSeccionesZona() {
  const lista = document.getElementById('secciones-zona-lista');
  lista.innerHTML = '<div class="secciones-vacio"><i class="fas fa-circle-notch fa-spin"></i> Cargando secciones de tu zona...</div>';
  try {
    const resp = await fetch(ZONAS_CIRC_URL + '&action=get_secciones_zona');
    const data = await resp.json();
    if (!data.success) throw new Error(data.error || 'No se pudieron cargar las secciones');

    zonaCZ = data.zona;
    seccionesZona = data.secciones || [];
    seccionesZonaCargadas = true;

    const lbl = document.getElementById('secciones-zona-nombre');
    if (lbl && zonaCZ) lbl.textContent = `— ${zonaCZ.nombre}${zonaCZ.clave ? ' (' + zonaCZ.clave + ')' : ''}`;

    renderSeccionesZona();
  } catch (e) {
    console.error('Error cargando secciones de zona:', e);
    lista.innerHTML = `<div class="secciones-vacio"><i class="fas fa-exclamation-triangle" style="color:var(--danger)"></i> ${e.message}</div>`;
  }
}

function seccionesFiltradas() {
  const filtro = (document.getElementById('buscarSeccion')?.value || '').trim();
  return filtro ? seccionesZona.filter(s => String(s.seccion).includes(filtro)) : seccionesZona;
}

function renderSeccionesZona() {
  const lista = document.getElementById('secciones-zona-lista');
  if (!seccionesZonaCargadas) return;

  if (seccionesZona.length === 0) {
    lista.innerHTML = '<div class="secciones-vacio"><i class="fas fa-map-pin"></i> Tu zona no tiene secciones configuradas.</div>';
    actualizarContadorSecciones();
    return;
  }

  const visibles = seccionesFiltradas();
  if (visibles.length === 0) {
    lista.innerHTML = '<div class="secciones-vacio">Ninguna sección coincide con el filtro.</div>';
    actualizarContadorSecciones();
    return;
  }

  const grupos = {};
  visibles.forEach(s => { (grupos[s.municipio] ||= []).push(s); });

  lista.innerHTML = Object.keys(grupos).sort().map(mun => `
    <div class="muni-grupo">
      <div class="muni-grupo-titulo"><i class="fas fa-city"></i> ${mun} <span>(${grupos[mun].length})</span></div>
      <div class="estado-chips">
        ${grupos[mun].map(s => `
          <div class="estado-chip seccion-chip ${seccionesSeleccionadas.has(String(s.seccion)) ? 'selected' : ''}"
               onclick="toggleSeccion('${s.seccion}')">
            ${s.seccion}${s.ln > 0 ? `<span class="seccion-chip-ln">LN ${Number(s.ln).toLocaleString('es-MX')}</span>` : ''}
          </div>`).join('')}
      </div>
    </div>`).join('');

  actualizarContadorSecciones();
}

function toggleSeccion(num) {
  num = String(num);
  if (seccionesSeleccionadas.has(num)) seccionesSeleccionadas.delete(num);
  else seccionesSeleccionadas.add(num);
  document.getElementById('secciones-zona-lista').classList.remove('error');
  renderSeccionesZona();
}

function seleccionarVisibles(seleccionar) {
  seccionesFiltradas().forEach(s => {
    if (seleccionar) seccionesSeleccionadas.add(String(s.seccion));
    else seccionesSeleccionadas.delete(String(s.seccion));
  });
  document.getElementById('secciones-zona-lista').classList.remove('error');
  renderSeccionesZona();
}

function actualizarContadorSecciones() {
  const n = seccionesSeleccionadas.size;
  const el = document.getElementById('secciones-contador');
  if (el) el.textContent = `${n} seleccionada${n !== 1 ? 's' : ''}`;
}

function seccionesSeleccionadasOrdenadas() {
  return [...seccionesSeleccionadas].sort((a, b) => parseInt(a) - parseInt(b));
}

// Carga, vía AJAX, los titulares (circunscripción/estatal/regional) que
// todavía no tienen un usuario espejo asignado y están dentro de tu alcance.
async function cargarTitularesEspejo(tipoBase) {
  const sel = document.getElementById('titularEspejo');
  sel.innerHTML = '<option value="">— Cargando titulares... —</option>';
  try {
    const resp = await fetch(ZONAS_CIRC_URL + '&action=get_titulares_espejo&tipo=' + encodeURIComponent(tipoBase));
    const data = await resp.json();
    if (data.success && data.titulares && data.titulares.length > 0) {
      sel.innerHTML = '<option value="">— Selecciona un titular —</option>';
      data.titulares.forEach(t => {
        const opt = document.createElement('option');
        opt.value = t.id;
        opt.textContent = `${t.nombre} (${t.etiqueta})`;
        sel.appendChild(opt);
      });
    } else {
      sel.innerHTML = '<option value="">— No hay titulares disponibles para reflejar —</option>';
    }
  } catch (e) {
    sel.innerHTML = '<option value="">— Error al cargar titulares —</option>';
    console.error('Error cargando titulares espejo:', e);
  }
}

function selectOnlyOne(chip, estado) {
  document.querySelectorAll('#chips-estados .estado-chip').forEach(c => c.classList.remove('selected'));
  chip.classList.add('selected');
  
  estadoSeleccionado = estado.trim();
  actualizarRegiones(estadoSeleccionado);
}

async function actualizarRegiones(estado) {
  const selectRegion = document.getElementById('region');
  const selectZona   = document.getElementById('zona');

  if(!selectRegion) return;

  selectRegion.innerHTML = '<option value="">— Cargando regiones... —</option>';
  selectZona.innerHTML   = '<option value="">— Selecciona primero una región —</option>';

  try {
    const respGet = await fetch(ZONAS_CIRC_URL + '&action=get_regiones&estado=' + encodeURIComponent(estado));
    const data = await respGet.json();
    regionesDataGlobal = data.regiones || [];

    if (regionesDataGlobal.length > 0) {
      renderizarRegiones();
    } else {
      selectRegion.innerHTML = '<option value="">— Sin regiones configuradas en este estado —</option>';
    }
  } catch (e) {
    selectRegion.innerHTML = '<option value="">— Error al cargar regiones —</option>';
    console.error('Error cargando regiones:', e);
  }
}

function renderizarRegiones() {
  const selectRegion = document.getElementById('region');
  const selectZona   = document.getElementById('zona');
  
  const currentVal = selectRegion.value; 
  
  selectRegion.innerHTML = '<option value="">— Selecciona una región —</option>';
  selectZona.innerHTML   = '<option value="">— Selecciona primero una región —</option>';

  regionesDataGlobal.forEach(reg => {
    const opt = document.createElement('option');
    opt.value = reg.id; 
    opt.textContent = reg.nombre + (reg.is_ocupada ? ' (con responsable)' : '');
    selectRegion.appendChild(opt);
  });

  if(selectRegion.options.length === 1) {
      selectRegion.innerHTML = '<option value="">— Sin regiones configuradas —</option>';
  }

  if (currentVal && selectRegion.querySelector(`option[value="${currentVal}"]`)) {
    selectRegion.value = currentVal;
    selectRegion.dispatchEvent(new Event('change')); 
  } else if (ES_CZ && regionesDataGlobal.length === 1) {
    // El Coord. de Zona solo tiene su propia región: se preselecciona
    selectRegion.value = regionesDataGlobal[0].id;
    selectRegion.dispatchEvent(new Event('change'));
  }
}

document.getElementById('region')?.addEventListener('change', function() {
  const selectZona = document.getElementById('zona');
  if(!selectZona) return;

  selectZona.innerHTML = '<option value="">— Cargando zonas... —</option>';
  
  const regionId = this.value;
  if (!regionId) {
      selectZona.innerHTML = '<option value="">— Selecciona primero una región —</option>';
      return;
  }

  fetch(ZONAS_CIRC_URL + '&action=get_zonas&region_id=' + encodeURIComponent(regionId))
    .then(response => response.json())
    .then(data => {
      selectZona.innerHTML = '<option value="">— Selecciona una zona —</option>';
      if (data.success && data.zonas && data.zonas.length > 0) {
        data.zonas.forEach(z => {
          let opt = document.createElement('option');
          opt.value = z.id;
          opt.textContent = `${z.nombre} ${z.clave ? `(${z.clave})` : ''}` + (z.is_ocupada ? ' (con responsable)' : '');
          if (z.is_ocupada) opt.disabled = true;
          selectZona.appendChild(opt);
        });

        const hayLibres = data.zonas.some(z => !z.is_ocupada);
        if (!hayLibres && tipoSeleccionado === 'zona') {
           selectZona.innerHTML += '<option value="" disabled>— Todas las zonas están ocupadas —</option>';
        }
      } else {
        selectZona.innerHTML = '<option value="">— Sin zonas configuradas —</option>';
      }
    })
    .catch(error => {
      console.error('Error al cargar zonas:', error);
      selectZona.innerHTML = '<option value="">— Error de conexión —</option>';
    });
});

function togglePwd(id, btn) {
  const input = document.getElementById(id);
  const icon = btn.querySelector('i');
  if (input.type === 'password') {
    input.type = 'text';
    icon.className = 'fas fa-eye-slash';
  } else {
    input.type = 'password';
    icon.className = 'fas fa-eye';
  }
}

// Envía la tabla correcta a la API Central manage.php
function mapearRolBd(tipo) {
    const mapa = {
        'regional': 'coordinador_regional',
        'zona': 'coordinador_zona',
        'espejo_circunscripcion': 'coordinadores_circunscripcion',
        'espejo_estatal': 'coordinador_estatal',
        'espejo_regional': 'coordinador_regional'
    };
    return mapa[tipo] || tipo;
}

function validateForm() {
  let valid = true;
  let required = ['nombre', 'apellido_p', 'telefono'];

  if (requiereAcceso) {
    required.push('correo', 'password', 'password2');
  }

  required.forEach(id => {
    const el = document.getElementById(id);
    if (!el || !el.value.trim()) { 
        if(el) el.classList.add('error'); 
        valid = false; 
    } else {
        el.classList.remove('error');
    }
  });

  if (!tipoSeleccionado) {
    showAlert('error', 'Selecciona el tipo de perfil.');
    return false;
  }

  const esEspejo = tipoSeleccionado.startsWith('espejo_');

  if (esEspejo) {
    const titularVal = document.getElementById('titularEspejo').value;
    if (!titularVal) {
      document.getElementById('titularEspejo').classList.add('error');
      showAlert('error', 'Selecciona el titular que se va a reflejar.');
      return false;
    } else {
      document.getElementById('titularEspejo').classList.remove('error');
    }
  } else {
    if (!estadoSeleccionado) {
      showAlert('error', 'Selecciona un estado.');
      return false;
    }

    // Validar Región si el perfil lo requiere
    if (['regional', 'zona', 'operador', 'movilizador', 'ciudadano'].includes(tipoSeleccionado)) {
      const regionVal = document.getElementById('region').value;
      if (!regionVal) {
        document.getElementById('region').classList.add('error');
        showAlert('error', 'Debes asignar una región.');
        return false;
      } else {
        document.getElementById('region').classList.remove('error');
      }
    }

    // Validar Zona si el perfil es Coordinador de Zona
    if (tipoSeleccionado === 'zona') {
      const zonaVal = document.getElementById('zona').value;
      if (!zonaVal) {
        document.getElementById('zona').classList.add('error');
        showAlert('error', 'Debes asignar una zona.');
        return false;
      } else {
        document.getElementById('zona').classList.remove('error');
      }
    }

    // Validar al menos una sección (Coord. de Zona creando perfiles sin acceso)
    if (usaSeccionesMultiples() && seccionesSeleccionadas.size === 0) {
      document.getElementById('secciones-zona-lista').classList.add('error');
      showAlert('error', 'Asigna al menos una sección.');
      return false;
    }
  }
  
  if (requiereAcceso) {
    const pwd = document.getElementById('password').value;
    const pwd2 = document.getElementById('password2').value;
    
    const emailStr = document.getElementById('correo').value.trim();
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(emailStr)) {
      document.getElementById('correo').classList.add('error');
      showAlert('error', 'Ingresa un correo electrónico válido.');
      return false;
    }

    if (pwd !== pwd2) {
      document.getElementById('password2').classList.add('error');
      showAlert('error', 'Las contraseñas no coinciden.');
      return false;
    }
    if (pwd.length < 8) {
      document.getElementById('password').classList.add('error');
      showAlert('error', 'La contraseña debe tener mínimo 8 caracteres.');
      return false;
    }
  }

  if (!valid) { showAlert('error', 'Completa todos los campos requeridos en rojo.'); return false; }
  return true;
}

function guardarUsuario() {
  if (!validateForm()) return;

  const btn = document.getElementById('btn-guardar');
  const original = btn.innerHTML;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
  btn.disabled = true;

  const rolTraducido = mapearRolBd(tipoSeleccionado);

  const formData = new FormData();
  formData.append('action', 'create');
  formData.append('rol', rolTraducido);
  formData.append('nombre_pila', document.getElementById('nombre').value.trim());
  formData.append('ap_paterno', document.getElementById('apellido_p').value.trim());
  formData.append('ap_materno', document.getElementById('apellido_m').value.trim());
  formData.append('telefono', document.getElementById('telefono').value.trim());
  
  if(requiereAcceso) {
      formData.append('email', document.getElementById('correo').value.trim());
      formData.append('password', document.getElementById('password').value);
      formData.append('confirm_password', document.getElementById('password2').value);
  } else {
      formData.append('email', '');
  }

  const esEspejoGuardar = tipoSeleccionado.startsWith('espejo_');
  const multiSecciones = usaSeccionesMultiples();

  formData.append('estado', estadoSeleccionado);
  formData.append('calle', document.getElementById('domicilio').value.trim());

  if (multiSecciones) {
    const lista = seccionesSeleccionadasOrdenadas();
    formData.append('secciones', JSON.stringify(lista));       // arreglo completo
    formData.append('seccion_electoral', lista[0] || '');      // compatibilidad: primera sección
  } else {
    formData.append('seccion_electoral', document.getElementById('seccion').value.trim());
  }

  formData.append('es_espejo', esEspejoGuardar ? '1' : '0');
  if (esEspejoGuardar) {
    formData.append('espejo_de', document.getElementById('titularEspejo').value);
  }
  
  // Guardamos las variables por si hay que enlazar y enviar al backend
  const regionPayload = document.getElementById('region')?.value || '';
  const zonaPayload = (multiSecciones && zonaCZ) ? String(zonaCZ.id) : (document.getElementById('zona')?.value || '');

  formData.append('region_id', regionPayload);
  formData.append('zona_id', zonaPayload);

  // Enviar a API manage.php
  fetch('../../api/equipo/manage.php', {
    method: 'POST',
    body: formData
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      // Sincronizar el ID del usuario con la estructura (Región o Zona)
      const newId = data.insert_id || data.id; 
      
      if (newId && (tipoSeleccionado === 'regional' || tipoSeleccionado === 'zona')) {
          const estrucId = tipoSeleccionado === 'regional' ? regionPayload : zonaPayload;
          fetch(ZONAS_CIRC_URL + '&action=link_coordinador', {
              method: 'POST',
              headers: {'Content-Type': 'application/x-www-form-urlencoded'},
              body: new URLSearchParams({
                  tipo: tipoSeleccionado,
                  estructura_id: estrucId,
                  coordinador_id: newId
              })
          });
      }

      const nombreCompleto = document.getElementById('nombre').value.trim() + " " + document.getElementById('apellido_p').value.trim();
      showAlert('success', `Registro de <strong>${nombreCompleto}</strong> exitoso.`);
      resetForm();
    } else {
      showAlert('error', data.error || data.message || 'No se pudo guardar la información.');
    }
  })
  .catch((e) => {
      console.error(e);
      showAlert('error', 'Error de conexión. Intenta de nuevo.')
  })
  .finally(() => {
    btn.innerHTML = original;
    btn.disabled = false;
  });
}

function resetForm() {
  document.querySelectorAll('.form-input, .form-select').forEach(el => {
    if(el.id !== 'region' && el.id !== 'zona') {
      el.value = '';
    }
    el.classList.remove('error');
  });
  
  document.querySelectorAll('.tipo-card').forEach(c => c.classList.remove('selected'));
  document.querySelectorAll('.field-conditional').forEach(f => f.classList.remove('visible'));
  tipoSeleccionado = null;

  // Reset del selector múltiple de secciones
  seccionesSeleccionadas.clear();
  document.getElementById('secciones-zona-lista')?.classList.remove('error');
  const fieldSeccionUnicaReset = document.getElementById('field-seccion-unica');
  if (fieldSeccionUnicaReset) fieldSeccionUnicaReset.style.display = '';
  renderSeccionesZona();
  
  if(document.getElementById('region')) document.getElementById('region').innerHTML = '<option value="">— Selecciona primero un estado —</option>';
  if(document.getElementById('zona')) document.getElementById('zona').innerHTML = '<option value="">— Selecciona primero una región —</option>';
  if(document.getElementById('titularEspejo')) document.getElementById('titularEspejo').innerHTML = '<option value="">— Selecciona primero un titular —</option>';
  const seccionTerritorialReset = document.getElementById('seccion-territorial');
  if (seccionTerritorialReset) seccionTerritorialReset.style.display = '';
  
  if(estadoSeleccionado) {
      actualizarRegiones(estadoSeleccionado);
  }
}

function showAlert(type, msg) {
  document.getElementById('alert-success').classList.remove('show');
  document.getElementById('alert-error').classList.remove('show');
  const el = document.getElementById('alert-' + type);
  const msgEl = document.getElementById(type === 'success' ? 'alert-msg' : 'alert-err-msg');
  if (msgEl) msgEl.innerHTML = msg;
  el.classList.add('show');
  el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  setTimeout(() => el.classList.remove('show'), 5000);
}
</script>
</body>
</html>