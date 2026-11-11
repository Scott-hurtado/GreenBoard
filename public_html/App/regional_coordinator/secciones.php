<?php
if(file_exists(__DIR__.'/../../includes/bootstrap.php')){require_once __DIR__.'/../../includes/bootstrap.php';}else{session_start();}
$rol=$_SESSION['user_role']??'';$user_estado=$_SESSION['user_estado']??'';$user_region=$_SESSION['user_region']??'';
$permitidos=['coordinador_regional','regional'];
if(!in_array($rol,$permitidos)){header('Location: /public/index.php');exit();}
$region_nombre='—';$secciones=[];$zonas_region=[];$municipios_region=[];$distritos_region=[];$tipo_integracion_region='municipios';
$total_secciones=0;$total_contactos=0;$total_activaciones=0;
try{
    $dbCfg=__DIR__.'/../../includes/database.php';
    $cfg=file_exists($dbCfg)?require $dbCfg:['host'=>'localhost','database'=>'u881824383_pverde','username'=>'u881824383_proyverde','password'=>'PProyectoverde8#','charset'=>'utf8mb4','options'=>[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]];
    $dsn="mysql:host={$cfg['host']};dbname={$cfg['database']};charset={$cfg['charset']}";
    $pdo=new PDO($dsn,$cfg['username'],$cfg['password'],$cfg['options']??[]);
    $user_id=$_SESSION['user_id']??0;
    // Obtener región asignada al coordinador desde regiones_estatales (incluye municipios/distritos asignados)
    $st=$pdo->prepare("SELECT id, nombre, municipios, tipo_integracion FROM regiones_estatales WHERE coordinador_id=? AND estado=? LIMIT 1");
    $st->execute([$user_id,$user_estado]);
    $region_row=$st->fetch();
    if($region_row){
        $region_nombre=$region_row['nombre'];
        $region_id=(int)$region_row['id'];
        $municipios_region=json_decode($region_row['municipios']??'[]',true)?:[];
        $tipo_integracion_region=$region_row['tipo_integracion']?:'municipios';
        if($tipo_integracion_region==='distrito_local'||$tipo_integracion_region==='distrito_federal'){
            sort($municipios_region,SORT_NUMERIC);
        }else{
            sort($municipios_region);
        }

        // Zonas de esa región (solo para poder indicar a qué zona pertenece cada sección, si aplica)
        $st=$pdo->prepare("SELECT id, nombre, secciones FROM zonas_estatales WHERE estructura_id=? ORDER BY nombre");
        $st->execute([$region_id]);
        $zonas_region=$st->fetchAll();
        $sec_to_zona=[];
        foreach($zonas_region as $z){
            $nums=json_decode($z['secciones']??'[]',true)?:[];
            foreach($nums as $n){ $sec_to_zona[$n]=$z['nombre']; }
        }

        // Secciones: TODAS las secciones de los municipios/distritos asignados a la región
        // (antes solo se mostraban las secciones ya asignadas a alguna zona; ahora se
        //  muestra el universo completo de secciones del/los municipio(s)/distrito(s) de la región,
        //  estén o no ya repartidas en zonas)
        if($municipios_region){
            $inMun=implode(',',array_fill(0,count($municipios_region),'?'));
            $params=array_merge([$user_estado],$municipios_region);
            if($tipo_integracion_region==='distrito_local'){
                $filtroColSec='s.distrito_local';
            }elseif($tipo_integracion_region==='distrito_federal'){
                $filtroColSec='s.distrito_federal';
            }else{
                $filtroColSec='m.nombre';
            }
            $st=$pdo->prepare("
                SELECT
                    se.id,
                    s.seccion              AS nombre_seccion,
                    m.nombre                AS municipio,
                    s.distrito_local       AS distrito_local,
                    s.distrito_federal     AS distrito_federal,
                    COALESCE(se.lista_nominal,          0) AS lista_nominal,
                    COALESCE(se.meta_contactos,         0) AS meta_contactos,
                    COALESCE(se.contactos_conseguidos,  0) AS contactos_conseguidos,
                    COALESCE(se.eventos_realizados,     0) AS eventos_realizados,
                    COALESCE(se.metas_votos,            0) AS metas_votos,
                    COALESCE(se.activaciones_totales,   0) AS activaciones_totales
                FROM secciones s
                LEFT JOIN municipios m
                       ON m.id = s.municipio_id
                LEFT JOIN secciones_electorales se
                       ON CAST(se.nombre_seccion AS UNSIGNED) = CAST(s.seccion AS UNSIGNED)
                      AND UPPER(TRIM(se.estado)) = UPPER(TRIM(s.estado_nombre))
                WHERE s.estado_nombre=? AND $filtroColSec IN ($inMun)
                ORDER BY CAST(s.seccion AS UNSIGNED)
            ");
            $st->execute($params);
            foreach($st->fetchAll() as $row){
                $row['zona_nombre']=$sec_to_zona[$row['nombre_seccion']]??'—';
                if($tipo_integracion_region==='distrito_local'){
                    $row['entidad']=$row['distrito_local'];
                }elseif($tipo_integracion_region==='distrito_federal'){
                    $row['entidad']=$row['distrito_federal'];
                }else{
                    $row['entidad']=$row['municipio'];
                }
                $secciones[]=$row;
                // Acumular totales para tarjetas
                $total_contactos  +=(int)$row['contactos_conseguidos'];
                $total_activaciones+=(int)$row['activaciones_totales'];
            }
            $total_secciones=count($secciones);
            // Distritos locales únicos para filtro (independiente del tipo de integración de la región)
            $distritos_region=array_values(array_unique(array_filter(array_column($secciones,'distrito_local'),fn($d)=>$d!==null&&$d!=='')));
            sort($distritos_region,SORT_NUMERIC);
        }
    }
}catch(PDOException $e){$error_db=$e->getMessage();}
?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Secciones - Región <?=htmlspecialchars($region_nombre)?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root{--primary:#2d7a3e;--primary-dark:#1f5a2d;--dark:#1a1a1a;--light:#f0f7f0;--white:#fff;--gray-100:#f8f9fa;--gray-200:#e9ecef;--text-muted:#6c757d;--shadow-sm:0 10px 30px rgba(0,0,0,.05);}
*{margin:0;padding:0;box-sizing:border-box;}body{font-family:Arial,sans-serif;background:var(--light);color:var(--dark);overflow-x:hidden;}
.container{max-width:100%;background:var(--white);min-height:100vh;padding-left:330px;padding-right:50px;padding-bottom:60px;transition:padding-left .3s;}
.design-text{position:fixed;left:290px;top:50%;writing-mode:vertical-rl;font-size:13px;font-weight:500;color:var(--primary);opacity:.3;letter-spacing:2px;z-index:0;pointer-events:none;}
.page-header{padding:50px 0 30px;display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:20px;position:relative;z-index:2;}
.ai-badge{color:var(--primary);font-size:13px;font-weight:800;letter-spacing:4px;margin-bottom:15px;display:inline-block;text-transform:uppercase;background:rgba(45,122,62,.1);padding:5px 15px;border-radius:20px;}
.main-title{font-size:54px;font-weight:900;line-height:1;margin-bottom:15px;}.description{color:#666;font-size:16px;line-height:1.6;max-width:600px;}

/* ── STAT CARDS ── */
.stats-row{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;margin-bottom:30px;}
.stat-card{background:var(--white);border:1px solid var(--gray-200);border-radius:20px;padding:25px 30px;box-shadow:var(--shadow-sm);display:flex;align-items:center;gap:18px;}
.stat-icon{width:52px;height:52px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;}
.stat-icon.verde{background:rgba(45,122,62,.1);color:var(--primary);}
.stat-icon.amarillo{background:#fffbeb;color:#d97706;}
.stat-icon.azul{background:#eff6ff;color:#2196f3;}
.stat-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);margin-bottom:5px;}
.stat-val{font-size:30px;font-weight:900;color:var(--dark);line-height:1;}

/* ── FILTERS ── */
.filters-bar{background:var(--white);border:1px solid var(--gray-200);border-radius:16px;padding:20px;margin-bottom:30px;display:flex;gap:15px;align-items:flex-end;box-shadow:var(--shadow-sm);flex-wrap:wrap;}
.filter-group{display:flex;flex-direction:column;gap:8px;flex:1;min-width:150px;}
.form-label{font-size:12px;font-weight:700;text-transform:uppercase;color:var(--text-muted);letter-spacing:.5px;}
.form-input-styled{width:100%;padding:12px 15px;border-radius:10px;border:2px solid var(--gray-200);background:var(--gray-100);font-size:14px;font-weight:600;color:var(--dark);transition:all .3s;outline:none;appearance:none;font-family:Arial,sans-serif;}
.form-input-styled:focus{border-color:var(--primary);background:var(--white);}
.btn-secondary{background:var(--white);color:var(--primary);border:2px solid var(--primary);padding:10px 20px;border-radius:12px;font-weight:700;cursor:pointer;transition:all .3s;display:flex;align-items:center;gap:8px;font-size:14px;align-self:flex-end;}
.btn-secondary:hover{background:var(--light);}

/* ── TABLE ── */
.table-section{background:var(--white);border-radius:20px;padding:30px;border:1px solid var(--gray-200);box-shadow:var(--shadow-sm);}
.section-header{margin-bottom:25px;display:flex;justify-content:space-between;align-items:center;}
.section-title{font-size:20px;font-weight:800;}
.modern-table{width:100%;border-collapse:separate;border-spacing:0;}
.modern-table th{text-align:left;padding:12px 16px;color:var(--text-muted);font-size:11px;text-transform:uppercase;letter-spacing:1px;border-bottom:2px solid var(--gray-200);cursor:pointer;user-select:none;white-space:nowrap;}
.modern-table th:hover{color:var(--primary);}.sortable.active{color:var(--primary);font-weight:800;}.sort-icon{margin-left:4px;font-size:12px;opacity:.3;}
.modern-table td{padding:14px 16px;border-bottom:1px solid var(--gray-200);font-size:13px;font-weight:500;vertical-align:middle;}
.modern-table tr:hover td{background:var(--gray-100);}.modern-table tr:last-child td{border-bottom:none;}
.bar-mini{background:#eee;border-radius:6px;height:6px;overflow:hidden;display:inline-block;width:60px;vertical-align:middle;margin-left:6px;}
.bar-mini-fill{height:100%;background:var(--primary);border-radius:6px;}
.empty-row td{text-align:center;padding:60px;color:var(--text-muted);}

/* ── DETALLE BUTTON ── */
.btn-detalle{background:rgba(45,122,62,.1);color:var(--primary);border:none;padding:8px 18px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;transition:all .2s;white-space:nowrap;}
.btn-detalle:hover{background:var(--primary);color:white;}

/* ── MODAL ── */
.modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(255,255,255,.9);backdrop-filter:blur(5px);z-index:2000;align-items:center;justify-content:center;opacity:0;transition:opacity .3s ease;}
.modal.open{display:flex;opacity:1;}
.modal-content{background:var(--white);border-radius:20px;border:1px solid var(--gray-200);box-shadow:0 25px 50px rgba(0,0,0,.1);width:90%;max-width:680px;padding:40px;position:relative;transform:translateY(30px);opacity:0;transition:all .4s ease-out;max-height:90vh;overflow-y:auto;}
.modal.open .modal-content{transform:translateY(0);opacity:1;}
.modal-close{position:absolute;top:20px;right:20px;background:var(--gray-100);border:none;cursor:pointer;width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:16px;color:var(--text-muted);transition:all .2s;}
.modal-close:hover{background:#ffeaea;color:#d32f2f;}
.modal-title{font-size:26px;font-weight:900;margin-bottom:6px;}
.modal-subtitle{font-size:13px;color:var(--text-muted);margin-bottom:30px;}
.det-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:25px;}
.det-item label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);display:block;margin-bottom:4px;}
.det-item span{font-size:15px;font-weight:700;color:var(--dark);}
.det-section-title{font-size:13px;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:var(--primary);margin:20px 0 12px;padding-bottom:6px;border-bottom:2px solid var(--light);}
.modal-progress-bg{height:12px;border-radius:6px;background:var(--gray-200);overflow:hidden;}
.modal-progress-fill{height:100%;border-radius:6px;background:linear-gradient(90deg,var(--primary),#4caf50);transition:width .6s ease;}
.modal-progress-label{display:flex;justify-content:space-between;font-size:12px;font-weight:700;color:var(--text-muted);margin-top:4px;}
.act-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;}
.act-stat-card{background:var(--gray-100);border-radius:12px;padding:16px;text-align:center;}
.act-stat-num{font-size:28px;font-weight:900;color:var(--primary);}
.act-stat-lbl{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);margin-top:2px;}

@media(max-width:1024px){.container{padding-left:20px;padding-right:20px;}.design-text{display:none;}}
@media(max-width:768px){.stats-row{grid-template-columns:1fr;}.det-grid{grid-template-columns:1fr;}.act-stats{grid-template-columns:1fr 1fr;}}
</style></head><body>
<?php if(file_exists(__DIR__.'/../../includes/components/sidebar_regional.php'))include __DIR__.'/../../includes/components/sidebar_regional.php';?>
<div class="design-text">SECCIONES</div>
<div class="container">
  <div class="page-header">
    <div>
      <div class="ai-badge">REGIÓN <?=htmlspecialchars(strtoupper($region_nombre))?></div>
      <h1 class="main-title">Secciones</h1>
      <p class="description">Gestión y seguimiento por sección electoral de tu región en <?=htmlspecialchars($user_estado)?>.</p>
    </div>
  </div>

  <?php if(isset($error_db)):?>
  <div style="background:#fee2e2;color:#991b1b;padding:14px;border-radius:12px;margin-bottom:20px"><i class="fas fa-exclamation-triangle"></i> <?=htmlspecialchars($error_db)?></div>
  <?php endif;?>

  <!-- TARJETAS DE RESUMEN -->
  <div class="stats-row">
    <div class="stat-card">
      <div class="stat-icon verde"><i class="fas fa-layer-group"></i></div>
      <div>
        <div class="stat-label">Secciones Totales</div>
        <div class="stat-val"><?=number_format($total_secciones)?></div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon amarillo"><i class="fas fa-users"></i></div>
      <div>
        <div class="stat-label">Contactos Conseguidos</div>
        <div class="stat-val"><?=number_format($total_contactos)?></div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon azul"><i class="fas fa-bolt"></i></div>
      <div>
        <div class="stat-label">Activaciones Totales</div>
        <div class="stat-val"><?=number_format($total_activaciones)?></div>
      </div>
    </div>
  </div>

  <!-- FILTROS -->
  <div class="filters-bar">
    <div class="filter-group" style="flex:1;min-width:200px">
      <label class="form-label" id="lbl-filtro-municipio">
        <?php
          if($tipo_integracion_region==='distrito_local') echo 'Distrito Local';
          elseif($tipo_integracion_region==='distrito_federal') echo 'Distrito Federal';
          else echo 'Municipio';
        ?>
      </label>
      <select id="filtro-municipio" class="form-input-styled" onchange="renderTabla()">
        <option value="">
          <?php
            if($tipo_integracion_region==='distrito_local') echo 'Todos los distritos locales';
            elseif($tipo_integracion_region==='distrito_federal') echo 'Todos los distritos federales';
            else echo 'Todos los municipios';
          ?>
        </option>
        <?php foreach($municipios_region as $m):?><option><?=htmlspecialchars($m)?></option><?php endforeach;?>
      </select>
    </div>
    <div class="filter-group" style="flex:1;min-width:200px">
      <label class="form-label">Zona</label>
      <select id="filtro-zona" class="form-input-styled" onchange="renderTabla()">
        <option value="">Todas</option>
        <?php foreach($zonas_region as $z):?><option><?=htmlspecialchars($z['nombre'])?></option><?php endforeach;?>
      </select>
    </div>
    <div class="filter-group" style="flex:1;min-width:200px">
      <label class="form-label">Distrito Local</label>
      <select id="filtro-distrito" class="form-input-styled" onchange="renderTabla()">
        <option value="">Todos los distritos</option>
        <?php foreach($distritos_region as $d):?><option><?=htmlspecialchars($d)?></option><?php endforeach;?>
      </select>
    </div>
    <div class="filter-group" style="flex:2;min-width:200px">
      <label class="form-label">Buscar</label>
      <div style="position:relative"><i class="fas fa-search" style="position:absolute;left:15px;top:14px;color:#999"></i>
      <input type="text" id="buscador" class="form-input-styled" style="padding-left:40px" placeholder="Nombre de sección..." oninput="renderTabla()"></div>
    </div>
    <button class="btn-secondary" onclick="limpiarFiltros()"><i class="fas fa-times"></i> Limpiar</button>
  </div>

  <!-- TABLA -->
  <div class="table-section">
    <div class="section-header">
      <div class="section-title">Tabla de Secciones</div>
      <div id="count-label" style="font-size:13px;color:var(--text-muted);font-weight:600;"></div>
    </div>
    <div style="overflow-x:auto;">
      <table class="modern-table">
        <thead id="tbl-head"><tr>
          <th class="sortable" onclick="sortTable('nombre_seccion','number',0)">Nombre de la Sección <i class="fas fa-sort sort-icon"></i></th>
          <th class="sortable" onclick="sortTable('lista_nominal','number',1)">Lista Nominal <i class="fas fa-sort sort-icon"></i></th>
          <th class="sortable" onclick="sortTable('meta_contactos','number',2)">Meta de Contactos <i class="fas fa-sort sort-icon"></i></th>
          <th class="sortable" onclick="sortTable('contactos_conseguidos','number',3)">Contactos Conseguidos <i class="fas fa-sort sort-icon"></i></th>
          <th>Barra de Avance</th>
          <th class="sortable" onclick="sortTable('eventos_realizados','number',5)">Eventos Realizados <i class="fas fa-sort sort-icon"></i></th>
          <th class="sortable" onclick="sortTable('metas_votos','number',6)">Metas de Votos <i class="fas fa-sort sort-icon"></i></th>
          <th>Detalles</th>
        </tr></thead>
        <tbody id="tabla-body"></tbody>
      </table>
    </div>
  </div>
</div>

<!-- MODAL DETALLES -->
<div class="modal" id="modal-det">
  <div class="modal-content">
    <button class="modal-close" onclick="cerrarModal('modal-det')"><i class="fas fa-times"></i></button>
    <h2 class="modal-title" id="det-titulo">—</h2>
    <div class="modal-subtitle" id="det-ubicacion">—</div>

    <div class="det-grid">
      <div class="det-item"><label>Sección</label><span id="det-seccion">—</span></div>
      <div class="det-item"><label>Municipio</label><span id="det-municipio">—</span></div>
      <div class="det-item"><label>Zona</label><span id="det-zona">—</span></div>
      <div class="det-item"><label>Distrito Local</label><span id="det-distrito">—</span></div>
      <div class="det-item"><label>Distrito Federal</label><span id="det-distrito-fed">—</span></div>
      <div class="det-item"><label>Lista Nominal</label><span id="det-nominal">—</span></div>
    </div>

    <div class="det-section-title">Contactos</div>
    <div class="det-grid">
      <div class="det-item"><label>Meta de Contactos</label><span id="det-meta">—</span></div>
      <div class="det-item"><label>Contactos Conseguidos</label><span id="det-contactos">—</span></div>
    </div>
    <div class="modal-progress-bg" style="margin-bottom:6px"><div class="modal-progress-fill" id="det-progress-fill" style="width:0%"></div></div>
    <div class="modal-progress-label"><span id="det-progress-pct">0%</span><span id="det-progress-rem">— restantes</span></div>

    <div class="det-section-title">Actividad</div>
    <div class="act-stats">
      <div class="act-stat-card"><div class="act-stat-num" id="det-eventos">0</div><div class="act-stat-lbl">Eventos Realizados</div></div>
      <div class="act-stat-card"><div class="act-stat-num" id="det-votos">0</div><div class="act-stat-lbl">Metas de Votos</div></div>
      <div class="act-stat-card"><div class="act-stat-num" id="det-activaciones">0</div><div class="act-stat-lbl">Activaciones Totales</div></div>
    </div>
  </div>
</div>

<script>
const SECCIONES=<?php
    $out=[];
    foreach($secciones as $s){
        $out[]=[
            'id'                   =>(int)($s['id']??0),
            'nombre_seccion'       =>$s['nombre_seccion']??'',
            'zona_nombre'          =>$s['zona_nombre']??'—',
            'municipio'            =>$s['municipio']??'',
            'distrito_local'       =>$s['distrito_local']??'',
            'distrito_federal'     =>$s['distrito_federal']??'',
            'entidad'              =>$s['entidad']??'',
            'lista_nominal'        =>(int)($s['lista_nominal']??0),
            'meta_contactos'       =>(int)($s['meta_contactos']??0),
            'contactos_conseguidos'=>(int)($s['contactos_conseguidos']??0),
            'eventos_realizados'   =>(int)($s['eventos_realizados']??0),
            'metas_votos'          =>(int)($s['metas_votos']??0),
            'activaciones_totales' =>(int)($s['activaciones_totales']??0),
        ];
    }
    echo json_encode($out,JSON_UNESCAPED_UNICODE);
?>;
const TIPO_INTEGRACION_REGION_SEC = <?php echo json_encode($tipo_integracion_region, JSON_UNESCAPED_UNICODE); ?>;

let sortKey='',sortAsc=true,sortType='string',sortCol=-1;

function fmt(n){ return parseInt(n||0).toLocaleString('es-MX'); }
function pct(c,m){ return m>0?Math.min(100,Math.round(c/m*100)):0; }

function limpiarFiltros(){
    document.getElementById('buscador').value='';
    document.getElementById('filtro-zona').value='';
    document.getElementById('filtro-municipio').value='';
    document.getElementById('filtro-distrito').value='';
    renderTabla();
}

function sortTable(key,type,col){
    if(sortKey===key){sortAsc=!sortAsc;}else{sortKey=key;sortAsc=true;sortType=type;sortCol=col;}
    document.querySelectorAll('#tbl-head th.sortable').forEach((th,i)=>{
        th.classList.remove('active');let ic=th.querySelector('.sort-icon');if(ic)ic.className='fas fa-sort sort-icon';
        if(i===col){th.classList.add('active');if(ic)ic.className=sortAsc?'fas fa-sort-up sort-icon':'fas fa-sort-down sort-icon';}
    });
    renderTabla();
}

function renderTabla(){
    const q=document.getElementById('buscador').value.toLowerCase();
    const fZona=document.getElementById('filtro-zona').value;
    const fMun=document.getElementById('filtro-municipio').value;
    const fDist=document.getElementById('filtro-distrito').value;
    let lista=SECCIONES.filter(s=>{
        if(q&&!s.nombre_seccion.toLowerCase().includes(q))return false;
        if(fZona&&s.zona_nombre!==fZona)return false;
        if(fMun&&s.entidad!==fMun)return false;
        if(fDist&&String(s.distrito_local)!==fDist)return false;
        return true;
    });
    if(sortKey){lista.sort((a,b)=>{let vA=a[sortKey]??'',vB=b[sortKey]??'';if(sortType==='number'){vA=parseFloat(vA)||0;vB=parseFloat(vB)||0;return sortAsc?vA-vB:vB-vA;}vA=String(vA).toLowerCase();vB=String(vB).toLowerCase();if(vA<vB)return sortAsc?-1:1;if(vA>vB)return sortAsc?1:-1;return 0;});}
    const total=lista.length;
    document.getElementById('count-label').textContent=total+' sección'+(total!==1?'(es)':'');
    const tb=document.getElementById('tabla-body');
    if(!lista.length){tb.innerHTML='<tr class="empty-row"><td colspan="8">Sin secciones.</td></tr>';return;}
    tb.innerHTML=lista.map(s=>{
        const p=pct(s.contactos_conseguidos,s.meta_contactos);
        return `<tr>
          <td><strong>${s.nombre_seccion}</strong></td>
          <td>${fmt(s.lista_nominal)}</td>
          <td>${fmt(s.meta_contactos)}</td>
          <td>${fmt(s.contactos_conseguidos)}</td>
          <td>
            <span style="font-size:12px;font-weight:700;color:var(--primary)">${p}%</span>
            <div class="bar-mini"><div class="bar-mini-fill" style="width:${p}%"></div></div>
          </td>
          <td>${fmt(s.eventos_realizados)}</td>
          <td>${fmt(s.metas_votos)}</td>
          <td><button class="btn-detalle" onclick="verDetalle(${s.id})"><i class="fas fa-eye"></i> Detalles</button></td>
        </tr>`;
    }).join('');
}

function verDetalle(id){
    const s=SECCIONES.find(x=>x.id===id);
    if(!s)return;
    document.getElementById('det-titulo').textContent='Sección '+s.nombre_seccion;
    document.getElementById('det-ubicacion').textContent=[s.entidad,s.zona_nombre].filter(Boolean).join(' · ')||'—';
    document.getElementById('det-seccion').textContent=s.nombre_seccion||'—';
    document.getElementById('det-municipio').textContent=s.municipio||'—';
    document.getElementById('det-zona').textContent=s.zona_nombre||'—';
    document.getElementById('det-distrito').textContent=s.distrito_local||'—';
    document.getElementById('det-distrito-fed').textContent=s.distrito_federal||'—';
    document.getElementById('det-nominal').textContent=fmt(s.lista_nominal);
    document.getElementById('det-meta').textContent=fmt(s.meta_contactos);
    document.getElementById('det-contactos').textContent=fmt(s.contactos_conseguidos);
    const p=pct(s.contactos_conseguidos,s.meta_contactos);
    document.getElementById('det-progress-fill').style.width=p+'%';
    document.getElementById('det-progress-pct').textContent=p+'%';
    document.getElementById('det-progress-rem').textContent=fmt(Math.max(0,s.meta_contactos-s.contactos_conseguidos))+' restantes';
    document.getElementById('det-eventos').textContent=fmt(s.eventos_realizados);
    document.getElementById('det-votos').textContent=fmt(s.metas_votos);
    document.getElementById('det-activaciones').textContent=fmt(s.activaciones_totales);
    document.getElementById('modal-det').classList.add('open');
}

function cerrarModal(id){document.getElementById(id).classList.remove('open');}
document.querySelectorAll('.modal').forEach(el=>el.addEventListener('click',e=>{if(e.target===el)cerrarModal(el.id);}));

renderTabla();
</script>
</body></html>