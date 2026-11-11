<?php
if (file_exists(__DIR__ . '/../../includes/bootstrap.php')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
} else { session_start(); }
$rol_sesion = $_SESSION['user_role'] ?? '';
$user_id    = $_SESSION['user_id'] ?? 0;
$user_circ  = $_SESSION['user_circunscripcion'] ?? '';
$permitidos = ['circunscripcion', 'superadmin', 'super admin', 'admin'];
if (!in_array($rol_sesion, $permitidos)) { header('Location: /public/index.php'); exit(); }

$CIRCS = [
    'Circunscripción 1' => ['Baja California','Baja California Sur','Chihuahua','Durango','Jalisco','Nayarit','Sinaloa','Sonora'],
    'Circunscripción 2' => ['Aguascalientes','Coahuila','Guanajuato','Nuevo León','San Luis Potosí','Tamaulipas','Zacatecas'],
    'Circunscripción 3' => ['Campeche','Chiapas','Oaxaca','Quintana Roo','Tabasco','Veracruz','Yucatán'],
    'Circunscripción 4' => ['Ciudad de México','Guerrero','Hidalgo','Morelos','Puebla','Tlaxcala'],
    'Circunscripción 5' => ['Colima','Estado de México','Michoacán','Querétaro'],
];
$estados_circ = $CIRCS[$user_circ] ?? [];
$usuarios = [];

// Función auxiliar
function getCircunscripcionId($estadoBuscado, $circunscripciones) {
    if (empty($estadoBuscado)) return 'all';
    $estadoBuscado = mb_strtolower(trim($estadoBuscado), 'UTF-8');
    $idx = 1;
    foreach($circunscripciones as $nombre => $data) {
        foreach($data as $e) {
            if(mb_strtolower(trim($e), 'UTF-8') === $estadoBuscado) {
                return "circ_" . $idx;
            }
        }
        $idx++;
    }
    return 'none'; 
}

try {
    $dbCfg = __DIR__ . '/../../includes/database.php';
    $cfg = file_exists($dbCfg) ? require $dbCfg : ['host'=>'localhost','database'=>'u881824383_pverde','username'=>'u881824383_proyverde','password'=>'PProyectoverde8#','charset'=>'utf8mb4','options'=>[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]];
    $dsn = "mysql:host={$cfg['host']};dbname={$cfg['database']};charset={$cfg['charset']}";
    $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], $cfg['options'] ?? []);
    
    if (!empty($estados_circ)) {
        $in     = implode(',', array_fill(0, count($estados_circ), '?'));
        $params = array_merge($estados_circ, $estados_circ, $estados_circ, $estados_circ);
        
        $sql = "
            SELECT 
                u.id, u.nombre_completo AS nombres, u.telefono, u.correo AS email, 
                u.colonia, u.direccion AS calle, u.seccion_electoral, 
                IF(u.activo = 1, 'activo', 'inactivo') AS estatus, 
                u.estado, re.nombre AS region, ze.nombre AS zona, u.fecha_registro, 'usuarios' AS tabla_origen, u.rol AS rol_tabla,
                u.username, u.activo
            FROM usuarios u
            LEFT JOIN regiones_estatales re ON re.id = CAST(u.region AS UNSIGNED) AND u.region REGEXP '^[0-9]+$'
            LEFT JOIN zonas_estatales    ze ON ze.id = CAST(u.zona   AS UNSIGNED) AND u.zona   REGEXP '^[0-9]+$'
            WHERE u.estado IN ($in)
            
            UNION ALL 
            
            SELECT 
                id, nombre AS nombres, telefono, email, 
                colonia, calle, seccion_electoral, 
                estatus, 
                estado, NULL AS region, NULL AS zona, fecha_registro, 'coordinador_estatal' AS tabla_origen, 'coordinador_estatal' AS rol_tabla,
                email AS username, IF(estatus='activo',1,0) AS activo
            FROM coordinador_estatal
            WHERE estado IN ($in)
            
            UNION ALL 
            
            SELECT 
                cr.id, cr.nombre AS nombres, cr.telefono, cr.email, 
                cr.colonia, cr.calle, cr.seccion_electoral, 
                cr.estatus, 
                cr.estado, re.nombre AS region, NULL AS zona, cr.fecha_registro, 'coordinador_regional' AS tabla_origen, 'coordinador_regional' AS rol_tabla,
                cr.email AS username, IF(cr.estatus='activo',1,0) AS activo
            FROM coordinador_regional cr
            LEFT JOIN regiones_estatales re ON re.id = cr.region
            WHERE cr.estado IN ($in)
            
            UNION ALL
            
            SELECT 
                cz.id, cz.nombre AS nombres, cz.telefono, cz.email, 
                cz.colonia, cz.calle, cz.seccion_electoral, 
                cz.estatus, 
                cz.estado, re.nombre AS region, ze.nombre AS zona, cz.fecha_registro, 'coordinador_zona' AS tabla_origen, 'coordinador_zona' AS rol_tabla,
                cz.email AS username, IF(cz.estatus='activo',1,0) AS activo
            FROM coordinador_zona cz
            LEFT JOIN regiones_estatales re ON re.id = cz.region
            LEFT JOIN zonas_estatales    ze ON ze.id = cz.zona
            WHERE cz.estado IN ($in)
            
            ORDER BY fecha_registro DESC
        ";
        
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $usuarios = $st->fetchAll();
    }
} catch (Exception $e) { $err_db = $e->getMessage(); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Usuarios - Circunscripción</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root{--primary:#2d7a3e;--primary-dark:#1f5a2d;--accent:#e8a020;--dark:#1a1a1a;--light:#f0f7f0;--white:#fff;--danger:#ef4444;--gray-100:#f8f9fa;--gray-200:#e9ecef;--text-muted:#6c757d;--shadow-sm:0 10px 30px rgba(0,0,0,.05);--radius:20px;}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:Arial,sans-serif;background:var(--light);color:var(--dark);overflow-x:hidden;}
.container{max-width:100%;background:var(--white);min-height:100vh;padding-left:330px;padding-right:50px;padding-bottom:40px;transition:padding-left .3s;}
.design-text{position:fixed;left:290px;top:50%;writing-mode:vertical-rl;font-size:13px;font-weight:500;color:var(--primary);opacity:.3;letter-spacing:2px;z-index:0;pointer-events:none;}
.page-header{padding:50px 0 30px;display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:20px;position:relative;z-index:2;}
.ai-badge{color:var(--primary);font-size:13px;font-weight:800;letter-spacing:4px;margin-bottom:15px;display:inline-block;text-transform:uppercase;background:rgba(45,122,62,.1);padding:5px 15px;border-radius:20px;}
.main-title{font-size:54px;font-weight:900;line-height:1;margin-bottom:15px;}
.description{color:#666;font-size:16px;line-height:1.6;max-width:600px;}
.header-actions{display:flex;gap:12px;align-items:center;}
.btn-primary-action{background:var(--primary);color:#fff;border:none;padding:12px 25px;border-radius:12px;font-weight:700;cursor:pointer;transition:all .3s;display:flex;align-items:center;gap:10px;font-size:14px;box-shadow:0 4px 15px rgba(45,122,62,.3);text-decoration:none;}
.btn-primary-action:hover{background:var(--primary-dark);transform:translateY(-2px);}
.table-section{background:var(--white);border-radius:var(--radius);padding:30px;border:1px solid var(--gray-200);box-shadow:var(--shadow-sm);}
.section-header{margin-bottom:25px;display:flex;justify-content:space-between;align-items:center;}
.section-title{font-size:20px;font-weight:800;}
.filters-bar{background:var(--white);border:1px solid var(--gray-200);border-radius:16px;padding:20px;margin-bottom:30px;display:flex;gap:15px;align-items:flex-end;box-shadow:var(--shadow-sm);flex-wrap:wrap;}
.filter-group{display:flex;flex-direction:column;gap:8px;flex:1;min-width:160px;}
.form-label{font-size:12px;font-weight:700;text-transform:uppercase;color:var(--text-muted);letter-spacing:.5px;}
.form-input-styled{width:100%;padding:12px 15px;border-radius:10px;border:2px solid var(--gray-200);background:var(--gray-100);font-size:14px;font-weight:600;color:var(--dark);transition:all .3s;outline:none;appearance:none;font-family:Arial,sans-serif;}
.form-input-styled:focus{border-color:var(--primary);background:var(--white);}
.modern-table{width:100%;border-collapse:separate;border-spacing:0;}
.table-wrapper{overflow-x:auto;-webkit-overflow-scrolling:touch;}
.modern-table{width:100%;min-width:900px;border-collapse:separate;border-spacing:0;}
.modern-table th{text-align:left;padding:12px 12px;color:var(--text-muted);font-size:11px;text-transform:uppercase;letter-spacing:1px;border-bottom:2px solid var(--gray-200);white-space:nowrap;}
.modern-table td{padding:13px 12px;border-bottom:1px solid var(--gray-200);font-size:13px;color:var(--dark);font-weight:500;vertical-align:middle;transition:background .2s;}
.modern-table tr:hover td{background:var(--gray-100);}
.modern-table tr:last-child td{border-bottom:none;}
.sortable{cursor:pointer;user-select:none;transition:background-color .2s;}
.sortable:hover{background-color:var(--gray-100);border-radius:8px 8px 0 0;}
.sort-icon{display:inline-block;margin-left:6px;font-size:14px;opacity:.3;transition:all .2s;}
.sortable.active{color:var(--primary);font-weight:800;}
.sortable.active .sort-icon{opacity:1;color:var(--primary);}
.badge{padding:5px 12px;border-radius:20px;font-size:11px;font-weight:700;text-transform:uppercase;display:inline-block;}
.badge-estatal{background:#e8f5e9;color:#2d7a3e;border:1px solid #a5d6a7;}
.badge-regional{background:#e3f2fd;color:#1565c0;border:1px solid #90caf9;}
.badge-zona{background:#fff8e1;color:#f57f17;border:1px solid #ffe082;}
.badge-activo{background:#dcfce7;color:#15803d;}
.badge-inactivo{background:#fee2e2;color:#b91c1c;}
.btn-edit{display:inline-flex;align-items:center;justify-content:center;gap:5px;padding:6px 12px;background:var(--white);color:var(--primary);border:2px solid var(--primary);border-radius:8px;font-size:11px;font-weight:700;cursor:pointer;transition:all .2s;white-space:nowrap;font-family:Arial,sans-serif;}
.btn-edit:hover{background:var(--primary);color:var(--white);transform:translateY(-1px);box-shadow:0 4px 12px rgba(45,122,62,.25);}

/* ── MODAL ── */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;display:flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:opacity .25s;}
.modal-overlay.open{opacity:1;pointer-events:all;}
.modal{background:var(--white);border-radius:var(--radius);width:100%;max-width:740px;max-height:90vh;overflow-y:auto;box-shadow:0 30px 80px rgba(0,0,0,.25);transform:translateY(30px);transition:transform .25s;}
.modal-overlay.open .modal{transform:translateY(0);}
.modal-header{padding:28px 32px 20px;border-bottom:1px solid var(--gray-200);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:var(--white);z-index:10;border-radius:var(--radius) var(--radius) 0 0;}
.modal-title{font-size:22px;font-weight:900;}
.modal-subtitle{font-size:13px;color:var(--text-muted);margin-top:4px;}
.modal-close{width:36px;height:36px;border-radius:10px;border:none;background:var(--gray-100);cursor:pointer;font-size:16px;color:var(--text-muted);display:flex;align-items:center;justify-content:center;transition:all .2s;}
.modal-close:hover{background:var(--gray-200);color:var(--dark);}
.modal-body{padding:28px 32px;}
.modal-footer{padding:20px 32px;border-top:1px solid var(--gray-200);display:flex;justify-content:flex-end;gap:12px;position:sticky;bottom:0;background:var(--white);border-radius:0 0 var(--radius) var(--radius);}
.m-grid{display:grid;gap:18px;}
.m-grid-3{grid-template-columns:1fr 1fr 1fr;}
.m-grid-2{grid-template-columns:1fr 1fr;}
.m-group{display:flex;flex-direction:column;gap:6px;}
.m-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#555;}
.m-label .req{color:var(--danger);margin-left:2px;}
.m-input,.m-select{padding:11px 14px;border:2px solid #eee;border-radius:10px;font-size:14px;font-weight:500;color:var(--dark);background:var(--white);outline:none;transition:all .2s;width:100%;font-family:Arial,sans-serif;appearance:none;}
.m-input:focus,.m-select:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(45,122,62,.1);}
.m-input.error{border-color:var(--danger);}
.select-wrap{position:relative;}
.select-wrap::after{content:'\f107';font-family:'Font Awesome 6 Free';font-weight:900;position:absolute;right:14px;top:50%;transform:translateY(-50%);color:var(--text-muted);pointer-events:none;}
.input-wrap{position:relative;}
.input-wrap .m-input{padding-right:44px;}
.pwd-btn{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-muted);font-size:14px;padding:0;}
.pwd-btn:hover{color:var(--primary);}
.toggle-estatus{display:flex;gap:10px;margin-top:2px;}
.t-btn{padding:8px 18px;border-radius:8px;border:2px solid #eee;font-size:12px;font-weight:700;cursor:pointer;transition:all .2s;background:white;font-family:Arial,sans-serif;}
.t-btn.activo.sel{background:#dcfce7;border-color:#15803d;color:#15803d;}
.t-btn.inactivo.sel{background:#fee2e2;border-color:#b91c1c;color:#b91c1c;}
.section-sep{font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);margin:24px 0 14px;display:flex;align-items:center;gap:10px;}
.section-sep::after{content:'';flex:1;height:1px;background:var(--gray-200);}
.m-hint{font-size:11px;color:var(--text-muted);margin-top:2px;}
.m-alert{padding:12px 16px;border-radius:10px;font-size:13px;font-weight:600;display:none;align-items:center;gap:8px;margin-bottom:16px;}
.m-alert.show{display:flex;}
.m-alert-success{background:#dcfce7;color:#15803d;border:1px solid #a7f3d0;}
.m-alert-error{background:#fee2e2;color:#b91c1c;border:1px solid #fca5a5;}
.btn-m{padding:11px 24px;border-radius:10px;border:none;font-weight:700;cursor:pointer;transition:all .3s;display:flex;align-items:center;gap:8px;font-size:14px;font-family:Arial,sans-serif;}
.btn-m-primary{background:var(--primary);color:white;}
.btn-m-primary:hover{background:var(--primary-dark);}
.btn-m-outline{background:white;color:var(--dark);border:2px solid #eee;}
.btn-m-outline:hover{border-color:var(--primary);color:var(--primary);}
.btn-m-danger{background:white;color:var(--danger);border:2px solid #fca5a5;}
.btn-m-danger:hover{background:#fee2e2;border-color:var(--danger);}
.btn-m-danger-solid{background:var(--danger);color:white;border:2px solid var(--danger);}
.btn-m-danger-solid:hover{background:#dc2626;}
/* Modal confirmación eliminar */
.confirm-overlay{position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:3000;display:flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:opacity .25s;}
.confirm-overlay.open{opacity:1;pointer-events:all;}
.confirm-box{background:white;border-radius:16px;padding:32px 28px;max-width:400px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,.2);transform:scale(.95);transition:transform .25s;}
.confirm-overlay.open .confirm-box{transform:scale(1);}
.confirm-icon{width:56px;height:56px;border-radius:50%;background:#fee2e2;display:flex;align-items:center;justify-content:center;margin:0 auto 18px;font-size:22px;color:var(--danger);}
.confirm-title{font-size:18px;font-weight:800;text-align:center;margin-bottom:8px;}
.confirm-msg{font-size:14px;color:#666;text-align:center;line-height:1.5;margin-bottom:24px;}
.confirm-actions{display:flex;gap:12px;justify-content:center;}
@media(max-width:1024px){.container{padding-left:20px;padding-right:20px;}}
@media(max-width:660px){.m-grid-3,.m-grid-2{grid-template-columns:1fr;}.modal{margin:0 12px;max-width:100%;}}
</style>
</head>
<body>

<?php
if (file_exists(__DIR__ . '/../../includes/components/sidebar_circunscripcion.php')) {
    include __DIR__ . '/../../includes/components/sidebar_circunscripcion.php';
}
?>

<div class="design-text">USUARIOS</div>

<div class="container">
  <div class="page-header">
    <div>
      <div class="ai-badge">GESTIÓN</div>
      <h1 class="main-title">Usuarios.</h1>
      <p class="description">Usuarios registrados en los estados de tu <?= htmlspecialchars($user_circ) ?>.</p>
    </div>
    <div class="header-actions">
      <a href="crear_usuario.php" class="btn-primary-action"><i class="fas fa-plus-circle"></i> Nuevo Usuario</a>
    </div>
  </div>

  <?php if(isset($err_db)): ?>
  <div style="background:#f8d7da;color:#721c24;padding:15px;border-radius:10px;margin-bottom:20px;">
    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($err_db) ?>
  </div>
  <?php endif; ?>

  <div class="filters-bar">
    <div class="filter-group" style="flex:2">
      <label class="form-label">Buscar</label>
      <div style="position:relative">
        <i class="fas fa-search" style="position:absolute;left:15px;top:14px;color:#999"></i>
        <input type="text" id="buscador" class="form-input-styled" style="padding-left:40px" placeholder="Nombre, usuario, estado..." oninput="renderTabla()">
      </div>
    </div>
    <div class="filter-group">
      <label class="form-label">Rol</label>
      <select id="filtro-rol" class="form-input-styled" onchange="renderTabla()">
        <option value="">Todos</option>
        <option value="coordinador_estatal">Estatal</option>
        <option value="coordinador_regional">Regional</option>
        <option value="coordinador_zona">Zona</option>
      </select>
    </div>
    <div class="filter-group">
      <label class="form-label">Estado</label>
      <select id="filtro-estado" class="form-input-styled" onchange="renderTabla()">
        <option value="">Todos</option>
        <?php foreach($estados_circ as $e): ?>
          <option><?= htmlspecialchars($e) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <div class="table-section">
    <div class="section-header">
      <div class="section-title">Listado de Usuarios</div>
      <div id="count-label" style="font-size:13px;color:var(--text-muted);font-weight:600;"></div>
    </div>
    <div class="table-wrapper">
    <table class="modern-table">
      <thead id="table-headers">
        <tr>
          <th class="sortable" onclick="sortTable('nombres','string',0)">Usuario <i class="fas fa-sort sort-icon"></i></th>
          <th class="sortable" onclick="sortTable('role','string',1)">Rol <i class="fas fa-sort sort-icon"></i></th>
          <th class="sortable" onclick="sortTable('estado','string',2)">Estado <i class="fas fa-sort sort-icon"></i></th>
          <th class="sortable" onclick="sortTable('region','string',3)">Región <i class="fas fa-sort sort-icon"></i></th>
          <th class="sortable" onclick="sortTable('zona','string',4)">Zona <i class="fas fa-sort sort-icon"></i></th>
          <th class="sortable" onclick="sortTable('telefono','string',5)">Contacto <i class="fas fa-sort sort-icon"></i></th>
          <th class="sortable" onclick="sortTable('fecha','string',6)">Registro <i class="fas fa-sort sort-icon"></i></th>
          <th>Acciones</th>
          <th class="sortable" onclick="sortTable('activo','number',8)">Estatus <i class="fas fa-sort sort-icon"></i></th>
        </tr>
      </thead>
      <tbody id="tabla-body"></tbody>
    </table>
    </div>
  </div>
</div>

<div class="modal-overlay" id="modal-overlay" onclick="closeModalOutside(event)">
  <div class="modal" id="modal">

    <div class="modal-header">
      <div>
        <div class="modal-title">Editar Usuario</div>
        <div class="modal-subtitle" id="modal-subtitle">—</div>
      </div>
      <button class="modal-close" onclick="cerrarModal()"><i class="fas fa-times"></i></button>
    </div>

    <div class="modal-body">
      <div class="m-alert m-alert-success" id="m-alert-success">
        <i class="fas fa-check-circle"></i> <span id="m-msg-ok">Cambios guardados.</span>
      </div>
      <div class="m-alert m-alert-error" id="m-alert-error">
        <i class="fas fa-exclamation-circle"></i> <span id="m-msg-err">Error.</span>
      </div>

      <div class="section-sep"><i class="fas fa-user"></i> Datos Personales</div>
      <div class="m-grid m-grid-3">
        <div class="m-group">
          <label class="m-label">Nombre(s) <span class="req">*</span></label>
          <input type="text" class="m-input" id="m-nombres" placeholder="Ej: Carlos">
        </div>
        <div class="m-group">
          <label class="m-label">Apellido Paterno <span class="req">*</span></label>
          <input type="text" class="m-input" id="m-ap_paterno" placeholder="Ej: López">
        </div>
        <div class="m-group">
          <label class="m-label">Apellido Materno</label>
          <input type="text" class="m-input" id="m-ap_materno" placeholder="Ej: Torres">
        </div>
      </div>

      <div class="m-grid m-grid-2" style="margin-top:18px">
        <div class="m-group">
          <label class="m-label">Teléfono <span class="req">*</span></label>
          <input type="tel" class="m-input" id="m-telefono" placeholder="10 dígitos" maxlength="10">
        </div>
        <div class="m-group">
          <label class="m-label">Estado <span class="req">*</span></label>
          <div class="select-wrap">
            <select class="m-select" id="m-estado">
              <?php foreach($estados_circ as $e): ?>
                <option value="<?= htmlspecialchars($e) ?>"><?= htmlspecialchars($e) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <div class="m-group" style="margin-top:18px">
        <label class="m-label">Estatus</label>
        <div class="toggle-estatus">
          <button type="button" class="t-btn activo" id="m-btn-activo" onclick="setActivo(1)">
            <i class="fas fa-check-circle"></i> Activo
          </button>
          <button type="button" class="t-btn inactivo" id="m-btn-inactivo" onclick="setActivo(0)">
            <i class="fas fa-times-circle"></i> Inactivo
          </button>
        </div>
      </div>

      <div class="section-sep" style="margin-top:28px"><i class="fas fa-lock"></i> Acceso al Sistema</div>
      <div class="m-group" style="margin-bottom:18px">
        <label class="m-label">Correo / Usuario</label>
        <input type="email" class="m-input" id="m-correo" placeholder="ejemplo@correo.com">
      </div>
      <div class="m-grid m-grid-2">
        <div class="m-group">
          <label class="m-label">Nueva Contraseña</label>
          <div class="input-wrap">
            <input type="password" class="m-input" id="m-password" placeholder="Dejar vacío para no cambiar">
            <button type="button" class="pwd-btn" onclick="togglePwd('m-password',this)"><i class="fas fa-eye"></i></button>
          </div>
          <span class="m-hint">Mín. 8 caracteres. Vacío = sin cambio.</span>
        </div>
        <div class="m-group">
          <label class="m-label">Confirmar Contraseña</label>
          <div class="input-wrap">
            <input type="password" class="m-input" id="m-password2" placeholder="Repetir contraseña">
            <button type="button" class="pwd-btn" onclick="togglePwd('m-password2',this)"><i class="fas fa-eye"></i></button>
          </div>
        </div>
      </div>
    </div>

    <div class="modal-footer" style="justify-content:space-between;">
      <button class="btn-m btn-m-danger" id="m-btn-eliminar" onclick="pedirConfirmacionEliminar()">
        <i class="fas fa-trash-alt"></i> Eliminar
      </button>
      <div style="display:flex;gap:10px;">
        <button class="btn-m btn-m-outline" onclick="cerrarModal()"><i class="fas fa-times"></i> Cancelar</button>
        <button class="btn-m btn-m-primary" id="m-btn-guardar" onclick="guardarEdicion()">
          <i class="fas fa-save"></i> Guardar Cambios
        </button>
      </div>
    </div>

  </div>
</div>

<div class="confirm-overlay" id="confirm-overlay">
  <div class="confirm-box">
    <div class="confirm-icon"><i class="fas fa-trash-alt"></i></div>
    <div class="confirm-title">¿Eliminar usuario?</div>
    <div class="confirm-msg" id="confirm-msg">Esta acción no se puede deshacer. El usuario será eliminado permanentemente del sistema.</div>
    <div class="confirm-actions">
      <button class="btn-m btn-m-outline" onclick="cerrarConfirm()"><i class="fas fa-times"></i> Cancelar</button>
      <button class="btn-m btn-m-danger-solid" id="confirm-btn-ok" onclick="ejecutarEliminar()">
        <i class="fas fa-trash-alt"></i> Sí, eliminar
      </button>
    </div>
  </div>
</div>

<script>
const USUARIOS = <?php
    $out = [];
    foreach ($usuarios as $u) {
        // Separar nombres para el formulario de edición
        $nombreCompleto = trim($u['nombres'] ?? '');
        $partes = explode(' ', $nombreCompleto, 3);
        $out[] = [
            'id'         => (int)$u['id'],
            'nombres'    => $nombreCompleto,
            'nombre_pila'=> trim($partes[0] ?? ''),
            'ap_paterno' => trim($partes[1] ?? ''),
            'ap_materno' => trim($partes[2] ?? ''),
            'username'   => $u['username']  ?? '',
            'role'       => $u['rol_tabla'] ?? '', // El rol real para la API
            'table'      => $u['tabla_origen'] ?? 'usuarios', // La tabla de donde viene
            'estado'     => $u['estado']    ?? '',
            'region'     => $u['region']    ?? '',
            'zona'       => $u['zona']      ?? '',
            'telefono'   => $u['telefono']  ?? '',
            'correo'     => $u['email']     ?? '',
            'activo'     => (int)($u['activo'] ?? 1),
            'fecha'      => $u['fecha_registro'] ?? '',
        ];
    }
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
?>;

const ROLE_LABELS = {
    'coordinador_estatal':'Estatal',
    'coordinador_regional':'Regional',
    'coordinador_zona':'Zona',
    'admin':'Admin',
    'voluntario':'Voluntario',
    'simpatizante':'Simpatizante'
};
const ROLE_BADGES = {
    'coordinador_estatal':'badge-estatal',
    'coordinador_regional':'badge-regional',
    'coordinador_zona':'badge-zona'
};

const API_URL = '../../api/create_users/crud_usuarios.php';

/* ── Ordenamiento ── */
let currentSortKey='', isSortAscending=true, currentSortType='string', currentSortCol=-1;

function sortTable(key, type, colIndex) {
    if (currentSortKey === key) { isSortAscending = !isSortAscending; }
    else { currentSortKey=key; isSortAscending=true; currentSortType=type; currentSortCol=colIndex; }
    document.querySelectorAll('#table-headers th.sortable').forEach((th, i) => {
        th.classList.remove('active');
        let ic = th.querySelector('.sort-icon');
        if (ic) ic.className = 'fas fa-sort sort-icon';
        if (i === colIndex) {
            th.classList.add('active');
            if (ic) ic.className = isSortAscending ? 'fas fa-sort-up sort-icon' : 'fas fa-sort-down sort-icon';
        }
    });
    renderTabla();
}

/* ── Render tabla ── */
function renderTabla() {
    const q   = document.getElementById('buscador').value.toLowerCase();
    const rol = document.getElementById('filtro-rol').value;
    const est = document.getElementById('filtro-estado').value;

    let lista = USUARIOS.filter(u => {
        if (q && !u.nombres.toLowerCase().includes(q) && !u.username.toLowerCase().includes(q) && !u.estado.toLowerCase().includes(q)) return false;
        if (rol && u.role !== rol) return false;
        if (est && u.estado !== est) return false;
        return true;
    });

    if (currentSortKey) {
        lista.sort((a, b) => {
            let A = a[currentSortKey] || '', B = b[currentSortKey] || '';
            if (currentSortType === 'number') { A = parseFloat(A)||0; B = parseFloat(B)||0; return isSortAscending ? A-B : B-A; }
            A = String(A).toLowerCase(); B = String(B).toLowerCase();
            return isSortAscending ? (A<B?-1:A>B?1:0) : (A<B?1:A>B?-1:0);
        });
    }

    document.getElementById('count-label').textContent = lista.length + ' usuario' + (lista.length !== 1 ? 's' : '');
    const tb = document.getElementById('tabla-body');
    if (!lista.length) {
        tb.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:40px;color:var(--text-muted)"><i class="fas fa-users-slash" style="font-size:30px;opacity:.2;display:block;margin-bottom:10px;"></i>No hay usuarios.</td></tr>';
        return;
    }
    tb.innerHTML = lista.map(u => `<tr>
        <td><div style="font-weight:700">${u.nombres}</div><div style="font-size:12px;color:var(--text-muted)">${u.username ? u.username : ''}</div></td>
        <td><span class="badge ${ROLE_BADGES[u.role]||''}">${ROLE_LABELS[u.role] || u.role.replace(/_/g, ' ').toUpperCase()}</span></td>
        <td>${u.estado||'—'}</td>
        <td style="font-size:13px">${u.region||'—'}</td>
        <td style="font-size:13px">${u.zona||'—'}</td>
        <td><div style="font-size:13px">${u.telefono||'—'}</div>${u.correo ? '<div style="font-size:11px;color:var(--text-muted)">'+u.correo+'</div>' : ''}</td>
        <td style="font-size:13px">${u.fecha||'—'}</td>
        <td><button class="btn-edit" onclick='abrirModal(${JSON.stringify(u)})'><i class="fas fa-pen"></i> Editar</button></td>
        <td><span class="badge ${u.activo ? 'badge-activo' : 'badge-inactivo'}">${u.activo ? 'Activo' : 'Inactivo'}</span></td>
    </tr>`).join('');
}

/* ── Modal ── */
let activoVal = 1, editUserId = null, editUserRole = null, editUserTable = null;

async function abrirModal(u) {
    editUserId    = u.id;
    editUserRole  = u.role;
    editUserTable = u.table;
    activoVal     = u.activo;

    try {
        // Pedimos los datos frescos a la API
        const response = await fetch(`${API_URL}?action=get&id=${u.id}&table=${u.table}`);
        const data = await response.json();

        if (data.success) {
            const dbUser = data.user;
            
            document.getElementById('modal-subtitle').textContent = (ROLE_LABELS[u.role]||u.role||'') + ' · ' + (dbUser.estado||'');
            
            // Asignación inteligente: la API general puede devolver 'nombre_completo', 'nombres', o 'nombre'
            const nombreFull = dbUser.nombre_completo || dbUser.nombres || dbUser.nombre || '';
            const partes = nombreFull.split(' ');
            
            document.getElementById('m-nombres').value    = partes[0] || '';
            document.getElementById('m-ap_paterno').value = dbUser.ap_paterno || partes[1] || '';
            document.getElementById('m-ap_materno').value = dbUser.ap_materno || partes[2] || '';
            
            document.getElementById('m-telefono').value   = dbUser.telefono   || '';
            document.getElementById('m-correo').value     = dbUser.correo || dbUser.email || dbUser.username || '';
            document.getElementById('m-password').value   = '';
            document.getElementById('m-password2').value  = '';

            const sel = document.getElementById('m-estado');
            for (let i = 0; i < sel.options.length; i++) {
                if (sel.options[i].value === dbUser.estado) { sel.selectedIndex = i; break; }
            }

            setActivo(u.activo);
            ocultarAlertas();
            document.getElementById('modal-overlay').classList.add('open');
            document.body.style.overflow = 'hidden';
        } else {
            alert("No se pudo cargar la información del usuario.");
        }
    } catch (e) {
        alert("Error de conexión al cargar datos.");
    }
}

function cerrarModal() {
    document.getElementById('modal-overlay').classList.remove('open');
    document.body.style.overflow = '';
}

function closeModalOutside(e) {
    if (e.target === document.getElementById('modal-overlay')) cerrarModal();
}

function setActivo(val) {
    activoVal = val;
    document.getElementById('m-btn-activo').classList.toggle('sel', val === 1);
    document.getElementById('m-btn-inactivo').classList.toggle('sel', val === 0);
}

function togglePwd(id, btn) {
    const inp = document.getElementById(id), ic = btn.querySelector('i');
    if (inp.type === 'password') { inp.type = 'text'; ic.className = 'fas fa-eye-slash'; }
    else { inp.type = 'password'; ic.className = 'fas fa-eye'; }
}

function ocultarAlertas() {
    document.getElementById('m-alert-success').classList.remove('show');
    document.getElementById('m-alert-error').classList.remove('show');
}

function showAlertModal(type, msg) {
    ocultarAlertas();
    const el = document.getElementById('m-alert-' + type);
    document.getElementById(type === 'success' ? 'm-msg-ok' : 'm-msg-err').innerHTML = msg;
    el.classList.add('show');
    el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

async function guardarEdicion() {
    const nombres    = document.getElementById('m-nombres').value.trim();
    const ap_paterno = document.getElementById('m-ap_paterno').value.trim();
    const ap_materno = document.getElementById('m-ap_materno').value.trim();
    const telefono   = document.getElementById('m-telefono').value.trim();
    const estado     = document.getElementById('m-estado').value;
    const correo     = document.getElementById('m-correo').value.trim();
    const pwd        = document.getElementById('m-password').value;
    const pwd2       = document.getElementById('m-password2').value;

    if (!nombres || !ap_paterno || !telefono || !estado) {
        showAlertModal('error', 'Completa todos los campos obligatorios.'); return;
    }
    if (pwd && pwd.length < 8) {
        showAlertModal('error', 'La contraseña debe tener mínimo 8 caracteres.'); return;
    }
    if (pwd && pwd !== pwd2) {
        showAlertModal('error', 'Las contraseñas no coinciden.'); return;
    }

    const btn = document.getElementById('m-btn-guardar'), orig = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
    btn.disabled = true;

    // Construimos el Payload para la API central
    const payload = {
        action: 'edit',
        id: editUserId,
        table: editUserTable,
        rol: editUserRole, // Necesario para la API
        nombre_pila: nombres,
        ap_paterno: ap_paterno,
        ap_materno: ap_materno,
        telefono: telefono,
        estado: estado,
        correo: correo,
        estatus: activoVal === 1 ? 'activo' : 'inactivo',
        requiere_acceso: true
    };
    
    if(pwd) {
        payload.password = pwd;
    }

    try {
        const res = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        
        if (data.success) {
            showAlertModal('success', '¡Cambios guardados correctamente!');
            setTimeout(() => location.reload(), 1500); // Recargamos para refrescar la lista general
        } else {
            showAlertModal('error', data.error || data.message || 'No se pudieron guardar los cambios.');
        }
    } catch (e) {
        showAlertModal('error', 'Error de conexión. Intenta de nuevo.');
    } finally {
        btn.innerHTML = orig;
        btn.disabled = false;
    }
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') { cerrarConfirm(); cerrarModal(); } });

function pedirConfirmacionEliminar() {
    const u = USUARIOS.find(u => u.id === editUserId && u.table === editUserTable);
    const nombre = u ? u.nombres : 'este usuario';
    document.getElementById('confirm-msg').innerHTML =
        `Estás a punto de eliminar a <strong>${nombre}</strong>. Esta acción no se puede deshacer.`;
    document.getElementById('confirm-overlay').classList.add('open');
}

function cerrarConfirm() {
    document.getElementById('confirm-overlay').classList.remove('open');
}

async function ejecutarEliminar() {
    const btn = document.getElementById('confirm-btn-ok');
    const orig = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Eliminando...';
    btn.disabled = true;

    const payload = {
        action: 'delete',
        id: editUserId,
        table: editUserTable
    };

    try {
        const res = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        
        if (data.success) {
            cerrarConfirm();
            cerrarModal();
            setTimeout(() => location.reload(), 500); // Recargamos para refrescar la lista general
        } else {
            cerrarConfirm();
            showAlertModal('error', data.error || data.message || 'No se pudo eliminar el usuario.');
        }
    } catch (e) {
        cerrarConfirm();
        showAlertModal('error', 'Error de conexión. Intenta de nuevo.');
    } finally {
        btn.innerHTML = orig;
        btn.disabled = false;
    }
}

renderTabla();
</script>
</body>
</html>