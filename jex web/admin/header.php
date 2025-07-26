<?php
// Iniciar búfer de salida para evitar errores de "headers sent".
ob_start();
// Iniciar sesión.
session_start();
// Requerir dependencias y configuración.
require_once __DIR__ . "/../settings/site.php";
require_once __DIR__ . "/../settings/recaptcha.php";
// Lógica para manejar el idioma.
if (isset($_GET['lang'])) {
$_SESSION["lang"] = $_GET['lang'];
}
$lang = $_SESSION["lang"] ?? $default_language;
require_once __DIR__ . "/../languages/lang_handler.php";
$langHandler = new LangHandler($lang);
?>
<!doctype html>
<html lang="<?php echo htmlspecialchars($lang); ?>">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
<title><?php echo htmlspecialchars($site_name); ?></title>
<!-- Estilos, Favicon y Scripts -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" xintegrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous" />
<link rel="stylesheet" href="../css/index.css" />
<link rel="icon" type="image/ico" href="/favicon.ico">
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark" style="font-size: 1.5em; padding: 20px;">
<div class="container-fluid">
<a class="navbar-brand" href="#"><?php echo htmlspecialchars($site_name); ?></a>
<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavDropdown" aria-controls="navbarNavDropdown" aria-expanded="false" aria-label="Toggle navigation">
<span class="navbar-toggler-icon"></span>
</button>
</div>
</nav>
<!-- El contenido principal de la página va aquí -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" xintegrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
</body>
</html>
<?php
// Envía todo el contenido del búfer al navegador.
ob_end_flush();
?>
