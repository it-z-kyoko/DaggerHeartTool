<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['auth']['userID']) || empty($_SESSION['auth']['username'])) {
    header("Location: /Login/login.php"); // ggf. /Login/signin.php bei dir
    exit;
}