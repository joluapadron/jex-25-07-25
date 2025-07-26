<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once "settings/site.php";
require_once "settings/recaptcha.php";

if (isset($_GET['lang'])) {
    $_SESSION["lang"] = $_GET['lang'];
}

$lang = isset($_SESSION["lang"]) ? $_SESSION["lang"] : $default_language;

require_once "languages/lang_handler.php";
$langHandler = new LangHandler($lang);
?>

<!doctype html>
<html lang="en">

<head>
    <title><?php echo $site_name; ?></title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <link rel="icon" type="image/ico" href="/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-4Q6Gf2aSP4eDXB8Miphtr37CMZZQ5oXLH2yaXMJ2w8e2ZtHTl7GptT4jmndRuHDT" crossorigin="anonymous">
    <script src="https://www.google.com/recaptcha/api.js?hl=<?php echo $langHandler->getTranslation('CAPTCHA', 'LANG'); ?>" async defer></script>
    <link rel="stylesheet" href="css/index.css" />
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" integrity="sha384-j1CDi7MgGQ12Z7Qab0qlWQ/Qqz24Gc6BM0thvEMVjHnfYGF0rmFCozFSxQBxwHKO" crossorigin="anonymous"></script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" integrity="sha384-j1CDi7MgGQ12Z7Qab0qlWQ/Qqz24Gc6BM0thvEMVjHnfYGF0rmFCozFSxQBxwHKO" crossorigin="anonymous"></script>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark" style="font-size: 1.5em; padding: 20px;">
        <div class="container-fluid">
            <a class="navbar-brand" href="#"><?php echo $site_name; ?></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavDropdown" aria-controls="navbarNavDropdown" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNavDropdown">
                <ul class="navbar-nav ml-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownMenuLink" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <?php echo $langHandler->getTranslation("NAV", "LANG"); ?>
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdownMenuLink">
                            <li>
                                <a class="dropdown-item" href="index.php?lang=en">
                                    <img src="img/flags/EN.png" alt="EN" style="height: 15px; width: 20px;"> EN
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="index.php?lang=es">
                                    <img src="img/flags/ES.png" alt="ES" style="height: 15px; width: 20px;"> ES
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="index.php?lang=pt">
                                    <img src="img/flags/PT.png" alt="PT" style="height: 15px; width: 20px;"> PT
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="index.php?lang=cn">
                                    <img src="img/flags/CN.png" alt="CN" style="height: 15px; width: 20px;"> CN
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" integrity="sha384-j1CDi7MgGQ12Z7Qab0qlWQ/Qqz24Gc6BM0thvEMVjHnfYGF0rmFCozFSxQBxwHKO" crossorigin="anonymous"></script>
</body>

</html>