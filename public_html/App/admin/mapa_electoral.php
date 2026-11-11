<?php
/**
 * Mapa Electoral - Multi-Estado
 * Requiere archivos geojson de municipios y secciones por estado en carpeta /geojson/
 */

if (file_exists(__DIR__ . '/../../includes/bootstrap.php')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
} else { session_start(); }
$rol = $_SESSION['user_role'] ?? '';
$permitidos = ['admin','administrador','superadmin','super admin'];
if (!in_array($rol, $permitidos)) { header('Location: /public/index.php'); exit(); }

$geojsonPath = __DIR__ . '/../../geojson/';

// Configuración de estados disponibles
// key: identificador, label: nombre visible, municipioFile: archivo de municipios, seccionFile: archivo de secciones, centroide: [lat,lng], zoom
$estados = [
    'sonora' => [
        'label'        => 'Sonora',
        'municipioFile'=> 'municipio.geojson',
        'seccionFile'  => 'seccion.geojson',
        'centroide'    => [29.5, -110.5],
        'zoom'         => 7,
    ],
    'bc' => [
        'label'        => 'Baja California',
        'municipioFile'=> 'municipio-bc.geojson',
        'seccionFile'  => 'seccion-bc.geojson',
        'centroide'    => [30.5, -115.5],
        'zoom'         => 7,
    ],
    'bcs' => [
        'label'        => 'Baja California Sur',
        'municipioFile'=> 'municipio-bcs.geojson',
        'seccionFile'  => 'seccion-bcs.geojson',
        'centroide'    => [24.5, -111.5],
        'zoom'         => 7,
    ],
    'chihuahua' => [
        'label'        => 'Chihuahua',
        'municipioFile'=> 'municipio-chihuahua.geojson',
        'seccionFile'  => 'seccion-chihuahua.geojson',
        'centroide'    => [28.6, -106.1],
        'zoom'         => 7,
    ],
    'durango' => [
        'label'        => 'Durango',
        'municipioFile'=> 'municipio-durango.geojson',
        'seccionFile'  => 'seccion-durango.geojson',
        'centroide'    => [24.5, -104.7],
        'zoom'         => 7,
    ],
    'sinaloa' => [
        'label'        => 'Sinaloa',
        'municipioFile'=> 'municipio-sinaloa.geojson',
        'seccionFile'  => 'seccion-sinaloa.geojson',
        'centroide'    => [24.8, -107.4],
        'zoom'         => 7,
    ],
    'nayarit' => [
        'label'        => 'Nayarit',
        'municipioFile'=> 'municipio-nayarit.geojson',
        'seccionFile'  => 'seccion-nayarit.geojson',
        'centroide'    => [21.75, -104.85],
        'zoom'         => 8,
    ],
    'jalisco' => [
        'label'        => 'Jalisco',
        'municipioFile'=> 'municipio-jalisco.geojson',
        'seccionFile'  => 'seccion-jalisco.geojson',
        'centroide'    => [20.6, -103.3],
        'zoom'         => 7,
    ],
    'aguascalientes' => [
        'label'        => 'Aguascalientes',
        'municipioFile'=> 'municipio-aguascalientes.geojson',
        'seccionFile'  => 'seccion-aguascalientes.geojson',
        'centroide'    => [22.04, -102.37],
        'zoom'         => 9,
    ],
    'campeche' => [
        'label'        => 'Campeche',
        'municipioFile'=> 'municipio-campeche.geojson',
        'seccionFile'  => 'seccion-campeche.geojson',
        'centroide'    => [19.53, -90.79],
        'zoom'         => 8,
    ],
    'chiapas' => [
        'label'        => 'Chiapas',
        'municipioFile'=> 'municipio-chiapas.geojson',
        'seccionFile'  => 'seccion-chiapas.geojson',
        'centroide'    => [16.26, -92.30],
        'zoom'         => 7,
    ],
    'coahuila' => [
        'label'        => 'Coahuila',
        'municipioFile'=> 'municipio-coahuila.geojson',
        'seccionFile'  => 'seccion-coahuila.geojson',
        'centroide'    => [27.21, -101.90],
        'zoom'         => 7,
    ],
    'colima' => [
        'label'        => 'Colima',
        'municipioFile'=> 'municipio-colima.geojson',
        'seccionFile'  => 'seccion-colima.geojson',
        'centroide'    => [19.10, -104.09],
        'zoom'         => 9,
    ],
    'guanajuato' => [
        'label'        => 'Guanajuato',
        'municipioFile'=> 'municipio-guanajuato.geojson',
        'seccionFile'  => 'seccion-guanajuato.geojson',
        'centroide'    => [20.88, -100.88],
        'zoom'         => 8,
    ],
    'quintana-roo' => [
        'label'        => 'Quintana Roo',
        'municipioFile'=> 'municipio-quintana-roo.geojson',
        'seccionFile'  => 'seccion-quintana-roo.geojson',
        'centroide'    => [19.18, -88.48],
        'zoom'         => 7,
    ],
    'san-luis-potosi' => [
        'label'        => 'San Luis Potosí',
        'municipioFile'=> 'municipio-san-luis-potosi.geojson',
        'seccionFile'  => 'seccion-san-luis-potosi.geojson',
        'centroide'    => [22.15, -100.05],
        'zoom'         => 7,
    ],
    'tabasco' => [
        'label'        => 'Tabasco',
        'municipioFile'=> 'municipio-tabasco.geojson',
        'seccionFile'  => 'seccion-tabasco.geojson',
        'centroide'    => [17.84, -92.62],
        'zoom'         => 8,
    ],
    'tamaulipas' => [
        'label'        => 'Tamaulipas',
        'municipioFile'=> 'municipio-tamaulipas.geojson',
        'seccionFile'  => 'seccion-tamaulipas.geojson',
        'centroide'    => [24.26, -98.84],
        'zoom'         => 7,
    ],
    'tlaxcala' => [
        'label'        => 'Tlaxcala',
        'municipioFile'=> 'municipio-tlaxcala.geojson',
        'seccionFile'  => 'seccion-tlaxcala.geojson',
        'centroide'    => [19.32, -98.24],
        'zoom'         => 9,
    ],
    'veracruz' => [
        'label'        => 'Veracruz',
        'municipioFile'=> 'municipio-veracruz.geojson',
        'seccionFile'  => 'seccion-veracruz.geojson',
        'centroide'    => [19.18, -96.14],
        'zoom'         => 7,
    ],
    'yucatan' => [
        'label'        => 'Yucatán',
        'municipioFile'=> 'municipio-yucatan.geojson',
        'seccionFile'  => 'seccion-yucatan.geojson',
        'centroide'    => [20.71, -89.00],
        'zoom'         => 8,
    ],
    'zacatecas' => [
        'label'        => 'Zacatecas',
        'municipioFile'=> 'municipio-zacatecas.geojson',
        'seccionFile'  => 'seccion-zacatecas.geojson',
        'centroide'    => [23.76, -102.58],
        'zoom'         => 7,
    ],
    'queretaro' => [
        'label'        => 'Querétaro',
        'municipioFile'=> 'municipio-queretaro.geojson',
        'seccionFile'  => 'seccion-queretaro.geojson',
        'centroide'    => [20.59, -100.00],
        'zoom'         => 9,
    ],
    'puebla' => [
        'label'        => 'Puebla',
        'municipioFile'=> 'municipio-puebla.geojson',
        'seccionFile'  => 'seccion-puebla.geojson',
        'centroide'    => [18.87, -98.20],
        'zoom'         => 8,
    ],
    'oaxaca' => [
        'label'        => 'Oaxaca',
        'municipioFile'=> 'municipio-oaxaca.geojson',
        'seccionFile'  => 'seccion-oaxaca.geojson',
        'centroide'    => [17.05, -96.72],
        'zoom'         => 7,
    ],
    'nuevo-leon' => [
        'label'        => 'Nuevo León',
        'municipioFile'=> 'municipio-nuevo-leon.geojson',
        'seccionFile'  => 'seccion-nuevo-leon.geojson',
        'centroide'    => [25.59, -99.59],
        'zoom'         => 8,
    ],
    'morelos' => [
        'label'        => 'Morelos',
        'municipioFile'=> 'municipio-morelos.geojson',
        'seccionFile'  => 'seccion-morelos.geojson',
        'centroide'    => [18.68, -99.10],
        'zoom'         => 9,
    ],
    'michoacan' => [
        'label'        => 'Michoacán',
        'municipioFile'=> 'municipio-michoacan.geojson',
        'seccionFile'  => 'seccion-michoacan.geojson',
        'centroide'    => [19.22, -101.88],
        'zoom'         => 8,
    ],
    'estado-de-mexico' => [
        'label'        => 'Estado de México',
        'municipioFile'=> 'municipio-estado-de-mexico.geojson',
        'seccionFile'  => 'seccion-estado-de-mexico.geojson',
        'centroide'    => [19.35, -99.65],
        'zoom'         => 9,
    ],
    'hidalgo' => [
        'label'        => 'Hidalgo',
        'municipioFile'=> 'municipio-hidalgo.geojson',
        'seccionFile'  => 'seccion-hidalgo.geojson',
        'centroide'    => [20.49, -98.91],
        'zoom'         => 8,
    ],
    'guerrero' => [
        'label'        => 'Guerrero',
        'municipioFile'=> 'municipio-guerrero.geojson',
        'seccionFile'  => 'seccion-guerrero.geojson',
        'centroide'    => [17.55, -99.50],
        'zoom'         => 7,
    ],
    'ciudad-de-mexico' => [
        'label'        => 'Ciudad de México',
        'municipioFile'=> 'municipio-ciudad-de-mexico.geojson',
        'seccionFile'  => 'seccion-ciudad-de-mexico.geojson',
        'centroide'    => [19.43, -99.13],
        'zoom'         => 11,
    ],
];

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

    $estadosData[$key] = [
        'label'      => $cfg['label'],
        'centroide'  => $cfg['centroide'],
        'zoom'       => $cfg['zoom'],
        'municipio'  => $munData,
        'seccionUrl' => $seccionUrl,   // ruta para fetch en el cliente
    ];
}

$estadosJson = json_encode($estadosData);
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
  </style>
</head>
<body>

<?php if (file_exists(__DIR__ . '/../../includes/components/sidebar_admin.php')) include __DIR__ . '/../../includes/components/sidebar_admin.php'; ?>

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
    <button class="btn-evento" onclick="abrirModal()">
      <i class="fa-solid fa-calendar-plus"></i>
      Agregar Evento
    </button>
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
          <div class="layer-color" style="background:#e8f0eb;border:2px solid #4a7c5e;"></div>
          <span class="layer-name" id="label-municipio">Municipios</span>
        </div>
        <div class="toggle on" id="toggle-municipio"></div>
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

      <div id="capas-placeholder" style="font-size:11px;color:var(--texto-dim);padding:6px 0;">
        Selecciona un estado para ver las capas disponibles.
      </div>

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

let seccionActiva  = null;
let municipioActivo = null;
let estadoActual   = null;

const capasVisibles = { municipio: true, secciones: true, labels: true };

// ──────────────────────────────────────────────
// Helpers: limpiar capas
// ──────────────────────────────────────────────
function limpiarCapas() {
  if (layerEstadoSilueta) { map.removeLayer(layerEstadoSilueta); layerEstadoSilueta = null; }
  if (layerMunicipio)     { map.removeLayer(layerMunicipio);     layerMunicipio = null; }
  if (layerSecciones)     { map.removeLayer(layerSecciones);     layerSecciones = null; }
  if (layerLabels)        { map.removeLayer(layerLabels);        layerLabels = null; }
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
  document.getElementById('capas-placeholder').style.display = 'block';

  // Reset toggles al estado "on"
  ['municipio','secciones','labels'].forEach(n => {
    capasVisibles[n] = true;
    document.getElementById(`toggle-${n}`).classList.add('on');
  });

  if (!key || !ESTADOS_DATA[key]) {
    document.getElementById('header-estado-span').textContent = '— Selecciona un estado';
    map.setView([23.6345, -102.5528], 5);
    return;
  }

  estadoActual = key;
  const cfg = ESTADOS_DATA[key];
  document.getElementById('header-estado-span').textContent = cfg.label;

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

  // PASO 3: secciones — fetch lazy si hay URL
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
        // Re-pintar eventos sobre las nuevas secciones cargadas
        repintarEventos();
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
function toggleCapa(nombre) {
  capasVisibles[nombre] = !capasVisibles[nombre];
  document.getElementById(`toggle-${nombre}`).classList.toggle('on', capasVisibles[nombre]);

  const capaMap = { municipio: layerMunicipio, secciones: layerSecciones, labels: layerLabels };
  const capa = capaMap[nombre];
  if (!capa) return;
  capasVisibles[nombre] ? map.addLayer(capa) : map.removeLayer(capa);
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
  document.getElementById('db-panel').style.display   = 'none';
  document.getElementById('db-no-data').style.display = 'none';
  document.getElementById('db-spinner').style.display = 'none';
});

// ──────────────────────────────────────────────
// Eventos — persisten en DB via agenda/procesar.php
// ──────────────────────────────────────────────
const AGENDA_API = '/api/mapa_electoral/procesar.php';
let eventosData   = [];          // eventos cargados de DB
let capasVisiblesEventos = true;
const layerEventos = L.layerGroup().addTo(map);

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

function repintarEventos() {
  layerEventos.clearLayers();
  eventosData.forEach(ev => colocarMarkerEvento(ev));
}

function actualizarBadge() {
  document.getElementById('ev-count-badge').textContent = eventosData.length;
}

// ── Toggle capa eventos ──
function toggleCapaEventos() {
  capasVisiblesEventos = !capasVisiblesEventos;
  document.getElementById('toggle-eventos').classList.toggle('on', capasVisiblesEventos);
  capasVisiblesEventos ? map.addLayer(layerEventos) : map.removeLayer(layerEventos);
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

// ── Modal ──
function abrirModal() {
  // Reset form
  ejeSeleccionadoM = null;
  document.querySelectorAll('.eje-btn-ag').forEach(b => b.className = 'eje-btn-ag');
  document.getElementById('m-subtipo').innerHTML = '<option value="">— Selecciona un eje primero —</option>';
  ['m-titulo','m-inicio','m-fin','m-horas','m-responsable','m-notas'].forEach(id => {
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

  if (!titulo)             { document.getElementById('m-titulo').focus(); mostrarToast('El título es obligatorio'); return; }
  if (!fecha)              { mostrarToast('La fecha es obligatoria'); return; }
  if (!ejeSeleccionadoM)   { mostrarToast('Selecciona un eje'); return; }
  if (!seccionParaEvento)  { mostrarToast('Selecciona una sección en el mapa'); return; }

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
  // Calcular posición: usar latitud/longitud si existen, si no buscar en secciones
  let latlng = null;
  if (ev.lat && ev.lng) {
    latlng = [parseFloat(ev.lat), parseFloat(ev.lng)];
  } else if (layerSecciones) {
    // Buscar la sección correspondiente en el layer activo
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
  if (!latlng) return; // no se puede pintar sin coordenadas

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

  } catch (err) {
    document.getElementById('db-spinner').style.display = 'none';
    document.getElementById('db-no-data').style.display = 'block';
    console.warn('cargarDatosSeccion error:', err);
  }
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
  <div class="modal">
    <button class="modal-close" onclick="cerrarModal()"><i class="fas fa-times"></i></button>
    <h2 class="modal-title">Nuevo Evento</h2>
    <p class="modal-subtitle">Registra un evento en la agenda. La sección se toma del mapa.</p>

    <!-- Sección seleccionada (autocompletada del mapa) -->
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

    <!-- Eje -->
    <div class="form-group" style="margin-bottom:20px;">
      <label class="form-label">Eje *</label>
      <div class="eje-selector-ag" style="margin-top:5px;">
        <button class="eje-btn-ag" data-eje="voto"         onclick="seleccionarEjeM('voto')"         type="button">🗳️ Voto / Territorio</button>
        <button class="eje-btn-ag" data-eje="comunicacion" onclick="seleccionarEjeM('comunicacion')" type="button">📣 Comunicación</button>
        <button class="eje-btn-ag" data-eje="organizacion" onclick="seleccionarEjeM('organizacion')" type="button">⚙️ Organización</button>
      </div>
    </div>

    <!-- Subtipo -->
    <div class="form-group" style="margin-bottom:20px;">
      <label class="form-label">Subtipo</label>
      <select id="m-subtipo" class="form-input-ag"><option value="">— Selecciona un eje primero —</option></select>
    </div>

    <!-- Título -->
    <div class="form-group" style="margin-bottom:20px;">
      <label class="form-label">Título *</label>
      <input type="text" id="m-titulo" class="form-input-ag" placeholder="Ej. Recorrido Colonia Pitic">
    </div>

    <!-- Fecha / Estado / Municipio -->
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:20px;">
      <div class="form-group">
        <label class="form-label">Fecha *</label>
        <input type="date" id="m-fecha" class="form-input-ag">
      </div>
      <div class="form-group">
        <label class="form-label">Estado</label>
        <input type="text" id="m-estado" class="form-input-ag" placeholder="Auto del mapa" readonly style="background:#f0f4f0;">
      </div>
      <div class="form-group">
        <label class="form-label">Municipio</label>
        <input type="text" id="m-municipio" class="form-input-ag" placeholder="Auto del mapa" readonly style="background:#f0f4f0;">
      </div>
    </div>

    <!-- Horario -->
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:20px;">
      <div class="form-group"><label class="form-label">Inicio</label><input type="time" id="m-inicio" class="form-input-ag"></div>
      <div class="form-group"><label class="form-label">Fin</label><input type="time" id="m-fin" class="form-input-ag" onchange="calcHorasM()"></div>
      <div class="form-group"><label class="form-label">Horas</label><input type="number" id="m-horas" class="form-input-ag" min="0" max="24" step="0.5" placeholder="0"></div>
    </div>

    <!-- Responsable -->
    <div class="form-group" style="margin-bottom:20px;">
      <label class="form-label">Responsable</label>
      <input type="text" id="m-responsable" class="form-input-ag" placeholder="Nombre del responsable">
    </div>

    <!-- Notas -->
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

</body>
</html>