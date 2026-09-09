<?php
require_once 'config.php';

$isTimeoutLogout = isset($_GET['timeout']) && $_GET['timeout'] === '1';
$isManualLogout = $_SERVER['REQUEST_METHOD'] === 'POST';
$loginPage = nxLoginPathForRole((string)($_SESSION['role'] ?? ''));

if (!$isTimeoutLogout && !$isManualLogout) {
    header('Location: ' . $loginPage);
    exit();
}

/* SECURITY: CSRF validation only for manual logout */
if ($isManualLogout && !validateCsrfToken('logout_form', $_POST['csrf_token'] ?? '')) {
    $_SESSION['error'] = "Invalid or expired logout form token.";
    header('Location: ' . $loginPage);
    exit();
}

/* AUDIT: record the logout before the session is wiped, since user_id,
   username, and role only exist in $_SESSION up to this point. */
$logoutUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$logoutUsername = (string)($_SESSION['username'] ?? 'unknown');
$logoutRole = (string)($_SESSION['role'] ?? 'unknown');
$logoutReason = $isTimeoutLogout ? 'timeout' : 'manual';

if ($logoutUserId !== null) {
    logAuthActivity($conn, $logoutUserId, $logoutUsername, $logoutRole, 'logout', $logoutReason);
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
session_id('');
session_start();

if ($isTimeoutLogout) {
    $_SESSION['error'] = "Your session expired due to 10 minutes of inactivity. Please log in again.";
    $_SESSION['form_type'] = 'login';
} else {
    $_SESSION['success'] = "You have been logged out successfully.";
}

header('Location: ' . $loginPage);
exit();
?>