<?php

// Inicia o reanuda la sesión existente.
session_start();

// --- Verificación de Seguridad: Usuario Autenticado y Administrador ---
// Verifica si el usuario ha iniciado sesión Y si está marcado como administrador en la sesión.
// Si no cumple los requisitos, redirige a la página de inicio de sesión del administrador.
// Asumimos que '../index.php' es la ruta correcta a la página de login del admin
// relativa a la ubicación de este file (e.g., if this is admin/dashboard/index.php,
// '../index.php' points to admin/index.php).
// If your admin login page uses a clean URL handled by .htaccess (e.g., https://jex.lat/admin/),
// consider changing the redirection line to: header('Location: /admin/');
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: ../index.php'); // Redirect to the admin login page
    exit; // It is crucial to stop script execution after a redirect
}

// --- Inclusión de Archivos Esenciales ---
// Include necessary files. Ensure these files (especially database.php)
// are not directly accessible via the browser (place them outside the web
// server's root directory if possible) and contain secure code.
// Include database connection logic first, before any database operations.
include_once("../../settings/database.php"); // Contains the database connection logic (CRITICAL for security)
include_once("header.php"); // Contains the HTML header (and possibly navigation links) - should start the HTML document structure

// --- Conexión a la Base de Datos ---
// Get the database connection using the function defined in database.php.
// This function should handle the connection securely (credentials outside public access, etc.).
$conn = getDBConnection();

// --- Basic Database Connection Error Handling ---
// Check if the database connection was successful.
if ($conn === false) {
    // In a real production environment, NEVER show detailed error information to the user.
    // Log the full error to a server log file for debugging.
    error_log("Database connection error: " . mysqli_connect_error()); // Log the error
    // Show a generic error message to the user.
    die("Internal server error. Please try again later.");
}

// --- Fetch Statistics from Database ---
// Fetch total number of users.
$queryUsers = "SELECT COUNT(*) AS total_users FROM users";
// Use prepared statements even for simple queries as a best practice,
// although mysqli::query is safe here as there is no user input.
// Adding error handling for query execution.
if ($resultUsers = $conn->query($queryUsers)) {
    $rowUsers = $resultUsers->fetch_assoc();
    $totalUsers = htmlspecialchars($rowUsers['total_users']); // Escape output even for numbers
    $resultUsers->free(); // Free result set
} else {
    error_log("Error fetching total users: " . $conn->error);
    $totalUsers = "N/A"; // Display N/A or an error message on the page
}

// Fetch code counts (read and unread).
$queryCodes = "SELECT SUM(case when used = 1 then 1 else 0 end) AS read_codes, SUM(case when used = 0 then 1 else 0 end) AS unread_codes FROM codes";
// Adding error handling for query execution.
if ($resultCodes = $conn->query($queryCodes)) {
    $rowCodes = $resultCodes->fetch_assoc();
    $readCodes = htmlspecialchars($rowCodes['read_codes']); // Escape output
    $unreadCodes = htmlspecialchars($rowCodes['unread_codes']); // Escape output
    $resultCodes->free(); // Free result set
} else {
    error_log("Error fetching code counts: " . $conn->error);
    $readCodes = "N/A"; // Display N/A or an error message
    $unreadCodes = "N/A"; // Display N/A or an error message
}

// --- Close Database Connection ---
// Close the database connection when it's no longer needed.
if ($conn) {
    $conn->close();
}

// --- HTML Output ---
// Assuming header.php has already opened <html>, <head>, and <body> tags.
// If not, you need to adjust header.php and footer.php accordingly.
?>
    <div class="container mt-4">
        <h1>Welcome <?= htmlspecialchars($_SESSION['username']); ?>!</h1>
    </div>

    <?php
    // Output the statistics card.
    echo '<div class="card container mt-4">';
    echo '<div class="card-header">Stats</div>';
    echo '<div class="card-body">';
    // Output statistics, already escaped when fetched from results.
    echo "<p>Total registered users: $totalUsers</p>";
    echo "<p>Total codes read: $readCodes</p>";
    echo "<p>Total codes unread: $unreadCodes</p>";
    echo '</div>'; // Close card-body
    echo '</div>'; // Close card
    ?>

<?php
// Include the footer. Ensure footer.php closes the HTML tags
// that were opened (like </body> and </html> if not closed in header.php).
include_once("footer.php");
?>
