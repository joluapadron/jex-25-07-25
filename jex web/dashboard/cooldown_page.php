<?php
// Este archivo es incluido por codigos/index.php cuando el usuario debe esperar

// Incluimos el mismo header para mantener la consistencia visual
include_once(__DIR__ . "/header.php"); 
?>

<main class="page-main">
    <div class="container">
        <div class="content-box">
            <h2>Por Favor, Espera</h2>
            <div class="content-body">
                <div class="alert-message">
                    Has recargado la página muy rápido. Debes esperar 
                    <strong id="countdown" style="color: #fff;"><?php echo htmlspecialchars($wait_seconds); ?></strong> 
                    segundos antes de volver a intentarlo.
                </div>
            </div>
        </div>
    </div>
</main>

<script>
    // JavaScript para el contador en tiempo real
    let countdownElement = document.getElementById('countdown');
    let seconds = parseInt(countdownElement.textContent);

    let countdownInterval = setInterval(function() {
        seconds--;
        countdownElement.textContent = seconds;
        if (seconds <= 0) {
            clearInterval(countdownInterval);
            location.reload(); // Recarga la página cuando el tiempo termina
        }
    }, 1000);
</script>

<?php
// Incluimos el footer
include_once(__DIR__ . "/footer.php");
?>