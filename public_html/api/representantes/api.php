<?php
/**
 * api/representantes/api.php
 * Backend único del módulo «Representantes».
 *   GET  ?action=...           consultas
 *   POST ?action=... (JSON)    escrituras
 *   POST multipart             cargas de archivos (documentos, acuses)
 * Todo filtra por el ámbito territorial del usuario en sesión (RN-07).
 */
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');

require_once __DIR__ . '/../../includes/bootstrap.php';
class_exists('RepModulo'); // carga RepModulo y RepError

function out(array $d, int $http = 200): void
{
    http_response_code($http);
    echo json_encode(['ok' => $http < 400] + $d, JSON_UNESCAPED_UNICODE);
    exit();
}
function fail(string $msg, int $http = 400, array $extra = []): void { out(['msg' => $msg] + $extra, $http); }
function body(): array
{
    static $b = null;
    if ($b === null) {
        $ct = $_SERVER['CONTENT_TYPE'] ?? '';
        $b = stripos($ct, 'application/json') !== false ? (json_decode(file_get_contents('php://input'), true) ?: []) : $_POST;
    }
    return $b;
}
function in_(string $k, $def = null) { $b = body(); return $b[$k] ?? $_GET[$k] ?? $def; }
function nombreCompleto(array $p): string { return trim($p['nombre'] . ' ' . $p['ap_paterno'] . ' ' . ($p['ap_materno'] ?? '')); }

const RX_CURP = '/^[A-Z][AEIOUX][A-Z]{2}\d{2}(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])[HM][A-Z]{2}[B-DF-HJ-NP-TV-Z]{3}[A-Z0-9]\d$/';
const RX_NOM = '/^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ\' .-]{2,60}$/u';
const RX_CASILLA = '/^(B|C\d{1,2}|E\d{1,2}|X\d{1,2})$/';

try {
    if (!isset($pdo)) $pdo = Database::getInstance()->getConnection();
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    $R = new RepModulo($pdo, $_GET['estado'] ?? (body()['estado'] ?? null));
    $P = $R->proc;
    $PID = $P['id'];

    /* ---------------------------------------------------------------
       Helpers que dependen del contexto
       --------------------------------------------------------------- */
    $asignacion = function (int $id) use ($pdo, $R, $PID): array {
        $st = $pdo->prepare("SELECT a.*, p.nombre, p.ap_paterno, p.ap_materno, p.telefono, p.email, p.seccion_domicilio, p.curp_mask, p.curp_cifrado
                             FROM rep_asignaciones a JOIN rep_personas p ON p.id = a.persona_id WHERE a.id = ? AND a.proceso_id = ?");
        $st->execute([$id, $PID]);
        $a = $st->fetch(PDO::FETCH_ASSOC);
        if (!$a) throw new RepError('Registro no encontrado.', 404);
        $ok = $a['tipo_rep'] === 'CONSEJERO'
            ? ($R->nivel <= 3 || ($R->nivel === 4 && $R->regionEnAmbito((int)$a['region_id'])))
            : ($R->nivel <= 3 || ($a['zona_id'] && $R->zonaEnAmbito((int)$a['zona_id'])));
        if (!$ok) throw new RepError('El registro está fuera de tu ámbito.', 403);
        return $a;
    };
    $puedeCapturar = function (array $a) use ($R): bool {
        if ($a['tipo_rep'] === 'CONSEJERO') return $R->can('capturar_con') && $R->regionEnAmbito((int)$a['region_id']);
        return $R->can('capturar') && $a['zona_id'] && $R->zonaEnAmbito((int)$a['zona_id']);
    };
    $historial = function (int $asigId, ?string $de, string $a, string $obs = '') use ($pdo, $R): void {
        $pdo->prepare("INSERT INTO rep_estatus_historial (asignacion_id, estatus_anterior, estatus_nuevo, usuario_id, usuario_nombre, observacion) VALUES (?,?,?,?,?,?)")
            ->execute([$asigId, $de, $a, $R->userId, $R->userName, mb_substr($obs, 0, 255)]);
    };
    $docsDe = function (string $tipo) use ($P): array { return $tipo === 'CONSEJERO' ? $P['docs_con'] : $P['docs_rep']; };
    $uploadDir = function (string $sub) : string {
        $base = PRIVATE_PATH . '/uploads/representantes';
        if (!is_dir($base)) {
            @mkdir($base, 0750, true);
            @file_put_contents($base . '/.htaccess', "Require all denied\nDeny from all\n");
            @file_put_contents($base . '/index.html', '');
        }
        $d = $base . '/' . $sub;
        if (!is_dir($d) && !@mkdir($d, 0750, true)) throw new RepError('No se pudo crear la carpeta de archivos en app_private/uploads.', 500);
        return $d;
    };
    $guardarArchivo = function (array $f, string $sub) use ($uploadDir): array {
        if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RepError('No se recibió el archivo.');
        if ($f['size'] > 10 * 1024 * 1024) throw new RepError('El archivo supera 10 MB.');
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
        $ext = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'][$mime] ?? null;
        if (!$ext) throw new RepError('Formato no permitido. Usa PDF, JPG o PNG.');
        $sha = hash_file('sha256', $f['tmp_name']);
        $dir = $uploadDir($sub);
        $name = $sha . '.' . $ext;
        if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) throw new RepError('No se pudo guardar el archivo.', 500);
        return ['ruta' => $sub . '/' . $name, 'mime' => $mime, 'sha' => $sha, 'size' => (int)$f['size'], 'nombre' => mb_substr(basename($f['name']), 0, 200)];
    };
    $hoyTs = date('Y-m-d H:i:s');

    switch ($action) {

    /* ===============================================================
       CONTEXTO
       =============================================================== */
    case 'contexto':
        $regs = [];
        foreach ($R->regionesAmbito() as $rid) {
            $r = $R->regiones[$rid];
            $regs[] = ['id' => $rid, 'nombre' => $r['nombre'], 'zonas' => array_values(array_map(function ($zid) use ($R) {
                return ['id' => $zid, 'nombre' => $R->zonas[$zid]['nombre'], 'coord' => $R->zonas[$zid]['coord']];
            }, array_values(array_filter($r['zonas'], [$R, 'zonaEnAmbito']))))];
        }
        out([
            'usuario' => $R->userName, 'rol' => $R->rol, 'nivel' => $R->nivel, 'rolLabel' => $R->rolLabel(), 'perms' => $R->perms(),
            'estados' => $R->estados, 'estado' => $R->estado, 'raiz' => $R->raiz(), 'regiones' => $regs,
            'proceso' => ['id' => $PID, 'nombre' => $P['nombre'], 'inicio' => $P['fecha_inicio_ruta'], 'inicioRegistro' => $P['inicio_registro'], 'cierreRegistro' => $P['cierre_registro'],
                'cierreSust' => $P['cierre_sustituciones'], 'jornada' => $P['fecha_jornada'], 'umbralRuta' => $P['umbral_ruta'], 'umbralAtn' => $P['umbral_atencion'], 'metaFuente' => $P['meta_fuente']],
            'hoy' => $R->hoy, 'periodo' => $R->periodo(), 'slots' => $R->slotsRC(), 'docsRep' => $P['docs_rep'], 'docsCon' => $P['docs_con'], 'motivos' => $P['motivos_obs'],
            'estatus' => RepModulo::ESTATUS, 'etapas' => RepModulo::ETAPAS, 'sem' => RepModulo::SEM,
        ]);

    /* ===============================================================
       TABLERO (P-01 a P-04)
       =============================================================== */
    case 'tablero':
        $u = (string)($_GET['u'] ?? '');
        if (!$R->enAmbito($u)) $u = $R->raiz();
        $t = $R->hoy;
        $snap = null;
        $corteSel = (string)($_GET['corte'] ?? '');
        if ($corteSel !== '') {
            $st = $pdo->prepare("SELECT fecha, snapshot FROM rep_cortes WHERE proceso_id = ? AND fecha = ? AND cerrado_en IS NOT NULL");
            $st->execute([$PID, $corteSel]);
            if ($row = $st->fetch(PDO::FETCH_ASSOC)) { $t = $row['fecha']; $snap = json_decode($row['snapshot'] ?? '', true); }
            else $corteSel = '';
        }
        // Módulos por estado (circunscripción consolida varios)
        $mods = [];
        if ($u === 'circ') foreach ($R->estados as $e) $mods[$e] = ($e === $R->estado ? $R : $R->paraEstado($e));
        $M = function (RepModulo $X, string $uu) use ($snap, $R) { return ($snap && $X === $R && isset($snap['u'][$uu])) ? $snap['u'][$uu]['m'] + ['req' => 0] : $X->meta($uu); };
        $Rl = function (RepModulo $X, string $uu, string $tt) use ($snap, $R) { return ($snap && $X === $R && isset($snap['u'][$uu])) ? $snap['u'][$uu]['r'] : $X->real($uu, $tt); };
        $pct = function ($a, $b) { return $b ? round($a / $b * 100, 1) : 0; };

        $kids = [];
        if ($u === 'circ') {
            $m = ['p' => 0, 'con' => 0, 'cas' => 0, 'rc' => 0, 'rg' => 0];
            $r = ['rec' => 0, 'exp' => 0, 'reg' => 0, 'con' => 0];
            foreach ($mods as $e => $X) {
                $mm = $X->meta('estado');
                $rr = $X->real('estado', $t);
                foreach ($m as $k => $_) $m[$k] += $mm[$k];
                foreach ($r as $k => $_) $r[$k] += $rr[$k];
                $kids[] = ['u' => 'estado', 'estado' => $e, 'nombre' => $e, 'coord' => count($X->regiones) . ' regiones', 'meta' => $mm, 'real' => $rr, 'sem' => $X->semUnidad($mm, $rr, $t)];
            }
        } else {
            $m = $M($R, $u);
            $r = $Rl($R, $u, $t);
            foreach ($R->hijos($u) as $k) {
                $mm = $M($R, $k);
                $rr = $Rl($R, $k, $t);
                $kids[] = ['u' => $k, 'nombre' => $R->nombre($k), 'coord' => $R->coord($k), 'meta' => $mm, 'real' => $rr, 'sem' => $R->semUnidad($mm, $rr, $t)];
            }
        }
        foreach ($kids as &$kd) {
            $kd['pct'] = ['rec' => $pct($kd['real']['rec'], $kd['meta']['p']), 'exp' => $pct($kd['real']['exp'], $kd['meta']['p']), 'reg' => $pct($kd['real']['reg'], $kd['meta']['p']), 'con' => $kd['meta']['con'] ? $pct($kd['real']['con'], $kd['meta']['con']) : null];
        }
        unset($kd);

        $etapas = [];
        foreach (RepModulo::ETAPAS as $s) {
            $meta = $s['k'] === 'con' ? (int)$m['con'] : (int)$m['p'];
            if ($s['k'] === 'con' && !$meta && strpos($u, 'z:') === 0) continue;
            $se = $R->semEtapa((int)$r[$s['k']], $meta, $s['k'], $t);
            $etapas[] = $s + ['meta' => $meta, 'real' => (int)$r[$s['k']], 'e' => round($R->esperado($s['k'], $t), 2), 'ex' => $se['ex'], 'sem' => $se['k'], 'cumpl' => $se['c']];
        }
        $semCount = ['ok' => 0, 'warn' => 0, 'bad' => 0, 'idle' => 0];
        foreach ($kids as $kd) $semCount[$kd['sem']]++;

        // Ficha de región o zona
        $ficha = null;
        $cov = $R->cobertura();
        if (strpos($u, 'r:') === 0 || strpos($u, 'z:') === 0) {
            $isR = strpos($u, 'r:') === 0;
            $id = (int)substr($u, 2);
            $rid = $isR ? $id : $R->zonas[$id]['region'];
            $secs = [];
            foreach ($isR ? $R->regiones[$rid]['zonas'] : [$id] as $zz) foreach ($R->zonas[$zz]['secciones'] as $s) $secs[] = $s;
            $info = $R->secInfo();
            $dl = [];
            $df = [];
            $ln = 0;
            foreach ($secs as $s) {
                if (!isset($info[$s])) continue;
                $ln += $info[$s]['ln'];
                if ($info[$s]['dl'] !== '') $dl['DL-' . str_pad($info[$s]['dl'], 2, '0', STR_PAD_LEFT)] = 1;
                if ($info[$s]['df'] !== '') $df['DF-' . str_pad($info[$s]['df'], 2, '0', STR_PAD_LEFT)] = 1;
            }
            ksort($dl);
            ksort($df);
            $covRC = $isR ? array_sum(array_map(function ($z) use ($cov) { return $cov['z'][$z] ?? 0; }, $R->regiones[$rid]['zonas'])) : ($cov['z'][$id] ?? 0);
            $mE = $M($R, 'estado');
            $mR = $M($R, 'r:' . $rid);
            $ficha = ['tipo' => $isR ? 'region' : 'zona', 'nombre' => $R->nombre($u), 'region' => $R->regiones[$rid]['nombre'], 'coord' => $R->coord($u),
                'municipios' => $R->regiones[$rid]['municipios'], 'integracion' => $R->regiones[$rid]['tipo'], 'zonas' => count($R->regiones[$rid]['zonas']),
                'secciones' => count($secs), 'ln' => $ln, 'distritos' => array_merge(array_keys($dl), array_keys($df)),
                'casPctPadre' => $isR ? $pct($m['cas'], $mE['cas']) : $pct($m['cas'], $mR['cas']), 'covRC' => $covRC];
        }

        // Secciones de la zona con cobertura
        $secciones = null;
        if (strpos($u, 'z:') === 0) {
            $zid = (int)substr($u, 2);
            $info = $R->secInfo();
            $tipos = [];
            $secsZ = $R->zonas[$zid]['secciones'];
            if ($secsZ) {
                $ph = implode(',', array_fill(0, count($secsZ), '?'));
                $st = $pdo->prepare("SELECT seccion, tipo FROM rep_casillas WHERE proceso_id = ? AND activa = 1 AND seccion IN ($ph) ORDER BY id");
                $st->execute(array_merge([$PID], $secsZ));
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $c) $tipos[RepModulo::sec($c['seccion'])][] = $c['tipo'];
            }
            $nSlots = count($R->slotsRC());
            $secciones = [];
            foreach ($secsZ as $s) {
                $n = count($tipos[$s] ?? []);
                $secciones[] = ['seccion' => $s, 'ln' => $info[$s]['ln'] ?? 0, 'casillas' => $tipos[$s] ?? [], 'req' => $n * $nSlots, 'cub' => $cov['s'][$s] ?? 0];
            }
        }

        // Alertas
        $alertas = [];
        $nxt = null;
        foreach ($R->cortes as $c) if ($c['fecha'] > $t) { $nxt = $c; break; }
        if ($nxt && $m['p']) {
            $dias = max(1, RepModulo::dn($nxt['fecha']) - RepModulo::dn($t));
            $need = (int)round($m['p'] * $nxt['rec'] / 100) - $r['rec'];
            if ($need > 0) $alertas[] = ['warn', 'Faltan ' . number_format($need) . ' reclutados para el corte del ' . RepModulo::fd($nxt['fecha']), $dias . ' días restantes · ' . number_format((int)ceil($need / $dias)) . ' por día'];
            $needE = (int)round($m['p'] * $nxt['exp'] / 100) - $r['exp'];
            if ($needE > 0) $alertas[] = ['warn', 'Faltan ' . number_format($needE) . ' expedientes completos para ese corte', 'Meta de expedientes del próximo corte'];
        }
        $rez = array_values(array_filter($kids, function ($k) { return $k['sem'] === 'bad'; }));
        if ($rez) $alertas[] = ['bad', count($rez) . ' ' . ($u === 'circ' ? 'estados' : ($u === 'estado' ? 'regiones' : 'zonas')) . ' en rezago', implode(', ', array_slice(array_map(function ($k) { return $k['nombre']; }, $rez), 0, 4)) . (count($rez) > 4 ? '…' : '')];
        if ($u !== 'circ') {
            $w = 'proceso_id = ? AND vigente = 1';
            $pp = [$PID];
            if (strpos($u, 'r:') === 0) { $w .= ' AND region_id = ?'; $pp[] = (int)substr($u, 2); }
            if (strpos($u, 'z:') === 0) { $w .= ' AND zona_id = ?'; $pp[] = (int)substr($u, 2); }
            $st = $pdo->prepare("SELECT SUM(estatus='OBSERVADO') o, SUM(estatus='EN_REVISION') v, SUM(posicion_key IS NULL AND tipo_rep <> 'CONSEJERO') sp FROM rep_asignaciones WHERE $w");
            $st->execute($pp);
            $q = $st->fetch(PDO::FETCH_ASSOC);
            if ((int)$q['o']) $alertas[] = ['bad', number_format((int)$q['o']) . ' expedientes observados por corregir', 'La zona recibe el motivo de cada observación'];
            if ((int)$q['v']) $alertas[] = ['idle', number_format((int)$q['v']) . ' expedientes en revisión', 'Pendientes en la cola de validación'];
            if ((int)$q['sp']) $alertas[] = ['idle', number_format((int)$q['sp']) . ' personas sin posición asignada', 'Cuentan en reclutamiento pero no cubren casilla (RN-05)'];
        }
        if (!$m['cas'] && $u !== 'circ') $alertas[] = ['warn', 'No hay casillas en el catálogo para esta unidad', 'Cárgalas en Configuración › Catálogo de casillas'];
        if ($t < $P['inicio_registro']) $alertas[] = ['idle', 'El registro con acuse abre el ' . RepModulo::fd($P['inicio_registro']), 'Faltan ' . (RepModulo::dn($P['inicio_registro']) - RepModulo::dn($t)) . ' días'];

        // Curva real: puntos semanales
        $curva = [];
        $tope = min($t, $R->hoy);
        $ini = $P['fecha_inicio_ruta'];
        if ($tope > $ini) {
            $fechas = [];
            for ($d = RepModulo::dn($ini) + 7; $d < RepModulo::dn($tope); $d += 7) $fechas[] = $d;
            $fechas[] = RepModulo::dn($tope);
            $acc = array_fill(0, count($fechas), ['rec' => 0, 'exp' => 0, 'reg' => 0, 'con' => 0]);
            foreach (($u === 'circ' ? array_values($mods) : [$R]) as $X) {
                $w = 'proceso_id = ?';
                $pp = [$X->proc['id']];
                if (strpos($u, 'r:') === 0) { $w .= ' AND region_id = ?'; $pp[] = (int)substr($u, 2); }
                if (strpos($u, 'z:') === 0) { $w .= ' AND zona_id = ?'; $pp[] = (int)substr($u, 2); }
                $st = $pdo->prepare("SELECT tipo_rep, f_reclutado, f_completo, f_registrado, baja_en FROM rep_asignaciones WHERE $w");
                $st->execute($pp);
                foreach ($st->fetchAll(PDO::FETCH_NUM) as $a) {
                    $fr = RepModulo::dn(substr($a[1], 0, 10));
                    $fc = $a[2] ? RepModulo::dn(substr($a[2], 0, 10)) : PHP_INT_MAX;
                    $fg = $a[3] ? RepModulo::dn(substr($a[3], 0, 10)) : PHP_INT_MAX;
                    $fb = $a[4] ? RepModulo::dn(substr($a[4], 0, 10)) : PHP_INT_MAX;
                    foreach ($fechas as $i => $d) {
                        if ($fb <= $d || $fr > $d) continue;
                        if ($a[0] === 'CONSEJERO') { $acc[$i]['con']++; continue; }
                        $acc[$i]['rec']++;
                        if ($fc <= $d) $acc[$i]['exp']++;
                        if ($fg <= $d) $acc[$i]['reg']++;
                    }
                }
            }
            foreach ($fechas as $i => $d) {
                $curva[] = ['f' => gmdate('Y-m-d', $d * 86400), 'rec' => $pct($acc[$i]['rec'], $m['p']), 'exp' => $pct($acc[$i]['exp'], $m['p']), 'reg' => $pct($acc[$i]['reg'], $m['p']), 'con' => $m['con'] ? $pct($acc[$i]['con'], $m['con']) : null];
            }
        }

        $tabs = null;
        if (strpos($u, 'r:') === 0 && in_array($R->raiz(), ['estado', 'circ'], true)) {
            $tabs = [];
            foreach (array_keys($R->regiones) as $rid) $tabs[] = ['u' => 'r:' . $rid, 'nombre' => $R->regiones[$rid]['nombre'], 'sem' => $R->semUnidad($M($R, 'r:' . $rid), $Rl($R, 'r:' . $rid, $t), $t)];
        }
        $crumbs = array_map(function ($c) use ($R) { return ['u' => $c, 'nombre' => $R->nombre($c)]; }, $R->ruta($u));

        out(['u' => $u, 't' => $t, 'corte' => $corteSel, 'nombre' => $R->nombre($u), 'meta' => $m, 'real' => $r, 'sem' => $u === 'circ' ? 'idle' : $R->semUnidad($m, $r, $t),
            'etapas' => $etapas, 'hijos' => $kids, 'semCount' => $semCount, 'ficha' => $ficha, 'secciones' => $secciones, 'alertas' => $alertas, 'curva' => $curva,
            'cortes' => $R->cortes, 'tabs' => $tabs, 'crumbs' => $crumbs, 'metaFuente' => $P['meta_fuente'], 'regiones' => count($R->regiones),
            'municipios' => count(array_unique(array_merge([], ...array_values(array_map(function ($x) { return array_map('strval', $x['municipios']); }, $R->regiones))))),
            'cov' => ['rc' => array_sum($cov['z']), 'rg' => array_sum($cov['rg'])]]);

    /* ===============================================================
       SECCIONES Y CASILLAS (P-05)
       =============================================================== */
    case 'casillas_zona':
        $zid = (int)($_GET['zona'] ?? 0);
        if (!$zid || !$R->zonaEnAmbito($zid)) { $za = $R->zonasAmbito(); $zid = $za[0] ?? 0; }
        if (!$zid) out(['zona' => null, 'secciones' => []]);
        $z = $R->zonas[$zid];
        $info = $R->secInfo();
        $cas = [];
        if ($z['secciones']) {
            $ph = implode(',', array_fill(0, count($z['secciones']), '?'));
            $st = $pdo->prepare("SELECT id, seccion, tipo, ubicacion FROM rep_casillas WHERE proceso_id = ? AND activa = 1 AND seccion IN ($ph)");
            $st->execute(array_merge([$PID], $z['secciones']));
            $cas = $st->fetchAll(PDO::FETCH_ASSOC);
            usort($cas, function ($a, $b) {
                $o = function ($t) { return ['B' => 0, 'C' => 1, 'E' => 2, 'X' => 3][$t[0]] * 100 + (int)substr($t, 1); };
                return $o($a['tipo']) <=> $o($b['tipo']);
            });
        }
        $occ = [];
        $cids = array_map(function ($c) { return (int)$c['id']; }, $cas);
        $w = "a.proceso_id = ? AND a.vigente = 1 AND (a.posicion_key LIKE ?" . ($cids ? " OR a.casilla_id IN (" . implode(',', $cids) . ")" : '') . ")";
        $st = $pdo->prepare("SELECT a.id, a.posicion_key, a.estatus, p.nombre, p.ap_paterno, p.ap_materno FROM rep_asignaciones a JOIN rep_personas p ON p.id = a.persona_id WHERE $w");
        $st->execute([$PID, 'Z' . $zid . ':RG%']);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $o) $occ[$o['posicion_key']] = ['id' => (int)$o['id'], 'nombre' => nombreCompleto($o), 'estatus' => $o['estatus']];
        $porSec = [];
        foreach ($cas as $c) $porSec[RepModulo::sec($c['seccion'])][] = ['id' => (int)$c['id'], 'tipo' => $c['tipo'], 'ubicacion' => $c['ubicacion']];
        $secs = [];
        foreach ($z['secciones'] as $s) $secs[] = ['seccion' => $s, 'ln' => $info[$s]['ln'] ?? 0, 'casillas' => $porSec[$s] ?? []];
        $m = $R->meta('z:' . $zid);
        out(['zona' => ['id' => $zid, 'nombre' => $z['nombre'], 'region' => $R->regiones[$z['region']]['nombre'], 'regionId' => $z['region'], 'coord' => $z['coord'], 'rg' => $R->rgZona($zid), 'meta' => $m],
            'secciones' => $secs, 'ocupadas' => $occ, 'slots' => array_map(function ($s) use ($R) { return ['k' => $s, 'l' => $R->slotLabel($s)]; }, $R->slotsRC()),
            'editable' => $R->can('capturar') && $R->periodo()['k'] !== 'cerrado', 'catalogo' => $R->can('casillas'), 'periodo' => $R->periodo()]);

    case 'candidatos':
        $key = (string)($_GET['pos'] ?? '');
        $pos = $R->posicion($key);
        if (!$pos) fail('Posición inválida.');
        $q = trim((string)($_GET['q'] ?? ''));
        $w = 'a.proceso_id = ? AND a.vigente = 1 AND a.posicion_key IS NULL AND a.tipo_rep = ?';
        $pp = [$PID, $pos['tipo']];
        if ($pos['tipo'] === 'CONSEJERO') { $w .= ' AND a.region_id = ?'; $pp[] = $pos['region_id']; }
        else { $w .= ' AND a.zona_id = ?'; $pp[] = $pos['zona_id']; }
        if ($q !== '') { $w .= " AND CONCAT_WS(' ', p.nombre, p.ap_paterno, p.ap_materno) LIKE ?"; $pp[] = '%' . $q . '%'; }
        $st = $pdo->prepare("SELECT a.id, a.estatus, p.nombre, p.ap_paterno, p.ap_materno, p.curp_mask FROM rep_asignaciones a JOIN rep_personas p ON p.id = a.persona_id WHERE $w ORDER BY p.nombre LIMIT 40");
        $st->execute($pp);
        out(['pos' => $pos, 'candidatos' => array_map(function ($c) { return ['id' => (int)$c['id'], 'nombre' => nombreCompleto($c), 'curp' => $c['curp_mask'], 'estatus' => $c['estatus']]; }, $st->fetchAll(PDO::FETCH_ASSOC))]);

    case 'vacantes':
        $tipo = (string)($_GET['tipo'] ?? 'RC');
        $keys = [];
        if ($tipo === 'CONSEJERO') {
            $rid = (int)($_GET['region'] ?? $R->regionId);
            if (!$R->regionEnAmbito($rid)) fail('Fuera de tu ámbito.', 403);
            for ($n = 1; $n <= $R->meta('r:' . $rid)['con']; $n++) $keys[] = 'R' . $rid . ':CON' . $n;
        } else {
            $zid = (int)($_GET['zona'] ?? 0);
            if (!$R->zonaEnAmbito($zid)) fail('Fuera de tu ámbito.', 403);
            if ($tipo === 'RG') {
                for ($n = 1; $n <= $R->rgZona($zid); $n++) $keys[] = 'Z' . $zid . ':RG' . $n;
            } elseif ($R->zonas[$zid]['secciones']) {
                $ph = implode(',', array_fill(0, count($R->zonas[$zid]['secciones']), '?'));
                $st = $pdo->prepare("SELECT id FROM rep_casillas WHERE proceso_id = ? AND activa = 1 AND seccion IN ($ph) ORDER BY seccion + 0, id");
                $st->execute(array_merge([$PID], $R->zonas[$zid]['secciones']));
                foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $cid) foreach ($R->slotsRC() as $s) $keys[] = 'C' . $cid . ':' . $s;
            }
        }
        $occ = [];
        if ($keys) {
            foreach (array_chunk($keys, 800) as $ch) {
                $st = $pdo->prepare("SELECT pos_vigente FROM rep_asignaciones WHERE proceso_id = ? AND pos_vigente IN (" . implode(',', array_fill(0, count($ch), '?')) . ")");
                $st->execute(array_merge([$PID], $ch));
                foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $k) $occ[$k] = 1;
            }
        }
        $libres = array_values(array_filter($keys, function ($k) use ($occ) { return !isset($occ[$k]); }));
        $lbl = $R->etiquetas(array_slice($libres, 0, 600));
        out(['total' => count($libres), 'vacantes' => array_map(function ($k) use ($lbl) { return ['k' => $k, 'l' => $lbl[$k] ?? $k]; }, array_slice($libres, 0, 600))]);

    case 'asignar':
        $a = $asignacion((int)in_('id'));
        if (!$puedeCapturar($a)) fail('Tu rol no asigna posiciones en este ámbito.', 403);
        if ($R->periodo()['k'] === 'cerrado') fail($R->periodo()['msg']);
        if (!(int)$a['vigente']) fail('La persona está dada de baja.');
        if ($a['posicion_key']) fail('Esa persona ya tiene una posición. Una persona ocupa a lo más una posición (RN-12).');
        $pos = $R->posicion((string)in_('pos'));
        if (!$pos || !$pos['valida'] || !$pos['activa']) fail('La posición no existe o ya no está activa.');
        if ($pos['tipo'] !== $a['tipo_rep']) fail('La posición es de tipo ' . $pos['tipo'] . ' y la persona es ' . $a['tipo_rep'] . '.');
        if ($pos['tipo'] === 'CONSEJERO' ? (int)$pos['region_id'] !== (int)$a['region_id'] : (int)$pos['zona_id'] !== (int)$a['zona_id']) fail('La posición pertenece a otra zona.');
        try {
            $pdo->prepare("UPDATE rep_asignaciones SET posicion_key = ?, casilla_id = ? WHERE id = ? AND posicion_key IS NULL")->execute([$pos['key'], $pos['casilla_id'], $a['id']]);
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) fail('La posición ya está ocupada.');
            throw $e;
        }
        $R->log('Asignación de posición', nombreCompleto($a), $pos['label'] . ($R->periodo()['k'] === 'sust' ? ' · sustitución' : ''), (int)$a['region_id'], $a['zona_id'] ? (int)$a['zona_id'] : null);
        out(['msg' => nombreCompleto($a) . ' asignado a ' . $pos['label'] . '.']);

    case 'liberar':
        $a = $asignacion((int)in_('id'));
        if (!$puedeCapturar($a)) fail('Tu rol no libera posiciones en este ámbito.', 403);
        if ($R->periodo()['k'] === 'cerrado') fail($R->periodo()['msg']);
        if (!$a['posicion_key']) fail('La persona no tiene posición.');
        $lbl = $R->etiquetas([$a['posicion_key']])[$a['posicion_key']] ?? $a['posicion_key'];
        $pdo->prepare("UPDATE rep_asignaciones SET posicion_key = NULL, casilla_id = NULL WHERE id = ?")->execute([$a['id']]);
        $R->log('Posición liberada', nombreCompleto($a), $lbl, (int)$a['region_id'], $a['zona_id'] ? (int)$a['zona_id'] : null);
        out(['msg' => $lbl . ' queda vacante.']);

    case 'casilla_agregar':
        $R->exigir('casillas');
        $s = RepModulo::sec((string)in_('seccion'));
        $tipo = strtoupper(trim((string)in_('tipo')));
        if (!isset($R->secZona[$s]) && !$R->seccionExiste($s)) fail('La sección no existe en el catálogo del estado.');
        if (!preg_match(RX_CASILLA, $tipo)) fail('Tipo de casilla inválido. Usa B, C1, C2…, E1 (especial) o X1 (extraordinaria).');
        try {
            $pdo->prepare("INSERT INTO rep_casillas (proceso_id, seccion, tipo, ubicacion, fuente) VALUES (?,?,?,?, 'manual')")->execute([$PID, $s, $tipo, trim((string)in_('ubicacion', '')) ?: null]);
        } catch (PDOException $e) {
            if ($e->getCode() != 23000) throw $e;
            $st = $pdo->prepare("UPDATE rep_casillas SET activa = 1, baja_en = NULL WHERE proceso_id = ? AND seccion = ? AND tipo = ? AND activa = 0");
            $st->execute([$PID, $s, $tipo]);
            if (!$st->rowCount()) fail('La casilla ' . $tipo . ' ya existe en la sección ' . $s . '.');
        }
        $R->log('Alta de casilla', 'Sección ' . $s, 'Casilla ' . $tipo);
        out(['msg' => 'Casilla ' . $tipo . ' agregada a la sección ' . $s . '.']);

    case 'casilla_baja':
        $R->exigir('casillas');
        $id = (int)in_('id');
        $st = $pdo->prepare("SELECT * FROM rep_casillas WHERE id = ? AND proceso_id = ? AND activa = 1");
        $st->execute([$id, $PID]);
        $c = $st->fetch(PDO::FETCH_ASSOC);
        if (!$c) fail('Casilla no encontrada.');
        $st = $pdo->prepare("SELECT a.posicion_key, p.nombre, p.ap_paterno, p.ap_materno FROM rep_asignaciones a JOIN rep_personas p ON p.id = a.persona_id WHERE a.casilla_id = ? AND a.vigente = 1");
        $st->execute([$id]);
        $occ = $st->fetchAll(PDO::FETCH_ASSOC);
        if ($occ) fail('Alerta de reubicación (RN-18): la casilla tiene posiciones ocupadas por ' . implode(', ', array_map('nombreCompleto', $occ)) . '. Libera o reubica a esas personas antes de darla de baja.');
        $pdo->prepare("UPDATE rep_casillas SET activa = 0, baja_en = NOW() WHERE id = ?")->execute([$id]);
        $R->log('Baja de casilla', 'Sección ' . $c['seccion'], 'Casilla ' . $c['tipo']);
        out(['msg' => 'Casilla dada de baja.']);

    /* ===============================================================
       PADRÓN Y FICHA (P-06, P-07)
       =============================================================== */
    case 'padron':
        $w = ['a.proceso_id = ?'];
        $pp = [$PID];
        if ($R->nivel === 5) { $w[] = "a.zona_id = ? AND a.tipo_rep <> 'CONSEJERO'"; $pp[] = $R->zonaId; }
        elseif ($R->nivel === 4) { $w[] = 'a.region_id = ?'; $pp[] = $R->regionId; }
        if ($v = (int)($_GET['region'] ?? 0)) { $w[] = 'a.region_id = ?'; $pp[] = $v; }
        if ($v = (int)($_GET['zona'] ?? 0)) { $w[] = 'a.zona_id = ?'; $pp[] = $v; }
        if (in_array($v = (string)($_GET['tipo'] ?? ''), ['RC', 'RG', 'CONSEJERO'], true)) { $w[] = 'a.tipo_rep = ?'; $pp[] = $v; }
        if (($_GET['sinpos'] ?? '') === '1') $w[] = "a.posicion_key IS NULL AND a.vigente = 1";
        $q = trim((string)($_GET['q'] ?? ''));
        if ($q !== '') {
            $qu = strtoupper(preg_replace('/\s+/', '', $q));
            if (preg_match(RX_CURP, $qu)) { $w[] = 'p.curp_hash = ?'; $pp[] = RepModulo::curpHash($qu); }
            else { $w[] = "(CONCAT_WS(' ', p.nombre, p.ap_paterno, p.ap_materno) LIKE ? OR a.folio LIKE ? OR p.telefono LIKE ?)"; array_push($pp, '%' . $q . '%', '%' . $q . '%', '%' . $q . '%'); }
        }
        $ws = implode(' AND ', $w);
        $st = $pdo->prepare("SELECT a.estatus, COUNT(*) n FROM rep_asignaciones a JOIN rep_personas p ON p.id = a.persona_id WHERE $ws GROUP BY a.estatus");
        $st->execute($pp);
        $counts = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $c) $counts[$c['estatus']] = (int)$c['n'];
        if (array_key_exists($v = (string)($_GET['estatus'] ?? ''), RepModulo::ESTATUS)) { $ws .= ' AND a.estatus = ?'; $pp[] = $v; }
        $st = $pdo->prepare("SELECT COUNT(*) FROM rep_asignaciones a JOIN rep_personas p ON p.id = a.persona_id WHERE $ws");
        $st->execute($pp);
        $total = (int)$st->fetchColumn();
        $per = 25;
        $page = max(0, (int)($_GET['page'] ?? 0));
        $all = ($_GET['export'] ?? '') === '1' && $R->can('export');
        $lim = $all ? '' : ' LIMIT ' . $per . ' OFFSET ' . ($page * $per);
        $st = $pdo->prepare("SELECT a.id, a.tipo_rep, a.estatus, a.posicion_key, a.region_id, a.zona_id, a.folio, a.actualizado_en, p.nombre, p.ap_paterno, p.ap_materno, p.curp_mask
                             FROM rep_asignaciones a JOIN rep_personas p ON p.id = a.persona_id WHERE $ws ORDER BY a.actualizado_en DESC, a.id DESC" . $lim);
        $st->execute($pp);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        $lbl = $R->etiquetas(array_column($rows, 'posicion_key'));
        out(['total' => $total, 'page' => $page, 'per' => $per, 'counts' => $counts, 'rows' => array_map(function ($a) use ($R, $lbl) {
            return ['id' => (int)$a['id'], 'nombre' => nombreCompleto($a), 'curp' => $a['curp_mask'], 'tipo' => $a['tipo_rep'], 'estatus' => $a['estatus'], 'pos' => $a['posicion_key'] ? ($lbl[$a['posicion_key']] ?? '') : null,
                'region' => $R->regiones[(int)$a['region_id']]['nombre'] ?? '', 'zona' => $a['zona_id'] ? ($R->zonas[(int)$a['zona_id']]['nombre'] ?? '') : null, 'folio' => $a['folio'], 'act' => substr($a['actualizado_en'], 0, 10)];
        }, $rows)]);

    case 'persona':
        $a = $asignacion((int)($_GET['id'] ?? 0));
        $docs = [];
        $st = $pdo->prepare("SELECT id, tipo_documento, nombre_original, estatus, motivo, subido_en, revisado_por FROM rep_documentos WHERE asignacion_id = ?");
        $st->execute([$a['id']]);
        $byTipo = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $d) $byTipo[$d['tipo_documento']] = $d;
        foreach ($docsDe($a['tipo_rep']) as $n) {
            $d = $byTipo[$n] ?? null;
            $docs[] = ['n' => $n, 'id' => $d ? (int)$d['id'] : null, 'e' => $d ? $d['estatus'] : 'pendiente', 'm' => $d['motivo'] ?? null, 'file' => $d['nombre_original'] ?? null];
        }
        $st = $pdo->prepare("SELECT estatus_anterior de, estatus_nuevo a, usuario_nombre u, observacion o, fecha f FROM rep_estatus_historial WHERE asignacion_id = ? ORDER BY id DESC");
        $st->execute([$a['id']]);
        $pos = $a['posicion_key'] ? ($R->etiquetas([$a['posicion_key']])[$a['posicion_key']] ?? null) : null;
        $cap = $puedeCapturar($a);
        out(['p' => ['id' => (int)$a['id'], 'nombre' => nombreCompleto($a), 'curp' => $a['curp_mask'], 'tel' => $a['telefono'], 'email' => $a['email'], 'secDom' => $a['seccion_domicilio'],
            'tipo' => $a['tipo_rep'], 'estatus' => $a['estatus'], 'vigente' => (int)$a['vigente'] === 1, 'pos' => $pos, 'posKey' => $a['posicion_key'], 'folio' => $a['folio'],
            'region' => $R->regiones[(int)$a['region_id']]['nombre'] ?? '', 'zona' => $a['zona_id'] ? ($R->zonas[(int)$a['zona_id']]['nombre'] ?? '') : null,
            'zonaCoord' => $a['zona_id'] ? ($R->zonas[(int)$a['zona_id']]['coord'] ?? '') : null, 'alta' => substr($a['f_reclutado'], 0, 10), 'baja' => $a['baja_en'], 'motivoBaja' => $a['motivo_baja']],
            'docs' => $docs, 'hist' => $st->fetchAll(PDO::FETCH_ASSOC),
            'puede' => ['capturar' => $cap, 'revisar' => $R->can('revisar'), 'registro' => $R->can('registro'), 'verid' => $R->can('verid'), 'periodo' => $R->periodo()]]);

    case 'ver_curp':
        $R->exigir('verid');
        $a = $asignacion((int)in_('id'));
        $R->log('Consulta de identificador completo', nombreCompleto($a), 'Dato personal consultado (RN-17)', (int)$a['region_id'], $a['zona_id'] ? (int)$a['zona_id'] : null);
        out(['curp' => RepModulo::curpDescifrar($a['curp_cifrado'])]);

    case 'buscar_curp':
        $curp = strtoupper(preg_replace('/\s+/', '', (string)in_('curp')));
        if (!preg_match(RX_CURP, $curp)) fail('El identificador no tiene un formato de CURP válido (18 caracteres).');
        $st = $pdo->prepare("SELECT p.id, p.nombre, p.ap_paterno, p.ap_materno, a.tipo_rep, a.estatus, a.zona_id, a.region_id, a.proceso_id, pr.estado
                             FROM rep_personas p LEFT JOIN rep_asignaciones a ON a.persona_id = p.id AND a.vigente = 1 LEFT JOIN rep_procesos pr ON pr.id = a.proceso_id WHERE p.curp_hash = ? LIMIT 1");
        $st->execute([RepModulo::curpHash($curp)]);
        $x = $st->fetch(PDO::FETCH_ASSOC);
        if (!$x) out(['existe' => false]);
        $donde = '';
        if ($x['tipo_rep']) {
            if ((int)$x['proceso_id'] === $PID) {
                $donde = ($x['zona_id'] ? ($R->zonas[(int)$x['zona_id']]['nombre'] ?? 'Zona') . ', ' : '') . ($R->regiones[(int)$x['region_id']]['nombre'] ?? '');
                $coord = $x['zona_id'] ? ($R->zonas[(int)$x['zona_id']]['coord'] ?? '') : ($R->regiones[(int)$x['region_id']]['coord'] ?? '');
                if ($coord) $donde .= ' · responsable: ' . $coord;
            } else $donde = 'otro estado (' . $x['estado'] . ')';
        }
        out(['existe' => true, 'vigente' => (bool)$x['tipo_rep'], 'nombre' => nombreCompleto($x), 'tipo' => $x['tipo_rep'], 'estatus' => $x['estatus'] ? RepModulo::ESTATUS[$x['estatus']][0] : null, 'donde' => $donde]);

    case 'alta':
        $tipo = (string)in_('tipo');
        if ($tipo === 'CONSEJERO') $R->exigir('capturar_con'); else $R->exigir('capturar');
        if (!in_array($tipo, ['RC', 'RG', 'CONSEJERO'], true)) fail('Tipo de representación inválido.');
        $per = $R->periodo();
        if ($per['k'] === 'cerrado') fail($per['msg']);
        $curp = strtoupper(preg_replace('/\s+/', '', (string)in_('curp')));
        $nom = trim((string)in_('nombre'));
        $ap = trim((string)in_('ap'));
        $am = trim((string)in_('am', ''));
        $tel = preg_replace('/\D/', '', (string)in_('tel'));
        $email = trim((string)in_('email', ''));
        $secDom = RepModulo::sec((string)in_('secDom'));
        $errs = [];
        if (!preg_match(RX_CURP, $curp)) $errs['curp'] = 'El identificador no tiene un formato de CURP válido.';
        if (!preg_match(RX_NOM, $nom)) $errs['nombre'] = 'Captura el nombre (2 a 60 letras, sin números).';
        if (!preg_match(RX_NOM, $ap)) $errs['ap'] = 'Captura el apellido paterno.';
        if ($am !== '' && !preg_match(RX_NOM, $am)) $errs['am'] = 'El apellido materno solo admite letras.';
        if (!preg_match('/^\d{10}$/', $tel)) $errs['tel'] = 'El teléfono debe tener 10 dígitos.';
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errs['email'] = 'Revisa el formato del correo.';
        if (!$R->seccionExiste($secDom)) $errs['secDom'] = 'La sección no existe en el catálogo de ' . $R->estado . '.';
        if ($tipo === 'CONSEJERO') {
            $rid = (int)in_('region_id', $R->regionId);
            $zid = null;
            if (!$R->regionEnAmbito($rid)) $errs['zona'] = 'La región está fuera de tu ámbito.';
        } else {
            $zid = (int)in_('zona_id');
            if (!$R->zonaEnAmbito($zid)) $errs['zona'] = 'Fuera de tu ámbito de responsabilidad.';
            $rid = $R->zonas[$zid]['region'] ?? 0;
        }
        $pos = null;
        if ($k = (string)in_('pos', '')) {
            $pos = $R->posicion($k);
            if (!$pos || !$pos['valida'] || !$pos['activa'] || $pos['tipo'] !== $tipo || ($tipo === 'CONSEJERO' ? $pos['region_id'] !== $rid : $pos['zona_id'] !== $zid)) $errs['pos'] = 'La posición no corresponde a la zona y tipo elegidos.';
        }
        if ($per['k'] === 'sust' && !$pos) $errs['pos'] = 'Después del cierre de registro solo se dan altas para cubrir una posición vacante.';
        if ($errs) fail('Revisa los campos marcados.', 422, ['errores' => $errs]);

        $hash = RepModulo::curpHash($curp);
        $st = $pdo->prepare("SELECT id FROM rep_personas WHERE curp_hash = ?");
        $st->execute([$hash]);
        $pid = (int)$st->fetchColumn();
        if ($pid) {
            $st = $pdo->prepare("SELECT COUNT(*) FROM rep_asignaciones WHERE persona_id = ? AND vigente = 1");
            $st->execute([$pid]);
            if ((int)$st->fetchColumn()) fail('Ya existe una persona con este identificador en el padrón. No se crea otro registro.', 409, ['errores' => ['curp' => 'Ya existe una persona con este identificador.']]);
        }
        $pdo->beginTransaction();
        try {
            if ($pid) {
                $pdo->prepare("UPDATE rep_personas SET nombre=?, ap_paterno=?, ap_materno=?, telefono=?, email=?, seccion_domicilio=?, estado=? WHERE id=?")
                    ->execute([$nom, $ap, $am, $tel, $email, $secDom, $R->estado, $pid]);
            } else {
                $pdo->prepare("INSERT INTO rep_personas (curp_hash, curp_cifrado, curp_mask, nombre, ap_paterno, ap_materno, telefono, email, seccion_domicilio, estado, creado_por, creado_por_rol) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
                    ->execute([$hash, RepModulo::curpCifrar($curp), RepModulo::curpMask($curp), $nom, $ap, $am, $tel, $email, $secDom, $R->estado, $R->userId, $R->rol]);
                $pid = (int)$pdo->lastInsertId();
            }
            $pdo->prepare("INSERT INTO rep_asignaciones (persona_id, proceso_id, tipo_rep, region_id, zona_id, posicion_key, casilla_id, estatus, f_reclutado, creado_por) VALUES (?,?,?,?,?,?,?, 'RECLUTADO', ?, ?)")
                ->execute([$pid, $PID, $tipo, $rid, $zid, $pos['key'] ?? null, $pos['casilla_id'] ?? null, $hoyTs, $R->userId]);
            $aid = (int)$pdo->lastInsertId();
            $historial($aid, null, 'RECLUTADO', $per['k'] === 'sust' ? 'Alta por sustitución' : 'Alta en el padrón');
            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode() == 23000) fail('La posición ya fue ocupada por otra persona. Elige otra.', 409, ['errores' => ['pos' => 'La posición ya está ocupada.']]);
            throw $e;
        }
        $R->log('Alta de representante', trim("$nom $ap $am"), $tipo . ' · ' . ($zid ? $R->zonas[$zid]['nombre'] : $R->regiones[$rid]['nombre']) . ($pos ? ' · ' . $pos['label'] : ''), $rid, $zid);
        $rr = $zid ? $R->real('z:' . $zid, $R->hoy) : $R->real('r:' . $rid, $R->hoy);
        $mm = $zid ? $R->meta('z:' . $zid) : $R->meta('r:' . $rid);
        out(['id' => $aid, 'msg' => trim("$nom $ap $am") . ' quedó registrado. ' . ($zid ? $R->zonas[$zid]['nombre'] . ' suma ' . number_format($rr['rec']) . ' de ' . number_format($mm['p']) . ' en reclutamiento.' : 'La región suma ' . $rr['con'] . ' de ' . $mm['con'] . ' consejeros.')]);

    case 'baja':
        $a = $asignacion((int)in_('id'));
        if (!$puedeCapturar($a)) fail('Tu rol no da de baja en este ámbito.', 403);
        $per = $R->periodo();
        if ($per['k'] === 'cerrado') fail($per['msg']);
        if (!(int)$a['vigente']) fail('La persona ya está dada de baja.');
        $mot = trim((string)in_('motivo'));
        if ($mot === '') fail('Indica el motivo de la baja.');
        $pdo->prepare("UPDATE rep_asignaciones SET vigente = 0, estatus = 'BAJA', baja_en = ?, motivo_baja = ? WHERE id = ?")->execute([$hoyTs, mb_substr($mot, 0, 150), $a['id']]);
        $historial((int)$a['id'], $a['estatus'], 'BAJA', $mot);
        $lbl = $a['posicion_key'] ? ($R->etiquetas([$a['posicion_key']])[$a['posicion_key']] ?? '') : '';
        $R->log('Baja de representante', nombreCompleto($a), $mot . ($lbl ? ' · libera ' . $lbl : ''), (int)$a['region_id'], $a['zona_id'] ? (int)$a['zona_id'] : null);
        out(['msg' => $lbl ? "Baja registrada. $lbl queda vacante y se reporta como faltante." : 'Baja registrada.']);

    /* ===============================================================
       EXPEDIENTE
       =============================================================== */
    case 'doc_subir':
        $a = $asignacion((int)in_('id'));
        if (!$puedeCapturar($a)) fail('Tu rol no carga documentos en este ámbito.', 403);
        if (!(int)$a['vigente']) fail('La persona está dada de baja.');
        $tipoDoc = (string)in_('tipo_documento');
        if (!in_array($tipoDoc, $docsDe($a['tipo_rep']), true)) fail('Documento no reconocido para este tipo de representación.');
        $st = $pdo->prepare("SELECT id, estatus FROM rep_documentos WHERE asignacion_id = ? AND tipo_documento = ?");
        $st->execute([$a['id'], $tipoDoc]);
        $prev = $st->fetch(PDO::FETCH_ASSOC);
        if ($prev && in_array($prev['estatus'], ['aprobado', 'revision'], true)) fail('Ese documento ya está ' . ($prev['estatus'] === 'aprobado' ? 'aprobado' : 'en revisión') . ' y no se puede reemplazar.');
        if (!in_array($a['estatus'], ['RECLUTADO', 'OBSERVADO', 'EN_REVISION'], true)) fail('El expediente ya no admite cambios.');
        $f = $guardarArchivo($_FILES['archivo'] ?? [], $PID . '/' . $a['id']);
        $st = $pdo->prepare("SELECT COUNT(*) FROM rep_documentos d JOIN rep_asignaciones x ON x.id = d.asignacion_id WHERE d.sha256 = ? AND x.persona_id <> ?");
        $st->execute([$f['sha'], $a['persona_id']]);
        if ((int)$st->fetchColumn()) { @unlink(PRIVATE_PATH . '/uploads/representantes/' . $f['ruta']); fail('Ese mismo archivo ya está cargado en el expediente de otra persona.'); }
        $nuevo = $a['estatus'] === 'EN_REVISION' ? 'revision' : 'cargado';
        if ($prev) $pdo->prepare("UPDATE rep_documentos SET archivo=?, nombre_original=?, mime=?, tamano=?, sha256=?, estatus=?, motivo=NULL, subido_por=?, subido_en=NOW(), revisado_por=NULL, revisado_en=NULL WHERE id=?")
            ->execute([$f['ruta'], $f['nombre'], $f['mime'], $f['size'], $f['sha'], $nuevo, $R->userName, $prev['id']]);
        else $pdo->prepare("INSERT INTO rep_documentos (asignacion_id, tipo_documento, archivo, nombre_original, mime, tamano, sha256, estatus, subido_por) VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([$a['id'], $tipoDoc, $f['ruta'], $f['nombre'], $f['mime'], $f['size'], $f['sha'], $nuevo, $R->userName]);
        $R->log('Carga de documento', nombreCompleto($a), $tipoDoc . ' · ' . $f['nombre'], (int)$a['region_id'], $a['zona_id'] ? (int)$a['zona_id'] : null);
        out(['msg' => 'Documento cargado.']);

    case 'doc_ver':
        $id = (int)($_GET['id'] ?? 0);
        $st = $pdo->prepare("SELECT * FROM rep_documentos WHERE id = ?");
        $st->execute([$id]);
        $d = $st->fetch(PDO::FETCH_ASSOC);
        if (!$d) fail('Documento no encontrado.', 404);
        $a = $asignacion((int)$d['asignacion_id']);
        $file = PRIVATE_PATH . '/uploads/representantes/' . $d['archivo'];
        if (!is_file($file)) fail('El archivo no está disponible en el servidor.', 404);
        $R->log('Consulta de documento', nombreCompleto($a), $d['tipo_documento'], (int)$a['region_id'], $a['zona_id'] ? (int)$a['zona_id'] : null);
        header_remove('Content-Type');
        header('Content-Type: ' . $d['mime']);
        header('Content-Length: ' . filesize($file));
        header('Content-Disposition: inline; filename="' . preg_replace('/[^\w.\- ]/u', '_', $d['nombre_original']) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        readfile($file);
        exit();

    case 'enviar_revision':
        $a = $asignacion((int)in_('id'));
        if (!$puedeCapturar($a)) fail('Tu rol no envía expedientes en este ámbito.', 403);
        if (!in_array($a['estatus'], ['RECLUTADO', 'OBSERVADO'], true)) fail('Transición no permitida.');
        $st = $pdo->prepare("SELECT tipo_documento, estatus FROM rep_documentos WHERE asignacion_id = ?");
        $st->execute([$a['id']]);
        $ds = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $d) $ds[$d['tipo_documento']] = $d['estatus'];
        $falt = array_values(array_filter($docsDe($a['tipo_rep']), function ($n) use ($ds) { return !isset($ds[$n]); }));
        if ($falt) fail('Faltan documentos por cargar: ' . implode(', ', $falt) . '.');
        if (in_array('observado', $ds, true)) fail('Vuelve a cargar los documentos observados antes de reenviar.');
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE rep_documentos SET estatus = 'revision' WHERE asignacion_id = ? AND estatus = 'cargado'")->execute([$a['id']]);
        $pdo->prepare("UPDATE rep_asignaciones SET estatus = 'EN_REVISION', f_revision = ? WHERE id = ?")->execute([$hoyTs, $a['id']]);
        $historial((int)$a['id'], $a['estatus'], 'EN_REVISION', $a['estatus'] === 'OBSERVADO' ? 'Corrección enviada' : 'Expediente enviado a revisión');
        $pdo->commit();
        $R->log('Cambio de estatus', nombreCompleto($a), RepModulo::ESTATUS[$a['estatus']][0] . ' → En revisión', (int)$a['region_id'], $a['zona_id'] ? (int)$a['zona_id'] : null);
        out(['msg' => 'Expediente enviado a revisión.']);

    case 'doc_revisar':
        $R->exigir('revisar');
        $st = $pdo->prepare("SELECT * FROM rep_documentos WHERE id = ?");
        $st->execute([(int)in_('doc_id')]);
        $d = $st->fetch(PDO::FETCH_ASSOC);
        if (!$d) fail('Documento no encontrado.', 404);
        $a = $asignacion((int)$d['asignacion_id']);
        if ($a['estatus'] !== 'EN_REVISION' || $d['estatus'] !== 'revision') fail('El documento no está en revisión.');
        $ok = (bool)in_('ok');
        $pdo->beginTransaction();
        if ($ok) {
            $pdo->prepare("UPDATE rep_documentos SET estatus='aprobado', motivo=NULL, revisado_por=?, revisado_en=NOW() WHERE id=?")->execute([$R->userName, $d['id']]);
            $R->log('Documento aprobado', nombreCompleto($a), $d['tipo_documento'], (int)$a['region_id'], $a['zona_id'] ? (int)$a['zona_id'] : null);
            $st = $pdo->prepare("SELECT tipo_documento, estatus FROM rep_documentos WHERE asignacion_id = ?");
            $st->execute([$a['id']]);
            $ds = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $x) $ds[$x['tipo_documento']] = $x['estatus'];
            $todo = true;
            foreach ($docsDe($a['tipo_rep']) as $n) if (($ds[$n] ?? '') !== 'aprobado') $todo = false;
            $msg = 'Documento aprobado.';
            if ($todo) {
                $pdo->prepare("UPDATE rep_asignaciones SET estatus='COMPLETO', f_completo=? WHERE id=?")->execute([$hoyTs, $a['id']]);
                $historial((int)$a['id'], 'EN_REVISION', 'COMPLETO', 'Documentos aprobados');
                $R->log('Cambio de estatus', nombreCompleto($a), 'En revisión → Expediente completo', (int)$a['region_id'], $a['zona_id'] ? (int)$a['zona_id'] : null);
                $msg = nombreCompleto($a) . ' pasa a Expediente completo y suma en la meta de expedientes.';
            }
        } else {
            $mot = trim((string)in_('motivo'));
            if ($mot === '') { $pdo->rollBack(); fail('Selecciona el motivo de la observación.'); }
            $pdo->prepare("UPDATE rep_documentos SET estatus='observado', motivo=?, revisado_por=?, revisado_en=NOW() WHERE id=?")->execute([mb_substr($mot, 0, 255), $R->userName, $d['id']]);
            $pdo->prepare("UPDATE rep_asignaciones SET estatus='OBSERVADO' WHERE id=?")->execute([$a['id']]);
            $historial((int)$a['id'], 'EN_REVISION', 'OBSERVADO', $d['tipo_documento'] . ': ' . $mot);
            $R->log('Documento observado', nombreCompleto($a), $d['tipo_documento'] . ' · ' . $mot, (int)$a['region_id'], $a['zona_id'] ? (int)$a['zona_id'] : null);
            $msg = 'Expediente observado. La zona recibe el motivo para corregir.';
        }
        $pdo->commit();
        out(['msg' => $msg]);

    case 'expedientes':
        $R->exigir('revisar');
        $w = "a.proceso_id = ? AND a.vigente = 1";
        $pp = [$PID];
        if ($R->nivel === 4) { $w .= ' AND a.region_id = ?'; $pp[] = $R->regionId; }
        $st = $pdo->prepare("SELECT a.id, a.tipo_rep, a.zona_id, a.region_id, a.f_revision, a.posicion_key, p.nombre, p.ap_paterno, p.ap_materno FROM rep_asignaciones a JOIN rep_personas p ON p.id = a.persona_id WHERE $w AND a.estatus = 'EN_REVISION' ORDER BY a.f_revision LIMIT 300");
        $st->execute($pp);
        $q = $st->fetchAll(PDO::FETCH_ASSOC);
        $st = $pdo->prepare("SELECT COUNT(*) FROM rep_asignaciones a WHERE $w AND a.estatus = 'OBSERVADO'");
        $st->execute($pp);
        $obs = (int)$st->fetchColumn();
        $st = $pdo->prepare("SELECT COUNT(*) FROM rep_asignaciones a WHERE $w AND a.estatus = 'EN_REVISION'");
        $st->execute($pp);
        $tot = (int)$st->fetchColumn();
        $hoyD = RepModulo::dn($R->hoy);
        $lbl = $R->etiquetas(array_column($q, 'posicion_key'));
        $cola = array_map(function ($x) use ($R, $hoyD, $lbl) {
            return ['id' => (int)$x['id'], 'nombre' => nombreCompleto($x), 'tipo' => $x['tipo_rep'], 'dias' => $hoyD - RepModulo::dn(substr($x['f_revision'], 0, 10)), 'enviado' => substr($x['f_revision'], 0, 10),
                'donde' => ($x['zona_id'] ? ($R->zonas[(int)$x['zona_id']]['nombre'] ?? '') . ' · ' : '') . ($R->regiones[(int)$x['region_id']]['nombre'] ?? ''), 'pos' => $x['posicion_key'] ? ($lbl[$x['posicion_key']] ?? '') : 'Sin posición asignada'];
        }, $q);
        $avg = $cola ? array_sum(array_column($cola, 'dias')) / count($cola) : 0;
        out(['cola' => $cola, 'total' => $tot, 'observados' => $obs, 'promedio' => round($avg, 1), 'motivos' => $P['motivos_obs']]);

    /* ===============================================================
       CARGA MASIVA (P-13)
       =============================================================== */
    case 'carga_validar':
    case 'carga_confirmar':
        $R->exigir('capturar');
        if ($R->periodo()['k'] !== 'abierto') fail('La carga masiva solo está disponible antes del cierre de registro.');
        $zid = (int)in_('zona_id');
        if (!$R->zonaEnAmbito($zid)) fail('Fuera de tu ámbito de responsabilidad.');
        $txt = (string)in_('csv', '');
        $lines = preg_split('/\r\n|\r|\n/', trim($txt));
        if (count($lines) < 2) fail('El archivo no tiene filas para validar.');
        if (count($lines) > 5001) fail('Máximo 5,000 filas por archivo.');
        $sep = substr_count($lines[0], ';') > substr_count($lines[0], ',') ? ';' : ',';
        array_shift($lines);
        $seen = [];
        $res = [];
        $hashes = [];
        foreach ($lines as $i => $l) {
            if (trim($l) === '') continue;
            $c = array_map('trim', str_getcsv($l, $sep));
            $c = array_pad($c, 7, '');
            [$curp, $nom, $ap, $am, $tel, $sec, $tipo] = $c;
            $curp = strtoupper(str_replace(' ', '', $curp));
            $tipo = strtoupper($tipo);
            $tel = preg_replace('/\D/', '', $tel);
            $err = [];
            if (!preg_match(RX_CURP, $curp)) $err[] = 'CURP con formato inválido';
            elseif (isset($seen[$curp])) $err[] = 'CURP repetida en el archivo';
            else { $hashes[$curp] = RepModulo::curpHash($curp); }
            $seen[$curp] = 1;
            if (!preg_match(RX_NOM, $nom) || !preg_match(RX_NOM, $ap) || ($am !== '' && !preg_match(RX_NOM, $am))) $err[] = 'Nombre o apellido inválido';
            if (!preg_match('/^\d{10}$/', $tel)) $err[] = 'Teléfono sin 10 dígitos';
            if (!$R->seccionExiste($sec)) $err[] = 'Sección inexistente';
            if (!in_array($tipo, ['RC', 'RG'], true)) $err[] = 'Tipo debe ser RC o RG';
            $res[] = ['n' => $i + 2, 'curp' => $curp, 'nombre' => $nom, 'ap' => $ap, 'am' => $am, 'tel' => $tel, 'sec' => RepModulo::sec($sec), 'tipo' => $tipo, 'err' => $err];
        }
        if ($hashes) {
            $existentes = [];
            foreach (array_chunk(array_values($hashes), 500) as $ch) {
                $st = $pdo->prepare("SELECT p.curp_hash FROM rep_personas p JOIN rep_asignaciones a ON a.persona_id = p.id AND a.vigente = 1 WHERE p.curp_hash IN (" . implode(',', array_fill(0, count($ch), '?')) . ")");
                $st->execute($ch);
                foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $h) $existentes[$h] = 1;
            }
            foreach ($res as &$r0) if (!$r0['err'] && isset($existentes[$hashes[$r0['curp']]])) $r0['err'][] = 'CURP ya registrada en el padrón';
            unset($r0);
        }
        $ok = array_values(array_filter($res, function ($r) { return !$r['err']; }));
        if ($action === 'carga_validar') {
            out(['filas' => array_map(function ($r) { unset($r['curp']); return $r; }, $res), 'validas' => count($ok), 'errores' => count($res) - count($ok)]);
        }
        if (!$ok) fail('No hay filas válidas para confirmar.');
        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO rep_cargas (proceso_id, zona_id, filas_total, filas_ok, filas_error, contenido, usuario_id, usuario_nombre) VALUES (?,?,?,?,?,?,?,?)")
            ->execute([$PID, $zid, count($res), count($ok), count($res) - count($ok), $txt, $R->userId, $R->userName]);
        $cid = (int)$pdo->lastInsertId();
        $insP = $pdo->prepare("INSERT INTO rep_personas (curp_hash, curp_cifrado, curp_mask, nombre, ap_paterno, ap_materno, telefono, seccion_domicilio, estado, creado_por, creado_por_rol) VALUES (?,?,?,?,?,?,?,?,?,?,?)
                               ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id), nombre = VALUES(nombre), ap_paterno = VALUES(ap_paterno), ap_materno = VALUES(ap_materno), telefono = VALUES(telefono), seccion_domicilio = VALUES(seccion_domicilio), estado = VALUES(estado)");
        $insA = $pdo->prepare("INSERT INTO rep_asignaciones (persona_id, proceso_id, tipo_rep, region_id, zona_id, estatus, f_reclutado, carga_id, creado_por) VALUES (?,?,?,?,?, 'RECLUTADO', ?, ?, ?)");
        foreach ($ok as $r) {
            $insP->execute([$hashes[$r['curp']], RepModulo::curpCifrar($r['curp']), RepModulo::curpMask($r['curp']), $r['nombre'], $r['ap'], $r['am'], $r['tel'], $r['sec'], $R->estado, $R->userId, $R->rol]);
            $insA->execute([(int)$pdo->lastInsertId(), $PID, $r['tipo'], $R->zonas[$zid]['region'], $zid, $hoyTs, $cid, $R->userId]);
            $historial((int)$pdo->lastInsertId(), null, 'RECLUTADO', 'Alta por carga masiva (lote ' . $cid . ')');
        }
        $pdo->commit();
        $R->log('Carga masiva', $R->zonas[$zid]['nombre'] . ' · ' . $R->regiones[$R->zonas[$zid]['region']]['nombre'], count($ok) . ' filas confirmadas, ' . (count($res) - count($ok)) . ' rechazadas (lote ' . $cid . ')', $R->zonas[$zid]['region'], $zid);
        out(['msg' => count($ok) . ' representantes agregados a ' . $R->zonas[$zid]['nombre'] . '.']);

    /* ===============================================================
       REGISTRO Y ACREDITACIÓN (P-09)
       =============================================================== */
    case 'registro_lista':
        $R->exigir('registro');
        $tab = ($_GET['tab'] ?? 'por') === 'reg' ? 'reg' : 'por';
        $w = 'a.proceso_id = ? AND a.vigente = 1';
        $pp = [$PID];
        if ($R->nivel === 4) { $w .= ' AND a.region_id = ?'; $pp[] = $R->regionId; }
        $st = $pdo->prepare("SELECT SUM(a.estatus='COMPLETO') c, SUM(a.estatus='REGISTRADO') r, SUM(a.estatus='ACREDITADO') ac FROM rep_asignaciones a WHERE $w");
        $st->execute($pp);
        $k = $st->fetch(PDO::FETCH_ASSOC);
        $st = $pdo->prepare("SELECT a.id, a.tipo_rep, a.estatus, a.zona_id, a.region_id, a.posicion_key, a.folio, a.f_completo, p.nombre, p.ap_paterno, p.ap_materno FROM rep_asignaciones a JOIN rep_personas p ON p.id = a.persona_id
                             WHERE $w AND a.estatus " . ($tab === 'por' ? "= 'COMPLETO'" : "IN ('REGISTRADO','ACREDITADO')") . " ORDER BY a.f_completo, a.id LIMIT 500");
        $st->execute($pp);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        $lbl = $R->etiquetas(array_column($rows, 'posicion_key'));
        out(['kpi' => ['por' => (int)$k['c'], 'reg' => (int)$k['r'], 'acr' => (int)$k['ac']], 'abierto' => $R->hoy >= $P['inicio_registro'] && $R->hoy <= $P['cierre_registro'],
            'rows' => array_map(function ($a) use ($R, $lbl) {
                return ['id' => (int)$a['id'], 'nombre' => nombreCompleto($a), 'tipo' => $a['tipo_rep'], 'estatus' => $a['estatus'], 'pos' => $a['posicion_key'] ? ($lbl[$a['posicion_key']] ?? '') : 'Sin posición asignada',
                    'donde' => ($a['zona_id'] ? ($R->zonas[(int)$a['zona_id']]['nombre'] ?? '') . ' · ' : '') . ($R->regiones[(int)$a['region_id']]['nombre'] ?? ''), 'folio' => $a['folio'], 'completo' => substr((string)$a['f_completo'], 0, 10)];
            }, $rows)]);

    case 'lote':
        $R->exigir('registro');
        $ids = array_values(array_unique(array_map('intval', json_decode((string)in_('ids', '[]'), true) ?: [])));
        $folio = trim((string)in_('folio'));
        $fecha = (string)in_('fecha');
        $errs = [];
        if ($R->hoy < $P['inicio_registro']) $errs[] = 'El registro ante la autoridad abre el ' . RepModulo::fd($P['inicio_registro']) . '.';
        if ($R->hoy > $P['cierre_registro']) $errs[] = 'El registro cerró el ' . RepModulo::fd($P['cierre_registro']) . '.';
        if (!$ids) $errs[] = 'Selecciona al menos una persona.';
        if ($folio === '' || !preg_match('/^[\w\-\/\.]{3,50}$/u', $folio)) $errs[] = 'Captura el folio del acuse (3 a 50 caracteres, sin espacios).';
        else {
            $st = $pdo->prepare("SELECT COUNT(*) FROM rep_lotes WHERE proceso_id = ? AND folio = ?");
            $st->execute([$PID, $folio]);
            if ((int)$st->fetchColumn()) $errs[] = 'Ese folio ya se usó en otro lote.';
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) $errs[] = 'Captura la fecha de presentación.';
        else {
            if ($fecha > $R->hoy) $errs[] = 'La fecha de presentación no puede ser futura.';
            if ($fecha < $P['inicio_registro']) $errs[] = 'La fecha es anterior al inicio del registro.';
        }
        if (empty($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) $errs[] = 'Adjunta el archivo del acuse.';
        $ps = [];
        foreach ($ids as $id) {
            try { $ps[] = $asignacion($id); } catch (RepError $e) { $errs[] = 'Hay personas fuera de tu ámbito en la selección.'; break; }
        }
        foreach ($ps as $a) {
            if ($a['estatus'] !== 'COMPLETO' || !(int)$a['vigente']) { $errs[] = 'Solo se registran personas con expediente completo.'; break; }
        }
        foreach ($ps as $a) if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) && substr($a['f_reclutado'], 0, 10) > $fecha) { $errs[] = nombreCompleto($a) . ' se dio de alta después de esa fecha.'; break; }
        if ($errs) fail('No se pudo registrar el lote.', 422, ['errores' => array_values(array_unique($errs))]);
        $f = $guardarArchivo($_FILES['archivo'], $PID . '/acuses');
        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO rep_lotes (proceso_id, folio, fecha_presentacion, archivo, nombre_original, sha256, total, origen, creado_por) VALUES (?,?,?,?,?,?,?, 'manual', ?)")
            ->execute([$PID, $folio, $fecha, $f['ruta'], $f['nombre'], $f['sha'], count($ps), $R->userName]);
        $lid = (int)$pdo->lastInsertId();
        $up = $pdo->prepare("UPDATE rep_asignaciones SET estatus='REGISTRADO', f_registrado=?, folio=?, lote_id=? WHERE id=?");
        foreach ($ps as $i => $a) {
            $fo = $folio . '-' . str_pad((string)($i + 1), 3, '0', STR_PAD_LEFT);
            $up->execute([$fecha . ' 12:00:00', $fo, $lid, $a['id']]);
            $historial((int)$a['id'], 'COMPLETO', 'REGISTRADO', 'Acuse ' . $fo);
        }
        $pdo->commit();
        $R->log('Registro con acuse', 'Lote ' . $folio, count($ps) . ' personas · presentado el ' . RepModulo::fd($fecha));
        out(['msg' => count($ps) . ' personas registradas con acuse. El avance de registro se actualizó.']);

    case 'acreditar':
        $R->exigir('registro');
        $ids = array_map('intval', (array)in_('ids', []));
        $n = 0;
        foreach ($ids as $id) {
            $a = $asignacion($id);
            if ($a['estatus'] !== 'REGISTRADO' || !(int)$a['vigente']) continue;
            $pdo->prepare("UPDATE rep_asignaciones SET estatus='ACREDITADO', f_acreditado=? WHERE id=?")->execute([$hoyTs, $a['id']]);
            $historial((int)$a['id'], 'REGISTRADO', 'ACREDITADO', 'Acreditación confirmada');
            $n++;
        }
        if (!$n) fail('Ninguna de las personas seleccionadas está registrada con acuse.');
        $R->log('Acreditación', $n . ' personas', 'Confirmación de la autoridad');
        out(['msg' => $n . ' personas acreditadas.']);

    case 'importar_acuses':
        $R->exigir('registro');
        if ($R->hoy < $P['inicio_registro'] || $R->hoy > $P['cierre_registro']) fail('El registro ante la autoridad no está abierto.');
        $lines = preg_split('/\r\n|\r|\n/', trim((string)in_('csv', '')));
        if (count($lines) < 2) fail('El archivo no tiene filas.');
        $sep = substr_count($lines[0], ';') > substr_count($lines[0], ',') ? ';' : ',';
        array_shift($lines);
        $res = [];
        $aplicar = (bool)in_('confirmar', false);
        $porFolio = [];
        $usados = [];
        foreach ($lines as $i => $l) {
            if (trim($l) === '') continue;
            [$curp, $folio, $fecha] = array_pad(array_map('trim', str_getcsv($l, $sep)), 3, '');
            $curp = strtoupper($curp);
            $err = '';
            $a = null;
            if (!preg_match(RX_CURP, $curp)) $err = 'CURP inválida';
            elseif (!preg_match('/^[\w\-\/\.]{3,50}$/u', $folio)) $err = 'Folio inválido';
            elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || $fecha > $R->hoy || $fecha < $P['inicio_registro']) $err = 'Fecha inválida (AAAA-MM-DD, no futura, dentro del registro)';
            elseif (isset($usados[$folio])) $err = 'Folio repetido en el archivo';
            else {
                $st = $pdo->prepare("SELECT a.id FROM rep_asignaciones a JOIN rep_personas p ON p.id = a.persona_id WHERE p.curp_hash = ? AND a.proceso_id = ? AND a.vigente = 1");
                $st->execute([RepModulo::curpHash($curp), $PID]);
                $aid = (int)$st->fetchColumn();
                if (!$aid) $err = 'La CURP no está en el padrón';
                else {
                    try { $a = $asignacion($aid); } catch (RepError $e) { $err = 'Fuera de tu ámbito'; }
                    if ($a && $a['estatus'] !== 'COMPLETO') $err = 'No tiene expediente completo';
                    $st = $pdo->prepare("SELECT COUNT(*) FROM rep_asignaciones WHERE proceso_id = ? AND folio = ?");
                    $st->execute([$PID, $folio]);
                    if (!$err && (int)$st->fetchColumn()) $err = 'Folio ya registrado';
                }
            }
            $usados[$folio] = 1;
            $res[] = ['n' => $i + 2, 'folio' => $folio, 'fecha' => $fecha, 'nombre' => $a ? nombreCompleto($a) : '', 'err' => $err];
            if (!$err) $porFolio[] = [$a, $folio, $fecha];
        }
        if (!$aplicar) out(['filas' => $res, 'validas' => count($porFolio)]);
        if (!$porFolio) fail('No hay filas válidas.');
        $pdo->beginTransaction();
        $insL = $pdo->prepare("INSERT INTO rep_lotes (proceso_id, folio, fecha_presentacion, total, origen, creado_por) VALUES (?,?,?,1,'importacion',?)");
        $up = $pdo->prepare("UPDATE rep_asignaciones SET estatus='REGISTRADO', f_registrado=?, folio=?, lote_id=? WHERE id=?");
        foreach ($porFolio as [$a, $folio, $fecha]) {
            $insL->execute([$PID, $folio, $fecha, $R->userName]);
            $up->execute([$fecha . ' 12:00:00', $folio, (int)$pdo->lastInsertId(), $a['id']]);
            $historial((int)$a['id'], 'COMPLETO', 'REGISTRADO', 'Acuse importado ' . $folio);
        }
        $pdo->commit();
        $R->log('Importación de acuses', count($porFolio) . ' acuses', 'Archivo de la autoridad');
        out(['msg' => count($porFolio) . ' acuses importados.']);

    /* ===============================================================
       METAS (P-10)
       =============================================================== */
    case 'metas':
        $padre = (string)($_GET['padre'] ?? '');
        $vistas = $R->nivel <= 3 ? array_merge(['estado'], array_map(function ($id) { return 'r:' . $id; }, array_keys($R->regiones))) : ($R->nivel === 4 ? ['r:' . $R->regionId] : ['r:' . $R->regionId]);
        if (!in_array($padre, $vistas, true)) $padre = $vistas[0];
        $editable = $R->can('metas') && (($padre === 'estado' && $R->nivel === 3) || ($R->nivel === 4 && $padre === 'r:' . $R->regionId));
        $kids = [];
        foreach ($R->hijos($padre) as $k) {
            $m = $R->meta($k);
            $kids[] = ['u' => $k, 'id' => (int)substr($k, 2), 'nombre' => $R->nombre($k), 'coord' => $R->coord($k), 'req' => $m['req'], 'meta' => $m['p'], 'con' => $m['con'], 'publicada' => $m['publicada']];
        }
        $mp = $R->meta($padre);
        $st = $pdo->prepare("SELECT version, ambito, suma, superior, autorizada, motivo, usuario_nombre, creado_en FROM rep_meta_versiones WHERE proceso_id = ? ORDER BY id DESC LIMIT 50");
        $st->execute([$PID]);
        $vers = $st->fetchAll(PDO::FETCH_ASSOC);
        out(['padre' => $padre, 'nombre' => $padre === 'estado' ? $R->estado : $R->nombre($padre), 'vistas' => array_map(function ($v) use ($R) { return ['u' => $v, 'l' => $v === 'estado' ? $R->estado . ' → regiones' : $R->nombre($v) . ' → zonas']; }, $vistas),
            'editable' => $editable, 'autorizar' => $R->can('autorizar'), 'superior' => $mp['p'], 'superiorCon' => $mp['con'], 'superiorReq' => $mp['req'], 'superiorPublicada' => $mp['publicada'],
            'hijos' => $kids, 'versiones' => $vers, 'siguiente' => ((int)($vers[0]['version'] ?? 0)) + 1, 'fuente' => $P['meta_fuente'], 'editaEstado' => $R->nivel === 3]);

    case 'meta_estatal':
        $R->exigir('metas');
        if ($R->nivel !== 3) fail('Solo la coordinación estatal fija la meta del estado.', 403);
        $v = in_('meta');
        $c = in_('con');
        if (!is_numeric($v) || (int)$v < 0 || (int)$v != $v || !is_numeric($c) || (int)$c < 0 || (int)$c != $c) fail('Las metas deben ser números enteros mayores o iguales a cero.');
        $fuente = trim((string)in_('fuente', $P['meta_fuente'])) ?: 'Cifras del CEN';
        $st = $pdo->prepare("SELECT COALESCE(MAX(version),0)+1 FROM rep_meta_versiones WHERE proceso_id = ?");
        $st->execute([$PID]);
        $ver = (int)$st->fetchColumn();
        $pdo->prepare("INSERT INTO rep_metas (proceso_id, unidad_tipo, unidad_id, meta_rep, meta_con, version, actualizado_por) VALUES (?, 'estado', 0, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE meta_rep=VALUES(meta_rep), meta_con=VALUES(meta_con), version=VALUES(version), actualizado_por=VALUES(actualizado_por)")
            ->execute([$PID, (int)$v, (int)$c, $ver, $R->userName]);
        $pdo->prepare("UPDATE rep_procesos SET meta_fuente = ? WHERE id = ?")->execute([mb_substr($fuente, 0, 100), $PID]);
        $pdo->prepare("INSERT INTO rep_meta_versiones (proceso_id, version, ambito, padre_tipo, padre_id, valores, suma, superior, autorizada, motivo, usuario_id, usuario_nombre) VALUES (?,?,?,?,?,?,?,?,0,?,?,?)")
            ->execute([$PID, $ver, 'Meta estatal · ' . $R->estado, 'estado', 0, json_encode(['meta' => (int)$v, 'con' => (int)$c]), (int)$v, (int)$v, 'Meta del estado (' . $fuente . ')', $R->userId, $R->userName]);
        $R->log('Meta estatal', $R->estado, 'Versión ' . $ver . ' · ' . number_format((int)$v) . ' RG + RC y ' . (int)$c . ' consejeros (' . $fuente . ')');
        out(['msg' => 'Meta estatal publicada como versión ' . $ver . '.']);

    case 'metas_publicar':
        $R->exigir('metas');
        $padre = (string)in_('padre');
        $editable = ($padre === 'estado' && $R->nivel === 3) || ($R->nivel === 4 && $padre === 'r:' . $R->regionId);
        if (!$editable) fail('Esta distribución la publica otro nivel. Aquí solo la consultas.', 403);
        $vals = (array)in_('valores', []);
        $kids = $R->hijos($padre);
        if (!$kids) fail('No hay unidades hijas para distribuir.');
        $sum = 0;
        $sumCon = 0;
        $limpios = [];
        foreach ($kids as $k) {
            $id = (string)(int)substr($k, 2);
            $v = $vals[$id]['meta'] ?? null;
            $c = $padre === 'estado' ? ($vals[$id]['con'] ?? 0) : 0;
            if (!is_numeric($v) || (int)$v != $v || (int)$v < 0 || !is_numeric($c) || (int)$c != $c || (int)$c < 0) fail('Las metas deben ser números enteros mayores o iguales a cero.');
            $limpios[$id] = ['meta' => (int)$v, 'con' => (int)$c];
            $sum += (int)$v;
            $sumCon += (int)$c;
        }
        $mp = $R->meta($padre);
        $sup = $mp['p'];
        $supCon = $padre === 'estado' ? $mp['con'] : 0;
        $desc = ($sum !== $sup) || ($padre === 'estado' && $sumCon !== $supCon);
        $mot = trim((string)in_('motivo', ''));
        if ($desc) {
            if (!$R->can('autorizar')) fail('La suma no cuadra con la meta superior (' . ($sum - $sup > 0 ? '+' : '') . number_format($sum - $sup) . '). Solo la coordinación estatal autoriza un descuadre (RN-02).');
            if (!in_('autorizo') || $mot === '') fail('Para publicar con descuadre marca la autorización y escribe el motivo (RN-02).');
        }
        $st = $pdo->prepare("SELECT COALESCE(MAX(version),0)+1 FROM rep_meta_versiones WHERE proceso_id = ?");
        $st->execute([$PID]);
        $ver = (int)$st->fetchColumn();
        $tipoHijo = $padre === 'estado' ? 'region' : 'zona';
        $pdo->beginTransaction();
        $up = $pdo->prepare("INSERT INTO rep_metas (proceso_id, unidad_tipo, unidad_id, meta_rep, meta_con, version, actualizado_por) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE meta_rep=VALUES(meta_rep), meta_con=VALUES(meta_con), version=VALUES(version), actualizado_por=VALUES(actualizado_por)");
        foreach ($limpios as $id => $v) $up->execute([$PID, $tipoHijo, (int)$id, $v['meta'], $v['con'], $ver, $R->userName]);
        if ($padre !== 'estado' || !$mp['publicada']) {
            // fija la meta superior vigente para que la suma quede trazable
            $tp = $padre === 'estado' ? 'estado' : 'region';
            $pdo->prepare("INSERT IGNORE INTO rep_metas (proceso_id, unidad_tipo, unidad_id, meta_rep, meta_con, version, actualizado_por) VALUES (?,?,?,?,?,?,?)")
                ->execute([$PID, $tp, $padre === 'estado' ? 0 : (int)substr($padre, 2), $sup, $mp['con'], $ver, $R->userName]);
        }
        $amb = $padre === 'estado' ? $R->estado . ' → regiones' : $R->nombre($padre) . ' → zonas';
        $pdo->prepare("INSERT INTO rep_meta_versiones (proceso_id, version, ambito, padre_tipo, padre_id, valores, suma, superior, autorizada, motivo, usuario_id, usuario_nombre) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$PID, $ver, $amb, $padre === 'estado' ? 'estado' : 'region', $padre === 'estado' ? 0 : (int)substr($padre, 2), json_encode($limpios), $sum, $sup, $desc ? 1 : 0, $mot ?: 'Ajuste de distribución', $R->userId, $R->userName]);
        $pdo->commit();
        $R->log('Publicación de metas', $amb, 'Versión ' . $ver . ' · suma ' . number_format($sum) . ' de ' . number_format($sup) . ($desc ? ' · descuadre autorizado: ' . $mot : ''), $padre === 'estado' ? null : (int)substr($padre, 2));
        out(['msg' => 'Metas publicadas como versión ' . $ver . '. Los tableros ya usan los nuevos valores.']);

    /* ===============================================================
       RUTA Y CORTES (P-11)
       =============================================================== */
    case 'ruta':
        out(['cortes' => $R->cortes, 'version' => $P['ruta_version'], 'editable' => $R->can('ruta'), 'inicio' => $P['fecha_inicio_ruta'], 'cierre' => $P['cierre_registro'], 'hoy' => $R->hoy,
            'inicioRegistro' => $P['inicio_registro']]);

    case 'ruta_guardar':
        $R->exigir('ruta');
        $cs = (array)in_('cortes', []);
        if (!$cs) fail('La ruta necesita al menos un corte.');
        $ks = ['rec', 'exp', 'reg', 'con'];
        $cerrados = [];
        foreach ($R->cortes as $c) if ($c['cerrado']) $cerrados[$c['id']] = $c;
        $prev = null;
        $nuevos = [];
        foreach ($cs as $i => $c) {
            $id = (int)($c['id'] ?? 0);
            if ($id && isset($cerrados[$id])) {
                $o = $cerrados[$id];
                if ($c['fecha'] !== $o['fecha']) fail('Corte cerrado: no se puede editar. Crea una nueva versión de la ruta.');
                foreach ($ks as $k) if ((float)$c[$k] !== (float)$o[$k]) fail('Corte cerrado: no se puede editar. Crea una nueva versión de la ruta.');
                unset($cerrados[$id]);
                $prev = $o;
                continue;
            }
            $f = (string)($c['fecha'] ?? '');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f)) fail('Fecha requerida en el corte ' . ($i + 1) . '.');
            if ($f < $P['fecha_inicio_ruta'] || $f > $P['cierre_registro']) fail('El corte del ' . RepModulo::fd($f) . ' está fuera del periodo de la ruta.');
            if ($prev && $f <= $prev['fecha']) fail('Las fechas de corte deben ser crecientes (corte ' . ($i + 1) . ').');
            $row = ['fecha' => $f];
            foreach ($ks as $k) {
                $v = $c[$k] ?? '';
                if (!is_numeric($v) || $v < 0 || $v > 100) fail('Los porcentajes van de 0 a 100 (corte ' . ($i + 1) . ').');
                if ($prev && (float)$v < (float)$prev[$k]) fail('El % acumulado no puede bajar (corte ' . ($i + 1) . ').');
                $row[$k] = (float)$v;
            }
            $nuevos[] = $row;
            $prev = $row;
        }
        if ($cerrados) fail('No se pueden eliminar cortes cerrados.');
        foreach ($ks as $k) if ((float)$prev[$k] !== 100.0) fail('El último corte de cada etapa debe ser 100%.');
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM rep_cortes WHERE proceso_id = ? AND cerrado_en IS NULL")->execute([$PID]);
        $ins = $pdo->prepare("INSERT INTO rep_cortes (proceso_id, fecha, orden, pct_rec, pct_exp, pct_reg, pct_con) VALUES (?,?,?,?,?,?,?)");
        foreach ($nuevos as $i => $c) $ins->execute([$PID, $c['fecha'], $i + 1, $c['rec'], $c['exp'], $c['reg'], $c['con']]);
        $pdo->prepare("UPDATE rep_procesos SET ruta_version = ruta_version + 1 WHERE id = ?")->execute([$PID]);
        $pdo->commit();
        $R->log('Ruta de cortes', $P['nombre'], 'Versión ' . ($P['ruta_version'] + 1) . ' · ' . count($cs) . ' cortes');
        out(['msg' => 'Ruta guardada como versión ' . ($P['ruta_version'] + 1) . '. Metas exigibles y semáforos recalculados.']);

    case 'corte_cerrar':
        $R->exigir('ruta');
        $id = (int)in_('id');
        $c = null;
        foreach ($R->cortes as $x) if ($x['id'] === $id) $c = $x;
        if (!$c) fail('Corte no encontrado.');
        if ($c['cerrado']) fail('El corte ya está cerrado.');
        if ($c['fecha'] > $R->hoy) fail('Solo se cierran cortes vencidos.');
        foreach ($R->cortes as $x) if (!$x['cerrado'] && $x['fecha'] < $c['fecha']) fail('Cierra primero el corte del ' . RepModulo::fd($x['fecha']) . '.');
        $foto = $R->fotoCorte($c['fecha']);
        $pdo->prepare("UPDATE rep_cortes SET cerrado_en = NOW(), cerrado_por = ?, snapshot = ? WHERE id = ? AND cerrado_en IS NULL")->execute([$R->userName, json_encode($foto, JSON_UNESCAPED_UNICODE), $id]);
        $R->log('Cierre de corte', 'Corte del ' . RepModulo::fd($c['fecha']), 'Foto inmutable guardada (RN-08)');
        out(['msg' => 'Corte del ' . RepModulo::fd($c['fecha']) . ' cerrado. Su foto ya no cambia.']);

    case 'corte_foto':
        $st = $pdo->prepare("SELECT fecha, snapshot, cerrado_en, cerrado_por FROM rep_cortes WHERE id = ? AND proceso_id = ? AND cerrado_en IS NOT NULL");
        $st->execute([(int)($_GET['id'] ?? 0), $PID]);
        $c = $st->fetch(PDO::FETCH_ASSOC);
        if (!$c) fail('Corte no encontrado.');
        $s = json_decode($c['snapshot'], true);
        $rows = [];
        $sem = function ($real, $meta, $k) use ($R, $c, $s) {
            $ex = (int)round($meta * $R->esperado($k, $c['fecha'], $s['cortes']) / 100);
            if (!$meta) return ['na', $ex];
            if ($ex <= 0) return [$real > 0 ? 'ok' : 'idle', $ex];
            $p = $real / $ex * 100;
            return [$p >= $s['umbral'][0] ? 'ok' : ($p >= $s['umbral'][1] ? 'warn' : 'bad'), $ex];
        };
        foreach ($s['u'] as $u => $x) {
            if (strpos($u, 'r:') !== 0 || ($R->nivel >= 4 && !$R->enAmbito($u))) continue;
            $o = ['u' => $u, 'nombre' => $R->nombre($u), 'meta' => $x['m']['p']];
            foreach (['rec', 'exp', 'reg'] as $k) { $q = $sem($x['r'][$k], $x['m']['p'], $k); $o[$k] = ['real' => $x['r'][$k], 'ex' => $q[1], 'sem' => $q[0]]; }
            $rows[] = $o;
        }
        out(['fecha' => $c['fecha'], 'cerrado_en' => $c['cerrado_en'], 'cerrado_por' => $c['cerrado_por'], 'rows' => $rows]);

    /* ===============================================================
       CONFIGURACIÓN (P-12, P-14)
       =============================================================== */
    case 'config':
        $prev = [];
        foreach (array_keys($R->regiones) as $rid) {
            $q = $R->requeridoRegion($rid);
            $m = $R->meta('r:' . $rid);
            $prev[] = ['id' => $rid, 'nombre' => $R->regiones[$rid]['nombre'], 'cas' => $q['cas'], 'rc' => $q['rc'], 'rg' => $q['rg'], 'meta' => $m['p'], 'publicada' => $m['publicada'], 'zonas' => count($R->regiones[$rid]['zonas']),
                'secciones' => array_sum(array_map(function ($z) use ($R) { return count($R->zonas[$z]['secciones']); }, $R->regiones[$rid]['zonas']))];
        }
        $st = $pdo->prepare("SELECT fuente, COUNT(*) n, SUM(activa) act FROM rep_casillas WHERE proceso_id = ? GROUP BY fuente");
        $st->execute([$PID]);
        $sinSec = 0;
        foreach (array_keys($R->secZona) as $s) if (empty($R->casSec[$s])) $sinSec++;
        out(['proceso' => [
                'nombre' => $P['nombre'], 'inicio' => $P['fecha_inicio_ruta'], 'inicioRegistro' => $P['inicio_registro'], 'cierreRegistro' => $P['cierre_registro'], 'cierreSust' => $P['cierre_sustituciones'], 'jornada' => $P['fecha_jornada'],
                'umbralRuta' => $P['umbral_ruta'], 'umbralAtn' => $P['umbral_atencion'], 'prop' => $P['rc_propietarios'], 'sup' => $P['rc_suplentes'], 'rgCada' => $P['rg_cada'], 'rgMult' => $P['rg_por_grupo'], 'ambito' => $P['rg_ambito'],
                'docsRep' => $P['docs_rep'], 'docsCon' => $P['docs_con'], 'motivos' => $P['motivos_obs'], 'fuente' => $P['meta_fuente']],
            'regiones' => $prev, 'catalogo' => ['fuentes' => $st->fetchAll(PDO::FETCH_ASSOC), 'secciones' => count($R->secZona), 'sinCasillas' => $sinSec, 'total' => array_sum($R->casSec)],
            'editable' => $R->can('config'), 'casillas' => $R->can('casillas')]);

    case 'config_guardar':
        $R->exigir('config');
        $c = (array)in_('proceso', []);
        $f = [];
        foreach (['inicio' => 'fecha_inicio_ruta', 'inicioRegistro' => 'inicio_registro', 'cierreRegistro' => 'cierre_registro', 'cierreSust' => 'cierre_sustituciones', 'jornada' => 'fecha_jornada'] as $k => $col) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($c[$k] ?? ''))) fail('Fecha inválida: ' . $k);
            $f[$col] = $c[$k];
        }
        if (!($f['fecha_inicio_ruta'] < $f['inicio_registro'] && $f['inicio_registro'] <= $f['cierre_registro'] && $f['cierre_registro'] <= $f['cierre_sustituciones'] && $f['cierre_sustituciones'] < $f['fecha_jornada'])) fail('Las fechas deben ir en orden: inicio de ruta < inicio de registro ≤ cierre de registro ≤ cierre de sustituciones < jornada.');
        $ur = (float)($c['umbralRuta'] ?? 100);
        $ua = (float)($c['umbralAtn'] ?? 85);
        if ($ur < 1 || $ur > 150 || $ua < 1 || $ua >= $ur) fail('El umbral de Atención debe ser menor que el de En ruta (1 a 150).');
        $prop = (int)($c['prop'] ?? 2);
        $sup = (int)($c['sup'] ?? 2);
        $cada = (int)($c['rgCada'] ?? 10);
        $mult = (int)($c['rgMult'] ?? 2);
        $amb = in_array($c['ambito'] ?? '', ['municipio', 'zona', 'region'], true) ? $c['ambito'] : 'municipio';
        if ($prop < 1 || $prop > 4 || $sup < 0 || $sup > 4 || $cada < 1 || $cada > 50 || $mult < 1 || $mult > 5) fail('Revisa las reglas de posiciones (propietarios 1–4, suplentes 0–4, casillas por grupo 1–50, RG por grupo 1–5).');
        if ($prop < $P['rc_propietarios'] || $sup < $P['rc_suplentes']) {
            $st = $pdo->prepare("SELECT COUNT(*) FROM rep_asignaciones WHERE proceso_id = ? AND vigente = 1 AND casilla_id IS NOT NULL AND (CAST(SUBSTRING(SUBSTRING_INDEX(posicion_key, ':', -1), 2) AS UNSIGNED) > IF(SUBSTRING(SUBSTRING_INDEX(posicion_key, ':', -1),1,1)='P', ?, ?))");
            $st->execute([$PID, $prop, $sup]);
            if ((int)$st->fetchColumn()) fail('Hay personas asignadas en posiciones que desaparecerían con esta regla. Reubícalas primero (RN-18).');
        }
        $lista = function ($v, $min) { $v = array_values(array_filter(array_map(function ($x) { return mb_substr(trim((string)$x), 0, 120); }, (array)$v))); return count($v) >= $min ? $v : null; };
        $dr = $lista($c['docsRep'] ?? [], 1);
        $dc = $lista($c['docsCon'] ?? [], 1);
        $mo = $lista($c['motivos'] ?? [], 1);
        if (!$dr || !$dc || !$mo) fail('Debe haber al menos un documento obligatorio por tipo y un motivo de observación.');
        $pdo->prepare("UPDATE rep_procesos SET nombre=?, fecha_inicio_ruta=?, inicio_registro=?, cierre_registro=?, cierre_sustituciones=?, fecha_jornada=?, umbral_ruta=?, umbral_atencion=?, rc_propietarios=?, rc_suplentes=?, rg_cada=?, rg_por_grupo=?, rg_ambito=?, docs_rep=?, docs_con=?, motivos_obs=? WHERE id=?")
            ->execute([mb_substr(trim((string)($c['nombre'] ?? $P['nombre'])), 0, 150) ?: $P['nombre'], $f['fecha_inicio_ruta'], $f['inicio_registro'], $f['cierre_registro'], $f['cierre_sustituciones'], $f['fecha_jornada'], $ur, $ua, $prop, $sup, $cada, $mult, $amb,
                json_encode($dr, JSON_UNESCAPED_UNICODE), json_encode($dc, JSON_UNESCAPED_UNICODE), json_encode($mo, JSON_UNESCAPED_UNICODE), $PID]);
        $R->log('Configuración del proceso', $P['nombre'], 'Fechas, umbrales (' . $ur . '/' . $ua . '), reglas (' . $prop . '+' . $sup . ' RC; ' . $mult . ' RG por cada ' . $cada . ' casillas, por ' . $amb . ')');
        out(['msg' => 'Configuración guardada. Los tableros se recalculan con los nuevos parámetros.']);

    case 'casillas_importar':
        $R->exigir('casillas');
        $lines = preg_split('/\r\n|\r|\n/', trim((string)in_('csv', '')));
        if (count($lines) < 2) fail('El archivo no tiene filas.');
        $sep = substr_count($lines[0], ';') > substr_count($lines[0], ',') ? ';' : ',';
        array_shift($lines);
        $aplicar = (bool)in_('confirmar', false);
        $st = $pdo->prepare("SELECT seccion, tipo FROM rep_casillas WHERE proceso_id = ?");
        $st->execute([$PID]);
        $exist = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $x) $exist[RepModulo::sec($x['seccion']) . '|' . $x['tipo']] = 1;
        $res = [];
        $ok = [];
        foreach ($lines as $i => $l) {
            if (trim($l) === '') continue;
            [$s, $t, $ub] = array_pad(array_map('trim', str_getcsv($l, $sep)), 3, '');
            $s = RepModulo::sec($s);
            $t = strtoupper($t);
            $err = '';
            if (!$R->seccionExiste($s)) $err = 'Sección inexistente';
            elseif (!preg_match(RX_CASILLA, $t)) $err = 'Tipo inválido (B, C1…, E1…, X1…)';
            elseif (isset($exist[$s . '|' . $t])) $err = 'Ya existe en el catálogo';
            $exist[$s . '|' . $t] = 1;
            $res[] = ['n' => $i + 2, 'seccion' => $s, 'tipo' => $t, 'err' => $err];
            if (!$err) $ok[] = [$s, $t, $ub];
        }
        if (!$aplicar) out(['filas' => array_slice($res, 0, 500), 'validas' => count($ok), 'errores' => count($res) - count($ok)]);
        if (!$ok) fail('No hay filas válidas.');
        $pdo->beginTransaction();
        $ins = $pdo->prepare("INSERT INTO rep_casillas (proceso_id, seccion, tipo, ubicacion, fuente) VALUES (?,?,?,?, 'importacion')");
        foreach ($ok as $o) $ins->execute([$PID, $o[0], $o[1], $o[2] ?: null]);
        $pdo->commit();
        $R->log('Importación de casillas', $R->estado, count($ok) . ' casillas agregadas al catálogo');
        out(['msg' => count($ok) . ' casillas agregadas al catálogo.']);

    case 'casillas_estimar':
        $R->exigir('casillas');
        $cada = max(100, min(1500, (int)in_('por_cada', 750)));
        $info = $R->secInfo();
        $n = 0;
        $ins = $pdo->prepare("INSERT IGNORE INTO rep_casillas (proceso_id, seccion, tipo, fuente) VALUES (?,?,?, 'estimacion')");
        $pdo->beginTransaction();
        foreach (array_keys($R->secZona) as $s) {
            if (!empty($R->casSec[$s])) continue;
            $k = max(1, (int)ceil(($info[$s]['ln'] ?? 0) / $cada));
            for ($i = 0; $i < $k; $i++) { $ins->execute([$PID, $s, $i === 0 ? 'B' : 'C' . $i]); $n += $ins->rowCount(); }
        }
        $pdo->commit();
        $R->log('Estimación de casillas', $R->estado, $n . ' casillas estimadas (1 por cada ' . $cada . ' electores de lista nominal)');
        out(['msg' => $n . ' casillas estimadas para las secciones sin catálogo. Sustitúyelas con el encarte oficial cuando esté disponible.']);

    /* ===============================================================
       BITÁCORA (P-15)
       =============================================================== */
    case 'bitacora':
        $R->exigir('bitacora');
        $w = 'proceso_id = ?';
        $pp = [$PID];
        if ($R->nivel === 4) { $w .= ' AND region_id = ?'; $pp[] = $R->regionId; }
        if ($q = trim((string)($_GET['q'] ?? ''))) { $w .= " AND CONCAT_WS(' ', usuario_nombre, accion, entidad, detalle) LIKE ?"; $pp[] = '%' . $q . '%'; }
        $page = max(0, (int)($_GET['page'] ?? 0));
        $st = $pdo->prepare("SELECT COUNT(*) FROM rep_bitacora WHERE $w");
        $st->execute($pp);
        $total = (int)$st->fetchColumn();
        $lim = ($_GET['export'] ?? '') === '1' ? ' LIMIT 20000' : ' LIMIT 50 OFFSET ' . ($page * 50);
        $st = $pdo->prepare("SELECT fecha, usuario_nombre u, rol, accion a, entidad e, detalle d FROM rep_bitacora WHERE $w ORDER BY id DESC" . $lim);
        $st->execute($pp);
        out(['total' => $total, 'page' => $page, 'per' => 50, 'rows' => $st->fetchAll(PDO::FETCH_ASSOC)]);

    default:
        fail('Acción no reconocida.', 404);
    }
} catch (RepError $e) {
    fail($e->getMessage(), $e->http);
} catch (Throwable $e) {
    error_log('[representantes] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    fail('Error interno del servidor.', 500);
}
