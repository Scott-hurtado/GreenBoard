<?php
if(file_exists(__DIR__.'/../../includes/bootstrap.php')){require_once __DIR__.'/../../includes/bootstrap.php';}else{session_start();}
$rol=$_SESSION['user_role']??'';$user_estado=$_SESSION['user_estado']??'';$user_region=$_SESSION['user_region']??'';
$permitidos=['coordinador_regional','regional'];
if(!in_array($rol,$permitidos)){header('Location: /public/index.php');exit();}
$region_nombre='—';$informes=[];
try{
    $dbCfg=__DIR__.'/../../includes/database.php';
    $cfg=file_exists($dbCfg)?require $dbCfg:['host'=>'localhost','database'=>'u881824383_pverde','username'=>'u881824383_proyverde','password'=>'PProyectoverde8#','charset'=>'utf8mb4','options'=>[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]];
    $dsn="mysql:host={$cfg['host']};dbname={$cfg['database']};charset={$cfg['charset']}";
    $pdo=new PDO($dsn,$cfg['username'],$cfg['password'],$cfg['options']??[]);
    if($user_region){$st=$pdo->prepare("SELECT nombre FROM geo_regiones WHERE id=? LIMIT 1");$st->execute([$user_region]);$r=$st->fetch();if($r)$region_nombre=ucfirst($r['nombre']);}
    try{$st=$pdo->prepare("SELECT * FROM informes WHERE estado=? ORDER BY fecha_creacion DESC");$st->execute([$user_estado]);$informes=$st->fetchAll();}catch(Exception $e){}
    if(!$informes){try{$st=$pdo->query("SELECT * FROM informes ORDER BY fecha_creacion DESC");$informes=$st->fetchAll();}catch(Exception $e){}}
}catch(PDOException $e){$error_db=$e->getMessage();}
?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Informes - <?=htmlspecialchars($user_estado)?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root{--primary:#2d7a3e;--primary-dark:#1f5a2d;--accent:#e8a020;--dark:#1a1a1a;--light:#f0f7f0;--white:#fff;--gray-100:#f8f9fa;--gray-200:#e9ecef;--text-muted:#6c757d;--shadow-sm:0 10px 30px rgba(0,0,0,.05);}
*{margin:0;padding:0;box-sizing:border-box;}body{font-family:Arial,sans-serif;background:var(--light);color:var(--dark);overflow-x:hidden;}
.container{max-width:100%;background:var(--white);min-height:100vh;padding-left:330px;padding-right:50px;padding-bottom:60px;transition:padding-left .3s;}
.design-text{position:fixed;left:290px;top:50%;writing-mode:vertical-rl;font-size:13px;font-weight:500;color:var(--primary);opacity:.3;letter-spacing:2px;z-index:0;pointer-events:none;}
.page-header{padding:50px 0 30px;display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:20px;position:relative;z-index:2;}
.ai-badge{color:var(--primary);font-size:13px;font-weight:800;letter-spacing:4px;margin-bottom:15px;display:inline-block;text-transform:uppercase;background:rgba(45,122,62,.1);padding:5px 15px;border-radius:20px;}
.main-title{font-size:54px;font-weight:900;line-height:1;margin-bottom:15px;}.description{color:#666;font-size:16px;line-height:1.6;}
.informes-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:20px;}
.informe-card{background:var(--white);border:1px solid var(--gray-200);border-radius:20px;padding:26px;box-shadow:var(--shadow-sm);transition:all .3s;cursor:pointer;}
.informe-card:hover{transform:translateY(-4px);box-shadow:0 20px 40px rgba(45,122,62,.1);border-color:var(--primary);}
.informe-tipo{font-size:11px;font-weight:800;text-transform:uppercase;color:var(--primary);letter-spacing:2px;margin-bottom:10px;}
.informe-titulo{font-size:17px;font-weight:800;margin-bottom:8px;line-height:1.3;}
.informe-meta{display:flex;gap:14px;font-size:12px;color:var(--text-muted);margin-bottom:14px;flex-wrap:wrap;}
.informe-meta span{display:flex;align-items:center;gap:4px;}
.informe-preview{font-size:13px;color:#555;line-height:1.6;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;}
.badge-pub{background:#dcfce7;color:#15803d;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:700;}
.badge-bor{background:#fef3c7;color:#92400e;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:700;}
.empty-state{text-align:center;padding:80px 20px;color:var(--text-muted);}
.empty-state i{font-size:48px;opacity:.15;display:block;margin-bottom:16px;}
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:2000;display:none;align-items:center;justify-content:center;backdrop-filter:blur(4px);}
.modal-overlay.open{display:flex;}
.modal{background:var(--white);border-radius:24px;max-width:760px;width:90%;max-height:85vh;overflow-y:auto;padding:40px;position:relative;}
.modal-title{font-size:24px;font-weight:900;margin-bottom:6px;}
.modal-meta{font-size:13px;color:var(--text-muted);margin-bottom:24px;}
.modal-body{font-size:15px;line-height:1.8;color:#333;}
.modal-close{position:absolute;top:20px;right:24px;background:none;border:none;font-size:22px;cursor:pointer;color:var(--text-muted);}
@media(max-width:1024px){.container{padding-left:20px;padding-right:20px;}.design-text{display:none;}}
</style></head><body>
<?php if(file_exists(__DIR__.'/../../includes/components/sidebar_regional.php'))include __DIR__.'/../../includes/components/sidebar_regional.php';?>
<div class="design-text">INFORMES</div>
<div class="container">
  <div class="page-header">
    <div>
      <div class="ai-badge"><?=htmlspecialchars($user_estado)?></div>
      <h1 class="main-title">Informes.</h1>
      <p class="description">Reportes del estado (solo lectura).</p>
    </div>
  </div>
  <?php if(isset($error_db)):?><div style="background:#fee2e2;color:#991b1b;padding:14px;border-radius:12px;margin-bottom:20px"><i class="fas fa-exclamation-triangle"></i> <?=htmlspecialchars($error_db)?></div><?php endif;?>
  <?php if($informes):?>
  <div class="informes-grid">
    <?php foreach($informes as $inf):?>
    <div class="informe-card" onclick="abrirInforme(<?=$inf['id']?>)">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px">
        <div class="informe-tipo"><?=htmlspecialchars($inf['tipo']??'General')?></div>
        <span class="<?=in_array($inf['estado']??'',['Publicado','publicado'])?'badge-pub':'badge-bor'?>"><?=htmlspecialchars($inf['estado']??'Borrador')?></span>
      </div>
      <div class="informe-titulo"><?=htmlspecialchars($inf['titulo'])?></div>
      <div class="informe-meta">
        <span><i class="fas fa-user"></i><?=htmlspecialchars($inf['creado_por'])?></span>
        <span><i class="fas fa-calendar"></i><?=htmlspecialchars($inf['fecha_creacion'])?></span>
        <?php if($inf['periodo']):?><span><i class="fas fa-clock"></i><?=htmlspecialchars($inf['periodo'])?></span><?php endif;?>
      </div>
      <?php if($inf['contenido']):?><div class="informe-preview"><?=strip_tags($inf['contenido'])?></div><?php endif;?>
    </div>
    <?php endforeach;?>
  </div>
  <div class="modal-overlay" id="modal-overlay" onclick="if(event.target===this)cerrarModal()">
    <div class="modal">
      <button class="modal-close" onclick="cerrarModal()"><i class="fas fa-times"></i></button>
      <div id="modal-tipo" style="font-size:11px;font-weight:800;text-transform:uppercase;color:var(--primary);letter-spacing:2px;margin-bottom:8px"></div>
      <div class="modal-title" id="modal-titulo"></div>
      <div class="modal-meta" id="modal-meta"></div>
      <div class="modal-body" id="modal-body"></div>
    </div>
  </div>
  <?php else:?>
  <div class="empty-state"><i class="fas fa-file-alt"></i><p>Sin informes disponibles.</p></div>
  <?php endif;?>
</div>
<script>
const INFORMES=<?php
    $out=[];
    foreach($informes as $inf){$out[$inf['id']]=['titulo'=>$inf['titulo'],'tipo'=>$inf['tipo']??'General','creado_por'=>$inf['creado_por'],'fecha'=>$inf['fecha_creacion'],'periodo'=>$inf['periodo']??'','estado'=>$inf['estado']??'','contenido'=>$inf['contenido']??''];}
    echo json_encode($out,JSON_UNESCAPED_UNICODE);
?>;
function abrirInforme(id){const inf=INFORMES[id];if(!inf)return;document.getElementById('modal-tipo').textContent=inf.tipo;document.getElementById('modal-titulo').textContent=inf.titulo;document.getElementById('modal-meta').innerHTML='<i class="fas fa-user" style="margin-right:4px"></i>'+inf.creado_por+' &nbsp;·&nbsp; <i class="fas fa-calendar" style="margin-right:4px"></i>'+inf.fecha+(inf.periodo?' &nbsp;·&nbsp; '+inf.periodo:'');document.getElementById('modal-body').innerHTML=inf.contenido||'<em style="color:#888">Sin contenido.</em>';document.getElementById('modal-overlay').classList.add('open');}
function cerrarModal(){document.getElementById('modal-overlay').classList.remove('open');}
document.addEventListener('keydown',e=>{if(e.key==='Escape')cerrarModal();});
</script></body></html>
