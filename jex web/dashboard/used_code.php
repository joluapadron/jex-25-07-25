<?php
// --- Script para Marcar Código como Usado ---
// Este script recibe una URL (y opcionalmente un mail) vía POST
// y marca el código correspondiente en la base de datos como usado.
// Diseñado para ser llamado vía AJAX o formulario POST.

// Inicia o reanuda la sesión existente.
// Necesario para acceder a variables de sesión como $_SESSION['alias'].
session_start();

// Incluye archivos esenciales.
// Asegúrate de que estos archivos estén ubicados de forma segura fuera de public_html.
require_once "../settings/database.php"; // Contiene la lógica de conexión a la BD
require_once "../utils/save_logs.php"; // Contiene la función saveLog()

// --- Conexión a la Base de Datos ---
// Obtiene la conexión a la base de datos.
$conn = getDBConnection();

// --- Manejo de Errores de Conexión a BD ---
// Verifica si la conexión a la base de datos fue exitosa.
if ($conn === false) {
    // En producción, registra el error y muestra un mensaje genérico o respuesta de error JSON.
    error_log("Error de conexión a la base de datos en script de marcar código usado: " . mysqli_connect_error());
    // Si este script es llamado por AJAX, es mejor devolver una respuesta JSON de error.
    header('Content-Type: application/json');
    echo json_encode(array("status" => "error", "message" => "Error interno del servidor al conectar a la base de datos."));
    exit(); // Detiene la ejecución
}

// --- Manejar Solicitud POST ---
// Verifica que la solicitud sea de tipo POST y que se haya enviado la 'url'.
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['url'])) {

    // Obtiene y limpia la entrada del usuario.
    // trim() elimina espacios en blanco al inicio y final.
    // htmlspecialchars() NO se usa aquí porque los valores se usan internamente (consulta BD, log).
    // La sanitización para la BD se maneja con sentencias preparadas.
    $url = trim($_POST['url']); // Obtiene la URL y elimina espacios
    $mail = trim($_POST['mail'] ?? ''); // Obtiene el mail (opcional) y elimina espacios, usa ?? ''

    // --- Validación Básica de Entrada ---
    // Verifica que la URL no esté vacía.
    if (empty($url)) {
         header('Content-Type: application/json');
         echo json_encode(array("status" => "error", "message" => "URL es requerida."));
         mysqli_close($conn); // Cierra la conexión antes de salir
         exit();
    }

    // --- Actualizar el Estado 'used' en la Base de Datos ---
    // Sentencia preparada para actualizar el campo 'used' a 1 donde la 'url' coincida.
    $updateQuery = "UPDATE codes SET used = 1 WHERE url = ?";
    $stmt = $conn->prepare($updateQuery);

    // Manejo de error al preparar la sentencia.
    if ($stmt === false) {
        error_log("Error al preparar sentencia de actualización de código usado: " . $conn->error);
        header('Content-Type: application/json');
        echo json_encode(array("status" => "error", "message" => "Error interno al preparar actualización."));
        mysqli_close($conn); // Cierra la conexión antes de salir
        exit();
    }

    // Liga el parámetro URL. 's' indica tipo string.
    // Se incluye manejo de error para la operación de ligar parámetros.
    if (!$stmt->bind_param("s", $url)) {
        error_log("Error al ligar parámetro en actualización de código usado: " . $stmt->error);
        header('Content-Type: application/json');
        echo json_encode(array("status" => "error", "message" => "Error interno al ligar parámetros."));
        $stmt->close(); // Cierra la sentencia
        mysqli_close($conn); // Cierra la conexión
        exit();
    }

    // Ejecuta la sentencia preparada.
    // Se incluye manejo de error para la operación de ejecución.
    if (!$stmt->execute()) {
        error_log("Error al ejecutar actualización de código usado: " . $stmt->error);
        header('Content-Type: application/json');
        echo json_encode(array("status" => "error", "message" => "Error al actualizar el código."));
        $stmt->close(); // Cierra la sentencia
        mysqli_close($conn); // Cierra la conexión
        exit();
    }

    // Cierra la sentencia después de una ejecución exitosa.
    $stmt->close();

    // --- Guardar Registro de Log ---
    // Verifica si la variable de sesión 'alias' está seteada.
    // Pasa el objeto de conexión $conn a la función saveLog().
    if (isset($_SESSION['alias'])) {
        // Asegúrate de que saveLog maneje la sanitización si es necesario,
        // pero $mail y $url ya se obtuvieron de forma segura.
        saveLog($conn, $_SESSION['alias'], "Email: " . $mail . " Read: " . $url);
    } else {
        // Si el usuario no está logueado (lo cual no debería pasar si se llama desde el dashboard de usuario),
        // registra un log con un indicador de no logueado.
        saveLog($conn, "Not logged in!", "Email: " . $mail . " Read: " . $url);
    }

    // --- Respuesta de Éxito ---
    // Devuelve una respuesta JSON indicando éxito.
    header('Content-Type: application/json');
    echo json_encode(array("status" => "success", "message" => "Código marcado como usado."));

} else {
    // Si la solicitud no es POST o no incluye la 'url', devuelve un error JSON.
    header('Content-Type: application/json');
    echo json_encode(array("status" => "error", "message" => "Solicitud inválida."));
}

// --- Cierra la Conexión a la Base de Datos ---
// Asegura que la conexión se cierre al final del script.
if ($conn) { // Verifica si $conn fue asignada antes de intentar cerrarla
    mysqli_close($conn);
}

// Nota: Este script está diseñado para ser llamado vía AJAX o directamente por otro script.
// La salida es JSON.
?>
