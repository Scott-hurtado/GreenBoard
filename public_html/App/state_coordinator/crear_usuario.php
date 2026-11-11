<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

$usuario_id = $_SESSION['user_id'] ?? 0;
$rol = $_SESSION['user_role'] ?? '';

// 1. Acceso restringido solo al Coordinador Estatal
$permitidos = ['coordinador_estatal'];
if (!in_array($rol, $permitidos)) {
    header('Location: /public/index.php');
    exit();
}

// 2. Obtener el estado del coordinador desde la base de datos
$estado_actual = '';
try {
    global $pdo;
    if (!$pdo && class_exists('Database')) {
        $pdo = Database::getInstance()->getConnection();
    }
    
    if($pdo){
        $stmt = $pdo->prepare("SELECT estado FROM coordinador_estatal WHERE id = ?");
        $stmt->execute([$usuario_id]);
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($userData && !empty($userData['estado'])) {
            $estado_actual = $userData['estado'];
        }
    }
} catch (PDOException $e) {
    error_log("Error al obtener estado del coordinador: " . $e->getMessage());
}

// ── MANEJO AJAX PARA REGIONES Y ZONAS (Nuevas tablas) ──
if (isset($_GET['ajax_geo'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_GET['action'] ?? '';
    
    try {
        if ($action === 'get_regiones') {
            $estado = $_GET['estado'] ?? '';
            // Leemos de la tabla correcta y verificamos si ya tienen coordinador
            $stmt = $pdo->prepare("SELECT id, nombre, coordinador_id FROM regiones_estatales WHERE estado = ? ORDER BY nombre ASC");
            $stmt->execute([$estado]);
            $regiones = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach($regiones as &$r) {
                $r['is_ocupada'] = !empty($r['coordinador_id']);
            }
            echo json_encode(['success' => true, 'regiones' => $regiones]);
            exit;
        }
        
        if ($action === 'get_zonas') {
            $region_id = (int)($_GET['region_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT id, nombre, clave, coordinador_id FROM zonas_estatales WHERE estructura_id = ? ORDER BY nombre ASC");
            $stmt->execute([$region_id]);
            $zonas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach($zonas as &$z) {
                $z['is_ocupada'] = !empty($z['coordinador_id']);
            }
            echo json_encode(['success' => true, 'zonas' => $zonas]);
            exit;
        }

        // NUEVO: Sincroniza al coordinador recién creado con la tabla de estructuras
        if ($action === 'link_coordinador') {
            $tipo = $_POST['tipo'] ?? '';
            $estruc_id = (int)($_POST['estructura_id'] ?? 0);
            $coord_id = (int)($_POST['coordinador_id'] ?? 0);

            if ($estruc_id > 0 && $coord_id > 0) {
                if ($tipo === 'regional') {
                    $pdo->prepare("UPDATE regiones_estatales SET coordinador_id = ? WHERE id = ?")->execute([$coord_id, $estruc_id]);
                } elseif ($tipo === 'zona') {
                    $pdo->prepare("UPDATE zonas_estatales SET coordinador_id = ? WHERE id = ?")->execute([$coord_id, $estruc_id]);
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Crear Usuario - Estatal - Proyecto Verde</title>
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

  .tipo-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
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
  .estado-chip.selected { background: var(--primary); border-color: var(--primary); color: white; cursor: default;}

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
    .tipo-grid { grid-template-columns: repeat(2, 1fr); }
  }
  @media (max-width: 768px) {
    .form-grid-3, .form-grid-2 { grid-template-columns: 1fr; }
    .tipo-grid { grid-template-columns: 1fr; }
  }
</style>
</head>
<body>

<?php 
if (file_exists(__DIR__ . '/../../includes/components/sidebar_ce.php')) {
    include __DIR__ . '/../../includes/components/sidebar_ce.php'; 
}
?>

<div class="design-text">USUARIOS</div>

<div class="container">

  <div class="page-header">
    <div class="header-content">
      <span class="ai-badge"><?php echo htmlspecialchars($estado_actual); ?></span>
      <h1 class="main-title">Crear Usuario</h1>
      <p class="description">Registra un nuevo perfil para tu estado.</p>
    </div>
    <div style="display:flex;gap:12px">
      <a href="ver_usuarios.php" class="btn-action btn-outline">
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
    <div class="tipo-card" id="tipo-regional" onclick="selectTipo('regional')">
      <div class="tipo-icon"><i class="fas fa-location-arrow"></i></div>
      <div class="tipo-info">
        <div class="tipo-name">Coord. Regional</div>
        <div class="tipo-desc">Acceso a su región asignada.</div>
      </div>
    </div>
    <div class="tipo-card" id="tipo-zona" onclick="selectTipo('zona')">
      <div class="tipo-icon"><i class="fas fa-map-pin"></i></div>
      <div class="tipo-info">
        <div class="tipo-name">Coord. de Zona</div>
        <div class="tipo-desc">Acceso a su zona asignada.</div>
      </div>
    </div>
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
        <div class="estado-chips" id="chips-estados">
           <div class='estado-chip selected'><i class="fas fa-lock"></i> <?php echo htmlspecialchars($estado_actual ?: 'Sin estado'); ?></div>
        </div>
        <span class="form-hint">Solo puedes registrar perfiles en tu estado asignado.</span>
      </div>

      <div class="form-grid form-grid-3">
          <div class="form-group field-conditional" id="field-region">
            <label class="form-label">Región Asignada <span class="required">*</span></label>
            <div class="select-wrapper">
              <select class="form-select" id="region">
                <option value="">— Cargando regiones... —</option>
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

          <div class="form-group">
            <label class="form-label">Sección Electoral</label>
            <input type="text" class="form-input" id="seccion" placeholder="Ej: 1234" maxlength="4" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
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
const ZONAS_CIRC_URL = window.location.pathname + '?ajax_geo=1';
const ESTADO_COORDINADOR = '<?php echo addslashes($estado_actual); ?>';

let tipoSeleccionado = null;
let requiereAcceso = true;
let regionesDataGlobal = [];

document.addEventListener('DOMContentLoaded', () => {
    if(ESTADO_COORDINADOR) {
        actualizarRegiones(ESTADO_COORDINADOR);
    } else {
        document.getElementById('region').innerHTML = '<option value="">— Error: Estado no asignado —</option>';
    }
});

function selectTipo(tipo) {
  document.querySelectorAll('.tipo-card').forEach(c => c.classList.remove('selected'));
  document.getElementById('tipo-' + tipo).classList.add('selected');
  tipoSeleccionado = tipo;

  const fieldRegion = document.getElementById('field-region');
  const fieldZona = document.getElementById('field-zona');
  const seccionAcceso = document.getElementById('seccion-acceso');

  fieldRegion.classList.remove('visible');
  fieldZona.classList.remove('visible');
  seccionAcceso.style.display = 'block';
  requiereAcceso = true;

  if (['operador', 'movilizador', 'ciudadano'].includes(tipo)) {
    seccionAcceso.style.display = 'none';
    requiereAcceso = false;
    fieldRegion.classList.add('visible'); 
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

async function actualizarRegiones(estado) {
  const selectRegion = document.getElementById('region');
  const selectZona   = document.getElementById('zona');

  selectRegion.innerHTML = '<option value="">— Cargando... —</option>';
  selectZona.innerHTML   = '<option value="">— Selecciona primero una región —</option>';

  try {
    const respGet = await fetch(ZONAS_CIRC_URL + '&action=get_regiones&estado=' + encodeURIComponent(estado));
    const data = await respGet.json();
    regionesDataGlobal = data.regiones || [];

    if (regionesDataGlobal.length > 0) {
      renderizarRegiones();
    } else {
      selectRegion.innerHTML = '<option value="">— Sin regiones configuradas —</option>';
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
    if (tipoSeleccionado === 'regional' && reg.is_ocupada) {
      return; 
    }

    const opt = document.createElement('option');
    opt.value = reg.id; 
    opt.textContent = reg.nombre;
    selectRegion.appendChild(opt);
  });

  if(selectRegion.options.length === 1 && tipoSeleccionado === 'regional') {
      selectRegion.innerHTML = '<option value="">— Todas las regiones están ocupadas —</option>';
  }

  if (currentVal && selectRegion.querySelector(`option[value="${currentVal}"]`)) {
    selectRegion.value = currentVal;
    selectRegion.dispatchEvent(new Event('change')); 
  }
}

document.getElementById('region')?.addEventListener('change', function() {
  const selectZona = document.getElementById('zona');
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
          if (tipoSeleccionado === 'zona' && z.is_ocupada) {
            return;
          }

          let opt = document.createElement('option');
          opt.value = z.id;
          opt.textContent = `${z.nombre} ${z.clave ? `(${z.clave})` : ''}`;
          selectZona.appendChild(opt);
        });

        if(selectZona.options.length === 1 && tipoSeleccionado === 'zona') {
           selectZona.innerHTML = '<option value="">— Todas las zonas están ocupadas —</option>';
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

function mapearRolBd(tipo) {
    const mapa = {
        'regional': 'coordinador_regional',
        'zona': 'coordinador_zona',
        'seccion': 'coordinador_seccion'
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
  if (!ESTADO_COORDINADOR) {
    showAlert('error', 'No tienes un estado asignado en el sistema.');
    return false;
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

  const payload = {
    action: 'create',
    rol: rolTraducido,
    requiere_acceso: requiereAcceso,
    nombre: document.getElementById('nombre').value.trim(),
    apellido_p: document.getElementById('apellido_p').value.trim(),
    apellido_m: document.getElementById('apellido_m').value.trim(),
    telefono: document.getElementById('telefono').value.trim(),
    correo: document.getElementById('correo') ? document.getElementById('correo').value.trim() : null,
    curp: document.getElementById('curp').value.trim().toUpperCase(),
    ine: document.getElementById('ine').value.trim().toUpperCase(),
    calle: document.getElementById('domicilio').value.trim(),
    seccion_electoral: document.getElementById('seccion').value.trim(),
    password: requiereAcceso ? document.getElementById('password').value : null,
    estados: [ESTADO_COORDINADOR], 
    estado: ESTADO_COORDINADOR,
    region: document.getElementById('region')?.value || '',
    zona: document.getElementById('zona')?.value || ''
  };

  fetch('/api/create_users/crud_usuarios.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      // NUEVO: Sincronizar el ID del usuario con la estructura (Región o Zona)
      const newId = data.insert_id;
      if (newId && (tipoSeleccionado === 'regional' || tipoSeleccionado === 'zona')) {
          const estrucId = tipoSeleccionado === 'regional' ? payload.region : payload.zona;
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

      showAlert('success', `Registro de <strong>${payload.nombre} ${payload.apellido_p}</strong> exitoso.`);
      resetForm();
    } else {
      showAlert('error', data.error || 'No se pudo guardar la información.');
    }
  })
  .catch(() => showAlert('error', 'Error de conexión. Intenta de nuevo.'))
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
  
  document.getElementById('region').value = '';
  document.getElementById('zona').innerHTML = '<option value="">— Selecciona primero una región —</option>';
  
  if(regionesDataGlobal.length > 0) {
      renderizarRegiones();
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