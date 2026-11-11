<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    /* Footer Styles */
    footer {
        background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
        color: white;
        padding: 80px 60px 30px 60px;
        margin-top: 100px;
        
        /* --- CORRECCIÓN --- */
        /* Esto empuja el footer para que no quede detrás de la sidebar */
        /* Si tu sidebar mide más o menos de 250px, cambia este número */
        margin-left: 250px; 
    }

    .footer-content {
        max-width: 1400px;
        margin: 0 auto;
        display: grid;
        grid-template-columns: 2fr 1fr 1fr 1fr;
        gap: 60px;
        margin-bottom: 60px;
    }

    .footer-section h3 {
        font-size: 20px;
        font-weight: 700;
        margin-bottom: 25px;
        color: #a5d6a7;
        letter-spacing: 1px;
    }

    .footer-about {
        max-width: 400px;
    }

    .footer-logo {
        font-size: 36px;
        font-weight: 700;
        color: #2d7a3e;
        letter-spacing: 2px;
        margin-bottom: 20px;
    }

    .footer-about p {
        color: #ccc;
        line-height: 1.8;
        font-size: 14px;
        margin-bottom: 25px;
    }

    .social-links {
        display: flex;
        gap: 15px;
        margin-top: 20px;
    }

    .social-links a {
        width: 45px;
        height: 45px;
        background: rgba(45, 122, 62, 0.2);
        border: 2px solid #2d7a3e;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #2d7a3e;
        font-size: 18px;
        transition: all 0.3s ease;
        text-decoration: none;
    }

    .social-links a:hover {
        background: #2d7a3e;
        color: white;
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(45, 122, 62, 0.4);
    }

    .footer-links {
        list-style: none;
        padding: 0;
    }

    .footer-links li {
        margin-bottom: 15px;
    }

    .footer-links a {
        color: #ccc;
        text-decoration: none;
        font-size: 14px;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .footer-links a:hover {
        color: #2d7a3e;
        padding-left: 5px;
    }

    .footer-links a i {
        font-size: 12px;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .footer-links a:hover i {
        opacity: 1;
    }

    .footer-contact {
        list-style: none;
        padding: 0;
    }

    .footer-contact li {
        margin-bottom: 20px;
        display: flex;
        align-items: flex-start;
        gap: 15px;
        color: #ccc;
        font-size: 14px;
    }

    .footer-contact li i {
        color: #2d7a3e;
        font-size: 18px;
        margin-top: 2px;
        min-width: 20px;
    }

    .newsletter {
        margin-top: 20px;
    }

    .newsletter p {
        color: #ccc;
        font-size: 14px;
        margin-bottom: 15px;
        line-height: 1.6;
    }

    .newsletter-form {
        display: flex;
        gap: 10px;
        margin-top: 15px;
    }

    .newsletter-form input {
        flex: 1;
        padding: 12px 15px;
        border: 2px solid #444;
        background: rgba(255, 255, 255, 0.05);
        color: white;
        border-radius: 5px;
        font-size: 14px;
        transition: all 0.3s ease;
    }

    .newsletter-form input:focus {
        outline: none;
        border-color: #2d7a3e;
        background: rgba(255, 255, 255, 0.1);
    }

    .newsletter-form input::placeholder {
        color: #999;
    }

    .newsletter-form button {
        padding: 12px 25px;
        background: #2d7a3e;
        color: white;
        border: none;
        border-radius: 5px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 14px;
    }

    .newsletter-form button:hover {
        background: #1f5a2d;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(45, 122, 62, 0.3);
    }

    .footer-bottom {
        max-width: 1400px;
        margin: 0 auto;
        padding-top: 30px;
        border-top: 1px solid #444;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 20px;
    }

    .footer-bottom p {
        color: #999;
        font-size: 13px;
    }

    .footer-bottom-links {
        display: flex;
        gap: 30px;
    }

    .footer-bottom-links a {
        color: #999;
        text-decoration: none;
        font-size: 13px;
        transition: all 0.3s ease;
    }

    .footer-bottom-links a:hover {
        color: #2d7a3e;
    }

    .back-to-top {
        position: fixed;
        bottom: 30px;
        right: 30px;
        width: 50px;
        height: 50px;
        background: #2d7a3e;
        color: white;
        border: none;
        border-radius: 50%;
        font-size: 20px;
        cursor: pointer;
        display: none;
        align-items: center;
        justify-content: center;
        box-shadow: 0 5px 20px rgba(45, 122, 62, 0.4);
        transition: all 0.3s ease;
        z-index: 1000;
    }

    .back-to-top.visible {
        display: flex;
    }

    .back-to-top:hover {
        background: #1f5a2d;
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(45, 122, 62, 0.5);
    }

    @media (max-width: 1024px) {
        footer {
            padding: 60px 40px 30px 40px;
        }

        .footer-content {
            grid-template-columns: 1fr 1fr;
            gap: 40px;
        }
    }

    @media (max-width: 768px) {
        footer {
            padding: 40px 30px 20px 30px;
            /* --- CORRECCIÓN MÓVIL --- */
            /* En pantallas chicas, quitamos el margen porque la sidebar no está */
            margin-left: 0; 
        }

        .footer-content {
            grid-template-columns: 1fr;
            gap: 40px;
        }

        .footer-bottom {
            flex-direction: column;
            text-align: center;
        }

        .newsletter-form {
            flex-direction: column;
        }

        .newsletter-form button {
            width: 100%;
        }
    }
</style>
</head>
<body>
    <footer>
        <div class="footer-content">
            <!-- About Section -->
            <div class="footer-section footer-about">
                <div class="footer-logo">PROYECTO VERDE</div>
                <p>
                    Un movimiento ciudadano comprometido con el desarrollo sostenible, la justicia social y la protección del medio ambiente para las futuras generaciones de México.
                </p>
                <div class="social-links">
                    <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                    <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                    <a href="#" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
                    <a href="#" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="footer-section">
                <h3>Enlaces Rápidos</h3>
                <ul class="footer-links">
                    <li><a href="#"><i class="fas fa-chevron-right"></i> Inicio</a></li>
                    <li><a href="#"><i class="fas fa-chevron-right"></i> Nosotros</a></li>
                    <li><a href="#"><i class="fas fa-chevron-right"></i> Propuestas</a></li>
                    <li><a href="#"><i class="fas fa-chevron-right"></i> Noticias</a></li>
                    <li><a href="#"><i class="fas fa-chevron-right"></i> Eventos</a></li>
                    <li><a href="#"><i class="fas fa-chevron-right"></i> Contacto</a></li>
                </ul>
            </div>

            <!-- Participate -->
            <div class="footer-section">
                <h3>Participa</h3>
                <ul class="footer-links">
                    <li><a href="#"><i class="fas fa-chevron-right"></i> Únete al Proyecto</a></li>
                    <li><a href="#"><i class="fas fa-chevron-right"></i> Voluntariado</a></li>
                    <li><a href="#"><i class="fas fa-chevron-right"></i> Donaciones</a></li>
                    <li><a href="#"><i class="fas fa-chevron-right"></i> Colectivos</a></li>
                    <li><a href="#"><i class="fas fa-chevron-right"></i> Capacitaciones</a></li>
                    <li><a href="#"><i class="fas fa-chevron-right"></i> Mi Cuenta</a></li>
                </ul>
            </div>

            <!-- Contact & Newsletter -->
            <div class="footer-section">
                <h3>Contacto</h3>
                <ul class="footer-contact">
                    <li>
                        <i class="fas fa-map-marker-alt"></i>
                        <span>Av. Principal #123<br>Hermosillo, Sonora, México</span>
                    </li>
                    <li>
                        <i class="fas fa-phone"></i>
                        <span>(662) 123-4567</span>
                    </li>
                    <li>
                        <i class="fas fa-envelope"></i>
                        <span>contacto@proyectoverde.mx</span>
                    </li>
                </ul>

                <div class="newsletter">
                    <h3>Newsletter</h3>
                    <p>Recibe nuestras noticias y actualizaciones</p>
                    <form class="newsletter-form" onsubmit="return false;">
                        <input type="email" placeholder="Tu correo electrónico" required>
                        <button type="submit"><i class="fas fa-paper-plane"></i></button>
                    </form>
                </div>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; 2024 Proyecto Verde. Todos los derechos reservados.</p>
            <div class="footer-bottom-links">
                <a href="#">Privacidad</a>
                <a href="#">Términos</a>
                <a href="#">Cookies</a>
                <a href="#">Aviso Legal</a>
            </div>
        </div>
    </footer>

    <!-- Back to Top Button -->
    <button class="back-to-top" id="backToTop" aria-label="Volver arriba">
        <i class="fas fa-arrow-up"></i>
    </button>

    <script>
        // Back to Top functionality
        const backToTopBtn = document.getElementById('backToTop');

        window.addEventListener('scroll', () => {
            if (window.pageYOffset > 300) {
                backToTopBtn.classList.add('visible');
            } else {
                backToTopBtn.classList.remove('visible');
            }
        });

        backToTopBtn.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });

        // Newsletter form
        document.querySelector('.newsletter-form').addEventListener('submit', function(e) {
            e.preventDefault();
            const input = this.querySelector('input');
            if (input.value) {
                alert('¡Gracias por suscribirte! Recibirás nuestras actualizaciones pronto.');
                input.value = '';
            }
        });
    </script>
</body>
</html>