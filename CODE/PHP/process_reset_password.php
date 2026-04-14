<?php
session_start();
include 'config.php';

date_default_timezone_set('Asia/Manila');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $_SESSION['error'] = "Invalid request.";
    header("Location: /NexGen/CODE/PHP/forgot_password.php");
    exit();
}

$email = trim($_POST['email'] ?? '');
$otp_code = trim($_POST['otp_code'] ?? '');
$new_password = trim($_POST['new_password'] ?? '');
$confirm_new_password = trim($_POST['confirm_new_password'] ?? '');

if (empty($email) || empty($otp_code) || empty($new_password) || empty($confirm_new_password)) {
    $_SESSION['error'] = "Please complete all fields.";
    header("Location: /NexGen/CODE/PHP/reset_password.php");
    exit();
}

if ($new_password !== $confirm_new_password) {
    $_SESSION['error'] = "Passwords do not match.";
    header("Location: /NexGen/CODE/PHP/reset_password.php");
    exit();
}

$sql = "SELECT id, otp_code, otp_expires_at FROM users WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    $_SESSION['error'] = "No account found with that email.";
    header("Location: /NexGen/CODE/PHP/reset_password.php");
    exit();
}

if (empty($user['otp_code']) || empty($user['otp_expires_at'])) {
    $_SESSION['error'] = "No valid OTP found. Please request a new one.";
    header("Location: /NexGen/CODE/PHP/forgot_password.php");
    exit();
}

if ($otp_code !== $user['otp_code']) {
    $_SESSION['error'] = "Invalid OTP code.";
    header("Location: /NexGen/CODE/PHP/reset_password.php");
    exit();
}

if (strtotime($user['otp_expires_at']) < time()) {
    $_SESSION['error'] = "OTP has expired. Please request a new one.";
    header("Location: /NexGen/CODE/PHP/forgot_password.php");
    exit();
}

$new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);

$update_sql = "UPDATE users SET password = ?, otp_code = NULL, otp_expires_at = NULL WHERE email = ?";
$update_stmt = $conn->prepare($update_sql);
$update_stmt->bind_param("ss", $new_password_hash, $email);

if ($update_stmt->execute()) {
    unset($_SESSION['reset_email']);
    $_SESSION['success'] = "Password reset successful. You may now log in.";
    header("Location: /NexGen/CODE/PHP/index.php");
    exit();
} else {
    $_SESSION['error'] = "Failed to reset password.";
    header("Location: /NexGen/CODE/PHP/reset_password.php");
    exit();
}
?>