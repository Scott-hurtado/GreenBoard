<?php
// api/agenda/procesar.php

// Siempre responder JSON — debe ir ANTES de cualquier output
header('Content-Type: application/json; charset=utf-8');
// Evitar que warnings/notices de PHP rompan el JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    if (file_exists(__DIR__ . '/../../includes/bootstrap.php')) {
        require_once __DIR__ . '/../../includes/bootstrap.php';
    } else { 
        if (session_status() === PHP_SESSION_NONE) {
            session_start(); 
        }
    }

    $rol      = $_SESSION['user_role'] ?? '';
    $user_id  = $_SESSION['user_id']   ?? null;
    
    // Variables de sesión para el Coordinador de Zona
    $estado_usuario = $_SESSION['user_estado'] ?? '';
    $zona_usuario   = $_SESSION['user_zona_id'] ?? '';

    // SE AÑADIÓ 'coordinador_zona' Y 'coordinador_regional' A LOS PERMITIDOS
    $permitidos = ['admin','administrador','superadmin','super admin', 'coordinador_estatal', 'coordinador_zona', 'coordinador_regional', 'regional', 'circunscripcion'];

    if (!in_array($rol, $permitidos)) {
        http_response_code(403);
        echo json_encode(['ok'=>false,'msg'=>'Sin permisos']);
        exit();
    }

    // Si bootstrap.php ya inyectó $pdo, lo usamos directamente
    if (!isset($pdo)) {
        $dsn = "mysql:host=localhost;dbname=u881824383_pverde;charset=utf8mb4";
        $pdo = new PDO($dsn, 'u881824383_proyverde', 'PProyectoverde8#', [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    // Si la sesión no tiene user_zona_id, lo obtenemos de la BD (para coordinador_zona)
    if (empty($zona_usuario) && $rol === 'coordinador_zona' && !empty($user_id)) {
        try {
            $stZ = $pdo->prepare("SELECT zona FROM coordinador_zona WHERE id = ? LIMIT 1");
            $stZ->execute([$user_id]);
            $zona_usuario = (string)($stZ->fetchColumn() ?: '');
        } catch (Exception $e) { /* silencioso */ }
    }

    // ── FILTROS TERRITORIALES ─────────────────────────────────────────────────────
    $estado_coordinador      = null;
    $estados_circunscripcion = [];

    if ($rol === 'coordinador_estatal') {
        $stmt = $pdo->prepare("SELECT estado FROM coordinador_estatal WHERE id = ?");
        $stmt->execute([$user_id]);
        $estado_coordinador = $stmt->fetchColumn();
        if (!$estado_coordinador) {
            echo json_encode(['ok'=>false,'msg'=>'Coordinador sin estado asignado']);
            exit();
        }
    } elseif ($rol === 'circunscripcion') {
        $user_circ = $_SESSION['user_circunscripcion'] ?? '';
        $CIRCS = [
            'Circunscripción 1' => ['Baja California','Baja California Sur','Chihuahua','Durango','Jalisco','Nayarit','Sinaloa','Sonora'],
            'Circunscripción 2' => ['Aguascalientes','Coahuila','Guanajuato','Nuevo León','San Luis Potosí','Tamaulipas','Zacatecas'],
            'Circunscripción 3' => ['Campeche','Chiapas','Oaxaca','Quintana Roo','Tabasco','Veracruz','Yucatán'],
            'Circunscripción 4' => ['Ciudad de México','Guerrero','Hidalgo','Morelos','Puebla','Tlaxcala'],
            'Circunscripción 5' => ['Colima','Estado de México','Michoacán','Querétaro'],
        ];
        $estados_circunscripcion = $CIRCS[$user_circ] ?? [];
        if (empty($estados_circunscripcion)) {
            echo json_encode(['ok'=>false,'msg'=>'Circunscripción no configurada o vacía']);
            exit();
        }
    }

    // ── HELPERS ───────────────────────────────────────────────────────────────────
    function generarCodigo(PDO $pdo): string {
        $prefix = 'AGE-' . date('ymd') . '-';
        $stmt = $pdo->prepare("SELECT codigo_referencia FROM agenda_eventos WHERE codigo_referencia LIKE ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$prefix . '%']);
        $ultimo = $stmt->fetchColumn();
        $num = $ultimo ? (intval(substr($ultimo, -3)) + 1) : 1;
        return $prefix . str_pad($num, 3, '0', STR_PAD_LEFT);
    }

    function calcularHoras(?string $ini, ?string $fin, $horasPost): float {
        $h = floatval($horasPost);
        if (!$h && $ini && $fin) {
            [$hi, $mi] = array_map('intval', explode(':', $ini));
            [$hf, $mf] = array_map('intval', explode(':', $fin));
            $diff = ($hf * 60 + $mf) - ($hi * 60 + $mi);
            if ($diff > 0) $h = round($diff / 60, 1);
        }
        return $h ?: 1.0;
    }

    // Helper: normaliza y valida un JSON de enlaces (solo URLs http/https)
    function limpiarEnlaces($raw): string {
        if (!is_string($raw)) $raw = json_encode($raw);
        $arr = json_decode($raw, true);
        if (!is_array($arr)) return '[]';
        $limpio = array_values(array_filter(array_map('trim', $arr), function($u) {
            return is_string($u) && $u !== '' && filter_var($u, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $u);
        }));
        return json_encode($limpio, JSON_UNESCAPED_UNICODE);
    }

    // Resuelve el nombre de la región a partir del municipio, usando el JSON
    // `municipios` de regiones_estatales. No depende de que el usuario la
    // seleccione a mano en el formulario.
    function resolverRegionPorMunicipio(PDO $pdo, string $estado, string $municipio): string {
        static $cache = [];
        $estado = trim($estado);
        $municipio = trim($municipio);
        if ($estado === '' || $municipio === '') return '';
        if (!isset($cache[$estado])) {
            $st = $pdo->prepare("SELECT nombre, municipios FROM regiones_estatales WHERE estado = ?");
            $st->execute([$estado]);
            $cache[$estado] = $st->fetchAll(PDO::FETCH_ASSOC);
        }
        $muniNorm = mb_strtoupper($municipio, 'UTF-8');
        foreach ($cache[$estado] as $row) {
            $lista = json_decode($row['municipios'] ?? '[]', true) ?: [];
            foreach ($lista as $m) {
                if (mb_strtoupper(trim($m), 'UTF-8') === $muniNorm) {
                    return $row['nombre'];
                }
            }
        }
        return '';
    }

    // Resuelve el nombre de la zona a partir de la sección electoral, dentro
    // de una región ya resuelta, usando el JSON `secciones` de zonas_estatales.
    function resolverZonaPorSeccion(PDO $pdo, string $regionNombre, string $seccion): string {
        static $cache = [];
        $regionNombre = trim($regionNombre);
        $seccion = trim($seccion);
        if ($regionNombre === '' || $seccion === '') return '';
        if (!isset($cache[$regionNombre])) {
            $st = $pdo->prepare("
                SELECT z.nombre, z.secciones
                FROM zonas_estatales z
                INNER JOIN regiones_estatales r ON z.estructura_id = r.id
                WHERE r.nombre = ?
            ");
            $st->execute([$regionNombre]);
            $cache[$regionNombre] = $st->fetchAll(PDO::FETCH_ASSOC);
        }
        foreach ($cache[$regionNombre] as $row) {
            $lista = json_decode($row['secciones'] ?? '[]', true) ?: [];
            foreach ($lista as $s) {
                if ((string)$s === $seccion) return $row['nombre'];
            }
        }
        return '';
    }

    // ── GET GEO ───────────────────────────────────────────────────────────────────
    if ($action === 'get_geo') {
        $stmtEst = $pdo->query("SELECT id, nombre FROM estados ORDER BY nombre");
        $estados = $stmtEst->fetchAll();
        $stmtMun = $pdo->query("SELECT m.nombre as municipio, e.nombre as estado FROM municipios m JOIN estados e ON m.estado_id = e.id ORDER BY m.nombre");
        $municipiosMap = [];
        foreach ($stmtMun->fetchAll() as $row) {
            $municipiosMap[$row['estado']][] = $row['municipio'];
        }
        echo json_encode(['ok'=>true, 'estados'=>$estados, 'municipios'=>$municipiosMap]);
        exit();
    }

    // ── CREATE ────────────────────────────────────────────────────────────────────
    if ($action === 'create') {
        $titulo      = trim($_POST['titulo'] ?? '');
        $fecha       = $_POST['fecha'] ?? '';
        $eje         = $_POST['eje']   ?? '';
        $estado_post = trim($_POST['estado'] ?? '');
        $zona_post   = trim($_POST['zona'] ?? '');

        // Validación de jurisdicción al crear
        if ($rol === 'coordinador_estatal' && $estado_coordinador) {
            $estado_post = $estado_coordinador;
        } elseif ($rol === 'coordinador_zona') {
            $estado_post = $estado_usuario;
            // Resolver zona: guardar siempre el NOMBRE (no el ID numérico)
            // para que el rol regional y otros puedan leerla sin conversión
            $zona_id_num = (int)$zona_usuario;
            if ($zona_id_num > 0) {
                $stZN = $pdo->prepare("
                    SELECT z.nombre AS zona_nombre, r.nombre AS region_nombre
                    FROM zonas_estatales z
                    LEFT JOIN regiones_estatales r ON z.estructura_id = r.id
                    WHERE z.id = ? LIMIT 1
                ");
                $stZN->execute([$zona_id_num]);
                $zonaRow = $stZN->fetch(PDO::FETCH_ASSOC);
                if ($zonaRow) {
                    $zona_post = $zonaRow['zona_nombre'];
                    // Si el POST no trae región, usar la de la BD
                    if (empty(trim($_POST['region'] ?? ''))) {
                        $_POST['region'] = $zonaRow['region_nombre'] ?? '';
                    }
                    // Si el POST no trae municipio, resolverlo desde la sección
                    $seccion_post = trim($_POST['seccion'] ?? '');
                    if (!empty($seccion_post) && empty(trim($_POST['municipio'] ?? ''))) {
                        $stMun = $pdo->prepare("
                            SELECT m.nombre FROM secciones s
                            LEFT JOIN municipios m ON m.id = s.municipio_id
                            WHERE s.seccion = ? AND s.estado_nombre = ? LIMIT 1
                        ");
                        $stMun->execute([$seccion_post, $estado_usuario]);
                        $munNombre = $stMun->fetchColumn();
                        if ($munNombre) $_POST['municipio'] = $munNombre;
                    }
                }
            } else {
                $zona_post = $zona_usuario; // fallback: ya es nombre de texto
            }
        } elseif ($rol === 'coordinador_regional' || $rol === 'regional') {
            $estado_post = $estado_usuario;
            // Resolver región siempre desde la BD (nunca confiar en lo que mande el cliente)
            $region_id_num = (int)($_SESSION['user_region'] ?? 0);
            if ($region_id_num > 0) {
                $stRN = $pdo->prepare("SELECT nombre FROM regiones_estatales WHERE id = ? LIMIT 1");
                $stRN->execute([$region_id_num]);
                $regionNombre = $stRN->fetchColumn();
                if ($regionNombre) $_POST['region'] = $regionNombre;
            }
        } elseif (!empty($estados_circunscripcion) && !empty($estado_post) && !in_array($estado_post, $estados_circunscripcion)) {
            echo json_encode(['ok'=>false,'msg'=>'No tienes permisos para agendar en este estado']);
            exit();
        }

        if (!$titulo || !$fecha || !$eje) {
            echo json_encode(['ok'=>false,'msg'=>'Faltan campos obligatorios']);
            exit();
        }
        if (!trim($_POST['municipio'] ?? '') || !trim($_POST['seccion'] ?? '')) {
            echo json_encode(['ok'=>false,'msg'=>'Debes indicar municipio y sección electoral']);
            exit();
        }

        $ini   = !empty($_POST['inicio']) ? $_POST['inicio'] : null;
        $fin   = !empty($_POST['fin'])    ? $_POST['fin']    : null;
        $horas = calcularHoras($ini, $fin, $_POST['horas'] ?? 0);
        $codigo = generarCodigo($pdo);

        $lat = (isset($_POST['lat']) && $_POST['lat'] !== '' && $_POST['lat'] !== 'undefined') ? floatval($_POST['lat']) : null;
        $lng = (isset($_POST['lng']) && $_POST['lng'] !== '' && $_POST['lng'] !== 'undefined') ? floatval($_POST['lng']) : null;

        // Respaldo: si la región/zona no vino resuelta por el rol ni elegida
        // a mano en el formulario, se calcula a partir del municipio/sección.
        $municipio_final = trim($_POST['municipio'] ?? '');
        $region_final    = trim($_POST['region'] ?? '');
        if ($region_final === '' && $municipio_final !== '' && $estado_post !== '') {
            $region_final = resolverRegionPorMunicipio($pdo, $estado_post, $municipio_final);
        }
        $seccion_final = trim($_POST['seccion'] ?? '');
        $zona_final    = $zona_post;
        if ($zona_final === '' && $region_final !== '' && $seccion_final !== '') {
            $zona_final = resolverZonaPorSeccion($pdo, $region_final, $seccion_final);
        }

        // Evidencia: llega como JSON string desde el frontend (fotos en base64, PDFs {name,dataUrl})
        $evidencia_fotos = $_POST['evidencia_fotos'] ?? '[]';
        $evidencia_pdfs  = $_POST['evidencia_pdfs']  ?? '[]';
        if (!is_string($evidencia_fotos)) $evidencia_fotos = json_encode($evidencia_fotos);
        if (!is_string($evidencia_pdfs))  $evidencia_pdfs  = json_encode($evidencia_pdfs);
        json_decode($evidencia_fotos); if (json_last_error() !== JSON_ERROR_NONE) $evidencia_fotos = '[]';
        json_decode($evidencia_pdfs);  if (json_last_error() !== JSON_ERROR_NONE) $evidencia_pdfs  = '[]';
        $evidencia_enlaces = limpiarEnlaces($_POST['evidencia_enlaces'] ?? '[]');

        $stmt = $pdo->prepare("
            INSERT INTO agenda_eventos 
            (codigo_referencia, eje, subtipo, titulo, fecha, estado, municipio, seccion, region, zona, lugar, lat, lng,
             inicio, fin, horas, responsable, activacion, notas, evidencia_fotos, evidencia_pdfs, evidencia_enlaces,
             creado_por_id, creado_por_rol, activo)
            VALUES 
            (:codigo, :eje, :subtipo, :titulo, :fecha, :estado, :municipio, :seccion, :region, :zona, :lugar, :lat, :lng,
             :inicio, :fin, :horas, :responsable, :activacion, :notas, :evidencia_fotos, :evidencia_pdfs, :evidencia_enlaces,
             :creado_por_id, :creado_por_rol, 1)
        ");
        $stmt->execute([
            ':codigo'          => $codigo, 
            ':eje'             => $eje, 
            ':subtipo'         => trim($_POST['subtipo'] ?? ''),
            ':titulo'          => $titulo, 
            ':fecha'           => $fecha, 
            ':estado'          => $estado_post,
            ':municipio'       => $municipio_final, 
            ':seccion'         => $seccion_final,
            ':region'          => $region_final, 
            ':zona'            => $zona_final,
            ':lugar'           => trim($_POST['lugar'] ?? ''),
            ':lat'             => $lat, 
            ':lng'             => $lng,
            ':inicio'          => $ini, 
            ':fin'             => $fin, 
            ':horas'           => $horas,
            ':responsable'     => trim($_POST['responsable'] ?? ''),
            ':activacion'      => trim($_POST['activacion']  ?? ''),
            ':notas'           => trim($_POST['notas']        ?? ''),
            ':evidencia_fotos' => $evidencia_fotos,
            ':evidencia_pdfs'  => $evidencia_pdfs,
            ':evidencia_enlaces' => $evidencia_enlaces,
            ':creado_por_id'   => $user_id, 
            ':creado_por_rol'  => $rol
        ]);
        echo json_encode(['ok'=>true, 'id'=>(int)$pdo->lastInsertId(), 'codigo'=>$codigo]);
        exit();
    }

    // ── UPDATE ────────────────────────────────────────────────────────────────────
    if ($action === 'update') {
        $id = intval($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['ok'=>false,'msg'=>'ID requerido']); exit(); }

        // Obtenemos los datos actuales para verificar permisos
        $check = $pdo->prepare("SELECT estado, zona, realizado FROM agenda_eventos WHERE id = ? AND activo = 1");
        $check->execute([$id]);
        $evento_actual = $check->fetch(PDO::FETCH_ASSOC);

        if (!$evento_actual) {
            echo json_encode(['ok'=>false,'msg'=>'Evento no encontrado']); exit();
        }

        if ((int)($evento_actual['realizado'] ?? 0) === 1) {
            echo json_encode(['ok'=>false,'msg'=>'Este evento ya fue marcado como Realizado y no puede editarse.']);
            exit();
        }

        if ($rol === 'coordinador_estatal' && $estado_coordinador && $evento_actual['estado'] !== $estado_coordinador) {
            echo json_encode(['ok'=>false,'msg'=>'No puedes editar eventos de otros estados']);
            exit();
        } elseif ($rol === 'coordinador_zona') {
            // La zona puede estar guardada como ID numérico (legacy) o como nombre (nuevo)
            $zona_id_num_chk = (int)$zona_usuario;
            $zona_nombre_chk = '';
            if ($zona_id_num_chk > 0) {
                $stChk = $pdo->prepare("SELECT nombre FROM zonas_estatales WHERE id = ? LIMIT 1");
                $stChk->execute([$zona_id_num_chk]);
                $zona_nombre_chk = (string)($stChk->fetchColumn() ?: '');
            }
            $zona_bd = (string)($evento_actual['zona'] ?? '');
            $zona_ok = ($zona_bd === (string)$zona_usuario)
                    || ($zona_nombre_chk && $zona_bd === $zona_nombre_chk)
                    || ($zona_bd === (string)$zona_id_num_chk);
            if ($evento_actual['estado'] !== $estado_usuario || !$zona_ok) {
                echo json_encode(['ok'=>false,'msg'=>'No puedes editar eventos de otras zonas']);
                exit();
            }
        } elseif (!empty($estados_circunscripcion) && !in_array($evento_actual['estado'], $estados_circunscripcion)) {
            echo json_encode(['ok'=>false,'msg'=>'El evento pertenece a una circunscripción ajena']);
            exit();
        }

        $ini         = !empty($_POST['inicio']) ? $_POST['inicio'] : null;
        $fin         = !empty($_POST['fin'])    ? $_POST['fin']    : null;
        $horas       = calcularHoras($ini, $fin, $_POST['horas'] ?? 0);
        
        $estado_post = trim($_POST['estado'] ?? '');
        $zona_post   = trim($_POST['zona'] ?? '');

        // Forzar jurisdicción
        if ($rol === 'coordinador_estatal') {
            $estado_post = $estado_coordinador;
        } elseif ($rol === 'coordinador_zona') {
            $estado_post = $estado_usuario;
            // Resolver zona: guardar siempre el NOMBRE (no el ID numérico)
            $zona_id_num = (int)$zona_usuario;
            if ($zona_id_num > 0) {
                $stZN2 = $pdo->prepare("
                    SELECT z.nombre AS zona_nombre, r.nombre AS region_nombre
                    FROM zonas_estatales z
                    LEFT JOIN regiones_estatales r ON z.estructura_id = r.id
                    WHERE z.id = ? LIMIT 1
                ");
                $stZN2->execute([$zona_id_num]);
                $zonaRow2 = $stZN2->fetch(PDO::FETCH_ASSOC);
                if ($zonaRow2) {
                    $zona_post = $zonaRow2['zona_nombre'];
                    if (empty(trim($_POST['region'] ?? ''))) {
                        $_POST['region'] = $zonaRow2['region_nombre'] ?? '';
                    }
                    $seccion_post2 = trim($_POST['seccion'] ?? '');
                    if (!empty($seccion_post2) && empty(trim($_POST['municipio'] ?? ''))) {
                        $stMun2 = $pdo->prepare("
                            SELECT m.nombre FROM secciones s
                            LEFT JOIN municipios m ON m.id = s.municipio_id
                            WHERE s.seccion = ? AND s.estado_nombre = ? LIMIT 1
                        ");
                        $stMun2->execute([$seccion_post2, $estado_usuario]);
                        $munNombre2 = $stMun2->fetchColumn();
                        if ($munNombre2) $_POST['municipio'] = $munNombre2;
                    }
                }
            } else {
                $zona_post = $zona_usuario;
            }
        } elseif ($rol === 'coordinador_regional' || $rol === 'regional') {
            $estado_post = $estado_usuario;
            $region_id_num = (int)($_SESSION['user_region'] ?? 0);
            if ($region_id_num > 0) {
                $stRN2 = $pdo->prepare("SELECT nombre FROM regiones_estatales WHERE id = ? LIMIT 1");
                $stRN2->execute([$region_id_num]);
                $regionNombre2 = $stRN2->fetchColumn();
                if ($regionNombre2) $_POST['region'] = $regionNombre2;
            }
        }

        if (!trim($_POST['municipio'] ?? '') || !trim($_POST['seccion'] ?? '')) {
            echo json_encode(['ok'=>false,'msg'=>'Debes indicar municipio y sección electoral']);
            exit();
        }

        $lat = (isset($_POST['lat']) && $_POST['lat'] !== '' && $_POST['lat'] !== 'undefined') ? floatval($_POST['lat']) : null;
        $lng = (isset($_POST['lng']) && $_POST['lng'] !== '' && $_POST['lng'] !== 'undefined') ? floatval($_POST['lng']) : null;

        // Respaldo: mismo cálculo automático que en create.
        $municipio_final2 = trim($_POST['municipio'] ?? '');
        $region_final2    = trim($_POST['region'] ?? '');
        if ($region_final2 === '' && $municipio_final2 !== '' && $estado_post !== '') {
            $region_final2 = resolverRegionPorMunicipio($pdo, $estado_post, $municipio_final2);
        }
        $seccion_final2 = trim($_POST['seccion'] ?? '');
        $zona_final2    = $zona_post;
        if ($zona_final2 === '' && $region_final2 !== '' && $seccion_final2 !== '') {
            $zona_final2 = resolverZonaPorSeccion($pdo, $region_final2, $seccion_final2);
        }

        // Evidencia: llega como JSON string desde el frontend (fotos en base64, PDFs {name,dataUrl})
        $evidencia_fotos = $_POST['evidencia_fotos'] ?? '[]';
        $evidencia_pdfs  = $_POST['evidencia_pdfs']  ?? '[]';
        if (!is_string($evidencia_fotos)) $evidencia_fotos = json_encode($evidencia_fotos);
        if (!is_string($evidencia_pdfs))  $evidencia_pdfs  = json_encode($evidencia_pdfs);
        json_decode($evidencia_fotos); if (json_last_error() !== JSON_ERROR_NONE) $evidencia_fotos = '[]';
        json_decode($evidencia_pdfs);  if (json_last_error() !== JSON_ERROR_NONE) $evidencia_pdfs  = '[]';
        $evidencia_enlaces = limpiarEnlaces($_POST['evidencia_enlaces'] ?? '[]');

        $stmt = $pdo->prepare("
            UPDATE agenda_eventos SET
                eje=:eje, subtipo=:subtipo, titulo=:titulo, fecha=:fecha,
                estado=:estado, municipio=:municipio, seccion=:seccion, region=:region, zona=:zona, lugar=:lugar,
                lat=:lat, lng=:lng,
                inicio=:inicio, fin=:fin, horas=:horas,
                responsable=:responsable, activacion=:activacion, notas=:notas,
                evidencia_fotos=:evidencia_fotos, evidencia_pdfs=:evidencia_pdfs, evidencia_enlaces=:evidencia_enlaces
            WHERE id=:id AND activo=1
        ");
        $stmt->execute([
            ':eje'             => $_POST['eje'] ?? 'voto',
            ':subtipo'         => trim($_POST['subtipo']     ?? ''),
            ':titulo'          => trim($_POST['titulo']      ?? ''),
            ':fecha'           => $_POST['fecha']             ?? '',
            ':estado'          => $estado_post,
            ':municipio'       => $municipio_final2,
            ':seccion'         => $seccion_final2,
            ':region'          => $region_final2,
            ':zona'            => $zona_final2,
            ':lugar'           => trim($_POST['lugar']        ?? ''),
            ':lat'             => $lat,
            ':lng'             => $lng,
            ':inicio'          => $ini,
            ':fin'             => $fin,
            ':horas'           => $horas,
            ':responsable'     => trim($_POST['responsable'] ?? ''),
            ':activacion'      => trim($_POST['activacion']  ?? ''),
            ':notas'           => trim($_POST['notas']       ?? ''),
            ':evidencia_fotos' => $evidencia_fotos,
            ':evidencia_pdfs'  => $evidencia_pdfs,
            ':evidencia_enlaces' => $evidencia_enlaces,
            ':id'              => $id
        ]);
        echo json_encode(['ok'=>true]);
        exit();
    }

    // ── DELETE ────────────────────────────────────────────────────────────────────
    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);

        $check = $pdo->prepare("SELECT estado, zona, realizado FROM agenda_eventos WHERE id = ?");
        $check->execute([$id]);
        $evento_actual = $check->fetch(PDO::FETCH_ASSOC);

        if (!$evento_actual) {
            echo json_encode(['ok'=>false,'msg'=>'Evento no encontrado']);
            exit();
        }

        if ((int)($evento_actual['realizado'] ?? 0) === 1) {
            echo json_encode(['ok'=>false,'msg'=>'Este evento ya fue marcado como Realizado y no puede eliminarse.']);
            exit();
        }

        if ($rol === 'coordinador_estatal' && $estado_coordinador && $evento_actual['estado'] !== $estado_coordinador) {
            echo json_encode(['ok'=>false,'msg'=>'No tienes permiso para eliminar este evento']);
            exit();
        } elseif ($rol === 'coordinador_zona') {
            $zona_id_num_del = (int)$zona_usuario;
            $zona_nombre_del = '';
            if ($zona_id_num_del > 0) {
                $stDel = $pdo->prepare("SELECT nombre FROM zonas_estatales WHERE id = ? LIMIT 1");
                $stDel->execute([$zona_id_num_del]);
                $zona_nombre_del = (string)($stDel->fetchColumn() ?: '');
            }
            $zona_bd_del = (string)($evento_actual['zona'] ?? '');
            $zona_ok_del = ($zona_bd_del === (string)$zona_usuario)
                        || ($zona_nombre_del && $zona_bd_del === $zona_nombre_del)
                        || ($zona_bd_del === (string)$zona_id_num_del);
            if ($evento_actual['estado'] !== $estado_usuario || !$zona_ok_del) {
                echo json_encode(['ok'=>false,'msg'=>'No tienes permiso para eliminar eventos de otras zonas']);
                exit();
            }
        } elseif (!empty($estados_circunscripcion) && !in_array($evento_actual['estado'], $estados_circunscripcion)) {
            echo json_encode(['ok'=>false,'msg'=>'El evento pertenece a una circunscripción ajena']);
            exit();
        }

        $pdo->prepare("UPDATE agenda_eventos SET activo=0 WHERE id=:id")->execute([':id'=>$id]);
        echo json_encode(['ok'=>true]);
        exit();
    }

    // ── MARCAR REALIZADO ─────────────────────────────────────────────────────────
    if ($action === 'marcar_realizado') {
        $id = intval($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['ok'=>false,'msg'=>'ID requerido']); exit(); }

        $check = $pdo->prepare("SELECT estado, zona, realizado FROM agenda_eventos WHERE id = ? AND activo = 1");
        $check->execute([$id]);
        $evento_actual = $check->fetch(PDO::FETCH_ASSOC);

        if (!$evento_actual) {
            echo json_encode(['ok'=>false,'msg'=>'Evento no encontrado']); exit();
        }
        if ((int)($evento_actual['realizado'] ?? 0) === 1) {
            echo json_encode(['ok'=>false,'msg'=>'Este evento ya estaba marcado como Realizado']); exit();
        }

        // Misma validación de jurisdicción que en update/delete
        if ($rol === 'coordinador_estatal' && $estado_coordinador && $evento_actual['estado'] !== $estado_coordinador) {
            echo json_encode(['ok'=>false,'msg'=>'No tienes permiso sobre este evento']);
            exit();
        } elseif ($rol === 'coordinador_zona') {
            $zona_id_num_mr = (int)$zona_usuario;
            $zona_nombre_mr = '';
            if ($zona_id_num_mr > 0) {
                $stMR = $pdo->prepare("SELECT nombre FROM zonas_estatales WHERE id = ? LIMIT 1");
                $stMR->execute([$zona_id_num_mr]);
                $zona_nombre_mr = (string)($stMR->fetchColumn() ?: '');
            }
            $zona_bd_mr = (string)($evento_actual['zona'] ?? '');
            $zona_ok_mr = ($zona_bd_mr === (string)$zona_usuario)
                       || ($zona_nombre_mr && $zona_bd_mr === $zona_nombre_mr)
                       || ($zona_bd_mr === (string)$zona_id_num_mr);
            if ($evento_actual['estado'] !== $estado_usuario || !$zona_ok_mr) {
                echo json_encode(['ok'=>false,'msg'=>'No tienes permiso sobre eventos de otras zonas']);
                exit();
            }
        } elseif (($rol === 'coordinador_regional' || $rol === 'regional') && $evento_actual['estado'] !== $estado_usuario) {
            echo json_encode(['ok'=>false,'msg'=>'No tienes permiso sobre eventos de otros estados']);
            exit();
        } elseif (!empty($estados_circunscripcion) && !in_array($evento_actual['estado'], $estados_circunscripcion)) {
            echo json_encode(['ok'=>false,'msg'=>'El evento pertenece a una circunscripción ajena']);
            exit();
        }

        $stmt = $pdo->prepare("
            UPDATE agenda_eventos
            SET realizado = 1, fecha_realizado = NOW(), realizado_por_id = :uid, estatus = 'completado'
            WHERE id = :id AND activo = 1
        ");
        $stmt->execute([':uid' => $user_id, ':id' => $id]);

        echo json_encode(['ok'=>true]);
        exit();
    }

    // ── LIST ──────────────────────────────────────────────────────────────────────
    if ($action === 'list') {
        if ($rol === 'coordinador_estatal' && $estado_coordinador) {
            $stmt = $pdo->prepare("SELECT id, codigo_referencia, eje, subtipo, titulo, fecha, estado, municipio, seccion, region, zona, lugar, inicio, fin, horas, responsable, activacion, notas, estatus, realizado, fecha_realizado, realizado_por_id, fecha_creacion, fecha_actualizacion, creado_por_id, creado_por_rol, activo, lat, lng FROM agenda_eventos WHERE activo=1 AND estado=? ORDER BY fecha, inicio");
            $stmt->execute([$estado_coordinador]);
        } elseif ($rol === 'coordinador_zona') {
            // FIX: el coordinador estatal guarda zona como NOMBRE (ej. "Z1.1 Humaya-Fovissste")
            // pero $zona_usuario es el ID NUMÉRICO (ej. "5").
            // Necesitamos buscar por ambos para no perder eventos asignados desde estatal.
            $zona_nombre_bd = '';
            if (!empty($zona_usuario)) {
                $stN = $pdo->prepare("SELECT nombre FROM zonas_estatales WHERE id = ? LIMIT 1");
                $stN->execute([$zona_usuario]);
                $zona_nombre_bd = (string)($stN->fetchColumn() ?: '');
            }

            if (!empty($zona_nombre_bd)) {
                // Busca por ID numérico O por nombre de zona
                $stmt = $pdo->prepare("SELECT id, codigo_referencia, eje, subtipo, titulo, fecha, estado, municipio, seccion, region, zona, lugar, inicio, fin, horas, responsable, activacion, notas, estatus, realizado, fecha_realizado, realizado_por_id, fecha_creacion, fecha_actualizacion, creado_por_id, creado_por_rol, activo, lat, lng FROM agenda_eventos WHERE activo=1 AND estado=? AND (zona=? OR zona=?) ORDER BY fecha, inicio");
                $stmt->execute([$estado_usuario, $zona_usuario, $zona_nombre_bd]);
            } else {
                $stmt = $pdo->prepare("SELECT id, codigo_referencia, eje, subtipo, titulo, fecha, estado, municipio, seccion, region, zona, lugar, inicio, fin, horas, responsable, activacion, notas, estatus, realizado, fecha_realizado, realizado_por_id, fecha_creacion, fecha_actualizacion, creado_por_id, creado_por_rol, activo, lat, lng FROM agenda_eventos WHERE activo=1 AND estado=? AND zona=? ORDER BY fecha, inicio");
                $stmt->execute([$estado_usuario, $zona_usuario]);
            }
        } elseif ($rol === 'coordinador_regional' || $rol === 'regional') {
            $stmt = $pdo->prepare("SELECT id, codigo_referencia, eje, subtipo, titulo, fecha, estado, municipio, seccion, region, zona, lugar, inicio, fin, horas, responsable, activacion, notas, estatus, realizado, fecha_realizado, realizado_por_id, fecha_creacion, fecha_actualizacion, creado_por_id, creado_por_rol, activo, lat, lng FROM agenda_eventos WHERE activo=1 AND estado=? ORDER BY fecha, inicio");
            $stmt->execute([$estado_usuario]);
            $todosEstado = $stmt->fetchAll();

            // FIX: antes se regresaban TODOS los eventos del estado sin filtrar
            // por región, lo que hacía que "Ver Todo" mostrara 179 en vez de
            // los ~49 que realmente pertenecen a la región del coordinador.
            // Resolvemos la región del usuario y filtramos igual que en
            // territorio_php_cr.php (mapa_electoral): 1) región exacta,
            // 2) sección dentro del catálogo de zonas de la región,
            // 3) respaldo por municipio SOLO si el evento no trae región
            //    asignada (dato viejo/incompleto).
            $user_region_id = $_SESSION['user_region'] ?? '';
            if (!empty($user_region_id)) {
                $stR = $pdo->prepare("SELECT id, nombre, municipios FROM regiones_estatales WHERE id = ? LIMIT 1");
                $stR->execute([$user_region_id]);
                $reg = $stR->fetch(PDO::FETCH_ASSOC);

                $regionNombre     = $reg['nombre'] ?? '';
                $municipiosRegion = $reg
                    ? array_map(fn($m) => mb_strtoupper(trim($m), 'UTF-8'), json_decode($reg['municipios'] ?? '[]', true) ?: [])
                    : [];

                $seccionesRegion = [];
                if ($reg) {
                    $stZ = $pdo->prepare("SELECT secciones FROM zonas_estatales WHERE estructura_id = ?");
                    $stZ->execute([$reg['id']]);
                    foreach ($stZ->fetchAll(PDO::FETCH_ASSOC) as $z) {
                        foreach (json_decode($z['secciones'] ?? '[]', true) ?: [] as $s) {
                            $seccionesRegion[(string)$s] = true;
                        }
                    }
                }

                // FIX: la región se guarda como TEXTO en agenda_eventos. Si la
                // región se renombra en regiones_estatales (ej. "RE1 CM PVEM" →
                // "RE1 CM PVEM (Culiacán)"), los eventos viejos conservan el
                // nombre anterior y dejaban de coincidir, desapareciendo de la
                // agenda del regional (incluso sus propios eventos).
                $normRegion = function (string $s): string {
                    $s = mb_strtoupper(trim($s), 'UTF-8');
                    $s = preg_replace('/\s*\([^)]*\)\s*$/u', '', $s); // quita sufijo "(Culiacán)"
                    return preg_replace('/\s+/u', ' ', trim($s));
                };
                $regionNorm = $normRegion($regionNombre);

                // Nombres vigentes de todas las regiones del estado: si el evento
                // trae una región que ya no existe (renombrada), se resuelve por
                // sección/municipio en lugar de descartarlo.
                $stRegs = $pdo->prepare("SELECT nombre FROM regiones_estatales WHERE estado = ?");
                $stRegs->execute([$estado_usuario]);
                $regionesVigentes = [];
                foreach ($stRegs->fetchAll(PDO::FETCH_COLUMN) as $rn) {
                    $regionesVigentes[mb_strtoupper(trim((string)$rn), 'UTF-8')] = true;
                }

                $eventosFiltrados = array_values(array_filter($todosEstado, function ($ev) use ($regionNombre, $regionNorm, $normRegion, $regionesVigentes, $seccionesRegion, $municipiosRegion, $user_id) {
                    // 1) Los eventos creados por el propio regional siempre son suyos
                    if (!empty($user_id) && (string)($ev['creado_por_id'] ?? '') === (string)$user_id) {
                        return true;
                    }
                    $evRegion = trim((string)($ev['region'] ?? ''));
                    if ($evRegion !== '') {
                        $evUp = mb_strtoupper($evRegion, 'UTF-8');
                        // 2) Región exacta o mismo nombre base (sin sufijo entre paréntesis)
                        if ($evUp === mb_strtoupper($regionNombre, 'UTF-8')) return true;
                        if ($regionNorm !== '' && $normRegion($evRegion) === $regionNorm) return true;
                        // 3) Pertenece a otra región vigente → no es de este regional
                        if (isset($regionesVigentes[$evUp])) return false;
                        // 4) Región obsoleta/desconocida → resolver solo por sección
                        //    (el municipio no basta: Culiacán está en varias regiones)
                        return isset($seccionesRegion[(string)($ev['seccion'] ?? '')]);
                    }
                    if (isset($seccionesRegion[(string)($ev['seccion'] ?? '')])) {
                        return true;
                    }
                    $evMuni = mb_strtoupper(trim((string)($ev['municipio'] ?? '')), 'UTF-8');
                    return $evMuni !== '' && in_array($evMuni, $municipiosRegion, true);
                }));

                echo json_encode(['ok'=>true,'eventos'=>$eventosFiltrados]);
                exit();
            }

            // Sin región asignada en sesión: no filtramos (comportamiento anterior)
            echo json_encode(['ok'=>true,'eventos'=>$todosEstado]);
            exit();
        } elseif (!empty($estados_circunscripcion)) {
            $in = implode(',', array_fill(0, count($estados_circunscripcion), '?'));
            $stmt = $pdo->prepare("SELECT id, codigo_referencia, eje, subtipo, titulo, fecha, estado, municipio, seccion, region, zona, lugar, inicio, fin, horas, responsable, activacion, notas, estatus, realizado, fecha_realizado, realizado_por_id, fecha_creacion, fecha_actualizacion, creado_por_id, creado_por_rol, activo, lat, lng FROM agenda_eventos WHERE activo=1 AND estado IN ($in) ORDER BY fecha, inicio");
            $stmt->execute($estados_circunscripcion);
        } else {
            // Admin / Superadmin
            $stmt = $pdo->query("SELECT id, codigo_referencia, eje, subtipo, titulo, fecha, estado, municipio, seccion, region, zona, lugar, inicio, fin, horas, responsable, activacion, notas, estatus, realizado, fecha_realizado, realizado_por_id, fecha_creacion, fecha_actualizacion, creado_por_id, creado_por_rol, activo, lat, lng FROM agenda_eventos WHERE activo=1 ORDER BY fecha, inicio");
        }
        echo json_encode(['ok'=>true,'eventos'=>$stmt->fetchAll()]);
        exit();
    }

    echo json_encode(['ok'=>false,'msg'=>'Acción no reconocida']);

} catch (Exception $e) {
    // CAPTURA GLOBAL DE ERRORES: devuelve el error exacto si algo más falla en la BD
    http_response_code(500);
    echo json_encode([
        'ok'  => false,
        'msg' => 'Error en el servidor: ' . $e->getMessage()
    ]);
    exit();
}