<?php
require_once __DIR__ . '/includes/bootstrap.php';

// Redirección si ya hay sesión activa
if (isset($_SESSION['user_id'])) {
    $rol = $_SESSION['user_role'] ?? '';
    if (in_array($rol, ['admin', 'administrador', 'superadmin'])) {
        header('Location: /App/admin/gestion.php');
        exit();
    } elseif ($rol === 'circunscripcion') {
        header('Location: /App/circunscripcion/dashboard.php');
        exit();
    } elseif ($rol === 'coordinador_estatal') {
        header('Location: /App/state_coordinator/dashboard.php');
        exit();
    } elseif ($rol === 'coordinador_regional') {
        header('Location: /App/regional_coordinator/dashboard.php');
        exit();
    } elseif ($rol === 'coordinador_zona') {
        header('Location: /App/zone_coordinator/dashboard.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso - Proyecto Verde</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        
        body { 
            background: #f0f2f5; 
            min-height: 100vh; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            padding: 20px;
        }

        .login-card {
            background: white;
            width: 100%;
            max-width: 400px;
            border-radius: 24px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #2d7a3e 0%, #1f5a2d 100%);
            padding: 40px 30px;
            text-align: center;
            color: white;
        }

        .header h1 { font-size: 24px; margin-bottom: 8px; }
        .header p { font-size: 14px; opacity: 0.9; }

        .header-logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: white;
            border-radius: 20px;
            padding: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }

        .header-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .content-step { padding: 40px 30px; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-size: 13px; font-weight: 600; color: #4a5568; }
        
        .input-box {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-box i { position: absolute; left: 15px; color: #a0aec0; }
        
        .input-box input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            outline: none;
            transition: 0.3s;
        }

        .input-box input:focus { border-color: #2d7a3e; box-shadow: 0 0 0 3px rgba(45,122,62,0.1); }

        .btn-primary {
            width: 100%;
            padding: 14px;
            background: #2d7a3e;
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
            transition: background 0.3s;
        }

        .btn-primary:hover { background: #1f5a2d; }
        .btn-primary:disabled { background: #a0aec0; cursor: not-allowed; }

        .alert {
            padding: 12px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 20px;
            display: none;
        }
        .alert-error { background: #fee2e2; color: #991b1b; }

        .fade-in { animation: fadeIn 0.5s ease forwards; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>

<div class="login-card">
    <div class="header">
        <div class="header-logo">
            <img src="/storage/logo/PVEM.svg" alt="Logo Proyecto Verde">
        </div>
        <h1>GreenBoard</h1>
        <p>Ingresa tus credenciales para continuar</p>
    </div>

    <div class="content-step fade-in">
        <div id="js-alert" class="alert alert-error"></div>

        <form id="loginForm">
            <div class="form-group">
                <label>Correo Electrónico</label>
                <div class="input-box">
                    <i class="fas fa-envelope"></i>
                    <input type="email" name="correo" placeholder="correo@ejemplo.com" required>
                </div>
            </div>

            <div class="form-group">
                <label>Contraseña</label>
                <div class="input-box">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="contrasena" placeholder="••••••••" required>
                </div>
            </div>

            <button type="submit" class="btn-primary" id="submit-btn">
                Iniciar Sesión
            </button>
        </form>

        <div style="text-align: center; margin-top: 25px;">
            <a href="/Public/index.php" style="color: #718096; text-decoration: none; font-size: 13px;">Volver al inicio</a>
        </div>
    </div>
</div>

<script>
    document.getElementById('loginForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = document.getElementById('submit-btn');
        const alertBox = document.getElementById('js-alert');
        
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verificando...';
        alertBox.style.display = 'none';

        try {
            const response = await fetch('/api/auth/login.php', {
                method: 'POST',
                body: new FormData(this)
            });

            const data = await response.json();

            if (data.success) {
                window.location.href = data.redirect;
            } else {
                throw new Error(data.message || 'Error de acceso. Verifica tus datos.');
            }
        } catch (error) {
            alertBox.innerText = error.message;
            alertBox.style.display = 'block';
            btn.disabled = false;
            btn.innerHTML = 'Iniciar Sesión';
        }
    });
</script>

</body>
</html>