<?php
if (file_exists(__DIR__ . '/../../includes/bootstrap.php')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
} else {
    session_start();
}

$rol = $_SESSION['user_role'] ?? '';
$permitidos = ['admin','administrador','superadmin','super admin','coordinador_estatal','coordinador estatal','estatal'];
if (!in_array($rol, $permitidos)) {
    header('Location: /public/index.php');
    exit();
}

$estado = $_SESSION['user_estado'] ?? 'Sonora';

/* ══════════════════════════════════════════════════════════════════
   API JSON
══════════════════════════════════════════════════════════════════ */
if (isset($_GET['api'])) {
    header('Content-Type: application/json; charset=utf-8');
    $accion = $_GET['api'] ?? '';

    try {

        /* ── MUNICIPIOS BASE: seccion[] por municipio (sin LN) ──────── */
        if ($accion === 'municipios_base') {
            $stmt = $pdo->prepare("SELECT id FROM estados WHERE nombre = ? LIMIT 1");
            $stmt->execute([$estado]);
            $erow = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$erow) { echo json_encode(['ok'=>false,'msg'=>'Estado no encontrado']); exit(); }
            $eid = $erow['id'];

            $stmt = $pdo->prepare("SELECT id, nombre FROM municipios WHERE estado_id = ? ORDER BY nombre");
            $stmt->execute([$eid]);
            $munis = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $base = [];
            foreach ($munis as $m) {
                $s2 = $pdo->prepare("SELECT seccion FROM secciones WHERE estado_id = ? AND municipio_id = ? ORDER BY CAST(seccion AS UNSIGNED)");
                $s2->execute([$eid, $m['id']]);
                $secs = array_column($s2->fetchAll(PDO::FETCH_ASSOC), 'seccion');
                if (!empty($secs)) $base[$m['nombre']] = $secs;
            }
            echo json_encode(['ok' => true, 'base' => $base], JSON_UNESCAPED_UNICODE);
            exit();
        }

        /* ── DISTRITOS BASE: seccion[] por distrito local / federal (sin LN) ──────── */
        if ($accion === 'distritos_base') {
            $tipoDist = ($_GET['tipo'] ?? '') === 'distrito_federal' ? 'distrito_federal' : 'distrito_local';
            $col = $tipoDist === 'distrito_federal' ? 'distrito_federal' : 'distrito_local';

            $stmt = $pdo->prepare("
                SELECT DISTINCT $col AS distrito
                FROM secciones
                WHERE estado_nombre = ? AND $col <> ''
                ORDER BY CAST($col AS UNSIGNED)
            ");
            $stmt->execute([$estado]);
            $distritos = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'distrito');

            $base = [];
            foreach ($distritos as $d) {
                $s2 = $pdo->prepare("SELECT seccion FROM secciones WHERE estado_nombre = ? AND $col = ? ORDER BY CAST(seccion AS UNSIGNED)");
                $s2->execute([$estado, $d]);
                $secs = array_column($s2->fetchAll(PDO::FETCH_ASSOC), 'seccion');
                if (!empty($secs)) $base[$d] = $secs;
            }
            echo json_encode(['ok' => true, 'base' => $base], JSON_UNESCAPED_UNICODE);
            exit();
        }

        /* ── LN POR ESTADO: todas las secciones del estado (para zona nueva sin región fija) ── */
        if ($accion === 'ln_por_estado') {
            $stmt = $pdo->prepare("
                SELECT sec.seccion,
                       COALESCE(se.lista_nominal, se.padron_electoral, 0) AS ln
                FROM secciones sec
                LEFT JOIN secciones_electorales se
                       ON se.nombre_seccion = sec.seccion
                      AND UPPER(TRIM(se.estado)) = UPPER(TRIM(sec.estado_nombre))
                      AND se.activo = 1
                WHERE sec.estado_nombre = ?
                ORDER BY CAST(sec.seccion AS UNSIGNED)
            ");
            $stmt->execute([$estado]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $ln = [];
            foreach ($rows as $r) { $ln[(string)$r['seccion']] = (int)$r['ln']; }
            echo json_encode(['ok' => true, 'ln' => $ln], JSON_UNESCAPED_UNICODE);
            exit();
        }

        /* ── LN POR REGIÓN: seccion=>ln solo para los municipios de una región ── */
        if ($accion === 'ln_por_region') {
            $region_id = (int)($_GET['region_id'] ?? 0);
            if (!$region_id) { echo json_encode(['ok'=>false,'msg'=>'region_id requerido']); exit(); }

            $stmt = $pdo->prepare("SELECT municipios, tipo_integracion FROM regiones_estatales WHERE id = ? AND estado = ? LIMIT 1");
            $stmt->execute([$region_id, $estado]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) { echo json_encode(['ok'=>false,'msg'=>'Región no encontrada']); exit(); }

            $municipios = json_decode($row['municipios'] ?? '[]', true) ?: [];
            $tipoIntegrLN = $row['tipo_integracion'] ?: 'municipios';
            if (empty($municipios)) { echo json_encode(['ok'=>true,'ln'=>[]]); exit(); }

            $placeholders = implode(',', array_fill(0, count($municipios), '?'));
            $params = array_merge([$estado], $municipios);

            if ($tipoIntegrLN === 'distrito_local') {
                $filtroCol = 'sec.distrito_local';
            } elseif ($tipoIntegrLN === 'distrito_federal') {
                $filtroCol = 'sec.distrito_federal';
            } else {
                $filtroCol = 'm.nombre';
            }

            $stmt = $pdo->prepare("
                SELECT sec.seccion,
                       COALESCE(se.lista_nominal, se.padron_electoral, 0) AS ln
                FROM secciones sec
                LEFT JOIN municipios m ON m.id = sec.municipio_id
                LEFT JOIN secciones_electorales se
                       ON se.nombre_seccion = sec.seccion
                      AND UPPER(TRIM(se.estado)) = UPPER(TRIM(sec.estado_nombre))
                      AND se.activo = 1
                WHERE sec.estado_nombre = ?
                  AND $filtroCol IN ($placeholders)
                ORDER BY CAST(sec.seccion AS UNSIGNED)
            ");
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $ln = [];
            foreach ($rows as $r) { $ln[(string)$r['seccion']] = (int)$r['ln']; }
            echo json_encode(['ok' => true, 'ln' => $ln], JSON_UNESCAPED_UNICODE);
            exit();
        }

        /* ── CARGAR toda la estructura del estado ─────────────────── */
        if ($accion === 'cargar') {
            $stmt = $pdo->prepare("
                SELECT * FROM regiones_estatales
                WHERE estado = ?
                ORDER BY id ASC
            ");
            $stmt->execute([$estado]);
            $estructuras = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($estructuras as &$est) {
                $est['id']               = (int)$est['id'];
                $est['meta']             = (int)$est['meta'];
                $est['municipios']       = json_decode($est['municipios'] ?? '[]', true) ?: [];
                $est['tipo_integracion'] = $est['tipo_integracion'] ?: 'municipios';
                $est['responsable']      = $est['coordinador_id'] ?? null;

                // zonas de esta estructura
                $stmt2 = $pdo->prepare("
                    SELECT * FROM zonas_estatales
                    WHERE estructura_id = ?
                    ORDER BY id ASC
                ");
                $stmt2->execute([$est['id']]);
                $zonas = $stmt2->fetchAll(PDO::FETCH_ASSOC);

                foreach ($zonas as &$z) {
                    $z['id']            = (int)$z['id'];
                    $z['estructura_id'] = (int)$z['estructura_id'];
                    $z['meta']          = (int)$z['meta'];
                    $z['miembros']      = (int)$z['miembros'];
                    $z['secciones']     = json_decode($z['secciones'] ?? '[]', true) ?: [];
                    $z['responsable']   = $z['coordinador_id'] ?? null;
                }
                unset($z);
                $est['zonas'] = $zonas;
            }
            unset($est);

            echo json_encode(['ok' => true, 'regiones' => $estructuras]);
            exit();
        }

        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        /* ── GUARDAR / EDITAR REGIÓN (regiones_estatales) ─────── */
        if ($accion === 'guardar_region') {
            $id          = isset($body['id']) ? (int)$body['id'] : 0;
            $nombre      = trim($body['nombre']      ?? '');
            $desc        = trim($body['desc']        ?? '');
            $meta        = (int)($body['meta']       ?? 0);
            
            $coordinador_id = isset($body['responsable']) && $body['responsable'] !== '' ? (int)$body['responsable'] : null;
            $municipios  = json_encode($body['municipios'] ?? [], JSON_UNESCAPED_UNICODE);
            $tipoIntegr  = trim($body['tipo_integracion'] ?? 'municipios');
            if (!in_array($tipoIntegr, ['municipios','distrito_local','distrito_federal'], true)) {
                $tipoIntegr = 'municipios';
            }

            if (!$nombre) { echo json_encode(['ok'=>false,'msg'=>'Nombre requerido']); exit(); }

            try {
                $colsChk = array_column($pdo->query("SHOW COLUMNS FROM regiones_estatales")->fetchAll(PDO::FETCH_ASSOC), 'Field');
                if (!in_array('tipo_integracion', $colsChk)) {
                    $pdo->exec("ALTER TABLE regiones_estatales ADD COLUMN tipo_integracion ENUM('municipios','distrito_local','distrito_federal') NOT NULL DEFAULT 'municipios' AFTER municipios");
                }
            } catch(Exception $eMig) { /* silencioso */ }

            $eventos_actualizados = 0;
            if ($id) {
                // verificar que pertenece al estado actual (y leer el nombre anterior)
                $chk = $pdo->prepare("SELECT id, nombre FROM regiones_estatales WHERE id = ? AND estado = ?");
                $chk->execute([$id, $estado]);
                $regActual = $chk->fetch(PDO::FETCH_ASSOC);
                if (!$regActual) { echo json_encode(['ok'=>false,'msg'=>'Región no encontrada']); exit(); }
                $nombre_anterior = trim((string)($regActual['nombre'] ?? ''));

                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare("
                        UPDATE regiones_estatales
                        SET nombre=?, descripcion=?, meta=?, municipios=?, tipo_integracion=?, coordinador_id=?
                        WHERE id=? AND estado=?
                    ");
                    $stmt->execute([$nombre, $desc, $meta, $municipios, $tipoIntegr, $coordinador_id, $id, $estado]);

                    // agenda_eventos.region guarda el NOMBRE como texto: al renombrar
                    // la región se propagan los eventos para que el coordinador
                    // regional los siga viendo en su agenda.
                    if ($nombre_anterior !== '' && $nombre_anterior !== $nombre) {
                        // Si otra región del estado conserva el nombre anterior, no se
                        // puede saber a cuál pertenecen esos eventos: no se tocan.
                        $stDup = $pdo->prepare("SELECT COUNT(*) FROM regiones_estatales WHERE estado = ? AND nombre = ? AND id <> ?");
                        $stDup->execute([$estado, $nombre_anterior, $id]);
                        if ((int)$stDup->fetchColumn() === 0) {
                            $stAg = $pdo->prepare("UPDATE agenda_eventos SET region = ? WHERE estado = ? AND region = ?");
                            $stAg->execute([$nombre, $estado, $nombre_anterior]);
                            $eventos_actualizados = $stAg->rowCount();
                        }
                    }
                    $pdo->commit();
                } catch (Exception $eTx) {
                    $pdo->rollBack();
                    throw $eTx;
                }
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO regiones_estatales (estado, nombre, descripcion, meta, municipios, tipo_integracion, coordinador_id)
                    VALUES (?,?,?,?,?,?,?)
                ");
                $stmt->execute([$estado, $nombre, $desc, $meta, $municipios, $tipoIntegr, $coordinador_id]);
                $id = (int)$pdo->lastInsertId();
            }

            echo json_encode(['ok' => true, 'id' => $id, 'eventos_actualizados' => $eventos_actualizados]);
            exit();
        }

        /* ── ELIMINAR REGIÓN ─────────────────────────────────────── */
        if ($accion === 'eliminar_region') {
            $id = (int)($body['id'] ?? 0);
            $stmt = $pdo->prepare("DELETE FROM regiones_estatales WHERE id = ? AND estado = ?");
            $stmt->execute([$id, $estado]);
            echo json_encode(['ok' => true]);
            exit();
        }

        /* ── GUARDAR / EDITAR ZONA (zonas_estatales) ─────────────── */
        if ($accion === 'guardar_zona') {
            $id            = isset($body['id']) ? (int)$body['id'] : 0;
            $estructura_id = (int)($body['region_id'] ?? 0);
            $nombre        = trim($body['nombre']   ?? '');
            $clave         = trim($body['clave']    ?? '');
            $foco          = trim($body['foco']     ?? '');
            $colonias      = trim($body['colonias'] ?? '');
            $meta          = (int)($body['meta']    ?? 0);
            
            $coordinador_id = isset($body['responsable']) && $body['responsable'] !== '' ? (int)$body['responsable'] : null;
            $secciones    = json_encode($body['secciones'] ?? [], JSON_UNESCAPED_UNICODE);

            if (!$nombre)       { echo json_encode(['ok'=>false,'msg'=>'Nombre requerido']); exit(); }
            if (!$estructura_id){ echo json_encode(['ok'=>false,'msg'=>'Región requerida']); exit(); }

            // verificar que la región pertenece al estado
            $chk = $pdo->prepare("SELECT id FROM regiones_estatales WHERE id = ? AND estado = ?");
            $chk->execute([$estructura_id, $estado]);
            if (!$chk->fetch()) { echo json_encode(['ok'=>false,'msg'=>'Región no válida']); exit(); }

            // verificar que ninguna sección ya esté asignada en otra zona del mismo estado
            $seccionesArr = $body['secciones'] ?? [];
            if (!empty($seccionesArr)) {
                $placeholders = implode(',', array_fill(0, count($seccionesArr), '?'));
                // Obtener todas las zonas del estado excluyendo la zona actual
                $sqlConflicto = "
                    SELECT z.id, z.nombre, z.secciones, r.nombre AS region_nombre
                    FROM zonas_estatales z
                    INNER JOIN regiones_estatales r ON r.id = z.estructura_id
                    WHERE r.estado = ? AND z.id != ?
                ";
                $stmtConf = $pdo->prepare($sqlConflicto);
                $stmtConf->execute([$estado, $id ?: 0]);
                $otrasZonas = $stmtConf->fetchAll(PDO::FETCH_ASSOC);
                $conflictos = [];
                foreach ($otrasZonas as $oz) {
                    $secsOtra = json_decode($oz['secciones'] ?? '[]', true) ?: [];
                    $duplicadas = array_intersect($seccionesArr, $secsOtra);
                    if (!empty($duplicadas)) {
                        $conflictos[] = 'Sección(es) ' . implode(', ', $duplicadas) . ' ya asignada(s) en zona "' . $oz['nombre'] . '" (Región: ' . $oz['region_nombre'] . ')';
                    }
                }
                if (!empty($conflictos)) {
                    echo json_encode(['ok'=>false,'msg'=>implode(' | ', $conflictos)]);
                    exit();
                }
            }

            $eventos_actualizados = 0;
            if ($id) {
                // Nombre anterior de la zona + nombre de su región (para propagar a la agenda)
                $stOld = $pdo->prepare("
                    SELECT z.nombre AS zona_nombre, r.nombre AS region_nombre
                    FROM zonas_estatales z
                    INNER JOIN regiones_estatales r ON r.id = z.estructura_id
                    WHERE z.id = ? AND r.estado = ? LIMIT 1
                ");
                $stOld->execute([$id, $estado]);
                $zonaOld = $stOld->fetch(PDO::FETCH_ASSOC) ?: [];
                $nombre_anterior = trim((string)($zonaOld['zona_nombre'] ?? ''));

                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare("
                        UPDATE zonas_estatales
                        SET nombre=?, clave=?, foco=?, colonias=?, meta=?, secciones=?, coordinador_id=?
                        WHERE id=? AND estructura_id=?
                    ");
                    $stmt->execute([$nombre, $clave, $foco, $colonias, $meta, $secciones, $coordinador_id, $id, $estructura_id]);

                    // agenda_eventos.zona guarda el NOMBRE como texto: propagar el renombre
                    if ($nombre_anterior !== '' && $nombre_anterior !== $nombre) {
                        // Si otra zona del estado comparte el nombre anterior, limitar
                        // el cambio a los eventos de la región de esta zona.
                        $stDup = $pdo->prepare("
                            SELECT COUNT(*) FROM zonas_estatales z
                            INNER JOIN regiones_estatales r ON r.id = z.estructura_id
                            WHERE r.estado = ? AND z.nombre = ? AND z.id <> ?
                        ");
                        $stDup->execute([$estado, $nombre_anterior, $id]);
                        if ((int)$stDup->fetchColumn() > 0) {
                            $stAg = $pdo->prepare("UPDATE agenda_eventos SET zona = ? WHERE estado = ? AND zona = ? AND region = ?");
                            $stAg->execute([$nombre, $estado, $nombre_anterior, (string)($zonaOld['region_nombre'] ?? '')]);
                        } else {
                            $stAg = $pdo->prepare("UPDATE agenda_eventos SET zona = ? WHERE estado = ? AND zona = ?");
                            $stAg->execute([$nombre, $estado, $nombre_anterior]);
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
                    INSERT INTO zonas_estatales (estructura_id, nombre, clave, foco, colonias, meta, secciones, coordinador_id)
                    VALUES (?,?,?,?,?,?,?,?)
                ");
                $stmt->execute([$estructura_id, $nombre, $clave, $foco, $colonias, $meta, $secciones, $coordinador_id]);
                $id = (int)$pdo->lastInsertId();
            }

            echo json_encode(['ok' => true, 'id' => $id, 'eventos_actualizados' => $eventos_actualizados]);
            exit();
        }

        /* ── ELIMINAR ZONA ───────────────────────────────────────── */
        if ($accion === 'eliminar_zona') {
            $id            = (int)($body['id']         ?? 0);
            $estructura_id = (int)($body['region_id'] ?? 0);

            // verificar que la zona pertenece al estado a través de la región
            $stmt = $pdo->prepare("
                DELETE z FROM zonas_estatales z
                INNER JOIN regiones_estatales r ON r.id = z.estructura_id
                WHERE z.id = ? AND r.estado = ?
            ");
            $stmt->execute([$id, $estado]);
            echo json_encode(['ok' => true]);
            exit();
        }

        /* ── GUARDAR SECCIONES de una zona ───────────────────────── */
        if ($accion === 'guardar_secciones') {
            $zona_id   = (int)($body['zona_id']  ?? 0);
            $secciones = json_encode($body['secciones'] ?? [], JSON_UNESCAPED_UNICODE);

            // verificar que la zona pertenece al estado
            $chk = $pdo->prepare("
                SELECT z.id FROM zonas_estatales z
                INNER JOIN regiones_estatales r ON r.id = z.estructura_id
                WHERE z.id = ? AND r.estado = ?
            ");
            $chk->execute([$zona_id, $estado]);
            if (!$chk->fetch()) { echo json_encode(['ok'=>false,'msg'=>'Zona no válida']); exit(); }

            // verificar que ninguna sección ya esté asignada en otra zona del mismo estado
            $seccionesArr = $body['secciones'] ?? [];
            if (!empty($seccionesArr)) {
                $stmtConf = $pdo->prepare("
                    SELECT z.id, z.nombre, z.secciones, r.nombre AS region_nombre
                    FROM zonas_estatales z
                    INNER JOIN regiones_estatales r ON r.id = z.estructura_id
                    WHERE r.estado = ? AND z.id != ?
                ");
                $stmtConf->execute([$estado, $zona_id]);
                $otrasZonas = $stmtConf->fetchAll(PDO::FETCH_ASSOC);
                $conflictos = [];
                foreach ($otrasZonas as $oz) {
                    $secsOtra = json_decode($oz['secciones'] ?? '[]', true) ?: [];
                    $duplicadas = array_intersect($seccionesArr, $secsOtra);
                    if (!empty($duplicadas)) {
                        $conflictos[] = 'Sección(es) ' . implode(', ', $duplicadas) . ' ya asignada(s) en zona "' . $oz['nombre'] . '" (Región: ' . $oz['region_nombre'] . ')';
                    }
                }
                if (!empty($conflictos)) {
                    echo json_encode(['ok'=>false,'msg'=>implode(' | ', $conflictos)]);
                    exit();
                }
            }

            $stmt = $pdo->prepare("UPDATE zonas_estatales SET secciones=? WHERE id=?");
            $stmt->execute([$secciones, $zona_id]);

            echo json_encode(['ok' => true]);
            exit();
        }

        echo json_encode(['ok' => false, 'msg' => 'Acción desconocida']);

    } catch (Exception $e) {
        error_log('zonas_ce API error: ' . $e->getMessage());
        echo json_encode(['ok' => false, 'msg' => 'Error del servidor: ' . $e->getMessage()]);
    }
    exit();
}

/* ══════════════════════════════════════════════════════════════════
   MUNICIPIOS BASE y SECCION_LN_MAP se cargan via API (lazy)
   Ver acciones: ?api=municipios_base  y  ?api=ln_por_region
══════════════════════════════════════════════════════════════════ */

/* ══════════════════════════════════════════════════════════════════
   USUARIOS BASE (para selectores de responsables)
══════════════════════════════════════════════════════════════════ */
$usuarios_base = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, TRIM(CONCAT(COALESCE(nombres,''),' ',COALESCE(ap_paterno,''),' ',COALESCE(ap_materno,''))) AS nombre, 'Regional' as rol 
        FROM coordinador_regional WHERE estado = ? AND estatus='activo'
        
        UNION ALL
        
        SELECT id, TRIM(CONCAT(COALESCE(nombres,''),' ',COALESCE(ap_paterno,''),' ',COALESCE(ap_materno,''))) AS nombre, 'Zona' as rol 
        FROM coordinador_zona WHERE estado = ? AND estatus='activo'
        
        UNION ALL
        
        SELECT id, nombre_completo AS nombre, rol 
        FROM usuarios WHERE estado = ? AND activo = 1 AND rol IN ('regional', 'zona', 'coordinador', 'operador', 'coordinador_regional', 'coordinador_zona')
        
        ORDER BY nombre ASC
    ");
    $stmt->execute([$estado, $estado, $estado]);
    $usuarios_base = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('zonas_ce error BD usuarios: ' . $e->getMessage());
}
$usuarios_base_json = json_encode($usuarios_base, JSON_UNESCAPED_UNICODE);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estructuras - <?php echo htmlspecialchars($estado); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    :root {
        --primary:#2d7a3e; --primary-dark:#1f5a2d; --dark:#1a1a1a; --light:#f0f7f0;
        --white:#ffffff; --gray-100:#f8f9fa; --gray-200:#e9ecef; --text-muted:#6c757d;
        --shadow-sm:0 10px 30px rgba(0,0,0,0.05);
    }
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family:'Arial',sans-serif; background:var(--light); color:var(--dark); overflow-x:hidden; }

    .container { max-width:100%; margin:0; background:var(--white); min-height:100vh; position:relative; padding-left:330px; padding-right:50px; padding-bottom:60px; transition:padding-left 0.3s ease; }
    .design-text { position:fixed; left:290px; top:50%; writing-mode:vertical-rl; font-size:13px; font-weight:500; color:var(--primary); opacity:0.3; letter-spacing:2px; z-index:0; pointer-events:none; }

    .page-header { padding:50px 0 30px 0; position:relative; z-index:2; display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:20px; border-bottom:2px solid var(--gray-200); margin-bottom:30px; }
    .ai-badge { color:var(--primary); font-size:13px; font-weight:800; letter-spacing:4px; margin-bottom:15px; display:inline-block; text-transform:uppercase; background:rgba(45,122,62,0.1); padding:5px 15px; border-radius:20px; }
    .main-title { font-size:54px; font-weight:900; line-height:1; margin-bottom:15px; color:var(--dark); }
    .description { color:#666; font-size:16px; line-height:1.6; max-width:600px; }

    .btn-primary-action { background:var(--primary); color:white; border:none; padding:12px 25px; border-radius:12px; font-weight:700; cursor:pointer; transition:all 0.3s; display:inline-flex; align-items:center; gap:10px; font-size:14px; box-shadow:0 4px 15px rgba(45,122,62,0.3); font-family:Arial,sans-serif; }
    .btn-primary-action:hover { background:var(--primary-dark); transform:translateY(-2px); }
    .btn-secondary-action { background:var(--white); color:var(--text-muted); border:1px solid var(--gray-200); padding:10px 18px; border-radius:10px; font-weight:600; cursor:pointer; transition:0.3s; display:inline-flex; align-items:center; gap:8px; font-size:13px; font-family:Arial,sans-serif; }
    .btn-secondary-action:hover { border-color:var(--dark); color:var(--dark); }
    .btn-sm { padding:6px 14px; font-size:12px; border-radius:8px; }

    .workspace { display:grid; grid-template-columns:280px 1fr 1fr; gap:0; border:1px solid var(--gray-200); border-radius:20px; overflow:hidden; box-shadow:var(--shadow-sm); min-height:600px; }

    .col-regiones { background:var(--white); border-right:1px solid var(--gray-200); display:flex; flex-direction:column; }
    .col-header { padding:18px 20px; border-bottom:1px solid var(--gray-200); display:flex; align-items:center; justify-content:space-between; background:var(--gray-100); }
    .col-header-title { font-size:14px; font-weight:800; color:var(--dark); display:flex; align-items:center; gap:8px; }
    .col-body { flex:1; overflow-y:auto; padding:12px; }
    .col-body::-webkit-scrollbar { width:4px; }
    .col-body::-webkit-scrollbar-thumb { background:#ddd; border-radius:4px; }

    .region-card, .zona-card { background:var(--white); border:1px solid var(--gray-200); border-radius:14px; padding:16px 18px; margin-bottom:10px; cursor:pointer; transition:all 0.2s; position:relative; }
    .region-card:hover, .zona-card:hover { border-color:var(--primary); box-shadow:0 4px 14px rgba(45,122,62,0.1); }
    .region-card.active, .zona-card.active { border-color:var(--primary); background:rgba(45,122,62,0.04); box-shadow:0 4px 14px rgba(45,122,62,0.12); }
    .card-name { font-size:16px; font-weight:700; color:var(--dark); margin-bottom:6px; }
    .card-meta { display:flex; justify-content:space-between; align-items:center; font-size:13px; }
    .card-miembros { color:var(--text-muted); }
    .card-pct { color:var(--primary); font-weight:800; font-size:14px; }
    .card-bar-track { height:3px; background:var(--gray-200); border-radius:99px; margin-top:8px; overflow:hidden; }
    .card-bar-fill { height:100%; background:var(--primary); border-radius:99px; transition:width 0.4s; }
    .card-actions { position:absolute; top:10px; right:10px; display:flex; gap:4px; opacity:0; transition:0.2s; }
    .region-card:hover .card-actions, .zona-card:hover .card-actions { opacity:1; }
    .card-action-btn { background:var(--gray-100); border:1px solid var(--gray-200); border-radius:6px; padding:4px 8px; font-size:11px; cursor:pointer; color:var(--text-muted); transition:0.2s; font-family:Arial,sans-serif; }
    .card-action-btn:hover { background:var(--primary); color:white; border-color:var(--primary); }
    .card-action-btn.del:hover { background:#d32f2f; border-color:#d32f2f; color:white; }
    .card-secciones-count { font-size:11px; color:var(--text-muted); margin-top:4px; }
    .card-meta-bottom { margin-top:4px; font-size:11px; color:var(--text-muted); font-style:italic; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

    .empty-col { text-align:center; padding:40px 20px; color:var(--text-muted); font-size:13px; }
    .empty-col i { display:block; font-size:28px; opacity:0.25; margin-bottom:10px; }

    .col-zonas { border-right:1px solid var(--gray-200); display:flex; flex-direction:column; background:var(--white); }
    .col-secciones { display:flex; flex-direction:column; background:var(--white); }

    .list-scroll { flex:1; overflow-y:auto; }
    .list-scroll::-webkit-scrollbar { width:4px; }
    .list-scroll::-webkit-scrollbar-thumb { background:#ddd; border-radius:4px; }

    .seccion-item { padding:10px 20px; font-size:13px; border-bottom:1px solid var(--gray-200); display:flex; align-items:center; justify-content:space-between; transition:background 0.15s; }
    .seccion-item:hover { background:var(--gray-100); }
    .seccion-num { font-weight:700; font-size:13px; }
    .seccion-ln { font-size:11px; font-weight:700; color:#92400e; background:#fef3c7; border:1px solid #fde68a; border-radius:5px; padding:2px 8px; margin-left:auto; margin-right:10px; }
    .seccion-assigned { color:var(--primary); font-size:11px; font-weight:700; display:flex; align-items:center; gap:4px; }

    .zona-info-box { margin:12px 16px; padding:14px 16px; background:rgba(45,122,62,0.05); border:1px solid rgba(45,122,62,0.2); border-radius:12px; font-size:13px; }
    .zona-info-box .zi-title { font-weight:800; font-size:14px; color:var(--dark); margin-bottom:4px; }
    .zona-info-box .zi-foco  { font-style:italic; color:var(--text-muted); margin-bottom:6px; font-size:12px; }
    .zona-info-box .zi-bullet { font-size:12px; color:var(--dark); margin-bottom:3px; display:flex; gap:6px; }
    .zona-info-box .zi-bullet strong { color:var(--primary); }

    .hint-text { text-align:center; padding:30px 16px; color:var(--text-muted); font-size:13px; }
    .hint-text i { display:block; font-size:24px; opacity:0.2; margin-bottom:8px; }

    /* Toast */
    .toast { position:fixed; bottom:30px; right:30px; background:var(--dark); color:white; padding:12px 20px; border-radius:12px; font-size:13px; font-weight:600; z-index:9999; transform:translateY(80px); opacity:0; transition:all 0.35s cubic-bezier(0.175,0.885,0.32,1.275); pointer-events:none; display:flex; align-items:center; gap:8px; }
    .toast.show { transform:translateY(0); opacity:1; }
    .toast.ok  { background:#2d7a3e; }
    .toast.err { background:#d32f2f; }

    /* Modal */
    .modal { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(255,255,255,0.9); backdrop-filter:blur(5px); z-index:2000; align-items:center; justify-content:center; opacity:0; transition:opacity 0.3s ease; }
    .modal.open { display:flex; opacity:1; }
    .modal-content { background:var(--white); border-radius:20px; border:1px solid var(--gray-200); box-shadow:0 25px 50px rgba(0,0,0,0.1); width:90%; max-width:560px; padding:40px; position:relative; transform:translateY(30px); opacity:0; transition:all 0.4s ease-out; max-height:90vh; overflow-y:auto; }
    .modal.open .modal-content { transform:translateY(0); opacity:1; }
    .close-modal { position:absolute; top:25px; right:25px; font-size:22px; cursor:pointer; color:#ccc; width:40px; height:40px; display:flex; align-items:center; justify-content:center; border-radius:50%; transition:all 0.3s cubic-bezier(0.175,0.885,0.32,1.275); }
    .close-modal:hover { color:var(--primary); background:#f0f7f0; transform:rotate(90deg) scale(1.1); }
    .form-group { display:flex; flex-direction:column; gap:8px; margin-bottom:18px; }
    .form-label { font-size:12px; font-weight:700; text-transform:uppercase; color:var(--text-muted); letter-spacing:0.5px; }
    .form-input-styled { width:100%; padding:12px 15px; border-radius:10px; border:2px solid var(--gray-200); background:var(--gray-100); font-size:14px; font-weight:600; color:var(--dark); transition:all 0.3s; outline:none; appearance:none; font-family:Arial,sans-serif; }
    .form-input-styled:focus { border-color:var(--primary); background:var(--white); }
    textarea.form-input-styled { resize:vertical; min-height:80px; font-weight:400; }
    .modal-footer { display:flex; justify-content:flex-end; gap:10px; margin-top:28px; padding-top:22px; border-top:1px solid var(--gray-200); }
    .btn-save { background:var(--primary); color:white; border:none; padding:12px 28px; border-radius:12px; font-weight:700; cursor:pointer; transition:all 0.3s; font-size:14px; box-shadow:0 4px 15px rgba(45,122,62,0.3); font-family:Arial,sans-serif; }
    .btn-save:hover { background:var(--primary-dark); }
    .btn-save:disabled { opacity:0.6; cursor:not-allowed; transform:none; }
    .btn-cancel { background:var(--white); color:var(--text-muted); border:1px solid var(--gray-200); padding:12px 22px; border-radius:12px; font-weight:600; cursor:pointer; transition:0.3s; font-size:13px; font-family:Arial,sans-serif; }
    .btn-cancel:hover { border-color:var(--dark); color:var(--dark); }

    .seccion-picker { display:flex; flex-wrap:wrap; gap:6px; max-height:180px; overflow-y:auto; padding:10px; background:var(--gray-100); border-radius:10px; border:2px solid var(--gray-200); }
    .seccion-tag { padding:4px 10px; border-radius:6px; border:1px solid var(--gray-200); font-size:12px; font-weight:600; cursor:pointer; background:var(--white); color:var(--text-muted); transition:0.15s; }
    .seccion-tag.selected { background:var(--primary); color:white; border-color:var(--primary); }
    .seccion-tag.ocupada { background:#f5f5f5; color:#bbb; border-color:#e0e0e0; cursor:not-allowed; text-decoration:line-through; opacity:0.65; }
    .seccion-tag.ocupada::after { content:' ✗'; font-size:9px; }
    .seccion-picker-search { width:100%; padding:8px 12px; border-radius:8px; border:1px solid var(--gray-200); background:var(--white); font-size:13px; outline:none; margin-bottom:8px; font-family:Arial,sans-serif; }
    .legend-box { font-size:12px; color:var(--primary); background:rgba(45,122,62,0.1); padding:10px 14px; border-radius:8px; margin-bottom:10px; font-weight:600; display:none; line-height:1.4; border:1px solid rgba(45,122,62,0.2); }

    .animate-up { animation:fadeInUp 0.6s ease forwards; opacity:0; }
    @keyframes fadeInUp { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }
    .animate-delay-1 { animation-delay:0.1s; }

    @media(max-width:1100px){ .container{padding-left:20px;padding-right:20px;} .workspace{grid-template-columns:1fr;} .design-text{display:none;} .col-regiones,.col-zonas{border-right:none;border-bottom:1px solid var(--gray-200);} }
    /* ─── fin estilos ─── */
    </style>
</head>
<body>
<?php if (file_exists(__DIR__ . '/../../includes/components/sidebar_ce.php')) include __DIR__ . '/../../includes/components/sidebar_ce.php'; ?>

<div class="design-text">ESTRUCTURA TERRITORIAL</div>

<div class="container">
    <div class="page-header animate-up">
        <div>
            <div class="ai-badge">VISTA COORDINADOR ESTATAL</div>
            <h1 class="main-title">Seleccionador<br>de Estructuras.</h1>
            <p class="description">Estructura operativa del estado de <strong><?php echo htmlspecialchars($estado); ?></strong>.</p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <button class="btn-secondary-action" onclick="abrirModalRegion()">
                <i class="fas fa-plus"></i> Nueva Región
            </button>
        </div>
    </div>

    <div class="workspace animate-up animate-delay-1">

        <div class="col-regiones">
            <div class="col-header">
                <div class="col-header-title"><i class="fas fa-map" style="color:var(--primary)"></i> Regiones</div>
                <button class="btn-primary-action btn-sm" onclick="abrirModalRegion()">
                    <i class="fas fa-plus"></i> Nueva
                </button>
            </div>
            <div class="col-body" id="col-regiones-body">
                <div class="empty-col"><i class="fas fa-circle-notch fa-spin"></i>Cargando...</div>
            </div>
        </div>

        <div class="col-zonas">
            <div class="col-header">
                <div class="col-header-title">
                    <i class="fas fa-layer-group" style="color:var(--primary)"></i> Zonas
                    <span id="col-zonas-subtitle" style="font-weight:400;color:var(--text-muted);font-size:12px;margin-left:4px;"></span>
                </div>
                <button class="btn-primary-action btn-sm" id="btn-nueva-zona" onclick="abrirModalZona()" style="display:none;">
                    <i class="fas fa-plus"></i> Nueva
                </button>
            </div>
            <div id="col-zonas-body" style="flex:1;display:flex;flex-direction:column;">
                <div class="hint-text"><i class="fas fa-arrow-left"></i>Selecciona una región.</div>
            </div>
        </div>

        <div class="col-secciones">
            <div class="col-header">
                <div class="col-header-title">
                    <i class="fas fa-list-ol" style="color:var(--primary)"></i> Secciones
                    <span id="col-sec-subtitle" style="font-weight:400;color:var(--text-muted);font-size:12px;margin-left:4px;"></span>
                </div>
                <div id="btns-asignar-sec" style="display:none;gap:6px;display:none;">
                    <button class="btn-primary-action btn-sm" id="btn-asignar-sec-lista" onclick="abrirModalAsignarSecciones()" title="Seleccionar desde lista">
                        <i class="fas fa-list"></i> Lista
                    </button>
                    <button class="btn-primary-action btn-sm" id="btn-asignar-sec-mapa" onclick="irAMapaDesdeColumna()" title="Seleccionar desde mapa del territorio" style="background:#1565c0;">
                        <i class="fas fa-map-marked-alt"></i> Mapa
                    </button>
                </div>
            </div>
            <div id="col-secciones-body" style="flex:1;display:flex;flex-direction:column;">
                <div class="hint-text"><i class="fas fa-arrow-left"></i>Selecciona una zona.</div>
            </div>
        </div>

    </div>
</div>

<div class="toast" id="toast"></div>

<div class="modal" id="modal-region">
    <div class="modal-content" style="max-width:600px;">
        <div class="close-modal" onclick="cerrarModal('modal-region')"><i class="fas fa-times"></i></div>
        <h2 id="modal-region-title" style="font-size:24px;font-weight:800;margin-bottom:5px;">Nueva Región</h2>
        <p style="font-size:14px;color:#999;margin-bottom:28px;">Agrega una región geográfica dentro del estado seleccionado.</p>
        
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div class="form-group">
                <label class="form-label">Nombre de la Región *</label>
                <input type="text" id="r-nombre" class="form-input-styled" placeholder="Ej. Región Norte">
            </div>
            <div class="form-group">
                <label class="form-label">Meta de Miembros</label>
                <input type="number" id="r-meta" class="form-input-styled" placeholder="0" min="0">
            </div>
        </div>

        <div class="form-group">
            <label class="form-label"><i class="fas fa-user-tie" style="color:var(--primary);margin-right:5px;"></i> Coordinador / Responsable</label>
            <select id="r-responsable" class="form-input-styled">
                <option value="">Cargando usuarios...</option>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">Descripción</label>
            <textarea id="r-desc" class="form-input-styled" placeholder="Descripción general de la región..." style="min-height: 60px;"></textarea>
        </div>

        <div class="form-group">
            <label class="form-label">Integradas por *</label>
            <div style="display:flex; gap:18px; margin-top:4px; flex-wrap:wrap;">
                <label style="display:flex; align-items:center; gap:6px; font-size:13px; cursor:pointer; font-weight:500;">
                    <input type="radio" name="r-tipo-integracion" value="municipios" checked onchange="cambiarTipoIntegracionRegion()"> Municipios
                </label>
                <label style="display:flex; align-items:center; gap:6px; font-size:13px; cursor:pointer; font-weight:500;">
                    <input type="radio" name="r-tipo-integracion" value="distrito_local" onchange="cambiarTipoIntegracionRegion()"> Distritos locales
                </label>
                <label style="display:flex; align-items:center; gap:6px; font-size:13px; cursor:pointer; font-weight:500;">
                    <input type="radio" name="r-tipo-integracion" value="distrito_federal" onchange="cambiarTipoIntegracionRegion()"> Distritos Federales
                </label>
            </div>
        </div>

        <div class="form-group">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <label class="form-label" id="r-muni-label">Asignados a la Región *</label>
                <button type="button" class="btn-secondary-action btn-sm" onclick="seleccionarTodosMunicipios()" style="padding:4px 8px; font-size:11px;">
                    <i class="fas fa-check-double"></i> Agregar Todos
                </button>
            </div>
            <input type="text" class="seccion-picker-search" id="r-muni-search" placeholder="Buscar (municipio / distrito local / distrito federal)..." oninput="filtrarMunicipiosPicker()">
            <div class="seccion-picker" id="muni-picker"></div>
            <div style="font-size:11px;color:var(--text-muted);margin-top:5px;" id="muni-counter">0 municipios seleccionados</div>
        </div>

        <div class="modal-footer">
            <button class="btn-cancel" onclick="cerrarModal('modal-region')">Cancelar</button>
            <button class="btn-save" id="btn-save-region" onclick="guardarRegion()"><i class="fas fa-check"></i> Guardar</button>
        </div>
    </div>
</div>

<div class="modal" id="modal-zona">
    <div class="modal-content" style="max-width:620px;">
        <div class="close-modal" onclick="cerrarModal('modal-zona')"><i class="fas fa-times"></i></div>
        <h2 id="modal-zona-title" style="font-size:24px;font-weight:800;margin-bottom:5px;">Nueva Zona</h2>
        <p style="font-size:14px;color:#999;margin-bottom:28px;">Define una zona dentro de la región seleccionada.</p>
        
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div class="form-group">
                <label class="form-label">Nombre de la Zona *</label>
                <input type="text" id="z-nombre" class="form-input-styled" placeholder="Ej. Zona Norte">
            </div>
            <div class="form-group">
                <label class="form-label">Clave / Código</label>
                <input type="text" id="z-clave" class="form-input-styled" placeholder="Ej. ZN-01">
            </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div class="form-group">
                <label class="form-label">Meta de Miembros</label>
                <input type="number" id="z-meta" class="form-input-styled" placeholder="0" min="0">
            </div>
            <div class="form-group">
                <label class="form-label"><i class="fas fa-user-tie" style="color:var(--primary);margin-right:5px;"></i> Coordinador / Responsable</label>
                <select id="z-responsable" class="form-input-styled">
                    <option value="">Cargando usuarios...</option>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Foco / Descripción</label>
            <textarea id="z-foco" class="form-input-styled" placeholder="Foco: Vivienda de interés social..."></textarea>
        </div>
        <div class="form-group">
            <label class="form-label">Colonias Clave</label>
            <input type="text" id="z-colonias" class="form-input-styled" placeholder="Ej. Pitic, Jesús García">
        </div>
        <div class="form-group">
            <label class="form-label">Secciones Electorales</label>
            
            <div id="z-muni-legend" class="legend-box"></div>

            <div style="display:flex;gap:8px;margin-bottom:8px;align-items:center;">
                <input type="text" class="seccion-picker-search" id="z-sec-search" style="flex:1;margin:0;" placeholder="Buscar sección..." oninput="filtrarSeccionesPicker()">
                <button type="button" onclick="irAMapaDesdeModal()" title="Ir al mapa del territorio para seleccionar secciones visualmente" style="flex-shrink:0;display:flex;align-items:center;gap:6px;background:#1565c0;color:white;border:none;padding:9px 13px;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;white-space:nowrap;font-family:Arial,sans-serif;">
                    <i class="fas fa-map-marked-alt"></i> Desde Mapa
                </button>
            </div>
            <div class="seccion-picker" id="seccion-picker"></div>
            <div style="font-size:11px;color:var(--text-muted);margin-top:5px;" id="sec-counter">0 secciones seleccionadas</div>
        </div>
        <div class="modal-footer">
            <button class="btn-cancel" onclick="cerrarModal('modal-zona')">Cancelar</button>
            <button class="btn-save" id="btn-save-zona" onclick="guardarZona()"><i class="fas fa-check"></i> Guardar</button>
        </div>
    </div>
</div>

<div class="modal" id="modal-secciones">
    <div class="modal-content" style="max-width:560px;">
        <div class="close-modal" onclick="cerrarModal('modal-secciones')"><i class="fas fa-times"></i></div>
        <h2 style="font-size:22px;font-weight:800;margin-bottom:5px;">Secciones de <span id="modal-sec-zona-name" style="color:var(--primary)">—</span></h2>
        <p style="font-size:14px;color:#999;margin-bottom:20px;">Selecciona o quita secciones electorales de esta zona.</p>
        
        <div id="edit-muni-legend" class="legend-box"></div>

        <input type="text" class="seccion-picker-search" id="edit-sec-search" style="margin-bottom:10px;" placeholder="Buscar sección..." oninput="filtrarSeccionesEdit()">
        <div class="seccion-picker" id="edit-seccion-picker" style="max-height:260px;"></div>
        <div style="font-size:11px;color:var(--text-muted);margin-top:6px;" id="edit-sec-counter">0 secciones seleccionadas</div>
        <div class="modal-footer" style="justify-content:space-between;">
            <button class="btn-secondary-action" onclick="irASeleccionarEnMapa('modal-secciones')" style="display:flex;align-items:center;gap:6px;padding:10px 16px;">
                <i class="fas fa-map" style="color:#1565c0;"></i> Desde Mapa
            </button>
            <div style="display:flex;gap:10px;">
                <button class="btn-cancel" onclick="cerrarModal('modal-secciones')">Cancelar</button>
                <button class="btn-save" id="btn-save-secciones" onclick="guardarSeccionesEdit()"><i class="fas fa-check"></i> Guardar</button>
            </div>
        </div>
    </div>
</div>

<!-- ══ fin modales ══ -->

<script>
/* ══════════════════════════════════════════════
   DATOS BASE — municipios, secciones y USUARIOS
══════════════════════════════════════════════ */
// MUNICIPIOS_BASE y SECCION_LN_MAP se cargan lazy via API
const USUARIOS_BASE   = <?php echo $usuarios_base_json; ?>;
const estadoActual    = <?php echo json_encode($estado, JSON_UNESCAPED_UNICODE); ?>;

let MUNICIPIOS_BASE          = { [estadoActual]: {} };   // se llena en init
let DISTRITOS_LOCALES_BASE   = { [estadoActual]: {} };   // se llena lazy
let DISTRITOS_FEDERALES_BASE = { [estadoActual]: {} };   // se llena lazy
let SECCION_LN_MAP    = {};                        // se llena al seleccionar región
let _lnRegionId       = null;                      // región cuyo LN está cargado
let tipoIntegracionRegion = 'municipios';

function getCatalogoIntegracion() {
    if (tipoIntegracionRegion === 'distrito_local')   return DISTRITOS_LOCALES_BASE;
    if (tipoIntegracionRegion === 'distrito_federal') return DISTRITOS_FEDERALES_BASE;
    return MUNICIPIOS_BASE;
}

function getEtiquetaIntegracion() {
    if (tipoIntegracionRegion === 'distrito_local')   return { label: 'Distritos locales asignados a la Región *', unidad: 'distritos locales', prefijo: 'DL ' };
    if (tipoIntegracionRegion === 'distrito_federal') return { label: 'Distritos Federales asignados a la Región *', unidad: 'distritos federales', prefijo: 'DF ' };
    return { label: 'Municipios asignados a la Región *', unidad: 'municipios', prefijo: '' };
}

function getEtiquetaBaseRegion(r) {
    if (r?.tipo_integracion === 'distrito_local')   return 'Distritos locales base';
    if (r?.tipo_integracion === 'distrito_federal') return 'Distritos Federales base';
    return 'Municipios base';
}

function getUnidadRegion(r) {
    if (r?.tipo_integracion === 'distrito_local')   return 'distritos locales';
    if (r?.tipo_integracion === 'distrito_federal') return 'distritos federales';
    return 'municipios';
}

/* ── Helpers lista nominal ── */
function calcTotalLN(secIterable) {
    let total = 0;
    secIterable.forEach(s => { total += SECCION_LN_MAP[String(s)] || 0; });
    return total;
}
function fmtLN(n) { return n > 0 ? n.toLocaleString('es-MX') : ''; }

/* ── Carga lazy de MUNICIPIOS_BASE ── */
async function cargarMunicipiosBase() {
    try {
        const res  = await fetch(`${API_BASE}?api=municipios_base`);
        const data = await res.json();
        if (data.ok) {
            MUNICIPIOS_BASE[estadoActual] = data.base;
        }
    } catch(e) { console.error('cargarMunicipiosBase:', e); }
}

/* ── Carga lazy de DISTRITOS_LOCALES_BASE / DISTRITOS_FEDERALES_BASE (una sola vez c/u) ── */
let _distritosLocalesCargados = false, _distritosFederalesCargados = false;
async function cargarDistritosBase(tipo) {
    if (tipo === 'distrito_local' && _distritosLocalesCargados) return;
    if (tipo === 'distrito_federal' && _distritosFederalesCargados) return;
    try {
        const res  = await fetch(`${API_BASE}?api=distritos_base&tipo=${tipo}`);
        const data = await res.json();
        if (data.ok) {
            if (tipo === 'distrito_federal') { DISTRITOS_FEDERALES_BASE[estadoActual] = data.base; _distritosFederalesCargados = true; }
            else                              { DISTRITOS_LOCALES_BASE[estadoActual]   = data.base; _distritosLocalesCargados   = true; }
        }
    } catch(e) { console.error('cargarDistritosBase:', e); }
}

/* ── Carga lazy de LN para la región activa ── */
async function cargarLNParaRegion(regionId) {
    if (_lnRegionId === regionId) return;   // ya cargado
    try {
        const res  = await fetch(`${API_BASE}?api=ln_por_region&region_id=${regionId}`);
        const data = await res.json();
        if (data.ok) {
            SECCION_LN_MAP = data.ln;
            _lnRegionId    = regionId;
        }
    } catch(e) { console.error('cargarLNParaRegion:', e); }
}

/* ══════════════════════════════════════════════
   API HELPER
══════════════════════════════════════════════ */
const API_BASE = window.location.pathname;

async function apiGet(accion) {
    const res = await fetch(`${API_BASE}?api=${accion}`);
    return res.json();
}
async function apiPost(accion, datos) {
    const res = await fetch(`${API_BASE}?api=${accion}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(datos)
    });
    return res.json();
}

/* ══════════════════════════════════════════════
   ESTADO EN MEMORIA
══════════════════════════════════════════════ */
let appData             = { regiones: [] };
let regionActiva        = null;   // id de regiones_estatales
let zonaActiva          = null;   // id de zonas_estatales
let editandoRegionId    = null;
let editandoZonaId      = null;
let seccionesSelPicker  = new Set();
let seccionesSelEdit    = new Set();
let municipiosSelPicker = new Set();

/* ══════════════════════════════════════════════
   TOAST
══════════════════════════════════════════════ */
function toast(msg, tipo='ok') {
    const el = document.getElementById('toast');
    el.className = 'toast ' + tipo;
    el.innerHTML = `<i class="fas fa-${tipo==='ok'?'check-circle':'exclamation-circle'}"></i> ${msg}`;
    el.classList.add('show');
    clearTimeout(window._toastTimer);
    window._toastTimer = setTimeout(() => el.classList.remove('show'), 3000);
}

/* ══════════════════════════════════════════════
   CARGA DESDE BD
══════════════════════════════════════════════ */
async function cargarDatos() {
    try {
        const data = await apiGet('cargar');
        if (!data.ok) throw new Error(data.msg || 'Error');
        appData = { regiones: data.regiones || [] };
        renderRegiones();
        renderZonas();
        renderSecciones();
    } catch(e) {
        document.getElementById('col-regiones-body').innerHTML =
            `<div class="empty-col"><i class="fas fa-exclamation-triangle" style="color:#d32f2f"></i>Error al cargar datos.<br><small>${e.message}</small></div>`;
    }
}

function getSeccionesOcupadas(excluirZonaId = null) {
    const ocupadas = new Set();
    // Recorrer TODAS las regiones del estado, no solo la activa
    for (const region of (appData.regiones||[])) {
        for (const z of (region.zonas||[])) {
            if (z.id === excluirZonaId) continue;
            for (const s of (z.secciones||[])) ocupadas.add(s);
        }
    }
    return ocupadas;
}

function renderSelectUsuarios(selectId, seleccionado = null, contexto = '') {
    const select = document.getElementById(selectId);
    let html = '<option value="">Sin responsable asignado</option>';
    
    USUARIOS_BASE.forEach(u => {
        let isSelected = false;
        if (u.id == seleccionado) {
            // Si pasamos un contexto, buscamos que el rol coincida
            if (contexto && u.rol && u.rol.toLowerCase().includes(contexto)) {
                isSelected = true;
            } else if (!contexto) {
                isSelected = true; // Fallback
            }
        }
        
        const isSelectedAttr = isSelected ? 'selected' : '';
        const rolLabel = u.rol ? ` - ${u.rol.replace('coordinador_', '').toUpperCase()}` : '';
        html += `<option value="${u.id}" ${isSelectedAttr}>${u.nombre}${rolLabel}</option>`;
    });
    
    select.innerHTML = html;
}

/* ══════════════════════════════════════════════
   RENDER REGIONES  (regiones_estatales)
══════════════════════════════════════════════ */
function renderRegiones() {
    const body = document.getElementById('col-regiones-body');
    if (!appData.regiones.length) {
        body.innerHTML = '<div class="empty-col"><i class="fas fa-map-marked-alt"></i>No hay regiones.<br>Crea la primera.</div>';
        return;
    }
    const regionesOrdenadas = [...appData.regiones].sort((a, b) =>
        (b.nombre || '').localeCompare(a.nombre || '', 'es', { numeric: true, sensitivity: 'base' })
    );
    body.innerHTML = regionesOrdenadas.map(r => {
        const totalMiembros = (r.zonas||[]).reduce((s,z) => s+(z.miembros||0), 0);
        const pct    = r.meta > 0 ? Math.min(100, Math.round(totalMiembros/r.meta*100)) : 0;
        const nZonas = (r.zonas||[]).length;
        const nMunis = (r.municipios||[]).length;
        
        // Mapear ID del responsable a Nombre Real
        const userResp = USUARIOS_BASE.find(u => u.id == r.responsable && u.rol && u.rol.toLowerCase().includes('regional')) 
              || USUARIOS_BASE.find(u => u.id == r.responsable);
        const nombResp = userResp ? userResp.nombre : null;

        return `<div class="region-card ${regionActiva===r.id?'active':''}" onclick="selectRegion(${r.id})">
            <div class="card-actions">
                <button class="card-action-btn" onclick="event.stopPropagation();editarRegion(${r.id})"><i class="fas fa-pen"></i></button>
                <button class="card-action-btn del" onclick="event.stopPropagation();eliminarRegion(${r.id})"><i class="fas fa-trash"></i></button>
            </div>
            <div class="card-name">${r.nombre}</div>
            ${nombResp ? `<div style="font-size:12px; color:var(--text-muted); margin-bottom:8px;"><i class="fas fa-user" style="color:var(--primary);"></i> ${nombResp}</div>` : ''}
            <div class="card-meta">
                <span class="card-miembros">${totalMiembros.toLocaleString('es-MX')} Miembros</span>
                <span class="card-pct">${pct}%</span>
            </div>
            <div class="card-bar-track"><div class="card-bar-fill" style="width:${pct}%"></div></div>
            <div class="card-secciones-count">${nZonas} zona${nZonas!==1?'s':''} · ${nMunis} ${getUnidadRegion(r)}</div>
        </div>`;
    }).join('');
}

function selectRegion(id) {
    regionActiva = id; zonaActiva = null;
    renderRegiones(); renderZonas(); renderSecciones();
    // Cargar LN de esta región en background (no bloquea el render)
    cargarLNParaRegion(id);
    // Si la región es por distrito, precargar su catálogo antes de usarlo en zonas
    const region = appData.regiones.find(r => r.id === id);
    const tipo = region?.tipo_integracion || 'municipios';
    if (tipo !== 'municipios') cargarDistritosBase(tipo);
}

/* ══════════════════════════════════════════════
   RENDER ZONAS  (zonas_estatales)
══════════════════════════════════════════════ */
function renderZonas() {
    const body     = document.getElementById('col-zonas-body');
    const btnNueva = document.getElementById('btn-nueva-zona');
    const subtitle = document.getElementById('col-zonas-subtitle');

    if (!regionActiva) {
        body.innerHTML = '<div class="hint-text"><i class="fas fa-arrow-left"></i>Selecciona una región.</div>';
        btnNueva.style.display = 'none'; subtitle.textContent = '';
        return;
    }
    const region = appData.regiones.find(r => r.id === regionActiva);
    if (!region) return;
    subtitle.textContent = region.nombre;
    btnNueva.style.display = 'flex';

    if (!region.zonas || !region.zonas.length) {
        body.innerHTML = '<div class="hint-text"><i class="fas fa-layer-group"></i>No hay zonas en esta región.</div>';
        return;
    }
    body.innerHTML = '<div class="col-body">' + region.zonas.map(z => {
        const pct  = z.meta > 0 ? Math.min(100, Math.round((z.miembros||0)/z.meta*100)) : 0;
        const nSec = (z.secciones||[]).length;
        
        // Mapear ID del responsable a Nombre Real
        const userResp = USUARIOS_BASE.find(u => u.id == z.responsable && u.rol && u.rol.toLowerCase().includes('zona')) 
              || USUARIOS_BASE.find(u => u.id == z.responsable);
        const nombResp = userResp ? userResp.nombre : null;

        return `<div class="zona-card ${zonaActiva===z.id?'active':''}" onclick="selectZona(${z.id})">
            <div class="card-actions">
                <button class="card-action-btn" onclick="event.stopPropagation();editarZona(${z.id})"><i class="fas fa-pen"></i></button>
                <button class="card-action-btn del" onclick="event.stopPropagation();eliminarZona(${z.id})"><i class="fas fa-trash"></i></button>
            </div>
            <div class="card-name">${z.nombre}${z.clave?' <span style="font-size:11px;color:var(--text-muted);font-weight:400;">('+z.clave+')</span>':''}</div>
            ${nombResp ? `<div style="font-size:12px; color:var(--text-muted); margin-bottom:8px;"><i class="fas fa-user" style="color:var(--primary);"></i> ${nombResp}</div>` : ''}
            <div class="card-meta">
                <span class="card-miembros">${(z.miembros||0).toLocaleString('es-MX')} Miembros</span>
                <span class="card-pct">${pct}%</span>
            </div>
            <div class="card-bar-track"><div class="card-bar-fill" style="width:${pct}%"></div></div>
            <div class="card-secciones-count">${nSec} sección${nSec!==1?'es':''} asignada${nSec!==1?'s':''} · Meta: ${(z.meta||0).toLocaleString('es-MX')}</div>
            ${z.foco?'<div class="card-meta-bottom">'+z.foco+'</div>':''}
        </div>`;
    }).join('') + '</div>';
}

function selectZona(id) {
    zonaActiva = id;
    renderZonas(); renderSecciones();
}

/* ══════════════════════════════════════════════
   RENDER SECCIONES  (zonas_estatales.secciones JSON)
══════════════════════════════════════════════ */
function renderSecciones() {
    const body     = document.getElementById('col-secciones-body');
    const btnsDiv  = document.getElementById('btns-asignar-sec');
    const subtitle = document.getElementById('col-sec-subtitle');

    if (!zonaActiva) {
        body.innerHTML = '<div class="hint-text"><i class="fas fa-arrow-left"></i>Selecciona una zona.</div>';
        btnsDiv.style.display = 'none'; subtitle.textContent = '';
        return;
    }
    const region = appData.regiones.find(r => r.id === regionActiva);
    const zona   = (region?.zonas||[]).find(z => z.id === zonaActiva);
    if (!zona) return;

    subtitle.textContent = zona.nombre;
    btnsDiv.style.display = 'flex';

    const secs = zona.secciones || [];
    const totalLN = calcTotalLN(secs);
    const infoBox = `<div class="zona-info-box">
        <div class="zi-title">${zona.nombre}${zona.clave?' · '+zona.clave:''}</div>
        ${zona.foco?'<div class="zi-foco">'+zona.foco+'</div>':''}
        <div class="zi-bullet"><strong>Secciones (${secs.length}):</strong> ${secs.length ? secs.slice(0,8).join(', ')+(secs.length>8?'...':'') : '—'}</div>
        ${totalLN > 0 ? `<div class="zi-bullet" style="margin-top:4px;"><span style="font-weight:800;color:#92400e;background:#fef3c7;border:1px solid #fde68a;border-radius:6px;padding:3px 10px;font-size:13px;">${fmtLN(totalLN)} de lista nominal</span></div>` : ''}
        ${zona.colonias?'<div class="zi-bullet"><strong>Colonias:</strong> '+zona.colonias+'</div>':''}
    </div>`;

    if (!secs.length) {
        body.innerHTML = infoBox + `<div class="hint-text"><i class="fas fa-list-ol"></i>Sin secciones asignadas.<br>
            <div style="display:flex;gap:8px;justify-content:center;margin-top:10px;">
                <button class="btn-primary-action btn-sm" onclick="abrirModalAsignarSecciones()">
                    <i class="fas fa-list"></i> Desde Lista
                </button>
                <button class="btn-primary-action btn-sm" onclick="irAMapaDesdeColumna()" style="background:#1565c0;">
                    <i class="fas fa-map-marked-alt"></i> Desde Mapa
                </button>
            </div></div>`;
        return;
    }
    body.innerHTML = infoBox + '<div class="list-scroll">' +
        secs.map(s => {
            const ln = SECCION_LN_MAP[String(s)] || 0;
            return `<div class="seccion-item">
                <span class="seccion-num">Sección ${s}</span>
                ${ln > 0 ? `<span class="seccion-ln">Ln: ${ln.toLocaleString('es-MX')}</span>` : ''}
                <span class="seccion-assigned"><i class="fas fa-check-circle"></i> Asignada</span>
            </div>`;
        }).join('') + '</div>';
}

/* ══════════════════════════════════════════════
   CRUD REGIONES  →  regiones_estatales
══════════════════════════════════════════════ */
async function abrirModalRegion(id=null) {
    editandoRegionId = id;
    document.getElementById('modal-region-title').textContent = id ? 'Editar Región' : 'Nueva Región';
    ['r-nombre','r-desc','r-meta','r-responsable','r-muni-search'].forEach(f => document.getElementById(f).value = '');
    municipiosSelPicker = new Set();
    tipoIntegracionRegion = 'municipios';
    document.querySelectorAll('input[name="r-tipo-integracion"]').forEach(rad => rad.checked = (rad.value === 'municipios'));

    let idResponsableActual = null;

    if (id) {
        const r = appData.regiones.find(x => x.id === id);
        if (r) {
            document.getElementById('r-nombre').value      = r.nombre      || '';
            document.getElementById('r-desc').value        = r.descripcion || '';
            document.getElementById('r-meta').value        = r.meta        || '';
            idResponsableActual                            = r.responsable;
            tipoIntegracionRegion = r.tipo_integracion || 'municipios';
            document.querySelectorAll('input[name="r-tipo-integracion"]').forEach(rad => rad.checked = (rad.value === tipoIntegracionRegion));
            municipiosSelPicker = new Set(r.municipios || []);
        }
    }
    
    // Rellenamos el selector con los usuarios de la Base de Datos
    renderSelectUsuarios('r-responsable', idResponsableActual, 'regional');
    
    if (tipoIntegracionRegion !== 'municipios') await cargarDistritosBase(tipoIntegracionRegion);
    renderMunicipiosPicker();
    document.getElementById('modal-region').classList.add('open');
}

async function cambiarTipoIntegracionRegion() {
    const sel = document.querySelector('input[name="r-tipo-integracion"]:checked');
    tipoIntegracionRegion = sel ? sel.value : 'municipios';
    municipiosSelPicker = new Set();
    document.getElementById('r-muni-search').value = '';
    if (tipoIntegracionRegion !== 'municipios') await cargarDistritosBase(tipoIntegracionRegion);
    renderMunicipiosPicker();
}

function seleccionarTodosMunicipios() {
    const allMunis = Object.keys(getCatalogoIntegracion()[estadoActual] || {});
    allMunis.forEach(m => municipiosSelPicker.add(m));
    renderMunicipiosPicker();
}

function renderMunicipiosPicker() {
    const q        = document.getElementById('r-muni-search').value.toLowerCase();
    const { label, unidad, prefijo } = getEtiquetaIntegracion();
    const allMunis = Object.keys(getCatalogoIntegracion()[estadoActual] || {});
    const munis    = allMunis.filter(m => m.toLowerCase().includes(q));
    const picker   = document.getElementById('muni-picker');

    const lbl = document.getElementById('r-muni-label');
    if (lbl) lbl.textContent = label;

    picker.innerHTML = munis.map(m => {
        const esSel = municipiosSelPicker.has(m);
        const cls   = esSel ? 'selected' : '';
        
        return `<span class="seccion-tag ${cls}" data-mun="${m.replace(/"/g,'&quot;')}" onclick="toggleMunicipio(this)">${prefijo}${m}</span>`;
    }).join('');
    
    document.getElementById('muni-counter').textContent = municipiosSelPicker.size + ' ' + unidad + ' seleccionados';
}

function filtrarMunicipiosPicker() { renderMunicipiosPicker(); }

function toggleMunicipio(el) {
    const m = el.dataset.mun;
    municipiosSelPicker.has(m) ? municipiosSelPicker.delete(m) : municipiosSelPicker.add(m);
    renderMunicipiosPicker();
}

async function guardarRegion() {
    const nombre = document.getElementById('r-nombre').value.trim();
    if (!nombre)                    { toast('El nombre es obligatorio.','err'); return; }
    if (!municipiosSelPicker.size)  { toast('Selecciona al menos un elemento (municipio / distrito).','err'); return; }

    const btn = document.getElementById('btn-save-region');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Guardando...';

    try {
        const res = await apiPost('guardar_region', {
            id:          editandoRegionId,
            nombre,
            desc:        document.getElementById('r-desc').value.trim(),
            meta:        parseInt(document.getElementById('r-meta').value) || 0,
            responsable: document.getElementById('r-responsable').value || null, // Obtenemos el ID o NULL
            municipios:  [...municipiosSelPicker].sort(),
            tipo_integracion: tipoIntegracionRegion
        });
        if (!res.ok) throw new Error(res.msg || 'Error');
        if (!editandoRegionId) regionActiva = res.id;
        await cargarDatos();
        cerrarModal('modal-region');
        toast(editandoRegionId ? 'Región actualizada.' : 'Región creada.');
    } catch(e) {
        toast('Error: ' + e.message, 'err');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check"></i> Guardar';
    }
}

function editarRegion(id) { abrirModalRegion(id); }

async function eliminarRegion(id) {
    if (!confirm('¿Eliminar esta región y todas sus zonas?')) return;
    try {
        const res = await apiPost('eliminar_region', { id });
        if (!res.ok) throw new Error(res.msg || 'Error');
        if (regionActiva === id) { regionActiva = null; zonaActiva = null; }
        await cargarDatos();
        toast('Región eliminada.');
    } catch(e) {
        toast('Error: ' + e.message, 'err');
    }
}

/* ══════════════════════════════════════════════
   CRUD ZONAS  →  zonas_estatales
══════════════════════════════════════════════ */
async function abrirModalZona(id=null) {
    editandoZonaId = id;
    document.getElementById('modal-zona-title').textContent = id ? 'Editar Zona' : 'Nueva Zona';
    ['z-nombre','z-clave','z-foco','z-colonias','z-meta','z-responsable','z-sec-search'].forEach(f => document.getElementById(f).value = '');
    seccionesSelPicker = new Set();
    let idResponsableActual = null;

    const region = appData.regiones.find(r => r.id === regionActiva);
    const tipo = region?.tipo_integracion || 'municipios';
    if (tipo !== 'municipios') await cargarDistritosBase(tipo);

    const legend = document.getElementById('z-muni-legend');
    if (region?.municipios?.length) {
        const prefijo = tipo === 'distrito_local' ? 'DL ' : tipo === 'distrito_federal' ? 'DF ' : '';
        const chips = region.municipios.map(m =>
            `<span class="mz-muni-chip" style="cursor:pointer;text-decoration:underline dotted;" title="Ver en el mapa" onclick="irAMapaDesdeModal('${String(m).replace(/'/g,"\\'")}')">${prefijo}${m}</span>`
        ).join(', ');
        legend.innerHTML = `<i class="fas fa-filter"></i> <strong>${getEtiquetaBaseRegion(region)}:</strong> ` + chips;
        legend.style.display = 'block';
    } else {
        legend.innerHTML = '<i class="fas fa-exclamation-triangle"></i> La región no tiene municipios asignados.';
        legend.style.display = 'block';
    }

    if (id && region) {
        const z = (region.zonas||[]).find(z => z.id === id);
        if (z) {
            document.getElementById('z-nombre').value      = z.nombre      || '';
            document.getElementById('z-clave').value       = z.clave       || '';
            document.getElementById('z-foco').value        = z.foco        || '';
            document.getElementById('z-colonias').value    = z.colonias    || '';
            document.getElementById('z-meta').value        = z.meta        || '';
            idResponsableActual                            = z.responsable;
            seccionesSelPicker = new Set(z.secciones||[]);
        }
    }
    
    // Rellenamos el selector con los usuarios
    renderSelectUsuarios('z-responsable', idResponsableActual, 'zona');
    
    document.getElementById('modal-zona').classList.add('open');
    // Si ya está cargado el LN renderiza de inmediato, si no espera
    if (_lnRegionId === regionActiva) {
        renderSeccionesPicker('seccion-picker', 'z-sec-search', seccionesSelPicker);
    } else {
        document.getElementById('seccion-picker').innerHTML = '<div style="padding:14px;text-align:center;color:var(--text-muted);font-size:13px;"><i class="fas fa-circle-notch fa-spin"></i> Cargando secciones…</div>';
        cargarLNParaRegion(regionActiva).then(() => {
            renderSeccionesPicker('seccion-picker', 'z-sec-search', seccionesSelPicker);
        });
    }
}

function getSeccionesPermitidas() {
    const region = appData.regiones.find(r => r.id === regionActiva);
    const munis  = region?.municipios || [];
    const tipo   = region?.tipo_integracion || 'municipios';
    const base   = (tipo === 'distrito_local'   ? DISTRITOS_LOCALES_BASE
                  : tipo === 'distrito_federal' ? DISTRITOS_FEDERALES_BASE
                  : MUNICIPIOS_BASE)[estadoActual] || {};
    let permitidas = [];
    munis.forEach(m => { if (base[m]) permitidas = permitidas.concat(base[m]); });
    // Convertir a string para que .includes() y Set.has() funcionen uniformemente
    return permitidas.map(s => String(s)).sort((a,b) => parseInt(a)-parseInt(b));
}

function renderSeccionesPicker(pickerId, searchId, selSet) {
    const q        = document.getElementById(searchId).value.toLowerCase();
    const secs     = getSeccionesPermitidas().filter(s => s.includes(q));
    const picker   = document.getElementById(pickerId);
    const ocupadas = getSeccionesOcupadas(editandoZonaId);

    picker.innerHTML = secs.map(s => {
        const esOcupada = ocupadas.has(s) && !selSet.has(s);
        const esSel     = selSet.has(s);
        const cls       = esSel ? 'selected' : esOcupada ? 'ocupada' : '';
        const click     = esOcupada ? '' : `onclick="toggleSeccion(this)"`;
        const title     = esOcupada ? 'title="Ya asignada a otra zona"' : '';
        return `<span class="seccion-tag ${cls}" data-sec="${s}" ${click} ${title}>${s}</span>`;
    }).join('');
    const _ln = calcTotalLN(selSet);
    document.getElementById('sec-counter').innerHTML =
        `<span>${selSet.size} secciones seleccionadas</span>` +
        (_ln > 0 ? `<span style="font-weight:700;color:#92400e;background:#fef3c7;border:1px solid #fde68a;border-radius:5px;padding:2px 8px;margin-left:8px;">${fmtLN(_ln)} de lista nominal</span>` : '');
}

function filtrarSeccionesPicker() { renderSeccionesPicker('seccion-picker','z-sec-search',seccionesSelPicker); }

function toggleSeccion(el) {
    const s = el.dataset.sec;
    if (seccionesSelPicker.has(s)) seccionesSelPicker.delete(s);
    else {
        if (getSeccionesOcupadas(editandoZonaId).has(s)) return;
        seccionesSelPicker.add(s);
    }
    renderSeccionesPicker('seccion-picker','z-sec-search',seccionesSelPicker);
}

async function guardarZona() {
    const nombre = document.getElementById('z-nombre').value.trim();
    if (!nombre)        { toast('El nombre es obligatorio.','err'); return; }
    if (!regionActiva)  { toast('Selecciona una región primero.','err'); return; }

    const btn = document.getElementById('btn-save-zona');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Guardando...';

    try {
        const res = await apiPost('guardar_zona', {
            id:          editandoZonaId,
            region_id:   regionActiva,          // → estructura_id en BD
            nombre,
            clave:       document.getElementById('z-clave').value.trim(),
            foco:        document.getElementById('z-foco').value.trim(),
            colonias:    document.getElementById('z-colonias').value.trim(),
            meta:        parseInt(document.getElementById('z-meta').value) || 0,
            responsable: document.getElementById('z-responsable').value || null, // Obtenemos el ID o NULL
            secciones:   [...seccionesSelPicker].sort((a,b)=>parseInt(a)-parseInt(b))
        });
        if (!res.ok) throw new Error(res.msg || 'Error');
        if (!editandoZonaId) zonaActiva = res.id;
        await cargarDatos();
        cerrarModal('modal-zona');
        toast(editandoZonaId ? 'Zona actualizada.' : 'Zona creada.');
    } catch(e) {
        toast('Error: ' + e.message, 'err');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check"></i> Guardar';
    }
}

function editarZona(id) { abrirModalZona(id); }

async function eliminarZona(id) {
    if (!confirm('¿Eliminar esta zona permanentemente?')) return;
    try {
        const res = await apiPost('eliminar_zona', { id, region_id: regionActiva });
        if (!res.ok) throw new Error(res.msg || 'Error');
        if (zonaActiva === id) zonaActiva = null;
        await cargarDatos();
        toast('Zona eliminada.');
    } catch(e) {
        toast('Error: ' + e.message, 'err');
    }
}

/* ══════════════════════════════════════════════
   MODAL EDITAR SECCIONES
══════════════════════════════════════════════ */
async function abrirModalAsignarSecciones() {
    const region = appData.regiones.find(r => r.id === regionActiva);
    const zona   = (region?.zonas||[]).find(z => z.id === zonaActiva);
    if (!zona) return;

    document.getElementById('modal-sec-zona-name').textContent = zona.nombre;
    document.getElementById('edit-sec-search').value = '';

    const tipo = region?.tipo_integracion || 'municipios';
    if (tipo !== 'municipios') await cargarDistritosBase(tipo);

    const legend = document.getElementById('edit-muni-legend');
    if (region.municipios?.length) {
        legend.innerHTML = `<i class="fas fa-filter"></i> <strong>${getEtiquetaBaseRegion(region)}:</strong> ` + region.municipios.join(', ');
        legend.style.display = 'block';
    }

    seccionesSelEdit = new Set(zona.secciones||[]);
    document.getElementById('modal-secciones').classList.add('open');
    if (_lnRegionId === regionActiva) {
        renderSeccionesEdit();
    } else {
        document.getElementById('edit-seccion-picker').innerHTML = '<div style="padding:14px;text-align:center;color:var(--text-muted);font-size:13px;"><i class="fas fa-circle-notch fa-spin"></i> Cargando secciones…</div>';
        cargarLNParaRegion(regionActiva).then(() => renderSeccionesEdit());
    }
}

function renderSeccionesEdit() {
    const q        = document.getElementById('edit-sec-search').value.toLowerCase();
    const secs     = getSeccionesPermitidas().filter(s => s.includes(q));
    const ocupadas = getSeccionesOcupadas(zonaActiva);

    document.getElementById('edit-seccion-picker').innerHTML = secs.map(s => {
        const esOcupada = ocupadas.has(s) && !seccionesSelEdit.has(s);
        const esSel     = seccionesSelEdit.has(s);
        const cls       = esSel ? 'selected' : esOcupada ? 'ocupada' : '';
        const click     = esOcupada ? '' : `onclick="toggleSeccionEdit(this)"`;
        const title     = esOcupada ? 'title="Ya asignada a otra zona"' : '';
        return `<span class="seccion-tag ${cls}" data-sec="${s}" ${click} ${title}>${s}</span>`;
    }).join('');
    const _lnEdit = calcTotalLN(seccionesSelEdit);
    document.getElementById('edit-sec-counter').innerHTML =
        `<span>${seccionesSelEdit.size} secciones seleccionadas</span>` +
        (_lnEdit > 0 ? `<span style="font-weight:700;color:#92400e;background:#fef3c7;border:1px solid #fde68a;border-radius:5px;padding:2px 8px;margin-left:8px;">${fmtLN(_lnEdit)} de lista nominal</span>` : '');
}

function filtrarSeccionesEdit() { renderSeccionesEdit(); }

function toggleSeccionEdit(el) {
    const s = el.dataset.sec;
    if (seccionesSelEdit.has(s)) seccionesSelEdit.delete(s);
    else {
        if (getSeccionesOcupadas(zonaActiva).has(s)) return;
        seccionesSelEdit.add(s);
    }
    renderSeccionesEdit();
}

async function guardarSeccionesEdit() {
    const btn = document.getElementById('btn-save-secciones');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Guardando...';
    try {
        const res = await apiPost('guardar_secciones', {
            zona_id:   zonaActiva,
            secciones: [...seccionesSelEdit].sort((a,b)=>parseInt(a)-parseInt(b))
        });
        if (!res.ok) throw new Error(res.msg || 'Error');
        await cargarDatos();
        cerrarModal('modal-secciones');
        toast('Secciones guardadas.');
    } catch(e) {
        toast('Error: ' + e.message, 'err');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check"></i> Guardar';
    }
}

/* ══════════════════════════════════════════════
   SELECCIÓN DESDE MAPA (redirect a territorio.php)
══════════════════════════════════════════════ */

// Guarda los arrays grandes en $_SESSION del servidor antes de redirigir,
// evitando que viajen en la URL y causen un 414 Request-URI Too Large.
async function _guardarSecsEnSession(secsActuales, ocupadas) {
    // Guardar en sessionStorage como respaldo (territorio.php lo lee si la sesión PHP llega vacía)
    sessionStorage.setItem('mapa_secciones_actuales', secsActuales || '');
    sessionStorage.setItem('mapa_secciones_ocupadas', ocupadas     || '');
    try {
        await fetch('territorio.php?api=guardar_secs_session', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({
                secciones_actuales: secsActuales || '',
                secciones_ocupadas: ocupadas     || ''
            })
        });
    } catch(e) { /* si falla el POST, sessionStorage tiene el respaldo */ }
}

function _buildMapaParams(zonaId, zonaNombre, zoomEntidad, zoomTipo) {
    const returnUrl = window.location.href.split('?')[0];
    const obj = {
        modo_seleccion: '1',
        zona_id:        zonaId       || '',
        zona_nombre:    zonaNombre   || '',
        region_id:      regionActiva || '',
        return_url:     returnUrl,
    };
    if (zoomEntidad) { obj.zoom_entidad = zoomEntidad; obj.zoom_tipo = zoomTipo || 'municipios'; }
    return new URLSearchParams(obj);
}

/** Desde el botón "Mapa" en el header de la columna Secciones.
 *  La zona ya existe → al volver se auto-guardan las secciones. */
async function irAMapaDesdeColumna() {
    if (!zonaActiva) return;
    const region  = appData.regiones.find(r => r.id === regionActiva);
    const zona    = (region?.zonas||[]).find(z => z.id === zonaActiva);
    const ocupadas = [...getSeccionesOcupadas(zonaActiva)].join(',');
    await _guardarSecsEnSession((zona?.secciones||[]).join(','), ocupadas);
    sessionStorage.setItem('zonas_nav_estado', JSON.stringify({ regionId: regionActiva, zonaId: zonaActiva }));
    window.location.href = 'territorio.php?' + _buildMapaParams(zonaActiva, zona?.nombre || '').toString();
}

/** Desde el botón "Desde Mapa" dentro del modal-zona (zona nueva o editando), o desde un clic
 *  en un municipio/distrito del listado (en ese caso, zoomEntidad trae su nombre/código). */
async function irAMapaDesdeModal(zoomEntidad) {
    const draft = {
        zonaId:      editandoZonaId,
        regionId:    regionActiva,
        nombre:      document.getElementById('z-nombre').value,
        clave:       document.getElementById('z-clave').value,
        foco:        document.getElementById('z-foco').value,
        colonias:    document.getElementById('z-colonias').value,
        meta:        document.getElementById('z-meta').value,
        responsable: document.getElementById('z-responsable').value,
        secciones:   [...seccionesSelPicker]
    };
    sessionStorage.setItem('zonas_modal_draft', JSON.stringify(draft));
    sessionStorage.setItem('zonas_nav_estado', JSON.stringify({ regionId: regionActiva, zonaId: editandoZonaId || null }));
    const ocupadas = [...getSeccionesOcupadas(editandoZonaId || '')].join(',');
    await _guardarSecsEnSession(draft.secciones.join(','), ocupadas);
    const region = appData.regiones.find(r => r.id === regionActiva);
    const tipo   = region?.tipo_integracion || 'municipios';
    const soloZoom = zoomEntidad && !seccionesSelPicker.size ? zoomEntidad : null;
    window.location.href = 'territorio.php?' + _buildMapaParams(editandoZonaId || '', draft.nombre || 'Zona nueva', soloZoom, tipo).toString();
}

/** Desde el botón "Desde Mapa" dentro del modal-secciones (zona existente). */
async function irASeleccionarEnMapa() {
    if (!zonaActiva) return;
    const region  = appData.regiones.find(r => r.id === regionActiva);
    const zona    = (region?.zonas||[]).find(z => z.id === zonaActiva);
    sessionStorage.setItem('zonas_sec_edit_retorno', '1');
    sessionStorage.setItem('zonas_nav_estado', JSON.stringify({ regionId: regionActiva, zonaId: zonaActiva }));
    const ocupadas = [...getSeccionesOcupadas(zonaActiva)].join(',');
    await _guardarSecsEnSession([...seccionesSelEdit].join(','), ocupadas);
    window.location.href = 'territorio.php?' + _buildMapaParams(zonaActiva, zona?.nombre || '').toString();
}

/* ══════════════════════════════════════════════
   UTILS
══════════════════════════════════════════════ */
function cerrarModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal').forEach(el =>
    el.addEventListener('click', e => { if (e.target === el) el.classList.remove('open'); })
);

document.addEventListener('DOMContentLoaded', async () => {
    // Cargar estructura y municipios en paralelo
    await Promise.all([cargarDatos(), cargarMunicipiosBase()]);

    /* ── Retorno desde territorio.php con secciones seleccionadas ── */
    const params     = new URLSearchParams(window.location.search);
    const secsStr    = params.get('secciones_mapa');

    if (secsStr !== null) {
        const zonaIdParam = params.get('zona_id') ? parseInt(params.get('zona_id')) : null;
        const secciones   = secsStr.split(',').filter(Boolean);

        // Limpiar URL para que no se reprocese en un F5
        history.replaceState({}, '', window.location.pathname);

        // ── Caso A: venía del modal-zona (zona nueva o editando) ──
        const draft = JSON.parse(sessionStorage.getItem('zonas_modal_draft') || 'null');
        sessionStorage.removeItem('zonas_modal_draft');

        if (draft) {
            regionActiva = draft.regionId;
            renderRegiones(); renderZonas(); renderSecciones();
            await new Promise(r => setTimeout(r, 60));
            abrirModalZona(draft.zonaId);
            ['nombre','clave','foco','colonias','meta'].forEach(f => {
                if (draft[f] !== undefined) document.getElementById('z-' + f).value = draft[f];
            });
            if (draft.responsable) document.getElementById('z-responsable').value = draft.responsable;
            seccionesSelPicker = new Set(secciones);
            renderSeccionesPicker('seccion-picker', 'z-sec-search', seccionesSelPicker);
            toast('✓ ' + secciones.length + ' secciones importadas desde el mapa.');
            return;
        }

        // ── Caso B: venía del modal-secciones (edición rápida) ──
        const eraSecEdit = sessionStorage.getItem('zonas_sec_edit_retorno') === '1';
        sessionStorage.removeItem('zonas_sec_edit_retorno');

        if (eraSecEdit && zonaIdParam) {
            for (const r of (appData.regiones||[])) {
                if ((r.zonas||[]).find(z => z.id === zonaIdParam)) {
                    regionActiva = r.id; zonaActiva = zonaIdParam; break;
                }
            }
            renderRegiones(); renderZonas(); renderSecciones();
            await new Promise(r => setTimeout(r, 60));
            abrirModalAsignarSecciones();
            seccionesSelEdit = new Set(secciones);
            renderSeccionesEdit();
            toast('✓ ' + secciones.length + ' secciones importadas desde el mapa.');
            return;
        }

        // ── Caso C: venía del botón columna (zona existente, auto-guardar) ──
        if (zonaIdParam) {
            try {
                const res = await apiPost('guardar_secciones', { zona_id: zonaIdParam, secciones });
                if (!res.ok) throw new Error(res.msg || 'Error');
                for (const r of (appData.regiones||[])) {
                    if ((r.zonas||[]).find(z => z.id === zonaIdParam)) {
                        regionActiva = r.id; zonaActiva = zonaIdParam; break;
                    }
                }
                await cargarDatos();
                if (regionActiva) await cargarLNParaRegion(regionActiva);
                renderRegiones(); renderZonas(); renderSecciones();
                toast('✓ Secciones guardadas desde el mapa.');
            } catch(e) { toast('Error al guardar: ' + e.message, 'err'); }
        }
    } else {
        // ── Retorno sin secciones_mapa (flecha del navegador o Cancelar) ──
        // Restaurar región/zona activa y LN para que la pantalla quede igual que antes de ir al mapa
        const navEstado = JSON.parse(sessionStorage.getItem('zonas_nav_estado') || 'null');
        if (navEstado && navEstado.regionId) {
            regionActiva = navEstado.regionId;
            zonaActiva   = navEstado.zonaId || null;
            await cargarLNParaRegion(regionActiva);
            renderRegiones(); renderZonas(); renderSecciones();
        }
        sessionStorage.removeItem('zonas_nav_estado');
    }
});
</script>
</body>
</html>