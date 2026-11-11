<?php
$nombre_completo = $_SESSION['user_name'] ?? 'Ciudadano';
$rol = $_SESSION['user_role'] ?? 'simpatizante';

function obtenerIniciales($nombre) {
    $nombre = trim($nombre);
    if (empty($nombre)) return 'US';
    $palabras = explode(' ', $nombre);
    if (count($palabras) >= 2) {
        return strtoupper(substr($palabras[0], 0, 1) . substr($palabras[1], 0, 1));
    } else {
        return strtoupper(substr($nombre, 0, 2));
    }
}

$iniciales = obtenerIniciales($nombre_completo);

function formatearRol($rol) {
    $roles = [
        'simpatizante' => 'Simpatizante',
        'voluntario' => 'Voluntario',
        'promotor' => 'Promotor',
        'lider_zona' => 'Líder de Zona',
        'ciudadano' => 'Ciudadano',
        'admin' => 'Administrador'
    ];
    return $roles[$rol] ?? ucfirst($rol);
}

$rol_formateado = formatearRol($rol);
$current_page = basename($_SERVER['PHP_SELF']);
?>

<style>
    /* SIDEBAR FIJO (SIEMPRE ABIERTO) */
    .sidebar {
        position: fixed;
        left: 0;
        top: 0;
        height: 100vh;
        width: 280px; /* Ancho fijo siempre */
        background: white;
        padding: 30px 0;
        box-shadow: 2px 0 10px rgba(0, 0, 0, 0.05);
        z-index: 1000;
        overflow-y: auto;
        overflow-x: hidden;
        border-right: 1px solid rgba(0, 0, 0, 0.05);
    }

    /* Logo */
    .sidebar-logo {
        padding: 0 25px 30px 25px;
        border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        margin-bottom: 30px;
        display: block;
        text-decoration: none;
    }
    
    .sidebar-logo-content {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .logo-icon {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, #a5d6a7 0%, #81c784 100%);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        color: #1f5a2d;
        flex-shrink: 0;
    }

    .logo-text {
        color: #1a1a1a;
        font-size: 22px;
        font-weight: 700;
        letter-spacing: 1px;
        white-space: nowrap;
        /* Quitamos opacity: 0 */
        opacity: 1; 
        width: auto;
    }

    /* Perfil de Usuario */
    .user-profile {
        padding: 0 25px 25px 25px;
        border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        margin-bottom: 25px;
        text-align: center;
    }

    .user-avatar {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: #2d7a3e;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        color: white;
        font-weight: 700;
        margin: 0 auto 12px auto;
        border: 2px solid #a5d6a7;
    }

    .user-info {
        opacity: 1;
        height: auto;
    }

    .user-name {
        color: #1a1a1a;
        font-size: 16px;
        font-weight: 600;
        margin-bottom: 4px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .user-role {
        color: #2d7a3e;
        font-size: 12px;
        padding: 4px 12px;
        background: #f0f7f0;
        border-radius: 12px;
        display: inline-block;
        font-weight: 600;
    }

    /* Menú */
    .sidebar-menu {
        list-style: none;
        padding: 0 15px;
    }

    .menu-item {
        margin-bottom: 8px;
    }

    .menu-link {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 14px 15px;
        color: #444;
        text-decoration: none;
        border-radius: 10px;
        transition: all 0.3s ease;
        position: relative;
    }

    .menu-link:hover, .menu-link.active {
        background: #e6f0e6;
        color: #2d7a3e;
    }

    .menu-link.active::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        height: 100%;
        width: 4px;
        background: #2d7a3e;
        border-radius: 10px 0 0 10px;
    }

    .menu-icon {
        width: 22px;
        height: 22px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
    }

    .menu-text {
        font-size: 14px;
        opacity: 1;
        width: auto;
    }

    .menu-badge {
        margin-left: auto;
        background: #ff5252;
        color: white;
        font-size: 11px;
        padding: 3px 8px;
        border-radius: 10px;
        font-weight: 600;
        opacity: 1;
    }

    /* Footer */
    .sidebar-footer {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        padding: 20px 15px;
        border-top: 1px solid rgba(0, 0, 0, 0.1);
    }

    .logout-btn {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 14px 15px;
        background: #f0f0f0;
        color: #1a1a1a;
        border: none;
        border-radius: 10px;
        width: 100%;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 14px;
        font-weight: 600;
    }

    .logout-btn:hover {
        background: #e6f0e6;
        color: #2d7a3e;
    }

    .logout-btn .menu-text {
        opacity: 1;
        width: auto;
    }

    /* Móvil: Ocultar y mostrar con botón */
    .mobile-toggle {
        display: none;
        position: fixed;
        top: 20px;
        left: 20px;
        width: 45px;
        height: 45px;
        background: #2d7a3e;
        color: white;
        border: none;
        border-radius: 10px;
        cursor: pointer;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        box-shadow: 0 4px 15px rgba(45, 122, 62, 0.3);
        z-index: 1001; /* Más alto que el sidebar para que se vea */
    }

    @media (max-width: 768px) {
        /* En móvil, el sidebar se esconde por defecto */
        .sidebar {
            transform: translateX(-100%);
            width: 280px !important;
        }
        /* Clase para mostrarlo */
        .sidebar.mobile-open {
            transform: translateX(0);
        }
        .mobile-toggle {
            display: flex;
        }
    }
</style>

<button class="mobile-toggle" onclick="toggleMobileSidebar()">
    <i class="fas fa-bars"></i>
</button>

<aside class="sidebar" id="sidebar">
    
    <a href="/App/citizens/dashboard.php" class="sidebar-logo">
        <div class="sidebar-logo-content">
            <div class="logo-icon"><i class="fas fa-leaf"></i></div>
            <div class="logo-text">VERDE</div>
        </div>
    </a>

    <div class="user-profile">
        <div class="user-avatar"><?php echo $iniciales; ?></div>
        <div class="user-info">
            <div class="user-name" title="<?php echo htmlspecialchars($nombre_completo); ?>">
                <?php echo htmlspecialchars($nombre_completo); ?>
            </div>
            <div class="user-role"><?php echo htmlspecialchars($rol_formateado); ?></div>
        </div>
    </div>

    <ul class="sidebar-menu">
        <li class="menu-item">
            <a href="/App/citizens/dashboard.php" class="menu-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                <span class="menu-icon"><i class="fas fa-home"></i></span>
                <span class="menu-text">Dashboard</span>
            </a>
        </li>
        
        <li class="menu-item">
            <a href="/App/citizens/actividades.php" class="menu-link <?php echo $current_page == 'actividades.php' ? 'active' : ''; ?>">
                <span class="menu-icon"><i class="fas fa-calendar-check"></i></span>
                <span class="menu-text">Actividades</span>
                <span class="menu-badge">5</span>
            </a>
        </li>
        
        <li class="menu-item">
            <a href="/App/citizens/materiales.php" class="menu-link <?php echo $current_page == 'materiales.php' ? 'active' : ''; ?>">
                <span class="menu-icon"><i class="fas fa-folder-open"></i></span>
                <span class="menu-text">Materiales</span>
            </a>
        </li>
    </ul>

    <div class="sidebar-footer">
        <button class="logout-btn" onclick="logout()">
            <span class="menu-icon"><i class="fas fa-sign-out-alt"></i></span>
            <span class="menu-text">Cerrar Sesión</span>
        </button>
    </div>
</aside>

<script>
    const body = document.body;

    function handleSidebarHover(isHovering) {
        if (window.innerWidth > 768) {
            if (isHovering) {
                body.classList.remove('sidebar-collapsed');
                body.classList.add('sidebar-expanded');
            } else {
                body.classList.remove('sidebar-expanded');
                body.classList.add('sidebar-collapsed');
            }
        }
    }
    
    window.addEventListener('DOMContentLoaded', () => {
        if (window.innerWidth > 768) {
             body.classList.add('sidebar-collapsed');
        }
    });

    function toggleMobileSidebar() {
        const sidebar = document.getElementById('sidebar');
        sidebar.classList.toggle('mobile-open');
    }

    document.addEventListener('click', function(event) {
        const sidebar = document.getElementById('sidebar');
        const mobileToggle = document.querySelector('.mobile-toggle');
        
        if (window.innerWidth <= 768) {
            if (!sidebar.contains(event.target) && !mobileToggle.contains(event.target)) {
                sidebar.classList.remove('mobile-open');
            }
        }
    });

    async function logout() {
        if(confirm('¿Cerrar sesión?')) {
            try {
                await fetch('/api/auth/logout.php');
                
                window.location.href = '/public/iniciar_sesion.php';
            } catch(error) {
                console.error('Error:', error);
                window.location.href = '/public/iniciar_sesion.php';
            }
        }
    }
</script>