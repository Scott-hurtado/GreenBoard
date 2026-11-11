<?php 
// 1. Cargamos el núcleo del sistema (incluye configuración y sesión)
require_once __DIR__ . '/../includes/bootstrap.php';

// 2. Si ya está logueado, lo sacamos de aquí
if (isset($_SESSION['user_id'])) {
    // Redirección segura según rol
    $rol = $_SESSION['user_role'] ?? '';
    $rolesAdmin = ['admin', 'administrador', 'superadmin', 'super admin'];
    if (in_array(strtolower($rol), array_map('strtolower', $rolesAdmin))) {
        $redirect = '/App/admin/dashboard.php';
    }
    header("Location: " . $redirect);
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Proyecto Verde</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ... TUS ESTILOS CSS ORIGINALES (Los mantengo intactos) ... */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; background: linear-gradient(135deg, #f0f7f0 0%, #e8f5e9 100%); min-height: 100vh; display: flex; flex-direction: column; }
        .main-wrapper { flex: 1; display: flex; align-items: center; justify-content: center; padding: 40px 20px; }
        .container { max-width: 1200px; width: 100%; display: flex; background: white; border-radius: 20px; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1); overflow: hidden; animation: fadeInUp 0.8s ease; }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        .left-side { flex: 1; background: linear-gradient(135deg, #2d7a3e 0%, #1f5a2d 100%); padding: 60px; color: white; display: flex; flex-direction: column; justify-content: center; }
        .left-side h1 { font-size: 48px; font-weight: 900; margin-bottom: 20px; line-height: 1.2; }
        .left-side p { font-size: 16px; line-height: 1.8; opacity: 0.9; margin-bottom: 40px; }
        .benefits { list-style: none; }
        .benefits li { padding: 15px 0; padding-left: 30px; position: relative; font-size: 14px; }
        .benefits li::before { content: '✓'; position: absolute; left: 0; color: #a5d6a7; font-weight: bold; font-size: 18px; }
        .right-side { flex: 1; padding: 60px; display: flex; flex-direction: column; }
        .back-home { display: inline-block; margin-bottom: 30px; color: #2d7a3e; text-decoration: none; font-size: 14px; font-weight: 600; }
        .back-home:hover { text-decoration: underline; }
        .step-indicator { display: flex; justify-content: center; margin-bottom: 40px; gap: 10px; }
        .step { width: 40px; height: 4px; background: #e0e0e0; border-radius: 2px; transition: all 0.3s ease; }
        .step.active { background: #2d7a3e; width: 60px; }
        .onboarding-screen { display: block; animation: fadeIn 0.5s ease; }
        .onboarding-screen.hidden { display: none; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .form-header { margin-bottom: 40px; text-align: center; }
        .form-header h2 { font-size: 32px; color: #1a1a1a; margin-bottom: 10px; }
        .form-header p { color: #666; font-size: 14px; }
        .account-type-selector { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }
        .account-type { padding: 40px 30px; border: 2px solid #e0e0e0; border-radius: 12px; text-align: center; cursor: pointer; transition: all 0.3s ease; background: white; }
        .account-type:hover { border-color: #2d7a3e; background: #f0f7f0; transform: translateY(-5px); box-shadow: 0 10px 30px rgba(45, 122, 62, 0.15); }
        .account-type.selected { border-color: #2d7a3e; background: #f0f7f0; box-shadow: 0 10px 30px rgba(45, 122, 62, 0.2); }
        .account-icon { font-size: 64px; margin-bottom: 20px; color: #2d7a3e; }
        .account-name { font-size: 20px; font-weight: 600; color: #1a1a1a; margin-bottom: 10px; }
        .account-description { font-size: 13px; color: #666; line-height: 1.6; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 8px; font-size: 14px; display: none; }
        .alert-error { background: #ffebee; color: #c62828; border: 1px solid #ef9a9a; }
        .alert-success { background: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7; }
        .login-form { animation: fadeIn 0.5s ease; }
        .form-group { margin-bottom: 25px; }
        .form-group label { display: block; margin-bottom: 8px; color: #333; font-size: 14px; font-weight: 600; }
        .form-group label .required { color: #d32f2f; }
        .input-with-icon { position: relative; }
        .input-with-icon i { position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: #999; font-size: 16px; }
        .form-group input { width: 100%; padding: 14px 18px 14px 50px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; transition: all 0.3s ease; background: white; }
        .form-group input:focus { outline: none; border-color: #2d7a3e; box-shadow: 0 0 0 3px rgba(45, 122, 62, 0.1); }
        .password-container { position: relative; }
        .password-toggle { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #666; user-select: none; z-index: 10; }
        .forgot-password { text-align: right; margin-top: -15px; margin-bottom: 20px; }
        .forgot-password a { color: #2d7a3e; text-decoration: none; font-size: 13px; font-weight: 600; }
        .forgot-password a:hover { text-decoration: underline; }
        .submit-btn { width: 100%; padding: 16px; background: linear-gradient(135deg, #2d7a3e 0%, #1f5a2d 100%); color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; margin-top: 10px; box-shadow: 0 4px 15px rgba(45, 122, 62, 0.3); }
        .submit-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(45, 122, 62, 0.4); }
        .back-btn { width: 100%; padding: 16px; background: white; color: #2d7a3e; border: 2px solid #2d7a3e; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; margin-bottom: 15px; }
        .back-btn:hover { background: #f0f7f0; }
        .register-link { text-align: center; margin-top: 20px; color: #666; font-size: 14px; }
        .register-link a { color: #2d7a3e; text-decoration: none; font-weight: 600; }
        .register-link a:hover { text-decoration: underline; }
        @media (max-width: 968px) { .container { flex-direction: column; } .left-side, .right-side { padding: 40px; } .account-type-selector { grid-template-columns: 1fr; } .left-side h1 { font-size: 36px; } }
    </style>
</head>
<body>
    
    <?php include __DIR__ . '/../includes/components/navbar.php'; ?>

    <div class="main-wrapper">
        <div class="container">
            <div class="left-side">
                <h1>Únete al<br>Proyecto Verde</h1>
                <p>Forma parte del cambio que México necesita. Juntos construiremos un futuro más sostenible y justo para todos.</p>
                <ul class="benefits">
                    <li>Participa en eventos y actividades</li>
                    <li>Accede a capacitaciones exclusivas</li>
                    <li>Conecta con otros ciudadanos comprometidos</li>
                    <li>Contribuye a las propuestas y decisiones</li>
                    <li>Recibe información y actualizaciones</li>
                </ul>
            </div>

            <div class="right-side">
                <a href="/index.php" class="back-home"><i class="fas fa-arrow-left"></i> Volver al inicio</a>
                
                <div class="step-indicator">
                    <div class="step active" id="step1"></div>
                    <div class="step" id="step2"></div>
                </div>

                <div id="js-alert" class="alert"></div>

                <div class="onboarding-screen" id="account-selection">
                    <div class="form-header">
                        <h2>Iniciar Sesión</h2>
                        <p>Selecciona tu tipo de cuenta</p>
                    </div>

                    <div class="account-type-selector">
                        <div class="account-type" data-type="ciudadano">
                            <div class="account-icon"><i class="fas fa-user"></i></div>
                            <div class="account-name">Ciudadano</div>
                            <div class="account-description">Acceso para simpatizantes y voluntarios individuales</div>
                        </div>

                        <div class="account-type" data-type="colectivo">
                            <div class="account-icon"><i class="fas fa-users"></i></div>
                            <div class="account-name">Colectivo</div>
                            <div class="account-description">Acceso para organizaciones y colectivos registrados</div>
                        </div>
                    </div>

                    <div class="register-link">
                        ¿No tienes una cuenta? <a href="/registro.php">Regístrate aquí</a>
                    </div>
                </div>

                <form class="onboarding-screen hidden login-form" id="form-ciudadano" onsubmit="handleLogin(event, this)">
                    <input type="hidden" name="tipo_cuenta" value="ciudadano">
                    
                    <div class="form-header">
                        <h2>Iniciar Sesión</h2>
                        <p>Ingresa tus credenciales como ciudadano</p>
                    </div>

                    <div class="form-group">
                        <label>Correo Electrónico <span class="required">*</span></label>
                        <div class="input-with-icon">
                            <i class="fas fa-envelope"></i>
                            <input type="email" name="correo" placeholder="tu@correo.com" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Contraseña <span class="required">*</span></label>
                        <div class="input-with-icon password-container">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="contrasena" id="password-ciudadano" placeholder="Ingresa tu contraseña" required>
                            <span class="password-toggle" onclick="togglePassword('password-ciudadano', 'toggle-icon-ciudadano')">
                                <i class="fas fa-eye" id="toggle-icon-ciudadano"></i>
                            </span>
                        </div>
                    </div>

                    <div class="forgot-password">
                        <a href="#">¿Olvidaste tu contraseña?</a>
                    </div>

                    <button type="button" class="back-btn" onclick="goBack()">
                        <i class="fas fa-arrow-left"></i> Volver
                    </button>
                    <button type="submit" class="submit-btn">
                        Iniciar Sesión <i class="fas fa-arrow-right"></i>
                    </button>

                    <div class="register-link">
                        ¿No tienes una cuenta? <a href="/registro.php">Regístrate aquí</a>
                    </div>
                </form>

                <form class="onboarding-screen hidden login-form" id="form-colectivo" onsubmit="handleLogin(event, this)">
                    <input type="hidden" name="tipo_cuenta" value="colectivo">
                    
                    <div class="form-header">
                        <h2>Iniciar Sesión</h2>
                        <p>Ingresa tus credenciales como colectivo</p>
                    </div>

                    <div class="form-group">
                        <label>Correo Electrónico <span class="required">*</span></label>
                        <div class="input-with-icon">
                            <i class="fas fa-envelope"></i>
                            <input type="email" name="correo" placeholder="contacto@colectivo.com" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Contraseña <span class="required">*</span></label>
                        <div class="input-with-icon password-container">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="contrasena" id="password-colectivo" placeholder="Ingresa tu contraseña" required>
                            <span class="password-toggle" onclick="togglePassword('password-colectivo', 'toggle-icon-colectivo')">
                                <i class="fas fa-eye" id="toggle-icon-colectivo"></i>
                            </span>
                        </div>
                    </div>

                    <div class="forgot-password">
                        <a href="#">¿Olvidaste tu contraseña?</a>
                    </div>

                    <button type="button" class="back-btn" onclick="goBack()">
                        <i class="fas fa-arrow-left"></i> Volver
                    </button>
                    <button type="submit" class="submit-btn">
                        Iniciar Sesión <i class="fas fa-arrow-right"></i>
                    </button>

                    <div class="register-link">
                        ¿No tienes una cuenta? <a href="/registro.php">Regístrate aquí</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/components/footer.php'; ?>

    <script>
        // Lógica de interfaz (Tabs, passwords, etc)
        let selectedAccountType = null;

        document.querySelectorAll('.account-type').forEach(type => {
            type.addEventListener('click', function() {
                document.querySelectorAll('.account-type').forEach(t => t.classList.remove('selected'));
                this.classList.add('selected');
                selectedAccountType = this.getAttribute('data-type');
                
                setTimeout(() => {
                    document.getElementById('account-selection').classList.add('hidden');
                    document.getElementById('form-' + selectedAccountType).classList.remove('hidden');
                    document.getElementById('step1').classList.remove('active');
                    document.getElementById('step2').classList.add('active');
                    hideAlert();
                }, 300);
            });
        });

        function goBack() {
            document.getElementById('form-ciudadano').classList.add('hidden');
            document.getElementById('form-colectivo').classList.add('hidden');
            document.getElementById('account-selection').classList.remove('hidden');
            document.getElementById('step1').classList.add('active');
            document.getElementById('step2').classList.remove('active');
            document.querySelectorAll('.account-type').forEach(t => t.classList.remove('selected'));
            selectedAccountType = null;
            hideAlert();
        }

        function togglePassword(inputId, iconId) {
            const passwordInput = document.getElementById(inputId);
            const toggleIcon = document.getElementById(iconId);
            
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

        // --- LÓGICA DE CONEXIÓN CON LA API (NUEVO) ---
        function showAlert(message, type) {
            const alertBox = document.getElementById('js-alert');
            alertBox.textContent = message;
            alertBox.className = 'alert ' + (type === 'error' ? 'alert-error' : 'alert-success');
            alertBox.style.display = 'block';
        }

        function hideAlert() {
            const alertBox = document.getElementById('js-alert');
            alertBox.style.display = 'none';
        }

        async function handleLogin(event, form) {
            event.preventDefault(); // Evitar recarga
            hideAlert();
            
            const submitBtn = form.querySelector('.submit-btn');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';

            const formData = new FormData(form);

            try {
                const response = await fetch('/api/auth/login.php', {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) {
                    throw new Error('Error de conexión: ' + response.status);
                }

                const data = await response.json();

                if (data.success) {
                    showAlert(data.message || 'Login exitoso, redirigiendo...', 'success');
                    setTimeout(() => {
                        window.location.href = data.redirect || '/App/citizens/dashboard.php';
                    }, 1000);
                } else {
                    showAlert(data.message || 'Error al iniciar sesión', 'error');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('Error: ' + error.message, 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        }
    </script>

</body>
</html>