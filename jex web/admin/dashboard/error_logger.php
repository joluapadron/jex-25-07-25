<?php
// --- Script Manejador de Errores Personalizado ---
// Este script define una función para capturar errores de PHP y escribirlos en un archivo.
// Útil cuando no se tiene acceso directo a los logs de error del servidor.

// Define la ruta completa al archivo donde se guardarán los logs de error.
// Es RECOMENDABLE que este archivo esté FUERA del directorio accesible vía web (public_html)
// para evitar que alguien pueda leer tus logs sensibles.
// Reemplaza '/ruta/segura/fuera/de/public_html/php_custom_error.log' con una ruta real y segura en tu servidor.
// Por ejemplo: '/home/u123456789/php_custom_error.log' si tu public_html está en /home/u123456789/public_html
$log_file_path = __DIR__ . '/../logs/php_errors.log'; // *** CAMBIA ESTO ***

// Asegúrate de que el directorio del archivo de log existe y tiene permisos de escritura para el usuario del servidor web.
// Puedes necesitar crear la carpeta 'logs' fuera de public_html manualmente y darle permisos 755 o 775.

// --- Función Manejador de Errores ---
// Esta función será llamada por PHP cada vez que ocurra un error.
// Los parámetros son proporcionados automáticamente por PHP.
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    // Escribe en el log de depuración para confirmar que la función fue llamada
    file_put_contents('/tmp/debug_trace.log', "PASO 6: DENTRO de customErrorHandler. Intentando escribir en el log real.\n", FILE_APPEND);

    global $log_file_path;// Accede a la ruta del archivo de log definida globalmente

    // Define un array con los tipos de error más comunes para hacer el log más legible.
    $error_types = array(
        E_ERROR             => 'Error Fatal',
        E_WARNING           => 'Advertencia',
        E_PARSE             => 'Error de Parseo',
        E_NOTICE            => 'Aviso',
        E_CORE_ERROR        => 'Error de Núcleo',
        E_CORE_WARNING      => 'Advertencia de Núcleo',
        E_COMPILE_ERROR     => 'Error de Compilación',
        E_COMPILE_WARNING   => 'Advertencia de Compilación',
        E_USER_ERROR        => 'Error de Usuario',
        E_USER_WARNING      => 'Advertencia de Usuario',
        E_USER_NOTICE       => 'Aviso de Usuario',
        E_STRICT            => 'Estricto',
        E_RECOVERABLE_ERROR => 'Error Recuperable',
        E_DEPRECATED        => 'Obsoleto',
        E_USER_DEPRECATED   => 'Obsoleto de Usuario'
    );

    // Obtiene el tipo de error en formato de texto.
    $errortype = isset($error_types[$errno]) ? $error_types[$errno] : 'Error Desconocido';

    // Formatea el mensaje de log.
    $log_message = date("Y-m-d H:i:s") . " [" . $errortype . "]: " . $errstr . " in " . $errfile . " on line " . $errline . "\n";

    // Escribe el mensaje de log en el archivo especificado.
    // FILE_APPEND asegura que se añada al final del archivo existente.
    // LOCK_EX asegura que solo un proceso escriba a la vez (evita corrupción si hay muchos errores simultáneos).
    file_put_contents($log_file_path, $log_message, FILE_APPEND | LOCK_EX);

    // Si el error es fatal (E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR),
    // puedes detener la ejecución del script aquí para evitar mostrar más errores o comportamiento inesperado.
    // En producción, generalmente quieres que los errores fatales detengan la ejecución.
    switch ($errno) {
        case E_ERROR:
        case E_PARSE:
        case E_CORE_ERROR:
        case E_COMPILE_ERROR:
        case E_USER_ERROR:
        case E_RECOVERABLE_ERROR:
            // Opcional: Mostrar un mensaje genérico al usuario en lugar de la página de error predeterminada.
            // En un entorno de producción, NUNCA muestres detalles del error aquí.
            // echo "Se ha producido un error crítico. Por favor, inténtalo de nuevo más tarde.";
            // exit; // Detiene la ejecución
            break;

        default:
            // Para otros tipos de errores (warnings, notices), puedes simplemente registrarlos y permitir que el script continúe.
            break;
    }

    // No ejecutes el manejador de errores interno de PHP.
    return true;
}

// --- Configurar PHP para Usar el Manejador de Errores Personalizado ---
// Desactiva la visualización de errores en el navegador (CRÍTICO para producción).
// Esto evita que los detalles del error se muestren a los usuarios.
ini_set('display_errors', 'Off');

// Asegura que el registro de errores esté activado.
ini_set('log_errors', 'On');

// Establece nuestro manejador de errores personalizado como el manejador predeterminado de PHP.
set_error_handler("customErrorHandler");

// Opcional: También puedes configurar un manejador para errores fatales que no son capturados por set_error_handler.
// register_shutdown_function('handleShutdown');
// function handleShutdown() {
//     $last_error = error_get_last();
//     if ($last_error && ($last_error['type'] === E_ERROR || $last_error['type'] === E_PARSE || $last_error['type'] === E_CORE_ERROR || $last_error['type'] === E_COMPILE_ERROR || $last_error['type'] === E_RECOVERABLE_ERROR)) {
//         // Llama a nuestro manejador personalizado para registrar el error fatal.
//         customErrorHandler($last_error['type'], $last_error['message'], $last_error['file'], $last_error['line']);
//         // Opcional: Mostrar un mensaje genérico al usuario aquí también.
//     }
// }

// Nota: Incluye este script (usando require_once) al principio de TODOS tus archivos PHP
// donde quieras capturar errores.
?>
