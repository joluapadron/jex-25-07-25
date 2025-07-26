<?php
// Inicia o reanuda la sesión existente.
// Esto es necesario para poder acceder y manipular las variables de sesión.
session_start();

// Elimina todas las variables de la sesión actual.
// Esto limpia los datos específicos del usuario que estaban almacenados en la sesión.
session_unset();

// Destruye completamente los datos de la sesión en el servidor.
// La sesión actual deja de existir después de llamar a esta función.
session_destroy();

// Redirige al usuario a la URL especificada.
// Hemos cambiado la URL de redirección a 'https://jex.lat' según tu solicitud.
header("Location: https://jex.lat/admin");

// Es crucial detener la ejecución del script después de enviar una cabecera de redirección.
// Esto asegura que no se procese ni se envíe ningún código adicional al navegador.
exit;
?>
