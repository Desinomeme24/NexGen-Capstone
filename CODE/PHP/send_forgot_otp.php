<?php
require_once 'config.php';
require_once 'resend_config.php';

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

/**
 * Send email through Resend's HTTPS REST API.
 *
 * @return array{success: bool, email_id?: string, error?: string}
 */
function sendResendEmail(
    string $recipientEmail,
    string $recipientName,
    string $subject,
    string $textContent
): array {
    if (!function_exists('curl_init')) {
        return [
            'success' => false,
            'error' => 'PHP cURL extension is not enabled.'
        ];
    }

    if (
        !defined('RESEND_API_KEY') ||
        RESEND_API_KEY === '' ||
        RESEND_API_KEY === 'PASTE_YOUR_RESEND_API_KEY_HERE'
    ) {
        return [
            'success' => false,
            'error' => 'Resend API key has not been configured.'
        ];
    }

    $from = RESEND_FROM_NAME . ' <' . RESEND_FROM_EMAIL . '>';

    $payload = [
        'from' => $from,
        'to' => [$recipientEmail],
        'subject' => $subject,
        'text' => $textContent
    ];

    $jsonPayload = json_encode(
        $payload,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );

    if ($jsonPayload === false) {
        return [
            'success' => false,
            'error' => 'Unable to encode Resend request.'
        ];
    }

    $ch = curl_init('https://api.resend.com/emails');

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . RESEND_API_KEY,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => $jsonPayload
    ]);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($response === false) {
        return [
            'success' => false,
            'error' => 'HTTPS connection error: ' . $curlError
        ];
    }

    $decoded = json_decode($response, true);

    if ($httpStatus < 200 || $httpStatus >= 300) {
        $apiMessage = '';

        if (is_array($decoded)) {
            $apiMessage = (string) (
                $decoded['message']
                ?? $decoded['error']['message']
                ?? $decoded['name']
                ?? ''
            );
        }

        return [
            'success' => false,
            'error' => 'Resend API returned HTTP ' . $httpStatus .
                ($apiMessage !== '' ? ': ' . $apiMessage : '')
        ];
    }

    return [
        'success' => true,
        'email_id' => is_array($decoded)
            ? (string)($decoded['id'] ?? '')
            : ''
    ];
}


/*
|--------------------------------------------------------------------------
| VALIDATE REQUEST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    forgotPasswordRedirect('Invalid request.');
}

$email = trim($_POST['email'] ?? '');

if ($email === '') {
    forgotPasswordRedirect('Please enter your email address.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    forgotPasswordRedirect('Please enter a valid email address.');
}


/*
|--------------------------------------------------------------------------
| FIND USER
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    'SELECT id, email, full_name
     FROM users
     WHERE email = ?
     LIMIT 1'
);

if (!$stmt) {
    error_log('Forgot password user lookup prepare error: ' . $conn->error);
    forgotPasswordRedirect(
        'Unable to process your request right now. Please try again.'
    );
}

$stmt->bind_param('s', $email);
$stmt->execute();

$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    forgotPasswordRedirect('No account found with that email.');
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
| SEND THROUGH RESEND HTTPS API
|--------------------------------------------------------------------------
*/

$sendResult = sendResendEmail(
    (string)$user['email'],
    $recipientName,
    'NexGen Password Reset OTP',
    $emailBody
);

if (!$sendResult['success']) {
    error_log(
        'Forgot password Resend error for user ID ' .
        (int)$user['id'] .
        ': ' .
        ($sendResult['error'] ?? 'Unknown Resend error')
    );

    forgotPasswordRedirect(
        "We couldn't send the OTP right now. Please try again in a moment."
    );
}


/*
|--------------------------------------------------------------------------
| STORE OTP ONLY AFTER RESEND ACCEPTS THE EMAIL
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