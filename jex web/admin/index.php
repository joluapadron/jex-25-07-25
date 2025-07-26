<?php
// El include DEBE ser la primera línea dentro del bloque PHP,
// y el bloque PHP DEBE ser lo primero en el archivo.
require_once("header.php");
?>
<!-- El comentario ahora está en un lugar seguro -->
<!-- You need support? -> https://t.me/JustLenore -->

<!-- La etiqueta <body> ya no es necesaria aquí, porque está en header.php -->
<div class="container">
    <div class="row">
        <div class="col-md-12">
            <img src="../img/logo.png" alt="logo" class="img-fluid" />
        </div>
    </div>
</div>

<main>
    <div class="container" data-bs-theme="dark">
        <div class="row">
            <div class="col-md-6 offset-md-3">
                <div class="card">
                    <div class="card-header">
                        <h3>Admin Login</h3>
                    </div>
                    <div class="card-body">
                        <form action="login.php" method="post">
                            <div class="mb-3">
                                <label for="username" class="form-label">USERNAME</label>
                                <input type="text" class="form-control" id="username" name="username" required />
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">PASSWORD</label>
                                <input type="password" class="form-control" id="password" name="password" required />
                            </div>
                            <div style="display: flex; justify-content: center;">
                                <div class="g-recaptcha" data-sitekey="<?php echo $SITE_KEY; ?>" data-callback="enableBtn"></div>
                            </div>
                            <button type="submit" class="btn btn-primary">LOGIN</button>
                        </form>
                        <?php if (isset($_GET['error']) && $_GET['error'] == 'invalidpassword') {
                            echo '<p style="color: red;">' . $langHandler->getTranslation("LOGIN", "INVALID_PASSWORD") . '</p>';
                        } ?>
                        <?php if (isset($_GET['error']) && $_GET['error'] == 'invalidcaptcha') {
                            echo '<p style="color: red;">' . $langHandler->getTranslation("LOGIN", "INVALID_CAPTCHA") . '</p>';
                        } ?>
                    </div>
                </div>
            </div>
        </div>
</main>

<?php 
// Incluye el footer. El footer debería tener las etiquetas de cierre </body> y </html>
// y la llamada a ob_end_flush();
require_once("footer.php"); 
?>