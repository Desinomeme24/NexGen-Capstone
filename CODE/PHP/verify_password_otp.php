<?php
session_start();
include 'config.php';

date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user_id'])) {
    header("Location: /NexGen/CODE/PHP/index.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $_SESSION['error'] = "Invalid request.";
    header("Location: /NexGen/CODE/PHP/settings.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$otp_code = trim($_POST['otp_code'] ?? '');

if ($otp_code === '') {
    $_SESSION['error'] = "Please enter the OTP code.";
    header("Location: /NexGen/CODE/PHP/settings.php");
    exit();
}

if (!isset($_SESSION['pending_new_password']) || $_SESSION['pending_new_password'] === '') {
    $_SESSION['error'] = "No pending password change found. Please request a new OTP.";
    header("Location: /NexGen/CODE/PHP/settings.php");
    exit();
}

$sql = "SELECT otp_code, otp_expires_at FROM users WHERE id = ? LIMIT 1";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    $_SESSION['error'] = "Database error: failed to prepare OTP lookup.";
    header("Location: /NexGen/CODE/PHP/settings.php");
    exit();
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user || empty($user['otp_code']) || empty($user['otp_expires_at'])) {
    $_SESSION['error'] = "No valid OTP found. Please request a new OTP.";
    header("Location: /NexGen/CODE/PHP/settings.php");
    exit();
}

if ($otp_code !== $user['otp_code']) {
    $_SESSION['error'] = "Invalid OTP code.";
    header("Location: /NexGen/CODE/PHP/settings.php");
    exit();
}

if (strtotime($user['otp_expires_at']) < time()) {
    $_SESSION['error'] = "OTP has expired. Please request a new one.";
    header("Location: /NexGen/CODE/PHP/settings.php");
    exit();
}

$new_password_hash = $_SESSION['pending_new_password'];

$update_sql = "UPDATE users SET password = ?, otp_code = NULL, otp_expires_at = NULL WHERE id = ?";
$update_stmt = $conn->prepare($update_sql);

if (!$update_stmt) {
    $_SESSION['error'] = "Database error: failed to prepare password update.";
    header("Location: /NexGen/CODE/PHP/settings.php");
    exit();
}

$update_stmt->bind_param("si", $new_password_hash, $user_id);

if ($update_stmt->execute()) {
    unset($_SESSION['pending_new_password']);
    unset($_SESSION['otp_new_password_plain']);
    unset($_SESSION['otp_confirm_password_plain']);
    $_SESSION['success'] = "Password changed successfully.";
} else {
    $_SESSION['error'] = "Failed to update password.";
}

$update_stmt->close();

header("Location: /NexGen/CODE/PHP/settings.php");
exit();
?>