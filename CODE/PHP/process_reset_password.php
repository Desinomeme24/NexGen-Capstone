<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/otp_security.php';

$fpPortal = nxNormalizeLoginPortal(
    $_POST['portal']
        ?? $_GET['portal']
        ?? $_SESSION['fp_portal']
        ?? $_SESSION['login_portal']
        ?? 'client'
);
$_SESSION['fp_portal'] = $fpPortal;
$forgotStartPage = 'forgot_password.php?portal=' . rawurlencode($fpPortal);
$successLoginPage = $fpPortal === 'admin' ? 'admin_login.php' : 'index.php';

date_default_timezone_set('Asia/Manila');

/* AJAX callers (the in-modal forgot-password wizard on index.php) send this
   header and get a JSON reply instead of the classic flash+redirect. */
$isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';

function prpRespond(bool $success, string $message, string $type, string $page, array $ajaxExtra = []): void
{
    global $isAjax;

    if ($isAjax) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(array_merge(['success' => $success, 'message' => $message], $ajaxExtra));
        exit();
    }

    $_SESSION[$type] = $message;
    header('Location: ' . nxAppUrl($page));
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    prpRespond(false, "Invalid request.", 'error', $forgotStartPage, ['restart' => true]);
}

$userId = isset($_SESSION['fp_selected_user_id']) ? (int) $_SESSION['fp_selected_user_id'] : 0;

// Preserve a neutral failure path when no account was selected.
if ($userId <= 0) {
    $user = [
        'id' => $userId,
        'otp_code' => '',
        'otp_expires_at' => date('Y-m-d H:i:s', time() - 3600)
    ];
} else {
    $sql = "SELECT id, role, otp_code, otp_expires_at FROM users WHERE id = ? AND account_status = 'active' LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log('Password reset account lookup prepare failed: ' . $conn->error);
        prpRespond(false, "Unable to process your request right now.", 'error', $forgotStartPage, ['restart' => true]);
    }
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        unset($_SESSION['fp_selected_user_id'], $_SESSION['fp_email']);
        prpRespond(false, "Account not found. Please start again.", 'error', $forgotStartPage, ['restart' => true]);
    }

    if (!nxRoleMatchesLoginPortal((string)($user['role'] ?? ''), $fpPortal)) {
        unset($_SESSION['fp_selected_user_id'], $_SESSION['fp_candidates'], $_SESSION['fp_email']);
        prpRespond(false, "Account not found. Please start again.", 'error', $forgotStartPage, ['restart' => true]);
    }
}

$otp_code = trim($_POST['otp_code'] ?? '');
$new_password = trim($_POST['new_password'] ?? '');
$confirm_new_password = trim($_POST['confirm_new_password'] ?? '');

if (empty($otp_code) || empty($new_password) || empty($confirm_new_password)) {
    prpRespond(false, "Please complete all fields.", 'error', 'reset_password.php');
}

if (!preg_match('/^[0-9]{6}$/D', $otp_code)) {
    prpRespond(false, "Please enter the complete 6-digit OTP.", 'error', 'reset_password.php');
}

if ($new_password !== $confirm_new_password) {
    prpRespond(false, "Passwords do not match.", 'error', 'reset_password.php');
}

if (!isStrongPassword($new_password)) {
    prpRespond(
        false,
        "Use 12 to 64 characters with uppercase, lowercase, number, and special character.",
        'error',
        'reset_password.php'
    );
}

$sql = "SELECT id, role, otp_code, otp_expires_at FROM users WHERE id = ? AND account_status = 'active' LIMIT 1";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log('Password reset verification lookup prepare failed: ' . $conn->error);
    prpRespond(false, "Unable to process your request right now.", 'error', 'reset_password.php');
}
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    unset($_SESSION['fp_selected_user_id'], $_SESSION['fp_email']);
    prpRespond(false, "Account not found. Please start again.", 'error', $forgotStartPage, ['restart' => true]);
}

if (!nxRoleMatchesLoginPortal((string)($user['role'] ?? ''), $fpPortal)) {
    unset($_SESSION['fp_selected_user_id'], $_SESSION['fp_candidates'], $_SESSION['fp_email']);
    prpRespond(false, "Account not found. Please start again.", 'error', $forgotStartPage, ['restart' => true]);
}

if (empty($user['otp_code']) || empty($user['otp_expires_at'])) {
    prpRespond(false, "No valid OTP found. Please request a new one.", 'error', 'reset_password.php');
}

if (!nxVerifyOtp($otp_code, (string)$user['otp_code'])) {
    prpRespond(false, "Invalid OTP code.", 'error', 'reset_password.php');
}

if (strtotime($user['otp_expires_at']) < time()) {
    prpRespond(false, "OTP has expired. Please request a new one.", 'error', 'reset_password.php');
}

try {
    $new_password_hash = nxHashPassword($new_password);
} catch (Throwable $e) {
    error_log('Password reset hashing failed: ' . $e->getMessage());
    prpRespond(false, "Unable to secure the new password right now.", 'error', 'reset_password.php');
}

$update_sql = "UPDATE users SET password = ?, otp_code = NULL, otp_expires_at = NULL WHERE id = ?";
$update_stmt = $conn->prepare($update_sql);
if (!$update_stmt) {
    error_log('Password reset update prepare failed: ' . $conn->error);
    prpRespond(false, "Failed to reset password.", 'error', 'reset_password.php');
}
$update_stmt->bind_param("si", $new_password_hash, $userId);
$passwordUpdated = $update_stmt->execute();
$update_stmt->close();

if ($passwordUpdated) {
    $_SESSION['login_portal'] = $fpPortal;
    unset($_SESSION['fp_selected_user_id'], $_SESSION['fp_candidates'], $_SESSION['fp_email'], $_SESSION['fp_portal']);
    prpRespond(true, "Password reset successful. You may now log in.", 'success', $successLoginPage);
} else {
    prpRespond(false, "Failed to reset password.", 'error', 'reset_password.php');
}
?>
