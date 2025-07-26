<?php
// Ensure session is started before any output if needed in header.php
// If session_start() is already called at the very beginning of the main page files,
// you might not need it here, but it's safe to have it if header.php
// relies on session variables (like $_SESSION['site_name'] if site_name comes from session).
// session_start(); // Uncomment if session_start() is NOT the very first line in files including this.

// --- Include Site and reCAPTCHA Settings ---
// Include necessary configuration files. Ensure these files are secure
// and ideally located outside the web-accessible root directory.
require_once "../../settings/site.php"; // Contains site-specific settings like $site_name


// --- Security Check: Escape Site Name Output ---
// Escape the site name using htmlspecialchars() to prevent Cross-Site Scripting (XSS)
// if the $site_name variable contains any malicious HTML or JavaScript.
// Assuming $site_name is loaded from ../../settings/site.php
$safe_site_name = htmlspecialchars($site_name ?? 'Default Site Name'); // Use default if $site_name is not set

?>

<!doctype html>
<html lang="en">

<head>
    <title><?php echo $safe_site_name; ?> - Admin Panel</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge"> <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" xintegrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous" />
    
    <link rel="icon" type="image/ico" href="/favicon.ico">
    <link rel="stylesheet" href="../../css/admin.css" />

    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
<link rel="icon" type="image/png" href="/favicon.png"
    <?php
    // Note: If any page including this header needs specific scripts or CSS
    // loaded in the <head>, those should be included directly in those pages
    // before the include_once("footer.php"); call.
    ?>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark" style="font-size: 1.5em; padding: 20px;">
        <div class="container-fluid">
            <a class="navbar-brand" href="/admin/dashboard/"><?php echo $safe_site_name; ?></a> <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavDropdown" aria-controls="navbarNavDropdown" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNavDropdown">
                <ul class="navbar-nav ml-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="./">Dashboard</a> </li>
                    <li class="nav-item">
                        <a class="nav-link" href="site.php">Site</a> </li>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php">Users</a> </li>
                    <li class="nav-item">
                        <a class="nav-link" href="generate_random_email.php">Generar Alias</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logs.php">Logs</a> </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/dashboard/authenticator_accounts.php">Authenticator</a> </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php" style="color: red;">Logout</a> </li>
                </ul>
            </div>
        </div>
    </nav>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" xintegrity="sha384-7a9815a4e06c6e92f35d1c3bf3d4c79a7a3da3c8f0dbad4b6cfe8c4d8832f5f5" crossorigin="anonymous"></script>

<?php
// Note: The </body> and </html> tags should be in the footer.php file.
// The content of the specific page (dashboard, users, logs) will go between
// this header.php include and the footer.php include.
?>
