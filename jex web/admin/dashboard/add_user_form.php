<?php
// Inicia o reanuda la sesión existente.
session_start();

// --- Verificación de Seguridad: Usuario Autenticado y Administrador ---
// Verifica si el usuario ha iniciado sesión Y si está marcado como administrador en la sesión.
// Si no cumple los requisitos, redirige a la página de inicio de sesión del administrador.
// Assumes '../index.php' is the correct relative path to the admin login page.
// If your admin login page uses a clean URL handled by .htaccess (e.g., https://jex.lat/admin/),
// consider changing the redirection line to: header('Location: /admin/');
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: ../index.php'); // Redirect to the admin login page
    exit; // Crucial to stop script execution after a redirect
}

// --- Include Essential Files ---
// Include necessary configuration and header files. Ensure these files (especialmente database.php)
// are secure and ideally located outside the web-accessible root directory.
include_once("../../settings/database.php"); // Contains database connection logic (CRITICAL for security)
include_once("header.php"); // Contains the HTML header (should start the HTML document structure)

// --- Conexión a la Base de Datos ---
// Get the database connection.
$conn = getDBConnection();

// --- Basic Database Connection Error Handling ---
// Check if the database connection was successful.
if ($conn === false) {
    // In production, log the error and show a generic message.
    error_log("Database connection error: " . mysqli_connect_error());
    die("Internal server error. Please try again later.");
}

// --- Fetch Authenticator Accounts for Dropdown ---
$authenticator_accounts = []; // Array to store authenticator accounts
$fetchAccountsQuery = "SELECT account_alias FROM shared_authenticator_accounts ORDER BY account_alias ASC";
$accountsResult = $conn->query($fetchAccountsQuery);

if ($accountsResult) {
    while ($row = $accountsResult->fetch_assoc()) {
        $authenticator_accounts[] = htmlspecialchars($row['account_alias']); // Escape alias for display
    }
    $accountsResult->free(); // Free result set
} else {
    error_log("Error fetching authenticator accounts: " . $conn->error);
    // You might want to display an error message on the page if this fails
}


// --- Initialize Variables for Messages ---
$error_message = ''; // Variable to store error messages for display
$success_message = ''; // Variable to store success messages for display

// --- Handle POST Request (Form Submission) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get data from the form. Use null coalescing operator (?? '') for robustness.
    $alias = trim($_POST['alias'] ?? ''); // trim() to remove leading/trailing whitespace
    $pin = trim($_POST['pin'] ?? ''); // trim()
    // is_paused is a checkbox, default to 0 if not set or not '1'.
    $is_paused = (isset($_POST['is_paused']) && $_POST['is_paused'] == '1') ? 1 : 0;
    $mails = trim($_POST['mails'] ?? ''); // trim()
    $services = trim($_POST['services'] ?? ''); // trim()
    // Get the selected authenticator account alias. Use null if "None" was selected.
    $authenticator_account_alias = trim($_POST['authenticator_account_alias'] ?? '');
    if ($authenticator_account_alias === 'None') {
        $authenticator_account_alias = null;
    }


  // --- Server-Side Input Validation ---
    // Validate required fields and PIN format.
    if (empty($alias)) {
        $error_message = "El alias es requerido.";
    } elseif (empty($pin)) {
         $error_message = "El PIN es requerido.";
    } elseif (!ctype_alnum($pin) || strlen($pin) < 4) { // Cambio aquí: ctype_digit por ctype_alnum
        // Valida que el PIN contenga solo caracteres alfanuméricos y tenga al menos 4 caracteres.
        $error_message = "Formato de PIN inválido. Debe contener solo letras y números, y tener al menos 4 caracteres."; // Mensaje actualizado
    } elseif (empty($mails)) { // Asumiendo que el campo de correos es requerido
         $error_message = "El campo de correos es requerido.";
    } elseif (empty($services)) { // Asumiendo que el campo de servicios es requerido
         $error_message = "El campo de servicios es requerido.";
    }
    // Add more specific validation for email format or service names if needed.

    if (empty($error_message)) { // Proceed only if no validation errors
        // --- Hashing Seguro del PIN ---
        // **CRÍTICO:** Hashea la contraseña (PIN) proporcionada por el usuario
        // antes de almacenarla en la base de datos. NUNCA guardes contraseñas en texto plano.
        $pin_hasheado = password_hash($pin, PASSWORD_DEFAULT);
        // password_hash() usa un algoritmo fuerte y genera un salt automáticamente.

        // --- Check if the Alias already exists ---
        // Prepara una consulta segura para verificar si el alias ya está en uso.
        $checkAliasQuery = "SELECT id FROM users WHERE alias = ?"; // Selecciona solo el ID por eficiencia
        $checkAliasStmt = $conn->prepare($checkAliasQuery);

        // Manejo de error al preparar la sentencia de verificación de alias.
        if ($checkAliasStmt === false) {
            error_log("Error preparing alias check query: " . $conn->error);
            $error_message = "Internal error preparing alias check.";
        } else {
            // Liga el parámetro del alias a la sentencia preparada. 's' indica tipo string.
            $checkAliasStmt->bind_param("s", $alias);
            // Ejecuta la consulta.
            if (!$checkAliasStmt->execute()) {
                 error_log("Error executing alias check query: " . $checkAliasStmt->error);
                 $error_message = "Internal error during alias check.";
            } else {
                // Obtiene el resultado de la consulta.
                $aliasResult = $checkAliasStmt->get_result();
                // Verifica si se encontró alguna fila (si el alias ya existe).
                if ($aliasResult->num_rows > 0) {
                    // Alias ya existe, establece un mensaje de error.
                    $error_message = "Alias already exists.";
                }
                $aliasResult->free(); // Libera el conjunto de resultados
            }
            $checkAliasStmt->close(); // Cierra la sentencia de verificación de alias
        }

        // Procede a verificar el PIN solo si no hubo errores de alias.
        if (empty($error_message)) {
            // --- Check if the PIN already exists ---
            // Prepara una consulta segura para verificar si el PIN (hash) ya está en uso.
            // Nota: Verificar si un hash ya existe no es tan directo como con texto plano.
            // Esta consulta verifica si el hash *exacto* ya existe. Si dos contraseñas diferentes
            // generan el mismo hash (muy improbable pero posible en teoría) o si alguien intenta
            // usar un hash existente como PIN, esta verificación lo detectará.
            $checkPinQuery = "SELECT id FROM users WHERE pin = ?"; // Selecciona solo el ID por eficiencia
            $checkPinStmt = $conn->prepare($checkPinQuery);

            // Manejo de error al preparar la sentencia de verificación de PIN.
            if ($checkPinStmt === false) {
                error_log("Error preparing PIN check query: " . $conn->error);
                $error_message = "Internal error preparing PIN check.";
            } else {
                // Liga el hash del PIN a la sentencia preparada. 's' indica tipo string.
                $checkPinStmt->bind_param("s", $pin_hasheado); // Usa el PIN hasheado aquí
                // Ejecuta la consulta.
                if (!$checkPinStmt->execute()) {
                    error_log("Error executing PIN check query: " . $checkPinStmt->error);
                    $error_message = "Internal error during PIN check.";
                } else {
                    // Obtiene el resultado de la consulta.
                    $pinResult = $checkPinStmt->get_result();
                    // Verifica si se encontró alguna fila (si el PIN/hash ya existe).
                    if ($pinResult->num_rows > 0) {
                        // PIN (hash) ya existe, establece un mensaje de error.
                        $error_message = "PIN already exists.";
                    }
                     $pinResult->free(); // Libera el conjunto de resultados
                }
                $checkPinStmt->close(); // Cierra la sentencia de verificación de PIN
            }
        }

        // Procede a insertar el nuevo usuario solo si no hubo errores de alias o PIN.
        if (empty($error_message)) {
            // --- Insert New User ---
            // Prepara la consulta SQL para insertar el nuevo usuario, incluyendo el campo authenticator_account_alias.
            $insertQuery = "INSERT INTO users (alias, pin, is_paused, mails, services, authenticator_account_alias) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insertQuery);

            // Manejo de error al preparar la sentencia de inserción.
            if ($stmt === false) {
                error_log("Error preparing insert user query: " . $conn->error);
                $error_message = "Internal error preparing user insertion.";
            } else {
                 // Liga los parámetros a la sentencia preparada. 'ssisss' especifica los tipos de datos:
                 // string (alias), string (pin_hasheado), integer (is_paused), string (mails), string (services), string (authenticator_account_alias o null).
                 // Para el último parámetro (authenticator_account_alias), si es null, usamos "s" como tipo y pasamos null.
                $bind_types = "ssiss";
                $bind_params = [$alias, $pin_hasheado, $is_paused, $mails, $services];

                if ($authenticator_account_alias === null) {
                    $bind_types .= "s"; // Use string type even for null
                    $bind_params[] = null; // Pass null value
                } else {
                    $bind_types .= "s";
                    $bind_params[] = $authenticator_account_alias;
                }

                // mysqli_stmt_bind_param requiere parámetros por referencia.
                // Usamos call_user_func_array para manejar esto dinámicamente.
                $bindParamsRefs = [];
                 foreach ($bind_params as $key => $value) {
                     $bindParamsRefs[$key] = &$bind_params[$key];
                 }
                 array_unshift($bindParamsRefs, $bind_types); // Add the type string as the first parameter

                if (!call_user_func_array([$stmt, 'bind_param'], $bindParamsRefs)) {
                    error_log("Error binding parameters for insert: " . $stmt->error);
                    $error_message = "Internal error binding insert parameters.";
                } elseif ($stmt->execute()) {
                    // Inserción exitosa.
                    $success_message = "New user added successfully.";
                    // Optional: Redirect to users list after success
                    // header('Location: users.php'); // Usa ruta relativa para URLs limpias
                    // exit;
                } else {
                    // Error durante la ejecución de la inserción.
                    error_log("Error executing user insert: " . $stmt->error);
                    // En producción, evita mostrar $stmt->error directamente al usuario.
                    $error_message = "Error adding new user.";
                }
                $stmt->close(); // Cierra la sentencia de inserción
            }
        }
    }
}

// --- Close Database Connection ---
// Cierra la conexión a la base de datos al final de la ejecución del script.
// Esto es importante para liberar los recursos de la base de datos.
if ($conn) {
    $conn->close();
}

// --- HTML Output ---
// Asumiendo que header.php ya ha abierto los tags <html>, <head>, y <body>.
// Si no es así, necesitas ajustar header.php y footer.php para que manejen
// la estructura principal del documento HTML.
?>
    <div class="container mt-5">
        <?php
        // Muestra mensajes de error o éxito si existen.
        // Usa htmlspecialchars para escapar el contenido de los mensajes y prevenir XSS.
        if (!empty($error_message)) {
            echo '<div class="alert alert-danger" role="alert">' . htmlspecialchars($error_message) . '</div>';
        }
        if (!empty($success_message)) {
            echo '<div class="alert alert-success" role="alert">' . htmlspecialchars($success_message) . '</div>';
        }
        ?>
        <div class="card">
            <div class="card-header">
                Add New User
            </div>
            <div class="card-body">
                <form action="" method="post">

                    <div class="mb-3">
                        <label for="alias" class="form-label">Alias:</label>
                        <input type="text" id="alias" name="alias" class="form-control" required>
                        <small style="color: red;">Alias can be phone number or mail to easy identify the user</small>
                    </div>

                    <div class="mb-3">
                        <label for="pin" class="form-label">PIN:</label>
                        <input type="text" id="pin" name="pin" class="form-control" pattern="[a-zA-Z0-9]{4,}" title="Debe contener solo letras y números, y tener al menos 4 caracteres." required inputmode="text" oninput="validatePin()">
                        <div id="pinError" style="color: red; display: none;">Debe contener solo letras y números, y tener al menos 4 caracteres.</div>
                        <script>
                            function validatePin() {
                                const pinInput = document.getElementById('pin');
                                const pinError = document.getElementById('pinError');
                                const pinPattern = /^[a-zA-Z0-9]{4,}$/;

                                // Opcional: si quieres limpiar caracteres no alfanuméricos mientras escribe:
                                // pinInput.value = pinInput.value.replace(/[^a-zA-Z0-9]/g, '');

                                // Lógica de validación del lado del cliente.
                                if (pinInput.value.length > 0 && !pinPattern.test(pinInput.value)) {
                                    pinInput.setCustomValidity('Debe contener solo letras y números, y tener al menos 4 caracteres.');
                                    pinError.textContent = 'Debe contener solo letras y números, y tener al menos 4 caracteres.';
                                    pinError.style.display = 'block';
                                } else if (pinInput.value.length > 0 && pinInput.value.length < 4) {
                                    pinInput.setCustomValidity('El PIN debe tener al menos 4 caracteres.');
                                    pinError.textContent = 'El PIN debe tener al menos 4 caracteres.';
                                    pinError.style.display = 'block';
                                } else {
                                    pinInput.setCustomValidity('');
                                    pinError.style.display = 'none';
                                }
                                // Nota: La validación en el lado del servidor es CRUCIAL para la seguridad.
                            }
                         </script>
                    </div>
                    <small style="color: red;">El PIN puede ser una combinación de letras y números (p.ej. pass123, user007) y se usará para iniciar sesión.</small>

                    <div class="mb-3">
                        <label for="is_paused" class="form-label">Is Paused:</label>
                         <input type="checkbox" id="is_paused" name="is_paused" value="1" class="form-check-input">
                        <small style="color: red;">Note: If checked, the user will not be able to access the site.</small>
                    </div>


                    <div class="mb-3">
                        <label for="mails" class="form-label">Emails:</label>
                        <input type="text" id="mails" name="mails" class="form-control" required> <small style="color: red;">Use "," to add multiple emails to the user</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Select Services:</label>
                        <ul id="serviceList" class="list-group">
                            <li class="list-group-item" data-service="Netflix">Netflix</li>
                            <li class="list-group-item" data-service="Disney">Disney</li>
                            <li class="list-group-item" data-service="Prime Video">Prime Video</li>
                            </ul>
                        <small style="color: red;">Double click on the service to add/remove it from the user's services.</small>
                    </div>

                    <div class="mb-3">
                        <label for="services" class="form-label">Services:</label>
                        <input type="text" id="services" name="services" class="form-control" style="background-color: #e9ecef;color: #6c757d; cursor: not-allowed;" readonly required> </div>

                    <script>
                        document.querySelectorAll('#serviceList .list-group-item').forEach(item => {
                            item.addEventListener('dblclick', function() {
                                const service = this.getAttribute('data-service');
                                const input = document.getElementById('services');
                                // Divide los servicios actuales por coma, filtrando cadenas vacías.
                                let services = input.value ? input.value.split(',').filter(s => s.trim() !== '') : [];

                                // Verifica si el servicio ya está en la lista.
                                const index = services.indexOf(service);

                                if (index > -1) {
                                    // Si el servicio existe, lo elimina.
                                    services.splice(index, 1);
                                } else {
                                    // Si el servicio no existe, lo añade.
                                    services.push(service);
                                }

                                // Une los servicios de nuevo con coma y actualiza el campo de entrada.
                                input.value = services.join(',');
                                // Optional: Activa/desactiva el estado 'required' basado en si hay servicios seleccionados
                                input.required = services.length === 0;
                            });
                        });
                         // Optional: Resalta los servicios seleccionados en la lista al cargar la página (menos relevante aquí ya que el campo inicia vacío).
                         // document.addEventListener('DOMContentLoaded', function() {
                         //    const input = document.getElementById('services');
                         //    const currentServices = input.value ? input.value.split(',').filter(s => s.trim() !== '') : [];
                         //    document.querySelectorAll('#serviceList .list-group-item').forEach(item => {
                         //        const service = item.getAttribute('data-service');
                         //        if (currentServices.includes(service)) {
                         //            item.style.fontWeight = 'bold'; // Ejemplo de resaltado
                         //            item.style.backgroundColor = '#e9ecef'; // Ejemplo de resaltado
                         //        }
                         //    });
                         // });
                    </script>

                    <div class="mb-3">
                        <label for="authenticator_account_alias" class="form-label">Asignar Cuenta Authenticator:</label>
                        <select id="authenticator_account_alias" name="authenticator_account_alias" class="form-select">
                            <option value="None">-- No asignar --</option>
                            <?php foreach ($authenticator_accounts as $account_alias): ?>
                                <option value="<?php echo $account_alias; ?>"><?php echo $account_alias; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">Selecciona la cuenta de Authenticator de Prime Video que se asignará a este usuario.</small>
                    </div>


                    <br><br>
                    <input type="submit" value="Add User" class="btn btn-primary">
                </form>
            </div> </div> </div> <?php
// Incluye el pie de página. Asegúrate de que footer.php cierre correctamente los tags HTML
// que fueron abiertos (como </body> y </html> si no se cerraron en header.php).
include_once("footer.php");
?>
