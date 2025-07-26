<?php
session_start();
date_default_timezone_set('UTC');

include_once("settings/database.php");
include_once("settings/recaptcha.php");
include_once("settings/site.php");

if ($site_version == "2") {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mail = $_POST['mail'];
    $recaptcha_response = $_POST['g-recaptcha-response'];

    $recaptcha_url = 'https://www.google.com/recaptcha/api/siteverify';
    $recaptcha_data = array(
        'secret' => $SECRET_KEY,
        'response' => $recaptcha_response
    );

    $options = array(
        'http' => array(
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($recaptcha_data)
        )
    );

    $context = stream_context_create($options);
    $recaptcha_result = file_get_contents($recaptcha_url, false, $context);
    $recaptcha_result_data = json_decode($recaptcha_result);

    if (!$recaptcha_result_data->success) {
        header('Location: index.php?error=invalidcaptcha');
        exit;
    }

    $conn = getDBConnection();

    if (!$conn) {
        die("Connection failed: " . mysqli_connect_error());
    }

    $results = [];

    $stmt = $conn->prepare("SELECT url, date, service FROM codes WHERE mail = ? AND used != 1 ORDER BY id DESC");
    $stmt->bind_param("s", $mail);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($url, $date, $service);
        while ($stmt->fetch()) {
            $results[] = ['url' => $url, 'date' => $date];
        }
    } else {
        header('Location: index.php?error=nomails');
        exit;
    }

    $stmt->close();
    mysqli_close($conn);
}
?>


<?php include_once("header.php"); ?>

<div class="container">
        <div class="row">
            <div class="col-md-12">
                <img src="img/logo.png" alt="logo" class="img-fluid" />
            </div>
        </div>
    </div>

<body>
    <main>
        <div class="container" data-bs-theme="dark">
            <div class="row">
                <div class="col-md-6 offset-md-3">
                    <div class="card">
                        <div class="card-header">
                            <h3><?php echo htmlspecialchars($langHandler->getTranslation("DASHBOARD", "MAIL_LIST"), ENT_QUOTES, 'UTF-8'); ?></h3>
                        </div>
                        <?php
                        if (isset($_SESSION['last_request']) && (time() - $_SESSION['last_request']) < 60) {
                            $waitSeconds = 60 - (time() - $_SESSION['last_request']);
                            echo "<br>";
                            echo "<button onclick='location.reload();'>" . htmlspecialchars($langHandler->getTranslation("DASHBOARD", "REFRESH_BUTTON"), ENT_QUOTES, 'UTF-8') . "</button>";
                            echo "<br>";
                            die(htmlspecialchars($langHandler->getTranslation("DASHBOARD", "PLEASE_WAIT"), ENT_QUOTES, 'UTF-8') . " " . htmlspecialchars($waitSeconds, ENT_QUOTES, 'UTF-8') . " " . htmlspecialchars($langHandler->getTranslation("DASHBOARD", "PLEASE_WAIT_ALT"), ENT_QUOTES, 'UTF-8'));
                        }

                        $_SESSION['last_request'] = time();
                        ?>

                        <?php
                        $counter = 0;
                        foreach ($results as $result) :
                            $btnId = "unique_" . $counter++;
                            $url = $result['url'];
                            $received_date = new DateTime($result['date']);
                            $minutesAgo = getMinutesAgo(new DateTime(), $received_date);
                            echo "<div class='card' style='border: 1px solid white;'>";
                            echo "<div class='card-body' style='display: flex; flex-direction: column;'>";
                            echo "<card-header><h4><img src='/img/services/" . htmlspecialchars($service) . ".png' alt='" . htmlspecialchars($service) . "' style='width:40px; height:40px; margin-right:10px;'>" . htmlspecialchars($mail) . "</h4></card-header>";
                            echo '<div style="text-align: center; padding: 20px;">';
                            
                            echo "<button id='" . $btnId . "' onclick='showCode(\"" . htmlspecialchars($result['url'], ENT_QUOTES, 'UTF-8') . "\", \"" . htmlspecialchars($btnId, ENT_QUOTES, 'UTF-8') . "\", \"" . htmlspecialchars($mail, ENT_QUOTES, 'UTF-8') . "\"); this.style.display=\"none\";'>" . htmlspecialchars($langHandler->getTranslation("DASHBOARD", "READ_BUTTON"), ENT_QUOTES, 'UTF-8') . "</button>";
                            echo "</div>";
                            if (strpos($url, "https://") === 0) :
                                echo "<div id='url_" . $btnId . "' style='display:none;'>";
                                echo "<a href='" . htmlspecialchars($url) . "' target='_blank'>";
                                echo "<button style='padding: 10px 20px; background-color: #e50914; color: #fff; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 1em; font-weight: 700; text-shadow: 2px 2px 4px #000;'>";
                                echo "<button style='...'>" . htmlspecialchars($langHandler->getTranslation("DASHBOARD", "ACCOUNT_LOGIN_BUTTON"), ENT_QUOTES, 'UTF-8') . "</button>";
                                echo "</button>";
                                echo "</a>";
                                echo "</div>";
                            else :
                                echo "<div id='url_" . $btnId . "' style='display:none;'>";
                                echo htmlspecialchars($langHandler->getTranslation("DASHBOARD", "CODE_TEXT"), ENT_QUOTES, 'UTF-8') . "<br>";
                                echo "<span style='font-size: 1.5em; font-weight: bold;'>" . htmlspecialchars($url) . "</span>";
                                echo "</div>";
                            endif;
                            echo "<br>";
                            echo "<p>" . htmlspecialchars($langHandler->getTranslation("DASHBOARD", "MINUTES_AGO"), ENT_QUOTES, 'UTF-8') . " " . htmlspecialchars($minutesAgo, ENT_QUOTES, 'UTF-8') . " " . htmlspecialchars($langHandler->getTranslation("DASHBOARD", "MINUTES_AGO_ALT"), ENT_QUOTES, 'UTF-8') . "</p>";
                            echo "</div>";
                            echo "</div><br>";
                        endforeach;
                        ?>
                        <div style="text-align: center; padding: 20px;">
                            <button onclick="location.href='index.php'"><?php echo htmlspecialchars($langHandler->getTranslation("DASHBOARD", "BACK_BUTTON"), ENT_QUOTES, 'UTF-8'); ?></button>
                        </div>
                        </div>
                        </div>
                        </div>
                        </div>
                        </main>
                        
                        <script>
                            function showCode(url, btnId, mail) {
                                var divId = 'url_' + btnId;
                                var div = document.getElementById(divId);
                                if (div) {
                                    div.style.display = 'block';
                                } else {
                                    console.error('Div not found for id:', divId);
                                }
                                document.getElementById(btnId).style.display = 'none';
                                fetch('dashboard/used_code.php', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/x-www-form-urlencoded',
                                    },
                                    body: 'url=' + encodeURIComponent(url) + '&mail=' + encodeURIComponent(mail)
                                });
                            }
                        </script>

    <?php
    function getMinutesAgo($now, $received_date)
    {
        $now = new DateTime("now", new DateTimeZone("UTC"));
        $received_date->setTimezone(new DateTimeZone("UTC"));
        
        $interval = $now->diff($received_date);
        $total_minutes = $interval->days * 24 * 60 + $interval->h * 60 + $interval->i;
        return $total_minutes;
    }
    ?>
</body>