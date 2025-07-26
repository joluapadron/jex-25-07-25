<?php
/**
 * login.php - Procesa la autenticación del administrador.
 *
 * @version 2.0
 */

// 1. Iniciar el búfer de salida y la sesión. DEBE ser lo primero.
ob_start();
session_start();

// 2. Incluir dependencias después de iniciar la sesión.
require_once "../settings/database.php"; // Lógica de conexión a la base de datos
require_once "../utils/save_logs.php";   // Función para guardar logs

// --- Verificación de la Solicitud ---
// Asegura que solo se procesen solicitudes de tipo POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// --- Obtención y Validación de Datos ---
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

// Verifica que los campos no estén vacíos.
if (empty($username) || empty($password)) {
    header('Location: index.php?error=' . urlencode('Por favor, completa todos los campos.'));
    exit;
}

// --- Conexión a la Base de Datos ---
$conn = getDBConnection();
if ($conn === false) {
    error_log("Error de conexión a la base de datos en login de admin: " . mysqli_connect_error());
    header('Location: index.php?error=' . urlencode('Error interno del servidor.'));
    exit;
}

// --- Autenticación Segura ---
try {
    // Prepara la consulta para obtener el hash de la contraseña.
    $sql = "SELECT user, pass FROM admin WHERE user = ?";
    $stmt = mysqli_prepare($conn, $sql);

    if ($stmt === false) {
        throw new Exception("Error al preparar la consulta: " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($result && $row = mysqli_fetch_assoc($result)) {
        // Usuario encontrado, ahora verifica la contraseña.
        if (password_verify($password, $row['pass'])) {
            // ¡Contraseña correcta! Inicio de sesión exitoso.
            mysqli_stmt_close($stmt);

            // Regenera el ID de sesión para prevenir ataques de fijación de sesión.
            session_regenerate_id(true);

            // Establece las variables de sesión.
            $_SESSION['loggedin'] = true;
            $_SESSION['username'] = $row['user'];
            $_SESSION['is_admin'] = true;

            // Guarda el log y redirige al dashboard.
            saveLog($conn, $row['user'], "Logged in as admin");
            header('Location: dashboard/');
            exit;

        } else {
            // Contraseña incorrecta.
            throw new Exception("Contraseña incorrecta para el usuario: " . $username);
        }
    } else {
        // Usuario no encontrado.
        throw new Exception("Usuario no encontrado: " . $username);
    }
} catch (Exception $e) {
    // Bloque para manejar cualquier error y asegurar una respuesta consistente.
    $log_message = "Intento de login fallido para '$username': " . $e->getMessage();
    error_log($log_message); // Guarda el error detallado para el administrador.
    saveLog($conn, $username, "Failed login attempt"); // Guarda un log genérico.

    // Redirige con un mensaje de error genérico para el usuario.
    // No se revela si el usuario o la contraseña fueron incorrectos.
    header('Location: index.php?error=' . urlencode('Usuario o contraseña incorrectos.'));
    exit;
} finally {
    // Este bloque se ejecuta siempre, asegurando que la conexión se cierre.
    if (isset($stmt) && $stmt) mysqli_stmt_close($stmt);
    if ($conn) mysqli_close($conn);
    ob_end_flush(); // Envía el búfer de salida (útil si hay redirecciones).
}
?>
