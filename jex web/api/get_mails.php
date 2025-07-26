<?php
// --- Script para Obtener y Marcar Código como Usado ---
// Este script busca un código/URL en la base de datos basado en mail, service y estado 'used',
// lo devuelve en formato JSON y opcionalmente lo marca como usado.
// Requiere una clave secreta para acceso.

// Configura la zona horaria a UTC para consistencia (opcional, pero buena práctica).
date_default_timezone_set('UTC');

// --- Verificación de Clave Secreta ---
// Es CRÍTICO que esta clave secreta sea FUERTE y NO sea fácil de adivinar.
// Idealmente, esta clave debería almacenarse de forma segura (ej: en un archivo de configuración fuera de public_html)
// y no hardcodeada aquí.
$expected_secret_key = '26124527'; // *** REEMPLAZA ESTO CON UNA CLAVE FUERTE Y SEGURA ***
$secret_key = $_GET['secret_key'] ?? ''; // Usa ?? '' para manejar si no está seteado

if ($secret_key !== $expected_secret_key) {
    // Registra el intento de acceso no autorizado.
    error_log("Intento de acceso no autorizado a script de código con clave: " . htmlspecialchars($secret_key));
    // Devuelve una respuesta JSON de error en lugar de solo "Acceso denegado".
    header('Content-Type: application/json'); // Especifica el tipo de contenido
    echo json_encode(array("error" => "Acceso denegado"));
    exit(); // Detiene la ejecución de forma segura
}

// --- Inclusión del Archivo de Conexión a la Base de Datos ---
// Asegúrate de que database.php esté ubicado FUERA del directorio accesible vía web (public_html).
// Ajusta la ruta relativa si es necesario.
require_once "../settings/database.php"; // Ajusta la ruta si database.php está en otra ubicación

// --- Conexión a la Base de Datos ---
$conn = getDBConnection();

// --- Manejo de Errores de Conexión a BD ---
if ($conn === false) {
    // Registra el error de conexión en el log del servidor.
    error_log("Error de conexión a la base de datos en script de código: " . mysqli_connect_error());
    // Devuelve una respuesta JSON de error.
    header('Content-Type: application/json');
    echo json_encode(array("error" => "Error interno del servidor al conectar a la base de datos."));
    exit(); // Detiene la ejecución
}

// --- Obtención y Sanitización de Parámetros de Entrada ---
// Obtiene los parámetros de la URL (GET).
// Se utiliza el operador de fusión de null (??) para manejar el caso en que los parámetros no estén seteados.
// Los valores se obtienen como cadenas. Se usarán de forma segura en consultas preparadas.
$mail = $_GET['mail'] ?? '';
$service = $_GET['service'] ?? '';
// Convierte 'used' a entero. Si no está seteado o no es numérico, por defecto es 0.
$used = isset($_GET['used']) ? (int)$_GET['used'] : 0;

// --- Validación Básica de Entrada ---
// Verifica que los parámetros esenciales no estén vacíos.
if (empty($mail) || empty($service)) {
     header('Content-Type: application/json');
     echo json_encode(array("error" => "Parámetros 'mail' y 'service' son requeridos."));
     mysqli_close($conn); // Cierra la conexión antes de salir
     exit();
}
// Opcional: Añadir validación adicional para formato de email o servicio si es necesario.


// --- Consulta para Obtener el Código/URL ---
// Consulta segura usando sentencia preparada para prevenir SQL Injection.
// Busca el primer código no usado (o usado, según el parámetro $used) para el mail y service dados.
$query = "SELECT id, url FROM codes WHERE mail = ? AND service = ? AND used = ? LIMIT 1";
$stmt = $conn->prepare($query);

// Manejo de error al preparar la sentencia.
if ($stmt === false) {
    error_log("Error al preparar consulta de código: " . $conn->error);
    header('Content-Type: application/json');
    echo json_encode(array("error" => "Error interno al preparar consulta de código."));
    mysqli_close($conn); // Cierra la conexión antes de salir
    exit();
}

// Liga los parámetros a la sentencia preparada. 'ssi' indica tipos: string, string, integer.
// Se incluye manejo de error para la operación de ligar parámetros.
if (!$stmt->bind_param('ssi', $mail, $service, $used)) {
    error_log("Error al ligar parámetros en consulta de código: " . $stmt->error);
    header('Content-Type: application/json');
    echo json_encode(array("error" => "Error interno al ligar parámetros para consulta de código."));
    $stmt->close(); // Cierra la sentencia
    mysqli_close($conn); // Cierra la conexión
    exit();
}

// Ejecuta la sentencia preparada.
// Se incluye manejo de error para la operación de ejecución.
if (!$stmt->execute()) {
    error_log("Error al ejecutar consulta de código: " . $stmt->error);
    header('Content-Type: application/json');
    echo json_encode(array("error" => "Error interno al ejecutar consulta de código."));
    $stmt->close(); // Cierra la sentencia
    mysqli_close($conn); // Cierra la conexión
    exit();
}

// Liga las variables a los resultados y obtiene la fila.
$stmt->bind_result($id, $url);
$stmt->fetch();

// Cierra la sentencia de selección después de obtener el resultado.
$stmt->close();

// --- Preparar y Devolver la Respuesta JSON ---
header('Content-Type: application/json'); // Especifica el tipo de contenido de la respuesta

// Si se encontró un código ($id tendrá un valor), devuelve la URL.
// Si no se encontró un código, $id será null (o 0 si la columna id es INT NOT NULL con auto_increment y no se encontró nada).
// Verificamos si $id tiene un valor válido (mayor que 0 si es INT con auto_increment).
if ($id > 0) {
    $response = array("url" => $url);
    echo json_encode($response);

    // --- Marcar Código como Usado (Si se encontró y $used era 0) ---
    // Solo marcamos como usado si la consulta original buscó códigos NO usados ($used == 0)
    // y encontró uno.
    if ($used == 0) {
        $updateQuery = "UPDATE codes SET used = 1 WHERE id = ?";
        $updateStmt = $conn->prepare($updateQuery);

        // Manejo de error al preparar la sentencia de actualización.
        if ($updateStmt === false) {
            error_log("Error al preparar sentencia de actualización de código: " . $conn->error);
            // Opcional: Podrías añadir un indicador de error en la respuesta JSON si la actualización falla.
            // $response["update_error"] = "Failed to prepare update.";
        } else {
            // Liga el parámetro ID. 'i' indica tipo integer.
            if (!$updateStmt->bind_param('i', $id)) {
                error_log("Error al ligar parámetro en actualización de código: " . $updateStmt->error);
                // Opcional: $response["update_error"] = "Failed to bind update parameter.";
            } elseif (!$updateStmt->execute()) {
                // Manejo de error al ejecutar la sentencia de actualización.
                error_log("Error al ejecutar actualización de código: " . $updateStmt->error);
                // Opcional: $response["update_error"] = "Failed to execute update.";
            }
            $updateStmt->close(); // Cierra la sentencia de actualización
        }
    }

} else {
    // Si no se encontró ningún código, devuelve una respuesta JSON indicando que no se encontró.
    $response = array("url" => null, "message" => "No unused code found for the specified mail and service.");
    echo json_encode($response);
}

// --- Cierra la Conexión a la Base de Datos ---
mysqli_close($conn);

// Nota: Este script está diseñado para ser llamado vía AJAX o directamente por otro script.
// La salida es JSON.
?>
