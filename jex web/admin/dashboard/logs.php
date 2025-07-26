<?php
// Inicia o reanuda la sesión existente.
session_start();

// --- Verificación de Seguridad: Usuario Autenticado y Administrador ---
// Verifica si el usuario ha iniciado sesión Y si está marcado como administrador en la sesión.
// Si no cumple los requisitos, redirige a la página de inicio de sesión del administrador.
// Asumimos que '../index.php' es la ruta correcta a la página de login del admin
// relativa a la ubicación de este archivo (ej: si este es admin/dashboard/logs.php,
// '../index.php' apunta a admin/index.php).
// Si tu página de login del admin usa una URL limpia manejada por .htaccess (ej: https://jex.lat/admin/),
// considera cambiar la línea de redirección a: header('Location: /admin/');
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: ../index.php'); // Redirección a la página de login del admin
    exit; // Es crucial detener la ejecución del script después de una redirección
}

// --- Inclusión de Archivos Esenciales ---
// Incluye archivos necesarios. Asegúrate de que estos archivos (especialmente database.php)
// no sean accesibles directamente a través del navegador (colócalos fuera del directorio
// raíz del servidor web si es posible) y que contengan código seguro.
include_once("header.php"); // Contiene la cabecera HTML (y posiblemente enlaces de navegación)
include_once("../../settings/database.php"); // Contiene la lógica de conexión a la BD (CRÍTICO para seguridad)

// --- Conexión a la Base de Datos ---
// Obtiene la conexión a la base de datos utilizando la función definida en database.php.
// Esta función debe manejar la conexión de forma segura (credenciales fuera del alcance público, etc.).
$conn = getDBConnection();

// --- Manejo Básico de Errores de Conexión a BD ---
// Verifica si la conexión a la base de datos fue exitosa.
if ($conn === false) {
    // En un entorno de producción real, NUNCA muestres detalles del error al usuario.
    // Registra el error completo en un archivo de log del servidor para depuración.
    error_log("Error de conexión a la base de datos: " . mysqli_connect_error()); // Registra el error
    // Muestra un mensaje genérico de error al usuario.
    die("Error interno del servidor. Por favor, inténtalo de nuevo más tarde.");
}


// --- Obtención y Sanitización de Parámetros de Paginación y Filtro ---
// Obtiene los parámetros de la URL (GET) para paginación y filtros.
// Se utiliza (int) para asegurar que 'page' y 'pageSize' son números enteros, previniendo inyección simple.
// Los valores de filtro se obtienen como cadenas. Se usarán de forma segura en consultas preparadas.
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$pageSize = isset($_GET['pageSize']) ? (int)$_GET['pageSize'] : 10;
$usernameFilter = isset($_GET['username']) ? $_GET['username'] : '';
$actionFilter = isset($_GET['action']) ? $_GET['action'] : '';
$ipFilter = isset($_GET['ip']) ? $_GET['ip'] : '';

// Calcula el offset (desplazamiento) para la consulta SQL basado en la página y el tamaño de página.
$offset = ($page - 1) * $pageSize;
// Asegura que el offset no sea negativo.
if ($offset < 0) $offset = 0;

// Asegura que pageSize sea un valor razonable y positivo para evitar consultas excesivamente grandes
// o errores de división por cero. Puedes ajustar $maxPageSize según el rendimiento de tu servidor.
$maxPageSize = 5000; // Define un tamaño máximo de página permitido
if ($pageSize <= 0 || $pageSize > $maxPageSize) {
    $pageSize = 10; // Establece un valor por defecto seguro si el valor es inválido
}


// --- Consulta para Obtener Logs con Filtros y Paginación ---
// Prepara la consulta SQL para obtener los logs. Se utilizan marcadores de posición (?)
// para los valores que provienen de la entrada del usuario, previniendo SQL Injection.
$query = "SELECT * FROM logs WHERE user LIKE ? AND action LIKE ? AND ip LIKE ? ORDER BY id DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($query);

// Prepara los valores para la cláusula LIKE añadiendo los comodines (%).
$likeUsername = '%' . $usernameFilter . '%';
$likeAction = '%' . $actionFilter . '%';
$likeIp = '%' . $ipFilter . '%';

// Liga los parámetros a la sentencia preparada. 'sssii' especifica los tipos de datos:
// string, string, string, integer, integer.
// Se incluye manejo de error para la operación de ligar parámetros.
if ($stmt === false || !$stmt->bind_param('sssii', $likeUsername, $likeAction, $likeIp, $pageSize, $offset)) {
     // Registra el error en el log del servidor.
     error_log("Error al ligar parámetros en consulta de logs: " . $conn->error);
     // Detiene la ejecución y muestra un mensaje genérico al usuario.
     die("Error interno al preparar consulta de logs.");
}

// Ejecuta la sentencia preparada.
// Se incluye manejo de error para la operación de ejecución.
if (!$stmt->execute()) {
    // Registra el error en el log del servidor.
    error_log("Error al ejecutar consulta de logs: " . $stmt->error);
    // Detiene la ejecución y muestra un mensaje genérico al usuario.
    die("Error interno al obtener datos de logs.");
}

$result = $stmt->get_result(); // Obtiene el conjunto de resultados de la consulta


// --- Consulta para Contar el Total de Logs (para Paginación) ---
// Prepara la consulta SQL para contar el total de logs con los mismos filtros.
// Necesario para calcular el número total de páginas.
$totalLogsQuery = "SELECT COUNT(*) as total FROM logs WHERE user LIKE ? AND action LIKE ? AND ip LIKE ?";
$totalLogsStmt = $conn->prepare($totalLogsQuery);

// Liga los parámetros de filtro. 'sss' especifica tres parámetros de tipo string.
// Se incluye manejo de error para la operación de ligar parámetros.
if ($totalLogsStmt === false || !$totalLogsStmt->bind_param('sss', $likeUsername, $likeAction, $likeIp)) {
     // Registra el error en el log del servidor.
     error_log("Error al ligar parámetros en consulta de conteo de logs: " . $conn->error);
     // En este caso, no detenemos la ejecución, pero establecemos el total a 0 para
     // evitar errores posteriores en la lógica de paginación.
     $totalLogs = 0;
} else {
    // Ejecuta la sentencia de conteo.
    // Se incluye manejo de error para la operación de ejecución.
    if (!$totalLogsStmt->execute()) {
        // Registra el error en el log del servidor.
        error_log("Error al ejecutar consulta de conteo de logs: " . $totalLogsStmt->error);
        // Establece el total a 0 si falla la ejecución.
        $totalLogs = 0;
    } else {
        $totalLogsResult = $totalLogsStmt->get_result();
        $totalLogs = $totalLogsResult->fetch_assoc()['total']; // Obtiene el valor total del resultado
    }
    $totalLogsStmt->close(); // Cierra la sentencia de conteo para liberar recursos
}


// --- Inicio del Cuerpo HTML y Head ---
// MEJORA: Para una estructura HTML consistente en todo el sitio, es una mejor práctica
// que los tags <html>, <head>, y <body> sean abiertos en el archivo header.php
// y cerrados en el archivo footer.php. Si header.php ya abre <head> y <body>,
// elimina estos tags de este archivo para evitar duplicación.
?>
<head>
    <meta charset="UTF-8"> <meta http-equiv="X-UA-Compatible" content="IE=edge"> <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1"> <title>Admin Dashboard - Logs</title> <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <link href="../../datatables/datatables.min.css" rel="stylesheet">
    <script src="../../datatables/datatables.min.js"></script>
    <script src="../../datatables/responsive.datatables.js"></script>
    <script src="../../datatables/datatables.responsive.js"></script>
    <link rel="stylesheet" href="../../datatables/datatables.datatables.css">
    <link rel="stylesheet" href="../../datatables/responsive.datatables.css">
    </head>

<body>
    <div class="container mt-4">
        <div class="card">
            <div class="card-body">
                <form method="GET" action="">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="username">Filter by Username:</label>
                                <input type="text" id="username" name="username" class="form-control" value="<?= htmlspecialchars($usernameFilter) ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="action">Filter by Action:</label>
                                <input type="text" id="action" name="action" class="form-control" value="<?= htmlspecialchars($actionFilter) ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="ip">Filter by IP:</label>
                                <input type="text" id="ip" name="ip" class="form-control" value="<?= htmlspecialchars($ipFilter) ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="pageSize">Rows per page:</label>
                                <select id="pageSize" name="pageSize" class="form-control">
                                    <option value="10" <?= ($pageSize == 10) ? 'selected' : '' ?>>10</option>
                                    <option value="50" <?= ($pageSize == 50) ? 'selected' : '' ?>>50</option>
                                    <option value="100" <?= ($pageSize == 100) ? 'selected' : '' ?>>100</option>
                                    <option value="500" <?= ($pageSize == 500) ? 'selected' : '' ?>>500</option>
                                    <option value="1000" <?= ($pageSize == 1000) ? 'selected' : '' ?>>1000</option>
                                    <option value="5000" <?= ($pageSize == 5000) ? 'selected' : '' ?>>5000</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary">Filter</button>
                        </div>
                    </div>
                </form>
                <br>
                <table class="table table-striped table-bordered" style="width:100%">
                    <thead>
                        <tr>
                            <th scope="col">ID</th>
                            <th scope="col">Username</th>
                            <th scope="col">Action</th>
                            <th scope="col">IP</th>
                            <th scope="col">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td><?= htmlspecialchars($row["id"]) ?></td>
                                <td><?= htmlspecialchars($row["user"]) ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="action-content" style="max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($row["action"]) ?>">
                                            <?= htmlspecialchars(substr($row["action"], 0, 30)) . (strlen($row["action"]) > 30 ? "..." : "") ?>
                                        </div>
                                        <?php if (strlen($row["action"]) > 30): ?>
                                            <button class="expand-btn btn btn-link" onclick="expandMessage(this)">More+</button>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (strlen($row["action"]) > 30): ?>
                                        <div class="full-action-content" style="display: none;">
                                            <?= htmlspecialchars($row["action"]) ?> </div>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($row["ip"]) ?></td>
                                <td><?= htmlspecialchars($row["date"]) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>

                <?php
                // Cierra la sentencia de la consulta principal después de haber iterado sobre los resultados.
                $stmt->close();
                ?>

                <nav aria-label="Page navigation" class="d-flex justify-content-center">
                    <ul class="pagination">
                        <?php
                        // Lógica para calcular el número total de páginas.
                        // Asegura que totalLogs sea al menos 0 y pageSize sea positivo para evitar división por cero
                        // o resultados incorrectos si la consulta de conteo falló.
                        $totalPages = ($totalLogs > 0 && $pageSize > 0) ? ceil($totalLogs / $pageSize) : 1;
                        $maxPagesToShow = 5; // Número máximo de enlaces de página a mostrar en la paginación

                        // Calcula el rango de páginas a mostrar alrededor de la página actual.
                        $startPage = max(1, $page - floor($maxPagesToShow / 2));
                        $endPage = min($totalPages, $startPage + $maxPagesToShow - 1);

                        // Ajusta el inicio del rango si el final se topa con el total de páginas.
                        $startPage = max(1, $endPage - $maxPagesToShow + 1);

                        // Genera los enlaces de paginación.
                        // Los enlaces usan '?' para añadir parámetros a la URL actual, lo cual funciona correctamente
                        // con las reglas de .htaccess para URLs limpias.
                        // Se incluyen los valores de los filtros y el tamaño de página en los enlaces
                        // para mantener el estado del filtro al navegar por las páginas.
                        // urlencode se usa para codificar los valores de los filtros en la URL.
                        $filterParams = '&username=' . urlencode($usernameFilter) . '&action=' . urlencode($actionFilter) . '&ip=' . urlencode($ipFilter) . '&pageSize=' . $pageSize;

                        // Enlace a la primera página
                        if ($page > 1) {
                            echo '<li class="page-item"><a class="page-link" href="?page=1' . $filterParams . '">&laquo; First</a></li>';
                        }

                        // Enlace a la página anterior
                        if ($page > 1) {
                            echo '<li class="page-item"><a class="page-link" href="?page=' . ($page - 1) . $filterParams . '">&lsaquo; Prev</a></li>';
                        }

                        // Enlaces a las páginas dentro del rango calculado
                        for ($i = $startPage; $i <= $endPage; $i++) {
                            echo '<li class="page-item ' . ($i == $page ? 'active' : '') . '"><a class="page-link" href="?page=' . $i . $filterParams . '">' . $i . '</a></li>';
                        }

                        // Enlace a la página siguiente
                        if ($page < $totalPages) {
                            echo '<li class="page-item"><a class="page-link" href="?page=' . ($page + 1) . $filterParams . '">Next &rsaquo;</a></li>';
                        }

                        // Enlace a la última página
                        if ($page < $totalPages) {
                            echo '<li class="page-item"><a class="page-link" href="?page=' . $totalPages . $filterParams . '">Last &raquo;</a></li>';
                        }
                        ?>
                        <li class="page-item">
                             <form action="" method="get" class="d-flex">
                                 <input type="hidden" name="username" value="<?= htmlspecialchars($usernameFilter) ?>">
                                <input type="hidden" name="action" value="<?= htmlspecialchars($actionFilter) ?>">
                                <input type="hidden" name="ip" value="<?= htmlspecialchars($ipFilter) ?>">
                                <input type="hidden" name="pageSize" value="<?= htmlspecialchars($pageSize) ?>">
                                <input type="number" name="page" min="1" max="<?= $totalPages ?>" class="form-control" style="width: 80px;" placeholder="Page">
                                <button type="submit" class="btn btn-primary ml-2">Go</button>
                            </form>
                        </li>
                    </ul>
                </nav>
            </div> </div> </div> <script>
        function expandMessage(button) {
            // Encuentra el div que contiene el contenido truncado (el hermano anterior del botón)
            var actionContent = button.previousElementSibling;
            // Encuentra el div que contiene el contenido completo (el siguiente hermano del elemento padre del botón)
            var fullActionContent = button.parentElement.nextElementSibling;

            // Alterna la visibilidad del contenido completo y truncado
            if (fullActionContent.style.display === "none") {
                fullActionContent.style.display = "block"; // Muestra el contenido completo
                actionContent.style.display = "none"; // Oculta el contenido truncado
                button.textContent = "Less-"; // Cambia el texto del botón a "Less-"
            } else {
                fullActionContent.style.display = "none"; // Oculta el contenido completo
                actionContent.style.display = "block"; // Muestra el contenido truncado
                button.textContent = "More+"; // Cambia el texto del botón a "More+"
            }
        }
    </script>

    <script>
        $(document).ready(function() {
            // Inicializa DataTables en la tabla con las opciones especificadas.
            // 'paging': false y 'info': false se mantienen según el original,
            // ya que la paginación y el conteo total se manejan con la lógica PHP.
            // 'responsive': true hace que la tabla se adapte a diferentes tamaños de pantalla.
            $("table").DataTable({
                responsive: true,
                "order": [
                    [0, "desc"]
                ], // Ordena la tabla por la primera columna (ID) en orden descendente por defecto
                "paging": false, // Deshabilita la paginación nativa de DataTables
                "info": false, // Deshabilita la información de entrada de DataTables
                "searching": false // Deshabilita la funcionalidad de búsqueda nativa de DataTables
                 // Nota: Dado que estás usando paginación y filtro con PHP,
                 // deshabilitar la búsqueda ('searching': false) en DataTables es apropiado
                 // para evitar confusión o doble funcionalidad.
            });
        });
    </script>

</body>

<?php
// Cierra la conexión a la base de datos al final del script.
// Esto es importante para liberar los recursos de la base de datos.
if ($conn) {
    $conn->close();
}

// Incluye el pie de página. Asegúrate de que footer.php cierre correctamente los tags HTML
// que fueron abiertos (como </body> y </html> si no se cerraron en header.php).
include_once("footer.php");
?>
