<?php
// Incluye el manejador de errores
require_once __DIR__ . '/admin/dashboard/error_logger.php';

session_start();

// --- Dependencias y configuración ---
if (!function_exists('getDBConnection')) {
    if (file_exists("settings/database.php")) { include_once("settings/database.php"); } 
    else { die("Error interno del servidor."); }
}
if (!isset($langHandler)) {
    if (file_exists("languages/lang_handler.php")) {
        include_once("languages/lang_handler.php");
        if (class_exists('LangHandler')) { try { $langHandler = new LangHandler('es'); } catch (Exception $e) { $langHandler = new stdClass(); } }
    }
}
function safeTranslation($langHandler, $section, $key, $default = '') {
    if (!is_object($langHandler) || !method_exists($langHandler, 'getTranslation')) { return $default; }
    try { $translation = $langHandler->getTranslation($section, $key); return $translation !== null ? $translation : $default; } 
    catch (Exception $e) { return $default; }
}
if (!isset($SITE_KEY)) {
    if (file_exists("settings/recaptcha.php")) { include_once("settings/recaptcha.php"); }
    if (!isset($SITE_KEY)) { $SITE_KEY = 'YOUR_DEFAULT_RECAPTCHA_SITE_KEY'; }
}
$conn = function_exists('getDBConnection') ? getDBConnection() : null;
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(safeTranslation($langHandler, 'SITE', 'LANGUAGE', 'es'), ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Autogestión</title>
    
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>
<body>

    <header class="main-header">
        <div class="header-content">
            <div class="logo">
                <a href="/index">
                    <img src="img/logo.png" alt="Logo JEX.LA">
                </a>
            </div>
            <div class="nav-wrapper">
                <nav class="main-nav">
                    <a href="/index#inicio">Inicio</a>
                    <a href="/index#nosotros">Nosotros</a>
                    <a href="/index#modelo">Modelo de Negocio</a>
                    <a href="/index#plataformas">Plataformas</a>
                    <a href="/Autogestion">Autogestión</a>
                </nav>
                <a href="#" class="login-button">Ingresar</a>
            </div>
            <button class="mobile-nav-toggle" aria-label="toggle navigation">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </header>

    <main class="login-page-main">
        <div class="login-container">
            <div class="login-box">
                <h2>Obtener Código</h2>
                
                <form action="obtener_codigo.php" method="post" id="loginForm">
                    <div class="input-group">
                        <label for="alias">Usuario</label>
                        <input type="text" id="alias" name="alias" required>
                    </div>

                    <div class="input-group">
                        <label for="pin">Contraseña</label>
                        <input type="password" id="pin" name="pin" required>
                    </div>

                    <div class="g-recaptcha" data-sitekey="<?php echo htmlspecialchars($SITE_KEY, ENT_QUOTES, 'UTF-8'); ?>" data-theme="dark"></div>

                    <?php
                    if (isset($_SESSION['login_error'])) {
                        // ... (código para mostrar errores) ...
                        $error_key = $_SESSION['login_error'];
                        $error_messages = [
                            'invalid_credentials' => "Usuario o Contraseña incorrectos",
                            'invalidcaptcha'      => "Verificación reCAPTCHA fallida",
                            'all_fields_required' => "Todos los campos son requeridos",
                            'account_suspended'   => "Tu cuenta ha sido suspendida.",
                            'server_error'        => "Error interno del servidor.",
                        ];
                        $message = $error_messages[$error_key] ?? "Error desconocido";
                        echo '<p class="error-message">' . htmlspecialchars($message) . '</p>';
                        unset($_SESSION['login_error']);
                    }
                    ?>
                    <button type="submit" class="submit-button">Obtener Código</button>
                </form>
            </div>
        </div>
    </main>

    <script src="script.js"></script>
    <?php if ($conn) mysqli_close($conn); ?>
</body>
</html>