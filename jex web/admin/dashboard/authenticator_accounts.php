<?php
// Configura la zona horaria a UTC para consistencia.
date_default_timezone_set('UTC');

// Inicia o reanuda la sesión existente.
session_start();

// --- Verificación de Seguridad: Usuario Autenticado y Es Admin ---
// Verifica si el usuario ha iniciado sesión Y si está marcado como administrador en la sesión.
// Si no cumple los requisitos, redirige a la página de inicio de sesión del administrador.
// CORREGIDO: Cambiada la comparación estricta de is_admin con 1 a comparación estricta con true.
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: /admin/'); // Redirige a la página de login del admin
    exit;
}

// --- **IMPORTANTE:** Elimina o comenta las líneas de depuración var_dump si las añadiste ---
// echo "<pre>";
// var_dump($_SESSION);
// echo "</pre>";
// exit;
// --- Fin de las líneas de depuración ---


// --- Inclusión de Archivos Esenciales ---
// Incluye archivos necesarios. Asegúrate de que las rutas sean correctas.
// CORREGIDO: Cambiado 'admin_header.php' a 'header.php' y 'admin_footer.php' a 'footer.php'.
// ELIMINADO: Inclusión de 'admin_sidebar.php' ya que no existe en tu estructura.
include_once("../../settings/database.php"); // Contiene la lógica de conexión a la BD
include_once("header.php"); // Cabecera HTML del panel admin (en la misma carpeta)
// include_once("admin_sidebar.php"); // ELIMINADO: No existe este archivo
// include_once("../../languages/lang_handler.php"); // Incluye si necesitas traducciones aquí
// include_once("../../settings/site.php"); // Incluye si necesitas configuraciones del sitio

// --- Conexión a la Base de Datos ---
$conn = getDBConnection();
if ($conn === false) {
    error_log("Error de conexión a la base de datos en admin/authenticator_accounts.php: " . mysqli_connect_error());
    die("Error interno del servidor al conectar a la base de datos.");
}

// --- Manejo de Acciones (Añadir, Editar, Eliminar) ---

$message = ''; // Variable para mensajes de éxito o error

// Lógica para Añadir Cuenta
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add_account') {
    // Validar y sanear los datos de entrada
    $account_alias = trim($_POST['account_alias'] ?? ''); // Usaremos el correo como alias
    $secret_key = trim($_POST['secret_key'] ?? '');
    $service_name = trim($_POST['service_name'] ?? 'Prime Video'); // Valor por defecto
    $notes = trim($_POST['notes'] ?? '');

    // Validación básica
    if (empty($account_alias) || empty($secret_key)) {
        $message = '<div class="alert alert-danger">El alias (correo) y la clave secreta no pueden estar vacíos.</div>';
    } else {
        // Consulta para insertar la nueva cuenta de authenticator compartida
        // Usamos sentencias preparadas para seguridad.
        $stmt = $conn->prepare("INSERT INTO shared_authenticator_accounts (account_alias, secret_key, service_name, notes) VALUES (?, ?, ?, ?)");
        if ($stmt === false) {
             error_log("Error al preparar INSERT en shared_authenticator_accounts: " . $conn->error);
             $message = '<div class="alert alert-danger">Error interno al preparar la inserción.</div>';
        } else {
            // Ligar parámetros
            if (!$stmt->bind_param("ssss", $account_alias, $secret_key, $service_name, $notes)) {
                error_log("Error al ligar parámetros para INSERT en shared_authenticator_accounts: " . $stmt->error);
                $message = '<div class="alert alert-danger">Error interno al ligar parámetros.</div>';
            } else {
                // Ejecutar la consulta
                if ($stmt->execute()) {
                    $message = '<div class="alert alert-success">Cuenta de Authenticator añadida correctamente.</div>';
                    // Limpiar los campos del formulario después de añadir con éxito
                    $_POST = array(); // Limpiar POST para que el formulario no muestre los datos anteriores
                } else {
                    // Manejar error específico de clave duplicada (account_alias)
                    if ($conn->errno == 1062) { // Código de error para entrada duplicada en MySQL
                        $message = '<div class="alert alert-warning">Ya existe una cuenta de Authenticator con este alias (correo).</div>';
                    } else {
                        error_log("Error al ejecutar INSERT en shared_authenticator_accounts: " . $stmt->error);
                        $message = '<div class="alert alert-danger">Error al añadir la cuenta de Authenticator. Por favor, inténtalo de nuevo.</div>';
                    }
                }
            }
            $stmt->close(); // Cierra el statement
        }
    }
}

// Lógica para Eliminar Cuenta (manejar vía GET o POST con ID)
// Usaremos GET para simplificar, pero POST es más seguro para acciones de eliminación.
// Si usas GET, asegúrate de añadir una confirmación en el frontend.
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $account_id = $_GET['id']; // ID de la cuenta a eliminar

    // Validación básica del ID (asegura que sea un número entero)
    if (!filter_var($account_id, FILTER_VALIDATE_INT)) {
        $message = '<div class="alert alert-danger">ID de cuenta no válido.</div>';
    } else {
        // Consulta para eliminar la cuenta de authenticator compartida
        // Usamos sentencias preparadas para seguridad.
        $stmt = $conn->prepare("DELETE FROM shared_authenticator_accounts WHERE id = ?");
         if ($stmt === false) {
             error_log("Error al preparar DELETE en shared_authenticator_accounts: " . $conn->error);
             $message = '<div class="alert alert-danger">Error interno al preparar la eliminación.</div>';
        } else {
            // Ligar parámetro (el ID)
            if (!$stmt->bind_param("i", $account_id)) { // "i" para entero
                 error_log("Error al ligar parámetro para DELETE en shared_authenticator_accounts: " . $stmt->error);
                 $message = '<div class="alert alert-danger">Error interno al ligar parámetro.</div>';
            } else {
                // Ejecutar la consulta
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        $message = '<div class="alert alert-success">Cuenta de Authenticator eliminada correctamente.</div>';
                    } else {
                        $message = '<div class="alert alert-warning">No se encontró la cuenta de Authenticator con ese ID.</div>';
                    }
                } else {
                    error_log("Error al ejecutar DELETE en shared_authenticator_accounts: " . $stmt->error);
                    $message = '<div class="alert alert-danger">Error al eliminar la cuenta de Authenticator. Por favor, inténtalo de nuevo.</div>';
                }
            }
            $stmt->close(); // Cierra el statement
        }
    }
}


// Lógica para Editar Cuenta (manejar vía GET para mostrar formulario, POST para guardar cambios)
// Implementaremos un formulario básico en la misma página usando un parámetro GET 'edit_id'.
$edit_account = null; // Variable para almacenar los datos de la cuenta si estamos editando

if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
     $edit_id = $_GET['id'];

     // Validación básica del ID
     if (filter_var($edit_id, FILTER_VALIDATE_INT)) {
         // Consulta para obtener los datos de la cuenta a editar
         $stmt = $conn->prepare("SELECT id, account_alias, secret_key, service_name, notes FROM shared_authenticator_accounts WHERE id = ? LIMIT 1");
         if ($stmt === false) {
             error_log("Error al preparar SELECT para editar en shared_authenticator_accounts: " . $conn->error);
             $message = '<div class="alert alert-danger">Error interno al preparar la consulta de edición.</div>';
         } else {
             if (!$stmt->bind_param("i", $edit_id)) {
                 error_log("Error al ligar parámetro para SELECT de edición: " . $stmt->error);
                 $message = '<div class="alert alert-danger">Error interno al ligar parámetro.</div>';
             } else {
                 if ($stmt->execute()) {
                     $result = $stmt->get_result();
                     if ($result->num_rows > 0) {
                         $edit_account = $result->fetch_assoc(); // Obtiene los datos de la cuenta
                     } else {
                         $message = '<div class="alert alert-warning">No se encontró la cuenta de Authenticator para editar.</div>';
                     }
                     $result->free();
                 } else {
                     error_log("Error al ejecutar SELECT para editar en shared_authenticator_accounts: " . $stmt->error);
                     $message = '<div class="alert alert-danger">Error al obtener datos de la cuenta para editar.</div>';
                 }
             }
             $stmt->close();
         }
     } else {
         $message = '<div class="alert alert-danger">ID de cuenta no válido para edición.</div>';
     }
}

// Lógica para Guardar Cambios de Edición (cuando se envía el formulario de edición)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_account') {
    // Validar y sanear los datos de entrada
    $account_id = $_POST['account_id'] ?? ''; // ID de la cuenta que se está actualizando
    $account_alias = trim($_POST['account_alias'] ?? ''); // Usaremos el correo como alias
    $secret_key = trim($_POST['secret_key'] ?? '');
    $service_name = trim($_POST['service_name'] ?? 'Prime Video'); // Valor por defecto
    $notes = trim($_POST['notes'] ?? '');

    // Validación básica
    if (empty($account_id) || !filter_var($account_id, FILTER_VALIDATE_INT) || empty($account_alias) || empty($secret_key)) {
        $message = '<div class="alert alert-danger">Datos de actualización incompletos o no válidos.</div>';
    } else {
        // Consulta para actualizar la cuenta de authenticator compartida
        // Usamos sentencias preparadas para seguridad.
        $stmt = $conn->prepare("UPDATE shared_authenticator_accounts SET account_alias = ?, secret_key = ?, service_name = ?, notes = ? WHERE id = ?");
        if ($stmt === false) {
             error_log("Error al preparar UPDATE en shared_authenticator_accounts: " . $conn->error);
             $message = '<div class="alert alert-danger">Error interno al preparar la actualización.</div>';
        } else {
            // Ligar parámetros
            if (!$stmt->bind_param("ssssi", $account_alias, $secret_key, $service_name, $notes, $account_id)) { // "ssssi" para 4 strings y 1 entero
                error_log("Error al ligar parámetros para UPDATE en shared_authenticator_accounts: " . $stmt->error);
                $message = '<div class="alert alert-danger">Error interno al ligar parámetros.</div>';
            } else {
                // Ejecutar la consulta
                if ($stmt->execute()) {
                     if ($stmt->affected_rows > 0) {
                        $message = '<div class="alert alert-success">Cuenta de Authenticator actualizada correctamente.</div>';
                    } else {
                         // Esto puede pasar si los datos enviados son idénticos a los existentes
                        $message = '<div class="alert alert-info">No se realizaron cambios en la cuenta de Authenticator.</div>';
                    }
                } else {
                     // Manejar error específico de clave duplicada (account_alias)
                    if ($conn->errno == 1062) { // Código de error para entrada duplicada en MySQL
                        $message = '<div class="alert alert-warning">Ya existe una cuenta de Authenticator con este alias (correo).</div>';
                    } else {
                        error_log("Error al ejecutar UPDATE en shared_authenticator_accounts: " . $stmt->error);
                        $message = '<div class="alert alert-danger">Error al actualizar la cuenta de Authenticator. Por favor, inténtalo de nuevo.</div>';
                    }
                }
            }
            $stmt->close(); // Cierra el statement
        }
    }
}


?>

<div class="admin-content">
    <h2>Administrar Cuentas de Authenticator Compartidas</h2>

    <?php
    // Mostrar mensajes de éxito o error
    echo $message;
    ?>

    <div class="card mb-4">
        <div class="card-header">
            <?php if ($edit_account): ?>
                Editar Cuenta de Authenticator (ID: <?php echo htmlspecialchars($edit_account['id']); ?>)
            <?php else: ?>
                Añadir Nueva Cuenta de Authenticator
            <?php endif; ?>
        </div>
        <div class="card-body">
            <form action="authenticator_accounts.php" method="POST">
                 <?php if ($edit_account): ?>
                     <input type="hidden" name="action" value="update_account">
                     <input type="hidden" name="account_id" value="<?php echo htmlspecialchars($edit_account['id']); ?>">
                 <?php else: ?>
                     <input type="hidden" name="action" value="add_account">
                 <?php endif; ?>

                <div class="mb-3">
                    <label for="account_alias" class="form-label">Alias de Cuenta (Correo de Prime Video):</label>
                    <input type="email" class="form-control" id="account_alias" name="account_alias" value="<?php echo htmlspecialchars($edit_account['account_alias'] ?? ''); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="secret_key" class="form-label">Clave Secreta de Authenticator:</label>
                    <input type="text" class="form-control" id="secret_key" name="secret_key" value="<?php echo htmlspecialchars($edit_account['secret_key'] ?? ''); ?>" required>
                </div>
                 <div class="mb-3">
                    <label for="service_name" class="form-label">Nombre del Servicio:</label>
                    <input type="text" class="form-control" id="service_name" name="service_name" value="<?php echo htmlspecialchars($edit_account['service_name'] ?? 'Prime Video'); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="notes" class="form-label">Notas:</label>
                     <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($edit_account['notes'] ?? ''); ?></textarea>
                </div>

                <?php if ($edit_account): ?>
                    <button type="submit" class="btn btn-primary">Actualizar Cuenta</button>
                    <a href="authenticator_accounts.php" class="btn btn-secondary">Cancelar</a>
                <?php else: ?>
                    <button type="submit" class="btn btn-primary">Añadir Cuenta</button>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            Lista de Cuentas de Authenticator Compartidas
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Alias (Correo)</th>
                            <th>Clave Secreta</th>
                            <th>Servicio</th>
                            <th>Notas</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Consulta para obtener todas las cuentas de authenticator compartidas
                        $sql = "SELECT id, account_alias, secret_key, service_name, notes FROM shared_authenticator_accounts ORDER BY account_alias ASC";
                        $result = $conn->query($sql);

                        if ($result->num_rows > 0) {
                            // Itera sobre los resultados y muestra cada cuenta en una fila de la tabla
                            while ($row = $result->fetch_assoc()) {
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['account_alias']) . "</td>";
                                // No mostrar la clave secreta completa por seguridad
                                echo "<td>" . htmlspecialchars(substr($row['secret_key'], 0, 5)) . "...</td>";
                                echo "<td>" . htmlspecialchars($row['service_name']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['notes']) . "</td>";
                                echo "<td>";
                                // Enlaces para editar y eliminar
                                echo "<a href='authenticator_accounts.php?action=edit&id=" . htmlspecialchars($row['id']) . "' class='btn btn-sm btn-warning me-2'>Editar</a>";
                                // Usar JavaScript para confirmación antes de eliminar (más seguro que solo el enlace GET)
                                echo "<a href='authenticator_accounts.php?action=delete&id=" . htmlspecialchars($row['id']) . "' class='btn btn-sm btn-danger' onclick='return confirm(\"¿Estás seguro de que quieres eliminar esta cuenta de Authenticator?\");'>Eliminar</a>";
                                echo "</td>";
                                echo "</tr>";
                            }
                        } else {
                            // Si no hay cuentas, muestra un mensaje
                            echo "<tr><td colspan='6'>No hay cuentas de Authenticator compartidas configuradas.</td></tr>";
                        }

                        $result->free(); // Libera el conjunto de resultados
                        $conn->close(); // Cierra la conexión a la base de datos

                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div> <?php
// Incluye el pie de página del panel admin
// CORREGIDO: Cambiado 'admin_footer.php' a 'footer.php'.
include_once("footer.php");
?>
