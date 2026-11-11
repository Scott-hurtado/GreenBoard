<?php
/* ════════════════════════════════════════════════════════════════
   global/gestion.php — Plan de Gestión Unificado 5 niveles

   Niveles 1-2 (Admin / Circ):  CRUD completo, datos globales sin filtro
                                 de estado (geo_objetivos WHERE activo=1),
                                 visión/misión desde geo_configuracion.
   Nivel 3 (Estatal):           CRUD completo + diagnóstico + datos
                                 electorales + archivos, filtrado por estado.
                                 Estado obtenido desde coordinador_estatal.
   Nivel 4 (Regional):          Solo lectura, datos del estado de la región.
   Nivel 5 (Zona):              Solo lectura, datos del estado de la zona.
                                 Metas y cronograma filtrados por zona_id.
════════════════════════════════════════════════════════════════ */

if (file_exists(__DIR__ . '/../../includes/bootstrap.php')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
} else { session_start(); }

$rol_sesion = strtolower($_SESSION['user_role'] ?? '');
$user_id    = $_SESSION['user_id']    ?? 0;
$user_estado= trim($_SESSION['user_estado'] ?? '');
$user_region= $_SESSION['user_region']?? '';
$user_zona  = $_SESSION['user_zona_nombre'] ?? '';
$zona_id    = $_SESSION['user_zona_id'] ?? 0;

$jerarquia = [
    'admin'=>1,'administrador'=>1,'superadmin'=>1,'super admin'=>1,
    'coordinadores_circunscripcion'=>2,'coordinador_circunscripcion'=>2,'circunscripcion'=>2,
    'coordinador_estatal'=>3,
    'coordinador_regional'=>4,'regional'=>4,
    'coordinador_zona'=>5,
];
$mi_nivel = $jerarquia[$rol_sesion] ?? 6;
$puede_editar = ($mi_nivel <= 3);

$permitidos = array_keys($jerarquia);
if (!in_array($rol_sesion, $permitidos)) { header('Location: /public/index.php'); exit(); }

// ── Conexión ──────────────────────────────────────────────
$db = null;
try {
    if (class_exists('Database')) {
        $db = Database::getInstance()->getConnection();
    } else {
        $dbCfg = __DIR__ . '/../../includes/database.php';
        $cfg   = file_exists($dbCfg) ? require $dbCfg : [];
        $dsn   = "mysql:host={$cfg['host']};dbname={$cfg['database']};charset=utf8mb4";
        $db    = new PDO($dsn, $cfg['username'], $cfg['password'],
                     [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    }
} catch (Exception $e) { $error_db = $e->getMessage(); }

// ── Detectar estado y nombre usuario para nivel 3 ─────────
$nombre_usuario = $_SESSION['user_name'] ?? 'Coordinador';
$estado_actual  = $user_estado;
$region_nombre  = '—';

if ($db) {
    try {
        if ($mi_nivel === 3) {
            $st = $db->prepare("SELECT nombre, estado FROM coordinador_estatal WHERE id=? LIMIT 1");
            $st->execute([$user_id]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row) { $estado_actual = $row['estado']; $nombre_usuario = $row['nombre']; }
        }
        if ($mi_nivel === 4 && $user_region) {
            $st = $db->prepare("SELECT nombre FROM geo_regiones WHERE id=? LIMIT 1");
            $st->execute([$user_region]); $r = $st->fetch(PDO::FETCH_ASSOC);
            if ($r) $region_nombre = ucfirst($r['nombre']);
        }
        if ($mi_nivel === 5 && empty($zona_id) && $user_id > 0) {
            $st = $db->prepare("SELECT z.id, z.nombre FROM usuarios u INNER JOIN zonas_estatales z ON u.zona=z.id WHERE u.id=? LIMIT 1");
            $st->execute([$user_id]); $rz = $st->fetch(PDO::FETCH_ASSOC);
            if ($rz) { $zona_id = $rz['id']; $user_zona = $rz['nombre']; }
        }
    } catch (Exception $e) {}
}

// ── AJAX handler (niveles 1-3 solamente) ─────────────────
if ($db && $puede_editar && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $res = ['success'=>false, 'message'=>''];
    try {
        $accion = $_POST['accion'] ?? '';
        $MAP_TABLA = [
            'objetivo'=>'geo_objetivos', 'estrategia'=>'geo_estrategias',
            'meta'=>'geo_metas', 'cronograma'=>'geo_cronograma'
        ];

        if ($accion === 'crear') {
            $tipo  = $_POST['tipo']       ?? '';
            $titulo= trim($_POST['titulo']?? '');
            $desc  = trim($_POST['descripcion'] ?? '');

            if ($tipo === 'vision_mision') {
                $vis = $_POST['vision'] ?? ''; $mis = $_POST['mision'] ?? '';
                if ($mi_nivel <= 2) {
                    // Admin/Circ: actualiza geo_configuracion
                    $db->prepare("INSERT INTO geo_configuracion (clave,valor) VALUES ('vision',?) ON DUPLICATE KEY UPDATE valor=VALUES(valor)")->execute([$vis]);
                    $db->prepare("INSERT INTO geo_configuracion (clave,valor) VALUES ('mision',?) ON DUPLICATE KEY UPDATE valor=VALUES(valor)")->execute([$mis]);
                } else {
                    // Estatal: geo_vision_mision filtrado por estado
                    $db->prepare("INSERT INTO geo_vision_mision (estado,vision,mision) VALUES (?,?,?) ON DUPLICATE KEY UPDATE vision=VALUES(vision),mision=VALUES(mision)")
                       ->execute([$estado_actual,$vis,$mis]);
                }
                $res['success'] = true;
            } elseif (isset($MAP_TABLA[$tipo])) {
                $tabla = $MAP_TABLA[$tipo];
                $params = [':edo'=>$estado_actual, ':tit'=>$titulo, ':desc'=>$desc];
                $extra_cols = ''; $extra_vals = '';
                if ($tipo === 'cronograma') {
                    $extra_cols = ', fecha_visual, fecha_orden';
                    $extra_vals = ', :fvis, :ford';
                    $params[':fvis'] = $_POST['fecha_visual'] ?? '';
                    $params[':ford'] = $_POST['fecha_orden']  ?? date('Y-m-d');
                }
                // Admin/Circ (nivel 1-2): sin WHERE estado en tablas globales
                if ($mi_nivel <= 2) {
                    $sql = "INSERT INTO $tabla (titulo, descripcion, activo $extra_cols) VALUES (:tit, :desc, 1 $extra_vals)";
                    unset($params[':edo']);
                } else {
                    $sql = "INSERT INTO $tabla (estado, titulo, descripcion, activo $extra_cols) VALUES (:edo, :tit, :desc, 1 $extra_vals)";
                }
                $db->prepare($sql)->execute($params);
                $res['success'] = true;
            }
        }

        elseif ($accion === 'editar') {
            $id   = (int)$_POST['id'];
            $tipo = $_POST['tipo'] ?? '';
            $titulo = trim($_POST['titulo'] ?? '');
            $desc   = trim($_POST['descripcion'] ?? '');
            if (isset($MAP_TABLA[$tipo])) {
                $tabla  = $MAP_TABLA[$tipo];
                $params = [':tit'=>$titulo, ':desc'=>$desc, ':id'=>$id];
                $sql    = "UPDATE $tabla SET titulo=:tit, descripcion=:desc";
                if ($tipo === 'cronograma') {
                    $sql .= ', fecha_visual=:fvis, fecha_orden=:ford';
                    $params[':fvis'] = $_POST['fecha_visual'] ?? '';
                    $params[':ford'] = $_POST['fecha_orden'] ?? date('Y-m-d');
                }
                if ($mi_nivel <= 2) { $sql .= ' WHERE id=:id'; }
                else { $sql .= ' WHERE id=:id AND estado=:edo'; $params[':edo']=$estado_actual; }
                $db->prepare($sql)->execute($params);
                $res['success'] = true;
            }
        }

        elseif ($accion === 'eliminar') {
            $id   = (int)$_POST['id'];
            $tipo = $_POST['tipo'] ?? '';
            if (isset($MAP_TABLA[$tipo])) {
                $tabla = $MAP_TABLA[$tipo];
                if ($mi_nivel <= 2) $db->prepare("DELETE FROM $tabla WHERE id=?")->execute([$id]);
                else $db->prepare("DELETE FROM $tabla WHERE id=? AND estado=?")->execute([$id,$estado_actual]);
                $res['success'] = true;
            }
        }

        elseif ($accion === 'guardar_diagnostico' && $mi_nivel === 3) {
            $db->beginTransaction();
            $cleanNum = fn($v) => is_numeric(str_replace([',','$',' '],'',$v??''))?str_replace([',','$',' '],'',$v):0;

            $db->prepare("INSERT INTO geo_diagnostico (
                estado,responsable,cargo,tel,mail,contacto_otro,
                mun_totales,mun_presencia,afil_registrados,afil_activos,promo_registrados,promo_confirmados,presupuesto,
                ofi_municipal,ofi_regional,ofi_estatal,
                link_facebook,seg_facebook,link_x,seg_x,link_instagram,seg_instagram,link_otro,seg_otro,
                foda_fortalezas,foda_debilidades,foda_oportunidades,foda_amenazas,
                problema_1_titulo,problema_1_desc,kpi_1_1,kpi_1_2,kpi_1_3,
                problema_2_titulo,problema_2_desc,kpi_2_1,kpi_2_2,kpi_2_3,
                problema_3_titulo,problema_3_desc,kpi_3_1,kpi_3_2,kpi_3_3,links_adjuntos
            ) VALUES (
                :estado,:responsable,:cargo,:tel,:mail,:contacto_otro,
                :mun_totales,:mun_presencia,:afil_registrados,:afil_activos,:promo_registrados,:promo_confirmados,:presupuesto,
                :ofi_municipal,:ofi_regional,:ofi_estatal,
                :link_facebook,:seg_facebook,:link_x,:seg_x,:link_instagram,:seg_instagram,:link_otro,:seg_otro,
                :foda_fortalezas,:foda_debilidades,:foda_oportunidades,:foda_amenazas,
                :p1_tit,:p1_desc,:k1_1,:k1_2,:k1_3,
                :p2_tit,:p2_desc,:k2_1,:k2_2,:k2_3,
                :p3_tit,:p3_desc,:k3_1,:k3_2,:k3_3,:links_adjuntos
            ) ON DUPLICATE KEY UPDATE
                responsable=VALUES(responsable),cargo=VALUES(cargo),tel=VALUES(tel),mail=VALUES(mail),contacto_otro=VALUES(contacto_otro),
                mun_totales=VALUES(mun_totales),mun_presencia=VALUES(mun_presencia),
                afil_registrados=VALUES(afil_registrados),afil_activos=VALUES(afil_activos),
                promo_registrados=VALUES(promo_registrados),promo_confirmados=VALUES(promo_confirmados),presupuesto=VALUES(presupuesto),
                ofi_municipal=VALUES(ofi_municipal),ofi_regional=VALUES(ofi_regional),ofi_estatal=VALUES(ofi_estatal),
                link_facebook=VALUES(link_facebook),seg_facebook=VALUES(seg_facebook),link_x=VALUES(link_x),seg_x=VALUES(seg_x),
                link_instagram=VALUES(link_instagram),seg_instagram=VALUES(seg_instagram),link_otro=VALUES(link_otro),seg_otro=VALUES(seg_otro),
                foda_fortalezas=VALUES(foda_fortalezas),foda_debilidades=VALUES(foda_debilidades),
                foda_oportunidades=VALUES(foda_oportunidades),foda_amenazas=VALUES(foda_amenazas),
                problema_1_titulo=VALUES(problema_1_titulo),problema_1_desc=VALUES(problema_1_desc),
                kpi_1_1=VALUES(kpi_1_1),kpi_1_2=VALUES(kpi_1_2),kpi_1_3=VALUES(kpi_1_3),
                problema_2_titulo=VALUES(problema_2_titulo),problema_2_desc=VALUES(problema_2_desc),
                kpi_2_1=VALUES(kpi_2_1),kpi_2_2=VALUES(kpi_2_2),kpi_2_3=VALUES(kpi_2_3),
                problema_3_titulo=VALUES(problema_3_titulo),problema_3_desc=VALUES(problema_3_desc),
                kpi_3_1=VALUES(kpi_3_1),kpi_3_2=VALUES(kpi_3_2),kpi_3_3=VALUES(kpi_3_3),
                links_adjuntos=VALUES(links_adjuntos)
            ")->execute([
                ':estado'=>$estado_actual, ':responsable'=>$_POST['responsable']??null,
                ':cargo'=>$_POST['cargo']??null, ':tel'=>$_POST['tel']??null,
                ':mail'=>$_POST['mail']??null, ':contacto_otro'=>$_POST['contacto_otro']??null,
                ':mun_totales'=>$cleanNum($_POST['mun_totales']),
                ':mun_presencia'=>$cleanNum($_POST['mun_presencia']),
                ':afil_registrados'=>$cleanNum($_POST['afil_registrados']),
                ':afil_activos'=>$cleanNum($_POST['afil_activos']),
                ':promo_registrados'=>$cleanNum($_POST['promo_registrados']),
                ':promo_confirmados'=>$cleanNum($_POST['promo_confirmados']),
                ':presupuesto'=>$cleanNum($_POST['presupuesto']),
                ':ofi_municipal'=>$cleanNum($_POST['ofi_municipal']),
                ':ofi_regional'=>$cleanNum($_POST['ofi_regional']),
                ':ofi_estatal'=>$cleanNum($_POST['ofi_estatal']),
                ':link_facebook'=>$_POST['link_facebook']??null,
                ':seg_facebook'=>$cleanNum($_POST['seg_facebook']),
                ':link_x'=>$_POST['link_x']??null, ':seg_x'=>$cleanNum($_POST['seg_x']),
                ':link_instagram'=>$_POST['link_instagram']??null,
                ':seg_instagram'=>$cleanNum($_POST['seg_instagram']),
                ':link_otro'=>$_POST['link_otro']??null, ':seg_otro'=>$cleanNum($_POST['seg_otro']),
                ':foda_fortalezas'=>$_POST['foda_fortalezas']??null,
                ':foda_debilidades'=>$_POST['foda_debilidades']??null,
                ':foda_oportunidades'=>$_POST['foda_oportunidades']??null,
                ':foda_amenazas'=>$_POST['foda_amenazas']??null,
                ':p1_tit'=>$_POST['problema_1_titulo']??null, ':p1_desc'=>$_POST['problema_1_desc']??null,
                ':k1_1'=>$_POST['kpi_1_1']??null, ':k1_2'=>$_POST['kpi_1_2']??null, ':k1_3'=>$_POST['kpi_1_3']??null,
                ':p2_tit'=>$_POST['problema_2_titulo']??null, ':p2_desc'=>$_POST['problema_2_desc']??null,
                ':k2_1'=>$_POST['kpi_2_1']??null, ':k2_2'=>$_POST['kpi_2_2']??null, ':k2_3'=>$_POST['kpi_2_3']??null,
                ':p3_tit'=>$_POST['problema_3_titulo']??null, ':p3_desc'=>$_POST['problema_3_desc']??null,
                ':k3_1'=>$_POST['kpi_3_1']??null, ':k3_2'=>$_POST['kpi_3_2']??null, ':k3_3'=>$_POST['kpi_3_3']??null,
                ':links_adjuntos'=>$_POST['links_adjuntos']??null,
            ]);

            // geo_diag_electoral por año
            $stmtElec = $db->prepare("INSERT INTO geo_diag_electoral (
                estado,anio,lista_nominal,participacion,votos_est,porc_est,votos_fed,porc_fed,
                encuesta_fuente,encuesta_fecha,encuesta_intencion,encuesta_link
            ) VALUES (:estado,:anio,:lista_nominal,:participacion,:votos_est,:porc_est,:votos_fed,:porc_fed,
                :encuesta_fuente,:encuesta_fecha,:encuesta_intencion,:encuesta_link)
            ON DUPLICATE KEY UPDATE
                lista_nominal=VALUES(lista_nominal),participacion=VALUES(participacion),
                votos_est=VALUES(votos_est),porc_est=VALUES(porc_est),
                votos_fed=VALUES(votos_fed),porc_fed=VALUES(porc_fed),
                encuesta_fuente=VALUES(encuesta_fuente),encuesta_fecha=VALUES(encuesta_fecha),
                encuesta_intencion=VALUES(encuesta_intencion),encuesta_link=VALUES(encuesta_link)");
            foreach ([2012,2015,2018,2021,2024] as $anio) {
                $stmtElec->execute([
                    ':estado'=>$estado_actual, ':anio'=>$anio,
                    ':lista_nominal'=>$cleanNum($_POST["lista_nominal_$anio"]),
                    ':participacion'=>$_POST["participacion_$anio"]??0,
                    ':votos_est'=>$cleanNum($_POST["votos_est_$anio"]),
                    ':porc_est'=>$_POST["porc_est_$anio"]??0,
                    ':votos_fed'=>$cleanNum($_POST["votos_fed_$anio"]),
                    ':porc_fed'=>$_POST["porc_fed_$anio"]??0,
                    ':encuesta_fuente'=>$_POST["encuesta_fuente_$anio"]??null,
                    ':encuesta_fecha'=>!empty($_POST["encuesta_fecha_$anio"])?$_POST["encuesta_fecha_$anio"]:null,
                    ':encuesta_intencion'=>$_POST["encuesta_intencion_$anio"]??0,
                    ':encuesta_link'=>$_POST["encuesta_link_$anio"]??null,
                ]);
            }

            // Archivos
            if (isset($_FILES['documentos']) && !empty($_FILES['documentos']['name'][0])) {
                $uploadDir = __DIR__ . '/../../uploads/diagnostico/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                $stmtArch = $db->prepare("INSERT INTO geo_diag_archivos (estado,nombre_original,nombre_servidor,ruta_archivo) VALUES (?,?,?,?)");
                foreach ($_FILES['documentos']['name'] as $k => $name) {
                    if ($_FILES['documentos']['error'][$k] === UPLOAD_ERR_OK) {
                        $ext = pathinfo($name, PATHINFO_EXTENSION);
                        $srv = uniqid('diag_'.$estado_actual.'_').'.'.$ext;
                        $dst = $uploadDir.$srv;
                        if (move_uploaded_file($_FILES['documentos']['tmp_name'][$k], $dst))
                            $stmtArch->execute([$estado_actual,$name,$srv,'/uploads/diagnostico/'.$srv]);
                    }
                }
            }

            $db->commit();
            $res = ['success'=>true, 'message'=>'Diagnóstico guardado correctamente.'];
        }

    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $res['message'] = $e->getMessage();
    }
    echo json_encode($res); exit;
}

// ── READ: cargar datos ────────────────────────────────────
$data = [
    'vision'=>'', 'mision'=>'',
    'objetivos'=>[], 'estrategias'=>[], 'metas'=>[], 'cronograma'=>[],
    'diagnostico'=>null, 'diag_electoral'=>[], 'archivos'=>[]
];
$stats = ['total_objetivos'=>0,'total_estrategias'=>0,'total_metas'=>0,'progreso'=>0];

if ($db) {
    try {
        if ($mi_nivel <= 2) {
            // Admin / Circ: tablas globales sin filtro de estado
            $data['objetivos']   = $db->query("SELECT * FROM geo_objetivos WHERE activo=1 ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
            $data['estrategias'] = $db->query("SELECT * FROM geo_estrategias WHERE activo=1 ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
            $data['metas']       = $db->query("SELECT * FROM geo_metas WHERE activo=1 ORDER BY orden ASC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
            $data['cronograma']  = $db->query("SELECT * FROM geo_cronograma WHERE activo=1 ORDER BY fecha_orden ASC")->fetchAll(PDO::FETCH_ASSOC);
            $cfg = $db->query("SELECT clave,valor FROM geo_configuracion WHERE clave IN ('vision','mision','progreso_general')")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($cfg as $c) {
                if ($c['clave']==='vision') $data['vision']=$c['valor'];
                if ($c['clave']==='mision') $data['mision']=$c['valor'];
                if ($c['clave']==='progreso_general') $stats['progreso']=(int)$c['valor'];
            }
        } else {
            // Niveles 3-5: filtrar por estado
            $st = $db->prepare("SELECT vision,mision FROM geo_vision_mision WHERE estado=? LIMIT 1");
            $st->execute([$estado_actual]); $vm = $st->fetch(PDO::FETCH_ASSOC);
            if ($vm) { $data['vision']=$vm['vision']; $data['mision']=$vm['mision']; }

            $st = $db->prepare("SELECT * FROM geo_objetivos WHERE estado=? AND activo=1 ORDER BY id DESC");
            $st->execute([$estado_actual]); $data['objetivos']=$st->fetchAll(PDO::FETCH_ASSOC);

            $st = $db->prepare("SELECT * FROM geo_estrategias WHERE estado=? AND activo=1 ORDER BY id DESC");
            $st->execute([$estado_actual]); $data['estrategias']=$st->fetchAll(PDO::FETCH_ASSOC);

            if ($mi_nivel === 5 && $zona_id) {
                // Zona: metas y cronograma filtrados por zona
                $st = $db->prepare("SELECT * FROM geo_metas WHERE estado=? AND zona=? AND activo=1 ORDER BY id ASC");
                $st->execute([$estado_actual,$zona_id]); $data['metas']=$st->fetchAll(PDO::FETCH_ASSOC);
                $st = $db->prepare("SELECT * FROM geo_cronograma WHERE estado=? AND zona=? AND activo=1 ORDER BY fecha_orden ASC");
                $st->execute([$estado_actual,$zona_id]); $data['cronograma']=$st->fetchAll(PDO::FETCH_ASSOC);
                $st = $db->prepare("SELECT * FROM geo_diagnostico WHERE estado=? AND zona=? LIMIT 1");
                $st->execute([$estado_actual,$zona_id]); $data['diagnostico']=$st->fetch(PDO::FETCH_ASSOC);
                $st = $db->prepare("SELECT * FROM geo_diag_electoral WHERE estado=? AND zona=? ORDER BY anio ASC");
                $st->execute([$estado_actual,$zona_id]);
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $data['diag_electoral'][$r['anio']]=$r;
                $st = $db->prepare("SELECT * FROM geo_diag_archivos WHERE estado=? AND zona=? ORDER BY fecha_subida DESC");
                $st->execute([$estado_actual,$zona_id]); $data['archivos']=$st->fetchAll(PDO::FETCH_ASSOC);
            } else {
                // Nivel 3 y 4: todos los datos del estado
                $st = $db->prepare("SELECT * FROM geo_metas WHERE estado=? AND activo=1 ORDER BY orden ASC, id ASC");
                $st->execute([$estado_actual]); $data['metas']=$st->fetchAll(PDO::FETCH_ASSOC);
                $st = $db->prepare("SELECT * FROM geo_cronograma WHERE estado=? AND activo=1 ORDER BY fecha_orden ASC");
                $st->execute([$estado_actual]); $data['cronograma']=$st->fetchAll(PDO::FETCH_ASSOC);
                $st = $db->prepare("SELECT * FROM geo_diagnostico WHERE estado=? LIMIT 1");
                $st->execute([$estado_actual]); $data['diagnostico']=$st->fetch(PDO::FETCH_ASSOC);
                $st = $db->prepare("SELECT * FROM geo_diag_electoral WHERE estado=? ORDER BY anio ASC");
                $st->execute([$estado_actual]);
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $data['diag_electoral'][$r['anio']]=$r;
                $st = $db->prepare("SELECT * FROM geo_diag_archivos WHERE estado=? ORDER BY fecha_subida DESC");
                $st->execute([$estado_actual]); $data['archivos']=$st->fetchAll(PDO::FETCH_ASSOC);
            }
        }
        $stats['total_objetivos']   = count($data['objetivos']);
        $stats['total_estrategias'] = count($data['estrategias']);
        $stats['total_metas']       = count($data['metas']);
    } catch (Exception $e) { $error_db = ($error_db??'').$e->getMessage(); }
}

// Badge de scope
$scope_badge = match($mi_nivel) {
    1 => 'ADMINISTRADOR',
    2 => strtoupper($_SESSION['user_circunscripcion'] ?? 'CIRCUNSCRIPCIÓN'),
    3 => strtoupper($estado_actual),
    4 => strtoupper($estado_actual).' · REGIÓN '.strtoupper($region_nombre),
    5 => strtoupper($estado_actual).' · ZONA '.strtoupper($user_zona),
    default => 'GESTIÓN'
};
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión Estratégica — Proyecto Verde</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    :root{--primary:#2d7a3e;--primary-dark:#1f5a2d;--dark:#1a1a1a;--light:#f0f7f0;--white:#ffffff;--gray-100:#f8f9fa;--gray-200:#e9ecef;--text-muted:#6c757d;--shadow-sm:0 10px 30px rgba(0,0,0,0.05);--shadow-hover:0 20px 40px rgba(45,122,62,0.15);}
    *{margin:0;padding:0;box-sizing:border-box;}
    body{font-family:'Arial',sans-serif;background:var(--light);color:var(--dark);overflow-x:hidden;}
    .container{max-width:100%;margin:0;background:var(--white);min-height:100vh;padding-left:330px;padding-right:50px;padding-bottom:60px;transition:padding-left 0.3s ease;}
    .design-text{position:fixed;left:290px;top:50%;writing-mode:vertical-rl;font-size:13px;font-weight:500;color:var(--primary);opacity:0.3;letter-spacing:2px;z-index:0;pointer-events:none;}
    /* Header */
    .page-header{padding:50px 0 40px 0;position:relative;z-index:2;display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:20px;}
    .ai-badge{color:var(--primary);font-size:13px;font-weight:800;letter-spacing:4px;margin-bottom:15px;display:inline-block;text-transform:uppercase;background:rgba(45,122,62,0.1);padding:5px 15px;border-radius:20px;}
    .main-title{font-size:54px;font-weight:900;line-height:1;margin-bottom:15px;color:var(--dark);}
    .subtitle{color:#666;font-size:16px;line-height:1.6;max-width:600px;}
    .read-only-badge{background:rgba(255,193,7,0.12);color:#d97706;font-size:12px;font-weight:700;padding:6px 14px;border-radius:20px;display:inline-flex;align-items:center;gap:6px;}
    .coord-card{display:flex;align-items:center;gap:16px;background:var(--gray-100);border-radius:16px;padding:20px 24px;margin-bottom:30px;border:1px solid var(--gray-200);}
    .coord-avatar{width:50px;height:50px;background:var(--primary);color:white;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;}
    .coord-info h4{font-weight:800;font-size:16px;margin-bottom:3px;}
    .coord-info p{font-size:13px;color:var(--text-muted);}
    /* Stat cards */
    .grid-overview{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;margin-bottom:40px;}
    .stat-card{background:var(--white);border:1px solid var(--gray-200);padding:25px;border-radius:20px;text-align:left;box-shadow:var(--shadow-sm);position:relative;overflow:hidden;display:flex;flex-direction:column;justify-content:space-between;height:140px;transition:transform 0.3s ease;}
    .stat-card:hover{transform:translateY(-5px);border-color:var(--primary);}
    .stat-card i{position:absolute;right:20px;top:20px;font-size:40px;color:var(--gray-100);}
    .stat-card .number{font-size:42px;font-weight:900;color:var(--dark);line-height:1;}
    .stat-card .label{font-size:12px;font-weight:700;text-transform:uppercase;color:var(--text-muted);margin-top:5px;}
    /* Content cards */
    .content-card{background:var(--white);border-radius:20px;padding:30px;border:1px solid var(--gray-200);box-shadow:var(--shadow-sm);position:relative;transition:all 0.3s ease;overflow:visible;display:flex;flex-direction:column;height:100%;}
    .content-card:hover{transform:translateY(-5px);border-color:var(--primary);box-shadow:var(--shadow-hover);}
    .card-icon{width:50px;height:50px;background:var(--light);color:var(--primary);border-radius:15px;display:flex;align-items:center;justify-content:center;font-size:20px;margin-bottom:20px;}
    .card-number-watermark{position:absolute;bottom:-15px;right:10px;font-size:80px;font-weight:900;color:var(--primary);opacity:0.08;z-index:0;line-height:1;pointer-events:none;transition:all 0.3s ease;}
    .content-card:hover .card-number-watermark{opacity:0.15;transform:scale(1.1) rotate(-5deg);}
    /* Grids */
    .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:25px;}
    .grid-3{display:grid;grid-template-columns:repeat(3,1fr);gap:25px;}
    /* Tabs */
    .tabs-container{background:var(--gray-100);border-radius:16px;padding:5px;display:inline-flex;gap:5px;margin-bottom:30px;border:1px solid var(--gray-200);flex-wrap:wrap;}
    .tab-btn{padding:10px 20px;border:none;background:transparent;border-radius:12px;font-weight:600;color:#666;cursor:pointer;transition:0.3s;font-size:13px;}
    .tab-btn.active{background:var(--white);color:var(--primary);box-shadow:0 2px 5px rgba(0,0,0,0.05);}
    .tab-content{display:none;}
    .tab-content.active{display:block;}
    /* Timeline */
    .timeline-container{position:relative;padding-left:30px;border-left:2px solid var(--gray-200);margin-left:10px;}
    .timeline-item{position:relative;margin-bottom:40px;padding-left:20px;overflow:visible;}
    .timeline-dot{position:absolute;left:-36px;top:0;width:14px;height:14px;background:var(--white);border:4px solid var(--primary);border-radius:50%;}
    .timeline-date{font-size:11px;font-weight:800;color:var(--primary);background:var(--light);padding:4px 10px;border-radius:20px;display:inline-block;margin-bottom:8px;}
    /* Dropdown 3 puntos */
    .action-cell{position:absolute;top:20px;right:20px;z-index:50;}
    .timeline-item .action-cell{top:0;right:0;}
    .action-btn{background:transparent;border:none;cursor:pointer;color:#ccc;font-size:18px;padding:5px 10px;transition:0.2s;}
    .action-btn:hover,.action-btn.active{color:var(--dark);}
    .dropdown-menu{position:absolute;right:0;top:100%;background:white;border:1px solid #eee;border-radius:8px;box-shadow:0 5px 15px rgba(0,0,0,0.1);width:140px;display:none;z-index:100;padding:5px 0;}
    .dropdown-menu.show{display:block;}
    .dropdown-item{padding:10px 15px;display:flex;align-items:center;gap:10px;font-size:13px;color:#333;cursor:pointer;transition:0.2s;background:white;border:none;width:100%;text-align:left;}
    .dropdown-item:hover{background:#f8f9fa;color:var(--primary);}
    .dropdown-item.delete{color:#d32f2f;}
    .dropdown-item.delete:hover{background:#ffeaea;}
    /* Modales */
    .modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(255,255,255,0.8);backdrop-filter:blur(5px);z-index:2000;align-items:center;justify-content:center;opacity:0;transition:opacity 0.4s ease;padding:20px;}
    .modal.open{display:flex;opacity:1;}
    .modal-content{background:var(--white);border-radius:15px;border:1px solid var(--gray-200);box-shadow:0 25px 50px rgba(0,0,0,0.1);width:100%;max-width:600px;position:relative;max-height:90vh;overflow-y:auto;padding:25px;transform:translateY(20px) scale(0.95);transition:transform 0.4s cubic-bezier(0.16,1,0.3,1),opacity 0.4s ease;opacity:0;}
    .modal.open .modal-content{transform:translateY(0) scale(1);opacity:1;}
    .close-modal{position:absolute;top:20px;right:20px;font-size:20px;cursor:pointer;color:#ccc;transition:all 0.3s;background:none;border:none;}
    .close-modal:hover{color:var(--dark);transform:rotate(90deg);}
    /* Forms */
    .form-label{font-size:12px;font-weight:700;text-transform:uppercase;color:var(--text-muted);margin-bottom:8px;display:block;}
    .form-input-styled{width:100%;padding:15px;border-radius:12px;border:2px solid var(--gray-200);background:var(--gray-100);font-size:14px;font-weight:600;color:var(--dark);transition:all 0.3s;outline:none;display:block;margin-bottom:20px;font-family:inherit;}
    .form-input-styled:focus{border-color:var(--primary);background:var(--white);}
    textarea.form-input-styled{resize:vertical;min-height:90px;font-weight:400;}
    .btn-primary-action{background:var(--primary);color:white;border:none;padding:12px 25px;border-radius:12px;font-weight:700;cursor:pointer;transition:all 0.3s;display:flex;align-items:center;gap:10px;font-size:14px;box-shadow:0 4px 15px rgba(45,122,62,0.3);}
    .btn-primary-action:hover{background:var(--primary-dark);transform:translateY(-2px);}
    /* Table */
    .custom-table{width:100%;border-collapse:collapse;font-size:14px;}
    .custom-table thead tr{background:var(--primary);color:white;}
    .custom-table thead th{padding:12px 16px;text-align:left;font-weight:700;font-size:12px;letter-spacing:0.5px;}
    .custom-table thead th:first-child{border-radius:10px 0 0 0;}
    .custom-table thead th:last-child{border-radius:0 10px 0 0;}
    .custom-table tbody tr{border-bottom:1px solid var(--gray-200);}
    .custom-table tbody tr:hover{background:var(--gray-100);}
    .custom-table tbody td{padding:12px 16px;vertical-align:middle;}
    .date-badge{background:rgba(45,122,62,0.1);color:var(--primary);font-size:11px;font-weight:700;padding:3px 9px;border-radius:20px;white-space:nowrap;}
    .card-tag{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:var(--primary);background:var(--light);padding:3px 8px;border-radius:6px;display:inline-block;margin-bottom:8px;}
    /* Char counter */
    .char-counter{font-size:11px;color:var(--text-muted);text-align:right;margin-top:-15px;margin-bottom:15px;}
    .char-counter.limit-reached{color:#dc2626;font-weight:700;}
    /* Sección diagnostico */
    .diag-section{background:var(--gray-100);border-radius:14px;padding:20px;margin-bottom:20px;}
    .diag-section h4{font-size:14px;font-weight:800;color:var(--primary);margin-bottom:16px;display:flex;align-items:center;gap:8px;}
    .foda-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
    .foda-card{background:white;border-radius:10px;padding:14px;border-left:4px solid var(--primary);}
    .foda-card h5{font-size:11px;font-weight:800;text-transform:uppercase;margin-bottom:8px;}
    .foda-card h5.fortaleza{color:#16a34a;}
    .foda-card h5.debilidad{color:#dc2626;}
    .foda-card h5.oportunidad{color:#2563eb;}
    .foda-card h5.amenaza{color:#d97706;}
    /* Animaciones */
    .animate-up{animation:fadeInUp 0.6s cubic-bezier(0.16,1,0.3,1) forwards;opacity:0;transform:translateY(20px);}
    @keyframes fadeInUp{from{opacity:0;transform:translateY(20px);}to{opacity:1;transform:translateY(0);}}
    .animate-delay-1{animation-delay:0.1s;}.animate-delay-2{animation-delay:0.2s;}.animate-delay-3{animation-delay:0.3s;}
    .section-title{font-size:20px;font-weight:800;margin-bottom:20px;color:var(--dark);display:flex;align-items:center;gap:10px;}
    @media(max-width:1024px){.container{padding-left:20px;padding-right:20px;}.grid-2,.grid-3{grid-template-columns:1fr;}.design-text{display:none;}}
    </style>
</head>
<body>
<?php
$path_sb = __DIR__ . '/../../includes/components/';
if      ($mi_nivel === 1) @include $path_sb . 'sidebar_admin.php';
elseif  ($mi_nivel === 2) @include $path_sb . 'sidebar_circunscripcion.php';
elseif  ($mi_nivel === 3) @include $path_sb . 'sidebar_ce.php';
elseif  ($mi_nivel === 4) @include $path_sb . 'sidebar_regional.php';
elseif  ($mi_nivel === 5) @include $path_sb . 'sidebar_cz.php';
?>
<div class="design-text">PLANIFICACIÓN</div>

<div class="container">

    <div class="page-header animate-up">
        <div>
            <div class="ai-badge"><?php echo htmlspecialchars($scope_badge); ?></div>
            <h1 class="main-title">Modelo de<br>Gestión.</h1>
            <p class="subtitle">Planificación estratégica: Visión, Misión, Objetivos, Metas, Estrategias y Cronograma.</p>
        </div>
        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:12px;">
            <?php if (!$puede_editar): ?>
            <span class="read-only-badge"><i class="fas fa-eye"></i> Solo lectura</span>
            <?php endif; ?>
            <?php if ($puede_editar): ?>
            <button class="btn-primary-action" onclick="openModal()">
                <i class="fas fa-plus"></i> Añadir Elemento
            </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if (isset($error_db)): ?>
    <div style="background:#fee2e2;color:#991b1b;padding:14px;border-radius:10px;margin-bottom:20px;">
        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_db); ?>
    </div>
    <?php endif; ?>

    <?php if ($mi_nivel === 3): ?>
    <div class="coord-card">
        <div class="coord-avatar"><i class="fas fa-user-circle"></i></div>
        <div class="coord-info">
            <h4><?php echo htmlspecialchars($nombre_usuario); ?></h4>
            <p>Coordinador Estatal — <strong><?php echo htmlspecialchars($estado_actual); ?></strong></p>
        </div>
    </div>
    <?php elseif ($mi_nivel === 5): ?>
    <div class="coord-card">
        <div class="coord-avatar"><i class="fas fa-map-pin"></i></div>
        <div class="coord-info">
            <h4>Zona: <?php echo htmlspecialchars($user_zona); ?></h4>
            <p>Estructura Territorial — <strong><?php echo htmlspecialchars($estado_actual); ?></strong></p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Stat cards -->
    <div class="grid-overview animate-up animate-delay-1">
        <div class="stat-card">
            <i class="fas fa-bullseye"></i>
            <span class="number" data-target="<?php echo $stats['total_objetivos']; ?>">0</span>
            <span class="label">Objetivos Activos</span>
        </div>
        <div class="stat-card">
            <i class="fas fa-flag"></i>
            <span class="number" data-target="<?php echo $stats['total_metas']; ?>">0</span>
            <span class="label">Metas</span>
        </div>
        <div class="stat-card">
            <i class="fas fa-chess-knight"></i>
            <span class="number" data-target="<?php echo $stats['total_estrategias']; ?>">0</span>
            <span class="label">Estrategias</span>
        </div>
        <div class="stat-card">
            <i class="fas fa-calendar-check"></i>
            <span class="number" data-target="<?php echo count($data['cronograma']); ?>">0</span>
            <span class="label">Acciones en Cronograma</span>
        </div>
    </div>

    <!-- TABS -->
    <div class="tabs-container animate-up animate-delay-2">
        <?php
        // Tabs dinámicas por nivel
        $tabs_def = [
            ['vision',      'Visión y Misión'],
            ['objetivos',   'Objetivos'],
            ['metas',       'Metas'],
            ['estrategias', 'Estrategias'],
            ['cronograma',  'Acciones'],
        ];
        if ($mi_nivel === 3) $tabs_def[] = ['diagnostico', 'Diagnóstico'];
        if ($mi_nivel === 5) $tabs_def   = [['cronograma','Mi Agenda'],['metas','Mis Metas'],['diagnostico','Diagnóstico'],['objetivos','Alineación Estatal']];
        $first_tab = $tabs_def[0][0];
        foreach ($tabs_def as $t): ?>
        <button class="tab-btn" data-tab="<?php echo $t[0]; ?>" onclick="showTab('<?php echo $t[0]; ?>')"><?php echo $t[1]; ?></button>
        <?php endforeach; ?>
    </div>

    <!-- TAB: VISIÓN Y MISIÓN -->
    <div id="tab-vision" class="tab-content animate-up animate-delay-3">
        <h3 class="section-title"><i class="fas fa-compass"></i> Visión y Misión</h3>
        <div class="grid-2">
            <div class="content-card">
                <div class="card-icon"><i class="fas fa-eye"></i></div>
                <h4 style="font-size:20px;font-weight:800;margin-bottom:15px;">Nuestra Visión</h4>
                <p style="color:#666;font-size:15px;line-height:1.6;flex-grow:1;">
                    <?php echo !empty($data['vision']) ? nl2br(htmlspecialchars($data['vision'])) : '<em style="color:#aaa">Sin visión definida.</em>'; ?>
                </p>
            </div>
            <div class="content-card">
                <div class="card-icon"><i class="fas fa-bullseye"></i></div>
                <h4 style="font-size:20px;font-weight:800;margin-bottom:15px;">Nuestra Misión</h4>
                <p style="color:#666;font-size:15px;line-height:1.6;flex-grow:1;">
                    <?php echo !empty($data['mision']) ? nl2br(htmlspecialchars($data['mision'])) : '<em style="color:#aaa">Sin misión definida.</em>'; ?>
                </p>
            </div>
        </div>
    </div>

    <!-- TAB: OBJETIVOS -->
    <div id="tab-objetivos" class="tab-content animate-up animate-delay-3">
        <h3 class="section-title"><i class="fas fa-bullseye"></i> Objetivos Principales</h3>
        <?php if (empty($data['objetivos'])): ?>
        <p style="color:#999;font-style:italic;padding:20px 0;">No hay objetivos registrados.</p>
        <?php else: ?>
        <div class="grid-3">
            <?php $i=1; foreach ($data['objetivos'] as $obj): ?>
            <div class="content-card">
                <?php if ($puede_editar): ?>
                <div class="action-cell">
                    <button class="action-btn" onclick="toggleMenu(event,this)"><i class="fas fa-ellipsis-v"></i></button>
                    <div class="dropdown-menu">
                        <button class="dropdown-item" onclick="openEditModal({id:<?php echo $obj['id']; ?>,type:'objetivo',title:<?php echo json_encode($obj['titulo']); ?>,desc:<?php echo json_encode($obj['descripcion']); ?>})"><i class="fas fa-edit"></i> Editar</button>
                        <button class="dropdown-item delete" onclick="deleteItem(<?php echo $obj['id']; ?>,'objetivo')"><i class="fas fa-trash-alt"></i> Eliminar</button>
                    </div>
                </div>
                <?php endif; ?>
                <div class="card-icon"><i class="fas fa-flag"></i></div>
                <span class="card-tag">Objetivo #<?php echo $i; ?></span>
                <h4 style="font-weight:800;margin-bottom:8px;font-size:15px;"><?php echo htmlspecialchars($obj['titulo']); ?></h4>
                <p style="font-size:13px;color:#666;flex-grow:1;"><?php echo htmlspecialchars($obj['descripcion']); ?></p>
                <span class="card-number-watermark"><?php echo $i; ?></span>
            </div>
            <?php $i++; endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- TAB: METAS -->
    <div id="tab-metas" class="tab-content animate-up animate-delay-3">
        <h3 class="section-title"><i class="fas fa-crosshairs"></i> Metas<?php echo $mi_nivel === 5 ? ' de Zona' : ''; ?></h3>
        <?php if (empty($data['metas'])): ?>
        <p style="color:#999;font-style:italic;padding:20px 0;"><?php echo $mi_nivel === 5 ? 'Aún no tienes metas asignadas.' : 'No hay metas registradas.'; ?></p>
        <?php else: ?>
        <div class="grid-3">
            <?php $i=1; foreach ($data['metas'] as $meta): ?>
            <div class="content-card" style="border-left:4px solid var(--primary);padding:18px 20px;min-height:80px;display:flex;align-items:center;">
                <?php if ($puede_editar): ?>
                <div class="action-cell">
                    <button class="action-btn" onclick="toggleMenu(event,this)"><i class="fas fa-ellipsis-v"></i></button>
                    <div class="dropdown-menu">
                        <button class="dropdown-item" onclick="openEditModal({id:<?php echo $meta['id']; ?>,type:'meta',title:<?php echo json_encode($meta['titulo']); ?>,desc:''})"><i class="fas fa-edit"></i> Editar</button>
                        <button class="dropdown-item delete" onclick="deleteItem(<?php echo $meta['id']; ?>,'meta')"><i class="fas fa-trash-alt"></i> Eliminar</button>
                    </div>
                </div>
                <?php endif; ?>
                <p style="font-weight:600;font-size:14px;margin:0;color:#1a1a1a;line-height:1.4;width:100%;padding-right:<?php echo $puede_editar?'30px':'0'; ?>;">
                    <span style="color:var(--primary);font-weight:800;display:block;margin-bottom:5px;font-size:11px;">META #<?php echo $i; ?></span>
                    <?php echo nl2br(htmlspecialchars($meta['titulo'])); ?>
                </p>
            </div>
            <?php $i++; endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- TAB: ESTRATEGIAS -->
    <div id="tab-estrategias" class="tab-content animate-up animate-delay-3">
        <h3 class="section-title"><i class="fas fa-chess-knight"></i> Estrategias</h3>
        <?php if (empty($data['estrategias'])): ?>
        <p style="color:#999;font-style:italic;padding:20px 0;">No hay estrategias registradas.</p>
        <?php else: ?>
        <div class="grid-2">
            <?php $i=1; foreach ($data['estrategias'] as $est): ?>
            <div class="content-card">
                <?php if ($puede_editar): ?>
                <div class="action-cell">
                    <button class="action-btn" onclick="toggleMenu(event,this)"><i class="fas fa-ellipsis-v"></i></button>
                    <div class="dropdown-menu">
                        <button class="dropdown-item" onclick="openEditModal({id:<?php echo $est['id']; ?>,type:'estrategia',title:<?php echo json_encode($est['titulo']); ?>,desc:<?php echo json_encode($est['descripcion']); ?>})"><i class="fas fa-edit"></i> Editar</button>
                        <button class="dropdown-item delete" onclick="deleteItem(<?php echo $est['id']; ?>,'estrategia')"><i class="fas fa-trash-alt"></i> Eliminar</button>
                    </div>
                </div>
                <?php endif; ?>
                <div class="card-icon"><i class="fas fa-chess-knight"></i></div>
                <span class="card-tag">Estrategia #<?php echo $i; ?></span>
                <h4 style="font-weight:800;margin-bottom:8px;"><?php echo htmlspecialchars($est['titulo']); ?></h4>
                <p style="font-size:13px;color:#666;flex-grow:1;"><?php echo htmlspecialchars($est['descripcion']); ?></p>
            </div>
            <?php $i++; endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- TAB: CRONOGRAMA -->
    <div id="tab-cronograma" class="tab-content animate-up animate-delay-3">
        <h3 class="section-title"><i class="fas fa-calendar-alt"></i> Cronograma de Acciones</h3>
        <?php if (empty($data['cronograma'])): ?>
        <p style="color:#999;font-style:italic;padding:20px 0;">No hay acciones en el cronograma.</p>
        <?php else: ?>
        <div class="timeline-container">
            <?php foreach ($data['cronograma'] as $crono): ?>
            <div class="timeline-item">
                <div class="timeline-dot"></div>
                <?php if ($puede_editar): ?>
                <div class="action-cell">
                    <button class="action-btn" onclick="toggleMenu(event,this)"><i class="fas fa-ellipsis-v"></i></button>
                    <div class="dropdown-menu">
                        <button class="dropdown-item" onclick="openEditModal({id:<?php echo $crono['id']; ?>,type:'cronograma',title:<?php echo json_encode($crono['titulo']); ?>,desc:<?php echo json_encode($crono['descripcion']); ?>,date_visual:<?php echo json_encode($crono['fecha_visual']??''); ?>,date_sort:<?php echo json_encode($crono['fecha_orden']??''); ?>})"><i class="fas fa-edit"></i> Editar</button>
                        <button class="dropdown-item delete" onclick="deleteItem(<?php echo $crono['id']; ?>,'cronograma')"><i class="fas fa-trash-alt"></i> Eliminar</button>
                    </div>
                </div>
                <?php endif; ?>
                <span class="timeline-date"><?php echo htmlspecialchars($crono['fecha_visual'] ?? 'Sin fecha'); ?></span>
                <div class="content-card" style="margin-top:8px;">
                    <h4 style="font-weight:800;margin-bottom:8px;"><?php echo htmlspecialchars($crono['titulo']); ?></h4>
                    <p style="font-size:13px;color:#666;"><?php echo htmlspecialchars($crono['descripcion']); ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- TAB: DIAGNÓSTICO (solo nivel 3 con edición / 4-5 solo lectura) -->
    <?php if ($mi_nivel >= 3): ?>
    <div id="tab-diagnostico" class="tab-content animate-up animate-delay-3">
        <h3 class="section-title"><i class="fas fa-file-signature"></i> Diagnóstico Inicial</h3>

        <?php if ($mi_nivel === 3): ?>
        <form id="formDiagnostico" onsubmit="guardarDiagnostico(event)" enctype="multipart/form-data">

            <div class="diag-section">
                <h4><i class="fas fa-id-card"></i> Ficha Identificativa</h4>
                <div class="grid-3">
                    <div><label class="form-label">Entidad</label><input class="form-input-styled" value="<?php echo htmlspecialchars($estado_actual); ?>" readonly style="background:#eee;"></div>
                    <div><label class="form-label">Responsable</label><input class="form-input-styled" name="responsable" maxlength="100" value="<?php echo htmlspecialchars($data['diagnostico']['responsable']??$nombre_usuario); ?>"></div>
                    <div><label class="form-label">Cargo</label><input class="form-input-styled" name="cargo" maxlength="100" value="<?php echo htmlspecialchars($data['diagnostico']['cargo']??''); ?>"></div>
                    <div><label class="form-label">Teléfono</label><input class="form-input-styled" name="tel" maxlength="10" value="<?php echo htmlspecialchars($data['diagnostico']['tel']??''); ?>"></div>
                    <div><label class="form-label">Email</label><input type="email" class="form-input-styled" name="mail" maxlength="100" value="<?php echo htmlspecialchars($data['diagnostico']['mail']??''); ?>"></div>
                    <div><label class="form-label">Otro contacto</label><input class="form-input-styled" name="contacto_otro" maxlength="100" value="<?php echo htmlspecialchars($data['diagnostico']['contacto_otro']??''); ?>"></div>
                </div>
            </div>

            <div class="diag-section">
                <h4><i class="fas fa-chart-bar"></i> Datos Operativos</h4>
                <div class="grid-3">
                    <div><label class="form-label">Municipios totales</label><input class="form-input-styled number-format" name="mun_totales" value="<?php echo htmlspecialchars($data['diagnostico']['mun_totales']??''); ?>"></div>
                    <div><label class="form-label">Con presencia</label><input class="form-input-styled number-format" name="mun_presencia" value="<?php echo htmlspecialchars($data['diagnostico']['mun_presencia']??''); ?>"></div>
                    <div><label class="form-label">Afiliados registrados</label><input class="form-input-styled number-format" name="afil_registrados" value="<?php echo htmlspecialchars($data['diagnostico']['afil_registrados']??''); ?>"></div>
                    <div><label class="form-label">Afiliados activos</label><input class="form-input-styled number-format" name="afil_activos" value="<?php echo htmlspecialchars($data['diagnostico']['afil_activos']??''); ?>"></div>
                    <div><label class="form-label">Promotores registrados</label><input class="form-input-styled number-format" name="promo_registrados" value="<?php echo htmlspecialchars($data['diagnostico']['promo_registrados']??''); ?>"></div>
                    <div><label class="form-label">Promotores confirmados</label><input class="form-input-styled number-format" name="promo_confirmados" value="<?php echo htmlspecialchars($data['diagnostico']['promo_confirmados']??''); ?>"></div>
                    <div><label class="form-label">Presupuesto</label><input class="form-input-styled currency-format" name="presupuesto" value="<?php echo htmlspecialchars($data['diagnostico']['presupuesto']??''); ?>"></div>
                    <div><label class="form-label">Oficinas municipal</label><input class="form-input-styled number-format" name="ofi_municipal" value="<?php echo htmlspecialchars($data['diagnostico']['ofi_municipal']??''); ?>"></div>
                    <div><label class="form-label">Oficinas regional</label><input class="form-input-styled number-format" name="ofi_regional" value="<?php echo htmlspecialchars($data['diagnostico']['ofi_regional']??''); ?>"></div>
                </div>
            </div>

            <div class="diag-section">
                <h4><i class="fas fa-share-alt"></i> Redes Sociales</h4>
                <div class="grid-2">
                    <?php foreach ([
                        ['Facebook','facebook'],['X (Twitter)','x'],
                        ['Instagram','instagram'],['Otro','otro']
                    ] as [$label,$key]): ?>
                    <div style="background:white;border-radius:10px;padding:14px;">
                        <label class="form-label"><?php echo $label; ?> — Link</label>
                        <input class="form-input-styled" name="link_<?php echo $key; ?>" value="<?php echo htmlspecialchars($data['diagnostico']['link_'.$key]??''); ?>" style="margin-bottom:8px;">
                        <label class="form-label">Seguidores</label>
                        <input class="form-input-styled number-format" name="seg_<?php echo $key; ?>" value="<?php echo htmlspecialchars($data['diagnostico']['seg_'.$key]??''); ?>">
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="diag-section">
                <h4><i class="fas fa-th-large"></i> Análisis FODA</h4>
                <div class="foda-grid">
                    <?php foreach ([
                        ['fortalezas','Fortalezas','fortaleza'],
                        ['debilidades','Debilidades','debilidad'],
                        ['oportunidades','Oportunidades','oportunidad'],
                        ['amenazas','Amenazas','amenaza'],
                    ] as [$key,$label,$cls]): ?>
                    <div class="foda-card">
                        <h5 class="<?php echo $cls; ?>"><?php echo $label; ?></h5>
                        <textarea class="form-input-styled" name="foda_<?php echo $key; ?>" rows="4" maxlength="2000"><?php echo htmlspecialchars($data['diagnostico']['foda_'.$key]??''); ?></textarea>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="diag-section">
                <h4><i class="fas fa-exclamation-triangle"></i> Problemas Identificados y KPIs</h4>
                <?php for ($p=1; $p<=3; $p++): ?>
                <div style="background:white;border-radius:10px;padding:14px;margin-bottom:14px;">
                    <label class="form-label">Problema <?php echo $p; ?></label>
                    <input class="form-input-styled" name="problema_<?php echo $p; ?>_titulo" maxlength="200" placeholder="Título del problema" value="<?php echo htmlspecialchars($data['diagnostico']['problema_'.$p.'_titulo']??''); ?>">
                    <textarea class="form-input-styled" name="problema_<?php echo $p; ?>_desc" rows="2" maxlength="1000" placeholder="Descripción"><?php echo htmlspecialchars($data['diagnostico']['problema_'.$p.'_desc']??''); ?></textarea>
                    <div class="grid-3">
                        <?php for ($k=1; $k<=3; $k++): ?>
                        <div>
                            <label class="form-label">KPI <?php echo $p.'-'.$k; ?></label>
                            <input class="form-input-styled" name="kpi_<?php echo $p; ?>_<?php echo $k; ?>" maxlength="200" value="<?php echo htmlspecialchars($data['diagnostico']['kpi_'.$p.'_'.$k]??''); ?>">
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
                <?php endfor; ?>
            </div>

            <div class="diag-section">
                <h4><i class="fas fa-vote-yea"></i> Datos Electorales Históricos</h4>
                <div style="overflow-x:auto;">
                    <table class="custom-table">
                        <thead><tr>
                            <th>Año</th><th>Lista Nominal</th><th>Participación%</th>
                            <th>Votos Estatal</th><th>%Est</th>
                            <th>Votos Federal</th><th>%Fed</th>
                        </tr></thead>
                        <tbody>
                            <?php foreach ([2012,2015,2018,2021,2024] as $anio):
                                $de = $data['diag_electoral'][$anio] ?? []; ?>
                            <tr>
                                <td><strong><?php echo $anio; ?></strong></td>
                                <td><input type="text" name="lista_nominal_<?php echo $anio; ?>" class="number-format" style="width:100%;padding:6px;border:1px solid #ddd;border-radius:6px;" value="<?php echo htmlspecialchars($de['lista_nominal']??''); ?>"></td>
                                <td><input type="number" step="0.01" name="participacion_<?php echo $anio; ?>" style="width:80px;padding:6px;border:1px solid #ddd;border-radius:6px;" value="<?php echo htmlspecialchars($de['participacion']??''); ?>"></td>
                                <td><input type="text" name="votos_est_<?php echo $anio; ?>" class="number-format" style="width:100%;padding:6px;border:1px solid #ddd;border-radius:6px;" value="<?php echo htmlspecialchars($de['votos_est']??''); ?>"></td>
                                <td><input type="number" step="0.01" name="porc_est_<?php echo $anio; ?>" style="width:70px;padding:6px;border:1px solid #ddd;border-radius:6px;" value="<?php echo htmlspecialchars($de['porc_est']??''); ?>"></td>
                                <td><input type="text" name="votos_fed_<?php echo $anio; ?>" class="number-format" style="width:100%;padding:6px;border:1px solid #ddd;border-radius:6px;" value="<?php echo htmlspecialchars($de['votos_fed']??''); ?>"></td>
                                <td><input type="number" step="0.01" name="porc_fed_<?php echo $anio; ?>" style="width:70px;padding:6px;border:1px solid #ddd;border-radius:6px;" value="<?php echo htmlspecialchars($de['porc_fed']??''); ?>"></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="diag-section">
                <h4><i class="fas fa-link"></i> Links y Documentos Adjuntos</h4>
                <div id="links-container"></div>
                <button type="button" onclick="addLink()" style="background:rgba(45,122,62,0.1);color:var(--primary);border:none;padding:8px 16px;border-radius:8px;font-weight:700;cursor:pointer;margin-bottom:16px;font-size:13px;">
                    <i class="fas fa-plus"></i> Añadir enlace
                </button>
                <input type="hidden" name="links_adjuntos" id="linksHidden">
                <label class="form-label">Archivos</label>
                <input type="file" name="documentos[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.png" style="width:100%;padding:10px;border:1.5px dashed #ccc;border-radius:10px;">
                <?php if (!empty($data['archivos'])): ?>
                <div style="margin-top:12px;">
                    <?php foreach ($data['archivos'] as $arch): ?>
                    <div style="display:flex;align-items:center;gap:8px;padding:6px 0;border-bottom:1px solid #f3f4f6;font-size:13px;">
                        <i class="fas fa-paperclip" style="color:var(--primary);"></i>
                        <a href="<?php echo htmlspecialchars($arch['ruta_archivo']); ?>" target="_blank"><?php echo htmlspecialchars($arch['nombre_original']); ?></a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <div style="text-align:right;margin-top:24px;">
                <button type="submit" class="btn-primary-action" style="display:inline-flex;">
                    <i class="fas fa-save"></i> Guardar Diagnóstico
                </button>
            </div>
        </form>

        <?php else: /* Niveles 4-5: solo lectura del diagnóstico */ ?>
        <?php if (empty($data['diagnostico'])): ?>
        <p style="color:#999;font-style:italic;padding:20px 0;">El diagnóstico aún no ha sido completado.</p>
        <?php else: $d = $data['diagnostico']; ?>
        <div class="grid-2" style="margin-bottom:20px;">
            <div class="content-card">
                <h4 style="font-weight:800;margin-bottom:12px;">Ficha de Contacto</h4>
                <?php foreach ([['Responsable','responsable'],['Cargo','cargo'],['Tel','tel'],['Email','mail']] as [$lbl,$key]): ?>
                <div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid #f3f4f6;font-size:13px;">
                    <span style="color:#6b7280;font-weight:600;"><?php echo $lbl; ?></span>
                    <span><?php echo htmlspecialchars($d[$key]??'—'); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="content-card">
                <h4 style="font-weight:800;margin-bottom:12px;">Datos Operativos</h4>
                <?php foreach ([['Municipios totales','mun_totales'],['Con presencia','mun_presencia'],['Afiliados activos','afil_activos'],['Promotores','promo_confirmados']] as [$lbl,$key]): ?>
                <div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid #f3f4f6;font-size:13px;">
                    <span style="color:#6b7280;font-weight:600;"><?php echo $lbl; ?></span>
                    <span><?php echo number_format((int)($d[$key]??0)); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php if (!empty($data['diag_electoral'])): ?>
        <div class="diag-section">
            <h4><i class="fas fa-vote-yea"></i> Datos Electorales</h4>
            <div style="overflow-x:auto;">
                <table class="custom-table">
                    <thead><tr><th>Año</th><th>Lista Nominal</th><th>Participación%</th><th>Votos Est.</th><th>Votos Fed.</th></tr></thead>
                    <tbody>
                        <?php foreach ($data['diag_electoral'] as $anio => $de): ?>
                        <tr>
                            <td><strong><?php echo $anio; ?></strong></td>
                            <td><?php echo number_format((int)($de['lista_nominal']??0)); ?></td>
                            <td><?php echo number_format((float)($de['participacion']??0),2); ?>%</td>
                            <td><?php echo number_format((int)($de['votos_est']??0)); ?></td>
                            <td><?php echo number_format((int)($de['votos_fed']??0)); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div><!-- /container -->

<!-- MODAL CREAR/EDITAR (solo para puede_editar) -->
<?php if ($puede_editar): ?>
<div class="modal" id="gestionModal" onclick="if(event.target===this)closeModal()">
    <div class="modal-content">
        <button class="close-modal" onclick="closeModal()"><i class="fas fa-times"></i></button>
        <h2 id="modalTitle" style="font-size:22px;font-weight:800;margin-bottom:5px;">Crear Elemento</h2>
        <p style="font-size:13px;color:#999;margin-bottom:20px;">Gestiona la planificación estratégica.</p>
        <form id="createForm" onsubmit="handleSave(event)">
            <input type="hidden" id="itemId" name="id">
            <div>
                <label class="form-label">Tipo de elemento</label>
                <select class="form-input-styled" id="itemType" onchange="toggleFields()">
                    <option value="objetivo">Objetivo</option>
                    <option value="estrategia">Estrategia</option>
                    <option value="meta">Meta</option>
                    <option value="cronograma">Acción / Cronograma</option>
                    <option value="vision_mision">Visión y Misión</option>
                </select>
            </div>
            <!-- Campos genéricos (objetivo/estrategia/cronograma) -->
            <div id="divGenericFields">
                <label class="form-label">Título</label>
                <input type="text" class="form-input-styled" id="itemTitle" maxlength="255" placeholder="Título claro y conciso">
                <div class="char-counter" id="titleCounter">0 / 255</div>
            </div>
            <div id="divDescField">
                <label class="form-label">Descripción</label>
                <textarea class="form-input-styled" id="itemDesc" rows="3" maxlength="2000" placeholder="Detalles operativos…"></textarea>
            </div>
            <!-- Cronograma: campos de fecha -->
            <div id="divCronoFields" style="display:none;background:#f8f9fa;padding:14px;border-radius:10px;margin-bottom:16px;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                    <div>
                        <label class="form-label">Fecha real (orden)</label>
                        <input type="date" class="form-input-styled" id="itemSortDate" onchange="generateVisualLabel()">
                    </div>
                    <div>
                        <label class="form-label">Formato visual</label>
                        <select class="form-input-styled" id="dateFormat" onchange="generateVisualLabel()">
                            <option value="mes">Mensual (ENERO 2025)</option>
                            <option value="trimestre">Trimestral (1ER TRIMESTRE)</option>
                            <option value="semestre">Semestral (1ER SEMESTRE)</option>
                            <option value="anio">Anual (AÑO 2025)</option>
                            <option value="custom">Manual</option>
                        </select>
                    </div>
                </div>
                <label class="form-label">Etiqueta visual</label>
                <input type="text" class="form-input-styled" id="itemDate" placeholder="Se generará automáticamente…" readonly style="background:#e9ecef;font-weight:700;">
            </div>
            <!-- Meta: solo título -->
            <div id="divMetaFields" style="display:none;">
                <label class="form-label">Meta (texto)</label>
                <textarea class="form-input-styled" id="metaText" rows="3" maxlength="500" placeholder="Descripción de la meta…"></textarea>
            </div>
            <!-- Visión / Misión -->
            <div id="divVMFields" style="display:none;">
                <label class="form-label">Visión</label>
                <textarea class="form-input-styled" id="vmVision" name="vision" rows="3" maxlength="2000" placeholder="Nuestra visión…"></textarea>
                <label class="form-label">Misión</label>
                <textarea class="form-input-styled" id="vmMision" name="mision" rows="3" maxlength="2000" placeholder="Nuestra misión…"></textarea>
            </div>
            <button type="submit" class="btn-primary-action" style="width:100%;justify-content:center;margin-top:8px;">
                <i class="fas fa-check"></i> <span id="btnSaveLabel">Guardar</span>
            </button>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
const MI_NIVEL = <?php echo $mi_nivel; ?>;
const PUEDE_EDITAR = <?php echo $puede_editar ? 'true' : 'false'; ?>;
const currentVision = <?php echo json_encode($data['vision'] ?? ''); ?>;
const currentMision = <?php echo json_encode($data['mision'] ?? ''); ?>;

// ── Tabs ──────────────────────────────────────────────────
function showTab(name) {
    localStorage.setItem('gestionTab', name);
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    const t = document.getElementById('tab-'+name); if(t) t.classList.add('active');
    const b = document.querySelector(`.tab-btn[data-tab="${name}"]`); if(b) b.classList.add('active');
}

// ── Animación números ─────────────────────────────────────
function animateNumber(el, target) {
    if(target<=0){el.innerText=target;return;}
    let cur=0; const inc=target/30;
    const tm=setInterval(()=>{cur+=inc;if(cur>=target){el.innerText=target;clearInterval(tm);}else el.innerText=Math.ceil(cur);},30);
}

// ── Dropdown 3 puntos ─────────────────────────────────────
function toggleMenu(event, btn) {
    event.stopPropagation();
    const menu = btn.nextElementSibling;
    document.querySelectorAll('.dropdown-menu').forEach(m => { if(m!==menu) m.classList.remove('show'); });
    menu.classList.toggle('show');
}
window.addEventListener('click', () => document.querySelectorAll('.dropdown-menu').forEach(m=>m.classList.remove('show')));

// ── Modal CRUD ────────────────────────────────────────────
function openModal() {
    document.getElementById('createForm').reset();
    document.getElementById('itemId').value = '';
    document.getElementById('itemType').disabled = false;
    document.getElementById('modalTitle').textContent = 'Crear Elemento';
    document.getElementById('btnSaveLabel').textContent = 'Guardar';
    toggleFields();
    document.getElementById('gestionModal').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeModal() {
    document.getElementById('gestionModal').classList.remove('open');
    document.body.style.overflow = '';
}
document.addEventListener('keydown', e => { if(e.key==='Escape') closeModal(); });

function toggleFields() {
    const type = document.getElementById('itemType').value;
    const show = id => document.getElementById(id).style.display = 'block';
    const hide = id => document.getElementById(id).style.display = 'none';
    hide('divGenericFields'); hide('divDescField'); hide('divCronoFields');
    hide('divMetaFields'); hide('divVMFields');
    if (type==='meta') { show('divMetaFields'); }
    else if (type==='cronograma') { show('divGenericFields'); show('divDescField'); show('divCronoFields'); }
    else if (type==='vision_mision') {
        show('divVMFields');
        document.getElementById('vmVision').value = currentVision;
        document.getElementById('vmMision').value = currentMision;
    }
    else { show('divGenericFields'); show('divDescField'); }
}

function openEditModal(data) {
    document.getElementById('createForm').reset();
    document.getElementById('itemId').value = data.id || '';
    document.getElementById('itemType').value = data.type;
    document.getElementById('itemType').disabled = true;
    document.getElementById('modalTitle').textContent = 'Editar Elemento';
    document.getElementById('btnSaveLabel').textContent = 'Actualizar';
    toggleFields();
    if (data.type === 'meta') {
        document.getElementById('metaText').value = data.title || '';
    } else {
        const tEl = document.getElementById('itemTitle');
        if(tEl) tEl.value = data.title || '';
        const dEl = document.getElementById('itemDesc');
        if(dEl) dEl.value = data.desc || '';
    }
    if (data.type === 'cronograma') {
        document.getElementById('itemSortDate').value = data.date_sort || '';
        document.getElementById('itemDate').value = data.date_visual || '';
    }
    document.getElementById('gestionModal').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function generateVisualLabel() {
    const dateVal = document.getElementById('itemSortDate').value;
    const format  = document.getElementById('dateFormat').value;
    const vis     = document.getElementById('itemDate');
    if (format==='custom'){vis.readOnly=false;vis.style.background='#fff';vis.focus();return;}
    vis.readOnly=true; vis.style.background='#e9ecef';
    if (!dateVal) return;
    const d=new Date(dateVal+'T00:00:00'), yr=d.getFullYear(), mo=d.getMonth();
    const M=['ENERO','FEBRERO','MARZO','ABRIL','MAYO','JUNIO','JULIO','AGOSTO','SEPTIEMBRE','OCTUBRE','NOVIEMBRE','DICIEMBRE'];
    if(format==='mes') vis.value=`${M[mo]} ${yr}`;
    else if(format==='trimestre') vis.value=`${Math.floor(mo/3)+1}º TRIMESTRE ${yr}`;
    else if(format==='semestre') vis.value=`${mo<6?'1ER':'2DO'} SEMESTRE ${yr}`;
    else if(format==='anio') vis.value=`AÑO ${yr}`;
}

async function handleSave(e) {
    e.preventDefault();
    const id   = document.getElementById('itemId').value;
    const type = document.getElementById('itemType').value;
    const btn  = document.getElementById('createForm').querySelector('button[type="submit"]');

    const fd = new FormData();
    fd.append('accion', id ? 'editar' : 'crear');
    fd.append('tipo',   type);
    if (id) fd.append('id', id);

    if (type === 'meta') {
        const txt = document.getElementById('metaText').value.trim();
        if (!txt) { alert('El texto de la meta es obligatorio.'); return; }
        fd.append('titulo', txt); fd.append('descripcion', txt);
    } else if (type === 'vision_mision') {
        fd.append('vision', document.getElementById('vmVision').value);
        fd.append('mision', document.getElementById('vmMision').value);
    } else {
        const titulo = document.getElementById('itemTitle').value.trim();
        if (!titulo) { alert('El título es obligatorio.'); return; }
        fd.append('titulo',      titulo);
        fd.append('descripcion', document.getElementById('itemDesc').value);
        if (type === 'cronograma') {
            fd.append('fecha_visual', document.getElementById('itemDate').value);
            fd.append('fecha_orden',  document.getElementById('itemSortDate').value);
        }
    }

    btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Guardando…';
    try {
        const res = await fetch(window.location.href, {method:'POST', body:fd});
        const data = await res.json();
        if (data.success) { closeModal(); location.reload(); }
        else alert('Error: '+data.message);
    } catch(err) { alert('Error de conexión'); }
    finally { btn.disabled=false; btn.innerHTML='<i class="fas fa-check"></i> Guardar'; }
}

async function deleteItem(id, type) {
    if (!confirm('¿Eliminar este elemento permanentemente?')) return;
    const fd = new FormData();
    fd.append('accion','eliminar'); fd.append('id',id); fd.append('tipo',type);
    try {
        const res  = await fetch(window.location.href,{method:'POST',body:fd});
        const data = await res.json();
        if (data.success) location.reload();
        else alert('Error: '+data.message);
    } catch(e) { alert('Error de conexión'); }
}

// ── Diagnóstico ───────────────────────────────────────────
async function guardarDiagnostico(e) {
    e.preventDefault();
    recopilarLinks();
    const fd  = new FormData(e.target);
    fd.append('accion','guardar_diagnostico');
    const btn = e.target.querySelector('button[type="submit"]');
    btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Guardando…';
    try {
        const res  = await fetch(window.location.href,{method:'POST',body:fd});
        const data = await res.json();
        if (data.success) { alert('¡Diagnóstico guardado!'); location.reload(); }
        else alert('Error: '+data.message);
    } catch(e) { alert('Error de conexión'); }
    finally { btn.disabled=false; btn.innerHTML='<i class="fas fa-save"></i> Guardar Diagnóstico'; }
}

// Links dinámicos
const linksGuardados = <?php echo json_encode(array_filter(explode("\n", $data['diagnostico']['links_adjuntos']??''))); ?>;

function initLinks() {
    const c = document.getElementById('links-container'); if(!c) return;
    c.innerHTML='';
    (linksGuardados.length?linksGuardados:['']).forEach(link => {
        if(link.trim()) addLink(link.trim());
    });
}
function addLink(val='') {
    const c = document.getElementById('links-container'); if(!c) return;
    const wrap = document.createElement('div');
    wrap.style.cssText='display:flex;gap:8px;align-items:center;margin-bottom:8px;';
    wrap.innerHTML=`<input type="text" class="form-input-styled dynamic-link-input" placeholder="https://..." style="margin-bottom:0;flex:1;" value="${val.replace(/"/g,'&quot;')}">
                    <button type="button" onclick="this.parentElement.remove()" style="width:36px;height:36px;border-radius:8px;border:none;background:#fee2e2;color:#dc2626;cursor:pointer;font-size:15px;flex-shrink:0;"><i class="fas fa-times"></i></button>`;
    c.appendChild(wrap);
}
function recopilarLinks() {
    const links = [...document.querySelectorAll('.dynamic-link-input')].map(i=>i.value.trim()).filter(Boolean);
    const el = document.getElementById('linksHidden'); if(el) el.value=links.join('\n');
}

// Mascaras de número y moneda
function applyMasks() {
    document.querySelectorAll('.number-format').forEach(input => {
        const fmt = v => { const n=parseInt(v.replace(/[^0-9]/g,'')); return isNaN(n)?'':n.toLocaleString('en-US'); };
        input.addEventListener('input', function(){this.value=fmt(this.value);});
        if(input.value) input.value=fmt(input.value);
    });
    document.querySelectorAll('.currency-format').forEach(input => {
        const fmt = v => { const n=parseInt(v.replace(/[^0-9]/g,'')); return isNaN(n)?'':'$ '+n.toLocaleString('en-US'); };
        input.addEventListener('input', function(){this.value=fmt(this.value);});
        if(input.value) input.value=fmt(input.value);
    });
}

// ── Init ──────────────────────────────────────────────────
window.addEventListener('load', () => {
    document.querySelectorAll('.number[data-target]').forEach(el => {
        animateNumber(el, parseInt(el.getAttribute('data-target')||0));
    });
    const savedTab = localStorage.getItem('gestionTab') || '<?php echo $first_tab ?? 'vision'; ?>';
    showTab(savedTab);
    if (PUEDE_EDITAR) toggleFields();
    applyMasks();
    initLinks();
});
</script>
</body>
</html>