<?php
if (file_exists(__DIR__ . '/../../includes/bootstrap.php')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
} else { session_start(); }

$rol         = $_SESSION['user_role']   ?? '';
$user_estado = $_SESSION['user_estado'] ?? '';
$user_region = $_SESSION['user_region'] ?? '';

$permitidos = ['coordinador_regional', 'regional'];
if (!in_array($rol, $permitidos)) { header('Location: /public/index.php'); exit(); }

$region_nombre = '—';
$data = [
    'vision'      => '',
    'mision'      => '',
    'objetivos'   => [],
    'estrategias' => [],
    'metas'       => [],
    'cronograma'  => [],
];
$stats = [
    'total_objetivos'   => 0,
    'total_estrategias' => 0,
    'total_metas'       => 0,
];

try {
    $dbCfg = __DIR__ . '/../../includes/database.php';
    $cfg = file_exists($dbCfg) ? require $dbCfg : [
        'host' => 'localhost', 'database' => 'u881824383_pverde',
        'username' => 'u881824383_proyverde', 'password' => 'PProyectoverde8#',
        'charset' => 'utf8mb4',
        'options' => [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    ];
    $dsn = "mysql:host={$cfg['host']};dbname={$cfg['database']};charset={$cfg['charset']}";
    $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], $cfg['options'] ?? [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // Región asignada al coordinador desde regiones_estatales
    $user_id = $_SESSION['user_id'] ?? 0;
    $st = $pdo->prepare("SELECT nombre FROM regiones_estatales WHERE coordinador_id = ? AND estado = ? LIMIT 1");
    $st->execute([$user_id, $user_estado]);
    $r = $st->fetch(); if ($r) $region_nombre = $r['nombre'];

    try {
        $st = $pdo->prepare("SELECT vision, mision FROM geo_vision_mision WHERE estado = ? LIMIT 1");
        $st->execute([$user_estado]);
        $vm = $st->fetch();
        if ($vm) { $data['vision'] = $vm['vision']; $data['mision'] = $vm['mision']; }
    } catch (Exception $e) {}

    try {
        $st = $pdo->prepare("SELECT * FROM geo_objetivos WHERE estado = ? AND activo = 1 ORDER BY id DESC");
        $st->execute([$user_estado]);
        $data['objetivos'] = $st->fetchAll();
        $stats['total_objetivos'] = count($data['objetivos']);
    } catch (Exception $e) {}

    try {
        $st = $pdo->prepare("SELECT * FROM geo_estrategias WHERE estado = ? AND activo = 1 ORDER BY id DESC");
        $st->execute([$user_estado]);
        $data['estrategias'] = $st->fetchAll();
        $stats['total_estrategias'] = count($data['estrategias']);
    } catch (Exception $e) {}

    try {
        $st = $pdo->prepare("SELECT * FROM geo_metas WHERE estado = ? AND activo = 1 ORDER BY orden ASC, id DESC");
        $st->execute([$user_estado]);
        $data['metas'] = $st->fetchAll();
        $stats['total_metas'] = count($data['metas']);
    } catch (Exception $e) {}

    try {
        $st = $pdo->prepare("SELECT * FROM geo_cronograma WHERE estado = ? AND activo = 1 ORDER BY fecha_orden ASC");
        $st->execute([$user_estado]);
        $data['cronograma'] = $st->fetchAll();
    } catch (Exception $e) {}

} catch (PDOException $e) { $error_db = $e->getMessage(); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión Estratégica - <?= htmlspecialchars($user_estado) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* --- VARIABLES --- */
        :root {
            --primary: #2d7a3e;
            --primary-dark: #1f5a2d;
            --dark: #1a1a1a;
            --light: #f0f7f0;
            --white: #ffffff;
            --gray-100: #f8f9fa;
            --gray-200: #e9ecef;
            --text-muted: #6c757d;
            --shadow-sm: 0 10px 30px rgba(0,0,0,0.05);
            --shadow-hover: 0 20px 40px rgba(45, 122, 62, 0.15);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; background: var(--light); color: var(--dark); overflow-x: hidden; }

        /* --- LAYOUT --- */
        .container {
            max-width: 100%; margin: 0; background: var(--white);
            min-height: 100vh; position: relative;
            padding-left: 330px; padding-right: 50px; padding-bottom: 40px;
            transition: padding-left 0.3s ease;
        }
        .design-text {
            position: fixed; left: 290px; top: 50%;
            writing-mode: vertical-rl; font-size: 13px; font-weight: 500;
            color: var(--primary); opacity: 0.3; letter-spacing: 2px;
            z-index: 0; pointer-events: none;
        }

        /* --- HEADER --- */
        .page-header {
            padding: 50px 0 40px 0; position: relative; z-index: 2;
            display: flex; justify-content: space-between; align-items: flex-end;
        }
        .ai-badge {
            color: var(--primary); font-size: 13px; font-weight: 800;
            letter-spacing: 4px; margin-bottom: 15px; display: inline-block;
            text-transform: uppercase; background: rgba(45,122,62,0.1);
            padding: 5px 15px; border-radius: 20px;
        }
        .main-title { font-size: 54px; font-weight: 900; line-height: 1; margin-bottom: 15px; color: var(--dark); }
        .description { color: #666; font-size: 16px; line-height: 1.6; max-width: 600px; }
        .section-title { font-size: 20px; font-weight: 800; margin-bottom: 20px; color: var(--dark); display: flex; align-items: center; gap: 10px; }

        /* --- READ-ONLY BADGE --- */
        .read-only-badge {
            background: #fef3c7; color: #92400e;
            padding: 10px 18px; border-radius: 12px;
            font-size: 13px; font-weight: 700;
            display: inline-flex; align-items: center; gap: 8px;
            border: 1px solid #fde68a;
        }

        /* --- STAT CARDS --- */
        .grid-overview { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 40px; }

        .stat-card {
            background: var(--white); border: 1px solid var(--gray-200);
            padding: 25px; border-radius: 20px; text-align: left;
            box-shadow: var(--shadow-sm); position: relative; overflow: hidden;
            display: flex; flex-direction: column; justify-content: space-between;
            height: 140px; transition: transform 0.3s ease;
        }
        .stat-card:hover { transform: translateY(-5px); border-color: var(--primary); }
        .stat-card > i { position: absolute; right: 20px; top: 20px; font-size: 40px; color: var(--gray-100); }
        .stat-card .number { font-size: 42px; font-weight: 900; color: var(--dark); line-height: 1; }
        .stat-card .label { font-size: 12px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-top: 5px; }

        /* --- TABS --- */
        .tabs-container {
            background: var(--gray-100); border-radius: 16px; padding: 5px;
            display: inline-flex; gap: 5px; margin-bottom: 30px;
            border: 1px solid var(--gray-200);
        }
        .tab-btn {
            padding: 10px 20px; border: none; background: transparent;
            border-radius: 12px; font-weight: 600; color: #666;
            cursor: pointer; transition: 0.3s; font-size: 13px;
        }
        .tab-btn.active { background: var(--white); color: var(--primary); box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        /* --- CONTENT CARDS (igual al circ) --- */
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; }
        .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 25px; }

        .content-card {
            background: var(--white); border-radius: 20px; padding: 30px;
            border: 1px solid var(--gray-200); box-shadow: var(--shadow-sm);
            position: relative; transition: all 0.3s ease;
            overflow: visible; display: flex; flex-direction: column; height: 100%;
        }
        .content-card:hover { transform: translateY(-5px); border-color: var(--primary); box-shadow: var(--shadow-hover); }

        .card-number-watermark {
            position: absolute; bottom: -15px; right: 10px; font-size: 80px;
            font-weight: 900; color: var(--primary); opacity: 0.08;
            z-index: 0; line-height: 1; pointer-events: none; transition: all 0.3s ease;
        }
        .content-card:hover .card-number-watermark { opacity: 0.15; transform: scale(1.1) rotate(-5deg); }

        .card-icon {
            width: 50px; height: 50px; background: var(--light); color: var(--primary);
            border-radius: 15px; display: flex; align-items: center; justify-content: center;
            font-size: 20px; margin-bottom: 20px; position: relative; z-index: 2;
        }

        /* --- TIMELINE --- */
        .timeline-container { position: relative; padding-left: 30px; border-left: 2px solid var(--gray-200); margin-left: 10px; }
        .timeline-item { position: relative; margin-bottom: 40px; padding-left: 20px; }
        .timeline-dot { position: absolute; left: -36px; top: 0; width: 14px; height: 14px; background: var(--white); border: 4px solid var(--primary); border-radius: 50%; }
        .timeline-date { font-size: 11px; font-weight: 800; color: var(--primary); background: var(--light); padding: 4px 10px; border-radius: 20px; display: inline-block; margin-bottom: 8px; }

        /* --- ANIMATIONS --- */
        .animate-up { animation: fadeInUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; opacity: 0; transform: translateY(20px); }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .animate-delay-1 { animation-delay: 0.1s; }
        .animate-delay-2 { animation-delay: 0.2s; }
        .animate-delay-3 { animation-delay: 0.3s; }

        @media (max-width: 1024px) {
            .container { padding-left: 20px; padding-right: 20px; }
            .grid-2, .grid-3 { grid-template-columns: 1fr; }
            .design-text { display: none; }
            .page-header { flex-direction: column; align-items: flex-start; gap: 20px; }
        }
        @media (max-width: 768px) {
            .tabs-container { flex-wrap: wrap; }
        }
    </style>
</head>
<body>
    <?php if (file_exists(__DIR__ . '/../../includes/components/sidebar_regional.php'))
        include __DIR__ . '/../../includes/components/sidebar_regional.php'; ?>

    <div class="design-text">PLANIFICACIÓN</div>

    <div class="container">

        <!-- ── HEADER ── -->
        <div class="page-header animate-up">
            <div>
                <div class="ai-badge"><?= htmlspecialchars($user_estado) ?> · REGIÓN <?= htmlspecialchars(strtoupper($region_nombre)) ?></div>
                <h1 class="main-title">Modelo de<br>Gestión.</h1>
                <p class="description">Planificación estratégica: Visión, Misión, objetivos, metas, estrategias y cronograma.</p>
            </div>
            <div>
                <span class="read-only-badge"><i class="fas fa-eye"></i> Solo lectura</span>
            </div>
        </div>

        <?php if (isset($error_db)): ?>
        <div style="background:#fee2e2;color:#991b1b;padding:14px 18px;border-radius:12px;margin-bottom:20px;font-size:14px;">
            <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error_db) ?>
        </div>
        <?php endif; ?>

        <!-- ── STAT CARDS ── -->
        <div class="grid-overview animate-up animate-delay-1">
            <div class="stat-card">
                <i class="fas fa-bullseye"></i>
                <span class="number" data-target="<?= $stats['total_objetivos'] ?>">0</span>
                <span class="label">Objetivos Activos</span>
            </div>
            <div class="stat-card">
                <i class="fas fa-flag"></i>
                <span class="number" data-target="<?= $stats['total_metas'] ?>">0</span>
                <span class="label">Metas Estatales</span>
            </div>
            <div class="stat-card">
                <i class="fas fa-chess-knight"></i>
                <span class="number" data-target="<?= $stats['total_estrategias'] ?>">0</span>
                <span class="label">Estrategias</span>
            </div>
            <div class="stat-card">
                <i class="fas fa-calendar-check"></i>
                <span class="number" data-target="<?= count($data['cronograma']) ?>">0</span>
                <span class="label">Acciones</span>
            </div>
        </div>

        <!-- ── TABS ── -->
        <div class="tabs-container animate-up animate-delay-2">
            <button class="tab-btn active" onclick="showTab('vision')">Visión y Misión</button>
            <button class="tab-btn" onclick="showTab('objetivos')">Objetivos</button>
            <button class="tab-btn" onclick="showTab('metas')">Metas</button>
            <button class="tab-btn" onclick="showTab('estrategias')">Estrategias</button>
            <button class="tab-btn" onclick="showTab('cronograma')">Acciones</button>
        </div>

        <!-- ── TAB: VISIÓN Y MISIÓN ── -->
        <div id="tab-vision" class="tab-content active animate-up animate-delay-3">
            <h3 class="section-title"><i class="fas fa-compass"></i> Visión y Misión</h3>
            <div class="grid-2">
                <div class="content-card">
                    <div class="card-icon"><i class="fas fa-eye"></i></div>
                    <h4 style="font-size:20px;font-weight:800;margin-bottom:15px;position:relative;z-index:2;">Nuestra Visión</h4>
                    <p style="color:#666;font-size:15px;line-height:1.6;flex-grow:1;position:relative;z-index:2;">
                        <?php echo !empty($data['vision'])
                            ? nl2br(htmlspecialchars($data['vision']))
                            : '<em style="color:var(--text-muted)">Sin visión definida.</em>'; ?>
                    </p>
                </div>
                <div class="content-card">
                    <div class="card-icon"><i class="fas fa-bullseye"></i></div>
                    <h4 style="font-size:20px;font-weight:800;margin-bottom:15px;position:relative;z-index:2;">Nuestra Misión</h4>
                    <p style="color:#666;font-size:15px;line-height:1.6;flex-grow:1;position:relative;z-index:2;">
                        <?php echo !empty($data['mision'])
                            ? nl2br(htmlspecialchars($data['mision']))
                            : '<em style="color:var(--text-muted)">Sin misión definida.</em>'; ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- ── TAB: OBJETIVOS ── -->
        <div id="tab-objetivos" class="tab-content animate-up animate-delay-3">
            <h3 class="section-title"><i class="fas fa-bullseye"></i> Objetivos Principales</h3>
            <div class="grid-3">
                <?php if (empty($data['objetivos'])): ?>
                    <div style="color:#999;font-style:italic;padding:20px;grid-column:1/-1;">No hay objetivos registrados.</div>
                <?php else: ?>
                    <?php $i = 1; foreach ($data['objetivos'] as $obj): ?>
                    <div class="content-card">
                        <div class="card-number-watermark"><?= sprintf("%02d", $i++) ?></div>
                        <div class="card-icon"><i class="fas fa-bullseye"></i></div>
                        <h4 style="font-size:16px;font-weight:800;margin-bottom:10px;position:relative;z-index:2;">
                            <?= htmlspecialchars($obj['titulo'] ?? $obj['nombre'] ?? '') ?>
                        </h4>
                        <?php if (!empty($obj['descripcion'])): ?>
                        <p style="color:#666;font-size:14px;line-height:1.5;flex-grow:1;position:relative;z-index:2;">
                            <?= htmlspecialchars($obj['descripcion']) ?>
                        </p>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── TAB: METAS ── -->
        <div id="tab-metas" class="tab-content animate-up animate-delay-3">
            <h3 class="section-title"><i class="fas fa-flag"></i> Metas a alcanzar</h3>
            <div class="grid-3">
                <?php if (empty($data['metas'])): ?>
                    <div style="color:#999;font-style:italic;padding:20px;grid-column:1/-1;">No hay metas registradas.</div>
                <?php else: ?>
                    <?php $i = 1; foreach ($data['metas'] as $meta): ?>
                    <div class="content-card">
                        <div class="card-number-watermark"><?= sprintf("%02d", $i++) ?></div>
                        <div class="card-icon"><i class="fas fa-flag"></i></div>
                        <h4 style="font-size:16px;font-weight:800;margin-bottom:10px;position:relative;z-index:2;">
                            <?= htmlspecialchars($meta['titulo'] ?? '') ?>
                        </h4>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── TAB: ESTRATEGIAS ── -->
        <div id="tab-estrategias" class="tab-content animate-up animate-delay-3">
            <h3 class="section-title"><i class="fas fa-chess-board"></i> Estrategias de Implementación</h3>
            <div class="grid-3">
                <?php if (empty($data['estrategias'])): ?>
                    <div style="color:#999;font-style:italic;padding:20px;grid-column:1/-1;">No hay estrategias registradas.</div>
                <?php else: ?>
                    <?php $i = 1; foreach ($data['estrategias'] as $est): ?>
                    <div class="content-card">
                        <div class="card-number-watermark"><?= sprintf("%02d", $i++) ?></div>
                        <div class="card-icon"><i class="fas fa-chess-knight"></i></div>
                        <h4 style="font-size:16px;font-weight:800;margin-bottom:10px;margin-top:10px;position:relative;z-index:2;">
                            <?= htmlspecialchars($est['titulo'] ?? $est['nombre'] ?? '') ?>
                        </h4>
                        <?php if (!empty($est['descripcion'])): ?>
                        <p style="color:#666;font-size:13px;flex-grow:1;position:relative;z-index:2;">
                            <?= htmlspecialchars($est['descripcion']) ?>
                        </p>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── TAB: CRONOGRAMA ── -->
        <div id="tab-cronograma" class="tab-content animate-up animate-delay-3">
            <h3 class="section-title"><i class="fas fa-clock"></i> Línea de Tiempo</h3>
            <div class="content-card">
                <div class="timeline-container">
                    <?php if (empty($data['cronograma'])): ?>
                        <p style="color:#999;font-style:italic;padding:20px 0;">No hay actividades registradas en el cronograma.</p>
                    <?php else: foreach ($data['cronograma'] as $evento): ?>
                    <div class="timeline-item">
                        <div class="timeline-dot"></div>
                        <span class="timeline-date">
                            <?= htmlspecialchars($evento['fecha_visual'] ?? $evento['fecha_texto'] ?? $evento['fecha_orden'] ?? '') ?>
                        </span>
                        <h4 style="font-weight:800;margin-bottom:5px;">
                            <?= htmlspecialchars($evento['titulo'] ?? '') ?>
                        </h4>
                        <?php if (!empty($evento['descripcion'])): ?>
                        <p style="font-size:14px;color:#666;"><?= htmlspecialchars($evento['descripcion']) ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>

    </div><!-- /container -->

    <script>
        // ── TABS ──────────────────────────────────────────
        function showTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));

            const target = document.getElementById('tab-' + tabName);
            if (target) target.classList.add('active');

            const buttons = document.querySelectorAll('.tab-btn');
            const map = { vision: 0, objetivos: 1, metas: 2, estrategias: 3, cronograma: 4 };
            if (map[tabName] !== undefined && buttons[map[tabName]]) {
                buttons[map[tabName]].classList.add('active');
            }
        }

        // ── ANIMACIÓN DE NÚMEROS ──────────────────────────
        function animateNumber(el, target) {
            let current = 0;
            const inc = target / 30;
            if (target <= 0) { el.innerText = target; return; }
            const timer = setInterval(() => {
                current += inc;
                if (current >= target) {
                    el.innerText = target;
                    clearInterval(timer);
                } else {
                    el.innerText = Math.ceil(current);
                }
            }, 30);
        }

        window.addEventListener('load', () => {
            document.querySelectorAll('.number').forEach(el => {
                const target = parseInt(el.getAttribute('data-target'));
                animateNumber(el, target);
            });
        });
    </script>
</body>
</html>