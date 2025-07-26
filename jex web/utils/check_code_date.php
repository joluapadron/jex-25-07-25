<?php
date_default_timezone_set('UTC');

include_once("../settings/database.php");
include_once("save_logs.php");
include_once("../settings/site.php");


if (!isset($_GET['key']) || $_GET['key'] !== $secret_key) {
    echo "Acceso denegado.";
    exit;
}

$conn = getDBConnection();

try {
    $sql = "SELECT id, date FROM codes WHERE used = 0";
    $result = $conn->query($sql);

    $now = new DateTime("now", new DateTimeZone("UTC"));

    $updatedCodesCount = 0;

    while ($row = $result->fetch_assoc()) { 
        $codeDate = new DateTime($row['date'], new DateTimeZone("UTC"));

        $diff = $now->diff($codeDate);
        $minutes = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;

        if ($minutes > 15) {
            $updateSql = "UPDATE codes SET used = 1 WHERE id = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("i", $row['id']);
            $updateStmt->execute();
            $updatedCodesCount++;
        }
    }

    if ($updatedCodesCount > 0) {
        saveLog("SYSTEM", "Removed $updatedCodesCount code(s) due to expiration");
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

$conn->close();
?>