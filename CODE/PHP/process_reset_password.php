<?php
session_start();
include 'config.php';

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
    header('Location: /NexGen/CODE/PHP/' . $page);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    prpRespond(false, "Invalid request.", 'error', 'forgot_password.php', ['restart' => true]);
}

$userId = isset($_SESSION['fp_selected_user_id']) ? (int) $_SESSION['fp_selected_user_id'] : 0;

if ($userId <= 0) {
    prpRespond(false, "Your password reset session has expired. Please start again.", 'error', 'forgot_password.php', ['restart' => true]);
}

$otp_code = trim($_POST['otp_code'] ?? '');
$new_password = trim($_POST['new_password'] ?? '');
$confirm_new_password = trim($_POST['confirm_new_password'] ?? '');

if (empty($otp_code) || empty($new_password) || empty($confirm_new_password)) {
    prpRespond(false, "Please complete all fields.", 'error', 'reset_password.php');
}

if ($new_password !== $confirm_new_password) {
    prpRespond(false, "Passwords do not match.", 'error', 'reset_password.php');
}

$sql = "SELECT id, otp_code, otp_expires_at FROM users WHERE id = ? AND account_status = 'active' LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    unset($_SESSION['fp_selected_user_id'], $_SESSION['fp_email']);
    prpRespond(false, "Account not found. Please start again.", 'error', 'forgot_password.php', ['restart' => true]);
}

if (empty($user['otp_code']) || empty($user['otp_expires_at'])) {
    prpRespond(false, "No valid OTP found. Please request a new one.", 'error', 'reset_password.php');
}

if ($otp_code !== $user['otp_code']) {
    prpRespond(false, "Invalid OTP code.", 'error', 'reset_password.php');
}

if (strtotime($user['otp_expires_at']) < time()) {
    prpRespond(false, "OTP has expired. Please request a new one.", 'error', 'reset_password.php');
}

$new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);

$update_sql = "UPDATE users SET password = ?, otp_code = NULL, otp_expires_at = NULL WHERE id = ?";
$update_stmt = $conn->prepare($update_sql);
$update_stmt->bind_param("si", $new_password_hash, $userId);

if ($update_stmt->execute()) {
    unset($_SESSION['fp_selected_user_id'], $_SESSION['fp_candidates'], $_SESSION['fp_email']);
    prpRespond(true, "Password reset successful. You may now log in.", 'success', 'index.php');
} else {
    prpRespond(false, "Failed to reset password.", 'error', 'reset_password.php');
}
?>
