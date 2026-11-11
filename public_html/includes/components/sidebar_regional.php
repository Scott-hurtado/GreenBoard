<div class="sidebar-overlay" onclick="toggleMobileSidebar()"></div>

<button class="mobile-toggle-btn" onclick="toggleMobileSidebar()">
    <i class="fas fa-bars"></i>
</button>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <div class="logo-wrapper">
            <div class="logo-icon">
                <img src="../../storage/logo/PVEM.svg" alt="Logo" style="width: 100%; height: 100%; object-fit: contain;">
            </div>
            <div class="logo-text">GreenBoard</div>
        </div>
        <button class="sidebar-toggle-btn" onclick="handleSidebarToggle()">
            <i class="fas fa-chevron-left"></i>
        </button>
    </div>

    <div class="user-profile">
        <div class="user-avatar"><?php
            $name = $_SESSION['user_name'] ?? 'Regional';
            $parts = explode(' ', trim($name));
            echo strtoupper(substr($parts[0],0,1) . (isset($parts[1]) ? substr($parts[1],0,1) : ''));
        ?></div>
        <div class="user-info">
            <div class="user-name"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Coordinador'); ?></div>
            <div class="user-role">Coord. Regional</div>
        </div>
    </div>

    <div class="menu-wrapper">
        <ul class="sidebar-menu">

            <li class="menu-item">
                <a href="/App/regional_coordinator/dashboard.php" class="menu-link">
                    <span class="menu-icon"><i class="fas fa-chart-pie"></i></span>
                    <span class="menu-text">Dashboard</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="/App/regional_coordinator/metas.php" class="menu-link">
                    <span class="menu-icon"><i class="fas fa-bullseye"></i></span>
                    <span class="menu-text">Metas</span>
                </a>
            </li>

            <li class="menu-item">
                <a href="/App/regional_coordinator/gestion.php" class="menu-link">
                    <span class="menu-icon"><i class="fas fa-sitemap"></i></span>
                    <span class="menu-text">Gestión</span>
                </a>
            </li>

            <li class="menu-item">
                <a href="/App/regional_coordinator/agenda.php" class="menu-link">
                    <span class="menu-icon"><i class="fas fa-calendar-alt"></i></span>
                    <span class="menu-text">Agenda</span>
                </a>
            </li>

            <li class="menu-item">
                <a href="/App/regional_coordinator/activaciones.php" class="menu-link">
                    <span class="menu-icon"><i class="fas fa-bolt"></i></span>
                    <span class="menu-text">Activaciones</span>
                </a>
            </li>

            <li class="menu-item">
                <a href="/App/regional_coordinator/estructura.php" class="menu-link">
                    <span class="menu-icon"><i class="fas fa-map-marked-alt"></i></span>
                    <span class="menu-text">Estructura</span>
                </a>
            </li>

            <li class="menu-item">
                <a href="/App/regional_coordinator/territorio.php" class="menu-link">
                    <span class="menu-icon"><i class="fas fa-map"></i></span>
                    <span class="menu-text">Territorio</span>
                </a>
            </li>

            <li class="menu-item">
                <a href="/App/regional_coordinator/secciones.php" class="menu-link">
                    <span class="menu-icon"><i class="fas fa-layer-group"></i></span>
                    <span class="menu-text">Secciones</span>
                </a>
            </li>

            <li class="menu-item">
                <a href="javascript:void(0);" class="menu-link toggle-submenu" onclick="toggleSidebarSubmenu(this)">
                    <span class="menu-icon"><i class="fas fa-user-plus"></i></span>
                    <span class="menu-text" style="flex: 1;">Usuarios</span>
                    <span class="menu-arrow"><i class="fas fa-chevron-down" style="font-size: 12px; transition: transform 0.3s;"></i></span>
                </a>
                <ul class="sidebar-submenu">
                    <li class="menu-item">
                        <a href="/App/global/crear_equipo.php" class="menu-link sub-link">
                            <i class="fas fa-circle nav-dot"></i> Crear Usuario
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="/App/global/directorio_equipo.php" class="menu-link sub-link">
                            <i class="fas fa-circle nav-dot"></i> Ver Usuarios
                        </a>
                    </li>
                </ul>
            </li>

            <li class="menu-item">
                <a href="/App/regional_coordinator/informes.php" class="menu-link">
                    <span class="menu-icon"><i class="fas fa-file-alt"></i></span>
                    <span class="menu-text">Informes</span>
                </a>
            </li>

            <li class="menu-item">
                <a href="/App/regional_coordinator/biblioteca.php" class="menu-link">
                    <span class="menu-icon"><i class="fas fa-book"></i></span>
                    <span class="menu-text">Biblioteca</span>
                </a>
            </li>

            <li class="menu-item">
                <a href="/App/regional_coordinator/database_qr.php" class="menu-link">
                    <span class="menu-icon"><i class="fas fa-qrcode"></i></span>
                    <span class="menu-text">Database QR</span>
                </a>
            </li>

        </ul>
    </div>

    <div class="sidebar-footer">
        <button class="logout-btn" onclick="logout(event)">
            <span class="menu-icon"><i class="fas fa-sign-out-alt"></i></span>
            <span class="menu-text">Cerrar Sesión</span>
        </button>
    </div>
</aside>

<script>
    const sidebar = document.getElementById('sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    const container = document.querySelector('.container');

    function isMobile() { return window.innerWidth <= 768; }

    function handleSidebarToggle() {
        if (isMobile()) toggleMobileSidebar();
        else toggleDesktopSidebar();
    }

    function toggleMobileSidebar() {
        sidebar.classList.toggle('mobile-open');
        overlay.classList.toggle('active');
    }

    function toggleDesktopSidebar() {
        sidebar.classList.toggle('collapsed');
        if (container) container.classList.toggle('expanded-content');
    }

    function setActiveLink() {
        const currentPath = window.location.pathname;
        document.querySelectorAll('.menu-link').forEach(link => {
            const href = link.getAttribute('href');
            if (href && href !== 'javascript:void(0);' && href !== '#') {
                if (currentPath === href || currentPath.endsWith(href)) {
                    link.classList.add('active');
                    const parentSubmenu = link.closest('.sidebar-submenu');
                    if (parentSubmenu) {
                        parentSubmenu.style.display = 'block';
                        const parentLi = parentSubmenu.closest('.menu-item');
                        if (parentLi) {
                            const toggle = parentLi.querySelector('.toggle-submenu');
                            if (toggle) {
                                toggle.classList.add('active-parent');
                                const arrow = toggle.querySelector('.menu-arrow i');
                                if (arrow) arrow.style.transform = 'rotate(180deg)';
                            }
                        }
                    }
                }
            }
        });
    }

    function toggleSidebarSubmenu(element) {
        const parentLi = element.closest('.menu-item');
        const submenu = parentLi.querySelector('.sidebar-submenu');
        const arrow = element.querySelector('.menu-arrow i');
        if (!submenu) return;
        if (sidebar.classList.contains('collapsed') && !isMobile()) toggleDesktopSidebar();
        const isVisible = submenu.style.display === 'block';
        submenu.style.display = isVisible ? 'none' : 'block';
        if (arrow) arrow.style.transform = isVisible ? 'rotate(0deg)' : 'rotate(180deg)';
    }

    document.addEventListener('DOMContentLoaded', () => {
        setActiveLink();
        document.querySelectorAll('.menu-link:not(.toggle-submenu)').forEach(link => {
            link.addEventListener('click', () => { if (isMobile()) toggleMobileSidebar(); });
        });
    });

    async function logout(e) {
        if (e) e.preventDefault();
        if (confirm('¿Cerrar sesión?')) {
            try { await fetch('/api/auth/logout.php'); } catch(err) {}
            window.location.href = '/index.php';
        }
    }
</script>

<style>
    :root {
        --primary: #2d7a3e;
        --primary-dark: #1f5a2d;
        --light: #f0f7f0;
        --dark: #1a1a1a;
        --white: #ffffff;
        --gray: #9e9e9e;
        --sidebar-width: 280px;
        --sidebar-collapsed-width: 80px;
    }

    .sidebar-overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.5); z-index: 999;
        opacity: 0; visibility: hidden; transition: all 0.3s;
        backdrop-filter: blur(2px);
    }
    .sidebar-overlay.active { opacity: 1; visibility: visible; }

    .sidebar {
        position: fixed; left: 0; top: 0; height: 100vh; width: var(--sidebar-width);
        background: var(--white); box-shadow: 4px 0 25px rgba(0,0,0,0.05);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        z-index: 1000; display: flex; flex-direction: column;
    }

    .sidebar-logo {
        height: 80px; padding: 0 20px; display: flex; align-items: center; justify-content: space-between;
        border-bottom: 1px solid #f0f0f0;
    }
    .logo-wrapper { display: flex; align-items: center; gap: 12px; overflow: hidden; }
    .logo-icon { width: 40px; height: 40px; flex-shrink: 0; }
    .logo-text { font-size: 20px; font-weight: 800; color: var(--primary-dark); white-space: nowrap; transition: 0.3s; }

    .sidebar-toggle-btn {
        background: transparent; border: none; color: var(--gray); cursor: pointer;
        width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
        transition: 0.3s;
    }
    .sidebar-toggle-btn:hover { background: var(--light); color: var(--primary); }

    .sidebar.collapsed { width: var(--sidebar-collapsed-width); }
    .sidebar.collapsed .logo-text,
    .sidebar.collapsed .user-info,
    .sidebar.collapsed .menu-text,
    .sidebar.collapsed .menu-arrow,
    .sidebar.collapsed .logout-btn .menu-text { opacity: 0; width: 0; display: none; }
    .sidebar.collapsed .sidebar-logo { justify-content: center; padding: 0; }
    .sidebar.collapsed .logo-wrapper { gap: 0; }
    .sidebar.collapsed .sidebar-toggle-btn { transform: rotate(180deg); position: absolute; right: -12px; top: 25px; background: white; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border: 1px solid #eee; width: 24px; height: 24px; }
    .sidebar.collapsed .user-profile { padding: 20px 10px; }
    .sidebar.collapsed .user-avatar { width: 40px; height: 40px; font-size: 16px; margin: 0 auto; }
    .sidebar.collapsed .menu-link { justify-content: center; padding: 15px 0; }
    .sidebar.collapsed .menu-icon { margin: 0; font-size: 20px; }
    .sidebar.collapsed .sidebar-submenu { display: none !important; }

    .user-profile { padding: 30px 20px; text-align: center; border-bottom: 1px solid #f0f0f0; }
    .user-avatar {
        width: 60px; height: 60px; border-radius: 50%;
        background: linear-gradient(135deg, #4caf50 0%, #2d7a3e 100%);
        color: white; font-weight: bold; font-size: 20px;
        display: flex; align-items: center; justify-content: center;
        margin: 0 auto 10px; box-shadow: 0 5px 15px rgba(45,122,62,0.2);
    }
    .user-info { transition: 0.3s; }
    .user-name { font-weight: 700; color: var(--dark); font-size: 15px; margin-bottom: 2px; }
    .user-role { font-size: 11px; color: var(--primary); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; background: rgba(45,122,62,0.1); padding: 2px 8px; border-radius: 4px; display: inline-block; }

    .menu-wrapper { flex: 1; overflow-y: auto; padding: 20px 15px; }
    .menu-wrapper::-webkit-scrollbar { width: 4px; }
    .menu-wrapper::-webkit-scrollbar-thumb { background: transparent; }
    .menu-wrapper:hover::-webkit-scrollbar-thumb { background: #ddd; border-radius: 4px; }

    .sidebar-menu { list-style: none; padding: 0; margin: 0; }
    .menu-item { margin-bottom: 5px; }

    .menu-link {
        display: flex; align-items: center; gap: 15px; padding: 12px 15px;
        color: #666; text-decoration: none; border-radius: 10px;
        transition: all 0.2s; font-weight: 500; font-size: 14px;
    }
    .menu-link:hover { background: var(--light); color: var(--primary); }
    .menu-link.active { background: rgba(45,122,62,0.08); color: var(--primary); font-weight: 700; }
    .menu-link.active-parent { color: var(--primary); }
    .menu-icon { width: 24px; display: flex; justify-content: center; transition: 0.3s; }

    .sidebar-submenu { display: none; list-style: none; padding-left: 0; margin-top: 2px; background: #fafafa; border-radius: 10px; overflow: hidden; }
    .sub-link { padding-left: 50px; font-size: 13px; padding-top: 10px; padding-bottom: 10px; }
    .nav-dot { font-size: 6px; margin-right: 10px; opacity: 0.5; }
    .sub-link.active .nav-dot { opacity: 1; transform: scale(1.5); }

    .sidebar-footer { padding: 15px; border-top: 1px solid #f0f0f0; }
    .logout-btn {
        width: 100%; display: flex; align-items: center; gap: 10px; padding: 12px;
        background: #fff0f0; color: #d32f2f; border: none; border-radius: 10px;
        cursor: pointer; transition: 0.2s; font-weight: 600;
    }
    .logout-btn:hover { background: #ffe0e0; }

    .mobile-toggle-btn {
        display: none; position: fixed; top: 20px; left: 20px; z-index: 1001;
        background: var(--primary); color: white; border: none; border-radius: 8px;
        width: 40px; height: 40px; font-size: 18px; box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        cursor: pointer; align-items: center; justify-content: center;
    }

    @media (max-width: 768px) {
        .mobile-toggle-btn { display: flex; }
        .sidebar { transform: translateX(-100%); width: 280px !important; }
        .sidebar.mobile-open { transform: translateX(0); }
        .sidebar-toggle-btn { display: none; }
    }
</style>