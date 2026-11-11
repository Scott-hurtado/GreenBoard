<?php
/**
 * Shell común de las pantallas del módulo «Representantes».
 * Valida sesión, incluye el sidebar del rol y carga CSS/JS del módulo.
 */
require_once __DIR__ . '/../../includes/bootstrap.php';
class_exists('RepModulo');

$REP_ROL = strtolower(trim($_SESSION['user_role'] ?? ''));
$REP_NIVEL = RepModulo::nivelDe($REP_ROL);
if ($REP_NIVEL > 5) {
    header('Location: /public/index.php');
    exit();
}

function rep_head(string $titulo, string $pagina): void
{
    global $REP_NIVEL;
    $v = '1.0.0';
    ?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($titulo) ?> · Representantes · GreenBoard</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;500;600;700&family=Source+Serif+4:opsz,wght@8..60,600;8..60,700&display=swap">
<link rel="stylesheet" href="/App/representantes/assets/rep.css?v=<?= $v ?>">
</head>
<body class="rep-body">
<?php
    $sb = __DIR__ . '/../../includes/components/';
    if ($REP_NIVEL === 1) include $sb . 'sidebar_admin.php';
    elseif ($REP_NIVEL === 2) include $sb . 'sidebar_circunscripcion.php';
    elseif ($REP_NIVEL === 3) include $sb . 'sidebar_ce.php';
    elseif ($REP_NIVEL === 4) include $sb . 'sidebar_regional.php';
    else include $sb . 'sidebar_cz.php';
    ?>
<div class="container rep" id="rep" data-page="<?= htmlspecialchars($pagina) ?>">
  <div class="topbar">
    <div class="tb-l"><span class="eyebrow">Representantes</span><h2>Integración y Seguimiento de Representantes Electorales</h2></div>
    <div class="tb-r" id="rep-topbar"></div>
  </div>
  <div id="view"><div class="page"><div class="empty"><i class="fas fa-spinner fa-spin"></i> Cargando…</div></div></div>
</div>
<div class="rep" id="rep-layer"></div>
<script src="/App/representantes/assets/rep.js?v=<?= $v ?>"></script>
</body>
</html>
<?php
}
