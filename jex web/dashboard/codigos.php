<?php
// --- 1. INICIALIZACIÓN Y SEGURIDAD ---
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/dashboard/error_logger.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/settings/site.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/languages/lang_handler.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/settings/database.php';

// Verificación de Autenticación
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: /Autogestion.php'); // Ajustado para ser más explícito
    exit;
}
if (isset($_SESSION['is_paused']) && $_SESSION['is_paused'] == 1) {
    die("Tu cuenta ha sido suspendida.");
}

// --- 2. CONFIGURACIÓN Y CONEXIÓN A BD ---
$lang = $_SESSION["lang"] ?? $default_language ?? 'es';
$langHandler = new LangHandler($lang);
$conn = getDBConnection();
if (!$conn) {
    customErrorHandler(E_USER_ERROR, "Fallo de conexión a la BD en la página de códigos.", __FILE__, __LINE__);
    die("Error de conexión.");
}

// --- 3. LÓGICA DE COOLDOWN ---
$cooldown_seconds = 15;
if (isset($_SESSION['last_request']) && (time() - $_SESSION['last_request']) < $cooldown_seconds) {
    $wait_seconds = $cooldown_seconds - (time() - $_SESSION['last_request']);
    include __DIR__ . '/cooldown_page.php'; 
    exit;
}
$_SESSION['last_request'] = time();

// --- 4. OBTENCIÓN DE DATOS ---
$stmt_user = $conn->prepare("SELECT mails, services FROM users WHERE alias = ?");
$stmt_user->bind_param("s", $_SESSION['alias']);
$stmt_user->execute();
$user_data = $stmt_user->get_result()->fetch_assoc();
$stmt_user->close();

$mails = $user_data['mails'] ?? '';
$services = $user_data['services'] ?? '';
$latest_code = null;

if (!empty($mails) && !empty($services)) {
    $stmt_code = $conn->prepare("SELECT id, url, service, mail, date FROM codes WHERE FIND_IN_SET(mail, ?) AND FIND_IN_SET(UPPER(service), UPPER(?)) AND used != 1 AND date >= DATE_SUB(NOW(), INTERVAL 14 MINUTE) ORDER BY id DESC LIMIT 1");
    $stmt_code->bind_param("ss", $mails, $services);
    $stmt_code->execute();
    $latest_code = $stmt_code->get_result()->fetch_assoc();
    $stmt_code->close();
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang, ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Códigos</title>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>

    <header class="main-header">
        <div class="header-content">
            <div class="logo">
                <a href="/index.php">
                    <img src="/img/logo.png" alt="Logo JEX.LA">
                </a>
            </div>
            <div class="nav-wrapper">
                <nav class="main-nav">
                    <a href="/index.php#inicio">Inicio</a>
                    <a href="/index.php#nosotros">Nosotros</a>
                    <a href="/index.php#modelo">Modelo de Negocio</a>
                    <a href="/index.php#plataformas">Plataformas</a>
                    <a href="/Autogestion.php">Autogestión</a>
                </nav>
                <a href="/dashboard/logout.php?redirect=/Autogestion.php" class="login-button">Cerrar Sesión</a>
                </div>
            <button class="mobile-nav-toggle" aria-label="toggle navigation">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </header>

    <main class="page-main">
        <div class="container">
            <div class="content-box">
                <h2>Tus Códigos Recientes</h2>
                <div class="content-body">
                    <?php if (empty($mails)): ?>
                        <div class="alert-message">No tienes correos asignados a tu cuenta.</div>
                    <?php elseif ($latest_code): ?>
                        <?php
                            // Preparamos los datos para la vista
                            $btn_id = "code_btn_" . htmlspecialchars($latest_code['id']);
                            $url_div_id = "url_div_" . htmlspecialchars($latest_code['id']);
                            $service_name = htmlspecialchars($latest_code['service']);
                            $mail_name = htmlspecialchars($latest_code['mail']);
                            $code_url = htmlspecialchars($latest_code['url']);
                            
                            $js_data = json_encode([
                                'url'      => $latest_code['url'],
                                'btnId'    => $btn_id,
                                'urlDivId' => $url_div_id,
                                'mail'     => $latest_code['mail']
                            ]);
                        ?>
                        <div class="code-card">
                            <div class="code-card-header">
                                <img src="/img/services/<?php echo $service_name; ?>.png" alt="<?php echo $service_name; ?>" class="service-icon" onerror="this.style.display='none'">
                                <h4><?php echo $mail_name; ?></h4>
                            </div>
                            <div class="code-card-body">
                                <button id="<?php echo $btn_id; ?>" onclick='showCode(<?php echo $js_data; ?>)' class="btn-reveal">Revelar Código</button>
                                <div id="<?php echo $url_div_id; ?>" class="code-display" style="display:none;">
                                    <?php if (filter_var($code_url, FILTER_VALIDATE_URL)): ?>
                                        <a href="<?php echo $code_url; ?>" target="_blank" class="btn-login-account">Iniciar Sesión en la Cuenta</a>
                                    <?php else: ?>
                                        <p>Código:</p>
                                        <span class="code-text"><?php echo $code_url; ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert-message">No hay códigos recientes disponibles.</div>
                    <?php endif; ?>

                    <div class="refresh-container">
                        <button onclick='location.reload();' class="btn-refresh">Actualizar</button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="/script.js"></script>
    <script>
        function showCode(data) {
            const div = document.getElementById(data.urlDivId); 
            const button = document.getElementById(data.btnId);
            
            if (div) div.style.display = 'block';
            if (button) button.style.display = 'none';

            // La ruta a used_code.php también debe ser correcta. 
            // Asumiendo que está en la misma carpeta que codigos.php
            fetch('used_code.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'url=' + encodeURIComponent(data.url) + '&mail=' + encodeURIComponent(data.mail)
            });
        }
    </script>
</body>
</html>