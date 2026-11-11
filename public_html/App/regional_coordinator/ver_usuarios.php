<?php
if (file_exists(__DIR__ . '/../../includes/bootstrap.php')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
} else { session_start(); }

$rol         = $_SESSION['user_role']   ?? '';
$user_id     = $_SESSION['user_id']     ?? 0;
$user_estado = $_SESSION['user_estado'] ?? '';
$user_region = $_SESSION['user_region'] ?? '';

$permitidos = ['coordinador_regional', 'regional'];
if (!in_array($rol, $permitidos)) { header('Location: /public/index.php'); exit(); }

$region_nombre = '—';
$usuarios = [];

try {
    $dbCfg = __DIR__ . '/../../includes/database.php';
    $cfg = file_exists($dbCfg) ? require $dbCfg : [
        'host'=>'localhost','database'=>'u881824383_pverde',
        'username'=>'u881824383_proyverde','password'=>'PProyectoverde8#',
        'charset'=>'utf8mb4',
        'options'=>[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
    ];
    $dsn = "mysql:host={$cfg['host']};dbname={$cfg['database']};charset={$cfg['charset']}";
    $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], $cfg['options'] ?? []);

    if ($user_region) {
        $st = $pdo->prepare("SELECT nombre FROM regiones_estatales WHERE id = ? LIMIT 1");
        $st->execute([$user_region]);
        $r = $st->fetch(); if ($r) $region_nombre = ucfirst($r['nombre']);
    }

    // CORRECCIÓN: Usuarios de la región (desde la tabla general y la tabla de zonas)
    $sql = "
        SELECT 
            u.id, u.nombre_completo AS nombres, u.telefono, u.correo AS email, 
            u.estado, u.region, u.zona, 
            DATE_FORMAT(u.fecha_registro,'%Y-%m-%d') AS fecha, 'usuarios' AS tabla_origen, u.rol AS rol,
            u.username, u.activo,
            gz.nombre AS zona_nombre
        FROM usuarios u
        LEFT JOIN zonas_estatales gz ON gz.id = CAST(u.zona AS UNSIGNED) AND u.zona REGEXP '^[0-9]+$'
        WHERE u.estado = ? AND u.region = ? AND u.rol IN ('zona','coordinador_zona', 'operador', 'movilizador', 'ciudadano')
        
        UNION ALL
        
        SELECT 
            cz.id, cz.nombre AS nombres, cz.telefono, cz.email, 
            cz.estado, cz.region, cz.zona, 
            DATE_FORMAT(cz.fecha_registro,'%Y-%m-%d') AS fecha, 'coordinador_zona' AS tabla_origen, 'coordinador_zona' AS rol,
            cz.email AS username, IF(cz.estatus='activo',1,0) AS activo,
            gz.nombre AS zona_nombre
        FROM coordinador_zona cz
        LEFT JOIN zonas_estatales gz ON gz.id = cz.zona
        WHERE cz.estado = ? AND cz.region = ?
        
        ORDER BY activo DESC, nombres ASC
    ";
    
    $st = $pdo->prepare($sql);
    $st->execute([$user_estado, (string)$user_region, $user_estado, (string)$user_region]);
    $usuarios = $st->fetchAll();

} catch (PDOException $e) { $error_db = $e->getMessage(); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Usuarios - Región <?= htmlspecialchars($region_nombre) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root { --primary:#2d7a3e; --primary-dark:#1f5a2d; --dark:#1a1a1a; --light:#f0f7f0; --white:#fff; --gray-100:#f8f9fa; --gray-200:#e9ecef; --text-muted:#6c757d; --shadow-sm:0 10px 30px rgba(0,0,0,0.05); }
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'Arial',sans-serif; background:var(--light); color:var(--dark); overflow-x:hidden; }
.container { max-width:100%; background:var(--white); min-height:100vh; padding-left:330px; padding-right:50px; padding-bottom:40px; transition:padding-left 0.3s; }
.design-text { position:fixed; left:290px; top:50%; writing-mode:vertical-rl; font-size:13px; font-weight:500; color:var(--primary); opacity:0.3; letter-spacing:2px; z-index:0; pointer-events:none; }
.page-header { padding:50px 0 30px; display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:20px; position:relative; z-index:2; }
.ai-badge { color:var(--primary); font-size:13px; font-weight:800; letter-spacing:4px; margin-bottom:15px; display:inline-block; text-transform:uppercase; background:rgba(45,122,62,0.1); padding:5px 15px; border-radius:20px; }
.main-title { font-size:54px; font-weight:900; line-height:1; margin-bottom:15px; }
.description { color:#666; font-size:16px; line-height:1.6; max-width:600px; }
.header-actions { display:flex; gap:12px; align-items:center; }
.btn-primary-action { background:var(--primary); color:#fff; border:none; padding:12px 25px; border-radius:12px; font-weight:700; cursor:pointer; transition:all 0.3s; display:flex; align-items:center; gap:10px; font-size:14px; box-shadow:0 4px 15px rgba(45,122,62,0.3); text-decoration:none; }
.btn-primary-action:hover { background:var(--primary-dark); transform:translateY(-2px); }
.filters-bar { background:var(--white); border:1px solid var(--gray-200); border-radius:16px; padding:20px; margin-bottom:30px; display:flex; gap:15px; align-items:flex-end; box-shadow:var(--shadow-sm); flex-wrap:wrap; }
.filter-group { display:flex; flex-direction:column; gap:8px; flex:1; min-width:160px; }
.form-label { font-size:12px; font-weight:700; text-transform:uppercase; color:var(--text-muted); letter-spacing:0.5px; }
.form-input-styled { width:100%; padding:12px 15px; border-radius:10px; border:2px solid var(--gray-200); background:var(--gray-100); font-size:14px; font-weight:600; color:var(--dark); transition:all 0.3s; outline:none; appearance:none; font-family:'Arial',sans-serif; }
.form-input-styled:focus { border-color:var(--primary); background:var(--white); }
.table-section { background:var(--white); border-radius:20px; padding:30px; border:1px solid var(--gray-200); box-shadow:var(--shadow-sm); }
.section-header { margin-bottom:25px; display:flex; justify-content:space-between; align-items:center; }
.section-title { font-size:20px; font-weight:800; }
.modern-table { width:100%; border-collapse:separate; border-spacing:0; }
.modern-table th { text-align:left; padding:15px 20px; color:var(--text-muted); font-size:12px; text-transform:uppercase; letter-spacing:1px; border-bottom:2px solid var(--gray-200); cursor:pointer; user-select:none; transition:color 0.2s; }
.modern-table th:hover { color:var(--primary); }
.sortable.active { color:var(--primary); font-weight:800; }
.sort-icon { display:inline-block; margin-left:6px; font-size:14px; opacity:0.3; transition:all 0.2s; }
.sortable.active .sort-icon { opacity:1; color:var(--primary); }
.modern-table td { padding:18px 20px; border-bottom:1px solid var(--gray-200); font-size:14px; color:var(--dark); font-weight:500; vertical-align:middle; transition:background 0.2s; }
.modern-table tr:hover td { background:var(--gray-100); }
.modern-table tr:last-child td { border-bottom:none; }
.badge { padding:5px 12px; border-radius:20px; font-size:11px; font-weight:700; text-transform:uppercase; display:inline-block; }
.badge-zona { background:#fff8e1; color:#f57f17; border:1px solid #ffe082; }
.badge-rol { background:#e3f2fd; color:#1565c0; border:1px solid #90caf9; }
.badge-activo { background:#dcfce7; color:#15803d; }
.badge-inactivo { background:#fee2e2; color:#b91c1c; }
.empty-row td { text-align:center; padding:60px 20px; color:var(--text-muted); font-size:14px; }

/* Menú de acciones (Dropdown) */
.action-btn { background: transparent; border: none; color: #aaa; cursor: pointer; font-size: 16px; padding: 8px; border-radius: 50%; transition: 0.2s; }
.action-btn:hover { background: var(--gray-200); color: var(--dark); }
.dropdown-menu { position: absolute; right: 0; background: white; border: 1px solid var(--gray-200); box-shadow: 0 10px 30px rgba(0,0,0,0.1); border-radius: 10px; width: 160px; opacity: 0; visibility: hidden; transform: translateY(10px); transition: 0.2s; z-index: 50; }
.dropdown-menu.show { opacity: 1; visibility: visible; transform: translateY(0); }
.dropdown-item { display: flex; align-items: center; gap: 10px; padding: 12px 15px; color: var(--dark); text-decoration: none; font-size: 13px; cursor: pointer; }
.dropdown-item:hover { background: var(--gray-100); color: var(--primary); }

@media(max-width:1024px) { .container { padding-left:20px; padding-right:20px; } .design-text { display:none; } }
</style>
</head>
<body>
<?php if (file_exists(__DIR__ . '/../../includes/components/sidebar_regional.php'))
    include __DIR__ . '/../../includes/components/sidebar_regional.php'; ?>

<div class="design-text">USUARIOS</div>
<div class="container">

  <div class="page-header">
    <div>
      <div class="ai-badge">REGIÓN <?= htmlspecialchars(strtoupper($region_nombre)) ?></div>
      <h1 class="main-title">Usuarios.</h1>
      <p class="description">Personal asignado a tu región en <?= htmlspecialchars($user_estado) ?>.</p>
    </div>
    <div class="header-actions">
      <a href="crear_usuario.php" class="btn-primary-action"><i class="fas fa-plus-circle"></i> Nuevo Usuario</a>
    </div>
  </div>

  <?php if (isset($error_db)): ?>
  <div style="background:#f8d7da;color:#721c24;padding:15px;border-radius:10px;margin-bottom:20px;"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error_db) ?></div>
  <?php endif; ?>

  <div class="filters-bar">
    <div class="filter-group" style="flex:2">
      <label class="form-label">Buscar</label>
      <div style="position:relative"><i class="fas fa-search" style="position:absolute;left:15px;top:14px;color:#999"></i>
      <input type="text" id="buscador" class="form-input-styled" style="padding-left:40px" placeholder="Nombre, usuario, zona..." oninput="renderTabla()"></div>
    </div>
    <div class="filter-group">
      <label class="form-label">Zona</label>
      <select id="filtro-zona" class="form-input-styled" onchange="renderTabla()">
        <option value="">Todas</option>
        <?php
        $zonas_vistas = [];
        foreach ($usuarios as $u) {
            $zn = $u['zona_nombre'] ?: $u['zona'] ?: '';
            if ($zn && !in_array($zn, $zonas_vistas)) {
                $zonas_vistas[] = $zn;
                echo '<option>'.htmlspecialchars($zn).'</option>';
            }
        }
        ?>
      </select>
    </div>
    <div class="filter-group">
      <label class="form-label">Estatus</label>
      <select id="filtro-activo" class="form-input-styled" onchange="renderTabla()">
        <option value="">Todos</option>
        <option value="1">Activo</option>
        <option value="0">Inactivo</option>
      </select>
    </div>
  </div>

  <div class="table-section">
    <div class="section-header">
      <div class="section-title">Directorio de la Región</div>
      <div id="count-label" style="font-size:13px;color:var(--text-muted);font-weight:600;"></div>
    </div>
    <div style="overflow-x: auto;">
        <table class="modern-table">
          <thead id="table-headers">
            <tr>
              <th class="sortable" onclick="sortTable('nombre_completo','string',0)">Nombre <i class="fas fa-sort sort-icon"></i></th>
              <th class="sortable" onclick="sortTable('rol','string',1)">Rol <i class="fas fa-sort sort-icon"></i></th>
              <th class="sortable" onclick="sortTable('zona_nombre','string',2)">Zona <i class="fas fa-sort sort-icon"></i></th>
              <th class="sortable" onclick="sortTable('telefono','string',3)">Contacto <i class="fas fa-sort sort-icon"></i></th>
              <th class="sortable" onclick="sortTable('fecha','string',4)">Registro <i class="fas fa-sort sort-icon"></i></th>
              <th class="sortable" onclick="sortTable('activo','number',5)">Estatus <i class="fas fa-sort sort-icon"></i></th>
            </tr>
          </thead>
          <tbody id="tabla-body"></tbody>
        </table>
    </div>
  </div>
</div>

<script>
const USUARIOS = <?php
    $out = [];
    foreach ($usuarios as $u) {
        $out[] = [
            'id'             => (int)$u['id'],
            'nombre_completo'=> $u['nombres'] ?? '',
            'username'       => $u['username'] ?? '',
            'rol'            => $u['rol'] ?? '',
            'table'          => $u['tabla_origen'] ?? 'usuarios',
            'zona_nombre'    => $u['zona_nombre'] ?: ($u['zona'] ?? ''),
            'telefono'       => $u['telefono'] ?? '',
            'correo'         => $u['email'] ?? '',
            'activo'         => (int)($u['activo'] ?? 1),
            'fecha'          => $u['fecha'] ?? '',
        ];
    }
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
?>;

const ROLE_LABELS = {
    'coordinador_zona':'Coord. Zona',
    'zona':'Coord. Zona',
    'operador':'Operador',
    'movilizador':'Movilizador',
    'ciudadano':'Ciudadano Afín'
};

let sortKey = '', sortAsc = true, sortType = 'string', sortCol = -1;

function sortTable(key, type, col) {
    if (sortKey === key) { sortAsc = !sortAsc; } else { sortKey = key; sortAsc = true; sortType = type; sortCol = col; }
    document.querySelectorAll('#table-headers th.sortable').forEach((th, i) => {
        th.classList.remove('active');
        let ic = th.querySelector('.sort-icon'); if (ic) ic.className = 'fas fa-sort sort-icon';
        if (i === col) {
            th.classList.add('active');
            if (ic) ic.className = sortAsc ? 'fas fa-sort-up sort-icon' : 'fas fa-sort-down sort-icon';
        }
    });
    renderTabla();
}

function renderTabla() {
    const q      = document.getElementById('buscador').value.toLowerCase();
    const fZona  = document.getElementById('filtro-zona').value;
    const fActivo= document.getElementById('filtro-activo').value;

    let lista = USUARIOS.filter(u => {
        if (q && !u.nombre_completo.toLowerCase().includes(q) && !u.username.toLowerCase().includes(q) && !u.zona_nombre.toLowerCase().includes(q)) return false;
        if (fZona && u.zona_nombre !== fZona) return false;
        if (fActivo !== '' && String(u.activo) !== fActivo) return false;
        return true;
    });

    if (sortKey) {
        lista.sort((a, b) => {
            let vA = a[sortKey] ?? '', vB = b[sortKey] ?? '';
            if (sortType === 'number') { vA = parseFloat(vA)||0; vB = parseFloat(vB)||0; return sortAsc ? vA-vB : vB-vA; }
            vA = String(vA).toLowerCase(); vB = String(vB).toLowerCase();
            if (vA < vB) return sortAsc ? -1 : 1; if (vA > vB) return sortAsc ? 1 : -1; return 0;
        });
    }

    document.getElementById('count-label').textContent = lista.length + ' usuario' + (lista.length !== 1 ? 's' : '');
    const tb = document.getElementById('tabla-body');
    if (!lista.length) {
        tb.innerHTML = '<tr class="empty-row"><td colspan="6"><i class="fas fa-users-slash" style="font-size:28px;opacity:0.15;display:block;margin-bottom:10px"></i>Sin usuarios encontrados.</td></tr>';
        return;
    }
    tb.innerHTML = lista.map(u => `<tr>
        <td>
            <div style="font-weight:700">${u.nombre_completo || '—'}</div>
            <div style="font-size:12px;color:var(--text-muted)">${u.username ? u.username : ''}</div>
        </td>
        <td><span class="badge badge-rol">${ROLE_LABELS[u.rol] || u.rol.replace(/_/g, ' ').toUpperCase()}</span></td>
        <td><span class="badge badge-zona">${u.zona_nombre || '—'}</span></td>
        <td>
            <div style="font-size:13px">${u.telefono || '—'}</div>
            ${u.correo ? '<div style="font-size:11px;color:var(--text-muted)">'+u.correo+'</div>' : ''}
        </td>
        <td style="font-size:13px">${u.fecha || '—'}</td>
        <td><span class="badge ${u.activo ? 'badge-activo' : 'badge-inactivo'}">${u.activo ? 'Activo' : 'Inactivo'}</span></td>
    </tr>`).join('');
}

renderTabla();
</script>
</body>
</html>