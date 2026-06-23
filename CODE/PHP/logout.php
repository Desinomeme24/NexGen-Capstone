<?php
session_start();
include 'config.php';

$isTimeoutLogout = isset($_GET['timeout']) && $_GET['timeout'] === '1';
$isManualLogout = $_SERVER['REQUEST_METHOD'] === 'POST';

if (!$isTimeoutLogout && !$isManualLogout) {
    header("Location: /NexGen/CODE/PHP/index.php");
    exit();
}

/* SECURITY: CSRF validation only for manual logout */
if ($isManualLogout && !validateCsrfToken('logout_form', $_POST['csrf_token'] ?? '')) {
    $_SESSION['error'] = "Invalid or expired logout form token.";
    header("Location: /NexGen/CODE/PHP/index.php");
    exit();
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();
session_start();

if ($isTimeoutLogout) {
    $_SESSION['error'] = "Your session expired due to 5 minutes of inactivity. Please log in again.";
    $_SESSION['form_type'] = 'login';
} else {
    $_SESSION['success'] = "You have been logged out successfully.";
}

header("Location: /NexGen/CODE/PHP/index.php");
exit();
?>