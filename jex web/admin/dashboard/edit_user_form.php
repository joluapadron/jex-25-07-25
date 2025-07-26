<?php
// 1. Inicia o reanuda la sesión existente. Esto DEBE ser lo primero.
session_start();

// --- Verificación de Seguridad: Usuario Autenticado y Administrador ---
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: ../index.php'); // Redirige a la página de login
    exit; // Detiene la ejecución
}

// --- Incluye solo los archivos de lógica necesarios ANTES de cualquier HTML ---
include_once("../../settings/database.php");

// --- Conexión a la Base de Datos ---
$conn = getDBConnection();
if ($conn === false) {
    error_log("Database connection error: " . mysqli_connect_error());
    die("Internal server error. Please try again later.");
}

// --- Inicialización de Variables ---
$error_message = '';
$success_message = '';

// --- BLOQUE DE PROCESAMIENTO DEL FORMULARIO (POST) ---
// Toda esta lógica se ejecuta ANTES de que se envíe cualquier HTML.
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Obtiene datos del formulario
    $original_alias = trim($_POST['original_alias'] ?? '');
    $pin = trim($_POST['pin'] ?? '');
    $is_paused = (isset($_POST['is_paused']) && $_POST['is_paused'] == '1') ? 1 : 0;
    $mails = trim($_POST['mails'] ?? '');
    $services = trim($_POST['services'] ?? '');
    $authenticator_account_alias = trim($_POST['authenticator_account_alias'] ?? '');
    if ($authenticator_account_alias === 'None') {
        $authenticator_account_alias = null;
    }

    // Validación del lado del servidor...
    // (Tu lógica de validación se mantiene aquí)
    $pin_para_guardar = null;
    if (!empty($pin)) {
        if (!ctype_alnum($pin) || strlen($pin) < 4) {
            $error_message = "Formato de PIN inválido. Debe contener solo letras y números, y tener al menos 4 caracteres.";
        } else {
            $pin_para_guardar = password_hash($pin, PASSWORD_DEFAULT);
        }
    }

    // Si no hay errores de validación, procede a actualizar.
    if (empty($error_message)) {
        // Tu lógica para construir y ejecutar la consulta UPDATE...
        $updateFields = [];
        $bindTypes = '';
        $bindParams = [];

        $updateFields[] = "is_paused=?"; $bindTypes .= 'i'; $bindParams[] = $is_paused;
        $updateFields[] = "mails=?"; $bindTypes .= 's'; $bindParams[] = $mails;
        $updateFields[] = "services=?"; $bindTypes .= 's'; $bindParams[] = $services;
        $updateFields[] = "authenticator_account_alias=?"; $bindTypes .= 's'; $bindParams[] = $authenticator_account_alias;

        if ($pin_para_guardar !== null) {
            $updateFields[] = "pin=?"; $bindTypes .= 's'; $bindParams[] = $pin_para_guardar;
        }

        $whereClause = "WHERE alias=?"; $bindTypes .= 's'; $bindParams[] = $original_alias;
        $updateQuery = "UPDATE users SET " . implode(", ", $updateFields) . " " . $whereClause;

        $stmt = $conn->prepare($updateQuery);

        if ($stmt) {
            $bindParamsRefs = [];
            foreach ($bindParams as $key => $value) {
                $bindParamsRefs[$key] = &$bindParams[$key];
            }
            array_unshift($bindParamsRefs, $bindTypes);
            call_user_func_array([$stmt, 'bind_param'], $bindParamsRefs);

            if ($stmt->execute()) {
                // --- ¡ÉXITO! ---
                // La redirección se hace aquí, ANTES de que se envíe cualquier HTML.
                // Esto SOLUCIONA el error.
                $stmt->close();
                $conn->close();
                header('Location: users.php');
                exit; // Crucial para detener la ejecución
            } else {
                error_log("Error executing user update: " . $stmt->error);
                $error_message = "Error al actualizar el registro del usuario.";
            }
            $stmt->close();
        } else {
            error_log("Error preparing user update query: " . $conn->error);
            $error_message = "Error interno al preparar la actualización.";
        }
    }
    // Si hubo un error en el POST, el script continúa para mostrar el formulario con el mensaje de error.
}

// --- Si la ejecución llega aquí, significa que no hubo redirección ---
// Ahora es seguro incluir el header y empezar a mostrar la página.
include_once("header.php");

// --- Lógica para Obtener Datos para Mostrar en el Formulario ---
$aliasToFetch = trim($_GET['alias'] ?? ($_POST['original_alias'] ?? ''));
if (empty($aliasToFetch)) {
    die("Se requiere un alias para editar el usuario.");
}

// Fetch user details
$fetchQuery = "SELECT alias, pin, is_paused, mails, services, authenticator_account_alias FROM users WHERE alias = ?";
$fetchStmt = $conn->prepare($fetchQuery);
$fetchStmt->bind_param("s", $aliasToFetch);
$fetchStmt->execute();
$result = $fetchStmt->get_result();

if ($result->num_rows > 0) {
    $userDetails = $result->fetch_assoc();
} else {
    die("No se encontró ningún usuario con ese alias.");
}
$fetchStmt->close();

// Fetch authenticator accounts
$authenticator_accounts = [];
$fetchAccountsQuery = "SELECT account_alias FROM shared_authenticator_accounts ORDER BY account_alias ASC";
$accountsResult = $conn->query($fetchAccountsQuery);
if ($accountsResult) {
    while ($row = $accountsResult->fetch_assoc()) {
        $authenticator_accounts[] = htmlspecialchars($row['account_alias']);
    }
}
$conn->close(); // Cerramos la conexión después de obtener todos los datos.
?>

<!-- El resto de tu código HTML y el formulario van aquí, sin cambios -->
<div class="container mt-5">
    <?php
    if (!empty($error_message)) {
        echo '<div class="alert alert-danger" role="alert">' . htmlspecialchars($error_message) . '</div>';
    }
    if (!empty($success_message)) {
        echo '<div class="alert alert-success" role="alert">' . htmlspecialchars($success_message) . '</div>';
    }
    ?>
    <div class="card">
        <div class="card-header">
            Edit User
        </div>
        <div class="card-body">
            <!-- Tu formulario HTML completo aquí... -->
            <form action="edit_user_form.php?alias=<?= htmlspecialchars($userDetails['alias']) ?>" method="post">
                <input type="hidden" name="original_alias" value="<?= htmlspecialchars($userDetails['alias']) ?>">

                <div class="mb-3">
                    <p>Alias: <?= htmlspecialchars($userDetails['alias']) ?></p>
                </div>
                
                <!-- PIN -->
                <div class="mb-3">
                    <label for="pin" class="form-label">PIN:</label>
                    <input type="text" id="pin" name="pin" class="form-control" value="" pattern="[a-zA-Z0-9]{4,}" title="Debe contener solo letras y números, y tener al menos 4 caracteres. Deje vacío para no cambiar." inputmode="text">
                    <small class="form-text text-muted">Deje el campo PIN vacío si no desea cambiarlo.</small>
                </div>

                <!-- Is Paused -->
                <div class="mb-3">
                    <label for="is_paused" class="form-label">Is Paused:</label>
                    <select id="is_paused" name="is_paused" class="form-select">
                        <option value="0" <?= $userDetails['is_paused'] == 0 ? 'selected' : '' ?>>No</option>
                        <option value="1" <?= $userDetails['is_paused'] == 1 ? 'selected' : '' ?>>Yes</option>
                    </select>
                </div>

                <!-- Emails -->
                <div class="mb-3">
                    <label for="mails" class="form-label">Emails:</label>
                    <input type="text" id="mails" name="mails" class="form-control" value="<?= htmlspecialchars($userDetails['mails']) ?>" required>
                    <small class="form-text text-muted">Separe múltiples correos con una coma ",".</small>
                </div>
                
                <!-- Services -->
                <div class="mb-3">
                    <label for="services" class="form-label">Services:</label>
                    <input type="text" id="services" name="services" class="form-control" readonly value="<?= htmlspecialchars($userDetails['services']) ?>" required>
                    <ul id="serviceList" class="list-group mt-2">
                        <li class="list-group-item" data-service="Netflix">Netflix</li>
                        <li class="list-group-item" data-service="Disney">Disney</li>
                        <li class="list-group-item" data-service="Prime Video">Prime Video</li>
                    </ul>
                    <small class="form-text text-muted">Haga doble clic en un servicio para agregarlo o quitarlo.</small>
                </div>
                
                <!-- Authenticator Account -->
                <div class="mb-3">
                    <label for="authenticator_account_alias" class="form-label">Asignar Cuenta Authenticator:</label>
                    <select id="authenticator_account_alias" name="authenticator_account_alias" class="form-select">
                        <option value="None">-- No asignar --</option>
                        <?php foreach ($authenticator_accounts as $account_alias): ?>
                            <option value="<?= $account_alias; ?>" <?= ($userDetails['authenticator_account_alias'] === $account_alias) ? 'selected' : ''; ?>>
                                <?= $account_alias; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <input type="submit" value="Update User" class="btn btn-primary">
            </form>
        </div>
    </div>
</div>

<script>
    // Tu script de servicios se mantiene igual
    document.querySelectorAll('#serviceList .list-group-item').forEach(item => {
        item.addEventListener('dblclick', function() {
            const service = this.getAttribute('data-service');
            const input = document.getElementById('services');
            let services = input.value ? input.value.split(',').map(s => s.trim()).filter(Boolean) : [];
            const index = services.indexOf(service);
            if (index > -1) {
                services.splice(index, 1);
            } else {
                services.push(service);
            }
            input.value = services.join(',');
        });
    });
</script>

<?php
// Incluye el footer.
include_once("footer.php");
?>
