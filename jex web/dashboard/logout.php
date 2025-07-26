<?php
// Inicia la sesión para poder acceder a ella.
session_start();

// --- PRESERVAR EL COOLDOWN (opcional, pero recomendado) ---
// Guarda la marca de tiempo del "cooldown" si existe, para mantener la protección anti-spam.
$lastRequest = $_SESSION['last_request'] ?? null;

// --- PROCESO DE CIERRE DE SESIÓN ---
// 1. Elimina todas las variables de la sesión.
$_SESSION = array();

// 2. Destruye la sesión del servidor.
session_destroy();

// 3. Inicia una nueva sesión limpia para el visitante.
session_start();

// 4. Restaura la marca de tiempo del cooldown en la nueva sesión.
if ($lastRequest !== null) {
    $_SESSION['last_request'] = $lastRequest;
}

// --- LÓGICA DE REDIRECCIÓN SEGURA ---
$redirectTo = '/'; // Destino por defecto (página de inicio).

// Lista blanca de destinos permitidos para la redirección.
$allowedRedirects = [
    '/Autogestion',
    // Puedes añadir otras rutas seguras aquí si lo necesitas en el futuro, ej: '/precios'
];

// Comprueba si se pasó un parámetro 'redirect' y si está en nuestra lista segura.
if (isset($_GET['redirect']) && in_array($_GET['redirect'], $allowedRedirects, true)) {
    $redirectTo = $_GET['redirect'];
}

// Redirige al destino final.
header("Location: " . $redirectTo);
exit();

?>