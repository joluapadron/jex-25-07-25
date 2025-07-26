<!DOCTYPE html>
require_once __DIR__ . '/admin/dashboard/error_logger.php';
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JEX.LA - Tu Solución Completa</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>

    <header class="main-header">
        <div class="header-content">
            <div class="logo">
                <a href="#inicio">
                    <img src="img/logo.png" alt="Logo JEX.LA">
                </a>
            </div>

            <div class="nav-wrapper">
                <nav class="main-nav">
                    <a href="#inicio">Inicio</a>
                    <a href="#nosotros">Nosotros</a>
                    <a href="#modelo">Modelo de Negocio</a>
                    <a href="#plataformas">Plataformas</a>
                    <a href="/Autogestion">Autogestión</a>
                </nav>
                <a href="#" class="login-button">Ingresar</a>
            </div>

            <button class="mobile-nav-toggle" aria-label="toggle navigation">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </header>

    <main>
        <section id="inicio" class="hero-section">
            <div class="hero-content">
                <h1>El Mejor Entretenimiento en Línea</h1>
                <p>Todas tus plataformas favoritas en un solo lugar.</p>
            </div>
        </section>

        <section id="nosotros" class="content-section">
            <div class="content-image">
                <img src="img/nosotros.jpg" alt="Personas viendo TV">
            </div>
            <div class="content-text">
                <h2>Nosotros</h2>
                <p>Somos una plataforma líder en la distribución de entretenimiento digital. Con años de experiencia, ofrecemos acceso a las mejores plataformas y más de 1,500 títulos activos. Nuestra misión es darte el mejor contenido al mejor precio.</p>
            </div>
        </section>

        <section id="modelo" class="content-section reverse">
             <div class="content-text">
                <h2>Modelo de Negocio</h2>
                <p>Nuestro modelo se basa en ofrecerte la mayor flexibilidad. Adquiere cuentas 100% legales y renovables, con soporte en línea y acceso desde cualquier dispositivo. Olvídate de los contratos y disfruta a tu ritmo.</p>
                <ul class="features-list">
                    <li>✓ Cuentas 100% legales</li>
                    <li>✓ Cuentas Renovables</li>
                    <li>✓ Cuentas Personalizadas</li>
                    <li>✓ Garantías en Red</li>
                    <li>✓ Soporte en línea</li>
                    <li>✓ Más de 1,500 puntos de carga</li>
                </ul>
            </div>
            <div class="content-image">
                <img src="img/modelo.jpg" alt="Hombre usando tablet">
            </div>
        </section>
        
        <section id="plataformas" class="platforms-section">
            <h2>Conoce las Plataformas que Manejamos</h2>
            <div class="platforms-grid">
                <?php
                    // Ruta a la carpeta de imágenes de servicios
                    $dir = 'img/services/';
                    // Escanea el directorio y filtra para obtener solo archivos de imagen
                    $files = scandir($dir);
                    if ($files !== false) {
                        $images = preg_grep('/\.(jpg|jpeg|png|gif)$/i', $files);
                        // Itera sobre cada imagen y crea una tarjeta para ella
                        foreach ($images as $image) {
                            // Extrae el nombre del servicio del nombre del archivo
                            $serviceName = pathinfo($image, PATHINFO_FILENAME);
                            echo '<div class="platform-card">';
                            echo '<img src="' . htmlspecialchars($dir . $image) . '" alt="' . htmlspecialchars($serviceName) . '">';
                            echo '</div>';
                        }
                    }
                ?>
            </div>
        </section>
    </main>

    <script src="script.js"></script>
</body>
</html>