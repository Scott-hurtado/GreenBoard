<?php
if (file_exists(__DIR__ . '/../../includes/bootstrap.php')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
} else { session_start(); }

// ── API: guardar secciones en sesión antes del redirect al mapa ──
if (isset($_GET['api']) && $_GET['api'] === 'guardar_secs_session') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $_SESSION['mapa_secciones_actuales'] = $body['secciones_actuales'] ?? '';
    $_SESSION['mapa_secciones_ocupadas'] = $body['secciones_ocupadas'] ?? '';
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

$rol         = $_SESSION['user_role']   ?? '';
$user_estado = $_SESSION['user_estado'] ?? '';
$user_region = $_SESSION['user_region'] ?? '';

$permitidos = ['coordinador_regional','regional'];
if (!in_array($rol, $permitidos)) { header('Location: /public/index.php'); exit(); }

try {
    $dbCfg = __DIR__ . '/../../includes/database.php';
    $cfg = file_exists($dbCfg) ? require $dbCfg : [
        'host'=>'localhost','database'=>'u881824383_pverde',
        'username'=>'u881824383_proyverde','password'=>'PProyectoverde8#',
        'charset'=>'utf8mb4',
        'options'=>[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
    ];
    if (!isset($pdo)) {
        $dsn = "mysql:host={$cfg['host']};dbname={$cfg['database']};charset={$cfg['charset']}";
        $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], $cfg['options'] ?? []);
    }
} catch (PDOException $e) { error_log('estructura_cr DB: '.$e->getMessage()); }

/* ══════════════════════════════════════════════════════════════════
   API JSON INTERNA
══════════════════════════════════════════════════════════════════ */
if (isset($_GET['api'])) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        if ($_GET['api'] === 'cargar') {
            // 1. Datos de la región
            $stR = $pdo->prepare("SELECT id, nombre, descripcion, meta, municipios, tipo_integracion FROM regiones_estatales WHERE id = ? LIMIT 1");
            $stR->execute([$user_region]);
            $region = $stR->fetch(PDO::FETCH_ASSOC);
            if (!$region) { echo json_encode(['ok'=>false,'msg'=>'Región no encontrada']); exit(); }
            $region['municipios']       = json_decode($region['municipios'] ?? '[]', true) ?: [];
            $region['tipo_integracion'] = $region['tipo_integracion'] ?: 'municipios';

            // 2. Zonas de la región
            $stZ = $pdo->prepare("SELECT id, nombre, clave, foco, colonias, meta, secciones, COALESCE(miembros,0) AS miembros FROM zonas_estatales WHERE estructura_id = ? ORDER BY nombre");
            $stZ->execute([$region['id']]);
            $zonas = $stZ->fetchAll(PDO::FETCH_ASSOC);

            // Recopilar todas las secciones
            $todasSecciones = [];
            foreach ($zonas as &$z) {
                $z['secciones'] = json_decode($z['secciones'] ?? '[]', true) ?: [];
                $z['id'] = (int)$z['id'];
                $z['meta'] = (int)$z['meta'];
                $z['miembros'] = (int)$z['miembros'];
                foreach ($z['secciones'] as $s) $todasSecciones[] = (string)$s;
            }
            unset($z);

            // 3. Mapa seccion → lista_nominal
            $seccionLnMap = [];
            if (!empty($todasSecciones) && !empty($user_estado)) {
                $ph  = implode(',', array_fill(0, count($todasSecciones), '?'));
                $stS = $pdo->prepare("SELECT nombre_seccion, COALESCE(lista_nominal, padron_electoral, 0) AS ln FROM secciones_electorales WHERE estado = ? AND nombre_seccion IN ($ph) AND activo = 1");
                $stS->execute(array_merge([$user_estado], $todasSecciones));
                foreach ($stS->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $seccionLnMap[(string)$row['nombre_seccion']] = (int)$row['ln'];
                }
            }

            echo json_encode(['ok'=>true,'region'=>$region,'zonas'=>$zonas,'seccion_ln_map'=>$seccionLnMap]);
            exit();
        }
        /* ── SECCIONES DE LA REGIÓN: picker del modal (con LN y ocupadas) ── */
        if ($_GET['api'] === 'secciones_region') {
            $stR = $pdo->prepare("SELECT municipios, tipo_integracion FROM regiones_estatales WHERE id = ? LIMIT 1");
            $stR->execute([$user_region]);
            $row = $stR->fetch(PDO::FETCH_ASSOC);
            if (!$row) { echo json_encode(['ok'=>false,'msg'=>'Región no encontrada']); exit(); }
            $municipios = json_decode($row['municipios'] ?? '[]', true) ?: [];
            $tipoInt    = $row['tipo_integracion'] ?: 'municipios';

            $secciones = []; $lnMap = [];
            if (!empty($municipios) && !empty($user_estado)) {
                $ph = implode(',', array_fill(0, count($municipios), '?'));
                if ($tipoInt === 'distrito_local') {
                    $filtroCol = 'sec.distrito_local';
                } elseif ($tipoInt === 'distrito_federal') {
                    $filtroCol = 'sec.distrito_federal';
                } else {
                    $filtroCol = 'm.nombre';
                }
                $stS = $pdo->prepare("
                    SELECT sec.seccion,
                           COALESCE(se.lista_nominal, se.padron_electoral, 0) AS ln
                    FROM secciones sec
                    LEFT JOIN municipios m ON m.id = sec.municipio_id
                    LEFT JOIN secciones_electorales se
                           ON se.nombre_seccion = sec.seccion
                          AND UPPER(TRIM(se.estado)) = UPPER(TRIM(sec.estado_nombre))
                          AND se.activo = 1
                    WHERE sec.estado_nombre = ?
                      AND $filtroCol IN ($ph)
                    ORDER BY CAST(sec.seccion AS UNSIGNED)
                ");
                $stS->execute(array_merge([$user_estado], $municipios));
                foreach ($stS->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $s = (string)$r['seccion'];
                    $secciones[] = $s;
                    $lnMap[$s]   = (int)$r['ln'];
                }
            }

            // Secciones ocupadas en otras zonas de la misma región
            $stZ = $pdo->prepare("SELECT id, secciones FROM zonas_estatales WHERE estructura_id = ?");
            $stZ->execute([$user_region]);
            $ocupadasPor = [];   // seccion => zona_id
            foreach ($stZ->fetchAll(PDO::FETCH_ASSOC) as $z) {
                foreach ((json_decode($z['secciones'] ?? '[]', true) ?: []) as $s) {
                    $ocupadasPor[(string)$s] = (int)$z['id'];
                }
            }

            echo json_encode(['ok'=>true,'secciones'=>$secciones,'ln'=>$lnMap,'ocupadas_por'=>$ocupadasPor], JSON_UNESCAPED_UNICODE);
            exit();
        }

        /* ── GUARDAR ZONA (crear/editar) — restringida a la región del CR ── */
        if ($_GET['api'] === 'guardar_zona') {
            $body = json_decode(file_get_contents('php://input'), true) ?: [];
            $id       = isset($body['id']) ? (int)$body['id'] : 0;
            $nombre   = trim($body['nombre']   ?? '');
            $clave    = trim($body['clave']    ?? '');
            $foco     = trim($body['foco']     ?? '');
            $colonias = trim($body['colonias'] ?? '');
            $meta     = (int)($body['meta']    ?? 0);
            $seccionesArr = array_values(array_filter(array_map('strval', $body['secciones'] ?? [])));
            $secciones    = json_encode($seccionesArr, JSON_UNESCAPED_UNICODE);

            if (!$nombre)       { echo json_encode(['ok'=>false,'msg'=>'Nombre requerido']); exit(); }
            if (!$user_region)  { echo json_encode(['ok'=>false,'msg'=>'Sin región asignada']); exit(); }

            // Si es edición: verificar que la zona pertenece a SU región
            if ($id) {
                $chk = $pdo->prepare("SELECT id FROM zonas_estatales WHERE id = ? AND estructura_id = ?");
                $chk->execute([$id, $user_region]);
                if (!$chk->fetch()) { echo json_encode(['ok'=>false,'msg'=>'Zona no pertenece a tu región']); exit(); }
            }

            // Conflictos: secciones ya asignadas en otra zona del mismo estado
            if (!empty($seccionesArr) && !empty($user_estado)) {
                $stmtConf = $pdo->prepare("
                    SELECT z.id, z.nombre, z.secciones, r.nombre AS region_nombre
                    FROM zonas_estatales z
                    INNER JOIN regiones_estatales r ON r.id = z.estructura_id
                    WHERE r.estado = ? AND z.id != ?
                ");
                $stmtConf->execute([$user_estado, $id ?: 0]);
                $conflictos = [];
                foreach ($stmtConf->fetchAll(PDO::FETCH_ASSOC) as $oz) {
                    $secsOtra   = json_decode($oz['secciones'] ?? '[]', true) ?: [];
                    $duplicadas = array_intersect($seccionesArr, array_map('strval', $secsOtra));
                    if (!empty($duplicadas)) {
                        $conflictos[] = 'Sección(es) ' . implode(', ', $duplicadas) . ' ya asignada(s) en zona "' . $oz['nombre'] . '" (Región: ' . $oz['region_nombre'] . ')';
                    }
                }
                if (!empty($conflictos)) { echo json_encode(['ok'=>false,'msg'=>implode(' | ', $conflictos)]); exit(); }
            }

            $eventos_actualizados = 0;
            if ($id) {
                // Nombre anterior (para propagar el cambio a la agenda)
                $stOld = $pdo->prepare("SELECT nombre FROM zonas_estatales WHERE id = ? AND estructura_id = ? LIMIT 1");
                $stOld->execute([$id, $user_region]);
                $nombre_anterior = trim((string)($stOld->fetchColumn() ?: ''));

                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare("
                        UPDATE zonas_estatales
                        SET nombre=?, clave=?, foco=?, colonias=?, meta=?, secciones=?
                        WHERE id=? AND estructura_id=?
                    ");
                    $stmt->execute([$nombre, $clave, $foco, $colonias, $meta, $secciones, $id, $user_region]);

                    // agenda_eventos.zona guarda el NOMBRE como texto: si la zona
                    // se renombra, se actualizan los eventos para que no dejen de
                    // aparecer en la agenda de regional/zona.
                    if ($nombre_anterior !== '' && $nombre_anterior !== $nombre && !empty($user_estado)) {
                        // Si otra zona del estado (de otra región) comparte el nombre
                        // anterior, limitar el cambio a eventos de esta región.
                        $stDup = $pdo->prepare("
                            SELECT COUNT(*) FROM zonas_estatales z
                            INNER JOIN regiones_estatales r ON r.id = z.estructura_id
                            WHERE r.estado = ? AND z.nombre = ? AND z.id <> ?
                        ");
                        $stDup->execute([$user_estado, $nombre_anterior, $id]);
                        $hayDuplicado = (int)$stDup->fetchColumn() > 0;

                        if ($hayDuplicado) {
                            $stRn = $pdo->prepare("SELECT nombre FROM regiones_estatales WHERE id = ? LIMIT 1");
                            $stRn->execute([$user_region]);
                            $regionNombreActual = (string)($stRn->fetchColumn() ?: '');
                            $stAg = $pdo->prepare("UPDATE agenda_eventos SET zona = ? WHERE estado = ? AND zona = ? AND region = ?");
                            $stAg->execute([$nombre, $user_estado, $nombre_anterior, $regionNombreActual]);
                        } else {
                            $stAg = $pdo->prepare("UPDATE agenda_eventos SET zona = ? WHERE estado = ? AND zona = ?");
                            $stAg->execute([$nombre, $user_estado, $nombre_anterior]);
                        }
                        $eventos_actualizados = $stAg->rowCount();
                    }
                    $pdo->commit();
                } catch (Exception $eTx) {
                    $pdo->rollBack();
                    throw $eTx;
                }
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO zonas_estatales (estructura_id, nombre, clave, foco, colonias, meta, secciones)
                    VALUES (?,?,?,?,?,?,?)
                ");
                $stmt->execute([$user_region, $nombre, $clave, $foco, $colonias, $meta, $secciones]);
                $id = (int)$pdo->lastInsertId();
            }
            echo json_encode(['ok'=>true,'id'=>$id,'eventos_actualizados'=>$eventos_actualizados]);
            exit();
        }

        /* ── LN POR REGIÓN: para el banner LN en territorio.php modo selección ── */
        if ($_GET['api'] === 'ln_por_region') {
            $stR = $pdo->prepare("SELECT municipios, tipo_integracion FROM regiones_estatales WHERE id = ? LIMIT 1");
            $stR->execute([$user_region]);
            $rowR = $stR->fetch(PDO::FETCH_ASSOC);
            $municipios = json_decode($rowR['municipios'] ?? '[]', true) ?: [];
            $tipoIntLN  = $rowR['tipo_integracion'] ?: 'municipios';
            $lnMap = [];
            if (!empty($municipios) && !empty($user_estado)) {
                $ph = implode(',', array_fill(0, count($municipios), '?'));
                if ($tipoIntLN === 'distrito_local') {
                    $filtroColLN = 'sec.distrito_local';
                } elseif ($tipoIntLN === 'distrito_federal') {
                    $filtroColLN = 'sec.distrito_federal';
                } else {
                    $filtroColLN = 'm.nombre';
                }
                $stLN = $pdo->prepare("
                    SELECT sec.seccion,
                           COALESCE(se.lista_nominal, se.padron_electoral, 0) AS ln
                    FROM secciones sec
                    LEFT JOIN municipios m ON m.id = sec.municipio_id
                    LEFT JOIN secciones_electorales se
                           ON se.nombre_seccion = sec.seccion
                          AND UPPER(TRIM(se.estado)) = UPPER(TRIM(sec.estado_nombre))
                          AND se.activo = 1
                    WHERE sec.estado_nombre = ? AND $filtroColLN IN ($ph)
                ");
                $stLN->execute(array_merge([$user_estado], $municipios));
                foreach ($stLN->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $lnMap[(string)$r['seccion']] = (int)$r['ln'];
                }
            }
            echo json_encode(['ok'=>true,'ln'=>$lnMap], JSON_UNESCAPED_UNICODE);
            exit();
        }

        /* ── GUARDAR SECCIONES DESDE MAPA (retorno de territorio.php modo selección) ── */
        if ($_GET['api'] === 'guardar_secciones') {
            $body = json_decode(file_get_contents('php://input'), true) ?: [];
            $zona_id  = (int)($body['zona_id']  ?? 0);
            $secciones = array_values(array_filter(array_map('strval', $body['secciones'] ?? [])));
            if (!$zona_id)  { echo json_encode(['ok'=>false,'msg'=>'zona_id requerido']); exit(); }
            // Verificar que pertenece a la región del CR
            $chk = $pdo->prepare("SELECT id FROM zonas_estatales WHERE id = ? AND estructura_id = ?");
            $chk->execute([$zona_id, $user_region]);
            if (!$chk->fetch()) { echo json_encode(['ok'=>false,'msg'=>'Zona no pertenece a tu región']); exit(); }
            $stmtUpd = $pdo->prepare("UPDATE zonas_estatales SET secciones = ? WHERE id = ?");
            $stmtUpd->execute([json_encode($secciones, JSON_UNESCAPED_UNICODE), $zona_id]);
            echo json_encode(['ok'=>true]);
            exit();
        }

        echo json_encode(['ok'=>false,'msg'=>'Acción desconocida']);
    } catch (Exception $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit();
}

$region_nombre = '—';
try {
    $st = $pdo->prepare("SELECT nombre FROM regiones_estatales WHERE id = ? LIMIT 1");
    $st->execute([$user_region]);
    $r = $st->fetch();
    if ($r) $region_nombre = $r['nombre'];
} catch(Exception $e) {}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Estructura - <?= htmlspecialchars($region_nombre) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root{--primary:#2d7a3e;--primary-dark:#1f5a2d;--dark:#1a1a1a;--light:#f0f7f0;--white:#fff;--gray-100:#f8f9fa;--gray-200:#e9ecef;--text-muted:#6c757d;--shadow-sm:0 10px 30px rgba(0,0,0,.05);}
*{margin:0;padding:0;box-sizing:border-box;}body{font-family:'Arial',sans-serif;background:var(--light);color:var(--dark);overflow-x:hidden;}
.container{max-width:100%;background:var(--white);min-height:100vh;padding-left:330px;padding-right:50px;padding-bottom:60px;transition:padding-left .3s;}
.design-text{position:fixed;left:290px;top:50%;writing-mode:vertical-rl;font-size:13px;font-weight:500;color:var(--primary);opacity:.3;letter-spacing:2px;z-index:0;pointer-events:none;}
.page-header{padding:50px 0 30px;display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:20px;border-bottom:2px solid var(--gray-200);margin-bottom:30px;position:relative;z-index:2;}
.ai-badge{color:var(--primary);font-size:13px;font-weight:800;letter-spacing:4px;margin-bottom:15px;display:inline-block;text-transform:uppercase;background:rgba(45,122,62,.1);padding:5px 15px;border-radius:20px;}
.main-title{font-size:54px;font-weight:900;line-height:1;margin-bottom:15px;}
.description{color:#666;font-size:16px;line-height:1.6;max-width:600px;}
.workspace{display:grid;grid-template-columns:280px 1fr 1fr;gap:0;border:1px solid var(--gray-200);border-radius:20px;overflow:hidden;box-shadow:var(--shadow-sm);min-height:600px;}
.col-panel{background:var(--white);border-right:1px solid var(--gray-200);display:flex;flex-direction:column;}
.col-panel:last-child{border-right:none;}
.col-header{padding:18px 20px;border-bottom:1px solid var(--gray-200);display:flex;align-items:center;justify-content:space-between;background:var(--gray-100);}
.col-header-title{font-size:14px;font-weight:800;color:var(--dark);display:flex;align-items:center;gap:8px;}
.col-body{flex:1;overflow-y:auto;padding:12px;}
.col-body::-webkit-scrollbar{width:4px;}
.col-body::-webkit-scrollbar-thumb{background:#ddd;border-radius:4px;}
.region-card,.zona-card{background:var(--white);border:1px solid var(--gray-200);border-radius:14px;padding:16px 18px;margin-bottom:10px;transition:all .2s;position:relative;}
.region-card{border-color:var(--primary);background:rgba(45,122,62,.02);cursor:default;}
.zona-card{cursor:pointer;}
.zona-card:hover{border-color:var(--primary);box-shadow:0 4px 14px rgba(45,122,62,.1);}
.zona-card.active{border-color:var(--primary);background:rgba(45,122,62,.04);box-shadow:0 4px 14px rgba(45,122,62,.12);}
.card-name{font-size:16px;font-weight:700;color:var(--dark);margin-bottom:6px;}
.region-card .card-name{font-size:20px;font-weight:900;}
.card-meta{display:flex;justify-content:space-between;align-items:center;font-size:13px;}
.card-miembros{color:var(--text-muted);}
.card-pct{color:var(--primary);font-weight:800;font-size:14px;}
.card-bar-track{height:4px;background:var(--gray-200);border-radius:99px;margin-top:8px;overflow:hidden;}
.card-bar-fill{height:100%;background:var(--primary);border-radius:99px;transition:width .4s;}
.card-secciones-count{font-size:11px;color:var(--text-muted);margin-top:6px;}
.card-meta-bottom{margin-top:4px;font-size:11px;color:var(--text-muted);font-style:italic;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.zi-bullet{font-size:12px;color:var(--dark);margin-top:8px;display:flex;gap:6px;align-items:flex-start;}
.zi-bullet i{color:var(--primary);margin-top:2px;}
.list-scroll{flex:1;overflow-y:auto;}
.list-scroll::-webkit-scrollbar{width:4px;}
.list-scroll::-webkit-scrollbar-thumb{background:#ddd;border-radius:4px;}
.seccion-item{padding:10px 20px;font-size:13px;border-bottom:1px solid var(--gray-200);display:flex;align-items:center;justify-content:space-between;transition:background .15s;}
.seccion-item:hover{background:var(--gray-100);}
.seccion-num{font-weight:700;font-size:13px;}
.seccion-ln{font-size:11px;font-weight:700;color:#92400e;background:#fef3c7;border:1px solid #fde68a;border-radius:5px;padding:2px 8px;margin-left:auto;margin-right:10px;}
.seccion-assigned{color:var(--primary);font-size:11px;font-weight:700;display:flex;align-items:center;gap:4px;}
.zona-info-box{margin:12px 16px;padding:14px 16px;background:rgba(45,122,62,.05);border:1px solid rgba(45,122,62,.2);border-radius:12px;font-size:13px;}
.zona-info-box .zi-title{font-weight:800;font-size:14px;color:var(--dark);margin-bottom:4px;}
.zona-info-box .zi-foco{font-style:italic;color:var(--text-muted);margin-bottom:6px;font-size:12px;}
.zona-info-box .zi-bullet{font-size:12px;color:var(--dark);margin-bottom:3px;display:flex;gap:6px;}
.zona-info-box .zi-bullet strong{color:var(--primary);}
.hint-text{text-align:center;padding:60px 20px;color:var(--text-muted);font-size:14px;}
.hint-text i{display:block;font-size:32px;opacity:.2;margin-bottom:12px;}
.empty-col{text-align:center;padding:40px 20px;color:var(--text-muted);font-size:13px;}
.empty-col i{display:block;font-size:28px;opacity:.25;margin-bottom:10px;}
.animate-up{animation:fadeInUp .6s ease forwards;opacity:0;}
@keyframes fadeInUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
.animate-delay-1{animation-delay:.1s;}
@media(max-width:1100px){.container{padding-left:20px;padding-right:20px;}.workspace{grid-template-columns:1fr;}.design-text{display:none;}.col-panel{border-right:none;border-bottom:1px solid var(--gray-200);}}

/* ── Botón Nueva zona + lápiz editar ── */
.btn-nueva-zona{display:flex;align-items:center;gap:6px;background:var(--primary);color:white;border:none;padding:6px 14px;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;font-family:Arial,sans-serif;}
.btn-nueva-zona:hover{background:var(--primary-dark);}
.zona-edit-btn{position:absolute;top:12px;right:12px;background:none;border:none;color:#bbb;cursor:pointer;font-size:13px;padding:4px;border-radius:6px;transition:all .15s;}
.zona-edit-btn:hover{color:var(--primary);background:rgba(45,122,62,.08);}

/* ── Modal zona ── */
.gb-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);display:none;align-items:center;justify-content:center;z-index:1000;padding:20px;}
.gb-overlay.open{display:flex;}
.gb-modal{background:white;border-radius:16px;max-width:640px;width:100%;max-height:90vh;overflow-y:auto;padding:28px;box-shadow:0 20px 60px rgba(0,0,0,.2);}
.gb-modal h3{font-size:20px;font-weight:900;margin-bottom:18px;}
.gb-field{margin-bottom:14px;}
.gb-field label{display:block;font-size:12px;font-weight:700;color:var(--text-muted);margin-bottom:5px;text-transform:uppercase;letter-spacing:.5px;}
.gb-field input,.gb-field textarea{width:100%;padding:10px 12px;border:1px solid var(--gray-200);border-radius:9px;font-size:14px;font-family:Arial,sans-serif;}
.gb-field input:focus,.gb-field textarea:focus{outline:none;border-color:var(--primary);}
.gb-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.sec-picker{border:1px solid var(--gray-200);border-radius:10px;max-height:220px;overflow-y:auto;padding:10px;display:flex;flex-wrap:wrap;gap:6px;}
.sec-chip{padding:5px 11px;border:1px solid var(--gray-200);border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;user-select:none;background:white;transition:all .12s;}
.sec-chip:hover{border-color:var(--primary);}
.sec-chip.sel{background:var(--primary);border-color:var(--primary);color:white;}
.sec-chip.ocupada{opacity:.4;cursor:not-allowed;text-decoration:line-through;}
.gb-ln-badge{font-size:12px;font-weight:800;color:#92400e;background:#fef3c7;border:1px solid #fde68a;border-radius:6px;padding:3px 10px;}
.gb-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:20px;}
.gb-btn{padding:10px 22px;border-radius:9px;font-size:14px;font-weight:700;cursor:pointer;border:none;font-family:Arial,sans-serif;}
.gb-btn-cancel{background:white;border:1px solid var(--gray-200);color:var(--dark);}
.gb-btn-save{background:var(--primary);color:white;}
.gb-btn-save:hover{background:var(--primary-dark);}
.gb-toast{position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:#1a1a1a;color:white;padding:12px 22px;border-radius:10px;font-size:13px;z-index:1100;display:none;}
</style>
</head>
<body>
<?php if(file_exists(__DIR__.'/../../includes/components/sidebar_regional.php')) include __DIR__.'/../../includes/components/sidebar_regional.php'; ?>
<div class="design-text">ESTRUCTURA REGIONAL</div>

<div class="container">
    <div class="page-header animate-up">
        <div>
            <div class="ai-badge">REGIÓN <?= htmlspecialchars(strtoupper($region_nombre)) ?></div>
            <h1 class="main-title">Estructura.</h1>
            <p class="description">Zonas y secciones electorales de tu región en <strong><?= htmlspecialchars($user_estado) ?></strong>.</p>
        </div>
    </div>

    <div class="workspace animate-up animate-delay-1">

        <div class="col-panel">
            <div class="col-header">
                <div class="col-header-title"><i class="fas fa-map" style="color:var(--primary)"></i> Resumen de Región</div>
            </div>
            <div class="col-body" id="col-region-body">
                <div class="empty-col"><i class="fas fa-circle-notch fa-spin"></i>Cargando...</div>
            </div>
        </div>

        <div class="col-panel">
            <div class="col-header">
                <div class="col-header-title">
                    <i class="fas fa-layer-group" style="color:var(--primary)"></i> Zonas
                    <span id="col-zonas-subtitle" style="font-weight:400;color:var(--text-muted);font-size:12px;margin-left:4px;"></span>
                </div>
                <button class="btn-nueva-zona" onclick="abrirModalZona(null)"><i class="fas fa-plus"></i> Nueva</button>
            </div>
            <div class="col-body" id="col-zonas-body">
                <div class="hint-text"><i class="fas fa-circle-notch fa-spin"></i>Cargando...</div>
            </div>
        </div>

        <div class="col-panel">
            <div class="col-header">
                <div class="col-header-title">
                    <i class="fas fa-list-ol" style="color:var(--primary)"></i> Secciones
                    <span id="col-sec-subtitle" style="font-weight:400;color:var(--text-muted);font-size:12px;margin-left:4px;"></span>
                </div>
                <button id="btn-ver-mapa" onclick="verEnMapa()" title="Ver zona en el mapa" style="display:none;align-items:center;gap:6px;background:#1565c0;color:white;border:none;padding:6px 14px;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;font-family:Arial,sans-serif;">
                    <i class="fas fa-map-marked-alt"></i> Mapa
                </button>
            </div>
            <div id="col-secciones-body" style="flex:1;display:flex;flex-direction:column;">
                <div class="hint-text"><i class="fas fa-arrow-left"></i>Selecciona una zona para ver sus secciones.</div>
            </div>
        </div>

    </div>
</div>

<!-- ── Modal Crear/Editar Zona ── -->
<div class="gb-overlay" id="modal-zona">
  <div class="gb-modal">
    <h3 id="mz-titulo">Nueva Zona</h3>
    <div class="gb-row">
      <div class="gb-field"><label>Nombre *</label><input id="mz-nombre" placeholder="Z1.1 Centro"></div>
      <div class="gb-field"><label>Clave</label><input id="mz-clave" placeholder="Z1.1"></div>
    </div>
    <div class="gb-field"><label>Foco / Descripción</label><textarea id="mz-foco" rows="2" placeholder="Zona habitacional..."></textarea></div>
    <div class="gb-row">
      <div class="gb-field"><label>Colonias</label><input id="mz-colonias" placeholder="Centro, Las Flores..."></div>
      <div class="gb-field"><label>Meta de contactos</label><input id="mz-meta" type="number" min="0" placeholder="0"></div>
    </div>
    <div class="gb-field">
      <label id="mz-muni-label">Municipios de la región</label>
      <div id="mz-muni-legend" style="font-size:12px;color:var(--text-muted);background:var(--gray-50,#f5f7fa);border:1px solid var(--gray-200,#e2e6ea);border-radius:8px;padding:8px 10px;margin-bottom:10px;line-height:1.6;"></div>
    </div>
    <div class="gb-field">
      <label>Secciones de la región</label>
      <input id="mz-sec-search" placeholder="Buscar sección..." style="margin-bottom:8px;" oninput="renderPickerZona()">
      <div class="sec-picker" id="mz-sec-picker"><div style="color:var(--text-muted);font-size:12px;">Cargando secciones…</div></div>
      <div style="margin-top:8px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
        <span style="font-size:12px;color:var(--text-muted);" id="mz-sec-count">0 secciones seleccionadas</span>
        <span class="gb-ln-badge" id="mz-ln-badge" style="display:none;">0 de lista nominal</span>
        <button type="button" onclick="irAMapaDesdeModal()" title="Seleccionar secciones visualmente en el mapa" style="margin-left:auto;display:flex;align-items:center;gap:6px;background:#1565c0;color:white;border:none;padding:7px 13px;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;font-family:Arial,sans-serif;">
          <i class="fas fa-map-marked-alt"></i> Desde Mapa
        </button>
      </div>
    </div>
    <div class="gb-actions">
      <button class="gb-btn gb-btn-cancel" onclick="cerrarModalZona()">Cancelar</button>
      <button class="gb-btn gb-btn-save" onclick="guardarZona()"><i class="fas fa-check"></i> Guardar</button>
    </div>
  </div>
</div>
<div class="gb-toast" id="gb-toast"></div>

<script>
let appData    = { region: null, zonas: [], seccionLnMap: {} };
let zonaActiva = null;

function getEtiquetaIntegracionCR() {
    const t = appData.region?.tipo_integracion || 'municipios';
    if (t === 'distrito_local')   return { titulo: 'Distritos locales de la región', prefijo: 'DL ', tipo: 'distrito_local' };
    if (t === 'distrito_federal') return { titulo: 'Distritos Federales de la región', prefijo: 'DF ', tipo: 'distrito_federal' };
    return { titulo: 'Municipios de la región', prefijo: '', tipo: 'municipios' };
}

function renderMuniLegendModal() {
    const et   = getEtiquetaIntegracionCR();
    const lbl  = document.getElementById('mz-muni-label');
    const cont = document.getElementById('mz-muni-legend');
    if (lbl) lbl.textContent = et.titulo;
    const munis = appData.region?.municipios || [];
    if (!cont) return;
    if (!munis.length) {
        cont.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Sin elementos asignados a la región.';
        return;
    }
    cont.innerHTML = munis.map(m =>
        `<span class="mz-muni-chip" style="display:inline-block;cursor:pointer;text-decoration:underline dotted;margin-right:4px;" title="Ver en el mapa" onclick="irAMapaDesdeModal('${String(m).replace(/'/g,"\\'")}')">${et.prefijo}${m}</span>`
    ).join(', ');
}

function getUnidadRegionCR() {
    const t = appData.region?.tipo_integracion || 'municipios';
    if (t === 'distrito_local')   return { singular: 'Distrito local', plural: 'Distritos locales' };
    if (t === 'distrito_federal') return { singular: 'Distrito Federal', plural: 'Distritos Federales' };
    return { singular: 'Municipio', plural: 'Municipios' };
}

function calcTotalLN(secs) { let t=0; secs.forEach(s=>{t+=appData.seccionLnMap[String(s)]||0;}); return t; }
function fmtLN(n) { return n>0 ? n.toLocaleString('es-MX') : ''; }

async function cargarDatos() {
    try {
        const res  = await fetch(`${window.location.pathname}?api=cargar`);
        const data = await res.json();
        if (!data.ok) throw new Error(data.msg||'Error');
        appData.region       = data.region;
        appData.zonas        = data.zonas || [];
        appData.seccionLnMap = data.seccion_ln_map || {};
        renderRegion();
        renderZonas();
    } catch(e) {
        document.getElementById('col-region-body').innerHTML =
            `<div class="empty-col"><i class="fas fa-exclamation-triangle" style="color:#d32f2f"></i>Error al cargar.<br><small>${e.message}</small></div>`;
    }
}

function renderRegion() {
    const body = document.getElementById('col-region-body');
    const r = appData.region; if (!r) return;
    const totalSecs     = appData.zonas.reduce((s,z)=>s+(z.secciones||[]).length, 0);
    const totalMiembros = appData.zonas.reduce((s,z)=>s+(z.miembros||0), 0);
    const pct           = r.meta>0 ? Math.min(100,Math.round(totalMiembros/r.meta*100)) : 0;
    const nZonas        = appData.zonas.length;
    const nMunis        = (r.municipios||[]).length;
    const totalLN       = calcTotalLN(appData.zonas.flatMap(z=>z.secciones||[]));

    body.innerHTML = `
        <div class="region-card">
            <div class="card-name">${r.nombre}</div>
            <div class="card-meta" style="margin-bottom:4px;">
                <span class="card-miembros">Avance de Contactos</span>
                <span class="card-pct">${pct}%</span>
            </div>
            <div class="card-bar-track"><div class="card-bar-fill" style="width:${pct}%"></div></div>
            <div class="card-secciones-count" style="margin-bottom:14px;">${totalMiembros.toLocaleString('es-MX')} de ${(r.meta||0).toLocaleString('es-MX')} meta total</div>
            <div style="border-top:1px solid rgba(45,122,62,.2);padding-top:12px;">
                <div class="zi-bullet"><i class="fas fa-layer-group"></i><div><strong>${nZonas}</strong> Zona${nZonas!==1?'s':''}</div></div>
                <div class="zi-bullet"><i class="fas fa-list-ol"></i><div><strong>${totalSecs}</strong> Secciones electorales</div></div>
                <div class="zi-bullet"><i class="fas fa-city"></i><div><strong>${nMunis}</strong> ${nMunis===1 ? getUnidadRegionCR().singular : getUnidadRegionCR().plural}</div></div>
                ${totalLN>0?`<div class="zi-bullet" style="margin-top:10px;"><i class="fas fa-users"></i><span style="font-weight:800;color:#92400e;background:#fef3c7;border:1px solid #fde68a;border-radius:6px;padding:3px 10px;font-size:13px;">${fmtLN(totalLN)} de lista nominal</span></div>`:''}
            </div>
        </div>
        <div style="text-align:center;margin-top:16px;font-size:11px;color:var(--text-muted);">Información gestionada por la coordinación estatal.</div>`;
}

function renderZonas() {
    const body     = document.getElementById('col-zonas-body');
    const subtitle = document.getElementById('col-zonas-subtitle');
    subtitle.textContent = `(${appData.zonas.length})`;
    if (!appData.zonas.length) {
        body.innerHTML = '<div class="hint-text"><i class="fas fa-layer-group"></i>No hay zonas en esta región.</div>';
        return;
    }
    body.innerHTML = appData.zonas.map(z => {
        const nSec = (z.secciones||[]).length;
        const pct  = z.meta>0 ? Math.min(100,Math.round((z.miembros||0)/z.meta*100)) : 0;
        const ln   = calcTotalLN(z.secciones||[]);
        return `<div class="zona-card ${zonaActiva===z.id?'active':''}" onclick="selectZona(${z.id})">
            <button class="zona-edit-btn" onclick="event.stopPropagation();abrirModalZona(${z.id})" title="Editar zona"><i class="fas fa-pen"></i></button>
            <div class="card-name">${z.nombre}${z.clave?` <span style="font-size:11px;color:var(--text-muted);font-weight:400;">(${z.clave})</span>`:''}</div>
            <div class="card-meta">
                <span class="card-miembros">${(z.miembros||0).toLocaleString('es-MX')} Miembros</span>
                <span class="card-pct">${pct}%</span>
            </div>
            <div class="card-bar-track"><div class="card-bar-fill" style="width:${pct}%"></div></div>
            <div class="card-secciones-count">${nSec} sección${nSec!==1?'es':''} asignada${nSec!==1?'s':''} · Meta: ${(z.meta||0).toLocaleString('es-MX')}</div>
            ${ln>0?`<div style="margin-top:6px;"><span style="font-size:11px;font-weight:700;color:#92400e;background:#fef3c7;border:1px solid #fde68a;border-radius:5px;padding:2px 8px;">${fmtLN(ln)} LN</span></div>`:''}
            ${z.foco?`<div class="card-meta-bottom">${z.foco}</div>`:''}
        </div>`;
    }).join('');
}

function selectZona(id) {
    zonaActiva = id;
    renderZonas();
    renderSecciones();
}

function verEnMapa() {
    if (!zonaActiva) return;
    const zona = appData.zonas.find(z => z.id === zonaActiva);
    const returnUrl = window.location.href.split('?')[0];

    // Calcular secciones ocupadas (de otras zonas)
    const ocupadas = new Set();
    (appData.zonas || []).forEach(z => {
        if (z.id === zonaActiva) return;
        (z.secciones || []).forEach(s => ocupadas.add(String(s)));
    });

    // Guardar secciones en sessionStorage (respaldo para evitar URL 414)
    const secsActuales = (zona?.secciones || []).join(',');
    const secsOcupadas = [...ocupadas].join(',');
    sessionStorage.setItem('mapa_secciones_actuales', secsActuales);
    sessionStorage.setItem('mapa_secciones_ocupadas', secsOcupadas);
    sessionStorage.setItem('estructura_cr_zona_activa', String(zonaActiva));

    // Guardar en sesión PHP (evita URL 414 con regiones con muchas secciones)
    fetch(`${window.location.pathname}?api=guardar_secs_session`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ secciones_actuales: secsActuales, secciones_ocupadas: secsOcupadas })
    }).catch(() => {}).finally(() => {
        const params = new URLSearchParams({
            modo_seleccion: '1',
            zona_id:        zonaActiva || '',
            zona_nombre:    zona?.nombre || '',
            return_url:     returnUrl,
        });
        window.location.href = 'territorio.php?' + params.toString();
    });
}

async function irAMapaDesdeModal(zoomEntidad) {
    // Guardar estado del modal como draft para restaurarlo al volver
    const draft = {
        zonaId:    mzEditandoId,
        nombre:    document.getElementById('mz-nombre').value,
        clave:     document.getElementById('mz-clave').value,
        foco:      document.getElementById('mz-foco').value,
        colonias:  document.getElementById('mz-colonias').value,
        meta:      document.getElementById('mz-meta').value,
        secciones: [...mzSelSet],
    };
    sessionStorage.setItem('estructura_cr_modal_draft', JSON.stringify(draft));
    sessionStorage.setItem('estructura_cr_zona_activa', String(mzEditandoId || zonaActiva || ''));

    // Secciones ocupadas por otras zonas
    const ocupadas = new Set();
    (appData.zonas || []).forEach(z => {
        if (z.id === mzEditandoId) return;
        (z.secciones || []).forEach(s => ocupadas.add(String(s)));
    });

    const secsActuales = [...mzSelSet].join(',');
    const secsOcupadas = [...ocupadas].join(',');
    sessionStorage.setItem('mapa_secciones_actuales', secsActuales);
    sessionStorage.setItem('mapa_secciones_ocupadas', secsOcupadas);

    const returnUrl = window.location.href.split('?')[0];
    await fetch(`${window.location.pathname}?api=guardar_secs_session`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ secciones_actuales: secsActuales, secciones_ocupadas: secsOcupadas })
    }).catch(() => {});

    const paramsObj = {
        modo_seleccion: '1',
        zona_id:        mzEditandoId || '',
        zona_nombre:    draft.nombre || 'Zona nueva',
        return_url:     returnUrl,
    };
    // Si el clic vino del listado de municipios/distritos y aún no hay secciones asignadas,
    // pedimos al mapa que haga acercamiento (zoom) a esa entidad al cargar.
    if (zoomEntidad && !mzSelSet.size) {
        paramsObj.zoom_entidad = zoomEntidad;
        paramsObj.zoom_tipo    = getEtiquetaIntegracionCR().tipo;
    }
    const params = new URLSearchParams(paramsObj);
    window.location.href = 'territorio.php?' + params.toString();
}

function renderSecciones() {
    const body     = document.getElementById('col-secciones-body');
    const subtitle = document.getElementById('col-sec-subtitle');
    const btnMapa  = document.getElementById('btn-ver-mapa');
    if (!zonaActiva) {
        body.innerHTML = '<div class="hint-text"><i class="fas fa-arrow-left"></i>Selecciona una zona para ver sus secciones.</div>';
        subtitle.textContent = '';
        btnMapa.style.display = 'none';
        return;
    }
    const zona = appData.zonas.find(z=>z.id===zonaActiva); if (!zona) return;
    subtitle.textContent = zona.nombre;
    btnMapa.style.display = 'flex';
    const secs    = zona.secciones||[];
    const totalLN = calcTotalLN(secs);

    const infoBox = `<div class="zona-info-box">
        <div class="zi-title">${zona.nombre}${zona.clave?' · '+zona.clave:''}</div>
        ${zona.foco?`<div class="zi-foco">${zona.foco}</div>`:''}
        <div class="zi-bullet"><strong>Secciones (${secs.length}):</strong> ${secs.length?secs.slice(0,8).join(', ')+(secs.length>8?'...':''):'—'}</div>
        ${totalLN>0?`<div class="zi-bullet" style="margin-top:4px;"><span style="font-weight:800;color:#92400e;background:#fef3c7;border:1px solid #fde68a;border-radius:6px;padding:3px 10px;font-size:13px;">${fmtLN(totalLN)} de lista nominal</span></div>`:''}
        ${zona.colonias?`<div class="zi-bullet"><strong>Colonias:</strong> ${zona.colonias}</div>`:''}
    </div>`;

    if (!secs.length) {
        body.innerHTML = infoBox + '<div class="hint-text"><i class="fas fa-list-ol"></i>Sin secciones asignadas.</div>';
        return;
    }
    const secsSorted = [...secs].sort((a,b)=>parseInt(a)-parseInt(b));
    body.innerHTML = infoBox + '<div class="list-scroll">' +
        secsSorted.map(s => {
            const ln = appData.seccionLnMap[String(s)]||0;
            return `<div class="seccion-item">
                <span class="seccion-num">Sección ${s}</span>
                ${ln>0?`<span class="seccion-ln">Ln: ${ln.toLocaleString('es-MX')}</span>`:''}
                <span class="seccion-assigned"><i class="fas fa-check-circle"></i> Asignada</span>
            </div>`;
        }).join('') + '</div>';
}

document.addEventListener('DOMContentLoaded', async () => {
    await cargarDatos();

    /* ── Retorno desde territorio.php con secciones seleccionadas ── */
    const params    = new URLSearchParams(window.location.search);
    const secsStr   = params.get('secciones_mapa');
    const zonaIdStr = params.get('zona_id');

    if (secsStr !== null) {
        const zonaIdParam = zonaIdStr ? parseInt(zonaIdStr) : null;
        const secciones   = secsStr.split(',').filter(Boolean);

        // Limpiar URL
        history.replaceState({}, '', window.location.pathname);

        // ── Caso A: viene del modal (draft guardado) ──
        const draft = JSON.parse(sessionStorage.getItem('estructura_cr_modal_draft') || 'null');
        sessionStorage.removeItem('estructura_cr_modal_draft');
        sessionStorage.removeItem('estructura_cr_zona_activa');

        if (draft) {
            // Restaurar zona activa si existe
            if (draft.zonaId) zonaActiva = draft.zonaId;
            renderSecciones();
            // Abrir modal con draft restaurado + secciones del mapa
            await abrirModalZona(draft.zonaId || null);
            document.getElementById('mz-nombre').value   = draft.nombre   || '';
            document.getElementById('mz-clave').value    = draft.clave    || '';
            document.getElementById('mz-foco').value     = draft.foco     || '';
            document.getElementById('mz-colonias').value = draft.colonias || '';
            document.getElementById('mz-meta').value     = draft.meta     || '';
            // Aplicar las secciones del mapa (reemplazan las del draft)
            mzSelSet = new Set(secciones);
            renderPickerZona();
            gbToast(`✓ ${secciones.length} sección${secciones.length !== 1 ? 'es' : ''} seleccionada${secciones.length !== 1 ? 's' : ''} desde el mapa`);
            return;
        }

        // ── Caso B: viene del botón Mapa de la columna → guardar directo en BD ──
        if (!zonaIdParam) return;
        try {
            const res = await fetch(`${window.location.pathname}?api=guardar_secciones`, {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ zona_id: zonaIdParam, secciones })
            });
            const data = await res.json();
            if (!data.ok) { gbToast('Error al guardar secciones: ' + (data.msg || '')); return; }

            pickerData = null;
            gbToast(`✓ Secciones guardadas (${secciones.length})`);
            zonaActiva = zonaIdParam;
            await cargarDatos();
            renderSecciones();
        } catch(e) {
            gbToast('Error de red al guardar secciones desde mapa.');
        }
    }
});

/* ══════════════════════════════════════════════════════
   CREAR / EDITAR ZONA
══════════════════════════════════════════════════════ */
let pickerData    = null;   // { secciones:[], ln:{}, ocupadas_por:{} }  (cache)
let mzSelSet      = new Set();
let mzEditandoId  = null;

function gbToast(msg) {
    const t = document.getElementById('gb-toast');
    t.textContent = msg;
    t.style.display = 'block';
    setTimeout(() => { t.style.display = 'none'; }, 3500);
}

async function cargarPickerData() {
    if (pickerData) return pickerData;
    const res  = await fetch(`${window.location.pathname}?api=secciones_region`);
    const data = await res.json();
    if (!data.ok) throw new Error(data.msg || 'Error al cargar secciones');
    pickerData = data;
    return data;
}

async function abrirModalZona(id) {
    mzEditandoId = id;
    mzSelSet     = new Set();

    const zona = id ? appData.zonas.find(z => z.id === id) : null;
    document.getElementById('mz-titulo').textContent   = zona ? `Editar ${zona.nombre}` : 'Nueva Zona';
    document.getElementById('mz-nombre').value   = zona?.nombre   || '';
    document.getElementById('mz-clave').value    = zona?.clave    || '';
    document.getElementById('mz-foco').value     = zona?.foco     || '';
    document.getElementById('mz-colonias').value = zona?.colonias || '';
    document.getElementById('mz-meta').value     = zona?.meta     || '';
    document.getElementById('mz-sec-search').value = '';
    (zona?.secciones || []).forEach(s => mzSelSet.add(String(s)));

    renderMuniLegendModal();
    document.getElementById('modal-zona').classList.add('open');

    try {
        await cargarPickerData();
        renderPickerZona();
    } catch(e) {
        document.getElementById('mz-sec-picker').innerHTML =
            `<div style="color:#d32f2f;font-size:12px;">${e.message}</div>`;
    }
}

function cerrarModalZona() {
    document.getElementById('modal-zona').classList.remove('open');
}

function renderPickerZona() {
    if (!pickerData) return;
    const filtro = (document.getElementById('mz-sec-search').value || '').trim();
    const cont   = document.getElementById('mz-sec-picker');

    const html = pickerData.secciones
        .filter(s => !filtro || s.includes(filtro))
        .map(s => {
            const sel       = mzSelSet.has(s);
            // Ocupada por OTRA zona (no la que estamos editando)
            const dueno     = pickerData.ocupadas_por[s];
            const ocupada   = dueno !== undefined && dueno !== mzEditandoId && !sel;
            const cls = 'sec-chip' + (sel ? ' sel' : '') + (ocupada ? ' ocupada' : '');
            const click = ocupada ? '' : `onclick="toggleSecZona('${s}')"`;
            const title = ocupada ? 'title="Asignada a otra zona"' : '';
            return `<span class="${cls}" ${click} ${title}>${s}</span>`;
        }).join('');

    cont.innerHTML = html || '<div style="color:var(--text-muted);font-size:12px;">Sin resultados.</div>';
    actualizarResumenPicker();
}

function toggleSecZona(s) {
    mzSelSet.has(s) ? mzSelSet.delete(s) : mzSelSet.add(s);
    renderPickerZona();
}

function actualizarResumenPicker() {
    const n  = mzSelSet.size;
    document.getElementById('mz-sec-count').textContent = `${n} sección${n!==1?'es':''} seleccionada${n!==1?'s':''}`;
    let ln = 0;
    mzSelSet.forEach(s => { ln += (pickerData?.ln?.[s] || 0); });
    const badge = document.getElementById('mz-ln-badge');
    if (ln > 0) {
        badge.textContent   = `${ln.toLocaleString('es-MX')} de lista nominal`;
        badge.style.display = '';
    } else {
        badge.style.display = 'none';
    }
}

async function guardarZona() {
    const nombre = document.getElementById('mz-nombre').value.trim();
    if (!nombre) { gbToast('El nombre es requerido.'); return; }

    const payload = {
        id:        mzEditandoId || 0,
        nombre,
        clave:     document.getElementById('mz-clave').value.trim(),
        foco:      document.getElementById('mz-foco').value.trim(),
        colonias:  document.getElementById('mz-colonias').value.trim(),
        meta:      parseInt(document.getElementById('mz-meta').value) || 0,
        secciones: [...mzSelSet],
    };

    try {
        const res  = await fetch(`${window.location.pathname}?api=guardar_zona`, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify(payload),
        });
        const data = await res.json();
        if (!data.ok) { gbToast(data.msg || 'Error al guardar.'); return; }

        cerrarModalZona();
        pickerData = null;            // invalidar cache (ocupadas cambiaron)
        gbToast(mzEditandoId ? '✓ Zona actualizada.' : '✓ Zona creada.');
        if (!mzEditandoId) zonaActiva = data.id;
        await cargarDatos();
        renderSecciones();
    } catch(e) {
        gbToast('Error de red al guardar.');
    }
}
</script>
</body>
</html>