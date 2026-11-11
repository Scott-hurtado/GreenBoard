<?php
/**
 * Módulo «Representantes» — contexto del usuario, ámbito territorial,
 * permisos y capa de cálculo (metas, exigible, avance, semáforo).
 *
 * Una instancia = un usuario operando sobre UN estado (proceso electoral).
 * La circunscripción y el admin pueden cambiar de estado con ?estado=.
 */
class RepModulo
{
    const ESTATUS = [
        'RECLUTADO'   => ['Reclutado', 'info', 1],
        'EN_REVISION' => ['En revisión', 'warn', 2],
        'OBSERVADO'   => ['Observado', 'bad', 2],
        'COMPLETO'    => ['Expediente completo', 'ok', 3],
        'REGISTRADO'  => ['Registrado con acuse', 'ok', 4],
        'ACREDITADO'  => ['Acreditado', 'ok', 5],
        'BAJA'        => ['Baja', '', 0],
    ];
    const ETAPAS = [
        ['k' => 'rec', 'l' => 'Reclutamiento',         'sub' => 'RG y RC con datos capturados'],
        ['k' => 'exp', 'l' => 'Expedientes completos', 'sub' => 'Revisados con el formato vigente'],
        ['k' => 'reg', 'l' => 'Registro con acuse',    'sub' => 'Trámite presentado con acuse'],
        ['k' => 'con', 'l' => 'Consejeros',            'sub' => 'Representaciones designadas'],
    ];
    const SEM = ['ok' => 'En ruta', 'warn' => 'Atención', 'bad' => 'Rezago', 'idle' => 'Arranque', 'na' => 'Sin meta'];

    const DOCS_REP = ['Credencial para votar (ambos lados)', 'Comprobante de domicilio', 'Formato de aceptación firmado', 'Fotografía reciente'];
    const DOCS_CON = ['Credencial para votar (ambos lados)', 'Nombramiento', 'Formato de aceptación firmado'];
    const MOTIVOS  = ['Credencial ilegible', 'Comprobante con más de 3 meses', 'Falta firma en el formato', 'Datos no coinciden con la credencial', 'Fotografía no cumple el formato', 'Otro'];

    // Ruta por defecto (propuesta técnica): fecha, % reclutamiento, expedientes, registro, consejeros
    const CORTES_DEF = [
        ['2026-11-30', 15, 5, 0, 10], ['2026-12-31', 35, 15, 0, 30], ['2027-01-31', 55, 30, 0, 55], ['2027-02-28', 75, 50, 0, 75],
        ['2027-03-31', 90, 70, 0, 90], ['2027-04-15', 100, 85, 0, 95], ['2027-04-30', 100, 95, 40, 100], ['2027-05-15', 100, 100, 85, 100],
        ['2027-05-24', 100, 100, 100, 100],
    ];

    const CIRCS = [
        'circunscripcion 1' => ['Baja California', 'Baja California Sur', 'Chihuahua', 'Durango', 'Jalisco', 'Nayarit', 'Sinaloa', 'Sonora'],
        'circunscripcion 2' => ['Aguascalientes', 'Coahuila', 'Guanajuato', 'Nuevo Leon', 'San Luis Potosi', 'Tamaulipas', 'Zacatecas'],
        'circunscripcion 3' => ['Campeche', 'Chiapas', 'Oaxaca', 'Quintana Roo', 'Tabasco', 'Veracruz', 'Yucatan'],
        'circunscripcion 4' => ['Ciudad de Mexico', 'Guerrero', 'Hidalgo', 'Morelos', 'Puebla', 'Tlaxcala'],
        'circunscripcion 5' => ['Colima', 'Estado de Mexico', 'Michoacan', 'Queretaro'],
    ];

    /** @var PDO */
    public $pdo;
    public $rol = '';
    public $nivel = 6;
    public $userId = 0;
    public $userName = '';
    public $estados = [];     // estados que puede consultar
    public $estado = '';      // estado activo
    public $regionId = 0;     // ámbito regional (0 = no aplica)
    public $zonaId = 0;       // ámbito de zona (0 = no aplica)
    public $hoy;              // 'Y-m-d'

    public $proc = null;
    public $cortes = [];
    public $regiones = [];    // id => [...]
    public $zonas = [];       // id => [...]
    public $secZona = [];     // seccion => zona_id
    public $casSec = [];      // seccion => casillas activas
    private $secInfo = null;  // seccion => [ln, municipio, dl, df]
    private $metas = null;
    private $cacheReal = [];
    private $cacheCov = null;

    /* ============================================================
       Construcción y ámbito
       ============================================================ */
    public static function nivelDe(string $rol): int
    {
        $map = [
            'admin' => 1, 'administrador' => 1, 'superadmin' => 1, 'super admin' => 1,
            'coordinadores_circunscripcion' => 2, 'coordinador_circunscripcion' => 2, 'circunscripcion' => 2,
            'coordinador_estatal' => 3, 'coordinador_regional' => 4, 'regional' => 4, 'coordinador_zona' => 5,
        ];
        return $map[strtolower(trim($rol))] ?? 6;
    }

    public static function norm(string $s): string
    {
        $s = mb_strtolower(trim($s), 'UTF-8');
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        return preg_replace('/\s+/', ' ', $t !== false ? $t : $s);
    }

    public static function sec($s): string
    {
        $s = trim((string)$s);
        return ctype_digit($s) ? (string)(int)$s : $s;
    }

    public function __construct(PDO $pdo, ?string $estadoParam = null, ?array $base = null)
    {
        $this->pdo = $pdo;
        $this->hoy = date('Y-m-d');
        if ($base) {
            // Clon para otro estado (vista de circunscripción)
            foreach (['rol', 'nivel', 'userId', 'userName', 'estados'] as $k) $this->$k = $base[$k];
            $this->estado = $estadoParam;
        } else {
            $this->rol = strtolower(trim($_SESSION['user_role'] ?? ''));
            $this->nivel = self::nivelDe($this->rol);
            $this->userId = (int)($_SESSION['user_id'] ?? 0);
            $this->userName = (string)($_SESSION['user_name'] ?? 'Usuario');
            if ($this->nivel > 5) throw new RepError('Sin acceso al módulo de representantes.', 403);
            $this->resolverAmbito($estadoParam);
        }
        $this->cargarProceso();
        $this->cargarEstructura();
    }

    public function paraEstado(string $estado): RepModulo
    {
        return new RepModulo($this->pdo, $estado, ['rol' => $this->rol, 'nivel' => $this->nivel, 'userId' => $this->userId, 'userName' => $this->userName, 'estados' => $this->estados]);
    }

    private function resolverAmbito(?string $estadoParam): void
    {
        $pdo = $this->pdo;
        $todos = $pdo->query("SELECT DISTINCT estado FROM regiones_estatales WHERE estado <> '' ORDER BY estado")->fetchAll(PDO::FETCH_COLUMN);

        if ($this->nivel === 1) {
            $this->estados = $todos;
        } elseif ($this->nivel === 2) {
            $circ = self::norm((string)($_SESSION['user_circunscripcion'] ?? ''));
            $lista = [];
            foreach (self::CIRCS as $k => $l) if (self::norm($k) === $circ) $lista = array_map([self::class, 'norm'], $l);
            $this->estados = array_values(array_filter($todos, function ($e) use ($lista) { return in_array(self::norm($e), $lista, true); }));
        } elseif ($this->nivel === 3) {
            $e = trim((string)($_SESSION['user_estado'] ?? ''));
            if ($e === '' && $this->userId) {
                $st = $pdo->prepare("SELECT estado FROM coordinador_estatal WHERE id = ? LIMIT 1");
                $st->execute([$this->userId]);
                $e = (string)$st->fetchColumn();
            }
            $this->estados = $e !== '' ? [$this->canonEstado($e, $todos)] : [];
        } elseif ($this->nivel === 4) {
            $rid = (int)($_SESSION['user_region'] ?? 0);
            if (!$rid && $this->userId) {
                $st = $pdo->prepare("SELECT region FROM coordinador_regional WHERE id = ? LIMIT 1");
                $st->execute([$this->userId]);
                $rid = (int)$st->fetchColumn();
            }
            if (!$rid && $this->userId) {
                $st = $pdo->prepare("SELECT id FROM regiones_estatales WHERE coordinador_id = ? LIMIT 1");
                $st->execute([$this->userId]);
                $rid = (int)$st->fetchColumn();
            }
            if (!$rid) throw new RepError('Tu usuario no tiene una región asignada.', 403);
            $st = $pdo->prepare("SELECT estado FROM regiones_estatales WHERE id = ? LIMIT 1");
            $st->execute([$rid]);
            $this->regionId = $rid;
            $this->estados = [(string)$st->fetchColumn()];
        } elseif ($this->nivel === 5) {
            $zid = (int)($_SESSION['user_zona_id'] ?? 0);
            if (!$zid && is_numeric($_SESSION['user_zona'] ?? null)) $zid = (int)$_SESSION['user_zona'];
            if (!$zid && $this->userId) {
                $st = $pdo->prepare("SELECT zona FROM coordinador_zona WHERE id = ? LIMIT 1");
                $st->execute([$this->userId]);
                $zid = (int)$st->fetchColumn();
            }
            if (!$zid && $this->userId) {
                $st = $pdo->prepare("SELECT id FROM zonas_estatales WHERE coordinador_id = ? LIMIT 1");
                $st->execute([$this->userId]);
                $zid = (int)$st->fetchColumn();
            }
            if (!$zid) throw new RepError('Tu usuario no tiene una zona asignada.', 403);
            $st = $pdo->prepare("SELECT z.estructura_id, r.estado FROM zonas_estatales z JOIN regiones_estatales r ON r.id = z.estructura_id WHERE z.id = ? LIMIT 1");
            $st->execute([$zid]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new RepError('La zona asignada a tu usuario no existe.', 403);
            $this->zonaId = $zid;
            $this->regionId = (int)$row['estructura_id'];
            $this->estados = [(string)$row['estado']];
        }

        if (!$this->estados) throw new RepError('No hay estados disponibles para tu usuario.', 403);
        $this->estado = $this->estados[0];
        if ($estadoParam !== null && $estadoParam !== '') {
            foreach ($this->estados as $e) if (self::norm($e) === self::norm($estadoParam)) $this->estado = $e;
        }
    }

    private function canonEstado(string $e, array $todos): string
    {
        foreach ($todos as $t) if (self::norm($t) === self::norm($e)) return $t;
        return $e;
    }

    /* ============================================================
       Permisos (matriz de la propuesta, sección 8.1)
       ============================================================ */
    public function perms(): array
    {
        switch ($this->nivel) {
            case 1: return ['export', 'bitacora', 'config', 'ruta', 'casillas'];
            case 2: return ['export', 'bitacora', 'autorizar'];
            case 3: return ['export', 'bitacora', 'metas', 'autorizar', 'ruta', 'casillas', 'registro', 'revisar', 'verid', 'config'];
            case 4: return ['export', 'bitacora', 'metas', 'revisar', 'registro', 'verid', 'capturar_con'];
            case 5: return ['export', 'capturar', 'verid'];
        }
        return [];
    }

    public function can(string $p): bool { return in_array($p, $this->perms(), true); }

    public function exigir(string $p): void
    {
        if (!$this->can($p)) throw new RepError('Tu rol no tiene permiso para esta acción.', 403);
    }

    public function rolLabel(): string
    {
        return [1 => 'Administración', 2 => 'Coordinación de circunscripción', 3 => 'Coordinación estatal', 4 => 'Coordinación regional', 5 => 'Coordinación de zona'][$this->nivel] ?? '';
    }

    /** Unidad raíz del usuario: circ | estado | r:ID | z:ID */
    public function raiz(): string
    {
        if ($this->nivel === 5) return 'z:' . $this->zonaId;
        if ($this->nivel === 4) return 'r:' . $this->regionId;
        if ($this->nivel === 2 && count($this->estados) > 1) return 'circ';
        return 'estado';
    }

    /* ============================================================
       Proceso electoral y ruta
       ============================================================ */
    private function cargarProceso(): void
    {
        $st = $this->pdo->prepare("SELECT * FROM rep_procesos WHERE estado = ? AND activo = 1 ORDER BY id DESC LIMIT 1");
        $st->execute([$this->estado]);
        $p = $st->fetch(PDO::FETCH_ASSOC);
        if (!$p) {
            $this->pdo->prepare("INSERT INTO rep_procesos (estado, fecha_inicio_ruta, inicio_registro, cierre_registro, cierre_sustituciones, fecha_jornada, docs_rep, docs_con, motivos_obs)
                                 VALUES (?, '2026-11-01', '2027-04-15', '2027-05-24', '2027-05-27', '2027-06-06', ?, ?, ?)")
                ->execute([$this->estado, json_encode(self::DOCS_REP, JSON_UNESCAPED_UNICODE), json_encode(self::DOCS_CON, JSON_UNESCAPED_UNICODE), json_encode(self::MOTIVOS, JSON_UNESCAPED_UNICODE)]);
            $pid = (int)$this->pdo->lastInsertId();
            $ins = $this->pdo->prepare("INSERT INTO rep_cortes (proceso_id, fecha, orden, pct_rec, pct_exp, pct_reg, pct_con) VALUES (?,?,?,?,?,?,?)");
            foreach (self::CORTES_DEF as $i => $c) $ins->execute([$pid, $c[0], $i + 1, $c[1], $c[2], $c[3], $c[4]]);
            $st->execute([$this->estado]);
            $p = $st->fetch(PDO::FETCH_ASSOC);
        }
        $p['id'] = (int)$p['id'];
        foreach (['umbral_ruta', 'umbral_atencion'] as $k) $p[$k] = (float)$p[$k];
        foreach (['rc_propietarios', 'rc_suplentes', 'rg_cada', 'rg_por_grupo', 'ruta_version'] as $k) $p[$k] = (int)$p[$k];
        $p['docs_rep'] = json_decode($p['docs_rep'] ?? '', true) ?: self::DOCS_REP;
        $p['docs_con'] = json_decode($p['docs_con'] ?? '', true) ?: self::DOCS_CON;
        $p['motivos_obs'] = json_decode($p['motivos_obs'] ?? '', true) ?: self::MOTIVOS;
        $this->proc = $p;
        $this->cargarCortes();
    }

    public function cargarCortes(): void
    {
        $st = $this->pdo->prepare("SELECT id, fecha, orden, pct_rec, pct_exp, pct_reg, pct_con, cerrado_en, cerrado_por FROM rep_cortes WHERE proceso_id = ? ORDER BY fecha");
        $st->execute([$this->proc['id']]);
        $this->cortes = array_map(function ($c) {
            return ['id' => (int)$c['id'], 'fecha' => $c['fecha'], 'rec' => (float)$c['pct_rec'], 'exp' => (float)$c['pct_exp'], 'reg' => (float)$c['pct_reg'], 'con' => (float)$c['pct_con'], 'cerrado' => $c['cerrado_en'] !== null, 'cerrado_en' => $c['cerrado_en'], 'cerrado_por' => $c['cerrado_por']];
        }, $st->fetchAll(PDO::FETCH_ASSOC));
    }

    public static function dn(string $f): int { return (int)floor(strtotime($f . ' 00:00:00 UTC') / 86400); }

    /** % esperado acumulado de la etapa $k a la fecha $t (interpolación lineal entre cortes) */
    public function esperado(string $k, string $t, ?array $cortes = null): float
    {
        $cortes = $cortes ?? $this->cortes;
        $td = self::dn($t);
        $pt = self::dn($this->proc['fecha_inicio_ruta']);
        $pv = 0.0;
        foreach ($cortes as $c) {
            $ct = self::dn($c['fecha']);
            $cv = (float)$c[$k];
            if ($td <= $ct) {
                if ($td <= $pt) return $pv;
                return $pv + ($cv - $pv) * ($td - $pt) / max(1, $ct - $pt);
            }
            $pt = $ct;
            $pv = $cv;
        }
        return $pv;
    }

    public function periodo(): array
    {
        $h = $this->hoy;
        if ($h > $this->proc['cierre_sustituciones']) return ['k' => 'cerrado', 'msg' => 'El periodo de sustituciones cerró el ' . self::fd($this->proc['cierre_sustituciones']) . '. No se permiten altas, bajas ni sustituciones.'];
        if ($h > $this->proc['cierre_registro']) return ['k' => 'sust', 'msg' => 'El registro cerró el ' . self::fd($this->proc['cierre_registro']) . '. Solo se permiten sustituciones en posiciones vacantes hasta el ' . self::fd($this->proc['cierre_sustituciones']) . '.'];
        return ['k' => 'abierto', 'msg' => ''];
    }

    public static function fd(string $f): string
    {
        $m = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
        $t = strtotime($f);
        return (int)date('j', $t) . ' ' . $m[(int)date('n', $t) - 1] . ' ' . date('Y', $t);
    }

    /* ============================================================
       Estructura territorial (lectura de tablas existentes)
       ============================================================ */
    private function cargarEstructura(): void
    {
        $pdo = $this->pdo;
        $st = $pdo->prepare("SELECT id, nombre, municipios, tipo_integracion, coordinador_id FROM regiones_estatales WHERE estado = ? ORDER BY nombre");
        $st->execute([$this->estado]);
        $regs = $st->fetchAll(PDO::FETCH_ASSOC);
        $ids = [];
        foreach ($regs as $r) {
            $id = (int)$r['id'];
            $ids[] = $id;
            $this->regiones[$id] = [
                'id' => $id, 'nombre' => $r['nombre'], 'municipios' => json_decode($r['municipios'] ?? '[]', true) ?: [],
                'tipo' => $r['tipo_integracion'] ?? 'municipios', 'coord' => '', 'coord_id' => (int)$r['coordinador_id'], 'zonas' => [],
            ];
        }
        if (!$ids) return;
        $in = implode(',', $ids);

        foreach ($pdo->query("SELECT region, nombre FROM coordinador_regional WHERE region IN ($in) AND (estatus IS NULL OR estatus = 'activo') AND COALESCE(es_espejo,0) = 0 ORDER BY id") as $c) {
            $rid = (int)$c['region'];
            if (isset($this->regiones[$rid]) && $this->regiones[$rid]['coord'] === '') $this->regiones[$rid]['coord'] = $c['nombre'];
        }

        $zs = $pdo->query("SELECT id, estructura_id, nombre, clave, secciones, coordinador_id FROM zonas_estatales WHERE estructura_id IN ($in) ORDER BY estructura_id, nombre")->fetchAll(PDO::FETCH_ASSOC);
        $zids = [];
        foreach ($zs as $z) {
            $zid = (int)$z['id'];
            $rid = (int)$z['estructura_id'];
            $secs = [];
            foreach (json_decode($z['secciones'] ?? '[]', true) ?: [] as $s) {
                $s = self::sec(is_array($s) ? ($s['seccion'] ?? $s['nombre_seccion'] ?? '') : $s);
                if ($s === '' || isset($this->secZona[$s])) continue;
                $this->secZona[$s] = $zid;
                $secs[] = $s;
            }
            usort($secs, function ($a, $b) { return (int)$a <=> (int)$b ?: strcmp($a, $b); });
            $this->zonas[$zid] = ['id' => $zid, 'region' => $rid, 'nombre' => $z['nombre'], 'clave' => $z['clave'] ?? '', 'coord' => '', 'coord_id' => (int)($z['coordinador_id'] ?? 0), 'secciones' => $secs];
            $this->regiones[$rid]['zonas'][] = $zid;
            $zids[] = $zid;
        }
        if ($zids) {
            $zin = implode(',', $zids);
            foreach ($pdo->query("SELECT zona, nombre FROM coordinador_zona WHERE zona IN ($zin) AND (estatus IS NULL OR estatus = 'activo') ORDER BY id") as $c) {
                $zid = (int)$c['zona'];
                if (isset($this->zonas[$zid]) && $this->zonas[$zid]['coord'] === '') $this->zonas[$zid]['coord'] = $c['nombre'];
            }
            // Respaldo: zonas_estatales.coordinador_id
            $faltan = [];
            foreach ($this->zonas as $zid => $z) if ($z['coord'] === '' && $z['coord_id']) $faltan[$z['coord_id']][] = $zid;
            if ($faltan) {
                foreach ($pdo->query("SELECT id, nombre FROM coordinador_zona WHERE id IN (" . implode(',', array_keys($faltan)) . ")") as $c) {
                    foreach ($faltan[(int)$c['id']] as $zid) $this->zonas[$zid]['coord'] = $c['nombre'];
                }
            }
        }

        $st = $pdo->prepare("SELECT seccion, COUNT(*) n FROM rep_casillas WHERE proceso_id = ? AND activa = 1 GROUP BY seccion");
        $st->execute([$this->proc['id']]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $c) $this->casSec[self::sec($c['seccion'])] = (int)$c['n'];
    }

    /** Catálogo de secciones del estado: lista nominal, municipio y distritos */
    public function secInfo(): array
    {
        if ($this->secInfo !== null) return $this->secInfo;
        $this->secInfo = [];
        $st = $this->pdo->prepare("SELECT nombre_seccion, municipio, lista_nominal, distrito_local, distrito_federal FROM secciones_electorales WHERE estado = ? AND activo = 1");
        $st->execute([$this->estado]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $this->secInfo[self::sec($r['nombre_seccion'])] = ['ln' => (int)$r['lista_nominal'], 'mun' => $r['municipio'], 'dl' => $r['distrito_local'], 'df' => $r['distrito_federal']];
        }
        if (!$this->secInfo) {
            // Respaldo: catálogo `secciones` (sin lista nominal)
            $st = $this->pdo->prepare("SELECT seccion, municipio_nombre, distrito_local, distrito_federal FROM secciones WHERE estado_nombre = ?");
            $st->execute([$this->estado]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $this->secInfo[self::sec($r['seccion'])] = ['ln' => 0, 'mun' => $r['municipio_nombre'], 'dl' => $r['distrito_local'], 'df' => $r['distrito_federal']];
            }
        }
        return $this->secInfo;
    }

    public function seccionExiste(string $s): bool
    {
        $s = self::sec($s);
        return $s !== '' && (isset($this->secInfo()[$s]) || isset($this->secZona[$s]));
    }

    /* ============================================================
       Unidades y ámbito
       ============================================================ */
    public function zonasAmbito(): array
    {
        if ($this->zonaId) return isset($this->zonas[$this->zonaId]) ? [$this->zonaId] : [];
        if ($this->regionId) return $this->regiones[$this->regionId]['zonas'] ?? [];
        return array_keys($this->zonas);
    }

    public function regionesAmbito(): array
    {
        if ($this->regionId) return isset($this->regiones[$this->regionId]) ? [$this->regionId] : [];
        return array_keys($this->regiones);
    }

    public function enAmbito(string $u): bool
    {
        if ($u === 'circ') return $this->nivel <= 2 && count($this->estados) > 1;
        if ($u === 'estado') return $this->nivel <= 3;
        if (strpos($u, 'r:') === 0) return isset($this->regiones[(int)substr($u, 2)]) && in_array((int)substr($u, 2), $this->regionesAmbito(), true) && $this->nivel <= 4;
        if (strpos($u, 'z:') === 0) return isset($this->zonas[(int)substr($u, 2)]) && in_array((int)substr($u, 2), $this->zonasAmbito(), true);
        return false;
    }

    public function zonaEnAmbito(int $zid): bool { return in_array($zid, $this->zonasAmbito(), true); }
    public function regionEnAmbito(int $rid): bool { return in_array($rid, $this->regionesAmbito(), true); }

    public function padre(string $u): ?string
    {
        if (strpos($u, 'z:') === 0) return 'r:' . $this->zonas[(int)substr($u, 2)]['region'];
        if (strpos($u, 'r:') === 0) return 'estado';
        if ($u === 'estado' && count($this->estados) > 1 && $this->nivel <= 2) return 'circ';
        return null;
    }

    public function ruta(string $u): array
    {
        $r = $this->raiz();
        $p = [$u];
        $c = $u;
        while ($c !== $r && ($c = $this->padre($c))) array_unshift($p, $c);
        return $p;
    }

    public function hijos(string $u): array
    {
        if ($u === 'estado') return array_map(function ($id) { return 'r:' . $id; }, array_keys($this->regiones));
        if (strpos($u, 'r:') === 0) return array_map(function ($id) { return 'z:' . $id; }, $this->regiones[(int)substr($u, 2)]['zonas'] ?? []);
        return [];
    }

    public function nombre(string $u): string
    {
        if ($u === 'circ') return 'Circunscripción';
        if ($u === 'estado') return $this->estado;
        if (strpos($u, 'r:') === 0) return $this->regiones[(int)substr($u, 2)]['nombre'] ?? 'Región';
        if (strpos($u, 'z:') === 0) return $this->zonas[(int)substr($u, 2)]['nombre'] ?? 'Zona';
        return '';
    }

    public function coord(string $u): string
    {
        if (strpos($u, 'r:') === 0) return $this->regiones[(int)substr($u, 2)]['coord'] ?: 'Coordinador por definir';
        if (strpos($u, 'z:') === 0) return $this->zonas[(int)substr($u, 2)]['coord'] ?: 'Coordinador por definir';
        return '';
    }

    /* ============================================================
       Posiciones requeridas y metas
       ============================================================ */
    public function slotsRC(): array
    {
        $s = [];
        for ($i = 1; $i <= $this->proc['rc_propietarios']; $i++) $s[] = 'P' . $i;
        for ($i = 1; $i <= $this->proc['rc_suplentes']; $i++) $s[] = 'S' . $i;
        return $s;
    }

    public function casZona(int $zid): int
    {
        $n = 0;
        foreach ($this->zonas[$zid]['secciones'] ?? [] as $s) $n += $this->casSec[$s] ?? 0;
        return $n;
    }

    private function rgDe(int $cas): int
    {
        return $cas > 0 ? (int)ceil($cas / max(1, $this->proc['rg_cada'])) * $this->proc['rg_por_grupo'] : 0;
    }

    /** RG requeridos (posiciones) de la zona: siempre se redondea por zona para crear los espacios */
    public function rgZona(int $zid): int { return $this->rgDe($this->casZona($zid)); }

    /** Requerido de una región según el ámbito de redondeo configurado */
    public function requeridoRegion(int $rid): array
    {
        $cas = 0;
        $rgZ = 0;
        $porMun = [];
        $info = null;
        foreach ($this->regiones[$rid]['zonas'] ?? [] as $zid) {
            $cz = $this->casZona($zid);
            $cas += $cz;
            $rgZ += $this->rgDe($cz);
            if ($this->proc['rg_ambito'] === 'municipio') {
                $info = $info ?? $this->secInfo();
                foreach ($this->zonas[$zid]['secciones'] as $s) {
                    $m = $info[$s]['mun'] ?? 'Sin municipio';
                    $porMun[$m] = ($porMun[$m] ?? 0) + ($this->casSec[$s] ?? 0);
                }
            }
        }
        if ($this->proc['rg_ambito'] === 'region') $rg = $this->rgDe($cas);
        elseif ($this->proc['rg_ambito'] === 'municipio') { $rg = 0; foreach ($porMun as $c) $rg += $this->rgDe($c); }
        else $rg = $rgZ;
        $rc = $cas * ($this->proc['rc_propietarios'] + $this->proc['rc_suplentes']);
        return ['cas' => $cas, 'rc' => $rc, 'rg' => $rg, 'req' => $rc + $rg];
    }

    public function metasRaw(): array
    {
        if ($this->metas !== null) return $this->metas;
        $st = $this->pdo->prepare("SELECT unidad_tipo, unidad_id, meta_rep, meta_con, version FROM rep_metas WHERE proceso_id = ?");
        $st->execute([$this->proc['id']]);
        $this->metas = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $m) $this->metas[$m['unidad_tipo'] . ':' . (int)$m['unidad_id']] = ['p' => (int)$m['meta_rep'], 'con' => (int)$m['meta_con'], 'v' => (int)$m['version']];
        return $this->metas;
    }

    /** Meta de una unidad: p = RG+RC, con = consejeros; si no se ha publicado, usa lo requerido */
    public function meta(string $u): array
    {
        $M = $this->metasRaw();
        if (strpos($u, 'z:') === 0) {
            $zid = (int)substr($u, 2);
            $cas = $this->casZona($zid);
            $rc = $cas * ($this->proc['rc_propietarios'] + $this->proc['rc_suplentes']);
            $rg = $this->rgZona($zid);
            $m = $M['zona:' . $zid] ?? null;
            return ['p' => $m ? $m['p'] : $rc + $rg, 'con' => 0, 'cas' => $cas, 'rc' => $rc, 'rg' => $rg, 'req' => $rc + $rg, 'publicada' => (bool)$m];
        }
        if (strpos($u, 'r:') === 0) {
            $rid = (int)substr($u, 2);
            $q = $this->requeridoRegion($rid);
            $m = $M['region:' . $rid] ?? null;
            return ['p' => $m ? $m['p'] : $q['req'], 'con' => $m ? $m['con'] : 0, 'cas' => $q['cas'], 'rc' => $q['rc'], 'rg' => $q['rg'], 'req' => $q['req'], 'publicada' => (bool)$m];
        }
        if ($u === 'estado') {
            $o = ['p' => 0, 'con' => 0, 'cas' => 0, 'rc' => 0, 'rg' => 0, 'req' => 0];
            foreach (array_keys($this->regiones) as $rid) {
                $r = $this->meta('r:' . $rid);
                foreach ($o as $k => $_) $o[$k] += $r[$k];
            }
            $m = $M['estado:0'] ?? null;
            if ($m) { $o['p'] = $m['p']; $o['con'] = $m['con']; }
            $o['publicada'] = (bool)$m;
            return $o;
        }
        return ['p' => 0, 'con' => 0, 'cas' => 0, 'rc' => 0, 'rg' => 0, 'req' => 0, 'publicada' => false];
    }

    /* ============================================================
       Avance real (calculado, nunca capturado)
       ============================================================ */
    public function conteos(string $t): array
    {
        if (isset($this->cacheReal[$t])) return $this->cacheReal[$t];
        $tt = $t . ' 23:59:59';
        $st = $this->pdo->prepare("SELECT region_id, zona_id, tipo_rep,
                SUM(f_reclutado <= ?) rec,
                SUM(f_completo IS NOT NULL AND f_completo <= ?) exp,
                SUM(f_registrado IS NOT NULL AND f_registrado <= ?) reg
            FROM rep_asignaciones WHERE proceso_id = ? AND (baja_en IS NULL OR baja_en > ?)
            GROUP BY region_id, zona_id, tipo_rep");
        $st->execute([$tt, $tt, $tt, $this->proc['id'], $tt]);
        $z = [];
        $r = [];
        $e = ['rec' => 0, 'exp' => 0, 'reg' => 0, 'con' => 0];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rid = (int)$row['region_id'];
            if (!isset($r[$rid])) $r[$rid] = ['rec' => 0, 'exp' => 0, 'reg' => 0, 'con' => 0];
            if ($row['tipo_rep'] === 'CONSEJERO') {
                $r[$rid]['con'] += (int)$row['rec'];
                $e['con'] += (int)$row['rec'];
                continue;
            }
            $zid = (int)$row['zona_id'];
            if (!isset($z[$zid])) $z[$zid] = ['rec' => 0, 'exp' => 0, 'reg' => 0, 'con' => 0];
            foreach (['rec', 'exp', 'reg'] as $k) { $z[$zid][$k] += (int)$row[$k]; $r[$rid][$k] += (int)$row[$k]; $e[$k] += (int)$row[$k]; }
        }
        return $this->cacheReal[$t] = ['z' => $z, 'r' => $r, 'e' => $e];
    }

    public function real(string $u, string $t): array
    {
        $c = $this->conteos($t);
        $zero = ['rec' => 0, 'exp' => 0, 'reg' => 0, 'con' => 0];
        if ($u === 'estado') return $c['e'];
        if (strpos($u, 'r:') === 0) return $c['r'][(int)substr($u, 2)] ?? $zero;
        if (strpos($u, 'z:') === 0) return $c['z'][(int)substr($u, 2)] ?? $zero;
        return $zero;
    }

    public function semEtapa(int $real, int $meta, string $k, string $t): array
    {
        if (!$meta) return ['k' => 'na', 'ex' => 0, 'c' => null];
        $ex = (int)round($meta * $this->esperado($k, $t) / 100);
        if ($ex <= 0) return ['k' => $real > 0 ? 'ok' : 'idle', 'ex' => $ex, 'c' => null];
        $c = $real / $ex * 100;
        return ['k' => $c >= $this->proc['umbral_ruta'] ? 'ok' : ($c >= $this->proc['umbral_atencion'] ? 'warn' : 'bad'), 'ex' => $ex, 'c' => round($c, 1)];
    }

    /** El estado de una unidad es el de su etapa más atrasada */
    public function semUnidad(array $m, array $r, string $t): string
    {
        $ord = ['bad' => 3, 'warn' => 2, 'ok' => 1, 'idle' => 0, 'na' => -1];
        $w = 'na';
        foreach (self::ETAPAS as $s) {
            $k = $this->semEtapa((int)$r[$s['k']], $s['k'] === 'con' ? (int)$m['con'] : (int)$m['p'], $s['k'], $t)['k'];
            if ($ord[$k] > $ord[$w]) $w = $k;
        }
        return $w === 'na' ? 'idle' : $w;
    }

    /* ============================================================
       Cobertura de posiciones
       ============================================================ */
    public function cobertura(): array
    {
        if ($this->cacheCov !== null) return $this->cacheCov;
        $sec = [];
        $cas = [];
        $st = $this->pdo->prepare("SELECT c.id, c.seccion FROM rep_asignaciones a JOIN rep_casillas c ON c.id = a.casilla_id WHERE a.proceso_id = ? AND a.vigente = 1 AND a.casilla_id IS NOT NULL");
        $st->execute([$this->proc['id']]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $s = self::sec($r['seccion']);
            $sec[$s] = ($sec[$s] ?? 0) + 1;
            $cas[(int)$r['id']] = ($cas[(int)$r['id']] ?? 0) + 1;
        }
        $z = [];
        foreach ($sec as $s => $n) if (isset($this->secZona[$s])) $z[$this->secZona[$s]] = ($z[$this->secZona[$s]] ?? 0) + $n;
        $rg = [];
        $st = $this->pdo->prepare("SELECT zona_id, COUNT(*) n FROM rep_asignaciones WHERE proceso_id = ? AND vigente = 1 AND tipo_rep = 'RG' AND posicion_key IS NOT NULL GROUP BY zona_id");
        $st->execute([$this->proc['id']]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $rg[(int)$r['zona_id']] = (int)$r['n'];
        return $this->cacheCov = ['s' => $sec, 'c' => $cas, 'z' => $z, 'rg' => $rg];
    }

    /* ============================================================
       Posiciones
       ============================================================ */
    public function slotLabel(string $slot): string
    {
        if (preg_match('/^P(\d+)$/', $slot, $m)) return 'Propietario ' . $m[1];
        if (preg_match('/^S(\d+)$/', $slot, $m)) return 'Suplente ' . $m[1];
        return $slot;
    }

    /** Valida una llave de posición y devuelve su descripción */
    public function posicion(?string $key): ?array
    {
        if (!$key) return null;
        if (preg_match('/^C(\d+):([PS]\d+)$/', $key, $m)) {
            $st = $this->pdo->prepare("SELECT id, seccion, tipo, activa FROM rep_casillas WHERE id = ? AND proceso_id = ?");
            $st->execute([(int)$m[1], $this->proc['id']]);
            $c = $st->fetch(PDO::FETCH_ASSOC);
            if (!$c) return null;
            $s = self::sec($c['seccion']);
            $zid = $this->secZona[$s] ?? 0;
            return ['key' => $key, 'tipo' => 'RC', 'casilla_id' => (int)$c['id'], 'activa' => (int)$c['activa'] === 1, 'seccion' => $s, 'casilla' => $c['tipo'], 'slot' => $m[2],
                'valida' => in_array($m[2], $this->slotsRC(), true), 'zona_id' => $zid, 'region_id' => $zid ? $this->zonas[$zid]['region'] : 0,
                'label' => 'Sección ' . $s . ' · Casilla ' . $c['tipo'] . ' · ' . $this->slotLabel($m[2])];
        }
        if (preg_match('/^Z(\d+):RG(\d+)$/', $key, $m)) {
            $zid = (int)$m[1];
            if (!isset($this->zonas[$zid])) return null;
            return ['key' => $key, 'tipo' => 'RG', 'casilla_id' => null, 'activa' => true, 'slot' => 'RG' . $m[2], 'valida' => (int)$m[2] >= 1 && (int)$m[2] <= $this->rgZona($zid),
                'zona_id' => $zid, 'region_id' => $this->zonas[$zid]['region'], 'label' => 'RG ' . $m[2] . ' · ' . $this->zonas[$zid]['nombre']];
        }
        if (preg_match('/^R(\d+):CON(\d+)$/', $key, $m)) {
            $rid = (int)$m[1];
            if (!isset($this->regiones[$rid])) return null;
            $n = (int)$m[2];
            return ['key' => $key, 'tipo' => 'CONSEJERO', 'casilla_id' => null, 'activa' => true, 'slot' => 'CON' . $n, 'valida' => $n >= 1 && $n <= $this->meta('r:' . $rid)['con'],
                'zona_id' => null, 'region_id' => $rid, 'label' => 'Consejero ' . (int)ceil($n / 2) . ' · ' . ($n % 2 ? 'Propietario' : 'Suplente') . ' · ' . $this->regiones[$rid]['nombre']];
        }
        return null;
    }

    /** Etiquetas de muchas posiciones de una vez (para listados) */
    public function etiquetas(array $keys): array
    {
        $out = [];
        $cids = [];
        foreach ($keys as $k) if ($k && preg_match('/^C(\d+):/', $k, $m)) $cids[(int)$m[1]] = true;
        $cas = [];
        if ($cids) {
            foreach ($this->pdo->query("SELECT id, seccion, tipo FROM rep_casillas WHERE id IN (" . implode(',', array_keys($cids)) . ")") as $c) $cas[(int)$c['id']] = $c;
        }
        foreach ($keys as $k) {
            if (!$k) continue;
            if (preg_match('/^C(\d+):([PS]\d+)$/', $k, $m)) {
                $c = $cas[(int)$m[1]] ?? null;
                $out[$k] = $c ? 'Sección ' . self::sec($c['seccion']) . ' · Casilla ' . $c['tipo'] . ' · ' . $this->slotLabel($m[2]) : $k;
            } elseif (preg_match('/^Z(\d+):RG(\d+)$/', $k, $m)) {
                $out[$k] = 'RG ' . $m[2] . ' · ' . ($this->zonas[(int)$m[1]]['nombre'] ?? 'Zona');
            } elseif (preg_match('/^R(\d+):CON(\d+)$/', $k, $m)) {
                $n = (int)$m[2];
                $out[$k] = 'Consejero ' . (int)ceil($n / 2) . ' · ' . ($n % 2 ? 'Propietario' : 'Suplente');
            }
        }
        return $out;
    }

    /* ============================================================
       Identificador oficial: hash (anti-duplicado) y cifrado
       ============================================================ */
    private static $clave = null;

    private static function clave(): string
    {
        if (self::$clave !== null) return self::$clave;
        $dir = defined('PRIVATE_PATH') ? PRIVATE_PATH . '/config' : dirname(__DIR__) . '/config';
        $f = $dir . '/representantes_clave.php';
        if (!file_exists($f)) {
            $k = bin2hex(random_bytes(32));
            if (@file_put_contents($f, "<?php\n// Clave del módulo Representantes. NO compartir, NO subir a GitHub y NO cambiar una vez que haya registros.\nreturn '" . $k . "';\n", LOCK_EX) === false) {
                throw new RepError('No se pudo crear la clave de cifrado en app_private/config. Revisa permisos de escritura.', 500);
            }
            @chmod($f, 0600);
        }
        $k = require $f;
        if (!is_string($k) || strlen($k) < 32) throw new RepError('Clave de cifrado inválida.', 500);
        return self::$clave = $k;
    }

    public static function curpHash(string $curp): string { return hash_hmac('sha256', strtoupper(trim($curp)), self::clave()); }

    public static function curpCifrar(string $curp): string
    {
        $key = hash('sha256', self::clave() . '|enc', true);
        $iv = random_bytes(12);
        $tag = '';
        $ct = openssl_encrypt(strtoupper(trim($curp)), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        return base64_encode($iv . $tag . $ct);
    }

    public static function curpDescifrar(string $b64): string
    {
        $raw = base64_decode($b64);
        $key = hash('sha256', self::clave() . '|enc', true);
        $pt = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        return $pt === false ? '' : $pt;
    }

    public static function curpMask(string $c): string { return substr($c, 0, 4) . str_repeat('•', 10) . substr($c, -4); }

    /* ============================================================
       Bitácora (solo inserción)
       ============================================================ */
    public function log(string $accion, string $entidad, string $detalle = '', ?int $regionId = null, ?int $zonaId = null): void
    {
        $this->pdo->prepare("INSERT INTO rep_bitacora (proceso_id, region_id, zona_id, usuario_id, usuario_nombre, rol, accion, entidad, detalle, ip) VALUES (?,?,?,?,?,?,?,?,?,?)")
            ->execute([$this->proc['id'], $regionId, $zonaId, $this->userId, $this->userName, $this->rol, $accion, mb_substr($entidad, 0, 200), $detalle, $_SERVER['REMOTE_ADDR'] ?? null]);
    }

    /* ============================================================
       Corte: foto inmutable de metas y avance (RN-08)
       ============================================================ */
    public function fotoCorte(string $t): array
    {
        $u = ['estado' => ['m' => $this->metaCorta('estado'), 'r' => $this->real('estado', $t)]];
        foreach (array_keys($this->regiones) as $rid) $u['r:' . $rid] = ['m' => $this->metaCorta('r:' . $rid), 'r' => $this->real('r:' . $rid, $t)];
        foreach (array_keys($this->zonas) as $zid) $u['z:' . $zid] = ['m' => $this->metaCorta('z:' . $zid), 'r' => $this->real('z:' . $zid, $t)];
        return ['fecha' => $t, 'cortes' => $this->cortes, 'umbral' => [$this->proc['umbral_ruta'], $this->proc['umbral_atencion']], 'u' => $u];
    }

    private function metaCorta(string $u): array
    {
        $m = $this->meta($u);
        return ['p' => $m['p'], 'con' => $m['con'], 'cas' => $m['cas'], 'rc' => $m['rc'], 'rg' => $m['rg']];
    }
}

class RepError extends Exception
{
    public $http;
    public function __construct(string $msg, int $http = 400)
    {
        parent::__construct($msg);
        $this->http = $http;
    }
}
