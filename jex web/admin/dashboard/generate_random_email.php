<?php
// Inicia o reanuda la sesión existente.
session_start();

// --- Verificación de Seguridad: Usuario Autenticado ---
// Si el usuario no ha iniciado sesión o la variable 'loggedin' no es true,
// redirige inmediatamente a la página de inicio de sesión del administrador.
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: ../index.php'); // Redirección a la página de login del admin
    exit; // Detiene la ejecución después de la redirección
}

// --- Inclusión de Archivos Esenciales ---
// Incluye la cabecera HTML y la lógica de conexión a la base de datos.
include_once("header.php"); // Contiene la cabecera HTML y la navegación
include_once("../../settings/database.php"); // Contiene la lógica de conexión a la BD

// --- Conexión a la Base de Datos ---
$conn = getDBConnection();

// --- Manejo Básico de Errores de Conexión a BD ---
if ($conn === false) {
    error_log("Error de conexión a la base de datos: " . mysqli_connect_error());
    die("Error interno del servidor. Por favor, inténtalo de nuevo más tarde.");
}

// --- Variables para mensajes de estado ---
$generated_alias = '';
$full_generated_alias = ''; // Nueva variable para el alias completo con dominio
$message = '';
$message_type = ''; // 'success' o 'error'

// --- Definición del Dominio de Alias (para mostrar con el alias generado) ---
$alias_domain = "@jex.lat"; // Definimos el dominio aquí

// --- Listas de nombres de personajes de Juego de Tronos (EXPANDIDAS para más combinaciones) ---
// Se han dividido en dos listas para generar combinaciones tipo "nombre.apellido" o "nombre1.nombre2"
$gotNamesPart1 = [
    'arya', 'sansa', 'jon', 'daenerys', 'tyrion', 'cersei', 'jaime', 'bran', 'jorah', 'gendry',
    'brienne', 'tormund', 'varys', 'samwell', 'missandei', 'greyworm', 'davos', 'melisandre', 'theon', 'yara',
    'euron', 'ramsay', 'joffrey', 'margaery', 'olenna', 'petyr', 'khal', 'drogo', 'ygritte', 'hodor',
    'ned', 'catelyn', 'robb', 'rickon', 'shae', 'bronn', 'podrick', 'lyanna', 'robert', 'stannis',
    'renly', 'viserys', 'sandor', 'gregor', 'oberyn', 'ellaria', 'myrcella', 'tommen', 'aegon', 'aemon',
    'maester', 'ser', 'lady', 'lord', 'king', 'queen', 'prince', 'princess', 'knight', 'wildling',
    'dothraki', 'unsullied', 'raven', 'dragon', 'wolf', 'lion', 'stag', 'bear', 'kraken', 'rose',
    'sun', 'viper', 'mountain', 'hound', 'littlefinger', 'highsparrow', 'nightking', 'ghost', 'nymeria', 'summer',
    'shaggydog', 'lady', 'greywind', 'drogon', 'rhaegal', 'viserion', 'white', 'walker', 'crow', 'watch',
    'oathkeeper', 'valyrian', 'dragonstone', 'winterfell', 'kingslanding', 'eyrie', 'sunspear', 'highgarden', 'casterlyrock', 'riverrun'
];

$gotNamesPart2 = [
    'snow', 'lannister', 'stark', 'targaryen', 'baratheon', 'greyjoy', 'tyrell', 'martell', 'arryn', 'tully',
    'bolton', 'clegane', 'baelish', 'seaworth', 'mormont', 'reed', 'umber', 'karstark', 'frey', 'royce',
    'redwyne', 'hightower', 'florent', 'tarly', 'payne', 'thorne', 'gilly', 'pyke', 'lasthearth', 'dreadfort',
    'bearisland', 'oldtown', 'thewall', 'beyond', 'north', 'south', 'east', 'west', 'iron', 'gold',
    'blood', 'fire', 'ice', 'stone', 'wood', 'river', 'lake', 'hill', 'vale', 'marsh',
    'forest', 'desert', 'storm', 'moon', 'star', 'light', 'dark', 'shadow', 'whisper', 'echo',
    'silence', 'fury', 'grin', 'blade', 'shield', 'hammer', 'axe', 'bow', 'arrow', 'spear',
    'sword', 'dagger', 'chain', 'whip', 'mask', 'cloak', 'crown', 'throne', 'owl', 'falcon',
    'hawk', 'serpent', 'spider', 'lionheart', 'strong', 'brave', 'wise', 'true', 'darkwood', 'brightwater',
    'deepwood', 'longbow', 'redhand', 'blackfyre', 'goldhand', 'stormborn', 'kingslayer', 'queensguard', 'nightswatch', 'unsullied'
];


// --- Función para generar un alias con nombres de Juego de Tronos y sufijo numérico ---
function generateGoTAlias() {
    global $gotNamesPart1, $gotNamesPart2;

    // Seleccionar un nombre de cada parte
    $randomName1 = $gotNamesPart1[array_rand($gotNamesPart1)];
    $randomName2 = $gotNamesPart2[array_rand($gotNamesPart2)];

    // Generar un sufijo numérico corto para asegurar unicidad
    $randomNumber = str_pad(rand(0, 999), 3, '0', STR_PAD_LEFT); // 3 dígitos (000-999)

    // Formato: nombre1.nombre2[XXX] (todo en minúsculas)
    return strtolower($randomName1 . '.' . $randomName2 . $randomNumber);
}

// --- Lógica para manejar la solicitud de generación de alias ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['generate_alias'])) {
    $is_unique = false;
    $attempts = 0;
    $max_attempts = 10; // Limitar intentos para evitar bucles infinitos en caso de colisiones

    while (!$is_unique && $attempts < $max_attempts) {
        $potential_alias_base = generateGoTAlias(); // Genera un alias basado en GoT
        $potential_alias_full = $potential_alias_base . $alias_domain; // Construye el alias completo para la verificación

        // --- Verificar si el alias completo ya existe en la tabla 'users' (columna 'alias') ---
        $check_query = "SELECT COUNT(*) FROM users WHERE alias = ?";
        $stmt = $conn->prepare($check_query);

        if ($stmt === false) {
            error_log("Error al preparar la consulta de verificación de alias en la tabla 'users': " . $conn->error);
            $message = "Error interno al verificar la unicidad del alias.";
            $message_type = 'error';
            break; // Salir del bucle si hay un error de preparación
        }

        $stmt->bind_param("s", $potential_alias_full); // Usar el alias completo para la verificación
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();

        if ($count == 0) {
            $is_unique = true;
            $generated_alias = $potential_alias_base; // Guardamos solo la base para referencia interna si es necesario
            $full_generated_alias = $potential_alias_full; // Guardamos el alias completo para mostrar
        }
        $attempts++;
    }

    if ($is_unique) {
        $message = "Alias de Juego de Tronos generado exitosamente. Puedes copiarlo y usarlo:";
        $message_type = 'success';
    } else {
        $message = "No se pudo generar un alias único después de " . $max_attempts . " intentos. Inténtalo de nuevo.";
        $message_type = 'error';
    }
}

// --- HTML para la interfaz de usuario ---
?>
<div class="container mt-4">
    <div class="card">
        <div class="card-header">
            Generador de Alias de Juego de Tronos
        </div>
        <div class="card-body">
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?= $message_type === 'success' ? 'success' : 'danger' ?>" role="alert">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <form action="" method="post">
                <button type="submit" name="generate_alias" class="btn btn-primary">Generar Nuevo Alias</button>
            </form>

            <?php if (!empty($full_generated_alias)): // Usamos $full_generated_alias para la visualización ?>
                <div class="mt-4">
                    <h5>Alias Generado (temporal):</h5>
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" id="generatedAliasField" value="<?= htmlspecialchars($full_generated_alias) ?>" readonly>
                        <button class="btn btn-outline-secondary" type="button" id="copyAliasButton">Copiar</button>
                    </div>
                    <small class="text-muted">Este alias no se ha guardado en la base de datos. Cópialo para usarlo.</small>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    // Script para copiar el alias al portapapeles
    document.getElementById('copyAliasButton').addEventListener('click', function() {
        const aliasField = document.getElementById('generatedAliasField');
        aliasField.select();
        document.execCommand('copy'); // Usar execCommand para mayor compatibilidad en iframes
        // Mostrar un mensaje temporal de confirmación
        const copyMessage = document.createElement('div');
        copyMessage.className = 'alert alert-info mt-2';
        copyMessage.textContent = 'Alias copiado al portapapeles: ' + aliasField.value;
        document.querySelector('.card-body').appendChild(copyMessage);
        setTimeout(() => {
            copyMessage.remove();
        }, 3000); // Elimina el mensaje después de 3 segundos
    });
</script>

<?php
// Cierra la conexión a la base de datos al final del script.
if ($conn) {
    $conn->close();
}

// Incluye el pie de página.
include_once("footer.php");
?>
