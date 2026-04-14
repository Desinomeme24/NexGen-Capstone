<?php
session_start();
include 'config.php';
require_once 'mailer_config.php';

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
$new_password = trim($_POST['new_password'] ?? '');
$confirm_new_password = trim($_POST['confirm_new_password'] ?? '');

/* keep the typed passwords temporarily */
$_SESSION['otp_new_password_plain'] = $new_password;
$_SESSION['otp_confirm_password_plain'] = $confirm_new_password;

if ($new_password === '' || $confirm_new_password === '') {
    $_SESSION['error'] = "Please complete all password fields.";
    header("Location: /NexGen/CODE/PHP/settings.php");
    exit();
}

if ($new_password !== $confirm_new_password) {
    $_SESSION['error'] = "New passwords do not match.";
    header("Location: /NexGen/CODE/PHP/settings.php");
    exit();
}

$sql = "SELECT email, full_name FROM users WHERE id = ? LIMIT 1";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    $_SESSION['error'] = "Database error: failed to prepare user lookup.";
    header("Location: /NexGen/CODE/PHP/settings.php");
    exit();
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    $_SESSION['error'] = "User account not found.";
    header("Location: /NexGen/CODE/PHP/settings.php");
    exit();
}

$otp_code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$otp_expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
$hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);

$_SESSION['pending_new_password'] = $hashed_new_password;

$update_sql = "UPDATE users SET otp_code = ?, otp_expires_at = ?, otp_preference = 'email' WHERE id = ?";
$update_stmt = $conn->prepare($update_sql);

if (!$update_stmt) {
    $_SESSION['error'] = "Database error: failed to save OTP.";
    header("Location: /NexGen/CODE/PHP/settings.php");
    exit();
}

$update_stmt->bind_param("ssi", $otp_code, $otp_expires_at, $user_id);

if (!$update_stmt->execute()) {
    $update_stmt->close();
    $_SESSION['error'] = "Failed to save OTP request.";
    header("Location: /NexGen/CODE/PHP/settings.php");
    exit();
}
$update_stmt->close();

try {
    $mail = createMailer();
    $mail->addAddress($user['email'], $user['full_name']);
    $mail->Subject = 'NextGen Password Change OTP';
    $mail->Body    = "Hello " . $user['full_name'] . ",\n\n"
                   . "Your OTP code is: " . $otp_code . "\n"
                   . "This OTP will expire in 10 minutes.\n\n"
                   . "If you did not request this, please ignore this email.";

    $mail->send();
    $_SESSION['success'] = "OTP sent to your email address.";
} catch (Exception $e) {
    $_SESSION['error'] = "Failed to send OTP email. Mailer error: " . $e->getMessage();
}

header("Location: /NexGen/CODE/PHP/settings.php");
exit();
?>