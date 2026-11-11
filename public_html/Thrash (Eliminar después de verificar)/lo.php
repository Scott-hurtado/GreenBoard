<?php 
session_start();
// Si ya está logueado, redirigir al dashboard
if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Administrador</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .admin-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255, 255, 255, 0.1);
            padding: 8px 16px;
            border-radius: 20px;
            color: white;
            font-size: 12px;
            font-weight: 600;
            backdrop-filter: blur(10px);
        }

        .container {
            max-width: 450px;
            width: 100%;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            animation: fadeInUp 0.8s ease;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .header {
            background: linear-gradient(135deg, #2d7a3e 0%, #1f5a2d 100%);
            padding: 40px 30px;
            text-align: center;
            color: white;
        }

        .header i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.9;
        }

        .header h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .header p {
            font-size: 14px;
            opacity: 0.9;
        }

        .form-container {
            padding: 40px 30px;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-size: 14px;
        }

        .alert-error {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ef9a9a;
        }

        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #a5d6a7;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-size: 14px;
            font-weight: 600;
        }

        .form-group label .required {
            color: #d32f2f;
        }

        .input-with-icon {
            position: relative;
        }

        .input-with-icon i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            font-size: 16px;
        }

        .form-group input {
            width: 100%;
            padding: 14px 18px 14px 50px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: white;
        }

        .form-group input:focus {
            outline: none;
            border-color: #2d7a3e;
            box-shadow: 0 0 0 3px rgba(45, 122, 62, 0.1);
        }

        .password-container {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #666;
            user-select: none;
            z-index: 10;
        }

        .forgot-password {
            text-align: right;
            margin-top: -15px;
            margin-bottom: 20px;
        }

        .forgot-password a {
            color: #2d7a3e;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
        }

        .forgot-password a:hover {
            text-decoration: underline;
        }

        .submit-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #2d7a3e 0%, #1f5a2d 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
            box-shadow: 0 4px 15px rgba(45, 122, 62, 0.3);
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(45, 122, 62, 0.4);
        }

        .back-link {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }

        .back-link a {
            color: #666;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s ease;
        }

        .back-link a:hover {
            color: #2d7a3e;
        }

        .security-notice {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            font-size: 12px;
            color: #666;
            text-align: center;
        }

        .security-notice i {
            color: #2d7a3e;
            margin-right: 5px;
        }

        @media (max-width: 480px) {
            .container {
                margin: 10px;
            }

            .header, .form-container {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="admin-badge">
        <i class="fas fa-shield-alt"></i> Panel Administrativo
    </div>

    <div class="container">
        <div class="header">
            <i class="fas fa-user-shield"></i>
            <h1>Acceso Administrativo</h1>
            <p>Proyecto Verde</p>
        </div>

        <div class="form-container">
            <?php
            if (isset($_GET['error'])) {
                echo '<div class="alert alert-error">';
                switch ($_GET['error']) {
                    case 'campos_vacios':
                        echo '<i class="fas fa-exclamation-circle"></i> Por favor completa todos los campos.';
                        break;
                    case 'credenciales_invalidas':
                        echo '<i class="fas fa-times-circle"></i> Correo o contraseña incorrectos.';
                        break;
                    case 'cuenta_inactiva':
                        echo '<i class="fas fa-ban"></i> Tu cuenta está inactiva. Contacta al superadministrador.';
                        break;
                    case 'error_servidor':
                        echo '<i class="fas fa-server"></i> Error del servidor. Intenta nuevamente.';
                        break;
                    case 'sesion_expirada':
                        echo '<i class="fas fa-clock"></i> Tu sesión ha expirado. Por favor inicia sesión nuevamente.';
                        break;
                    default:
                        echo '<i class="fas fa-exclamation-triangle"></i> Ocurrió un error. Por favor intenta nuevamente.';
                }
                echo '</div>';
            }
            
            if (isset($_GET['success'])) {
                echo '<div class="alert alert-success">';
                switch ($_GET['success']) {
                    case 'sesion_cerrada':
                        echo '<i class="fas fa-check-circle"></i> Sesión cerrada correctamente.';
                        break;
                    default:
                        echo '<i class="fas fa-check"></i> Operación exitosa.';
                }
                echo '</div>';
            }
            ?>

            <form action="procesar_login_admin.php" method="POST">
                <div class="form-group">
                    <label>Correo Electrónico <span class="required">*</span></label>
                    <div class="input-with-icon">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="correo" placeholder="admin@proyectoverde.com" required autofocus>
                    </div>
                </div>

                <div class="form-group">
                    <label>Contraseña <span class="required">*</span></label>
                    <div class="input-with-icon password-container">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="contrasena" id="password" placeholder="Ingresa tu contraseña" required>
                        <span class="password-toggle" onclick="togglePassword()">
                            <i class="fas fa-eye" id="toggle-icon"></i>
                        </span>
                    </div>
                </div>

                <div class="forgot-password">
                    <a href="recuperar_contrasena_admin.php">¿Olvidaste tu contraseña?</a>
                </div>

                <button type="submit" class="submit-btn">
                    <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
                </button>

                <div class="security-notice">
                    <i class="fas fa-lock"></i>
                    Conexión segura y cifrada
                </div>
            </form>

            <div class="back-link">
                <a href="/index.php"><i class="fas fa-arrow-left"></i> Volver al sitio principal</a>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggle-icon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Prevenir ataques de fuerza bruta con delay en intentos fallidos
        if (window.location.search.includes('error=credenciales_invalidas')) {
            const submitBtn = document.querySelector('.submit-btn');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Espera un momento...';
            
            setTimeout(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Iniciar Sesión';
            }, 2000);
        }
    </script>
</body>
</html>