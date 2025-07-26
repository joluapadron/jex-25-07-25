<?php
// --- Script de Utilidad para Guardar Logs ---
// Este archivo contiene la función para insertar registros en la tabla 'logs'.

// Este archivo NO debe incluir database.php si la conexión se pasa como parámetro.
// El script que llama a saveLog debe manejar la conexión a la base de datos.
// require_once "../settings/database.php"; // Elimina esta línea si existe en tu save_logs.php

// --- Función para Guardar un Registro de Log ---
// Esta función inserta una entrada en la tabla 'logs'.
// Recibe el objeto de conexión a la base de datos ($conn) como primer parámetro.
// $conn: Objeto de conexión mysqli abierto.
// $user: Nombre del usuario o identificador relacionado con la acción.
// $action: Descripción de la acción realizada.
function saveLog($conn, $user, $action) {
    // Verifica si el objeto de conexión recibido es válido antes de intentar usarlo.
    // Esto previene errores si el script que llama no pasa una conexión válida.
    if ($conn === false || $conn === null || !($conn instanceof mysqli) || $conn->connect_error) {
        // Si la conexión no es válida, registra un error en el log del servidor
        // y sale de la función para evitar un error fatal.
        error_log("saveLog: Se intentó guardar log con una conexión a base de datos inválida.");
        return false; // Indica que la operación falló
    }

    // Obtiene la dirección IP del usuario de forma segura.
    // Usa el operador de fusión de null (??) para manejar el caso en que $_SERVER['REMOTE_ADDR'] no esté definido.
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'N/A';

    // Prepara la consulta SQL para insertar el registro de log.
    // Se utilizan marcadores de posición (?) para los valores que se insertarán,
    // previniendo la inyección SQL.
    $query = "INSERT INTO logs (user, action, ip, date) VALUES (?, ?, ?, NOW())";
    $stmt = $conn->prepare($query);

    // Verifica si la preparación de la sentencia falló.
    if ($stmt === false) {
        // Registra el error de preparación de la consulta en el log del servidor.
        error_log("saveLog: Error al preparar consulta de inserción de log: " . $conn->error);
        return false; // Indica que la operación falló
    }

    // Liga los parámetros a la sentencia preparada.
    // 'sss' indica que los tres parámetros son de tipo string (user, action, ip).
    // Se incluye manejo de error para la operación de ligar parámetros.
    if (!$stmt->bind_param("sss", $user, $action, $ip)) {
        // Registra el error de ligado de parámetros en el log del servidor.
        error_log("saveLog: Error al ligar parámetros para inserción de log: " . $stmt->error);
        $stmt->close(); // Cierra la sentencia antes de salir
        return false; // Indica que la operación falló
    }

    // Ejecuta la sentencia preparada para insertar el registro.
    // Se incluye manejo de error para la operación de ejecución.
    if (!$stmt->execute()) {
        // Registra el error de ejecución de la consulta en el log del servidor.
        error_log("saveLog: Error al ejecutar consulta de inserción de log: " . $stmt->error);
        $stmt->close(); // Cierra la sentencia antes de salir
        return false; // Indica que la operación falló
    }

    // Cierra la sentencia preparada después de una ejecución exitosa.
    $stmt->close();

    return true; // Indica que la operación fue exitosa
}

// Nota: Este archivo SOLO debe contener la definición de la función saveLog.
// NO debe contener código que se ejecute directamente o que cierre la conexión a la base de datos.
?>
