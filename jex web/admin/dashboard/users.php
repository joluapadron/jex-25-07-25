<?php
// Inicia o reanuda la sesión existente.
session_start();

// --- Verificación de Seguridad: Usuario Autenticado ---
// Si el usuario no ha iniciado sesión o la variable 'loggedin' no es true,
// redirige inmediatamente a la página de inicio de sesión del administrador.
// Asumimos que '../index.php' es la ruta correcta a la página de login del admin
// relativa a la ubicación de este archivo (ej: si este es admin/dashboard/index.php,
// '../index.php' apunta a admin/index.php).
// Si deseas que la URL de login sea '/admin/' (y .htaccess lo maneja),
// considera cambiar la línea a: header('Location: /admin/');
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: ../index.php'); // Redirección a la página de login del admin
    exit; // Detiene la ejecución después de la redirección
}

// --- Inclusión de Archivos Esenciales ---
// Incluye archivos necesarios. Asegúrate de que estos archivos no sean accesibles directamente
// a través del navegador (colócalos fuera del directorio raíz del servidor web si es posible)
// y que contengan código seguro.
include_once("header.php"); // Contiene la cabecera HTML (y posiblemente enlaces de navegación)
include_once("../../settings/database.php"); // Contiene la lógica de conexión a la BD (CRÍTICO para seguridad)

// --- Conexión a la Base de Datos ---
// Obtiene la conexión a la base de datos. La función getDBConnection() debe estar definida en database.php
// y manejar la conexión de forma segura (credenciales fuera del alcance público, etc.).
$conn = getDBConnection();

// --- Manejo Básico de Errores de Conexión a BD ---
// Verifica si la conexión a la base de datos fue exitosa.
if ($conn === false) {
    // En un entorno de producción, no muestres detalles del error al usuario.
    // Registra el error en un archivo de log del servidor y muestra un mensaje genérico.
    error_log("Error de conexión a la base de datos: " . mysqli_connect_error()); // Registra el error
    die("Error interno del servidor. Por favor, inténtalo de nuevo más tarde."); // Mensaje genérico para el usuario
}


// --- Obtención de Parámetros de Paginación y Filtro ---
// Obtiene los parámetros de la URL (GET) para paginación y filtros.
// Se usa (int) para asegurar que 'page' y 'pageSize' son números enteros, previniendo inyección simple.
// Se usa htmlspecialchars para escapar la salida de los filtros en el formulario HTML.
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$pageSize = isset($_GET['pageSize']) ? (int)$_GET['pageSize'] : 10;
// htmlspecialchars se usa aquí para escapar la salida en el formulario,
// PERO los valores $aliasFilter y $mailsFilter se usan de forma segura en la consulta preparada.
$aliasFilter = isset($_GET['alias']) ? $_GET['alias'] : '';
$mailsFilter = isset($_GET['mails']) ? $_GET['mails'] : '';

// Calcula el offset para la consulta SQL.
$offset = ($page - 1) * $pageSize;
// Asegura que offset no sea negativo.
if ($offset < 0) $offset = 0;

// Asegura que pageSize sea un valor razonable para evitar consultas excesivamente grandes.
// Puedes ajustar estos límites según tus necesidades.
$maxPageSize = 5000; // Define un tamaño máximo de página
if ($pageSize <= 0 || $pageSize > $maxPageSize) {
    $pageSize = 10; // Valor por defecto si es inválido
}


// --- Consulta para Obtener Usuarios con Filtros y Paginación ---
// Consulta segura usando sentencias preparadas para prevenir SQL Injection.
// Los filtros LIKE usan comodines (%) que se añaden *después* de obtener los valores del usuario
// y *antes* de ligarlos a la sentencia preparada.
$query = "SELECT id, alias, is_paused, mails, services FROM users WHERE alias LIKE ? AND mails LIKE ? LIMIT ? OFFSET ?";
$stmt = $conn->prepare($query);

// Prepara los valores para la cláusula LIKE.
$likeAlias = '%' . $aliasFilter . '%';
$likeMails = '%' . $mailsFilter . '%';

// Liga los parámetros a la sentencia preparada. 'ssii' indica tipos: string, string, integer, integer.
// Manejo de error para bind_param
if ($stmt === false || !$stmt->bind_param('ssii', $likeAlias, $likeMails, $pageSize, $offset)) {
     error_log("Error al ligar parámetros en consulta de usuarios: " . $conn->error);
     // En producción, no muestres detalles.
     die("Error interno al preparar consulta de usuarios.");
}

// Ejecuta la sentencia preparada.
if (!$stmt->execute()) {
    error_log("Error al ejecutar consulta de usuarios: " . $stmt->error);
    // En producción, no muestres detalles.
    die("Error interno al obtener datos de usuarios.");
}

$result = $stmt->get_result(); // Obtiene el resultado de la consulta


// --- Consulta para Contar el Total de Usuarios (para Paginación) ---
// Consulta segura usando sentencias preparadas.
$totalUsersQuery = "SELECT COUNT(*) as total FROM users WHERE alias LIKE ? AND mails LIKE ?";
$totalUsersStmt = $conn->prepare($totalUsersQuery);

// Liga los parámetros de filtro. 'ss' indica dos parámetros de tipo string.
// Manejo de error para bind_param
if ($totalUsersStmt === false || !$totalUsersStmt->bind_param('ss', $likeAlias, $likeMails)) {
     error_log("Error al ligar parámetros en consulta de conteo: " . $conn->error);
     // En producción, no muestres detalles.
     // Continúa con 0 usuarios si el conteo falla, o detén la ejecución.
     $totalUsers = 0;
} else {
    // Ejecuta la sentencia de conteo.
    if (!$totalUsersStmt->execute()) {
        error_log("Error al ejecutar consulta de conteo: " . $totalUsersStmt->error);
        // En producción, no muestres detalles.
        $totalUsers = 0; // Establece el total a 0 si falla la ejecución
    } else {
        $totalUsersResult = $totalUsersStmt->get_result();
        $totalUsers = $totalUsersResult->fetch_assoc()['total']; // Obtiene el conteo total
    }
    $totalUsersStmt->close(); // Cierra la sentencia de conteo
}


// --- Inicio del Cuerpo HTML y Head ---
// MEJORA: Idealmente, los tags <html>, <head>, y <body> deberían ser abiertos en header.php
// y cerrados en footer.php para una estructura HTML correcta y consistente en todo el sitio.
// Si header.php ya abre <head> y <body>, elimina estos tags de aquí.
?>
<head>
    <meta charset="UTF-8"> <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>Admin Dashboard - Users</title> <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
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
                            <label for="alias">Filter by Alias:</label>
                            <input type="text" id="alias" name="alias" class="form-control" value="<?= htmlspecialchars($aliasFilter) ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="mails">Filter by Mails:</label>
                            <input type="text" id="mails" name="mails" class="form-control" value="<?= htmlspecialchars($mailsFilter) ?>">
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
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary mt-4">Filter</button>
                    </div>
                    <div class="col-md-3">
                        <button type="button" onclick="location.href='add_user_form.php';" class="btn btn-primary mt-4">Add User</button>
                    </div>
                </div>
            </form>

            <table class="table table-striped table-bordered" style="width:100%">
                <thead>
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">Alias</th>
                        <th scope="col">Is Paused</th>
                        <th scope="col">Mails</th>
                        <th scope="col">Services</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td><?= htmlspecialchars($row["id"]) ?></td>
                            <td><?= htmlspecialchars($row["alias"]) ?></td>
                            <td><?= htmlspecialchars($row["is_paused"]) ?></td>
                            <td><?= htmlspecialchars($row["mails"]) ?></td>
                            <td><?= htmlspecialchars($row["services"]) ?></td>
                            <td>
                                <button type="button" onclick="location.href='edit_user_form.php?alias=<?= urlencode($row['alias']) ?>';">Edit</button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

            <?php
            // Cierra la sentencia de la consulta principal después del bucle.
            $stmt->close();
            ?>

            <nav aria-label="Page navigation" class="d-flex justify-content-center">
                <ul class="pagination">
                    <?php
                    // Lógica para calcular el número total de páginas
                    // Asegura que totalUsers sea al menos 0 para evitar división por cero si la consulta de conteo falló.
                    $totalPages = ($totalUsers > 0 && $pageSize > 0) ? ceil($totalUsers / $pageSize) : 1;
                    $maxPagesToShow = 5; // Número máximo de enlaces de página a mostrar

                    // Calcula el rango de páginas a mostrar
                    $startPage = max(1, $page - floor($maxPagesToShow / 2));
                    $endPage = min($totalPages, $startPage + $maxPagesToShow - 1);

                    // Ajusta el inicio si el final se topa con el total de páginas
                    $startPage = max(1, $endPage - $maxPagesToShow + 1);

                    // Genera enlaces de paginación
                    // Los enlaces usan '?' para añadir parámetros a la URL actual, lo cual funciona con .htaccess
                    // Se incluyen los filtros y el tamaño de página en los enlaces de paginación.
                    // urlencode se usa para codificar los valores de los filtros en la URL.
                    $filterParams = '&alias=' . urlencode($aliasFilter) . '&mails=' . urlencode($mailsFilter) . '&pageSize=' . $pageSize;

                    if ($page > 1) {
                        echo '<li class="page-item"><a class="page-link" href="?page=1' . $filterParams . '">&laquo; First</a></li>';
                    }

                    if ($page > 1) {
                        echo '<li class="page-item"><a class="page-link" href="?page=' . ($page - 1) . $filterParams . '">&lsaquo; Prev</a></li>';
                    }

                    for ($i = $startPage; $i <= $endPage; $i++) {
                        echo '<li class="page-item ' . ($i == $page ? 'active' : '') . '"><a class="page-link" href="?page=' . $i . $filterParams . '">' . $i . '</a></li>';
                    }

                    if ($page < $totalPages) {
                        echo '<li class="page-item"><a class="page-link" href="?page=' . ($page + 1) . $filterParams . '">Next &rsaquo;</a></li>';
                    }

                    if ($page < $totalPages) {
                        echo '<li class="page-item"><a class="page-link" href="?page=' . $totalPages . $filterParams . '">Last &raquo;</a></li>';
                    }
                    ?>
                    <li class="page-item">
                         <form action="" method="get" class="d-flex">
                             <input type="hidden" name="alias" value="<?= htmlspecialchars($aliasFilter) ?>">
                            <input type="hidden" name="mails" value="<?= htmlspecialchars($mailsFilter) ?>">
                            <input type="hidden" name="pageSize" value="<?= htmlspecialchars($pageSize) ?>">
                            <input type="number" name="page" min="1" max="<?= $totalPages ?>" class="form-control" style="width: 80px;" placeholder="Page">
                            <button type="submit" class="btn btn-primary ml-2">Go</button>
                        </form>
                    </li>
                </ul>
            </nav>
        </div> </div> </div> <script>
    $(document).ready(function() {
        // Inicializa DataTables en la tabla.
        // 'paging': false y 'info': false se mantienen según el original,
        // ya que la paginación y conteo total se manejan con PHP.
        // 'responsive': true para que la tabla se adapte a diferentes tamaños de pantalla.
        $("table").DataTable({
            responsive: true,
            "order": [[0, "desc"]], // Ordena por la primera columna (ID) descendente por defecto
            "paging": false, // Deshabilita la paginación de DataTables (usamos paginación PHP)
            "info": false, // Deshabilita la información de "Showing X to Y of Z entries"
            "searching": false // Deshabilita la búsqueda de DataTables (usamos filtro PHP)
        });
         // Nota: Dado que estás usando paginación y filtro con PHP,
         // deshabilitar la búsqueda ('searching': false) en DataTables es apropiado
         // para evitar confusión o doble funcionalidad.
    });
</script>

</body>

<?php
// Cierra la conexión a la base de datos al final del script.
if ($conn) {
    $conn->close();
}

// Incluye el pie de página. Asegúrate de que footer.php cierre los tags HTML
// que fueron abiertos (como </body> y </html> si no se cerraron en header.php).
include_once("footer.php");
?>
