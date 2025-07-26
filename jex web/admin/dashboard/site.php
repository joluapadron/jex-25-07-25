<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: ../index.php');
    exit;
}

include_once("header.php");
include_once("../../settings/database.php");

$conn = getDBConnection();

$sql = "SELECT head, footer FROM news WHERE id = 1";
$result = mysqli_query($conn, $sql);

if (!$result) {
    die("Error en la consulta: " . mysqli_error($conn));
}

$news = [];
if (mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    $news['head'] = $row['head'];
    $news['footer'] = $row['footer'];
} else {
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $headContent = $_POST['head'];
    $footerContent = $_POST['footer'];

    mysqli_begin_transaction($conn);

    try {
        $updateSqlHead = "UPDATE news SET head = ? WHERE id = 1";
        $stmt = mysqli_prepare($conn, $updateSqlHead);
        mysqli_stmt_bind_param($stmt, "s", $headContent);
        mysqli_stmt_execute($stmt);

        $updateSqlFooter = "UPDATE news SET footer = ? WHERE id = 1";
        $stmt = mysqli_prepare($conn, $updateSqlFooter);
        mysqli_stmt_bind_param($stmt, "s", $footerContent);
        mysqli_stmt_execute($stmt);
    
        mysqli_commit($conn);
        header("Refresh:0");
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo "<p>Error updating news: " . $e->getMessage() . "</p>";
        }
    }

mysqli_close($conn);
?>

<div class="container mt-4">
    <div class="card">
        <div class="card-body">
            <form method="POST" class="container">
                <div class="row">
                    <div class="col-md-6 offset-md-3">
                        <div class="form-group">
                            <label for="head">Header:</label>
                            <textarea id="head" name="head" class="form-control"><?php echo htmlspecialchars($news['head'] ?? ''); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="footer">Footer:</label>
                            <textarea id="footer" name="footer" class="form-control"><?php echo htmlspecialchars($news['footer'] ?? ''); ?></textarea>
                        </div>
                        <br>
                        <button type="submit" class="btn btn-primary">Update News</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once("footer.php"); ?>