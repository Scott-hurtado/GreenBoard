<?php 
// CORREGIDO: Respetando Mayúsculas 'Includes' (Importante para Linux/Hostinger)
require_once __DIR__ . '/../includes/bootstrap.php'; 
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - Proyecto Verde</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <style>
        /* ... Tu CSS original se mantiene igual ... */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; background: linear-gradient(135deg, #f0f7f0 0%, #e8f5e9 100%); min-height: 100vh; display: flex; flex-direction: column; }
        .main-wrapper { flex: 1; display: flex; align-items: center; justify-content: center; width: 100%; padding: 40px 20px; }
        .container { max-width: 1200px; width: 100%; display: flex; background: white; border-radius: 20px; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1); overflow: hidden; animation: fadeInUp 0.8s ease; }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        .left-side { flex: 1; background: linear-gradient(135deg, #2d7a3e 0%, #1f5a2d 100%); padding: 60px; color: white; display: flex; flex-direction: column; justify-content: center; }
        .left-side h1 { font-size: 48px; font-weight: 900; margin-bottom: 20px; line-height: 1.2; }
        .left-side p { font-size: 16px; line-height: 1.8; opacity: 0.9; margin-bottom: 40px; }
        .benefits { list-style: none; }
        .benefits li { padding: 15px 0; padding-left: 30px; position: relative; font-size: 14px; }
        .benefits li::before { content: '\f00c'; font-family: 'Font Awesome 6 Free'; font-weight: 900; position: absolute; left: 0; color: #a5d6a7; font-size: 18px; }
        .right-side { flex: 1; padding: 60px; }
        .form-header { margin-bottom: 40px; }
        .form-header h2 { font-size: 32px; color: #1a1a1a; margin-bottom: 10px; }
        .form-header p { color: #666; font-size: 14px; }
        .form-group { margin-bottom: 25px; }
        .form-group label { display: block; margin-bottom: 8px; color: #333; font-size: 14px; font-weight: 600; }
        .form-group label .required { color: #d32f2f; }
        .form-group input, .form-group select { width: 100%; padding: 14px 18px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; transition: all 0.3s ease; background: white; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #2d7a3e; box-shadow: 0 0 0 3px rgba(45, 122, 62, 0.1); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .password-container { position: relative; }
        .password-toggle { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #666; user-select: none; }
        .file-upload { position: relative; border: 2px dashed #e0e0e0; border-radius: 8px; padding: 30px; text-align: center; cursor: pointer; transition: all 0.3s ease; background: #fafafa; }
        .file-upload:hover { border-color: #2d7a3e; background: #f0f7f0; }
        .file-upload input[type="file"] { position: absolute; width: 100%; height: 100%; top: 0; left: 0; opacity: 0; cursor: pointer; }
        .file-upload-content { pointer-events: none; }
        .file-upload-icon { font-size: 40px; color: #2d7a3e; margin-bottom: 10px; }
        .file-upload-text { color: #666; font-size: 14px; }
        .file-upload-text strong { color: #2d7a3e; }
        .file-name { margin-top: 10px; color: #2d7a3e; font-size: 13px; font-weight: 600; }
        .file-name i { margin-right: 5px; }
        .submit-btn { width: 100%; padding: 16px; background: linear-gradient(135deg, #2d7a3e 0%, #1f5a2d 100%); color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; margin-top: 10px; box-shadow: 0 4px 15px rgba(45, 122, 62, 0.3); }
        .submit-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(45, 122, 62, 0.4); }
        .submit-btn:disabled { opacity: 0.7; cursor: not-allowed; }
        .login-link { text-align: center; margin-top: 20px; color: #666; font-size: 14px; }
        .login-link a { color: #2d7a3e; text-decoration: none; font-weight: 600; }
        .login-link a:hover { text-decoration: underline; }
        .back-home { display: inline-block; margin-bottom: 20px; color: #2d7a3e; text-decoration: none; font-size: 14px; font-weight: 600; }
        .back-home:hover { text-decoration: underline; }
        
        /* Estilos para alertas JS */
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 8px; font-size: 14px; display: none; }
        .alert-error { background: #ffebee; color: #c62828; border: 1px solid #ef9a9a; }
        .alert-success { background: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7; }
        
        @media (max-width: 968px) { .container { flex-direction: column; } .left-side { padding: 40px; } .right-side { padding: 40px; } .form-row { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

    <?php include __DIR__ . '/../Includes/components/navbar.php'; ?>

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
                <a href="/Public/index.php" class="back-home">← Volver al inicio</a>
                
                <div class="form-header">
                    <h2>Crear Cuenta</h2>
                    <p>Completa tus datos para registrarte</p>
                </div>

                <div id="js-alert" class="alert"></div>

                <form id="register-form" onsubmit="handleRegister(event, this)">
                    <div class="form-group">
                        <label>Nombre Completo <span class="required">*</span></label>
                        <input type="text" name="nombre" placeholder="Ej: Juan Pérez García" required> </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Teléfono Celular <span class="required">*</span></label>
                            <input type="tel" name="telefono" placeholder="Ej: 6621234567" pattern="[0-9]{10}" required>
                        </div>

                        <div class="form-group">
                            <label>Correo Electrónico <span class="required">*</span></label>
                            <input type="email" name="correo" placeholder="tu@correo.com" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Contraseña <span class="required">*</span></label>
                            <div class="password-container">
                                <input type="password" name="contrasena" id="password" placeholder="Mínimo 8 caracteres" minlength="8" required>
                                <span class="password-toggle" onclick="togglePassword('password')"><i class="fas fa-eye"></i></span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Confirmar Contraseña <span class="required">*</span></label>
                            <div class="password-container">
                                <input type="password" name="confirmar_contrasena" id="confirm-password" placeholder="Repite tu contraseña" minlength="8" required>
                                <span class="password-toggle" onclick="togglePassword('confirm-password')"><i class="fas fa-eye"></i></span>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Colonia / Barrio <span class="required">*</span></label>
                            <input type="text" name="colonia" placeholder="Ej: Centro" required>
                        </div>

                        <div class="form-group">
                            <label>Calle y Número <span class="required">*</span></label>
                            <input type="text" name="direccion" placeholder="Ej: Av. Principal #123" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Sección Electoral <span class="required">*</span></label>
                            <input type="number" name="seccion_electoral" placeholder="Ej: 1234" min="1" required>
                        </div>

                        <div class="form-group">
                            <label>Rol <span class="required">*</span></label>
                            <select name="rol" required>
                                <option value="">Selecciona un rol</option>
                                <option value="simpatizante">Simpatizante</option>
                                <option value="voluntario">Voluntario</option>
                                <option value="promotor">Promotor</option>
                                <option value="lider_zona">Líder de Zona</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Adjuntar INE (PDF o Imagen) <span class="required">*</span></label>
                        <div class="file-upload">
                            <input type="file" name="ine_file" accept=".pdf,.jpg,.jpeg,.png" required id="ine-upload">
                            <div class="file-upload-content">
                                <div class="file-upload-icon"><i class="fas fa-file-alt"></i></div>
                                <div class="file-upload-text">
                                    <strong>Haz clic para seleccionar</strong> o arrastra tu archivo aquí<br>
                                    <small>Formatos: PDF, JPG, PNG (Max 5MB)</small>
                                </div>
                            </div>
                        </div>
                        <div class="file-name" id="file-name"></div>
                    </div>

                    <button type="submit" class="submit-btn">Crear Cuenta</button>

                    <div class="login-link">
                        ¿Ya tienes una cuenta? <a href="/Public/iniciar-sesion.php">Inicia sesión aquí</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <?php include __DIR__ . '/../Includes/components/footer.php'; ?>

    <script>
        // Manejo de archivo visual
        document.getElementById('ine-upload').addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name;
            const fileNameDiv = document.getElementById('file-name');
            if (fileName) {
                fileNameDiv.innerHTML = '<i class="fas fa-check-circle"></i> ' + fileName;
            } else {
                fileNameDiv.textContent = '';
            }
        });

        // Toggle contraseña
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.nextElementSibling.querySelector('i');

            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const passwordToggles = document.querySelectorAll('.password-toggle i');
            passwordToggles.forEach(icon => {
                icon.classList.add('fas', 'fa-eye');
            });
        });

        // --- MANEJO DE REGISTRO CON AJAX ---
        function showAlert(message, type) {
            const alertBox = document.getElementById('js-alert');
            alertBox.textContent = message;
            alertBox.className = 'alert ' + (type === 'error' ? 'alert-error' : 'alert-success');
            alertBox.style.display = 'block';
            
            // Scroll hacia la alerta si hay error
            if(type === 'error') {
                alertBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }

        async function handleRegister(event, form) {
            event.preventDefault();
            
            // Validación de contraseñas
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm-password').value;
            
            if (password !== confirmPassword) {
                showAlert('Las contraseñas no coinciden. Por favor verifica.', 'error');
                return;
            }

            const submitBtn = form.querySelector('.submit-btn');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creando cuenta...';

            const formData = new FormData(form);

            try {
                // Enviar a la API con ruta relativa correcta desde /Public/
                const response = await fetch('../Api/auth/register.php', {
                    method: 'POST',
                    body: formData
                });

                // Intentar parsear el JSON, si falla es que PHP devolvió un error fatal (HTML)
                let data;
                const textResponse = await response.text(); 
                try {
                    data = JSON.parse(textResponse);
                } catch (e) {
                    console.error("Respuesta no válida del servidor:", textResponse);
                    throw new Error("El servidor devolvió una respuesta no válida.");
                }

                if (data.success) {
                    showAlert('¡Registro exitoso! Redirigiendo al inicio de sesión...', 'success');
                    setTimeout(() => {
                        window.location.href = data.redirect || '/Public/iniciar-sesion.php';
                    }, 1500);
                } else {
                    showAlert(data.message || 'Error al registrar usuario.', 'error');
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