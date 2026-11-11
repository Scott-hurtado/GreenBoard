<?php
/**
 * Ítem «Representantes» del menú lateral, con sus subitems según el rol.
 * Se incluye desde cada sidebar (admin, circunscripción, estatal, regional, zona).
 */
$__repRol = strtolower(trim($_SESSION['user_role'] ?? ''));
$__repNivel = [
    'admin' => 1, 'administrador' => 1, 'superadmin' => 1, 'super admin' => 1,
    'coordinadores_circunscripcion' => 2, 'coordinador_circunscripcion' => 2, 'circunscripcion' => 2,
    'coordinador_estatal' => 3, 'coordinador_regional' => 4, 'regional' => 4, 'coordinador_zona' => 5,
][$__repRol] ?? 6;

// [archivo, texto, ícono, niveles que lo ven]
$__repItems = [
    ['tablero.php',       'Tablero',                 'fa-gauge-high',     [1, 2, 3, 4, 5]],
    ['casillas.php',      'Secciones y casillas',    'fa-box-archive',    [1, 2, 3, 4, 5]],
    ['padron.php',        'Padrón',                  'fa-users',          [1, 2, 3, 4, 5]],
    ['registrar.php',     'Registrar representante', 'fa-user-plus',      [4, 5]],
    ['expedientes.php',   'Revisión de expedientes', 'fa-file-circle-check', [3, 4]],
    ['registro.php',      'Registro y acreditación', 'fa-stamp',          [3, 4]],
    ['metas.php',         'Metas',                   'fa-bullseye',       [1, 2, 3, 4, 5]],
    ['ruta.php',          'Ruta y cortes',           'fa-route',          [1, 2, 3, 4, 5]],
    ['configuracion.php', 'Configuración',           'fa-sliders',        [1, 2, 3, 4, 5]],
    ['bitacora.php',      'Bitácora',                'fa-list-check',     [1, 2, 3, 4]],
];
if ($__repNivel <= 5):
?>
            <li class="menu-item">
                <a href="javascript:void(0);" class="menu-link toggle-submenu" onclick="toggleSidebarSubmenu(this)">
                    <span class="menu-icon"><i class="fas fa-id-card-clip"></i></span>
                    <span class="menu-text" style="flex: 1;">Representantes</span>
                    <span class="menu-arrow"><i class="fas fa-chevron-down" style="font-size: 12px; transition: transform 0.3s;"></i></span>
                </a>
                <ul class="sidebar-submenu">
<?php foreach ($__repItems as $__it): if (!in_array($__repNivel, $__it[3], true)) continue; ?>
                    <li class="menu-item">
                        <a href="/App/representantes/<?= $__it[0] ?>" class="menu-link sub-link">
                            <i class="fas fa-circle nav-dot"></i> <?= $__it[1] ?>
                        </a>
                    </li>
<?php endforeach; ?>
                </ul>
            </li>
<?php endif; ?>
