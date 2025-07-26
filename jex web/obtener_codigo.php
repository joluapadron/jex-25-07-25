<?php
// 1. Incluir el manejador de errores PRIMERO que nada.
require_once __DIR__ . '/admin/dashboard/error_logger.php';

// 2. Iniciar sesión
session_start();

// 3. Incluir dependencias
require_once "settings/database.php";
require_once "utils/save_logs.php";
require_once "settings/recaptcha.php";

// 4. Solo procesar si el método es POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /');
    exit;
}

try {
    $alias = trim($_POST['alias'] ?? '');
    $pin = trim($_POST['pin'] ?? '');
    $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';

    if (empty($alias) || empty($pin) || empty($recaptcha_response)) {
        throw new Exception('all_fields_required');
    }

    // ... (Lógica de reCAPTCHA) ...
    $recaptcha_url = 'https://www.google.com/recaptcha/api/siteverify';
    $recaptcha_secret = $SECRET_KEY;
    $recaptcha_data = http_build_query(['secret' => $recaptcha_secret, 'response' => $recaptcha_response]);
    $recaptcha_result_json = file_get_contents($recaptcha_url . '?' . $recaptcha_data);
    $recaptcha_result = json_decode($recaptcha_result_json);

    if (!$recaptcha_result || !$recaptcha_result->success) {
        throw new Exception('invalidcaptcha');
    }

    $conn = getDBConnection();
    if (!$conn) {
        throw new Exception('server_error');
    }

    $sql = "SELECT id, alias, pin, is_paused FROM users WHERE alias = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $alias);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        if ($user['is_paused'] == 1) {
            throw new Exception('account_suspended');
        }
        if (password_verify($pin, $user['pin'])) {
            // Éxito
            session_regenerate_id(true);
            $_SESSION['loggedin'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['alias'] = $user['alias'];
            $stmt->close();
            $conn->close();
            header('Location: dashboard/codigos');
            exit;
        }
    }
    
    throw new Exception('invalid_credentials');

} catch (Exception $e) {
    // Llama manualmente a tu manejador para registrar el intento de login fallido.
    customErrorHandler(E_USER_NOTICE, "Intento de login fallido: " . $e->getMessage(), __FILE__, __LINE__);
    
    $_SESSION['login_error'] = $e->getMessage();
    
    header('Location: /Autogestion');
    exit;
}
?>