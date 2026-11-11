<?php
// api/metas/procesar.php
// Backend único para el módulo de Metas (Estatal → metas por Región,
// Regional → metas por Zona) y para que los Dashboards lean el
// acumulado ya resuelto según Año/Mes/Semana/Vista.

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    if (file_exists(__DIR__ . '/../../includes/bootstrap.php')) {
        require_once __DIR__ . '/../../includes/bootstrap.php';
    } else {
        if (session_status() === PHP_SESSION_NONE) session_start();
    }

    $rol     = $_SESSION['user_role']   ?? '';
    $user_id = $_SESSION['user_id']     ?? null;
    $user_estado = $_SESSION['user_estado'] ?? '';
    $user_region = $_SESSION['user_region'] ?? ''; // id de regiones_estatales (rol regional)

    $permitidos = ['admin','administrador','superadmin','super admin','coordinador_estatal','coordinador_regional','regional','coordinador_zona'];
    if (!in_array($rol, $permitidos)) {
        http_response_code(403);
        echo json_encode(['ok'=>false,'msg'=>'Sin permisos']);
        exit();
    }

    if (!isset($pdo)) {
        $dbConfigPath = __DIR__ . '/../../includes/database.php';
        $dbConfig = file_exists($dbConfigPath) ? require $dbConfigPath : [
            'host'=>'localhost','database'=>'u881824383_pverde',
            'username'=>'u881824383_proyverde','password'=>'PProyectoverde8#',
            'charset'=>'utf8mb4',
            'options'=>[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
        ];
        $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
        $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options'] ?? []);
    }

    $esEstatal  = in_array($rol, ['coordinador_estatal','admin','administrador','superadmin','super admin']);
    $esRegional = in_array($rol, ['coordinador_regional','regional']);
    $esZona     = ($rol === 'coordinador_zona');

    // Identidad de la propia Zona (id + nombre + región a la que pertenece),
    // resuelta en servidor — nunca se confía en el parámetro `entidad` de la
    // URL para este rol, así una Zona no puede ver la meta de otra cambiando
    // el query string.
    $zona_propia_nombre = '';
    $zona_region_id     = 0;
    if ($esZona) {
        $user_zona_nombre = $_SESSION['user_zona_nombre'] ?? $_SESSION['user_zona'] ?? '';
        if ($user_zona_nombre !== '') {
            $st = $pdo->prepare("SELECT id, nombre, estructura_id FROM zonas_estatales WHERE TRIM(nombre) = TRIM(?) LIMIT 1");
            $st->execute([$user_zona_nombre]);
            $z = $st->fetch();
            if ($z) { $zona_propia_nombre = $z['nombre']; $zona_region_id = (int)$z['estructura_id']; }
        }
        if (!$zona_propia_nombre) {
            $zona_id_s = (int)($_SESSION['user_zona_id'] ?? 0);
            if ($zona_id_s > 0) {
                $st = $pdo->prepare("SELECT id, nombre, estructura_id FROM zonas_estatales WHERE id = ? LIMIT 1");
                $st->execute([$zona_id_s]);
                $z = $st->fetch();
                if ($z) { $zona_propia_nombre = $z['nombre']; $zona_region_id = (int)$z['estructura_id']; }
            }
        }
        if (!$zona_propia_nombre) {
            // Último fallback: tabla coordinador_zona (cz.id = user_id) -> zona
            $st = $pdo->prepare("SELECT z.id, z.nombre, z.estructura_id FROM coordinador_zona cz
                                  INNER JOIN zonas_estatales z ON cz.zona = z.id WHERE cz.id = ? LIMIT 1");
            $st->execute([$user_id]);
            $z = $st->fetch();
            if ($z) { $zona_propia_nombre = $z['nombre']; $zona_region_id = (int)$z['estructura_id']; }
        }
    }

    // Región propia del coordinador regional (id + nombre), para acotar su scope.
    $region_propia_id = 0;
    $region_propia_nombre = '';
    if ($esRegional) {
        if (!empty($user_region)) {
            $st = $pdo->prepare("SELECT id, nombre, estado FROM regiones_estatales WHERE id = ? LIMIT 1");
            $st->execute([$user_region]);
            $r = $st->fetch();
            if ($r) {
                $region_propia_id     = (int)$r['id'];
                $region_propia_nombre = $r['nombre'];
                if (empty($user_estado)) $user_estado = $r['estado'];
            }
        }
        if (!$region_propia_id) {
            // Fallback: buscar región donde coordinador_id = user_id
            $st = $pdo->prepare("SELECT id, nombre, estado FROM regiones_estatales WHERE coordinador_id = ? LIMIT 1");
            $st->execute([$user_id]);
            $r = $st->fetch();
            if ($r) {
                $region_propia_id     = (int)$r['id'];
                $region_propia_nombre = $r['nombre'];
                if (empty($user_estado)) $user_estado = $r['estado'];
            }
        }
    }

    // Devuelve el número de semana ISO-8601 (mismo criterio que usa el JS
    // getWeekNumber() de los dashboards) para una fecha dada.
    function semanaIso(string $fecha): int {
        return (int)date('W', strtotime($fecha));
    }

    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    // ── LISTAR ENTIDADES A LAS QUE SE LES PUEDE PONER META ────────
    // (para pintar las filas de la tabla del módulo Metas)
    if ($action === 'entidades') {
        if ($esRegional) {
            if (!$region_propia_id) { echo json_encode(['ok'=>true,'nivel'=>'zona','entidades'=>[]]); exit(); }
            $st = $pdo->prepare("SELECT id, nombre, clave FROM zonas_estatales WHERE estructura_id = ? ORDER BY nombre");
            $st->execute([$region_propia_id]);
            $rows = $st->fetchAll();
            echo json_encode(['ok'=>true,'nivel'=>'zona','region_id'=>$region_propia_id,'region_nombre'=>$region_propia_nombre,'entidades'=>$rows]);
            exit();
        } else {
            if (empty($user_estado)) { echo json_encode(['ok'=>true,'nivel'=>'region','entidades'=>[]]); exit(); }
            $st = $pdo->prepare("SELECT id, nombre FROM regiones_estatales WHERE estado = ? ORDER BY nombre");
            $st->execute([$user_estado]);
            $rows = $st->fetchAll();
            echo json_encode(['ok'=>true,'nivel'=>'region','estado'=>$user_estado,'entidades'=>$rows]);
            exit();
        }
    }

    // ── LISTAR METAS DE UN PERIODO (para editar en el módulo Metas) ──
    if ($action === 'listar') {
        $anio = intval($_GET['anio'] ?? 0);
        $mes  = intval($_GET['mes']  ?? 0);
        if (!$anio || !$mes) { echo json_encode(['ok'=>false,'msg'=>'Año y mes requeridos']); exit(); }

        if ($esRegional) {
            if (!$region_propia_id) { echo json_encode(['ok'=>true,'metas'=>[]]); exit(); }
            $st = $pdo->prepare("SELECT entidad_id, semana, eje, meta FROM metas_semanales WHERE nivel='zona' AND region_id=? AND anio=? AND mes=?");
            $st->execute([$region_propia_id, $anio, $mes]);
        } else {
            if (empty($user_estado)) { echo json_encode(['ok'=>true,'metas'=>[]]); exit(); }
            $st = $pdo->prepare("SELECT entidad_id, semana, eje, meta FROM metas_semanales WHERE nivel='region' AND estado=? AND anio=? AND mes=?");
            $st->execute([$user_estado, $anio, $mes]);
        }
        echo json_encode(['ok'=>true,'metas'=>$st->fetchAll()]);
        exit();
    }

    // ── GUARDAR METAS (batch) ──────────────────────────────────────
    if ($action === 'guardar') {
        $body = json_decode(file_get_contents('php://input'), true);
        if (!is_array($body)) $body = $_POST;

        $anio = intval($body['anio'] ?? 0);
        $mes  = intval($body['mes']  ?? 0);
        $filas = $body['filas'] ?? []; // [{entidad_id, entidad_nombre, semana, voto, comunicacion, organizacion}, ...]

        if (!$anio || !$mes || !is_array($filas) || empty($filas)) {
            echo json_encode(['ok'=>false,'msg'=>'Datos incompletos']); exit();
        }

        $nivel = $esRegional ? 'zona' : 'region';

        // Validar scope de cada entidad antes de guardar
        $idsValidos = [];
        if ($esRegional) {
            if (!$region_propia_id) { echo json_encode(['ok'=>false,'msg'=>'No tienes una región asignada']); exit(); }
            $st = $pdo->prepare("SELECT id FROM zonas_estatales WHERE estructura_id = ?");
            $st->execute([$region_propia_id]);
            $idsValidos = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
        } else {
            if (empty($user_estado)) { echo json_encode(['ok'=>false,'msg'=>'No tienes un estado asignado']); exit(); }
            $st = $pdo->prepare("SELECT id FROM regiones_estatales WHERE estado = ?");
            $st->execute([$user_estado]);
            $idsValidos = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
        }

        $stmt = $pdo->prepare("
            INSERT INTO metas_semanales
                (nivel, estado, entidad_id, entidad_nombre, region_id, anio, mes, semana, eje, meta, creado_por_id, creado_por_rol)
            VALUES
                (:nivel, :estado, :entidad_id, :entidad_nombre, :region_id, :anio, :mes, :semana, :eje, :meta, :uid, :urol)
            ON DUPLICATE KEY UPDATE
                meta = VALUES(meta),
                entidad_nombre = VALUES(entidad_nombre),
                region_id = VALUES(region_id),
                creado_por_id = VALUES(creado_por_id),
                creado_por_rol = VALUES(creado_por_rol)
        ");

        $pdo->beginTransaction();
        $guardadas = 0;
        foreach ($filas as $fila) {
            $entidad_id = intval($fila['entidad_id'] ?? 0);
            if (!$entidad_id || !in_array($entidad_id, $idsValidos, true)) continue;
            $entidad_nombre = trim($fila['entidad_nombre'] ?? '');
            $semana = intval($fila['semana'] ?? 0);
            if (!$semana) continue;
            $region_id_fila = $esRegional ? $region_propia_id : null;
            $estado_fila = $user_estado ?: '';

            foreach (['voto'=>'voto','comunicacion'=>'comunicacion','organizacion'=>'organizacion'] as $key=>$eje) {
                if (!isset($fila[$key])) continue;
                $meta = max(0, intval($fila[$key]));
                $stmt->execute([
                    ':nivel'=>$nivel, ':estado'=>$estado_fila, ':entidad_id'=>$entidad_id,
                    ':entidad_nombre'=>$entidad_nombre, ':region_id'=>$region_id_fila,
                    ':anio'=>$anio, ':mes'=>$mes, ':semana'=>$semana, ':eje'=>$eje, ':meta'=>$meta,
                    ':uid'=>$user_id, ':urol'=>$rol,
                ]);
                $guardadas++;
            }
        }
        $pdo->commit();
        echo json_encode(['ok'=>true,'guardadas'=>$guardadas]);
        exit();
    }

    // ── RESUMEN PARA EL DASHBOARD ───────────────────────────────────
    // Devuelve la meta ya acumulada según: vista (nivel a consultar),
    // alcance (una entidad puntual o todas las del rol) y periodo
    // (año + mes ["todas" = año completo] + semana ["todas" = mes completo]).
    if ($action === 'resumen') {
        $nivel   = $_GET['nivel']   ?? 'region';       // 'region' | 'zona'
        $scope   = $_GET['scope']   ?? 'propio';        // 'propio' = todas las entidades del usuario; 'una' = una entidad puntual
        $entidad = $_GET['entidad'] ?? '';               // nombre de la entidad si scope=una
        $anio    = intval($_GET['anio'] ?? date('Y'));
        $mesRaw  = $_GET['mes']    ?? 'todas';
        $semRaw  = $_GET['semana'] ?? 'todas';

        $where  = ["anio = :anio", "nivel = :nivel"];
        $params = [':anio'=>$anio, ':nivel'=>$nivel];

        if ($mesRaw !== 'todas' && $mesRaw !== '') {
            $where[] = "mes = :mes";
            $params[':mes'] = intval($mesRaw);
            if ($semRaw !== 'todas' && $semRaw !== '') {
                $where[] = "semana = :semana";
                $params[':semana'] = intval($semRaw);
            }
        }

        if ($nivel === 'region') {
            if (empty($user_estado)) { echo json_encode(['ok'=>true,'voto'=>0,'comunicacion'=>0,'organizacion'=>0]); exit(); }
            $where[] = "estado = :estado";
            $params[':estado'] = $user_estado;
            if ($scope === 'una' && $entidad !== '' && $entidad !== 'todas') {
                $where[] = "entidad_nombre = :entidad_nombre";
                $params[':entidad_nombre'] = $entidad;
            }
        } else { // nivel === 'zona'
            if ($esZona) {
                // La propia Zona consultando su dashboard: siempre su propio
                // nombre/región, resuelto en servidor — ignora `entidad` de la URL.
                if (!$zona_propia_nombre) { echo json_encode(['ok'=>true,'voto'=>0,'comunicacion'=>0,'organizacion'=>0]); exit(); }
                $where[] = "entidad_nombre = :entidad_nombre";
                $params[':entidad_nombre'] = $zona_propia_nombre;
                if ($zona_region_id) {
                    $where[] = "region_id = :region_id";
                    $params[':region_id'] = $zona_region_id;
                }
            } else {
                // Estatal viendo una zona puntual: no tiene región propia, filtra solo por estado.
                // Regional viendo su resumen o una de sus zonas: filtra por region_id propio.
                if ($esRegional && $region_propia_id) {
                    $where[] = "region_id = :region_id";
                    $params[':region_id'] = $region_propia_id;
                } elseif (!empty($user_estado)) {
                    $where[] = "estado = :estado";
                    $params[':estado'] = $user_estado;
                }
                if ($scope === 'una' && $entidad !== '' && $entidad !== 'todas') {
                    $where[] = "entidad_nombre = :entidad_nombre";
                    $params[':entidad_nombre'] = $entidad;
                }
            }
        }

        $sql = "SELECT eje, COALESCE(SUM(meta),0) AS total FROM metas_semanales WHERE " . implode(' AND ', $where) . " GROUP BY eje";
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $out = ['voto'=>0,'comunicacion'=>0,'organizacion'=>0];
        foreach ($st->fetchAll() as $r) {
            if (isset($out[$r['eje']])) $out[$r['eje']] = (int)$r['total'];
        }
        echo json_encode(['ok'=>true] + $out);
        exit();
    }

    echo json_encode(['ok'=>false,'msg'=>'Acción no reconocida']);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok'=>false,'msg'=>'Error en el servidor: '.$e->getMessage()]);
    exit();
}