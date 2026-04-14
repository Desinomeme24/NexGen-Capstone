<?php
session_start();
include 'config.php';
require_once 'mailer_config.php';

date_default_timezone_set('Asia/Manila');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $_SESSION['error'] = "Invalid request.";
    header("Location: /NexGen/CODE/PHP/forgot_password.php");
    exit();
}

$email = trim($_POST['email'] ?? '');

if (empty($email)) {
    $_SESSION['error'] = "Please enter your email address.";
    header("Location: /NexGen/CODE/PHP/forgot_password.php");
    exit();
}

$sql = "SELECT id, email, full_name FROM users WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    $_SESSION['error'] = "No account found with that email.";
    header("Location: /NexGen/CODE/PHP/forgot_password.php");
    exit();
}

$otp_code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$otp_expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));

$update_sql = "UPDATE users SET otp_code = ?, otp_expires_at = ?, otp_preference = 'email' WHERE id = ?";
$update_stmt = $conn->prepare($update_sql);
$update_stmt->bind_param("ssi", $otp_code, $otp_expires_at, $user['id']);
$update_stmt->execute();

$_SESSION['reset_email'] = $email;

try {
    $mail = createMailer();
    $mail->addAddress($user['email'], $user['full_name']);
    $mail->Subject = 'NextGen Password Reset OTP';
    $mail->Body    = "Hello " . $user['full_name'] . ",\n\n"
                   . "Your password reset OTP is: " . $otp_code . "\n"
                   . "This OTP will expire in 10 minutes.\n\n"
                   . "If you did not request this, please ignore this email.";

    $mail->send();

    $_SESSION['success'] = "OTP sent to your email address.";
    header("Location: /NexGen/CODE/PHP/reset_password.php");
    exit();
} catch (Exception $e) {
    $_SESSION['error'] = "Failed to send OTP email. Mailer error: " . $e->getMessage();
    header("Location: /NexGen/CODE/PHP/forgot_password.php");
    exit();
}
?>