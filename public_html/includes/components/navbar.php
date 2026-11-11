<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* Navbar */
        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 30px 60px;
            background: white;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0);
            transition: all 0.3s ease;
        }

        nav.scrolled {
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px 60px;
        }

        .logo {
            font-size: 32px;
            font-weight: 700;
            color: #2d7a3e;
            letter-spacing: 2px;
            transition: transform 0.3s ease;
        }

        .logo:hover {
            transform: scale(1.05);
        }

        .logo-icon {
            display: inline-block;
            width: 24px;
            height: 24px;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="none" stroke="%232d7a3e" stroke-width="1.5"/><circle cx="12" cy="12" r="3" fill="%232d7a3e"/></svg>');
            background-size: contain;
            vertical-align: middle;
            margin: 0 2px;
            animation: rotate 4s linear infinite;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .nav-menu {
            display: flex;
            list-style: none;
            gap: 40px;
        }

        .nav-menu a {
            text-decoration: none;
            color: #666;
            font-size: 13px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            position: relative;
            padding-bottom: 5px;
        }

        .nav-menu a::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background: #333;
            transition: width 0.3s ease;
        }

        .nav-menu a:hover::after,
        .nav-menu a.active::after {
            width: 100%;
        }

        .nav-menu a.active {
            color: #333;
        }

        .nav-menu a:hover {
            color: #333;
            transform: translateY(-2px);
        }

        .nav-auth {
            display: flex;
            gap: 30px;
        }

        .nav-auth a {
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            letter-spacing: 0.5px;
            padding: 10px 20px;
            border-radius: 4px;
            transition: all 0.3s ease;
        }

        .nav-auth .login {
            color: #333;
            border: 2px solid transparent;
        }

        .nav-auth .login:hover {
            border: 2px solid #333;
            transform: translateY(-2px);
        }

        .nav-auth .register {
            color: white;
            background: #333;
            border: 2px solid #333;
        }

        .nav-auth .register:hover {
            background: transparent;
            color: #333;
            transform: translateY(-2px);
        }

        /* Responsive */
        @media (max-width: 768px) {
            nav {
                flex-direction: column;
                gap: 20px;
            }

            .nav-menu {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <nav id="navbar">
        <div class="logo">
            vizi<span class="logo-icon"></span>n
        </div>
        <ul class="nav-menu">
            <li><a href="/index.php">INICIO</a></li>
            <li><a href="/public/nosotros.php">NOSOTROS</a></li>
            <li><a href="/public/propuestas.php">PROPUESTAS</a></li>
            <li><a href="/public/contacto.php">CONTACTO</a></li>
            <li><a href="/public/noticias.php">NOTICIAS</a></li>
        </ul>
        <div class="nav-auth">
            <a href="/public/iniciar_sesion.php" class="login">Iniciar Sesion</a>
            <a href="/public/registro.php" class="register">Registrarme</a>
        </div>
    </nav>

    <script>
        window.addEventListener('scroll', function() {
            const navbar = document.getElementById('navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });

        document.addEventListener("DOMContentLoaded", function() {
            const currentUrl = window.location.href; 
            const navLinks = document.querySelectorAll('.nav-menu a');

            navLinks.forEach(link => {
                if (link.href === currentUrl) {
                    link.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>