<?php
require_once 'config.php';
require_once 'mailer_config.php';

date_default_timezone_set('Asia/Manila');

function forgotPasswordRedirect(
    string $message,
    string $type = 'error',
    string $page = 'forgot_password.php'
): void {
    $_SESSION[$type] = $message;
    header('Location: /NexGen/CODE/PHP/' . $page);
    exit();
}

/*
|--------------------------------------------------------------------------
| VALIDATE REQUEST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    forgotPasswordRedirect('Invalid request.');
}

$identifier = trim($_POST['identifier'] ?? '');

if ($identifier === '') {
    forgotPasswordRedirect('Please enter your username or email address.');
}

/*
|--------------------------------------------------------------------------
| FIND USER
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    'SELECT id, email, full_name
     FROM users
     WHERE username = ? OR email = ?
     LIMIT 1'
);

if (!$stmt) {
    error_log('Forgot password user lookup prepare error: ' . $conn->error);
    forgotPasswordRedirect(
        'Unable to process your request right now. Please try again.'
    );
}

$stmt->bind_param('ss', $identifier, $identifier);
$stmt->execute();

$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    forgotPasswordRedirect('No account found with that username or email.');
}


/*
|--------------------------------------------------------------------------
| GENERATE OTP
|--------------------------------------------------------------------------
*/

$otpCode = str_pad(
    (string) random_int(0, 999999),
    6,
    '0',
    STR_PAD_LEFT
);

$otpExpiresAt = date(
    'Y-m-d H:i:s',
    strtotime('+10 minutes')
);

$recipientName = trim((string)($user['full_name'] ?? ''));

if ($recipientName === '') {
    $recipientName = 'NexGen User';
}

$emailBody =
    "Hello {$recipientName},\n\n" .
    "Your NexGen password reset OTP is: {$otpCode}\n\n" .
    "This OTP will expire in 10 minutes.\n\n" .
    "If you did not request a password reset, you can ignore this email.";


/*
|--------------------------------------------------------------------------
| SEND THROUGH PHPMAILER
|--------------------------------------------------------------------------
*/

try {
    $mail = createMailer();
    $mail->addAddress($user['email'], $recipientName);
    $mail->Subject = 'NexGen Password Reset OTP';
    $mail->Body    = $emailBody;

    $mail->send();
} catch (Exception $e) {
    error_log(
        'Forgot PHPMailer error for user ID ' .
        (int)$user['id'] .
        ': ' .
        $e->getMessage()
    );

    forgotPasswordRedirect(
        "We couldn't send the OTP right now. Please try again in a moment."
    );
}


/*
|--------------------------------------------------------------------------
| STORE OTP ONLY AFTER EMAIL SENT SUCCESSFULLY
|--------------------------------------------------------------------------
*/

$updateStmt = $conn->prepare(
    "UPDATE users
     SET otp_code = ?,
         otp_expires_at = ?,
         otp_preference = 'email'
     WHERE id = ?"
);

if (!$updateStmt) {
    error_log(
        'Forgot password OTP update prepare error: ' . $conn->error
    );

    forgotPasswordRedirect(
        'The email was sent, but the reset request could not be saved. Please request a new OTP.'
    );
}

$userId = (int)$user['id'];

$updateStmt->bind_param(
    'ssi',
    $otpCode,
    $otpExpiresAt,
    $userId
);

if (!$updateStmt->execute()) {
    error_log(
        'Forgot password OTP update error: ' .
        $updateStmt->error
    );

    $updateStmt->close();

    forgotPasswordRedirect(
        'The email was sent, but the reset request could not be saved. Please request a new OTP.'
    );
}

$updateStmt->close();

$_SESSION['reset_email'] = (string)$user['email'];
$_SESSION['success'] = 'OTP sent to your email address.';

header('Location: /NexGen/CODE/PHP/reset_password.php');
exit();