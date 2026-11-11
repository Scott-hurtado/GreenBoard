<?php
// /api/secciones/secciones_api.php

// ── Seguridad y Configuración Inicial ─────────────────────
if (file_exists(__DIR__ . '/../../includes/bootstrap.php')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
} else { 
    session_start(); 
}

$rol        = $_SESSION['user_role'] ?? '';
// Permitimos acceso a los coordinadores estatales para que la Agenda y Territorio funcionen
$permitidos = ['admin', 'administrador', 'superadmin', 'super admin', 'coordinador_estatal', 'coordinador estatal', 'estatal', 'circunscripcion'];

if (!in_array($rol, $permitidos)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Sin permisos']);
    exit();
}

header('Content-Type: application/json; charset=utf-8');

// ── Conexión a la Base de Datos ───────────────────────────
try {
    $dbConfigPath = __DIR__ . '/../../includes/database.php';
    $dbConfig = file_exists($dbConfigPath) ? require $dbConfigPath : [
        'host'     => 'localhost',
        'database' => 'u881824383_pverde',
        'username' => 'u881824383_proyverde',
        'password' => 'PProyectoverde8#',
        'charset'  => 'utf8mb4',
        'options'  => [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ],
    ];
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options'] ?? []);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error de BD: ' . $e->getMessage()]);
    exit();
}

// ── Configuración de Estados ──────────────────────────────
$GEO_ESTADOS = [
    'sonora'    => ['clave_ine' => 26, 'label' => 'Sonora'],
    'bc'        => ['clave_ine' => 2,  'label' => 'Baja California'],
    'bcs'       => ['clave_ine' => 3,  'label' => 'Baja California Sur'],
    'chihuahua' => ['clave_ine' => 8,  'label' => 'Chihuahua'],
    'durango'   => ['clave_ine' => 10, 'label' => 'Durango'],
    'jalisco'   => ['clave_ine' => 14, 'label' => 'Jalisco'],
    'nayarit'   => ['clave_ine' => 18, 'label' => 'Nayarit'],
    'sinaloa'   => ['clave_ine' => 25, 'label' => 'Sinaloa'],
];

// ── Campos permitidos para tablas de gestión ──────────────
$CAMPOS_PERMITIDOS = [
    'nombre_seccion', 'estado', 'municipio', 'distrito_federal', 'distrito_local',
    'region_asignada', 'zona_asignada', 'lista_nominal',
    'grupo_edad_18_24', 'grupo_edad_25_34', 'grupo_edad_35_44', 'grupo_edad_45_54', 'grupo_edad_55_mas',
    'meta_contactos', 'contactos_conseguidos', 'eventos_realizados', 'metas_votos',
    'activaciones_totales', 'con_candidato', 'sin_candidato',
    'principales_necesidades', 'enfoque', 'perfil_socioeconomico',
];

function fetchZonaSeccion($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM secciones_electorales WHERE id = ? AND activo = 1");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    // ── OBTENER CATÁLOGO DE ESTADOS Y MUNICIPIOS ──
    case 'geo_data':
        $claves_ine = array_column(array_values($GEO_ESTADOS), 'clave_ine');
        $in = implode(',', array_map('intval', $claves_ine));

        $stmt = $pdo->query("
            SELECT m.nombre AS municipio_nombre, e.cve_ine AS estado_cve_ine 
            FROM municipios m 
            INNER JOIN estados e ON e.id = m.estado_id 
            WHERE e.cve_ine IN ($in) 
            ORDER BY e.cve_ine, m.nombre
        ");
        $rows = $stmt->fetchAll();

        $munisPorCveIne = [];
        foreach ($rows as $r) {
            $munisPorCveIne[(int)$r['estado_cve_ine']][] = $r['municipio_nombre'];
        }

        $resultado = [];
        foreach ($GEO_ESTADOS as $key => $cfg) {
            $resultado[$key] = [
                'label'      => $cfg['label'],
                'municipios' => $munisPorCveIne[$cfg['clave_ine']] ?? [],
            ];
        }
        echo json_encode(['ok' => true, 'estados' => $resultado], JSON_UNESCAPED_UNICODE);
        break;

    // ── OBTENER SECCIONES DE UN MUNICIPIO ──
    case 'geo_secciones':
        $key = trim($_GET['estado'] ?? '');
        $municipio = trim($_GET['municipio'] ?? '');

        if (!$key || !isset($GEO_ESTADOS[$key]) || $municipio === '') {
            echo json_encode(['ok' => false, 'msg' => 'Parámetros incompletos']); 
            break;
        }

        $cveIne = $GEO_ESTADOS[$key]['clave_ine'];
        
        $stmtS = $pdo->prepare("
            SELECT s.seccion 
            FROM secciones s
            INNER JOIN estados e ON s.estado_id = e.id
            INNER JOIN municipios m ON s.municipio_id = m.id
            WHERE e.cve_ine = ? AND UPPER(m.nombre) = UPPER(?)
            ORDER BY CAST(s.seccion AS UNSIGNED)
        ");
        $stmtS->execute([$cveIne, $municipio]);
        $secciones = $stmtS->fetchAll(PDO::FETCH_COLUMN);

        echo json_encode(['ok' => true, 'secciones' => $secciones], JSON_UNESCAPED_UNICODE);
        break;

    // ── OBTENER COORDENADAS (CENTROIDE) DE UNA SECCIÓN ──
    case 'get_centroid':
        $num = trim($_GET['seccion'] ?? '');
        $est = trim($_GET['estado'] ?? '');
        
        if (!$num) { 
            echo json_encode(['ok' => false, 'msg' => 'Sección requerida']); 
            break; 
        }

        $query = "SELECT lat_centroide as lat, lng_centroide as lng FROM secciones WHERE seccion = ?";
        $params = [$num];

        if ($est) {
            $query .= " AND (estado_nombre = ? OR estado_id = (SELECT id FROM estados WHERE nombre = ? LIMIT 1))";
            $params[] = $est; 
            $params[] = $est;
        }

        $stmt = $pdo->prepare($query . " LIMIT 1");
        $stmt->execute($params);
        $res = $stmt->fetch();

        echo json_encode([
            'ok'  => !!$res,
            'lat' => $res ? (float)$res['lat'] : null,
            'lng' => $res ? (float)$res['lng'] : null
        ]);
        break;

    // ── BUSCAR DATOS DE GESTIÓN POR NÚMERO DE SECCIÓN ──
    case 'get_by_num':
        $num    = trim($_GET['seccion'] ?? $_POST['seccion'] ?? '');
        $estado = trim($_GET['estado']  ?? $_POST['estado']  ?? '');

        if ($num === '') {
            echo json_encode(['ok' => false, 'msg' => 'Número requerido']);
            break;
        }

        try {
            $sql = "SELECT
                        s.seccion          AS nombre_seccion,
                        s.estado_nombre    AS estado,
                        s.distrito_federal,
                        s.distrito_local,
                        m.nombre           AS municipio,
                        COALESCE(se.lista_nominal,           0)  AS lista_nominal,
                        COALESCE(se.lista_hombres,           0)  AS lista_hombres,
                        COALESCE(se.lista_mujeres,           0)  AS lista_mujeres,
                        COALESCE(se.padron_electoral,        0)  AS padron_electoral,
                        COALESCE(se.meta_contactos,          0)  AS meta_contactos,
                        COALESCE(se.contactos_conseguidos,   0)  AS contactos_conseguidos,
                        COALESCE(se.metas_votos,             0)  AS metas_votos,
                        COALESCE(se.region_asignada,        '')  AS region_asignada,
                        COALESCE(se.zona_asignada,          '')  AS zona_asignada,
                        COALESCE(se.principales_necesidades,'')  AS principales_necesidades,
                        COALESCE(se.enfoque,                '')  AS enfoque,
                        COALESCE(se.perfil_socioeconomico,  '')  AS perfil_socioeconomico,
                        COALESCE(se.grupo_edad_18_24,        0)  AS grupo_edad_18_24,
                        COALESCE(se.grupo_edad_25_34,        0)  AS grupo_edad_25_34,
                        COALESCE(se.grupo_edad_35_44,        0)  AS grupo_edad_35_44,
                        COALESCE(se.grupo_edad_45_54,        0)  AS grupo_edad_45_54,
                        COALESCE(se.grupo_edad_55_mas,       0)  AS grupo_edad_55_mas
                    FROM secciones s
                    LEFT JOIN municipios m ON s.municipio_id = m.id
                    LEFT JOIN secciones_electorales se
                        ON  CAST(se.nombre_seccion AS UNSIGNED) = CAST(s.seccion AS UNSIGNED)
                        AND UPPER(TRIM(se.estado)) = UPPER(TRIM(s.estado_nombre))
                    WHERE CAST(s.seccion AS UNSIGNED) = CAST(? AS UNSIGNED)";

            $params = [$num];
            if ($estado !== '') {
                $sql .= " AND UPPER(TRIM(s.estado_nombre)) = UPPER(TRIM(?))";
                $params[] = $estado;
            }
            $sql .= " LIMIT 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $sec = $stmt->fetch();

            if (!$sec) {
                echo json_encode(['ok' => false, 'msg' => 'No encontrada', 'seccion' => null]);
                break;
            }

            // ── BUG A FIX: Buscar Región y Zona desde zonas_estatales / regiones_estatales ──
            // secciones_electorales.region_asignada / zona_asignada pueden estar vacíos.
            // La fuente de verdad es zonas_estatales.secciones (JSON array con números de sección).
            if (empty($sec['region_asignada']) || empty($sec['zona_asignada'])) {
                try {
                    $stZona = $pdo->prepare("
                        SELECT z.nombre AS zona, r.nombre AS region
                        FROM zonas_estatales z
                        INNER JOIN regiones_estatales r ON z.estructura_id = r.id
                        WHERE JSON_CONTAINS(z.secciones, JSON_QUOTE(?))
                          AND UPPER(TRIM(r.estado)) = UPPER(TRIM(?))
                        LIMIT 1
                    ");
                    $stZona->execute([$num, $sec['estado']]);
                    $zonaRow = $stZona->fetch();
                    if ($zonaRow) {
                        if (empty($sec['region_asignada'])) $sec['region_asignada'] = $zonaRow['region'];
                        if (empty($sec['zona_asignada']))   $sec['zona_asignada']   = $zonaRow['zona'];
                    }
                } catch (Exception $ez) {
                    error_log('secciones_api zona_lookup: ' . $ez->getMessage());
                }
            }

            // ── BUG C FIX: Auto-meta usa padron_electoral como base (no lista_nominal) ──
            // lista_nominal puede ser un agregado de distrito; padron_electoral es el dato real por sección.
            if (intval($sec['meta_contactos']) === 0) {
                $base = intval($sec['padron_electoral']) > 0
                    ? intval($sec['padron_electoral'])
                    : intval($sec['lista_nominal']);
                if ($base > 0) {
                    $sec['meta_contactos'] = round($base * 0.12);
                }
            }

            // Defaults seguros para campos dinámicos
            $sec['eventos_realizados']   = 0;
            $sec['activaciones_totales'] = 0;
            $sec['con_candidato']        = 0;
            $sec['sin_candidato']        = 0;
            $sec['eventos_lista']        = [];
            $sec['activaciones_lista']   = [];

            // Eventos de agenda_eventos
            try {
                $s2 = $pdo->prepare("SELECT COUNT(*) FROM agenda_eventos WHERE seccion = ? AND activo = 1");
                $s2->execute([$num]);
                $sec['eventos_realizados'] = (int)$s2->fetchColumn();

                $s3 = $pdo->prepare("
                    SELECT id, titulo, eje, subtipo, fecha, inicio, fin,
                           estado, municipio, responsable, notas
                    FROM agenda_eventos
                    WHERE seccion = ? AND activo = 1
                    ORDER BY fecha DESC, inicio DESC
                    LIMIT 20
                ");
                $s3->execute([$num]);
                $sec['eventos_lista'] = $s3->fetchAll();
            } catch (Exception $e2) {
                error_log('secciones_api agenda_eventos: ' . $e2->getMessage());
            }

            // Activaciones reales
            try {
                $s4 = $pdo->prepare("SELECT COUNT(*) FROM activaciones WHERE seccion_electoral = ? AND activo = 1");
                $s4->execute([$num]);
                $sec['activaciones_totales'] = (int)$s4->fetchColumn();

                $s5 = $pdo->prepare("SELECT COUNT(*) FROM activaciones WHERE seccion_electoral = ? AND activo = 1 AND candidato IS NOT NULL AND candidato <> ''");
                $s5->execute([$num]);
                $sec['con_candidato'] = (int)$s5->fetchColumn();

                $sec['sin_candidato'] = $sec['activaciones_totales'] - $sec['con_candidato'];

                $s6 = $pdo->prepare("
                    SELECT id, titulo, tipo, estatus, fecha_actividad,
                           coordinador, candidato, contactos_estimados, asistentes_estimados
                    FROM activaciones
                    WHERE seccion_electoral = ? AND activo = 1
                    ORDER BY fecha_actividad DESC
                    LIMIT 20
                ");
                $s6->execute([$num]);
                $sec['activaciones_lista'] = $s6->fetchAll();
            } catch (Exception $e3) {
                error_log('secciones_api activaciones: ' . $e3->getMessage());
            }

            echo json_encode(['ok' => true, 'seccion' => $sec]);

        } catch (PDOException $e) {
            error_log('secciones_api get_by_num: ' . $e->getMessage());
            echo json_encode(['ok' => false, 'msg' => 'Error de BD: ' . $e->getMessage()]);
        }
        break;


    // ── CREAR REGISTRO USANDO secciones.id (evita duplicados) ──
    case 'create_for_sec':
        $secId = intval($_POST['sec_id'] ?? 0);
        if (!$secId) {
            echo json_encode(['ok' => false, 'msg' => 'sec_id requerido']);
            break;
        }
        try {
            // Obtener número y estado desde la tabla maestra
            $stSec = $pdo->prepare("SELECT seccion, estado_nombre FROM secciones WHERE id = ? LIMIT 1");
            $stSec->execute([$secId]);
            $rowSec = $stSec->fetch();
            if (!$rowSec) {
                echo json_encode(['ok' => false, 'msg' => 'Sección no encontrada']);
                break;
            }
            // Verificar si ya existe un registro activo para esta sección+estado
            $stChk = $pdo->prepare(
                "SELECT id FROM secciones_electorales
                 WHERE CAST(nombre_seccion AS UNSIGNED) = CAST(? AS UNSIGNED)
                   AND UPPER(TRIM(estado)) = UPPER(TRIM(?))
                   AND activo = 1
                 LIMIT 1"
            );
            $stChk->execute([$rowSec['seccion'], $rowSec['estado_nombre']]);
            $existing = $stChk->fetchColumn();
            if ($existing) {
                // Ya existe → hacer UPDATE con los campos enviados
                $sets = []; $vals = [];
                foreach ($CAMPOS_PERMITIDOS as $c) {
                    if (isset($_POST[$c])) { $sets[] = "$c = ?"; $vals[] = $_POST[$c]; }
                }
                if (!empty($sets)) {
                    $vals[] = $existing;
                    $pdo->prepare("UPDATE secciones_electorales SET " . implode(',', $sets) . " WHERE id = ?")->execute($vals);
                }
                echo json_encode(['ok' => true, 'seccion' => fetchZonaSeccion($pdo, $existing)]);
                break;
            }
            // No existe → INSERT seguro
            $campos = ['nombre_seccion', 'estado']; $vals = [$rowSec['seccion'], $rowSec['estado_nombre']];
            foreach ($CAMPOS_PERMITIDOS as $c) {
                if (isset($_POST[$c])) { $campos[] = $c; $vals[] = $_POST[$c]; }
            }
            $cols  = implode(',', $campos);
            $marks = implode(',', array_fill(0, count($campos), '?'));
            $pdo->prepare("INSERT INTO secciones_electorales ($cols, activo, fecha_creacion) VALUES ($marks, 1, NOW())")->execute($vals);
            echo json_encode(['ok' => true, 'seccion' => fetchZonaSeccion($pdo, $pdo->lastInsertId())]);
        } catch (PDOException $e) {
            echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
        }
        break;

    // ── CREAR REGISTRO EN SECCIONES ELECTORALES ──
    case 'create':
        $campos = []; $vals = [];
        foreach ($CAMPOS_PERMITIDOS as $c) {
            if (isset($_POST[$c])) { 
                $campos[] = $c; 
                $vals[] = $_POST[$c]; 
            }
        }
        if (empty($campos)) { 
            echo json_encode(['ok' => false, 'msg' => 'Sin datos']); 
            break; 
        }

        $cols = implode(',', $campos);
        $marks = implode(',', array_fill(0, count($campos), '?'));
        $stmt = $pdo->prepare("INSERT INTO secciones_electorales ($cols, activo, fecha_creacion) VALUES ($marks, 1, NOW())");
        $stmt->execute($vals);
        
        echo json_encode(['ok' => true, 'seccion' => fetchZonaSeccion($pdo, $pdo->lastInsertId())]);
        break;

    // ── ACTUALIZAR REGISTRO ──
    case 'update':
        $id = intval($_POST['id'] ?? 0);
        if (!$id) { 
            echo json_encode(['ok' => false, 'msg' => 'ID requerido']); 
            break; 
        }
        
        $sets = []; $vals = [];
        foreach ($CAMPOS_PERMITIDOS as $c) {
            if (isset($_POST[$c])) { 
                $sets[] = "$c = ?"; 
                $vals[] = $_POST[$c]; 
            }
        }
        if (empty($sets)) { 
            echo json_encode(['ok' => false, 'msg' => 'Sin datos']); 
            break; 
        }
        
        $vals[] = $id;
        $stmt = $pdo->prepare("UPDATE secciones_electorales SET " . implode(',', $sets) . " WHERE id = ? AND activo = 1");
        $stmt->execute($vals);
        
        echo json_encode(['ok' => true, 'seccion' => fetchZonaSeccion($pdo, $id)]);
        break;

    // ── ACTUALIZACIÓN DE UN SOLO CAMPO (INLINE) - UPSERT ──
    case 'update_field':
        $id    = intval($_POST['id'] ?? 0); 
        $field = $_POST['field'] ?? '';
        $value = $_POST['value'] ?? '';
        
        if (!$id || !$field) { 
            echo json_encode(['ok' => false, 'msg' => 'Parámetros incompletos']); 
            break; 
        }
        if (!in_array($field, $CAMPOS_PERMITIDOS)) { 
            echo json_encode(['ok' => false, 'msg' => 'Campo no permitido']); 
            break; 
        }

        try {
            $stGet = $pdo->prepare("SELECT seccion, estado_nombre FROM secciones WHERE id = ?");
            $stGet->execute([$id]);
            $row = $stGet->fetch();

            if (!$row) {
                echo json_encode(['ok' => false, 'msg' => 'Sección no encontrada']); 
                break; 
            }

            $sql = "INSERT INTO secciones_electorales (nombre_seccion, estado, $field, activo) 
                    VALUES (?, ?, ?, 1) 
                    ON DUPLICATE KEY UPDATE $field = VALUES($field)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$row['seccion'], $row['estado_nombre'], $value]);

            echo json_encode(['ok' => true]);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'msg' => 'Error al actualizar: ' . $e->getMessage()]);
        }
        break;

    // ── BORRADO LÓGICO ──
    case 'delete':
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("UPDATE secciones_electorales SET activo = 0 WHERE id = ?")->execute([$id]);
            echo json_encode(['ok' => true]);
        } else {
            echo json_encode(['ok' => false, 'msg' => 'ID inválido']);
        }
        break;

    // ── OBTENER ZONAS DE UNA REGIÓN ──────────────────────────────────────
    // GET ?action=get_zonas&region=NombreRegion&estado=Sonora
    // Retorna: { ok: true, zonas: ["Zona Norte", "Zona Sur", ...] }
    case 'get_zonas':
        $region = trim($_GET['region'] ?? '');
        $estado = trim($_GET['estado'] ?? '');

        if ($region === '' || $estado === '') {
            echo json_encode(['ok' => false, 'msg' => 'Parámetros region y estado requeridos']);
            break;
        }

        try {
            $stmt = $pdo->prepare("
                SELECT z.nombre
                FROM zonas_estatales z
                INNER JOIN regiones_estatales r ON z.estructura_id = r.id
                WHERE UPPER(TRIM(r.nombre)) = UPPER(TRIM(?))
                  AND UPPER(TRIM(r.estado)) = UPPER(TRIM(?))
                ORDER BY z.nombre
            ");
            $stmt->execute([$region, $estado]);
            $zonas = $stmt->fetchAll(PDO::FETCH_COLUMN);

            echo json_encode(['ok' => true, 'zonas' => $zonas], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            error_log('secciones_api get_zonas: ' . $e->getMessage());
            echo json_encode(['ok' => false, 'msg' => 'Error de BD: ' . $e->getMessage()]);
        }
        break;

    // ── OBTENER SECCIONES DE UNA ZONA ────────────────────────────────────
    // GET ?action=get_secciones_zona&zona=NombreZona&region=NombreRegion&estado=Sonora
    // Retorna: { ok: true, secciones: ["101", "102", ...] }
    case 'get_secciones_zona':
        $zona   = trim($_GET['zona']   ?? '');
        $region = trim($_GET['region'] ?? '');
        $estado = trim($_GET['estado'] ?? '');

        if ($zona === '' || $estado === '') {
            echo json_encode(['ok' => false, 'msg' => 'Parámetros zona y estado requeridos']);
            break;
        }

        try {
            $sql = "
                SELECT z.secciones
                FROM zonas_estatales z
                INNER JOIN regiones_estatales r ON z.estructura_id = r.id
                WHERE UPPER(TRIM(z.nombre)) = UPPER(TRIM(?))
                  AND UPPER(TRIM(r.estado)) = UPPER(TRIM(?))
            ";
            $params = [$zona, $estado];

            // region es opcional pero ayuda a desambiguar si hubiera zonas con mismo nombre en distintas regiones
            if ($region !== '') {
                $sql .= " AND UPPER(TRIM(r.nombre)) = UPPER(TRIM(?))";
                $params[] = $region;
            }

            $sql .= " LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch();

            if (!$row || empty($row['secciones'])) {
                echo json_encode(['ok' => false, 'msg' => 'Zona no encontrada o sin secciones asignadas', 'secciones' => []]);
                break;
            }

            // secciones puede ser un JSON array ["101","102",...] o una cadena CSV "101,102,..."
            $raw = $row['secciones'];
            $secciones = is_string($raw) ? json_decode($raw, true) : $raw;

            // Fallback: si no es JSON válido, intentar como CSV
            if (!is_array($secciones)) {
                $secciones = array_filter(array_map('trim', explode(',', $raw)));
            }

            // Normalizar a strings y ordenar numéricamente
            $secciones = array_values(array_unique(array_map('strval', $secciones)));
            usort($secciones, fn($a, $b) => intval($a) - intval($b));

            echo json_encode(['ok' => true, 'secciones' => $secciones], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            error_log('secciones_api get_secciones_zona: ' . $e->getMessage());
            echo json_encode(['ok' => false, 'msg' => 'Error de BD: ' . $e->getMessage()]);
        }
        break;

    // ── LISTAR SECCIONES CON PAGINACIÓN SERVER-SIDE ──
    case 'list':
        $estado    = trim($_GET['estado']    ?? '');
        $municipio = trim($_GET['municipio'] ?? '');
        $buscar    = trim($_GET['buscar']    ?? '');
        $page      = max(1, intval($_GET['page']     ?? 1));
        $perPage   = max(1, min(200, intval($_GET['per_page'] ?? 50)));
        $sortCol   = $_GET['sort'] ?? 'nombre_seccion';
        $sortDir   = strtoupper($_GET['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';

        $sortAllowed = [
            'nombre_seccion'        => 'CAST(sec.seccion AS UNSIGNED)',
            'lista_nominal'         => 'COALESCE(se.lista_nominal, 0)',
            'meta_contactos'        => 'COALESCE(se.meta_contactos, 0)',
            'contactos_conseguidos' => 'COALESCE(se.contactos_conseguidos, 0)',
            'barra_avance'          => 'CASE WHEN COALESCE(se.meta_contactos,0)=0 THEN 0
                                            ELSE COALESCE(se.contactos_conseguidos,0)*100/COALESCE(se.meta_contactos,1)
                                       END',
            'eventos_realizados'    => 'COALESCE(ev_cnt.cnt, 0)',
            'metas_votos'           => 'COALESCE(se.metas_votos, 0)',
        ];
        $orderExpr = $sortAllowed[$sortCol] ?? 'CAST(sec.seccion AS UNSIGNED)';

        if ($estado === '') {
            echo json_encode(['ok' => false, 'msg' => 'Estado requerido']);
            break;
        }

        try {
            $where  = 'WHERE sec.estado_nombre = ?';
            $params = [$estado];

            if ($municipio !== '') {
                $where .= ' AND m.nombre = ?';
                $params[] = $municipio;
            }
            if ($buscar !== '') {
                $where .= ' AND sec.seccion LIKE ?';
                $params[] = '%' . $buscar . '%';
            }

            $baseJoin = "
                FROM secciones sec
                LEFT JOIN municipios m
                       ON m.id = sec.municipio_id
                LEFT JOIN secciones_electorales se
                       ON se.nombre_seccion = sec.seccion
                      AND UPPER(TRIM(se.estado)) = UPPER(TRIM(sec.estado_nombre))
                      AND se.activo = 1
                LEFT JOIN (
                    SELECT ae.seccion             AS sec_num,
                           UPPER(TRIM(ae.estado)) AS est_norm,
                           COUNT(*)               AS cnt
                    FROM agenda_eventos ae
                    WHERE ae.activo = 1
                    GROUP BY ae.seccion, UPPER(TRIM(ae.estado))
                ) ev_cnt
                       ON ev_cnt.sec_num  = sec.seccion
                      AND ev_cnt.est_norm = UPPER(TRIM(sec.estado_nombre))
                $where
            ";

            $stCount = $pdo->prepare("
                SELECT
                    COUNT(*)                                    AS total,
                    SUM(COALESCE(se.contactos_conseguidos, 0))  AS stat_contactos,
                    SUM(COALESCE(se.activaciones_totales,  0))  AS stat_activaciones
                $baseJoin
            ");
            $stCount->execute($params);
            $counts = $stCount->fetch();

            $total            = (int)$counts['total'];
            $statContactos    = (int)$counts['stat_contactos'];
            $statActivaciones = (int)$counts['stat_activaciones'];

            $limitInt  = (int)$perPage;
            $offsetInt = (int)(($page - 1) * $perPage);

            $stRows = $pdo->prepare("
                SELECT
                    sec.id,
                    se.id                                    AS se_id,
                    sec.seccion                              AS nombre_seccion,
                    sec.estado_nombre                        AS estado,
                    m.nombre                                 AS municipio,
                    sec.distrito_federal,
                    sec.distrito_local,
                    COALESCE(se.lista_nominal,         0)   AS lista_nominal,
                    COALESCE(se.padron_electoral,      0)   AS padron_electoral,
                    COALESCE(se.lista_hombres,         0)   AS lista_hombres,
                    COALESCE(se.lista_mujeres,         0)   AS lista_mujeres,
                    COALESCE(se.meta_contactos,        0)   AS meta_contactos,
                    COALESCE(se.contactos_conseguidos, 0)   AS contactos_conseguidos,
                    COALESCE(ev_cnt.cnt,               0)   AS eventos_realizados,
                    COALESCE(se.metas_votos,           0)   AS metas_votos,
                    COALESCE(se.region_asignada,      '')   AS region_asignada,
                    COALESCE(se.zona_asignada,        '')   AS zona_asignada,
                    COALESCE(se.principales_necesidades,'') AS principales_necesidades,
                    COALESCE(se.enfoque,              '')   AS enfoque,
                    COALESCE(se.perfil_socioeconomico,'')   AS perfil_socioeconomico,
                    COALESCE(se.activaciones_totales,  0)   AS activaciones_totales,
                    COALESCE(se.con_candidato,         0)   AS con_candidato,
                    COALESCE(se.sin_candidato,         0)   AS sin_candidato
                $baseJoin
                ORDER BY $orderExpr $sortDir
                LIMIT $limitInt OFFSET $offsetInt
            ");
            $stRows->execute($params);
            $rows = $stRows->fetchAll();

            echo json_encode([
                'ok'               => true,
                'rows'             => $rows,
                'total'            => $total,
                'page'             => $page,
                'per_page'         => $perPage,
                'stat_contactos'   => $statContactos,
                'stat_activaciones'=> $statActivaciones,
            ], JSON_UNESCAPED_UNICODE);

        } catch (PDOException $e) {
            error_log('secciones_api list: ' . $e->getMessage());
            echo json_encode(['ok' => false, 'msg' => 'Error de BD: ' . $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['ok' => false, 'msg' => 'Acción desconocida']);
}